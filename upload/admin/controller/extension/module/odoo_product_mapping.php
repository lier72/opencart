<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 09/12/24
 * Time: 16:45
 * File: controller/extension/module/odoo_product_mapping.php
 */

class ControllerExtensionModuleOdooProductMapping extends Controller {
    private $error = array();

    // Status constants matching model
    const SYNC_STATUS = [
        0 => 'Not Synced',
        1 => 'Synced',
        2 => 'Pending',
        3 => 'Error'
    ];

    public function index() {
        $this->checkInstall();

        $this->load->language('extension/module/odoo_product_mapping');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/odoo_product_mapping');

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/odoo_product_mapping', 'user_token=' . $this->session->data['user_token'], true)
        );

        if (isset($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];
            unset($this->session->data['error']);
        } elseif (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Build status filter options
        $data['sync_statuses'] = self::SYNC_STATUS;

        // Get filter values
        $filter_data = $this->getFilterData();


        $filter_url = '';

        if (isset($this->request->get['filter_product_id'])) {
            $filter_url .= '&filter_product_id=' . urlencode(html_entity_decode($this->request->get['filter_product_id'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_product'])) {
            $filter_url .= '&filter_product=' . urlencode(html_entity_decode($this->request->get['filter_product'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_model'])) {
            $filter_url .= '&filter_model=' . urlencode(html_entity_decode($this->request->get['filter_model'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_odoo_ref'])) {
            $filter_url .= '&filter_odoo_ref=' . urlencode(html_entity_decode($this->request->get['filter_odoo_ref'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_sync_status'])) {
            $filter_url .= '&filter_sync_status=' . $this->request->get['filter_sync_status'];
        }

        if (isset($this->request->get['page'])) {
            $filter_url .= '&page=' . $this->request->get['page'];
        }
//
//// Build sort URLs with only necessary parameters
        $order_type = ($filter_data['order'] == 'ASC') ? 'DESC' : 'ASC';

        $data['sort_product_id'] = $this->url->link('extension/module/odoo_product_mapping',
            'user_token=' . $this->session->data['user_token'] . $filter_url . '&sort=p.product_id&order=' . $order_type, true);
        $data['sort_product'] = $this->url->link('extension/module/odoo_product_mapping',
            'user_token=' . $this->session->data['user_token'] . $filter_url . '&sort=pd.name&order=' . $order_type, true);
        $data['sort_model'] = $this->url->link('extension/module/odoo_product_mapping',
            'user_token=' . $this->session->data['user_token'] . $filter_url . '&sort=p.model&order=' . $order_type, true);
        $data['sort_odoo_ref'] = $this->url->link('extension/module/odoo_product_mapping',
            'user_token=' . $this->session->data['user_token'] . $filter_url . '&sort=opm.odoo_product_id&order=' . $order_type, true);
//
//// Build complete URL for pagination and other purposes
        $url = $filter_url;
        $url .= '&sort=' . $filter_data['sort'];
        $url .= '&order=' . $filter_data['order'];

//
       $data['products'] = array();


        $product_total = $this->model_extension_module_odoo_product_mapping->getTotalMappedProducts($filter_data);
        $results = $this->model_extension_module_odoo_product_mapping->getMappedProducts($filter_data);


        foreach ($results as $result) {
            $res = self::SYNC_STATUS[$result['is_synch']];
            $sync_status = isset($res) ? $res : 'Unknown';
            $status_class = $this->getStatusClass($result['is_synch']);

            $data['products'][] = array(
                'product_id'    => $result['product_id'],
                'name'          => $result['product_name'],
                'model'         => $result['model'],
                'variant'       => $result['option_name'] ? $result['option_name'] . ': ' . $result['option_value_name'] : '',
                'price'         => $this->currency->format($result['price'], $this->config->get('config_currency')),
                'odoo_ref'      => $result['odoo_product_id'] ? $result['odoo_product_id'] : $this->language->get('text_not_mapped'),
                'sync_status'   => $sync_status,
                'status_class'  => $status_class,
                'sync_history' => $this->url->link('extension/module/odoo_product_mapping/syncHistory',
                    'user_token=' . $this->session->data['user_token'] .
                    '&product_id=' . $result['product_id'] .
                    '&return_url=' . rawurlencode($filter_url), true),
//                'last_sync'     => $result['last_sync'] ? date($this->language->get('date_format_short'), strtotime($result['last_sync'])) : $this->language->get('text_never'),
                'sync'          => $this->url->link('extension/module/odoo_product_mapping/sync', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $result['product_id'], true),
                'product_url'    => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $result['product_id'], true)
            );
 //           $this->log->write("Info odoo_product_mappimg index(): model".$data['products']['model']." getMappedProduct last_sync_status: " .
//                serialize($result['last_sync_status']) . ", sync_status: ". $data['products']['sync_status'] );
//            $this->log->write("Info odoo_product_mappimg index(): data: ". serialize($data) );

        }

        $data['heading_title'] = $this->language->get('heading_title');
        // Add pagination
        $pagination = new Pagination();
        $pagination->total = $product_total;
        $pagination->page = $filter_data['page'];
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link('extension/module/odoo_product_mapping', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($filter_data['page'] - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($filter_data['page'] - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($filter_data['page'] - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

        $data['text_list'] = $this->language->get('text_list');
        $data['text_no_results'] = $this->language->get('text_no_results');
        $data['text_confirm'] = $this->language->get('text_confirm');
        $data['text_sync_progress'] = $this->language->get('text_sync_progress');

        $data['column_product_id'] = $this->language->get('column_product_id');
        $data['column_product'] = $this->language->get('column_product');
        $data['column_model'] = $this->language->get('column_model');
        $data['column_variant'] = $this->language->get('column_variant');
        $data['column_price'] = $this->language->get('column_price');
        $data['column_odoo_ref'] = $this->language->get('column_odoo_ref');
        $data['column_sync_status'] = $this->language->get('column_sync_status');
        $data['column_last_sync'] = $this->language->get('column_last_sync');
        $data['column_action'] = $this->language->get('column_action');

        $data['entry_product_id'] = $this->language->get('entry_product_id');
        $data['entry_product'] = $this->language->get('entry_product');
        $data['entry_model'] = $this->language->get('entry_model');
        $data['entry_odoo_ref'] = $this->language->get('entry_odoo_ref');
        $data['entry_sync_status'] = $this->language->get('entry_sync_status');

        $data['button_stock'] = $this->language->get('button_stock');
        $data['button_filter'] = $this->language->get('button_filter');
        $data['button_mass_sync'] = $this->language->get('button_mass_sync');
        $data['button_sync'] = $this->language->get('button_sync');
        $data['button_history'] = $this->language->get('button_history');
        $data['text_not_synced'] = $this->language->get('text_not_synced');
        $data['text_synced'] = $this->language->get('text_synced');
        $data['text_pending'] = $this->language->get('text_pending');
        $data['text_error'] = $this->language->get('text_error');
        $data['user_token'] = $this->session->data['user_token'];

        $data['filter_product_id'] = $filter_data['filter_product_id'];
        $data['filter_product'] = $filter_data['filter_product'];
        $data['filter_model'] = $filter_data['filter_model'];
        $data['filter_odoo_ref'] = $filter_data['filter_odoo_ref'];
        $data['filter_sync_status'] = $filter_data['filter_sync_status'];

        $data['sort'] = $filter_data['sort'];
        $data['order'] = $filter_data['order'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_product_mapping', $data));
    }

    public function checkInstall() {
        $required_connector_tables = array(
            'odoo_config',
            'odoo_product_variant_map',
            'odoo_order_total_map'
        );

        $connector_install_required = false;

        foreach ($required_connector_tables as $table_name) {
            $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . $this->db->escape($table_name) . "'");

            if ($query->num_rows == 0) {
                $connector_install_required = true;
                break;
            }
        }

        if ($connector_install_required) {
            $this->load->model('extension/module/odoo_connector');
            $this->model_extension_module_odoo_connector->install();
        }

        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "odoo_product_sync_log'");

        if ($query->num_rows == 0) {
            $this->load->controller('extension/module/odoo_product_sync/install');
        }

        return true;
    }

    public function sync()
    {
        $this->load->language('extension/module/odoo_product_mapping');
        $json = array();
//        $this->log->write('Controller sync called with product_id: '. $this->request->get['product_id']);

        // Check for explicit AJAX flag
        $is_ajax_request = isset($this->request->post['ajax']) && $this->request->post['ajax'] === '1';
//        $this->log->write('Controller sync called with product_id: '. $this->request->get['product_id']);

        if (!$this->user->hasPermission('modify', 'extension/module/odoo_product_mapping')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            if (isset($this->request->get['product_id'])) {
                $this->load->model('extension/module/odoo_product_mapping');

                try {
                    // Start output buffering to catch any stray output
                    ob_start();

                    $result = $this->model_extension_module_odoo_product_mapping->syncProductToOdoo($this->request->get['product_id']);

                    // Clear any debug output
                    ob_clean();

                    $this->log->write("Info odoo_product_mapping syncProductToOdoo: " . $this->request->get['product_id'] . " \n" . serialize($result));
                    $json['success'] = $this->language->get('text_sync_success');
                } catch (Exception $e) {
                    // Clear any output before error handling
                    ob_clean();

                    // Log error and return user-friendly message
                    $this->log->write('Error syncing product ' . $this->request->get['product_id'] . ': ' . $e->getMessage());
                    $json['error'] = $this->language->get('error_sync_failed');
                    if ($this->user->hasPermission('access', 'tool/error_log')) {
                        $json['error'] = $e->getMessage();
                    }
                }
            }
        }
        // Handle response based on request type
        if ($is_ajax_request) {
            // For AJAX requests, send direct output
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json');
            echo json_encode($json);
            exit();
        } else {
            // For regular requests, use OpenCart's response class
            while (ob_get_level()) {
                ob_end_clean();
            }

            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
        }
    }

    private function getFilterData() {
        $filter_data = array();

        if (isset($this->request->get['filter_product_id'])) {
            $filter_data['filter_product_id'] = $this->request->get['filter_product_id'];
        } else {
            $filter_data['filter_product_id'] = '';
        }

        if (isset($this->request->get['filter_product'])) {
            $filter_data['filter_product'] = $this->request->get['filter_product'];
        } else {
            $filter_data['filter_product'] = '';
        }

        if (isset($this->request->get['filter_model'])) {
            $filter_data['filter_model'] = $this->request->get['filter_model'];
        } else {
            $filter_data['filter_model'] = '';
        }

        if (isset($this->request->get['filter_odoo_ref'])) {
            $filter_data['filter_odoo_ref'] = $this->request->get['filter_odoo_ref'];
        } else {
            $filter_data['filter_odoo_ref'] = '';
        }

        if (isset($this->request->get['filter_sync_status'])) {
            $filter_data['filter_sync_status'] = $this->request->get['filter_sync_status'];
        } else {
            $filter_data['filter_sync_status'] = '';
        }

        if (isset($this->request->get['sort'])) {
            $filter_data['sort'] = $this->request->get['sort'];
        } else {
            $filter_data['sort'] = 'pd.name';
        }

        if (isset($this->request->get['order'])) {
            $filter_data['order'] = $this->request->get['order'];
        } else {
            $filter_data['order'] = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $filter_data['page'] = $this->request->get['page'];
        } else {
            $filter_data['page'] = 1;
        }

        $filter_data['start'] = ($filter_data['page'] - 1) * $this->config->get('config_limit_admin');
        $filter_data['limit'] = $this->config->get('config_limit_admin');

        return $filter_data;
    }

    private function getStatusClass($status) {
        switch ($status) {
            case 0: // not_synced
                return 'label label-default';
            case 1: // synced
                return 'label label-success';
            case 2: // pending
                return 'label label-warning';
            case 3: // error
                return 'label label-danger';
            default:
                return 'label label-default';
        }
    }

    public function massSync() {
        $this->load->language('extension/module/odoo_product_mapping');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/odoo_product_mapping')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            if (isset($this->request->post['selected']) && is_array($this->request->post['selected'])) {
                $this->load->model('extension/module/odoo_product_mapping');

                // Get batch size from settings
                $this->load->model('extension/module/odoo_connector');
                $batch_size = $this->model_extension_module_odoo_connector->getConfig('sync_batch_size') ?: 50;

                try {
                    // Sync only for unique product not every option
                    $product_ids_uniqs = array_unique($this->request->post['selected']);
                    // Start output buffering to catch any stray output
                    ob_start();

                    $result = $this->model_extension_module_odoo_product_mapping->massSyncProductsToOdoo(
                        $product_ids_uniqs,
                        $batch_size
                    );


                    // Clear any debug output
                    ob_clean();

                    $json = array_merge($json, $result);
                    $json['success'] = $this->language->get('text_sync_success');

                } catch (Exception $e) {
                    // Clear any output before error handling
                    ob_clean();
                    $json['error'] = $e->getMessage();
                }
            } else {
                $json['error'] = $this->language->get('error_no_selection');
            }
        }

        // For regular requests, use OpenCart's response class
        while (ob_get_level()) {
            ob_end_clean();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // Shows Individual product syncing information
    public function syncHistory() {
        $this->load->language('extension/module/odoo_product_mapping');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/odoo_product_mapping');

        // Store the referring URL parameters
        $return_url = '';
        if (isset($this->request->get['return_url'])) {
            $decoded_url = rawurldecode($this->request->get['return_url']);
            // Replace any HTML entities back to characters
            $return_url = html_entity_decode($decoded_url);
        }

        if (isset($this->request->get['product_id'])) {
            $product_id = (int)$this->request->get['product_id'];
        } else {
            $this->response->redirect($this->url->link('extension/module/odoo_product_mapping', 'user_token=' . $this->session->data['user_token'], true));
        }

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'created_on';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'DESC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/odoo_product_mapping', 'user_token=' . $this->session->data['user_token'] . $return_url, true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_sync_history'),
            'href' => $this->url->link('extension/module/odoo_product_mapping/syncHistory', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product_id . $url, true)
        );

        $data['back'] = $this->url->link('extension/module/odoo_product_mapping', 'user_token=' . $this->session->data['user_token'] .$return_url, true);

        // Get product info
        $product_info = $this->model_extension_module_odoo_product_mapping->getProductInfo($product_id);

        $product_info['product_url'] = $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product_id , true);

        if (!$product_info) {
            $this->response->redirect($this->url->link('extension/module/odoo_product_mapping', 'user_token=' . $this->session->data['user_token'], true));
        }

        $data['product_info'] = $product_info;

        $data['sync_history'] = array();

        $filter_data = array(
            'sort'  => $sort,
            'order' => $order,
            'start' => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit' => $this->config->get('config_limit_admin')
        );

        $sync_history_total = $this->model_extension_module_odoo_product_mapping->getTotalSyncHistoryEntries($product_id);
        $sync_history = $this->model_extension_module_odoo_product_mapping->getProductSyncHistory($product_id, $filter_data);

        foreach ($sync_history as $history) {
            $data['sync_history'][] = array(
                'created_on' => date($this->language->get('datetime_format'), strtotime($history['created_on'])),
                'sync_direction' => $this->language->get('text_direction_' . $history['sync_direction']),
                'status' => $this->language->get('text_' . $history['status']),
                'message' => $history['message']
            );
        }

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        $pagination = new Pagination();
        $pagination->total = $sync_history_total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link('extension/module/odoo_product_mapping/syncHistory', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product_id . $url . '&page={page}', true);

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_sync_history'] = $this->language->get('text_sync_history');
        $data['button_back'] = $this->language->get('button_back');

        $data['entry_product'] = $this->language->get('entry_product');

        $data['entry_model'] = $this->language->get('entry_model');

        $data['entry_odoo_ref'] = $this->language->get('entry_odoo_ref');

        $data['entry_sync_status'] = $this->language->get('entry_sync_status');

        $data['column_date'] = $this->language->get('column_date');

        $data['column_direction'] = $this->language->get('column_direction');

        $data['column_sync_status'] = $this->language->get('column_sync_status');

        $data['column_message'] = $this->language->get('column_message');
        $data['text_no_results'] = $this->language->get('text_no_results');


        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($sync_history_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($sync_history_total - $this->config->get('config_limit_admin'))) ? $sync_history_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $sync_history_total, ceil($sync_history_total / $this->config->get('config_limit_admin')));

        $data['sort'] = $sort;
        $data['order'] = $order;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_product_sync_history', $data));
    }

    function updateSyncHistory(){
        $this->load->model('extension/module/odoo_product_mapping');
        $this->model_extension_module_odoo_product_mapping->updateSyncStatus();

    }
}
