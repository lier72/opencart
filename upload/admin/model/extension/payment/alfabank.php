<?php
class ModelExtensionPaymentAlfabank extends Model
{
    public function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alfabank_order` (
            `gateway_order_id` int(11) NOT NULL AUTO_INCREMENT,
            `gateway_order_reference` varchar(64),
            `tx_url` varchar(512),
            `order_id` int(11) NOT NULL,
            `order_number` varchar(64),
            `currency` varchar(3) COMMENT 'ISO 4217 numeric currency code (e.g., 810 for RUB)',
            `payment_way` varchar(50) COMMENT 'Payment method (e.g., ALFAPAY, CARD)',
            `payment_system` varchar(50) COMMENT 'Payment system (e.g., MIR, VISA, MASTERCARD)',
            `order_amount` decimal(15,4) NOT NULL COMMENT 'Order amount',
            `order_amount_deposited` decimal(15,4) NOT NULL COMMENT 'Order deposited amount',
            `order_amount_refunded` decimal(15,4) NOT NULL DEFAULT 0 COMMENT 'Order refunded amount',
            `status_deposited` tinyint(1) NOT NULL DEFAULT 0,
            `status_reversed` tinyint(1) NOT NULL DEFAULT 0,
            `status_refunded` tinyint(1) NOT NULL DEFAULT 0,
            `status` tinyint(1) NOT NULL DEFAULT 0,
            `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            `date_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (`gateway_order_id`),
            UNIQUE KEY `unique_gateway_reference` (`gateway_order_reference`),
            KEY `idx_order_id` (`order_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

        $this->migratePaymentAttemptsSchema();
    }

    /**
     * Convert legacy one-row-per-order installations to one row per gateway transaction.
     */
    public function migratePaymentAttemptsSchema()
    {
        $table = DB_PREFIX . 'alfabank_order';
        $legacy_index = $this->db->query("SHOW INDEX FROM `" . $table . "` WHERE `Key_name` = 'unique_order_id'");

        if ($legacy_index->num_rows) {
            $this->db->query("ALTER TABLE `" . $table . "` DROP INDEX `unique_order_id`");
        }

        $order_index = $this->db->query("SHOW INDEX FROM `" . $table . "` WHERE `Key_name` = 'idx_order_id'");

        if (!$order_index->num_rows) {
            $this->db->query("ALTER TABLE `" . $table . "` ADD INDEX `idx_order_id` (`order_id`)");
        }
    }

    public function deleteTables()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "alfabank_order`");
    }

    public function getGatewayOrder($order_id, $gateway_order_reference = '')
    {
        $sql = "SELECT * FROM `" . DB_PREFIX . "alfabank_order`
                WHERE `order_id` = " . (int)$order_id;

        if ($gateway_order_reference !== '') {
            $sql .= " AND `gateway_order_reference` = '" . $this->db->escape($gateway_order_reference) . "'";
        }

        $sql .= " ORDER BY `gateway_order_id` DESC LIMIT 1";

        $result = $this->db->query($sql);
        return $result->row;
    }

    public function getGatewayOrders($order_id)
    {
        $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "alfabank_order`
            WHERE `order_id` = " . (int)$order_id . "
            ORDER BY `gateway_order_id` DESC");

        return $result->rows;
    }

    public function updateGatewayOrder($gateway_order_reference, $data)
    {
        $sql_data = array();

        if (isset($data['order_amount_deposited'])) {
            $sql_data[] = "`order_amount_deposited` = '" . (float)$data['order_amount_deposited'] . "'";
        }
        if (isset($data['order_amount_refunded'])) {
            $sql_data[] = "`order_amount_refunded` = '" . (float)$data['order_amount_refunded'] . "'";
        }
        if (isset($data['status_deposited'])) {
            $sql_data[] = "`status_deposited` = " . (int)$data['status_deposited'];
        }
        if (isset($data['status_reversed'])) {
            $sql_data[] = "`status_reversed` = " . (int)$data['status_reversed'];
        }
        if (isset($data['status_refunded'])) {
            $sql_data[] = "`status_refunded` = " . (int)$data['status_refunded'];
        }
        if (isset($data['status'])) {
            $sql_data[] = "`status` = " . (int)$data['status'];
        }

        $sql_data[] = "`date_updated` = NOW()";

        $this->db->query("UPDATE `" . DB_PREFIX . "alfabank_order` SET " .
            implode(', ', $sql_data) .
            " WHERE `gateway_order_reference` = '" . $this->db->escape($gateway_order_reference) . "'");
    }
}
