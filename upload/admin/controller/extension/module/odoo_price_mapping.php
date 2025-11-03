<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 17/01/25
 * Time: 14:33
 */
// controller/extension/module/odoo_price_mapping.php
class ControllerExtensionModuleOdooPriceMapping extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/odoo_price_mapping');
        $this->document->setTitle($this->language->get('heading_title'));

        // Set translation data
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_list'] = $this->language->get('text_list');
        $data['text_confirm'] = $this->language->get('text_confirm');
        $data['text_no_results'] = $this->language->get('text_no_results');

        $data['column_odoo_pricelist'] = $this->language->get('column_odoo_pricelist');
        $data['column_customer_group'] = $this->language->get('column_customer_group');
        $data['column_status'] = $this->language->get('column_status');
        $data['text_active'] = $this->language->get('text_active');
        $data['text_inactive']= $this->language->get('text_inactive');
        $data['column_pricelist_id'] = $this->language->get('column_pricelist_id');
        $data['column_pricelist_name'] = $this->language->get('column_pricelist_name');
        $data['column_last_sync'] = $this->language->get('column_last_sync');
        $data['column_action'] = $this->language->get('column_action');
        $data['text_available_pricelists'] = $this->language->get('text_available_pricelists');
        // Buttons
        $data['button_edit'] = $this->language->get('button_edit');
        $data['button_history'] = $this->language->get('button_history');
        $data['button_add'] = $this->language->get('button_add');
        $data['button_sync'] = $this->language->get('button_sync');
        $data['button_delete'] = $this->language->get('button_delete');

        $this->load->model('extension/module/odoo_price_sync');

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/odoo_price_mapping', 'user_token=' . $this->session->data['user_token'], true)
        );

        // Get existing mappings
        $data['mappings'] = $this->model_extension_module_odoo_price_sync->getPricelistMappings();
        foreach ($data['mappings'] as &$mapping){
            $mapping['edit'] = $this->url->link('extension/module/odoo_price_mapping/edit&mapping_id='. $mapping['id'], 'user_token=' . $this->session->data['user_token'], true);
            $mapping['history'] = $this->url->link('extension/module/odoo_price_mapping/history&mapping_id='. $mapping['id'], 'user_token=' . $this->session->data['user_token'], true);
        }
        //var_dump($data['mappings']);

        // Get available Odoo pricelists
        $odoo_pricelists = $this->model_extension_module_odoo_price_sync->getAvailableOdooPricelists();
        $data['odoo_pricelists'] = $odoo_pricelists;

        // Get OpenCart customer groups
        $this->load->model('customer/customer_group');
        $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

        // Action URLs
        $data['add'] = $this->url->link('extension/module/odoo_price_mapping/add', 'user_token=' . $this->session->data['user_token'], true);
        $data['delete'] = $this->url->link('extension/module/odoo_price_mapping/delete', 'user_token=' . $this->session->data['user_token'], true);
        $data['sync'] = $this->url->link('extension/module/odoo_price_mapping/sync', 'user_token=' . $this->session->data['user_token'], true);
        $data['sync_url'] = html_entity_decode($this->url->link('extension/module/odoo_price_mapping/sync', 'user_token=' . $this->session->data['user_token'], true));

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_price_mapping_list', $data));
    }

    public function add() {
        $this->load->language('extension/module/odoo_price_mapping');
        $this->document->setTitle($this->language->get('heading_title'));

        // Prepare data array for form
        $data = array();
        $data['error_permission'] = $this->language->get('error_permission');
//        $data['error_pricelist'] = $this->language->get('error_pricelist');
//        $data['error_customer_group'] = $this->language->get('error_customer_group');
        $data['entry_odoo_pricelist'] = $this->language->get('entry_odoo_pricelist');
        $data['entry_customer_group'] = $this->language->get('entry_customer_group');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['text_select'] = $this->language->get('text_select');
        $data['text_active'] = $this->language->get('text_active');
        $data['text_inactive'] = $this->language->get('text_inactive');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
            $this->load->model('extension/module/odoo_price_sync');
            $this->load->model('user/user');
            $creator = $this->model_user_user->getUser($this->user->getId());
//            var_dump($creator);
            // Get the pricelist name from Odoo data
            $odoo_pricelists = $this->model_extension_module_odoo_price_sync->getAvailableOdooPricelists();
            $pricelist_name = '';
            foreach ($odoo_pricelists as $pricelist) {
                if ($pricelist['pricelist_id'] == $this->request->post['odoo_pricelist_id']) {
                    $pricelist_name = $pricelist['pricelist_name'];
                    break;
                }
            }
            $mapping_data = array(
                'odoo_pricelist_id' => $this->request->post['odoo_pricelist_id'],
                'opencart_customer_group_id' => $this->request->post['customer_group_id'],
                'odoo_pricelist_name' => $pricelist_name,
                'price_type' => 'discount',
                'sync_direction' => 'bidirectional',
                'is_active' => isset($this->request->post['is_active']) ? (int) $this->request->post['is_active'] : 0,
                'created_by' => $creator['username'],
            );

            $this->model_extension_module_odoo_price_sync->createPricelistMapping($mapping_data);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/odoo_price_mapping', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->getForm($data);
    }

    public function edit() {
        $this->load->language('extension/module/odoo_price_mapping');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('extension/module/odoo_price_sync');

        $mapping_id = $this->request->get['mapping_id'];


        // Error
        $data['error_permission']     = $this->language->get('error_permission');
//        $data['error_pricelist']      = $this->language->get('error_pricelist');
//        $data['error_customer_group'] = $this->language->get('error_customer_group');
        // Entry
        $data['entry_odoo_pricelist']  =  $this->language->get('entry_odoo_pricelist');
        $data['entry_customer_group']  =  $this->language->get('entry_customer_group');
        $data['entry_status']         =  $this->language->get('entry_status');
        // Text
        $data['text_select']          =  $this->language->get('text_select');
        $data['text_active'] = $this->language->get('text_active');
        $data['text_inactive']= $this->language->get('text_inactive');

        // Button
        $data['button_save']          = $this->language->get('button_save');
        $data['button_cancel']        = $this->language->get('button_cancel');

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {

            try {
                // Get current mapping to preserve existing data
                $current_mapping = $this->model_extension_module_odoo_price_sync->getPricelistMapping($mapping_id);

                // Get fresh pricelist data from Odoo
                $odoo_pricelists = $this->model_extension_module_odoo_price_sync->getAvailableOdooPricelists();

                // Determine pricelist name (get from Odoo if available, otherwise keep as is)
                $pricelist_name = $current_mapping['odoo_pricelist_name'];
                if (isset($this->request->post['odoo_pricelist_id'])) {
                    foreach ($odoo_pricelists as $pricelist) {
                        if ($pricelist['pricelist_id'] == $this->request->post['odoo_pricelist_id']) {
                            $pricelist_name = $pricelist['pricelist_name'];
                            break;
                        }
                    }
                }

                $mapping_data = array(
                    'odoo_pricelist_id' => isset($this->request->post['odoo_pricelist_id']) ? (int)$this->request->post['odoo_pricelist_id'] : $current_mapping['odoo_pricelist_id'],
                    'opencart_customer_group_id' => isset($this->request->post['customer_group_id']) ? (int)$this->request->post['customer_group_id'] : $current_mapping['opencart_customer_group_id'],
                    'odoo_pricelist_name' => $pricelist_name,
                    'is_active' => isset($this->request->post['is_active']) ? (int)$this->request->post['is_active'] : 0
                );

                $this->model_extension_module_odoo_price_sync->updatePricelistMapping($mapping_id, $mapping_data);

                $this->log->write("Successfully updated mapping ID " . $mapping_id . " with data: " . json_encode($mapping_data));

                $this->session->data['success'] = $this->language->get('text_success');
                $this->response->redirect($this->url->link('extension/module/odoo_price_mapping', 'user_token=' . $this->session->data['user_token'], true));
            } catch (Exception $e) {
                $this->log->write("Error updating price mapping: " . $e->getMessage());
                $this->error['warning'] = $this->language->get('error_update');
            }
        }

        $this->getForm($data);
    }

    public function delete() {
        $this->load->language('extension/module/odoo_price_mapping');

        if (isset($this->request->post['selected'])) {
            $this->load->model('extension/module/odoo_price_sync');

            foreach ($this->request->post['selected'] as $mapping_id) {
                $this->model_extension_module_odoo_price_sync->deletePricelistMapping($mapping_id);
            }

            $this->session->data['success'] = $this->language->get('text_success');
        }

        $this->response->redirect($this->url->link('extension/module/odoo_price_mapping', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function history() {
        $this->load->language('extension/module/odoo_price_mapping');
        $this->document->setTitle($this->language->get('heading_title'));

        // Initialize parameters array
        $params = array();
        $data = array();

        // Determine context (mapping or product)
        if (isset($this->request->get['mapping_id'])) {
            $params['mapping_id'] = $this->request->get['mapping_id'];
        } elseif (isset($this->request->get['product_id'])) {
            $params['product_id'] = $this->request->get['product_id'];
            // Load customer groups when viewing product history
            $this->load->model('customer/customer_group');
            $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
//            $this->log->write('Function history $data[customer_groups]' . serialize($data['customer_groups']));
        } else {
            $this->response->redirect($this->url->link('extension/module/odoo_price_mapping', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->load->model('extension/module/odoo_price_sync');

        // Get filter values
        $filter_data = array();
        $url = '';

        if (isset($this->request->get['filter_product_id'])) {
            $filter_data['filter_product_id'] = $this->request->get['filter_product_id'];
            $url .= '&filter_product_id=' . urlencode($this->request->get['filter_product_id']);
        }

        if (isset($this->request->get['filter_product_name'])) {
            $filter_data['filter_product_name'] = $this->request->get['filter_product_name'];
            $url .= '&filter_product_name=' . urlencode($this->request->get['filter_product_name']);
        }

        if (isset($this->request->get['filter_status'])) {
            $filter_data['filter_status'] = $this->request->get['filter_status'];
            $url .= '&filter_status=' . urlencode($this->request->get['filter_status']);
        }

        if (isset($this->request->get['filter_pricelist'])) {
            $filter_data['filter_pricelist'] = $this->request->get['filter_pricelist'];
            $url .= '&filter_pricelist=' . urlencode($this->request->get['filter_pricelist']);
        }

        if (isset($this->request->get['filter_model'])) {
            $filter_data['filter_model'] = $this->request->get['filter_model'];
            $url .= '&filter_model=' . urlencode($this->request->get['filter_model']);
        }

        // Add customer group filter for product context
        if (isset($params['product_id']) && isset($this->request->get['filter_customer_group_id'])) {
            $filter_data['filter_customer_group_id'] = $this->request->get['filter_customer_group_id'];
            $url .= '&filter_customer_group_id=' . urlencode($this->request->get['filter_customer_group_id']);
        }


        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        // pl_page could be given from product list view and used to form breadcrumbs to point back to the page it was called from
        if (isset($this->request->get['pl_page'])) {
            $data['pl_page'] = $this->request->get['pl_page'];
            $url .= '&pl_page=' . urlencode($this->request->get['pl_page']);
        }


        // Add pagination to filter data
        $filter_data['start'] = ($page - 1) * $this->config->get('config_limit_admin');
        $filter_data['limit'] = $this->config->get('config_limit_admin');

        // Get data
        $data['sync_history'] = $this->model_extension_module_odoo_price_sync->getSyncHistory($params, $filter_data);
        $total = $this->model_extension_module_odoo_price_sync->getSyncHistoryTotal($params, $filter_data);

        // Make context ID available to template
        if (isset($this->request->get['mapping_id'])) {
            $data['mapping_id'] = $this->request->get['mapping_id'];
        } elseif (isset($this->request->get['product_id'])) {
            $data['product_id'] = $this->request->get['product_id'];
        }

        // Load language strings
        $this->loadLanguageStrings($data);

        // Get context specific data
        if (isset($params['mapping_id'])) {
            $this->loadMappingContext($data, $params['mapping_id']);
        } else {
            $this->loadProductContext($data, $params['product_id']);
        }

        // Common data
        $data['filters'] = $filter_data;
        $data['user_token'] = $this->session->data['user_token'];

        // Pagination
        $this->buildPagination($data, $total, $page, $url, $params);

        // Common template data
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_price_mapping_history', $data));
    }

    protected function loadLanguageStrings(&$data) {
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_history_list'] = $this->language->get('text_history_list');
        $data['text_no_results'] = $this->language->get('text_no_results');
        $data['text_all'] = $this->language->get('text_all');

        // Column Headers
        $data['column_product'] = $this->language->get('column_product');
        $data['column_product_name'] = $this->language->get('column_product_name');
        $data['column_old_price'] = $this->language->get('column_old_price');
        $data['column_new_price'] = $this->language->get('column_new_price');
        $data['column_sync_direction'] = $this->language->get('column_sync_direction');
        $data['column_status'] = $this->language->get('column_status');
        $data['column_message'] = $this->language->get('column_message');
        $data['column_date'] = $this->language->get('column_date');
        $data['column_customer_group'] = $this->language->get('column_customer_group');

        // Direction and Status labels
        $data['text_direction_to_odoo'] = $this->language->get('text_direction_to_odoo');
        $data['text_direction_from_odoo'] = $this->language->get('text_direction_from_odoo');
        $data['text_status_synced'] = $this->language->get('text_status_synced');
        $data['text_status_pending'] = $this->language->get('text_status_pending');
        $data['text_status_error'] = $this->language->get('text_status_error');

        $data['button_filter'] = $this->language->get('button_filter');

        // Entry labels
        $data['entry_product_id'] = $this->language->get('entry_product_id');
        $data['entry_product_name'] = $this->language->get('entry_product_name');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_pricelist'] = $this->language->get('entry_pricelist');
        $data['entry_model'] = $this->language->get('entry_model');
        $data['entry_customer_group'] = $this->language->get('entry_customer_group'); // Added
    }

    protected function loadMappingContext(&$data, $mapping_id) {
        // Get pricelists for filter
        $data['pricelists'] = $this->model_extension_module_odoo_price_sync->getPricelistMappings();

        // Get current pricelist name
        foreach ($data['pricelists'] as $pricelist) {
            if ($pricelist['id'] == $mapping_id) {
                $data['text_history_list'] .= " " . $pricelist['odoo_pricelist_name'];
                break;
            }
        }

        // Build breadcrumbs
        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
            ),
            array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/odoo_price_mapping', 'user_token=' . $this->session->data['user_token'], true)
            ),
            array(
                'text' => $this->language->get('heading_history'),
                'href' => $this->url->link('extension/module/odoo_price_mapping/history', 'user_token=' . $this->session->data['user_token'] . '&mapping_id=' . $mapping_id, true)
            )
        );
    }

    protected function loadProductContext(&$data, $product_id) {
        // Load product information
        $this->load->model('catalog/product');
        $product_info = $this->model_catalog_product->getProduct($product_id);

        if ($product_info) {
            $data['text_history_list'] .= " " . $product_info['name'];
        }

        $pl_url ='';
        if(isset($data['pl_page'])){
            $pl_url = '&page=' . $data['pl_page'];
        }


        // Build breadcrumbs for product context
        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
            ),
            array(
                'text' => $this->language->get('text_product'),
                'href' => $this->url->link('catalog/product', 'user_token=' . $this->session->data['user_token'] . $pl_url, true)
            ),
            array(
                'text' => $product_info['name'],
                'href' => $this->url->link('catalog/product/edit', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product_id, true)
            ),
            array(
                'text' => $this->language->get('heading_history'),
                'href' => $this->url->link('extension/module/odoo_price_mapping/history', 'user_token=' . $this->session->data['user_token'] . '&product_id=' . $product_id, true)
            )
        );
    }

    protected function buildPagination(&$data, $total, $page, $url, $params) {
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');

        // Build URL based on context
        $base_url = 'index.php?route=extension/module/odoo_price_mapping/history&user_token=' . $this->session->data['user_token'];
        if (isset($params['mapping_id'])) {
            $base_url .= '&mapping_id=' . $params['mapping_id'];
        } elseif (isset($params['product_id'])) {
            $base_url .= '&product_id=' . $params['product_id'];
        }

        $pagination->url = $base_url . $url . '&page={page}';

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'),
            ($total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0,
            ((($page - 1) * $this->config->get('config_limit_admin')) > ($total - $this->config->get('config_limit_admin'))) ? $total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')),
            $total,
            ceil($total / $this->config->get('config_limit_admin'))
        );
    }

    //    public function history() {
//        $this->load->language('extension/module/odoo_price_mapping');
//        $this->document->setTitle($this->language->get('heading_title'));
//
//        if (isset($this->request->get['mapping_id'])) {
//            $this->load->model('extension/module/odoo_price_sync');
//
//            $data['sync_history'] = $this->model_extension_module_odoo_price_sync->getSyncHistory($this->request->get['mapping_id']);
//
//            $data['heading_title'] = $this->language->get('heading_history');
//
//            $data['header'] = $this->load->controller('common/header');
//            $data['column_left'] = $this->load->controller('common/column_left');
//            $data['footer'] = $this->load->controller('common/footer');
//
//            $this->response->setOutput($this->load->view('extension/module/odoo_price_mapping_history', $data));
//        } else {
//            $this->response->redirect($this->url->link('extension/module/odoo_price_mapping', 'user_token=' . $this->session->data['user_token'], true));
//        }
//    }

    protected function getForm($data=array()) {
        $data['heading_title'] = $this->language->get('heading_title');

        $data['text_form'] = !isset($this->request->get['mapping_id']) ?
            $this->language->get('text_add') : $this->language->get('text_edit');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['pricelist'])) {
            $data['error_pricelist'] = $this->error['pricelist'];
        } else {
            $data['error_pricelist'] = '';
        }

        if (isset($this->error['customer_group'])) {
            $data['error_customer_group'] = $this->error['customer_group'];
        } else {
            $data['error_customer_group'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/odoo_price_mapping', 'user_token=' . $this->session->data['user_token'], true)
        );

        if (!isset($this->request->get['mapping_id'])) {
            $data['action'] = $this->url->link('extension/module/odoo_price_mapping/add', 'user_token=' . $this->session->data['user_token'], true);
        } else {
            $data['action'] = $this->url->link('extension/module/odoo_price_mapping/edit', 'user_token=' . $this->session->data['user_token'] . '&mapping_id=' . $this->request->get['mapping_id'], true);
        }

        $data['cancel'] = $this->url->link('extension/module/odoo_price_mapping', 'user_token=' . $this->session->data['user_token'], true);

        $this->load->model('extension/module/odoo_price_sync');

        $mapping_info = null;
        if (isset($this->request->get['mapping_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
            $mapping_info = $this->model_extension_module_odoo_price_sync->getPricelistMapping($this->request->get['mapping_id']);
        }

        // Get Odoo pricelists
        $odoo_pricelists = $this->model_extension_module_odoo_price_sync->getAvailableOdooPricelists();

        // If editing and the stored pricelist is not in the available list, add it with a warning
        if ($mapping_info && !empty($mapping_info['odoo_pricelist_id'])) {
            $pricelist_exists = false;
            foreach ($odoo_pricelists as $pricelist) {
                if ($pricelist['pricelist_id'] == $mapping_info['odoo_pricelist_id']) {
                    $pricelist_exists = true;
                    break;
                }
            }

            if (!$pricelist_exists) {
                // Add the stored pricelist to the list with a warning indicator
                array_unshift($odoo_pricelists, array(
                    'pricelist_id' => $mapping_info['odoo_pricelist_id'],
                    'pricelist_name' => $mapping_info['odoo_pricelist_name'] . ' [NOT AVAILABLE IN ODOO]'
                ));
            }
        }

        $data['odoo_pricelists'] = $odoo_pricelists;

        // Get OpenCart customer groups
        $this->load->model('customer/customer_group');
        $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

        if (isset($this->request->post['odoo_pricelist_id'])) {
            $data['odoo_pricelist_id'] = $this->request->post['odoo_pricelist_id'];
        } elseif (!empty($mapping_info)) {
            $data['odoo_pricelist_id'] = $mapping_info['odoo_pricelist_id'];
        } else {
            $data['odoo_pricelist_id'] = '';
        }

        if (isset($this->request->post['customer_group_id'])) {
            $data['customer_group_id'] = $this->request->post['customer_group_id'];
        } elseif (!empty($mapping_info)) {
            $data['customer_group_id'] = $mapping_info['opencart_customer_group_id'];
        } else {
            $data['customer_group_id'] = '';
        }

        if (isset($this->request->post['is_active'])) {
            $data['is_active'] = $this->request->post['is_active'];
        } elseif (!empty($mapping_info)) {
            $data['is_active'] = $mapping_info['is_active'];
        } else {
            $data['is_active'] = 1;
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_price_mapping_form', $data));
    }

    protected function validateForm() {
        if (empty($this->request->post['odoo_pricelist_id'])) {
            $this->error['warning'] = $this->language->get('error_pricelist');
            $this->error['pricelist'] = $this->language->get('error_pricelist');
            $this->log->write("Validation failed: odoo_pricelist_id is empty. POST data: " . json_encode($this->request->post));
        }

        if (empty($this->request->post['customer_group_id'])) {
            $this->error['warning'] = $this->language->get('error_customer_group');
            $this->error['customer_group'] = $this->language->get('error_customer_group');
            $this->log->write("Validation failed: customer_group_id is empty. POST data: " . json_encode($this->request->post));
        }

        return empty($this->error);
    }


//    public function sync(){
//        $this->load->model('extension/module/odoo_price_sync');
//        $stats = $this->model_extension_module_odoo_price_sync->processOdooPrices();
//    }


    public function sync() {
        $this->load->language('extension/module/odoo_price_mapping');

        // Ensure no output before headers
        if (ob_get_level()) {
            ob_end_clean();
        }

        $json = array();

        try {
            $this->load->model('extension/module/odoo_price_sync');
            $stats = $this->model_module_odoo_price_sync->processOdooPrices();

            // Format success message
            $json['success'] = true;
            $json['message'] = $this->formatSyncMessage($stats);
            $json['stats'] = $stats;

        } catch (Exception $e) {
            $this->log->write('Error syncing prices with Odoo: ' . $e->getMessage());
            $json['error'] = sprintf($this->language->get('error_sync'), $e->getMessage());
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function formatSyncMessage($stats) {
        $message = sprintf(
            $this->language->get('text_sync_stats'),
            $stats['processed_pricelists'],
            $stats['total_pricelists'],
            $stats['processed_products'],
            $stats['total_products'],
            isset($stats['total_products']) && $stats['total_products'] > 0
                ? round(($stats['processed_products'] / $stats['total_products']) * 100)
                : 0
        );

        if ($stats['failed_products'] > 0) {
            $message .= sprintf($this->language->get('text_sync_failures'), $stats['failed_products']);
        }

        if (!empty($stats['errors'])) {
            $message .= "\n" . $this->language->get('text_sync_errors') . "\n";
            $message .= implode("\n", array_slice($stats['errors'], 0, 5));
            if (count($stats['errors']) > 5) {
                $message .= sprintf($this->language->get('text_more_errors'), count($stats['errors']) - 5);
            }
        }

        return $message;
    }

    public function install(){

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "odoo_pricelist_map` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `odoo_pricelist_id` int(11) NOT NULL,
        `opencart_customer_group_id` int(11) NOT NULL,
        `odoo_pricelist_name` varchar(255) NOT NULL COMMENT 'Name from Odoo pricelist',
        `price_type` varchar(20) NOT NULL COMMENT 'special/discount',
        `sync_direction` varchar(20) DEFAULT 'bidirectional',
        `is_active` tinyint(1) DEFAULT 1,
        `created_by` varchar(50) NOT NULL,
        `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `modified_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_mapping` (`odoo_pricelist_id`, `opencart_customer_group_id`, `price_type`)
        ) 
        ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "odoo_price_sync_log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `product_id` int(11) NOT NULL,
        `customer_group_id` int(11) NOT NULL,
        `old_price` decimal(15,4),
        `new_price` decimal(15,4),
        `sync_direction` varchar(20) NOT NULL COMMENT 'to_odoo/from_odoo',
        `status` varchar(20) NOT NULL COMMENT 'pending/synced/error',
        `message` text,
        `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_product_status` (`product_id`, `status`)
        ) 
        ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
    }
    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "odoo_pricelist_map");
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "odoo_price_sync_log");
    }

    protected function checkInstall() {
        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "odoo_pricelist_map'");
        if ($query->num_rows == 0) {
            $this->install();
        }
    }
}