<?php

class AlfabankRefundStatus
{
    public static function comment($reference, $captured, $refunded)
    {
        return 'AlfaBank: возвращено ' . number_format((float)$refunded / 100, 2, '.', '') .
            ' из ' . number_format((float)$captured / 100, 2, '.', '') . ' (ID: ' . $reference . ')';
    }

    /** Amounts are cumulative gateway amounts in minor currency units. */
    public static function resolve($config, $captured, $refunded)
    {
        if (!is_numeric($captured) || !is_numeric($refunded)) {
            return null;
        }

        $captured = (int)round((float)$captured);
        $refunded = (int)round((float)$refunded);
        if ($captured <= 0 || $refunded <= 0 || $refunded > $captured) {
            return null;
        }

        $full = $refunded === $captured;
        $key = $full ? 'payment_alfabank_order_status_refunded_id'
            : 'payment_alfabank_order_status_partially_refunded_id';
        return (int)$config->get($key) ?: ($full ? 13 : 8);
    }
}
