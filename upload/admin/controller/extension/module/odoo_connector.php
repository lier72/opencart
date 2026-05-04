<?php
/**
 * Created by Maxim Surdu.
 * User: max
 * Date: 29/10/25
 * Time: 12:21 PM
 */
class ControllerExtensionModuleOdooConnector extends Controller {

    const VERSION = 0.3;
    private $error = array();

/*    public function __construct($registry)
    {

        parent::__construct($registry);
    }*/

    public function index()
    {

        $this->checkInstall();

        $this->load->language('extension/module/odoo_connector');

        $this->load->model('extension/module/odoo_connector');
        $this->load->model('catalog/category');


        $this->document->setTitle($this->language->get('heading_title'));

        // Add language variables to data array
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_environment_status'] = $this->language->get('text_environment_status');
        $data['text_test_mode'] = $this->language->get('text_test_mode');
        $data['text_production_mode'] = $this->language->get('text_production_mode');
        $data['text_order_total_mapping'] = $this->language->get('text_order_total_mapping');

        // Form entries
        $data['entry_url'] = $this->language->get('entry_url');
        $data['entry_database'] = $this->language->get('entry_database');
        $data['entry_username'] = $this->language->get('entry_username');
        $data['entry_password'] = $this->language->get('entry_password');
        $data['entry_port'] = $this->language->get('entry_port');
        // Add new language entries
        $data['entry_sync_batch_size'] = $this->language->get('entry_sync_batch_size');
        $data['help_sync_batch_size'] = $this->language->get('help_sync_batch_size');
        $data['entry_debug'] = $this->language->get('entry_debug');
        $data['entry_virtual_available_categories'] = $this->language->get('entry_virtual_available_categories');
        $data['help_virtual_available_categories'] = $this->language->get('help_virtual_available_categories');

        // Buttons
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_test_connection'] = $this->language->get('button_test_connection');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->saveConfig();

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/module/odoo_connector', 'user_token=' . $this->session->data['user_token'], true));
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/odoo_connector', 'user_token=' . $this->session->data['user_token'], true)
        );

        // Load current configuration
        $config_values = $this->model_extension_module_odoo_connector->getConfig();
//        $config_values = $this->model_extension_module_odoo_connector->getUserData();

//        $this->log->write("Info odoo_connector Controller config_data: " . serialize($config_values));

