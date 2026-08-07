<?php

namespace Tests\Unit\Model\Extension\Payment;

use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AlfabankUnmappedReconciliationTest extends TestCase
{
    private $db;
    private $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new \DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "alfabank_order`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "odoo_order_map`");
        $this->db->query("CREATE TABLE `" . DB_PREFIX . "alfabank_order` (
            `gateway_order_id` int(11) NOT NULL AUTO_INCREMENT,
            `gateway_order_reference` varchar(64) NOT NULL,
            `order_id` int(11) NOT NULL,
            `order_number` varchar(64) NOT NULL,
            `order_amount` decimal(15,4) NOT NULL DEFAULT 0,
            `order_amount_deposited` decimal(15,4) NOT NULL DEFAULT 0,
            `order_amount_refunded` decimal(15,4) NOT NULL DEFAULT 0,
            `status_deposited` tinyint(1) NOT NULL DEFAULT 0,
            `status_reversed` tinyint(1) NOT NULL DEFAULT 0,
            `status_refunded` tinyint(1) NOT NULL DEFAULT 0,
            `status` tinyint(1) NOT NULL DEFAULT 0,
            `date_added` datetime NOT NULL,
            `date_updated` datetime NOT NULL,
            PRIMARY KEY (`gateway_order_id`),
            UNIQUE KEY `unique_gateway_reference` (`gateway_order_reference`),
            KEY `idx_order_id` (`order_id`),
            KEY `idx_odoo_export_status` (`status`, `gateway_order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $this->db->query("CREATE TABLE `" . DB_PREFIX . "odoo_order_map` (
            `opencart_order_id` int(11) NOT NULL,
            PRIMARY KEY (`opencart_order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $registry = new \Registry();
        $registry->set('db', $this->db);
        $registry->set('config', new \Config());
        $registry->set('log', new AlfabankReconciliationFakeLog());
        require_once(DIR_APPLICATION . '../admin/model/extension/payment/alfabank.php');
        $this->model = new class($registry) extends \ModelExtensionPaymentAlfabank {
            public $responses = array();
            public $calls = array();

            protected function fetchGatewayOrderStatus($gateway_order_reference)
            {
                $this->calls[] = $gateway_order_reference;

                if (!array_key_exists($gateway_order_reference, $this->responses)) {
                    throw new \RuntimeException('Missing fake response for ' . $gateway_order_reference);
                }

                return $this->responses[$gateway_order_reference];
            }
        };
    }

    protected function tearDown(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "alfabank_order`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "odoo_order_map`");
        parent::tearDown();
    }

    public function testIgnoresOnlyFreshlyVerifiedUnpaidAttempts(): void
    {
        $this->insertAttempt(1001, 'registered', 0);
        $this->insertAttempt(1002, 'declined', 6);
        $this->insertAttempt(1003, 'reversed-zero', 3);
        $this->insertAttempt(1004, 'paid', 0);
        $this->insertAttempt(1005, 'uncertain', 5);
        $this->insertAttempt(1006, 'gateway-error', -1);
        $this->insertAttempt(1007, 'held-locally', 1);
        $this->insertAttempt(1008, 'recent', 0, true);
        $this->insertAttempt(1009, 'mapped', 0);
        $this->db->query("UPDATE `" . DB_PREFIX . "alfabank_order`
            SET `date_updated` = NOW()
            WHERE `gateway_order_reference` IN ('registered', 'declined')");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "odoo_order_map`
            SET `opencart_order_id` = 1009");

        $this->model->responses = array(
            'registered' => $this->gatewayResponse(0, 0, 0),
            'declined' => $this->gatewayResponse(6),
            'reversed-zero' => $this->gatewayResponse(3, 0, 0),
            'paid' => $this->gatewayResponse(2, 159900, 0),
            'uncertain' => $this->gatewayResponse(5, 0, 0),
            'gateway-error' => array('errorCode' => 5, 'errorMessage' => 'temporary gateway error'),
        );

        $result = $this->model->reconcileUnmappedPaymentAttempts(100, 7, 24);

        $this->assertSame(6, $result['checked']);
        $this->assertSame(3, $result['ignored_unpaid']);
        $this->assertSame(2, $result['paid_without_order']);
        $this->assertSame(1, $result['unresolved']);
        $this->assertSame(1, $result['errors']);
        $this->assertCount(2, $result['paid_attempts']);
        $this->assertSame(2, (int)$this->getAttempt('registered')['status']);
        $this->assertSame(2, (int)$this->getAttempt('declined')['status']);
        $this->assertSame(2, (int)$this->getAttempt('reversed-zero')['status']);
        $this->assertSame(0, (int)$this->getAttempt('paid')['status']);
        $this->assertSame(2, (int)$this->getAttempt('paid')['status_deposited']);
        $this->assertSame(159900.0, (float)$this->getAttempt('paid')['order_amount_deposited']);
        $this->assertSame(0, (int)$this->getAttempt('uncertain')['status']);
        $this->assertSame(0, (int)$this->getAttempt('gateway-error')['status']);
        $this->assertSame(0, (int)$this->getAttempt('held-locally')['status']);
        $this->assertSame(0, (int)$this->getAttempt('recent')['status']);
        $this->assertSame(0, (int)$this->getAttempt('mapped')['status']);
        $this->assertNotContains('held-locally', $this->model->calls);
        $this->assertNotContains('recent', $this->model->calls);
        $this->assertNotContains('mapped', $this->model->calls);
    }

    public function testLateFinancialStatusReopensAnIgnoredAttempt(): void
    {
        $this->insertAttempt(2001, 'paid-late', 0, false, 2);

        $this->model->updateGatewayOrder('paid-late', array(
            'status_deposited' => 2,
            'order_amount_deposited' => 50000,
            'order_amount_refunded' => 0,
        ));

        $attempt = $this->getAttempt('paid-late');
        $this->assertSame(0, (int)$attempt['status']);
        $this->assertSame(2, (int)$attempt['status_deposited']);
        $this->assertSame(50000.0, (float)$attempt['order_amount_deposited']);
    }

    private function insertAttempt($order_id, $reference, $gateway_status, $recent = false, $export_status = 0): void
    {
        $date = $recent ? 'NOW()' : "DATE_SUB(NOW(), INTERVAL 10 DAY)";
        $this->db->query("INSERT INTO `" . DB_PREFIX . "alfabank_order` SET
            `gateway_order_reference` = '" . $this->db->escape($reference) . "',
            `order_id` = " . (int)$order_id . ",
            `order_number` = '" . (int)$order_id . "_1',
            `order_amount` = 159900,
            `status_deposited` = " . (int)$gateway_status . ",
            `status` = " . (int)$export_status . ",
            `date_added` = " . $date . ",
            `date_updated` = " . $date);
    }

    private function gatewayResponse($status, $approved_amount = null, $refunded_amount = null): array
    {
        $response = array(
            'errorCode' => 0,
            'orderStatus' => $status,
        );

        if ($approved_amount !== null || $refunded_amount !== null) {
            $response['paymentAmountInfo'] = array();
        }
        if ($approved_amount !== null) {
            $response['paymentAmountInfo']['approvedAmount'] = $approved_amount;
        }
        if ($refunded_amount !== null) {
            $response['paymentAmountInfo']['refundedAmount'] = $refunded_amount;
        }

        return $response;
    }

    private function getAttempt($reference): array
    {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "alfabank_order`
            WHERE `gateway_order_reference` = '" . $this->db->escape($reference) . "'")->row;
    }
}

class AlfabankReconciliationFakeLog
{
    public $messages = array();

    public function write($message)
    {
        $this->messages[] = $message;
    }
}
