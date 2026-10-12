<?php

/** Keep gateway diagnostics without credentials, card data, contacts or cookies. */
class AlfabankLogSanitizer
{
    public static function payload($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return '[unstructured payload omitted]';
        }
        $safe = array();
        foreach (array('orderId', 'orderNumber', 'amount', 'currency', 'orderStatus',
            'errorCode', 'errorMessage', 'actionCode', 'paymentWay') as $key) {
            if (isset($value[$key]) && is_scalar($value[$key])) {
                $safe[$key] = $value[$key];
            }
        }
        if (isset($value['paymentAmountInfo']) && is_array($value['paymentAmountInfo'])) {
            foreach (array('paymentState', 'approvedAmount', 'depositedAmount', 'refundedAmount') as $key) {
                if (isset($value['paymentAmountInfo'][$key]) && is_scalar($value['paymentAmountInfo'][$key])) {
                    $safe['paymentAmountInfo'][$key] = $value['paymentAmountInfo'][$key];
                }
            }
        }
        return $safe;
    }

    public static function endpoint($url)
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return '[endpoint omitted]';
        }
        return $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '');
    }
}
