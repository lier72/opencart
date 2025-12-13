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
            `tx_url` = IF('" . (isset($data['tx_url']) && !empty($data['tx_url']) ? $this->db->escape($data['tx_url']) : '') . "' != '', '" . (isset($data['tx_url']) ? $this->db->escape($data['tx_url']) : '') . "', `tx_url`),
            `order_number` = '" . (isset($data['order_number']) ? $this->db->escape($data['order_number']) : '') . "',
            `currency` = '" . $this->db->escape($data['currency']) . "',
            `payment_way` = '" . (isset($data['payment_way']) ? $this->db->escape($data['payment_way']) : '') . "',
            `payment_system` = '" . (isset($data['payment_system']) ? $this->db->escape($data['payment_system']) : '') . "',
            `order_amount` = '" . (float)$data['order_amount'] . "',
            `order_amount_deposited` = '" . (float)$data['order_amount_deposited'] . "',
            `status_deposited` = '" . (int)$data['status_deposited'] . "',
            `date_updated` = NOW()");
    }

    public function get_alfabank_current_payment_list($order_status = array(-1, 0, 1, 2, 3, 4, 5, 6))
    {
        $array = implode(", ", $order_status);
        $res = $this->db->query("SELECT *, UNIX_TIMESTAMP(date_updated) as date_updated_timestamp FROM `" . DB_PREFIX . "alfabank_order` WHERE `status_deposited` IN (" . $array . ")");
        return $res->rows;
    }

    public function check_payment_status($orderId)
    {
        $order_number = $this->get_opencart_order_id($orderId);
        $this->initializeAlfabank();
        $response = $this->alfabank->_getGatewayOrderStatus($orderId);
        $response = json_decode($response, true);
        if (($response['errorCode'] == 0)) {
            if ($this->config->get('payment_alfabank_logging'))
                $this->log->write(sprintf(
                    "Alfabank check_payment_status: Order # %s payment status: %s ActionStatus %s - ",
                    $order_number,
                    $response['orderStatus'],
                    $response['actionCode'],
                    $response['actionCodeDescription']
                ));
        } else {
            if ($this->config->get('payment_alfabank_logging'))
                $this->log->write(sprintf(
                    "Alfabank check_payment_status: Order # %s, Error %s, Description: %s",
                    $order_number,
                    $response['errorCode'],
                    $response['errorMessage']
                ));
            $response['orderStatus'] = -1;
        }
        return $response;
    }

    public function get_opencart_order_id($orderId)
    {
        $res = $this->db->query("SELECT `order_id` FROM " . DB_PREFIX . "alfabank_order WHERE `gateway_order_reference` = '" . $this->db->escape($orderId) . "'");
        return $res->row['order_id'];
    }

    public function update_alfabank_order($data)
    {
        // Extract payment way and payment system if available
        $payment_way = isset($data['paymentWay']) ? $data['paymentWay'] : null;
        $payment_system = isset($data['cardAuthInfo']['paymentSystem']) ? $data['cardAuthInfo']['paymentSystem'] : null;

        $sql = "UPDATE " . DB_PREFIX . "alfabank_order SET
            `date_updated` = NOW(),
            `status_deposited` = " . (int)$data['orderStatus'] . ",
            `order_amount_deposited` = " . (float)$data['amount'];

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

    public function update_opencart_order_history($order_id, $alfabank_response)
    {
        if ($this->config->get('payment_alfabank_logging'))
            $this->log->write("Alfabank update_opencart_order_history: was called");
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $order_paid = $this->get_oc_paid_status($order_id);

        if ($this->config->get('payment_alfabank_logging'))
            $this->log->write(sprintf(
                "Alfabank update_opencart_order_history: Order # %s payed in order history is : %s",
                $order_id,
                $order_paid ? 'true' : 'false'
            ));

        if ($order_info && !$order_paid) {
            $payment_amount = (float)($alfabank_response['amount']) / 100;
            $order_amount = $order_info['total'] * $order_info['currency_value'];
            $amount_difference = $payment_amount - $order_amount;

            // Determine payment completeness
            if (abs($amount_difference) < 0.01) {
                $payment_status = 'полностью';
            } elseif ($amount_difference < 0) {
                $payment_status = 'НЕ ПОЛНОСТЬЮ';
            } else {
                $payment_status = 'с переплатой';
            }

            // Determine payment type
            $payment_type = $alfabank_response['orderStatus'] == 2
                ? 'Полная авторизация'
                : 'Предавторизация (Двухстадийный платеж)';

            $comment = sprintf(
                "Заказ № %s Оплачен %s\n" .
                    "Тип платежа: %s\n" .
                    "Сумма платежа: %s руб. (Заказ: %s руб.)\n" .
                    "ID транзакции: %s\n" .
                    "Код действия: %s - %s\n" .
                    "Дата обработки: %s",
                $order_id,
                $payment_status,
                $payment_type,
                number_format($payment_amount, 2, '.', ' '),
                number_format($order_amount, 2, '.', ' '),
                $alfabank_response['orderId'],
                isset($alfabank_response['actionCode']) ? $alfabank_response['actionCode'] : 'N/A',
                isset($alfabank_response['actionCodeDescription']) ? $alfabank_response['actionCodeDescription'] : 'Успешно',
                date('Y-m-d H:i:s')
            );

            // Add warning if amounts don't match
            if (abs($amount_difference) >= 0.01) {
                $comment .= sprintf(
                    "\n⚠️ ВНИМАНИЕ: Разница в сумме: %s руб.",
                    number_format($amount_difference, 2, '.', ' ')
                );
            }

            // Add card info if available
            if (isset($alfabank_response['cardAuthInfo']['pan'])) {
                $comment .= "\nКарта: " . $alfabank_response['cardAuthInfo']['pan'];
            }

            // Add binding info if available
            if (isset($alfabank_response['bindingId'])) {
                $comment .= "\nBinding ID: " . $alfabank_response['bindingId'];
            }

            $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_alfabank_order_status_completed_id'), $comment);
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

    private function initializeAlfabank()
    {
        $this->library('alfabank/Alfabank');
        $this->alfabank = new Alfabank();
        $this->alfabank->token = $this->config->get('payment_alfabank_merchantToken');
        $this->alfabank->login = $this->config->get('payment_alfabank_merchantLogin');
        $this->alfabank->password = htmlspecialchars_decode($this->config->get('payment_alfabank_merchantPassword'));
        $this->alfabank->mode = $this->config->get('payment_alfabank_mode');
        $this->alfabank->logging = $this->config->get('payment_alfabank_logging');
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
