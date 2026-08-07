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
                `date_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY (`gateway_order_id`),
                UNIQUE KEY `unique_gateway_reference` (`gateway_order_reference`),
                UNIQUE KEY `unique_order_id` (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

            require_once(DIR_APPLICATION . '../admin/model/extension/payment/alfabank.php');
            $model = new \ModelExtensionPaymentAlfabank($registry);
            $model->migratePaymentAttemptsSchema();

            $legacy_index = $db->query("SHOW INDEX FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `Key_name` = 'unique_order_id'");
            $order_index = $db->query("SHOW INDEX FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `Key_name` = 'idx_order_id'");

            $this->assertSame(0, $legacy_index->num_rows);
            $this->assertSame(1, $order_index->num_rows);
            $this->assertSame('1', $order_index->row['Non_unique']);

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
                ORDER BY `gateway_order_id`")->rows;
            $this->assertSame('2', $statuses[0]['status_deposited']);
            $this->assertSame('0', $statuses[1]['status_deposited']);
        } finally {
            $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "alfabank_order`");
        }
    }
}