        // Form fields
        $data['odoo_url'] = isset($this->request->post['odoo_url']) ? $this->request->post['odoo_url'] : (isset($config_values['url']) ? $config_values['url'] : '');
        $data['odoo_db_name'] = isset($this->request->post['odoo_db_name']) ? $this->request->post['odoo_db_name'] : (isset($config_values['db_name']) ? $config_values['db_name'] : '');
        $data['odoo_user'] = isset($this->request->post['odoo_user']) ? $this->request->post['odoo_user'] : (isset($config_values['user']) ? $config_values['user'] : '');
        $data['odoo_password'] = isset($this->request->post['odoo_password']) ? $this->request->post['odoo_password'] : (isset($config_values['password']) ? $config_values['password'] : '');
        $data['odoo_port'] = isset($this->request->post['odoo_port']) ? $this->request->post['odoo_port'] : (isset($config_values['port']) ? $config_values['port'] : '');
        $data['sync_batch_size'] = isset($this->request->post['sync_batch_size']) ? $this->request->post['sync_batch_size'] : (isset($config_values['sync_batch_size']) ? $config_values['sync_batch_size'] : 20);
    // Add error for sync_batch_size
        $data['error_sync_batch_size'] = isset($this->error['sync_batch_size']) ?  $this->error['sync_batch_size']: '';
        $data['debug'] = isset($this->request->post['debug']) ? $this->request->post['debug'] : (isset($config_values['debug']) ? $config_values['debug'] : 0);
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $data['selected_virtual_available_category_ids'] = $this->model_extension_module_odoo_connector->normalizeCategoryIds(
                isset($this->request->post['virtual_available_category_ids']) ?
                    $this->request->post['virtual_available_category_ids'] :
                    array()
            );
        } else {
            $data['selected_virtual_available_category_ids'] = $this->model_extension_module_odoo_connector->getVirtualAvailableCategoryIds();
        }
        $data['available_categories'] = $this->model_catalog_category->getCategories(array(
            'sort' => 'name',
            'order' => 'ASC'
        ));

    // Test environment status
    $data['test_environment'] = ModelExtensionModuleOdooConnector::$test_environment;

        // Error handling
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        // Success message
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Actions
        $data['action'] = $this->url->link('extension/module/odoo_connector', 'user_token=' . $this->session->data['user_token'], true);
        $data['test_connection_url'] = $this->url->link('extension/module/odoo_connector/testConnection', 'user_token=' . $this->session->data['user_token'], true);
        $data['order_total_mapping_url'] = $this->url->link('extension/module/odoo_connector/orderTotalMapping', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('extension/module/odoo_connector', 'user_token=' . $this->session->data['user_token'], true);

        $data['user_token'] = $this->session->data['user_token'];

        // Load common templates
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_config', $data));

    }

    /**
     *  Will implement logic to check the insatllation
    */
    function checkInstall(){
//        $this->log->write("Info odoo_connector checkInstall - has been called.");
        $this->load->controller('extension/module/odoo_product_mapping/checkInstall');
        return true;
    }
    public function syncOrderState(){

        $this->load->model('extension/module/odoo_connector');

        // Initialize connection and check if successful
        $this->model_extension_module_odoo_connector->getConfig();
        $connection = $this->model_extension_module_odoo_connector->getConnection();

        if (!$connection || !isset($connection['status']) || !$connection['status']) {
            $json = array(
                'success' => false,
                'errors' => array('Failed to connect to Odoo server. Please check configuration.')
            );
            if (isset($connection['error'])) {
                $json['errors'][] = $connection['error'];
            }
            $this->log->write('Controller odoo_connector syncOrderState: Connection failed - ' . serialize($connection));
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $json = $this->model_extension_module_odoo_connector->syncOpenCartOrderState();

        $this->log->write('Controller odoo_connector syncOrderState $json: ' . serialize($json));
        // Add response headers
        $this->response->addHeader('Content-Type: application/json');
        // Send the JSON response
        $this->response->setOutput(json_encode($json));
    }

    public function createOdooOrder(){
        if (empty($this->request->get['order_id'])) {
            $this->response->redirect($this->url->link('extension/module/odoo_connector', 'user_token=' . $this->session->data['user_token'], 'SSL'));
        }
        $order_id = $this->request->get['order_id'];
//        var_dump($order_id);
        $this->load->model('extension/module/odoo_connector');

        // Initialize connection and check if successful
        $this->model_extension_module_odoo_connector->getConfig();
        $connection = $this->model_extension_module_odoo_connector->getConnection();

        if (!$connection || !isset($connection['status']) || !$connection['status']) {
            $json = array(
                'success' => false,
                'errors' => array('Failed to connect to Odoo server. Please check configuration.')
            );
            if (isset($connection['error'])) {
                $json['errors'][] = $connection['error'];
            }
            $this->log->write('Controller odoo_connector createOdooOrder: Connection failed - ' . serialize($connection));
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $result = $this->model_extension_module_odoo_connector->createOdooOrder($order_id);
        $this->log->write('Controller odoo_connector createOdooOrder $result: '. serialize($result));

        // Transform response format to match JavaScript expectations
        $json = array();

        if (!empty($result['errors']) && is_array($result['errors'])) {
            // If there are errors, set error message
            $json['error'] = implode(' ', $result['errors']);
        } elseif (!empty($result['success']) && !empty($result['message'])) {
            // If successful, set success message
            $json['success'] = $result['message'];
        } elseif (!empty($result['success'])) {
            // Success but no specific message
            $json['success'] = 'Order created successfully in Odoo!';
        } else {
            // Unknown error
            $json['error'] = 'Unknown error occurred while creating order in Odoo.';
        }

        // Add response headers
        $this->response->addHeader('Content-Type: application/json');
        // Send the JSON response
        $this->response->setOutput(json_encode($json));
    }

    private function validateForm() {
        if (!$this->user->hasPermission('modify', 'extension/module/odoo_connector')) {
            $this->error['warning'] = $this->language->get('error_permission');
            return false;
        }

        if (empty($this->request->post['odoo_url'])) {
            $this->error['warning'] = $this->language->get('error_url_required');
            return false;
        }

        if (empty($this->request->post['odoo_db_name'])) {
            $this->error['warning'] = $this->language->get('error_db_required');
            return false;
        }

        if (empty($this->request->post['odoo_user'])) {
            $this->error['warning'] = $this->language->get('error_user_required');
            return false;
        }

        if (empty($this->request->post['odoo_password'])) {
            $this->error['warning'] = $this->language->get('error_password_required');
            return false;
        }
        // Add error for sync_batch_size
        if (isset($this->error['sync_batch_size'])) {
            $data['error_sync_batch_size'] = $this->error['sync_batch_size'];
        } else {
            $data['error_sync_batch_size'] = '';
        }

        return true;
    }

    private function saveConfig() {
        $category_ids = $this->model_extension_module_odoo_connector->normalizeCategoryIds(
            isset($this->request->post['virtual_available_category_ids']) ?
                $this->request->post['virtual_available_category_ids'] :
                array()
        );

        $config_data = array(
            'url' => $this->request->post['odoo_url'],
            'db_name' => $this->request->post['odoo_db_name'],
            'user' => $this->request->post['odoo_user'],
            'password' => $this->request->post['odoo_password'],
            'port' => $this->request->post['odoo_port'],
            'sync_batch_size' => max(1, min(100, (int)$this->request->post['sync_batch_size'])),
            'debug' => $this->request->post['debug'],
            ModelExtensionModuleOdooConnector::CONFIG_KEY_VIRTUAL_AVAILABLE_CATEGORY_IDS => implode(',', $category_ids),
        );

        $managed_keys = array();

        foreach ($this->model_extension_module_odoo_connector->getManagedConfigKeys() as $key) {
            $managed_keys[] = "'" . $this->db->escape($key) . "'";
        }

        if ($managed_keys) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "odoo_config
                WHERE `key` IN (" . implode(',', $managed_keys) . ")");
        }

        foreach ($config_data as $key => $value) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "odoo_config SET 
            `key` = '" . $this->db->escape($key) . "', 
            `value` = '" . $this->db->escape($value) . "'");
        }
    }
    public function testConnection(){
//        $this->log->write("testConnection called with method: " . $this->request->server['REQUEST_METHOD']);
//        $this->log->write("POST data: " . print_r($this->request->post, true));
        $this->load->model('extension/module/odoo_connector');
        $json = $this->model_extension_module_odoo_connector->testConnection($this->request->post);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Payment Acquirer Mapping Management
     */
    public function paymentMapping() {
        $this->load->language('extension/module/odoo_connector');
        $this->load->model('extension/module/odoo_connector');

        $this->document->setTitle('Odoo Payment Acquirer Mapping');

        $data['heading_title'] = 'Odoo Payment Acquirer Mapping';
        $data['text_list'] = 'Payment Acquirer Mappings';
        $data['text_no_results'] = 'No mappings found';
        $data['text_confirm'] = 'Are you sure?';

        $data['column_oc_code'] = 'OpenCart Payment Code';
        $data['column_oc_name'] = 'OpenCart Payment Name';
        $data['column_odoo_id'] = 'Odoo Acquirer ID';
        $data['column_odoo_name'] = 'Odoo Acquirer Name';
        $data['column_active'] = 'Active';
        $data['column_action'] = 'Action';

        $data['button_edit'] = 'Edit';
        $data['button_save'] = 'Save';
        $data['button_cancel'] = 'Cancel';

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => 'Home',
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => 'Odoo Connector',
            'href' => $this->url->link('extension/module/odoo_connector', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => 'Payment Mapping',
            'href' => $this->url->link('extension/module/odoo_connector/paymentMapping', 'user_token=' . $this->session->data['user_token'], true)
        );

        // Get mappings
        $data['mappings'] = $this->model_extension_module_odoo_connector->getPaymentAcquirerMappings();

        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_payment_mapping', $data));
    }

    /**
     * Currency Mapping Management
     */
    public function currencyMapping() {
        $this->load->language('extension/module/odoo_connector');
        $this->load->model('extension/module/odoo_connector');

        $this->document->setTitle('Odoo Currency Mapping');

        $data['heading_title'] = 'Odoo Currency Mapping';
        $data['text_list'] = 'Currency Mappings';
        $data['text_no_results'] = 'No mappings found';
        $data['text_confirm'] = 'Are you sure?';

        $data['column_oc_code'] = 'OpenCart Currency Code';
        $data['column_oc_name'] = 'OpenCart Currency Name';
        $data['column_odoo_id'] = 'Odoo Currency ID';
        $data['column_odoo_name'] = 'Odoo Currency Name';
        $data['column_active'] = 'Active';
        $data['column_action'] = 'Action';

        $data['button_edit'] = 'Edit';
        $data['button_save'] = 'Save';
        $data['button_cancel'] = 'Cancel';

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => 'Home',
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => 'Odoo Connector',
            'href' => $this->url->link('extension/module/odoo_connector', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => 'Currency Mapping',
            'href' => $this->url->link('extension/module/odoo_connector/currencyMapping', 'user_token=' . $this->session->data['user_token'], true)
        );

        // Get mappings
        $data['mappings'] = $this->model_extension_module_odoo_connector->getCurrencyMappings();

        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_currency_mapping', $data));
    }

    public function orderTotalMapping() {
        $this->load->language('extension/module/odoo_connector');
        $this->load->model('extension/module/odoo_connector');

        $this->document->setTitle($this->language->get('text_order_total_mapping'));

        $data['heading_title'] = $this->language->get('text_order_total_mapping');
        $data['text_list'] = $this->language->get('text_order_total_mapping_list');
        $data['text_no_results'] = $this->language->get('text_no_results');
        $data['text_confirm'] = $this->language->get('text_confirm');
        $data['text_order_total_mapping_help'] = $this->language->get('text_order_total_mapping_help');

        $data['column_total_code'] = $this->language->get('column_total_code');
        $data['column_include_pattern'] = $this->language->get('column_include_pattern');
        $data['column_exclude_pattern'] = $this->language->get('column_exclude_pattern');
        $data['column_odoo_product_id'] = $this->language->get('column_odoo_product_id');
        $data['column_odoo_product_name'] = $this->language->get('column_odoo_product_name');
        $data['column_priority'] = $this->language->get('column_priority');
        $data['column_active'] = $this->language->get('column_active');
        $data['column_action'] = $this->language->get('column_action');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_add_mapping'] = $this->language->get('button_add_mapping');
        $data['button_delete'] = $this->language->get('button_delete');

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/odoo_connector', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_order_total_mapping'),
            'href' => $this->url->link('extension/module/odoo_connector/orderTotalMapping', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['mappings'] = $this->model_extension_module_odoo_connector->getOrderTotalMappings();
        $data['create_mapping_url'] = $this->url->link('extension/module/odoo_connector/createOrderTotalMapping', 'user_token=' . $this->session->data['user_token'], true);
        $data['update_mapping_url'] = $this->url->link('extension/module/odoo_connector/updateOrderTotalMapping', 'user_token=' . $this->session->data['user_token'], true);
        $data['delete_mapping_url'] = $this->url->link('extension/module/odoo_connector/deleteOrderTotalMapping', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('extension/module/odoo_connector', 'user_token=' . $this->session->data['user_token'], true);

        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_order_total_mapping', $data));
    }

    private function sendJsonResponse($json) {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Update payment acquirer mapping via AJAX
     */
    public function updatePaymentMapping() {
        $this->load->model('extension/module/odoo_connector');

        $json = array('success' => false);

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $result = $this->model_extension_module_odoo_connector->updatePaymentAcquirerMapping($this->request->post);
            $json = $result;
        }

        $this->sendJsonResponse($json);
    }

    /**
     * Update currency mapping via AJAX
     */
    public function updateCurrencyMapping() {
        $this->load->model('extension/module/odoo_connector');

        $json = array('success' => false);

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $result = $this->model_extension_module_odoo_connector->updateCurrencyMapping($this->request->post);
            $json = $result;
        }

        $this->sendJsonResponse($json);
    }

    public function createOrderTotalMapping() {
        $this->load->language('extension/module/odoo_connector');
        $this->load->model('extension/module/odoo_connector');

        $json = array('success' => false);

        if (!$this->user->hasPermission('modify', 'extension/module/odoo_connector')) {
            $json['error'] = $this->language->get('error_permission');
        } elseif ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $json = $this->model_extension_module_odoo_connector->createOrderTotalMapping($this->request->post);
        } else {
            $json['error'] = $this->language->get('error_invalid_request');
        }

        $this->sendJsonResponse($json);
    }

    public function updateOrderTotalMapping() {
        $this->load->language('extension/module/odoo_connector');
        $this->load->model('extension/module/odoo_connector');

        $json = array('success' => false);

        if (!$this->user->hasPermission('modify', 'extension/module/odoo_connector')) {
            $json['error'] = $this->language->get('error_permission');
        } elseif ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $json = $this->model_extension_module_odoo_connector->updateOrderTotalMapping($this->request->post);
        } else {
            $json['error'] = $this->language->get('error_invalid_request');
        }

        $this->sendJsonResponse($json);
    }

    public function deleteOrderTotalMapping() {
        $this->load->language('extension/module/odoo_connector');
        $this->load->model('extension/module/odoo_connector');

        $json = array('success' => false);

        if (!$this->user->hasPermission('modify', 'extension/module/odoo_connector')) {
            $json['error'] = $this->language->get('error_permission');
        } elseif ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $mapping_id = isset($this->request->post['id']) ? (int)$this->request->post['id'] : 0;
            $json = $this->model_extension_module_odoo_connector->deleteOrderTotalMapping($mapping_id);
        } else {
            $json['error'] = $this->language->get('error_invalid_request');
        }

        $this->sendJsonResponse($json);
    }

    /**
     * Fetch payment acquirers from Odoo via AJAX
     */
    public function fetchOdooAcquirers() {
        $this->load->model('extension/module/odoo_connector');

        $json = $this->model_extension_module_odoo_connector->fetchOdooPaymentAcquirers();

        $this->sendJsonResponse($json);
    }

    /**
     * Fetch currencies from Odoo via AJAX
     */
    public function fetchOdooCurrencies() {
        $this->load->model('extension/module/odoo_connector');

        $json = $this->model_extension_module_odoo_connector->fetchOdooCurrencies();

        $this->sendJsonResponse($json);
    }

}
