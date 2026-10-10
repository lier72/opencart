<?php

/** Normalize a successful gateway snapshot for every local payment writer. */
class AlfabankGatewayState
{
    public static function paidOrderStatus($config, array $order, $captured)
    {
        $current = (int)$order['order_status_id'];
        $expected = (int)round(round((float)$order['total'] * (float)$order['currency_value'], 2) * 100);
        $pending = (int)$config->get('payment_alfabank_order_status_before_id');
        $paid = (int)$config->get('payment_alfabank_order_status_completed_id');
        return $paid > 0 && $expected > 0 && (int)round($captured) === $expected &&
            in_array($current, array(0, $pending), true) ? $paid : $current;
    }

    /**
     * Map a decoded AlfaBank status response to alfabank_order update fields.
     * Shared by catalog and admin writers; this method does not write to the
     * database or change the OpenCart order status.
     *
     * Accepts orderStatus 0..6 and rejects a nonzero errorCode when supplied.
     * Returns an empty array for invalid/error responses so callers can retain
     * the last known financial state instead of replacing it with a poll error.
     *
     * Amounts remain in gateway minor currency units (kopecks for RUB). States
     * 0 and 6 force the deposited amount to zero; otherwise approvedAmount is
     * preferred, with nominal amount used as a fallback only for captured
     * state 2. Missing/invalid amounts are omitted to preserve stored values.
     * The reversal flag follows state 3; the refund flag follows refundedAmount
     * when available, otherwise state 4. The Odoo export flag is not included.
     *
     * @param mixed $response Decoded gateway response; numeric strings are accepted.
     * @return array Database field/value updates, or an empty array if rejected.
     */
    public static function normalize($response)
    {
        if (!is_array($response) ||
            (isset($response['errorCode']) && (string)$response['errorCode'] !== '0') ||
            !isset($response['orderStatus']) ||
            !in_array((string)$response['orderStatus'], array('0', '1', '2', '3', '4', '5', '6'), true)) {
            return array();
        }

        $status = (int)$response['orderStatus'];
        $data = array('status_deposited' => $status, 'status_reversed' => $status === 3 ? 1 : 0);
        $amounts = isset($response['paymentAmountInfo']) && is_array($response['paymentAmountInfo'])
            ? $response['paymentAmountInfo'] : array();

        if (in_array($status, array(0, 6), true)) {
            $data['order_amount_deposited'] = 0.0;
        } elseif (isset($amounts['approvedAmount']) && is_numeric($amounts['approvedAmount']) && $amounts['approvedAmount'] >= 0) {
            $data['order_amount_deposited'] = (float)$amounts['approvedAmount'];
        } elseif ($status === 2 && isset($response['amount']) && is_numeric($response['amount']) && $response['amount'] >= 0) {
            $data['order_amount_deposited'] = (float)$response['amount'];
        }

        if (isset($amounts['refundedAmount']) && is_numeric($amounts['refundedAmount']) && $amounts['refundedAmount'] >= 0) {
            $data['order_amount_refunded'] = (float)$amounts['refundedAmount'];
            $data['status_refunded'] = $data['order_amount_refunded'] > 0 ? 1 : 0;
        } else {
            $data['status_refunded'] = $status === 4 ? 1 : 0;
        }

        return $data;
    }
}
