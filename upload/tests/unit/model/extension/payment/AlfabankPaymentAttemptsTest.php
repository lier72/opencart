<?php

namespace Tests\Unit\Model\Extension\Payment;

use PHPUnit\Framework\TestCase;

class AlfabankPaymentAttemptsTest extends TestCase
{
    private $db;
    private $model;
    private $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new \Registry();
        $this->db = new \DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
        $this->registry->set('db', $this->db);

        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "alfabank_order`");
        $this->db->query("CREATE TABLE `" . DB_PREFIX . "alfabank_order` (
            `gateway_order_id` int(11) NOT NULL AUTO_INCREMENT,
            `gateway_order_reference` varchar(64) NOT NULL,
            `tx_url` varchar(512) NOT NULL DEFAULT '',
            `order_id` int(11) NOT NULL,
            `order_number` varchar(64) NOT NULL DEFAULT '',
            `currency` varchar(3) NOT NULL DEFAULT '',
            `payment_way` varchar(50) NOT NULL DEFAULT '',
            `payment_system` varchar(50) NOT NULL DEFAULT '',
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
            KEY `idx_order_id` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        require_once(DIR_APPLICATION . 'model/extension/payment/alfabank.php');
        $this->model = new \ModelExtensionPaymentAlfabank($this->registry);
    }

    protected function tearDown(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "alfabank_order`");
        parent::tearDown();
    }

    public function testStoresEveryPaymentAttemptAndUpdatesCallbacksByGatewayReference(): void
    {
        $this->model->storeGatewayOrder($this->attemptData('gateway-attempt-a', '1001_1'));
        $this->model->storeGatewayOrder($this->attemptData('gateway-attempt-b', '1001_2'));

        $attempts = $this->getAttempts();

        $this->assertCount(2, $attempts);
        $this->assertSame('gateway-attempt-a', $attempts[0]['gateway_order_reference']);
        $this->assertSame('gateway-attempt-b', $attempts[1]['gateway_order_reference']);

        $paid_attempt = $this->attemptData('gateway-attempt-b', '1001_2');
        $paid_attempt['tx_url'] = '';
        $paid_attempt['payment_way'] = 'CARD';
        $paid_attempt['payment_system'] = 'MIR';
        $paid_attempt['order_amount_deposited'] = 159900;
        $paid_attempt['status_deposited'] = 2;
        $this->model->storeGatewayOrder($paid_attempt);

        $attempts = $this->getAttempts();

        $this->assertCount(2, $attempts);
        $this->assertSame('https://gateway.example/gateway-attempt-a', $attempts[0]['tx_url']);
        $this->assertSame('0', $attempts[0]['status_deposited']);
        $this->assertSame('https://gateway.example/gateway-attempt-b', $attempts[1]['tx_url']);
        $this->assertSame('CARD', $attempts[1]['payment_way']);
        $this->assertSame('MIR', $attempts[1]['payment_system']);
        $this->assertSame('2', $attempts[1]['status_deposited']);
        $this->assertSame('159900.0000', $attempts[1]['order_amount_deposited']);

        $declined_attempt = $this->attemptData('gateway-attempt-c', '1001_3');
        $declined_attempt['status_deposited'] = 6;
        $this->model->storeGatewayOrder($declined_attempt);

        require_once(DIR_APPLICATION . '../admin/model/extension/module/odoo_connector.php');
        $odoo_model = new \ModelExtensionModuleOdooConnector($this->registry);
        $method = new \ReflectionMethod($odoo_model, 'getAlfabankTransactionData');
        $method->setAccessible(true);
        $odoo_attempt = $method->invoke($odoo_model, 1001);

        $this->assertSame('gateway-attempt-b', $odoo_attempt['gateway_order_reference']);
        $this->assertSame('1001_2', $odoo_attempt['order_number']);
    }

    private function attemptData($gateway_reference, $order_number)
    {
        return array(
            'order_id' => 1001,
            'gateway_order_reference' => $gateway_reference,
            'tx_url' => 'https://gateway.example/' . $gateway_reference,
            'order_number' => $order_number,
            'currency' => '810',
            'order_amount' => 159900,
            'order_amount_deposited' => 0,
            'status_deposited' => 0,
        );
    }

    private function getAttempts()
    {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "alfabank_order`
            WHERE `order_id` = 1001
            ORDER BY `gateway_order_id`")->rows;
    }
}
