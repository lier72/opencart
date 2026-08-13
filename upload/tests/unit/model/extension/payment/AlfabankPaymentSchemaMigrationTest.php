<?php

namespace Tests\Unit\Model\Extension\Payment;

use PHPUnit\Framework\TestCase;

class AlfabankPaymentSchemaMigrationTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMigratesLegacyUniqueOrderIndexToNonUniqueLookupIndex(): void
    {
        $db = new \DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
        $registry = new \Registry();
        $registry->set('db', $db);

        $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "alfabank_order`");

        try {
            $db->query("CREATE TABLE `" . DB_PREFIX . "alfabank_order` (
                `gateway_order_id` int(11) NOT NULL AUTO_INCREMENT,
                `gateway_order_reference` varchar(64),
                `order_id` int(11) NOT NULL,
                `status_deposited` tinyint(1) NOT NULL DEFAULT 0,
                `status_reversed` tinyint(1) NOT NULL DEFAULT 0,
                `status_refunded` tinyint(1) NOT NULL DEFAULT 0,
                `status` tinyint(1) NOT NULL DEFAULT 0,
                `date_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY (`gateway_order_id`),
                UNIQUE KEY `unique_gateway_reference` (`gateway_order_reference`),
                UNIQUE KEY `unique_order_id` (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

            $db->query("INSERT INTO `" . DB_PREFIX . "alfabank_order`
                SET `gateway_order_reference` = 'legacy-refund', `order_id` = 9001,
                    `status_deposited` = 1, `status_refunded` = 1, `status` = 1");
            $db->query("INSERT INTO `" . DB_PREFIX . "alfabank_order`
                SET `gateway_order_reference` = 'legacy-reverse', `order_id` = 9002,
                    `status_deposited` = 0, `status_reversed` = 1, `status` = 1");

            require_once(DIR_APPLICATION . '../admin/model/extension/payment/alfabank.php');
            $model = new \ModelExtensionPaymentAlfabank($registry);
            $model->migratePaymentAttemptsSchema();

            $legacy_index = $db->query("SHOW INDEX FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `Key_name` = 'unique_order_id'");
            $order_index = $db->query("SHOW INDEX FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `Key_name` = 'idx_order_id'");
            $export_status_index = $db->query("SHOW INDEX FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `Key_name` = 'idx_odoo_export_status'");
            $payment_way_column = $db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "alfabank_order`
                LIKE 'payment_way'");
            $payment_system_column = $db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "alfabank_order`
                LIKE 'payment_system'");

            $this->assertSame(0, $legacy_index->num_rows);
            $this->assertSame(1, $order_index->num_rows);
            $this->assertSame('1', $order_index->row['Non_unique']);
            $this->assertSame(2, $export_status_index->num_rows);
            $this->assertSame(1, $payment_way_column->num_rows);
            $this->assertSame('', $payment_way_column->row['Default']);
            $this->assertSame(1, $payment_system_column->num_rows);
            $this->assertSame('', $payment_system_column->row['Default']);

            $normalized = $db->query("SELECT `gateway_order_reference`, `status_deposited`, `status`
                FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `gateway_order_reference` IN ('legacy-refund', 'legacy-reverse')
                ORDER BY `gateway_order_reference`")->rows;
            $this->assertSame('4', $normalized[0]['status_deposited']);
            $this->assertSame('0', $normalized[0]['status']);
            $this->assertSame('3', $normalized[1]['status_deposited']);
            $this->assertSame('0', $normalized[1]['status']);

            $db->query("UPDATE `" . DB_PREFIX . "alfabank_order`
                SET `status` = 1 WHERE `gateway_order_reference` = 'legacy-refund'");
            $model->migratePaymentAttemptsSchema();
            $exported = $db->query("SELECT `status` FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `gateway_order_reference` = 'legacy-refund'");
            $this->assertSame('1', $exported->row['status']);

            $model->updateGatewayOrder('legacy-refund', array(
                'status' => 0,
                'status_deposited' => 4,
            ));
            $exported_after_gateway_update = $db->query("SELECT `status` FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `gateway_order_reference` = 'legacy-refund'");
            $this->assertSame('1', $exported_after_gateway_update->row['status']);

            $db->query("INSERT INTO `" . DB_PREFIX . "alfabank_order`
                SET `gateway_order_reference` = 'attempt-a', `order_id` = 1001");
            $db->query("INSERT INTO `" . DB_PREFIX . "alfabank_order`
                SET `gateway_order_reference` = 'attempt-b', `order_id` = 1001");

            $attempts = $db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `order_id` = 1001");
            $this->assertSame('2', $attempts->row['total']);

            $latest_attempt = $model->getGatewayOrder(1001);
            $first_attempt = $model->getGatewayOrder(1001, 'attempt-a');

            $this->assertSame('attempt-b', $latest_attempt['gateway_order_reference']);
            $this->assertSame('attempt-a', $first_attempt['gateway_order_reference']);

            $model->updateGatewayOrder('attempt-a', array('status_deposited' => 2));

            $statuses = $db->query("SELECT `gateway_order_reference`, `status_deposited`
                FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `order_id` = 1001
                ORDER BY `gateway_order_id`")->rows;
            $this->assertSame('2', $statuses[0]['status_deposited']);
            $this->assertSame('0', $statuses[1]['status_deposited']);

            $db->query("UPDATE `" . DB_PREFIX . "alfabank_order`
                SET `status` = 2, `status_deposited` = 2
                WHERE `gateway_order_reference` = 'attempt-b'");
            $model->migratePaymentAttemptsSchema();
            $reopened = $db->query("SELECT `status` FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `gateway_order_reference` = 'attempt-b'");
            $this->assertSame('0', $reopened->row['status']);
        } finally {
            $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "alfabank_order`");
        }
    }
}
