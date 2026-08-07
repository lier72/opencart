<?php
class ModelExtensionPaymentAlfabank extends Model
{
    const ODOO_EXPORT_PENDING = 0;
    const ODOO_EXPORT_CONFIRMED = 1;
    const ODOO_EXPORT_IGNORED_UNPAID = 2;

    private $gateway_client;

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
            `status_deposited` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'AlfaBank orderStatus code (-1..6)',
            `status_reversed` tinyint(1) NOT NULL DEFAULT 0,
            `status_refunded` tinyint(1) NOT NULL DEFAULT 0,
            `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Odoo export: 0 pending, 1 confirmed, 2 ignored unpaid',
            `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            `date_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (`gateway_order_id`),
            UNIQUE KEY `unique_gateway_reference` (`gateway_order_reference`),
            KEY `idx_order_id` (`order_id`),
            KEY `idx_odoo_export_status` (`status`, `gateway_order_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

        $this->migratePaymentAttemptsSchema();
    }

    /**
     * Migrate legacy payment-attempt indexes and status semantics.
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

        $export_status_index = $this->db->query("SHOW INDEX FROM `" . $table . "` WHERE `Key_name` = 'idx_odoo_export_status'");

        if (!$export_status_index->num_rows) {
            // Legacy status=1 meant locally closed. Normalize those rows to the
            // corresponding gateway state, then reuse status solely as the
            // Odoo payment.transaction existence-confirmation flag.
            $this->db->query("UPDATE `" . $table . "` SET
                `status_deposited` = CASE
                    WHEN `status_refunded` = 1 THEN 4
                    WHEN `status_reversed` = 1 THEN 3
                    ELSE `status_deposited`
                END,
                `status` = 0");
            $this->db->query("ALTER TABLE `" . $table . "`
                ADD INDEX `idx_odoo_export_status` (`status`, `gateway_order_id`)");
        }

        // An ignored attempt may still be paid later. Gateway callbacks update
        // status_deposited, and this makes the transaction eligible again even
        // if an older callback path did not explicitly reopen the export flag.
        $this->db->query("UPDATE `" . $table . "`
            SET `status` = " . self::ODOO_EXPORT_PENDING . "
            WHERE `status` = " . self::ODOO_EXPORT_IGNORED_UNPAID . "
              AND `status_deposited` IN (1, 2, 4)");
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

        if ($this->hasFinancialGatewayData($data)) {
            $sql_data[] = "`status` = IF(`status` = " . self::ODOO_EXPORT_IGNORED_UNPAID . ", " .
                self::ODOO_EXPORT_PENDING . ", `status`)";
        }

        $sql_data[] = "`date_updated` = NOW()";

        $this->db->query("UPDATE `" . DB_PREFIX . "alfabank_order` SET " .
            implode(', ', $sql_data) .
            " WHERE `gateway_order_reference` = '" . $this->db->escape($gateway_order_reference) . "'");
    }

    /**
     * Reconcile old AlfaBank attempts whose OpenCart sale order has not been
     * mapped to Odoo. Only a fresh, definitive unpaid gateway response is
     * terminally ignored. Paid or uncertain attempts remain pending.
     *
     * status semantics: 0=pending, 1=confirmed in Odoo, 2=ignored unpaid.
     */
    public function reconcileUnmappedPaymentAttempts($limit = 50, $grace_days = 7, $recheck_hours = 24)
    {
        $limit = max(1, min(1000, (int)$limit));
        $grace_days = max(1, min(365, (int)$grace_days));
        $recheck_hours = max(1, min(168, (int)$recheck_hours));
        $result = array(
            'checked' => 0,
            'ignored_unpaid' => 0,
            'paid_without_order' => 0,
            'unresolved' => 0,
            'errors' => 0,
            'paid_attempts' => array(),
            'error_messages' => array(),
        );

        $attempts = $this->db->query("SELECT ao.gateway_order_id,
                ao.gateway_order_reference, ao.order_id, ao.order_number,
                ao.status_deposited, ao.order_amount_deposited,
                ao.order_amount_refunded, ao.date_added, ao.date_updated
            FROM `" . DB_PREFIX . "alfabank_order` ao
            LEFT JOIN `" . DB_PREFIX . "odoo_order_map` oom
                ON oom.opencart_order_id = ao.order_id
            WHERE ao.status = " . self::ODOO_EXPORT_PENDING . "
              AND oom.opencart_order_id IS NULL
              AND ao.gateway_order_reference <> ''
              AND ao.order_number <> ''
              AND ao.status_deposited NOT IN (1, 2, 4)
              AND (ao.date_added = '0000-00-00 00:00:00'
                   OR ao.date_added <= DATE_SUB(NOW(), INTERVAL " . $grace_days . " DAY))
              AND (ao.status_deposited IN (0, 6)
                   OR ao.date_updated = '0000-00-00 00:00:00'
                   OR ao.date_updated <= DATE_SUB(NOW(), INTERVAL " . $recheck_hours . " HOUR))
            ORDER BY ao.date_updated ASC, ao.gateway_order_id ASC
            LIMIT " . $limit)->rows;

        foreach ($attempts as $attempt) {
            $gateway_reference = trim((string)$attempt['gateway_order_reference']);
            $context = 'OpenCart order ' . (int)$attempt['order_id'] .
                ', AlfaBank reference ' . $gateway_reference;
            $result['checked']++;

            try {
                $response = $this->fetchGatewayOrderStatus($gateway_reference);
            } catch (Exception $e) {
                $this->markGatewayOrderCheckError($gateway_reference);
                $this->addReconciliationError($result, $context . ': ' . $e->getMessage());
                continue;
            }

            if (!is_array($response)) {
                $response = json_decode((string)$response, true);
            }

            if (!is_array($response)) {
                $this->markGatewayOrderCheckError($gateway_reference);
                $this->addReconciliationError($result, $context . ': invalid JSON returned by AlfaBank.');
                continue;
            }

            if ((isset($response['errorCode']) && (int)$response['errorCode'] !== 0) ||
                !isset($response['orderStatus'])) {
                $this->markGatewayOrderCheckError($gateway_reference);
                $message = isset($response['errorMessage'])
                    ? (string)$response['errorMessage']
                    : 'gateway response did not contain orderStatus.';
                $this->addReconciliationError($result, $context . ': ' . $message);
                continue;
            }

            $gateway_data = $this->getGatewayUpdateData($response);
            $this->updateGatewayOrder($gateway_reference, $gateway_data);

            if ($this->hasFinancialGatewayData($gateway_data)) {
                continue;
            }

            if ($this->isDefinitivelyUnpaid($response, $gateway_data)) {
                $this->markGatewayOrderIgnoredUnpaid($gateway_reference);
                $result['ignored_unpaid']++;
                continue;
            }

            $result['unresolved']++;
        }

        $paid_condition = "ao.status = " . self::ODOO_EXPORT_PENDING . "
            AND oom.opencart_order_id IS NULL
            AND (ao.status_deposited IN (1, 2, 4)
                 OR ao.order_amount_deposited > 0
                 OR ao.order_amount_refunded > 0)";
        $paid_total = $this->db->query("SELECT COUNT(*) AS total
            FROM `" . DB_PREFIX . "alfabank_order` ao
            LEFT JOIN `" . DB_PREFIX . "odoo_order_map` oom
                ON oom.opencart_order_id = ao.order_id
            WHERE " . $paid_condition);
        $result['paid_without_order'] = (int)$paid_total->row['total'];

        if ($result['paid_without_order']) {
            $result['paid_attempts'] = $this->db->query("SELECT ao.order_id,
                    ao.order_number, ao.gateway_order_reference,
                    ao.status_deposited, ao.order_amount_deposited,
                    ao.order_amount_refunded, ao.date_updated
                FROM `" . DB_PREFIX . "alfabank_order` ao
                LEFT JOIN `" . DB_PREFIX . "odoo_order_map` oom
                    ON oom.opencart_order_id = ao.order_id
                WHERE " . $paid_condition . "
                ORDER BY ao.gateway_order_id ASC
                LIMIT " . $limit)->rows;
        }

        return $result;
    }

    protected function fetchGatewayOrderStatus($gateway_order_reference)
    {
        if (!$this->gateway_client) {
            require_once(DIR_SYSTEM . 'library/alfabank/Alfabank.php');
            $this->gateway_client = new Alfabank();
            $this->gateway_client->token = $this->config->get('payment_alfabank_merchantToken');
            $this->gateway_client->login = $this->config->get('payment_alfabank_merchantLogin');
            $this->gateway_client->password = htmlspecialchars_decode(
                (string)$this->config->get('payment_alfabank_merchantPassword'),
                ENT_QUOTES
            );
            $this->gateway_client->mode = $this->config->get('payment_alfabank_mode');
            $this->gateway_client->logging = $this->config->get('payment_alfabank_logging');
            $this->gateway_client->enable_cacert = (bool)$this->config->get('payment_alfabank_enable_cacert');

            if ($this->gateway_client->enable_cacert && file_exists(DIR_SYSTEM . 'library/cacert.cer')) {
                $this->gateway_client->cacert_path = DIR_SYSTEM . 'library/cacert.cer';
            }
        }

        return $this->gateway_client->_getGatewayOrderStatus($gateway_order_reference);
    }

    private function getGatewayUpdateData(array $response)
    {
        $gateway_status = (int)$response['orderStatus'];
        $data = array(
            'status_deposited' => $gateway_status,
            'status_reversed' => $gateway_status === 3 ? 1 : 0,
        );

        if (isset($response['paymentAmountInfo']['approvedAmount'])) {
            $data['order_amount_deposited'] = (float)$response['paymentAmountInfo']['approvedAmount'];
        } elseif ($gateway_status === 2 && isset($response['amount'])) {
            $data['order_amount_deposited'] = (float)$response['amount'];
        }

        if (isset($response['paymentAmountInfo']['refundedAmount'])) {
            $data['order_amount_refunded'] = (float)$response['paymentAmountInfo']['refundedAmount'];
            $data['status_refunded'] = (float)$response['paymentAmountInfo']['refundedAmount'] > 0 ? 1 : 0;
        } else {
            $data['status_refunded'] = $gateway_status === 4 ? 1 : 0;
        }

        return $data;
    }

    private function hasFinancialGatewayData(array $data)
    {
        $gateway_status = isset($data['status_deposited']) ? (int)$data['status_deposited'] : null;

        return in_array($gateway_status, array(1, 2, 4), true) ||
            (isset($data['order_amount_deposited']) && (float)$data['order_amount_deposited'] > 0) ||
            (isset($data['order_amount_refunded']) && (float)$data['order_amount_refunded'] > 0);
    }

    private function isDefinitivelyUnpaid(array $response, array $gateway_data)
    {
        $gateway_status = (int)$gateway_data['status_deposited'];

        if (in_array($gateway_status, array(0, 6), true)) {
            return true;
        }

        return $gateway_status === 3 &&
            isset($response['paymentAmountInfo']['approvedAmount']) &&
            (float)$response['paymentAmountInfo']['approvedAmount'] <= 0 &&
            (!isset($response['paymentAmountInfo']['refundedAmount']) ||
                (float)$response['paymentAmountInfo']['refundedAmount'] <= 0);
    }

    private function markGatewayOrderIgnoredUnpaid($gateway_order_reference)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "alfabank_order`
            SET `status` = " . self::ODOO_EXPORT_IGNORED_UNPAID . ", `date_updated` = NOW()
            WHERE `gateway_order_reference` = '" . $this->db->escape($gateway_order_reference) . "'
              AND `status` = " . self::ODOO_EXPORT_PENDING);
    }

    private function markGatewayOrderCheckError($gateway_order_reference)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "alfabank_order`
            SET `status_deposited` = -1, `date_updated` = NOW()
            WHERE `gateway_order_reference` = '" . $this->db->escape($gateway_order_reference) . "'");
    }

    private function addReconciliationError(array &$result, $message)
    {
        $result['errors']++;
        $result['error_messages'][] = $message;

        if ($this->registry->has('log')) {
            $this->registry->get('log')->write('reconcileUnmappedPaymentAttempts: ' . $message);
        }
    }
}
