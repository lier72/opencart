<?php

namespace Tests\Unit\Controller\Extension\Payment;

use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AlfabankRefundCallbackTest extends TestCase
{
    /** @dataProvider refundAmounts */
    public function testRefundUsesCumulativeAmounts($captured, $refunded, $expected, $gatewayStatus = 4, $errorCode = 0): void
    {
        require_once DIR_APPLICATION . 'controller/extension/payment/alfabank.php';
        require_once DIR_APPLICATION . 'model/extension/payment/alfabank.php';

        $registry = new \Registry();
        $registry->set('config', new \Config());
        $registry->set('response', new \Response());
        $registry->set('log', new class { public function write($message) { throw new \RuntimeException($message); } });
        $request = new \stdClass();
        $request->get = array('mdOrder' => 'refunded-attempt');
        $registry->set('request', $request);
        $registry->set('load', new class {
            public function language($route) {}
            public function model($route) {}
        });
        $order = new class(14) {
            public $status;
            public $history = array();
            public $comments = array();
            public function __construct($status) { $this->status = $status; }
            public function getOrder($id) {
                return array('order_id' => (int)$id, 'order_status_id' => $this->status);
            }
            public function addOrderHistory($id, $status, $comment, $notify) {
                $this->status = $status;
                $this->history[] = $status;
                $this->comments[] = $comment;
            }
        };
        $registry->set('model_checkout_order', $order);
        $db = new class($order) {
            private $order;
            public function __construct($order) { $this->order = $order; }
            public $queries = array();
            public function escape($value) { return addslashes($value); }
            public function query($sql) {
                $this->queries[] = $sql;
                $found = 0;
                foreach ($this->order->comments as $comment) {
                    if (strpos($sql, "comment = '" . addslashes($comment) . "'") !== false) $found = 1;
                }
                return (object)array('row' => array('acquired' => 1), 'num_rows' => $found);
            }
        };
        $registry->set('db', $db);
        $registry->set('model_extension_payment_alfabank', new \ModelExtensionPaymentAlfabank($registry));
        $controller = new class($registry) extends \ControllerExtensionPaymentAlfabank {
            public $amounts;
            public $gatewayStatus;
            public $errorCode;
            protected function initializeGatewayLibrary() {
                $this->method_library = new class($this->amounts, $this->gatewayStatus, $this->errorCode) {
                    private $amounts;
                    private $gatewayStatus;
                    private $errorCode;
                    public function __construct($amounts, $gatewayStatus, $errorCode) { $this->amounts = $amounts; $this->gatewayStatus = $gatewayStatus; $this->errorCode = $errorCode; }
                    public function _getGatewayOrderStatus($reference) {
                        return json_encode(array(
                            'errorCode' => $this->errorCode,
                            'orderNumber' => '1001_12345',
                            'orderStatus' => $this->gatewayStatus,
                            'paymentAmountInfo' => $this->amounts,
                            'amount' => 159900,
                            // The queried mdOrder identifies the attempt, even if this field is absent.
                        ));
                    }
                };
            }
        };

        $controller->gatewayStatus = $gatewayStatus;
        $controller->errorCode = $errorCode;
        $controller->amounts = array('approvedAmount' => $captured, 'refundedAmount' => $refunded);
        $controller->callback();
        $controller->callback();
        $this->assertSame($expected === null ? array() : array($expected), $order->history);
        $this->assertSame($errorCode ? null : 'OK', $registry->get('response')->getOutput());
    }

    public function refundAmounts(): array
    {
        return array(
            'reversal preserves paid order status' => array(0, 0, 14, 3),
            'gateway error is not applied' => array(10000, 2000, null, 4, 5),
            'partial refund' => array(10000, 2000, 8),
            'full refund mixed numeric types' => array('10000', 10000, 13),
            'full refund after partial capture' => array(5000, '5000', 13),
            'successive partial refunds reach full amount' => array(10000, '10000', 13),
            'missing amount' => array(null, 2000, null),
            'zero refund' => array(10000, 0, null),
            'refund exceeds capture' => array(10000, 11000, null),
        );
    }
}
