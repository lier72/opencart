<?php
class ControllerExtensionPaymentAlfabank extends Controller
{
    private $error = array();
    /**
     * settings
     */
    public function index()
    {
        $this->library('alfabank/Alfabank');
        $method_library = new Alfabank();
        $this->load->language('extension/payment/alfabank');
        $this->load->model('extension/payment/alfabank');
        $this->model_extension_payment_alfabank->install();
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $categories_allowed = !empty($this->request->post['enabled_category']) ? $this->request->post['enabled_category'] : [];
            $this->request->post['payment_alfabank_allowed_categories'] = json_encode($categories_allowed);
            $categories_denied = !empty($this->request->post['denied_category']) ? $this->request->post['denied_category'] : [];
            $this->request->post['payment_alfabank_denied_categories'] = json_encode($categories_denied);
            $this->model_setting_setting->editSetting('payment_alfabank', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $test_mode = !empty($this->request->post['payment_alfabank_mode']) && $this->request->post['payment_alfabank_mode'] === 'test';
            if ($method_library->allowCallbacks) {
                $this->_updateGateSettings($test_mode);
            }
            $this->response->redirect(
                $this->url->link(
                    'extension/payment/alfabank',
                    'user_token=' . $this->session->data['user_token'] . '&type=payment',
                    true
                )
            );
        }
        $data['heading_title'] = $this->language->get('heading_title');
        $data['breadcrumbs'] = array();
        array_push(
            $data['breadcrumbs'],
            array(  // main
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], 'SSL')
            ),
            array(  // payment
                'text' => $this->language->get('text_payment'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], 'SSL')
            ),
            array(  // Payment by
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/payment/alfabank', 'user_token=' . $this->session->data['user_token'], 'SSL')
            )
        );
        $data['action'] = $this->url->link('extension/payment/alfabank', 'user_token=' . $this->session->data['user_token'], 'SSL');
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', 'SSL');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['text_settings'] = $this->language->get('text_settings');
        $data['entry_status'] = $this->language->get('status');
        $data['status_enabled'] = $this->language->get('status_enabled');
        $data['status_disabled'] = $this->language->get('status_disabled');
        if (isset($this->request->post['payment_alfabank_status'])) {
            $data['payment_alfabank_status'] = $this->request->post['payment_alfabank_status'];
        } else {
            $data['payment_alfabank_status'] = $this->config->get('payment_alfabank_status');
        }
        $data['entry_merchantToken'] = "Token"; //$this->language->get('merchantToken');
        $data['payment_alfabank_merchantToken'] = $this->config->get('payment_alfabank_merchantToken');
        $data['entry_merchantLogin'] = $this->language->get('merchantLogin');
        $data['payment_alfabank_merchantLogin'] = $this->config->get('payment_alfabank_merchantLogin');
        $data['entry_merchantPassword'] = $this->language->get('merchantPassword');
        $data['payment_alfabank_merchantPassword'] = $this->config->get('payment_alfabank_merchantPassword');
        if (!empty($data['payment_alfabank_merchantLogin']) && !empty($data['payment_alfabank_merchantPassword'])) {
            $token_default = base64_encode($data['payment_alfabank_merchantLogin'] . ":" . $data['payment_alfabank_merchantPassword']);
            $data['payment_alfabank_merchantToken'] = $token_default;
        }
        $data['entry_mode'] = $this->language->get('mode');
        $data['mode_test'] = $this->language->get('mode_test');
        $data['mode_prod'] = $this->language->get('mode_prod');
        $data['payment_alfabank_mode'] = $this->config->get('payment_alfabank_mode');
        $data['entry_enable_cacert'] = $this->language->get('entry_enable_cacert');
        $data['cacert_enabled'] = $this->language->get('entry_enable_cacert_enable');
        $data['cacert_disabled'] = $this->language->get('entry_enable_cacert_disable');
        $data['payment_alfabank_enable_cacert'] = $this->config->get('payment_alfabank_enable_cacert');
        $data['entry_stage'] = $this->language->get('stage');
        $data['stage_one'] = $this->language->get('stage_one');
        $data['stage_two'] = $this->language->get('stage_two');
        $data['payment_alfabank_stage'] = $this->config->get('payment_alfabank_stage');
        $data['entry_order_status_completed'] = $this->language->get('entry_order_status_completed');
        if (isset($this->request->post['payment_alfabank_order_status_completed_id'])) {
            $data['payment_alfabank_order_status_completed_id'] = $this->request->post['payment_alfabank_order_status_completed_id'];
        } else {
            $data['payment_alfabank_order_status_completed_id'] = $this->config->get('payment_alfabank_order_status_completed_id');
        }
        $data['entry_order_status_before'] = $this->language->get('entry_order_status_before');
        if (isset($this->request->post['payment_alfabank_order_status_before_id'])) {
            $data['payment_alfabank_order_status_before_id'] = $this->request->post['payment_alfabank_order_status_before_id'];
        } else {
            $data['payment_alfabank_order_status_before_id'] = $this->config->get('payment_alfabank_order_status_before_id');
        }
        $data['entry_order_status_reversed'] = $this->language->get('entry_order_status_reversed');
        if (isset($this->request->post['payment_alfabank_order_status_reversed_id'])) {
            $data['payment_alfabank_order_status_reversed_id'] = $this->request->post['payment_alfabank_order_status_reversed_id'];
        } else {
            $data['payment_alfabank_order_status_reversed_id'] = $this->config->get('payment_alfabank_order_status_reversed_id');
        }
        $data['entry_order_status_refunded'] = $this->language->get('entry_order_status_refunded');
        if (isset($this->request->post['payment_alfabank_order_status_refunded_id'])) {
            $data['payment_alfabank_order_status_refunded_id'] = $this->request->post['payment_alfabank_order_status_refunded_id'];
        } else {
            $data['payment_alfabank_order_status_refunded_id'] = $this->config->get('payment_alfabank_order_status_refunded_id');
        }
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Load customer groups
        $this->load->model('customer/customer_group');
        $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

        if (isset($this->request->post['payment_alfabank_customer_group_id'])) {
            $data['payment_alfabank_customer_group_id'] = $this->request->post['payment_alfabank_customer_group_id'];
        } else {
            $data['payment_alfabank_customer_group_id'] = $this->config->get('payment_alfabank_customer_group_id');
        }

        $data['entry_customer_group'] = $this->language->get('entry_customer_group');

        $data['entry_sortOrder'] = $this->language->get('entry_sortOrder');
        $data['payment_alfabank_sort_order'] = $this->config->get('payment_alfabank_sort_order');
        $data['entry_minAmount'] = $this->language->get('entry_minAmount');
        $data['payment_alfabank_min_amount'] = round((float) $this->config->get('payment_alfabank_min_amount'), 2);
        $data['entry_maxAmount'] = $this->language->get('entry_maxAmount');
        $data['payment_alfabank_max_amount'] = round((float) $this->config->get('payment_alfabank_max_amount'), 2);
        $data['entry_allowed_categories'] = $this->language->get('entry_allowed_categories');
        $data['entry_denied_categories'] = $this->language->get('entry_denied_categories');
        $data['user_token'] = $this->session->data['user_token'];
        $this->load->model('catalog/category');
        $categories_allowed = json_decode($this->config->get('payment_alfabank_allowed_categories'), true);
        $data['enabled_categories'] = array();
        foreach ($categories_allowed as $category_id) {
            $category_info = $this->model_catalog_category->getCategory($category_id);
            if ($category_info) {
                $data['enabled_categories'][] = array(
                    'category_id' => $category_info['category_id'],
                    'name'        => ($category_info['path']) ? $category_info['path'] . ' &gt; ' . $category_info['name'] : $category_info['name']
                );
            }
        }
        $categories_denied = json_decode($this->config->get('payment_alfabank_denied_categories'), true);
        $data['denied_categories'] = array();
        if (is_array($categories_denied)) {
            foreach ($categories_denied as $category_id) {
                $category_info = $this->model_catalog_category->getCategory($category_id);
                if ($category_info) {
                    $data['denied_categories'][] = array(
                        'category_id' => $category_info['category_id'],
                        'name'        => ($category_info['path']) ? $category_info['path'] . ' &gt; ' . $category_info['name'] : $category_info['name']
                    );
                }
            }
        }
        $data['entry_logging'] = $this->language->get('logging');
        $data['logging_enabled'] = $this->language->get('logging_enabled');
        $data['logging_disabled'] = $this->language->get('logging_disabled');
        $data['payment_alfabank_logging'] = $this->config->get('payment_alfabank_logging');
        $data['entry_ofdStatus'] = $this->language->get('entry_ofdStatus');
        $data['payment_alfabank_send_cart'] = $this->config->get('payment_alfabank_send_cart');
        $data['entry_ofd_enabled'] = $this->language->get('entry_ofd_enabled');
        $data['entry_ofd_disabled'] = $this->language->get('entry_ofd_disabled');
        $data['entry_taxSystem'] = $this->language->get('entry_taxSystem');
        $data['taxSystem_list'] = $this->getTaxSystemList();
        $data['payment_alfabank_taxSystem'] = $this->config->get('payment_alfabank_taxSystem');
        $data['entry_taxType'] = $this->language->get('entry_taxType');
        $data['taxType_list'] = $this->getTaxTypeList();
        $data['payment_alfabank_taxType'] = $this->config->get('payment_alfabank_taxType');
        $data['entry_versionFfdFormat'] = $this->language->get('entry_versionFfdFormat');
        $data['versionFfdList'] = $this->getVersionFfdlist();
        $data['payment_alfabank_versionFfd'] = $this->config->get('payment_alfabank_versionFfd');
        $data['entry_paymentMethod'] = $this->language->get('entry_paymentMethod');
        $data['paymentMethodTypeList'] = $this->getPaymentMethodTypeList();
        $data['payment_alfabank_paymentMethodType'] = $this->config->get('payment_alfabank_paymentMethodType');
        $data['entry_paymentMethodDelivery'] = $this->language->get('entry_paymentMethodDelivery');
        $data['payment_alfabank_paymentMethodTypeDelivery'] = $this->config->get('payment_alfabank_paymentMethodTypeDelivery');
        $data['entry_paymentObject'] = $this->language->get('entry_paymentObject');
        $data['paymentObjectTypeListList'] = $this->getPaymentObjectTypeList();
        $data['payment_alfabank_paymentObjectType'] = $this->config->get('payment_alfabank_paymentObjectType');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['enable_cart_options'] = $method_library->enable_cart_options;
        $data['enable_cacert'] = $method_library->enable_cacert;
        $data['enable_refund_options'] = $method_library->enable_refund_options;
        $data['enable_back_url_settings'] = $method_library->enable_back_url_settings;
        $data['entry_backToShopURL'] = $this->language->get('entry_backToShopURL');
        $data['entry_backToShopURL_description'] = $this->language->get('entry_backToShopURL_description');
        $data['payment_alfabank_backToShopURL'] = $this->config->get('payment_alfabank_backToShopURL');
        $data['api_version'] = $method_library->api_version;
        $Gateway = new Alfabank();
        $currentVersion = $Gateway->module_version;
        if (!empty($Gateway->home_url)) {
            $remoteVersion = @file_get_contents($Gateway->home_url  . 'VERSION');
            if (
                $remoteVersion
                && $this->compare_major_minor($currentVersion, $remoteVersion) < 0
            ) {
                $data['remoteVersion'] = $remoteVersion;
                $data['currentVersion'] = $currentVersion;
                $data['newVersionUri'] = $Gateway->home_url . 'opencart3.x.ocmod.zip';
            }
        }
        $this->response->setOutput($this->load->view('extension/payment/alfabank', $data));
    }
    /**
     * @return bool
     */
    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/alfabank')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
    public function _updateGateSettings($test_mode = true)
    {
        $this->library('alfabank/Alfabank');
        $method_library = new Alfabank();
        $this->library('log');
        $file_name = "oc3x_alfabank_" . date("y-m") . ".log";
        $logger = new Log($file_name);
        $url = new Url(HTTP_CATALOG, $this->config->get('config_secure') ? HTTP_CATALOG : HTTPS_CATALOG);
        $callback_addresses_string = $url->link('extension/payment/alfabank/callback');
        if (!empty($this->config->get('payment_alfabank_merchantToken'))) {
            $decoded_credentials = base64_decode($this->config->get('payment_alfabank_merchantToken'));
            list($l, $p) = explode(':', $decoded_credentials);
            $login = $l;
            $password = $p;
        } else {
            $login = $this->config->get('payment_alfabank_merchantLogin');
            $password = htmlspecialchars_decode($this->config->get('payment_alfabank_merchantPassword'));
        }
        if ($test_mode) {
            $action_adr = $method_library->getTestUrl();
            $gate_url = str_replace("payment/rest", "mportal/mvc/public/merchant/update", $action_adr);
        } else {
            $action_adr = $method_library->getProdUrl();
            $gate_url = str_replace("payment/rest", "mportal/mvc/public/merchant/update", $action_adr);
            $alternative_domain = $method_library->getAlternativeDomain();
            if (!is_null($alternative_domain)) {
                $pattern = '/^https:\/\/[^\/]+/';
                $gate_url = preg_replace($pattern, rtrim($alternative_domain, '/'), $gate_url);
            }
        }
        $gate_url .= substr($login, 0, -4);
        $headers = array(
            'Content-Type:application/json',
            'Authorization: Basic ' . base64_encode($login . ":" . $password)
        );
        $data['callbacks_enabled'] = true;
        $data['callback_type'] = $method_library->callbackType;
        $data['callback_http_method'] = "GET";
        $data['callback_operations'] = "deposited,approved,declinedByTimeout,reversed,refunded";
        if ($method_library->callbackType == "STATIC") {
            $data['callback_addresses'] = $callback_addresses_string;
        }
        $response = $method_library->_sendGatewayData(json_encode($data), $gate_url, $headers);
        $logger->write("\nREQUEST: " . $gate_url . "\n" . json_encode($data) . "\nRESPONSE: " . json_encode($response) . "\n");
    }
    /**
     * @return array
     */
    private function getTaxTypeList()
    {
        return [
            [
                'numeric' => 0,
                'alphabetic' => $this->language->get('entry_no_vat')
            ],
            [
                'numeric' => 1,
                'alphabetic' => $this->language->get('entry_vat0')
            ],
            [
                'numeric' => 2,
                'alphabetic' => $this->language->get('entry_vat10')
            ],
            [
                'numeric' => 3,
                'alphabetic' => $this->language->get('entry_vat18')
            ],
            [
                'numeric' => 4,
                'alphabetic' => $this->language->get('entry_vat10_110')
            ],
            [
                'numeric' => 5,
                'alphabetic' => $this->language->get('entry_vat18_118')
            ],
            [
                'numeric' => 6,
                'alphabetic' => $this->language->get('entry_vat20')
            ],
            [
                'numeric' => 7,
                'alphabetic' => $this->language->get('entry_vat20_120')
            ],
            [
                'numeric' => 10,
                'alphabetic' => $this->language->get('entry_vat5')
            ],
            [
                'numeric' => 11,
                'alphabetic' => $this->language->get('entry_vat5_105')
            ],
            [
                'numeric' => 12,
                'alphabetic' => $this->language->get('entry_vat7')
            ],
            [
                'numeric' => 13,
                'alphabetic' => $this->language->get('entry_vat7_107')
            ],
        ];
    }
    /**
     * @return array
     */
    private function getTaxSystemList()
    {
        return [
            [
                'numeric' => 0,
                'alphabetic' => $this->language->get('entry_tax_system_1')
            ],
            [
                'numeric' => 1,
                'alphabetic' => $this->language->get('entry_tax_system_2')
            ],
            [
                'numeric' => 2,
                'alphabetic' => $this->language->get('entry_tax_system_3')
            ],
            [
                'numeric' => 3,
                'alphabetic' => $this->language->get('entry_tax_system_4')
            ],
            [
                'numeric' => 4,
                'alphabetic' => $this->language->get('entry_tax_system_5')
            ],
            [
                'numeric' => 5,
                'alphabetic' => $this->language->get('entry_tax_system_6')
            ],
        ];
    }
    /**
     * @return array
     */
    private function getVersionFfdlist()
    {
        return [
            [
                'value' => 'v1_05',
                'title' => 'v1.05'
            ],
            [
                'value' => 'v1_2',
                'title' => 'v1.2'
            ],
        ];
    }
    /**
     * @return array
     */
    private function getPaymentMethodTypeList()
    {
        return [
            [
                'numeric' => 1,
                'alphabetic' => $this->language->get('entry_payment_method_1')
            ],
            [
                'numeric' => 2,
                'alphabetic' => $this->language->get('entry_payment_method_2')
            ],
            [
                'numeric' => 3,
                'alphabetic' => $this->language->get('entry_payment_method_3')
            ],
            [
                'numeric' => 4,
                'alphabetic' => $this->language->get('entry_payment_method_4')
            ],
            [
                'numeric' => 5,
                'alphabetic' => $this->language->get('entry_payment_method_5')
            ],
            [
                'numeric' => 6,
                'alphabetic' => $this->language->get('entry_payment_method_6')
            ],
            [
                'numeric' => 7,
                'alphabetic' => $this->language->get('entry_payment_method_7')
            ],
        ];
    }
    /**
     * @return array
     */
    private function getPaymentObjectTypeList()
    {
        return [
            [
                'numeric' => 1,
                'alphabetic' => $this->language->get('entry_payment_object_1')
            ],
            [
                'numeric' => 2,
                'alphabetic' => $this->language->get('entry_payment_object_2')
            ],
            [
                'numeric' => 3,
                'alphabetic' => $this->language->get('entry_payment_object_3')
            ],
            [
                'numeric' => 4,
                'alphabetic' => $this->language->get('entry_payment_object_4')
            ],
            [
                'numeric' => 5,
                'alphabetic' => $this->language->get('entry_payment_object_5')
            ],
            [
                'numeric' => 7,
                'alphabetic' => $this->language->get('entry_payment_object_7')
            ],
            [
                'numeric' => 9,
                'alphabetic' => $this->language->get('entry_payment_object_9')
            ],
            [
                'numeric' => 10,
                'alphabetic' => $this->language->get('entry_payment_object_10')
            ],
            [
                'numeric' => 12,
                'alphabetic' => $this->language->get('entry_payment_object_12')
            ],
            [
                'numeric' => 13,
                'alphabetic' => $this->language->get('entry_payment_object_13')
            ],
        ];
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
    public function install()
    {
        $this->load->model('extension/payment/alfabank');
        $this->model_extension_payment_alfabank->install();
    }
    public function uninstall()
    {
        $this->load->model('extension/payment/alfabank');
        $this->model_extension_payment_alfabank->deleteTables();
    }
    public function order()
    {
        $this->language->load('extension/payment/alfabank');
        $this->load->model('extension/payment/alfabank');
        $this->load->model('sale/order');
        $data['gateway_order'] = $this->model_extension_payment_alfabank->getGatewayOrder($this->request->get['order_id']);
        $data['gateway_orders'] = $this->model_extension_payment_alfabank->getGatewayOrders($this->request->get['order_id']);
        $data['gateway_order'] = $this->prepareGatewayOrderForView($data['gateway_order']);

        foreach ($data['gateway_orders'] as &$gateway_order) {
            $gateway_order = $this->prepareGatewayOrderForView($gateway_order);
        }
        unset($gateway_order);

        $order_info = $this->model_sale_order->getOrder($this->request->get['order_id']);
        $this->library('alfabank/Alfabank');
        $method_library = new Alfabank();
        if (
            $method_library->enable_refund_options === true &&
            !empty($order_info) && !empty($data['gateway_order'])
        ) {
            $data['catalog'] = $this->request->server['HTTPS'] ? HTTPS_CATALOG : HTTP_CATALOG;
            $this->load->model('user/api');
            $api_info = $this->model_user_api->getApi($this->config->get('config_api_id'));
            if ($api_info && $this->user->hasPermission('modify', 'sale/order')) {
                $session = new Session($this->config->get('session_engine'), $this->registry);
                $session->start();
                if (version_compare(VERSION, '3.0.3.7', '<')) {
                    $this->model_user_api->deleteApiSessionBySessonId($session->getId());
                } else {
                    $this->model_user_api->deleteApiSessionBySessionId($session->getId());
                }
                $this->model_user_api->addApiSession($api_info['api_id'], $session->getId(), $this->request->server['REMOTE_ADDR']);
                $session->data['api_id'] = $api_info['api_id'];
                $data['api_token'] = $session->getId();
            } else {
                $data['api_token'] = '';
            }
            $data['store_id'] = $order_info['store_id'];
            $data['can_modify_order_payment'] = $this->user->hasPermission('modify', 'sale/order');
            $data['help_alfabank_amount'] = $data['gateway_order']['help_alfabank_amount'];
            $data['gateway_amount'] = $data['gateway_order']['gateway_amount'];
            $data['user_token'] = $this->request->get['user_token'];
            $data['order_id'] = $this->request->get['order_id'];
            return $this->load->view('extension/payment/alfabank_order', $data);
        }
    }

    private function prepareGatewayOrderForView($gateway_order)
    {
        if (empty($gateway_order)) {
            return $gateway_order;
        }

        $deposited_amount = max(0, (float)$gateway_order['order_amount_deposited']);
        $refunded_amount = max(0, (float)$gateway_order['order_amount_refunded']);
        $remaining_deposited_amount = max(0, $deposited_amount - $refunded_amount);
        $operation_amount = $this->canRefundGatewayOrder($gateway_order)
            ? $remaining_deposited_amount
            : (float)$gateway_order['order_amount'];
        $amount = (int)($operation_amount / 100);
        $gateway_status = $this->getGatewayStatusForView($gateway_order['status_deposited']);
        $gateway_order['gateway_amount'] = $amount;
        $gateway_order['gateway_status_code'] = $gateway_status['code'];
        $gateway_order['gateway_status_label'] = $gateway_status['label'];
        $gateway_order['gateway_status_class'] = $gateway_status['class'];
        $gateway_order['can_deposit'] = $this->canDepositGatewayOrder($gateway_order);
        $gateway_order['can_refund'] = $this->canRefundGatewayOrder($gateway_order);
        $gateway_order['can_reverse'] = $this->canReverseGatewayOrder($gateway_order);
        $gateway_order['help_alfabank_amount'] = sprintf(
            $this->language->get('help_alfabank_amount'),
            $amount,
            $amount,
            $gateway_order['currency']
        );

        return $gateway_order;
    }

    private function getGatewayStatusForView($status)
    {
        $status = (int)$status;
        $statuses = array(
            -1 => array('text_gateway_status_error', 'danger'),
            0 => array('text_gateway_status_registered', 'default'),
            1 => array('text_gateway_status_held', 'warning'),
            2 => array('text_gateway_status_authorized', 'success'),
            3 => array('text_gateway_status_cancelled', 'default'),
            4 => array('text_gateway_status_refunded', 'info'),
            5 => array('text_gateway_status_acs', 'warning'),
            6 => array('text_gateway_status_declined', 'danger'),
        );

        if (isset($statuses[$status])) {
            return array(
                'code' => $status,
                'label' => $this->language->get($statuses[$status][0]),
                'class' => $statuses[$status][1],
            );
        }

        return array(
            'code' => $status,
            'label' => $this->language->get('text_gateway_status_unknown'),
            'class' => 'default',
        );
    }

    private function canDepositGatewayOrder($gateway_order)
    {
        return (
            !empty($gateway_order) &&
            isset($gateway_order['status_deposited']) &&
            (int)$gateway_order['status_deposited'] === 1
        );
    }

    private function canRefundGatewayOrder($gateway_order)
    {
        if (empty($gateway_order) || !isset($gateway_order['status_deposited'])) {
            return false;
        }

        $gateway_status = (int)$gateway_order['status_deposited'];
        $deposited_amount = max(0, (float)$gateway_order['order_amount_deposited']);
        $refunded_amount = max(0, (float)$gateway_order['order_amount_refunded']);

        return in_array($gateway_status, array(2, 4), true) && $deposited_amount > $refunded_amount;
    }

    private function canReverseGatewayOrder($gateway_order)
    {
        return (
            !empty($gateway_order) &&
            isset($gateway_order['status_deposited']) &&
            in_array((int)$gateway_order['status_deposited'], array(1, 2), true) &&
            (float)$gateway_order['order_amount_refunded'] <= 0
        );
    }

    private function fetchGatewayOrderStatus($method_library, $action_adr, $parameters)
    {
        unset($parameters['amount']);
        $response = $method_library->_sendGatewayData(
            $parameters,
            $action_adr . 'getOrderStatusExtended.do'
        );

        return json_decode($response, true);
    }

    private function isGatewayStatusForOrder($response, $order_info)
    {
        return (
            is_array($response) &&
            !empty($response['orderNumber']) &&
            isset($response['orderStatus']) &&
            (int)$order_info['order_id'] === (int)explode('_', $response['orderNumber'])[0]
        );
    }

    private function getGatewayOrderUpdateData($response)
    {
        $data = array();

        if (!is_array($response) || !isset($response['orderStatus'])) {
            return $data;
        }

        $gateway_status = (int)$response['orderStatus'];
        $data['status_deposited'] = $gateway_status;
        $data['status_reversed'] = $gateway_status === 3 ? 1 : 0;

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

    public function gatewayOrderAction()
    {
        $this->language->load('extension/payment/alfabank');
        $json = array();
        $order_action = isset($this->request->post['order_action'])
            ? trim($this->request->post['order_action'])
            : '';
        $allowed_actions = array(
            'payment_status',
            'payment_deposit_partial',
            'payment_deposit_full',
            'payment_refund_partial',
            'payment_refund_full',
            'payment_reverse',
        );

        if (!in_array($order_action, $allowed_actions, true)) {
            $json['error'] = $this->language->get('error_invalid_payment_action');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (
            $order_action !== 'payment_status' &&
            !$this->user->hasPermission('modify', 'sale/order')
        ) {
            $json['error'] = $this->language->get('error_payment_action_permission');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if ($order_action === 'payment_reverse') {
            $admin_password = isset($this->request->post['admin_password'])
                ? html_entity_decode((string)$this->request->post['admin_password'], ENT_QUOTES, 'UTF-8')
                : '';

            if (!$this->verifyCurrentUserPassword($admin_password)) {
                $json['error'] = $this->language->get('error_invalid_admin_password');
                $this->response->setOutput(json_encode($json));
                return;
            }
        }

        $this->load->model('extension/payment/alfabank');
        $this->load->model('sale/order');
        $this->library('alfabank/Alfabank');
        $method_library = new Alfabank();
        $gateway_order_reference = isset($this->request->post['gateway_order_reference'])
            ? trim($this->request->post['gateway_order_reference'])
            : '';
        $gateway_order = $this->model_extension_payment_alfabank->getGatewayOrder(
            $this->request->get['order_id'],
            $gateway_order_reference
        );
        $order_info = $this->model_sale_order->getOrder($this->request->get['order_id']);

        if ($order_action === 'payment_reverse' && !$this->canReverseGatewayOrder($gateway_order)) {
            $json['error'] = $this->language->get('error_payment_reverse_status');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (strpos($order_action, 'payment_deposit') === 0 && !$this->canDepositGatewayOrder($gateway_order)) {
            $json['error'] = $this->language->get('error_payment_operation_status');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (strpos($order_action, 'payment_refund') === 0 && !$this->canRefundGatewayOrder($gateway_order)) {
            $json['error'] = $this->language->get('error_payment_operation_status');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if ($gateway_order && $order_info && !empty($this->request->post['order_action'])) {
            $gateway_order['order_amount'] = (int)round($gateway_order['order_amount']);
            $parameters = array();
            $parameters['orderId'] = trim($gateway_order['gateway_order_reference']);
            if (!empty($this->config->get('payment_alfabank_merchantToken'))) {
                $decoded_credentials = base64_decode($this->config->get('payment_alfabank_merchantToken'));
                list($l, $p) = explode(':', $decoded_credentials);
                $parameters['userName'] = $l;
                $parameters['password'] = $p;
            } else {
                $parameters['userName'] = $this->config->get('payment_alfabank_merchantLogin');
                $parameters['password'] = $this->config->get('payment_alfabank_merchantPassword');
            }
            $test_mode = $this->config->get('payment_alfabank_mode') == 'test' ? true : false;
            if ($test_mode) {
                $action_adr = $method_library->getTestUrl();
            } else {
                $action_adr = $method_library->getProdUrl();
            }
            if ($order_action == 'payment_status') {
                $response = $this->fetchGatewayOrderStatus($method_library, $action_adr, $parameters);
                if ($this->isGatewayStatusForOrder($response, $order_info)) {
                    $this->model_extension_payment_alfabank->updateGatewayOrder(
                        $gateway_order['gateway_order_reference'],
                        $this->getGatewayOrderUpdateData($response)
                    );
                    $payment_state = isset($response['paymentAmountInfo']['paymentState'])
                        ? $response['paymentAmountInfo']['paymentState']
                        : $this->getGatewayStatusForView($response['orderStatus'])['label'];
                    $json['success'] = "Gateway transaction status is: " . $payment_state;
                } else {
                    $json['error'] = isset($response['errorMessage'])
                        ? $response['errorMessage']
                        : $this->language->get('error_order_missing');
                }
            } elseif (strpos($order_action, 'payment_deposit') !== false) {
                if ($order_action == 'payment_deposit_partial') {
                    $user_amount = (float)str_replace(',', '.', trim($this->request->post['user_amount'])) * 100;
                } else {
                    $user_amount = 0;
                }
                if ($gateway_order['order_amount'] < $user_amount) {
                    $json['error'] = sprintf($this->language->get('error_invalid_refund_amount'), number_format((int)$gateway_order['order_amount'] / 100, 2));
                    $this->response->setOutput(json_encode($json));
                    return;
                }
                $parameters['amount'] = intval($user_amount);
                $response = $method_library->_sendGatewayData($parameters, $action_adr . "deposit.do");
                $response = json_decode($response, true);
                if (!empty($response) && isset($response['errorCode'])) {
                    if (empty($response['errorCode'])) {
                        $json['success'] = $this->language->get('text_success_deposit');
                        $json['redirect'] = 1;
                        $sql_data = array(
                            'order_amount_deposited' => ($order_action == 'payment_deposit_partial' ? $user_amount : $gateway_order['order_amount']),
                            'status_deposited' => 2,
                            'status_reversed' => 0,
                            'status_refunded' => 0,
                        );
                        $status_response = $this->fetchGatewayOrderStatus($method_library, $action_adr, $parameters);
                        if ($this->isGatewayStatusForOrder($status_response, $order_info) &&
                            (int)$status_response['orderStatus'] === 2) {
                            $sql_data = array_merge($sql_data, $this->getGatewayOrderUpdateData($status_response));
                        }
                        $this->model_extension_payment_alfabank->updateGatewayOrder($gateway_order['gateway_order_reference'], $sql_data);
                        $json['history'] = array(
                            'order_status_id' => $this->config->get('payment_alfabank_order_status_completed_id'),
                            'comment' => sprintf($this->language->get('text_success_deposit_amount'), number_format(($order_action == 'payment_deposit_partial' ? $user_amount : $gateway_order['order_amount']) / 100, 2)),
                            'notify' => 0,
                        );
                    } else {
                        $json['error'] = $response['errorMessage'];
                    }
                }
            } elseif (strpos($order_action, 'payment_refund') !== false) {
                if ($order_action == 'payment_refund_partial') {
                    $user_amount = (float)str_replace(',', '.', trim($this->request->post['user_amount'])) * 100;
                } else {
                    $user_amount = $gateway_order['order_amount_deposited'] - $gateway_order['order_amount_refunded'];
                }
                if ($user_amount <= 0 ||
                    $gateway_order['order_amount_deposited'] < $user_amount + $gateway_order['order_amount_refunded']) {
                    $json['error'] = sprintf($this->language->get('error_invalid_refund_amount'), number_format((int)($gateway_order['order_amount_deposited'] - $gateway_order['order_amount_refunded']) / 100, 2));
                    $this->response->setOutput(json_encode($json));
                    return;
                }
                $parameters['amount'] = intval($user_amount);
                $response = $method_library->_sendGatewayData($parameters, $action_adr . "refund.do");
                $response = json_decode($response, true);
                if (!empty($response) && isset($response['errorCode'])) {
                    if (empty($response['errorCode'])) {
                        $json['success'] = $this->language->get('text_success_refund');
                        $json['redirect'] = 1;
                        $sql_data = array(
                            'order_amount_refunded' => $user_amount + $gateway_order['order_amount_refunded'],
                            'status_deposited' => 4,
                            'status_refunded' => 1,
                            'status_reversed' => 0,
                        );
                        $status_response = $this->fetchGatewayOrderStatus($method_library, $action_adr, $parameters);
                        if ($this->isGatewayStatusForOrder($status_response, $order_info) &&
                            (int)$status_response['orderStatus'] === 4) {
                            $sql_data = array_merge($sql_data, $this->getGatewayOrderUpdateData($status_response));
                        }
                        $this->model_extension_payment_alfabank->updateGatewayOrder($gateway_order['gateway_order_reference'], $sql_data);
                        $json['history'] = array(
                            'order_status_id' => $this->config->get('payment_alfabank_order_status_refunded_id'),
                            'comment' => sprintf($this->language->get('text_success_refund_amount'), number_format($user_amount / 100, 2)),
                            'notify' => 0,
                        );
                    } else {
                        $json['error'] = $response['errorMessage'];
                    }
                }
            } elseif ($order_action == 'payment_reverse') {
                $response = $method_library->_sendGatewayData($parameters, $action_adr . "reverse.do");
                $response = json_decode($response, true);
                if (!empty($response) && isset($response['errorCode'])) {
                    if (empty($response['errorCode'])) {
                        $json['success'] = $this->language->get('text_success_reverse');
                        $json['redirect'] = 1;
                        $sql_data = array(
                            'order_amount_deposited' => 0.00,
                            'status_deposited' => 3,
                            'status_reversed' => 1,
                        );
                        $status_response = $this->fetchGatewayOrderStatus($method_library, $action_adr, $parameters);
                        if ($this->isGatewayStatusForOrder($status_response, $order_info) &&
                            (int)$status_response['orderStatus'] === 3) {
                            $sql_data = array_merge($sql_data, $this->getGatewayOrderUpdateData($status_response));
                        }
                        $this->model_extension_payment_alfabank->updateGatewayOrder($gateway_order['gateway_order_reference'], $sql_data);
                        $json['history'] = array(
                            'order_status_id' => $this->config->get('payment_alfabank_order_status_reversed_id'),
                            'comment' => $this->language->get('text_success_reverse'),
                            'notify' => 0,
                        );
                    } else {
                        $json['error'] = $response['errorMessage'];
                    }
                }
            }
            $this->response->setOutput(json_encode($json));
            return;
        }
        if (!isset($json['success']) && !isset($json['error'])) {
            $json['error'] = $this->language->get('error_order_missing');
        }
        $this->response->setOutput(json_encode($json));
    }

    private function verifyCurrentUserPassword($password)
    {
        if ($password === '' || !$this->user->isLogged()) {
            return false;
        }

        $this->load->model('user/user');
        $user_info = $this->model_user_user->getUser((int)$this->user->getId());

        return $this->passwordMatchesUser($password, $user_info);
    }

    private function passwordMatchesUser($password, $user_info)
    {
        if (empty($user_info) || empty($user_info['password'])) {
            return false;
        }

        $stored_password = (string)$user_info['password'];
        $salt = isset($user_info['salt']) ? (string)$user_info['salt'] : '';
        $salted_password = sha1($salt . sha1($salt . sha1($password)));

        return hash_equals($stored_password, $salted_password) ||
            hash_equals($stored_password, md5($password));
    }

    private function compare_major_minor($v1, $v2)
    {
        [$x1, $y1] = array_map('intval', explode('.', $v1));
        [$x2, $y2] = array_map('intval', explode('.', $v2));
        if ($x1 > $x2) return 1;
        if ($x1 < $x2) return -1;
        if ($y1 > $y2) return 1;
        if ($y1 < $y2) return -1;
        return 0;
    }
}
