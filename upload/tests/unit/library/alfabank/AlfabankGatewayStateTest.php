<?php
namespace Tests\Unit\Library\Alfabank;
use PHPUnit\Framework\TestCase;

class AlfabankGatewayStateTest extends TestCase
{
    protected function setUp(): void
    {
        require_once DIR_SYSTEM . 'library/alfabank/AlfabankGatewayState.php';
    }

    public function testRefundAndReversalAmountsAndFlagsAreConsistent(): void
    {
        $refund = \AlfabankGatewayState::normalize(array('errorCode' => '0', 'orderStatus' => '4',
            'paymentAmountInfo' => array('approvedAmount' => '10000', 'refundedAmount' => '2000')));
        $this->assertSame(10000.0, $refund['order_amount_deposited']);
        $this->assertSame(2000.0, $refund['order_amount_refunded']);
        $this->assertSame(1, $refund['status_refunded']);
        $this->assertSame(0, $refund['status_reversed']);
        $reverse = \AlfabankGatewayState::normalize(array('orderStatus' => 3,
            'paymentAmountInfo' => array('approvedAmount' => 0, 'refundedAmount' => 0)));
        $this->assertSame(0.0, $reverse['order_amount_deposited']);
        $this->assertSame(1, $reverse['status_reversed']);
        $this->assertSame(0, $reverse['status_refunded']);
    }

    public function testInvalidResponsesCannotReplaceFinancialState(): void
    {
        foreach (array(null, array(), array('orderStatus' => -1), array('orderStatus' => 'broken'),
            array('orderStatus' => 2, 'errorCode' => 5)) as $response) {
            $this->assertSame(array(), \AlfabankGatewayState::normalize($response));
        }
        $held = \AlfabankGatewayState::normalize(array('orderStatus' => 1, 'amount' => 10000));
        $this->assertArrayNotHasKey('order_amount_deposited', $held);
        $declined = \AlfabankGatewayState::normalize(array('orderStatus' => 6, 'amount' => 10000));
        $this->assertSame(0.0, $declined['order_amount_deposited']);
    }

    public function testOnlyMatchingCapturePromotesAnAwaitingOrder(): void
    {
        $config = new \Config();
        $config->set('payment_alfabank_order_status_before_id', 17);
        $config->set('payment_alfabank_order_status_completed_id', 14);
        $order = array('order_status_id' => 17, 'total' => 100, 'currency_value' => 1);
        $this->assertSame(14, \AlfabankGatewayState::paidOrderStatus($config, $order, 10000));
        $this->assertSame(17, \AlfabankGatewayState::paidOrderStatus($config, $order, 5000));
        $this->assertSame(17, \AlfabankGatewayState::paidOrderStatus($config, $order, 11000));
        foreach (array(5, 8, 9, 13) as $current) {
            $order['order_status_id'] = $current;
            $this->assertSame($current, \AlfabankGatewayState::paidOrderStatus($config, $order, 10000));
        }
    }
}
