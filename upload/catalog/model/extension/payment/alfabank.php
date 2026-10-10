<?php
class ModelExtensionPaymentAlfabank extends Model
{
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/alfabank');
        $this->load->model('catalog/product');
        $this->load->model('catalog/category');

        // Check customer group restriction
        if ($this->customer->isLogged()) {
            $customer_group_id = $this->customer->getGroupId();
        } else {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }

        $allowed_customer_groups = $this->config->get('payment_alfabank_customer_group_id');

        if ($allowed_customer_groups && is_array($allowed_customer_groups) && !in_array($customer_group_id, $allowed_customer_groups)) {
            return [];
        }

        $min_amount = (float)$this->config->get('payment_alfabank_min_amount');
        $max_amount = (float)$this->config->get('payment_alfabank_max_amount');
        if ($total < $min_amount) {
            return [];
        }
        if ($max_amount > 0 && $total > $max_amount) {
            return [];
        }
        $allowed_categories_json = $this->config->get('payment_alfabank_allowed_categories');
        $allowed_categories = json_decode($allowed_categories_json, true);
        $allowed_categories = is_array($allowed_categories) ? array_map('intval', $allowed_categories) : [];
        $denied_categories_json = $this->config->get('payment_alfabank_denied_categories');
        $denied_categories = json_decode($denied_categories_json, true);
        $denied_categories = is_array($denied_categories) ? array_map('intval', $denied_categories) : [];
        $getAllChildCategories = function ($parent_id) use (&$getAllChildCategories) {
            $children = $this->model_catalog_category->getCategories($parent_id);
            $result = [];
            foreach ($children as $child) {
                $result[] = (int)$child['category_id'];
                $result = array_merge($result, $getAllChildCategories($child['category_id']));
            }
            return $result;
        };
        $all_allowed_categories = [];
        foreach ($allowed_categories as $cat_id) {
            $all_allowed_categories[] = $cat_id;
            $all_allowed_categories = array_merge($all_allowed_categories, $getAllChildCategories($cat_id));
        }
        $all_allowed_categories = array_unique($all_allowed_categories);
        $all_denied_categories = [];
        foreach ($denied_categories as $cat_id) {
            $all_denied_categories[] = $cat_id;
            $all_denied_categories = array_merge($all_denied_categories, $getAllChildCategories($cat_id));
        }
        $all_denied_categories = array_unique($all_denied_categories);
        $cart_products = $this->cart->getProducts();
        foreach ($cart_products as $product) {
            $product_categories = $this->model_catalog_product->getCategories($product['product_id']);
            $category_ids = array_map(function ($c) {
                return (int)$c['category_id'];
            }, $product_categories);
            foreach ($category_ids as $cid) {
                if (in_array($cid, $all_denied_categories)) {
                    return [];
                }
            }
            if (!empty($all_allowed_categories)) {
                $found_allowed = false;
                foreach ($category_ids as $cid) {
                    if (in_array($cid, $all_allowed_categories)) {
                        $found_allowed = true;
                        break;
                    }
                }
                if (!$found_allowed) {
                    return [];
                }
            }
        }
        return [
            'code'       => 'alfabank',
            'title'      => $this->language->get('entry_alfabank_text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_alfabank_sort_order')
        ];
    }
    public function storeGatewayOrder($data)
    {
        // Each gateway reference is one payment attempt. Repeated callbacks update only that attempt.
        $this->db->query("INSERT INTO `" . DB_PREFIX . "alfabank_order` SET
            `order_id` = '" . (int)$data['order_id'] . "',
            `gateway_order_reference` = '" . $this->db->escape($data['gateway_order_reference']) . "',
            `tx_url` = '" . (isset($data['tx_url']) ? $this->db->escape($data['tx_url']) : '') . "',
            `order_number` = '" . (isset($data['order_number']) ? $this->db->escape($data['order_number']) : '') . "',
            `currency` = '" . $this->db->escape($data['currency']) . "',
            `payment_way` = '" . (isset($data['payment_way']) ? $this->db->escape($data['payment_way']) : '') . "',
            `payment_system` = '" . (isset($data['payment_system']) ? $this->db->escape($data['payment_system']) : '') . "',
            `order_amount` = '" . (float)$data['order_amount'] . "',
            `order_amount_deposited` = '" . (float)$data['order_amount_deposited'] . "',
            `status_deposited` = '" . (int)$data['status_deposited'] . "',
            `date_added` = NOW(),
            `date_updated` = NOW()
        ON DUPLICATE KEY UPDATE
            `gateway_order_reference` = '" . $this->db->escape($data['gateway_order_reference']) . "',
            `tx_url` = IF('" . (isset($data['tx_url']) && !empty($data['tx_url']) ? $this->db->escape($data['tx_url']) : '') . "' != '', '" . (isset($data['tx_url']) ? $this->db->escape($data['tx_url']) : '') . "', `tx_url`),
            `order_number` = '" . (isset($data['order_number']) ? $this->db->escape($data['order_number']) : '') . "',
            `currency` = '" . $this->db->escape($data['currency']) . "',
            `payment_way` = '" . (isset($data['payment_way']) ? $this->db->escape($data['payment_way']) : '') . "',
            `payment_system` = '" . (isset($data['payment_system']) ? $this->db->escape($data['payment_system']) : '') . "',
            `order_amount` = '" . (float)$data['order_amount'] . "',
            `order_amount_deposited` = '" . (float)$data['order_amount_deposited'] . "',
            `status_deposited` = '" . (int)$data['status_deposited'] . "',
            `status` = IF(`status` = 2 AND " . (int)$data['status_deposited'] . " IN (1, 2, 4), 0, `status`),
            `date_updated` = NOW()");
    }

    public function get_alfabank_current_payment_list($order_status = array(-1, 0, 1, 2, 3, 4, 5, 6))
    {
        $array = implode(", ", $order_status);
        $res = $this->db->query("SELECT *, UNIX_TIMESTAMP(date_updated) as date_updated_timestamp FROM `" . DB_PREFIX . "alfabank_order` WHERE `status_deposited` IN (" . $array . ")");
        return $res->rows;
    }

    /** Poll only unfinished attempts; terminal changes arrive through callbacks. */
    public function getPaymentsForReconciliation()
    {
        return $this->db->query("SELECT *, UNIX_TIMESTAMP(date_updated) AS date_updated_timestamp
            FROM `" . DB_PREFIX . "alfabank_order`
            WHERE status_deposited IN (-1,0,1,5)
              AND date_updated <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ORDER BY date_updated ASC, gateway_order_id ASC LIMIT 50")->rows;
    }

    /** Retryable history projection: saving a snapshot never consumes the history event. */
    public function syncAdjustmentHistory($order_id, array $response)
    {
        require_once DIR_SYSTEM . 'library/alfabank/AlfabankGatewayState.php';
        require_once DIR_SYSTEM . 'library/alfabank/AlfabankRefundStatus.php';
        $fields = AlfabankGatewayState::normalize($response);
        if (!$fields || empty($response['orderId']) || !in_array($fields['status_deposited'], array(3,4), true)) {
            return;
        }
        $this->load->model('checkout/order');
        // Serialize duplicate callback/cron history writes for this order.
        $lock = 'alfa-history-' . sha1(DB_PREFIX . ':' . (int)$order_id);
        $locked = $this->db->query("SELECT GET_LOCK('" . $lock . "', 5) AS acquired")->row;
        if (empty($locked['acquired'])) {
            throw new RuntimeException('AlfaBank order history is busy; retry reconciliation.');
        }
        try {
            $order = $this->model_checkout_order->getOrder($order_id);
            if (!$order) return;
            if ($fields['status_deposited'] === 4) {
                $captured = $response['paymentAmountInfo']['approvedAmount'] ?? null;
                $refunded = $response['paymentAmountInfo']['refundedAmount'] ?? null;
                $status = AlfabankRefundStatus::resolve($this->config, $captured, $refunded);
                if ($status === null) return;
                $comment = AlfabankRefundStatus::comment($response['orderId'], $captured, $refunded);
            } else {
                // Releasing a payment authorization does not select cash on delivery
                // or cancel fulfilment. Record the event without changing the order state.
                $status = (int)$order['order_status_id'];
                $comment = 'AlfaBank: авторизация отменена (ID: ' . $response['orderId'] . ')';
            }
            $exists = $this->db->query("SELECT order_history_id FROM `" . DB_PREFIX . "order_history`
                WHERE order_id = " . (int)$order_id . " AND comment = '" . $this->db->escape($comment) . "' LIMIT 1");
            if (!$exists->num_rows) {
                $this->model_checkout_order->addOrderHistory($order_id, $status, $comment, false);
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('" . $lock . "')");
        }
    }

    public function check_payment_status($orderId)
    {
        $order_number = $this->get_opencart_order_id($orderId);
        $this->initializeAlfabank();
        $response = $this->alfabank->_getGatewayOrderStatus($orderId);
        $response = json_decode($response, true);
        $response = is_array($response) ? $response : array();
        $response['orderId'] = $orderId;

        require_once DIR_SYSTEM . 'library/alfabank/AlfabankGatewayState.php';
        if (!isset($response['errorCode']) || !AlfabankGatewayState::normalize($response)) {
            $response['orderStatus'] = -1;
            if ($this->config->get('payment_alfabank_logging')) {
                $this->log->write('Alfabank status check failed for order ' . (int)$order_number);
            }
        }
        return $response;
    }

    public function get_opencart_order_id($orderId)
    {
        $res = $this->db->query("SELECT `order_id` FROM " . DB_PREFIX . "alfabank_order WHERE `gateway_order_reference` = '" . $this->db->escape($orderId) . "'");
        return isset($res->row['order_id']) ? (int)$res->row['order_id'] : 0;
    }

    public function update_alfabank_order($data)
    {
        require_once DIR_SYSTEM . 'library/alfabank/AlfabankGatewayState.php';
        $fields = AlfabankGatewayState::normalize($data);
        if (empty($data['orderId'])) {
            return;
        }
        // A failed poll must not replace the last known financial state with -1.
        $sql = "UPDATE " . DB_PREFIX . "alfabank_order SET `date_updated` = NOW()";
        foreach ($fields as $field => $value) {
            $sql .= ", `" . $field . "` = " . (float)$value;
        }
        if ($fields && in_array($fields['status_deposited'], array(1, 2, 4), true)) {
            $sql .= ", `status` = IF(`status` = 2, 0, `status`)";
        }
        $payment_way = $fields && isset($data['paymentWay']) ? $data['paymentWay'] : null;
        $payment_system = $fields && isset($data['cardAuthInfo']['paymentSystem']) ? $data['cardAuthInfo']['paymentSystem'] : null;

        if ($payment_way !== null) {
            $sql .= ", `payment_way` = '" . $this->db->escape($payment_way) . "'";
        }

        if ($payment_system !== null) {
            $sql .= ", `payment_system` = '" . $this->db->escape($payment_system) . "'";
        }

        $sql .= " WHERE `gateway_order_reference` = '" . $this->db->escape($data['orderId']) . "'";

        $this->db->query($sql);
    }

    public function delete_alfabank_order($orderId)
    {
        if (is_numeric($orderId)) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "alfabank_order WHERE `order_id` = '" . (int)$orderId . "'");
        } else {
            $this->db->query("DELETE FROM " . DB_PREFIX . "alfabank_order WHERE `gateway_order_reference` = '" . $this->db->escape($orderId) . "'");
        }
    }

    public function update_opencart_order_history($order_id, $response)
    {
        $lock = 'alfa-history-' . sha1(DB_PREFIX . ':' . (int)$order_id);
        $locked = $this->db->query("SELECT GET_LOCK('" . $lock . "', 5) AS acquired")->row;
        if (empty($locked['acquired'])) {
            throw new RuntimeException('AlfaBank order history is busy; retry reconciliation.');
        }
        try {
            $this->syncCapturedHistory($order_id, $response);
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('" . $lock . "')");
        }
    }

    private function syncCapturedHistory($order_id, $response)
    {
        require_once DIR_SYSTEM . 'library/alfabank/AlfabankGatewayState.php';
        $fields = AlfabankGatewayState::normalize($response);
        // A hold is not captured money. Wait for status 2 before marking paid.
        if (!$fields || $fields['status_deposited'] !== 2 ||
            !isset($fields['order_amount_deposited']) || empty($response['orderId'])) {
            return;
        }
        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($order_id);
        if (!$order) return;
        $captured = $fields['order_amount_deposited'];
        $status = AlfabankGatewayState::paidOrderStatus($this->config, $order, $captured);
        $expected = (int)round(round((float)$order['total'] * (float)$order['currency_value'], 2) * 100);
        $comment = 'AlfaBank: списано ' . number_format($captured / 100, 2, '.', '') .
            ' (ID: ' . $response['orderId'] . ')';
        if ((int)round($captured) !== $expected) {
            $comment .= '; сумма не совпадает с заказом: ' . number_format($expected / 100, 2, '.', '');
        }
        $exists = $this->db->query("SELECT order_history_id FROM `" . DB_PREFIX . "order_history`
            WHERE order_id = " . (int)$order_id . " AND comment = '" . $this->db->escape($comment) . "' LIMIT 1");
        if (!$exists->num_rows) {
            $this->model_checkout_order->addOrderHistory($order_id, $status, $comment, false);
        }
    }

    public function get_oc_paid_status($oc_order_id, $paid_statuses = array(14, 15, 22, 23))
    {
        $oc_order_id = (int) $oc_order_id;
        $array = implode(", ", array_map('intval', $paid_statuses));
        $res = $this->db->query("SELECT EXISTS (SELECT 1 FROM `" . DB_PREFIX . "order_history` WHERE
`order_id` = " . $oc_order_id . " AND `order_status_id` IN (" . $array . ")) AS exists_flag");
        $status = $res->row;
        if ($this->config->get('payment_alfabank_logging'))
            $this->log->write(sprintf(
                "Alfabank get_oc_paid_status: Order # %s has been %s ",
                $oc_order_id,
                (bool)$status['exists_flag'] ? 'paid' : 'not paid'
            ));
        return (bool)$status['exists_flag'];
    }

    /**
     * Init Library
     */
    private function initializeAlfabank()
    {
        $this->library('alfabank/Alfabank');
        $this->alfabank = new Alfabank();
        $this->alfabank->token = $this->config->get('payment_alfabank_merchantToken');
        $this->alfabank->login = $this->config->get('payment_alfabank_merchantLogin');
        $this->alfabank->password = htmlspecialchars_decode($this->config->get('payment_alfabank_merchantPassword'));
        $this->alfabank->stage = $this->config->get('payment_alfabank_stage');
        $this->alfabank->mode = $this->config->get('payment_alfabank_mode');
        $this->alfabank->logging = $this->config->get('payment_alfabank_logging');
        $this->alfabank->taxSystem = $this->config->get('payment_alfabank_taxSystem');
        $this->alfabank->taxType = $this->config->get('payment_alfabank_taxType');
        $this->alfabank->send_cart = $this->config->get('payment_alfabank_send_cart');
        $this->alfabank->versionFfd = $this->config->get('payment_alfabank_versionFfd');
        $this->alfabank->paymentMethodType = $this->config->get('payment_alfabank_paymentMethodType');
        $this->alfabank->paymentObjectType = $this->config->get('payment_alfabank_paymentObjectType');
        $this->alfabank->paymentMethodTypeDelivery = $this->config->get('payment_alfabank_paymentMethodTypeDelivery');
        if (file_exists(DIR_SYSTEM . "library/cacert.cer") && $this->config->get('payment_alfabank_enable_cacert') == true) {
            $this->alfabank->enable_cacert = $this->config->get('payment_alfabank_enable_cacert');
            $this->alfabank->cacert_path = DIR_SYSTEM . "library/cacert.cer";
        } else {
            $this->alfabank->enable_cacert = (float)$this->config->get('payment_alfabank_enable_cacert');
        }
        $this->alfabank->language = substr($this->language->get('code'), 0, 2);
        $this->alfabank->backToShopURL = $this->config->get('payment_alfabank_backToShopURL');
    }

    private function library($library)
    {
        $file = DIR_SYSTEM . 'library/' . str_replace('../', '', (string)$library) . '.php';
        if (file_exists($file)) {
            include_once($file);
        } else {
            trigger_error('Error: Could not load library ' . $file . '!');
            exit();
        }
    }
}
