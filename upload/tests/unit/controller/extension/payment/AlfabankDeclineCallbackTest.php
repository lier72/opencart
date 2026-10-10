<?php

namespace Tests\Unit\Controller\Extension\Payment;

use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AlfabankDeclineCallbackTest extends TestCase
{
    /** @dataProvider orderStatuses */
    public function testDeclineUpdatesOnlyItsAttemptWithoutChangingOrderHistory($status): void
    {
        require_once DIR_APPLICATION . 'controller/extension/payment/alfabank.php';
        require_once DIR_APPLICATION . 'model/extension/payment/alfabank.php';

        $registry = new \Registry();
        $registry->set('config', new \Config());
        $registry->set('response', new \Response());
        $registry->set('log', new class { public function write($message) { throw new \RuntimeException($message); } });
        $request = new \stdClass();
        $request->get = array('mdOrder' => 'declined-attempt');
        $registry->set('request', $request);
        $registry->set('load', new class {
            public function language($route) {}
            public function model($route) {}
        });
        $order = new class($status) {
            public $status;
            public $history = array();
            public function __construct($status) { $this->status = $status; }
            public function getOrder($id) {
                return array('order_id' => (int)$id, 'order_status_id' => $this->status);
            }
            public function addOrderHistory($id, $status, $comment, $notify) {
                $this->status = $status;
                $this->history[] = $status;
            }
        };
        $registry->set('model_checkout_order', $order);
        $db = new class {
            public $queries = array();
            public function escape($value) { return addslashes($value); }
            public function query($sql) { $this->queries[] = $sql; }
        };
        $registry->set('db', $db);
        $registry->set('model_extension_payment_alfabank', new \ModelExtensionPaymentAlfabank($registry));
        $controller = new class($registry) extends \ControllerExtensionPaymentAlfabank {
            protected function initializeGatewayLibrary() {
                $this->method_library = new class {
                    public function _getGatewayOrderStatus($reference) {
                        return json_encode(array(
                            'errorCode' => 0,
                            'orderNumber' => '1001_12345',
                            'orderStatus' => 6,
                            'amount' => 159900,
                            // The queried mdOrder identifies the attempt, even if this field is absent.
                        ));
                    }
                };
            }
        };

        // Repeated or late notifications must not mark an unpaid order paid,
        // reopen a cancelled order, or roll back a paid/fulfilled order.
        $controller->callback();
        $controller->callback();

        $this->assertSame($status, $order->status);
        $this->assertSame(array(), $order->history);
        $this->assertCount(2, $db->queries);
        foreach ($db->queries as $sql) {
            $this->assertStringContainsString('`status_deposited` = 6', $sql);
            $this->assertStringContainsString('`order_amount_deposited` = 0', $sql);
            $this->assertStringContainsString("WHERE `gateway_order_reference` = 'declined-attempt'", $sql);
        }
    }

    public function orderStatuses(): array
    {
        return array(
            'unconfirmed' => array(0),
            'awaiting payment' => array(17),
            'paid through another attempt' => array(14),
            'completed' => array(5),
            'cancelled' => array(9),
        );
    }
}
