<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 22/12/24
 * Time: 23:41
 * File: controller/extension/module/odoo_order_mapping.php
 */

class ControllerExtensionModuleOdooOrderMapping extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/odoo_order_mapping');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('extension/module/odoo_connector');

        // Base URL for links
        $url = '';

        // Get filters
        if (isset($this->request->get['filter_order_id'])) {
            $filter_order_id = $this->request->get['filter_order_id'];
            $url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
        } else {
            $filter_order_id = '';
        }

        if (isset($this->request->get['filter_odoo_id'])) {
            $filter_odoo_id = $this->request->get['filter_odoo_id'];
            $url .= '&filter_odoo_id=' . $this->request->get['filter_odoo_id'];
        } else {
            $filter_odoo_id = '';
        }

        if (isset($this->request->get['filter_oc_status'])) {
            $filter_oc_status = $this->request->get['filter_oc_status'];
            $url .= '&filter_oc_status=' . $this->request->get['filter_oc_status'];
        } else {
            $filter_oc_status = '';
        }

        if (isset($this->request->get['filter_odoo_state'])) {
            $filter_odoo_state = $this->request->get['filter_odoo_state'];
            $url .= '&filter_odoo_state=' . $this->request->get['filter_odoo_state'];
        } else {
            $filter_odoo_state = '';
        }

        if (isset($this->request->get['filter_sync'])) {
            $filter_sync = $this->request->get['filter_sync'];
            $url .= '&filter_sync=' . $this->request->get['filter_sync'];
        } else {
            $filter_sync = '0'; // Default to showing non-synced orders
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
            $url .= '&order=' . $this->request->get['order'];
        } else {
            $order = 'DESC';
        }
        // no Sorting is added to the url variable HERE

        $data['sort_order_id'] = $this->url->link('extension/module/odoo_order_mapping', 'user_token=' . $this->session->data['user_token'] . $url . '&sort=oom.opencart_order_id&order=' . ($order == 'ASC' ? 'DESC' : 'ASC'), true);

        $data['sort_odoo_id'] = $this->url->link('extension/module/odoo_order_mapping', 'user_token=' . $this->session->data['user_token'] . $url . '&sort=oom.odoo_order_id&order=' . ($order == 'ASC' ? 'DESC' : 'ASC'), true);

        $data['sort_oc_status'] = $this->url->link('extension/module/odoo_order_mapping', 'user_token=' . $this->session->data['user_token'] . $url . '&sort=os.name&order=' . ($order == 'ASC' ? 'DESC' : 'ASC'), true);

        $data['sort_odoo_state'] = $this->url->link('extension/module/odoo_order_mapping', 'user_token=' . $this->session->data['user_token'] . $url . '&sort=oom.odoo_order_state&order=' . ($order == 'ASC' ? 'DESC' : 'ASC'), true);

        $data['sort_date_added'] = $this->url->link('extension/module/odoo_order_mapping', 'user_token=' . $this->session->data['user_token'] . $url . '&sort=o.date_added&order=' . ($order == 'ASC' ? 'DESC' : 'ASC'), true);

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
            $url .= '&sort=' . $this->request->get['sort'];
        } else {
            $sort = 'o.date_added';
        }



        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
            $url .= '&page=' . $this->request->get['page'];
        } else {
            $page = 1;
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/odoo_order_mapping', 'user_token=' . $this->session->data['user_token'] . $url, true)
        );

        $data['sync_url'] = $this->url->link('extension/module/odoo_connector/syncOrderState', 'user_token=' . $this->session->data['user_token'], true);

        // Get Orders
        $filter_data = array(
            'filter_order_id'    => $filter_order_id,
            'filter_odoo_id'     => $filter_odoo_id,
            'filter_oc_status'   => $filter_oc_status,
            'filter_odoo_state'  => $filter_odoo_state,
            'filter_sync'        => $filter_sync,
            'sort'               => $sort,
            'order'              => $order,
            'start'              => ($page - 1) * $this->config->get('config_limit_admin'),
            'limit'              => $this->config->get('config_limit_admin')
        );

        $order_total = $this->model_extension_module_odoo_connector->getTotalMappedOrders($filter_data);
//        $this->log->write('Info odoo_order_mapping contrller ControllerModuleOdooOrderMapping index():'. serialize($filter_data));
        $results = $this->model_extension_module_odoo_connector->getMappedOrders($filter_data);

        $data['orders'] = array();
        foreach ($results as $result) {
            $data['orders'][] = array(
                'order_id'          => $result['opencart_order_id'],
                'odoo_order_id'     => $result['odoo_order_id'],
                'customer'          => $result['firstname'] . ' ' . $result['lastname'],
                'oc_status'         => $result['order_status_name'],
                'odoo_state'        => $result['odoo_order_state'],
                'total'             => $this->currency->format($result['order_total'], $result['currency_code']),
                'date_added'        => date($this->language->get('date_format_short'), strtotime($result['order_date'])),
                'modified_on'       => date($this->language->get('date_format_short'), strtotime($result['modified_on'])),
                'view'              => $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $result['opencart_order_id'], true)
            );
        }

        // Language strings
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_list'] = $this->language->get('text_list');
        $data['text_no_results'] = $this->language->get('text_no_results');
        $data['text_confirm'] = $this->language->get('text_confirm');
        $data['text_all_status'] = $this->language->get('text_all_status');
        $data['text_sync_action'] = $this->language->get('text_sync_action');

        $data['column_order_id'] = $this->language->get('column_order_id');
        $data['column_odoo_id'] = $this->language->get('column_odoo_id');
        $data['column_customer'] = $this->language->get('column_customer');
        $data['column_oc_status'] = $this->language->get('column_oc_status');
        $data['column_odoo_state'] = $this->language->get('column_odoo_state');
        $data['column_total'] = $this->language->get('column_total');
        $data['column_date_added'] = $this->language->get('column_date_added');
        $data['column_modified'] = $this->language->get('column_modified');
        $data['column_action'] = $this->language->get('column_action');

        $data['entry_order_id'] = $this->language->get('entry_order_id');
        $data['entry_odoo_id'] = $this->language->get('entry_odoo_id');
        $data['entry_oc_status'] = $this->language->get('entry_oc_status');
        $data['entry_odoo_state'] = $this->language->get('entry_odoo_state');
        $data['entry_sync'] = $this->language->get('entry_sync');

        $data['button_filter'] = $this->language->get('button_filter');
        $data['button_view'] = $this->language->get('button_view');
        $data['button_sync'] = $this->language->get('button_sync');
        // Get status options for filter
        $data['order_statuses'] = $this->model_extension_module_odoo_connector->getOrderStatuses();

        // URLs
        $url = '';
        if (isset($this->request->get['filter_order_id'])) {
            $url .= '&filter_order_id=' . $this->request->get['filter_order_id'];
        }


        $pagination = new Pagination();
        $pagination->total = $order_total;
        $pagination->page = $page;
        $pagination->limit = $this->config->get('config_limit_admin');
        $pagination->url = $this->url->link('extension/module/odoo_order_mapping', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'),
            ($order_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0,
            ((($page - 1) * $this->config->get('config_limit_admin')) > ($order_total - $this->config->get('config_limit_admin')))
                ? $order_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')),
            $order_total,
            ceil($order_total / $this->config->get('config_limit_admin'))
        );

        $data['filter_order_id'] = $filter_order_id;
        $data['filter_odoo_id'] = $filter_odoo_id;
        $data['filter_oc_status'] = $filter_oc_status;
        $data['filter_odoo_state'] = $filter_odoo_state;
        $data['filter_sync'] = $filter_sync;

        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['user_token'] = $this->session->data['user_token'];

        // Get payment and currency mappings for the tabs
        $data['payment_mappings'] = $this->model_extension_module_odoo_connector->getPaymentAcquirerMappings();
        $data['currency_mappings'] = $this->model_extension_module_odoo_connector->getCurrencyMappings();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_order_mapping', $data));
    }
}