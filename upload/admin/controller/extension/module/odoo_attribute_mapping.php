<?php
/**
 * Odoo Attribute to OpenCart Option Mapping Admin Controller
 *
 * Provides an interface to map Odoo product attributes to OpenCart product options
 * User: max
 * Date: 23/12/24
 */

class ControllerExtensionModuleOdooAttributeMapping extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/odoo_attribute_mapping');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/odoo_attribute_mapping');
        $this->load->model('extension/module/odoo_connector');

        // Handle form submission
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_module_odoo_attribute_mapping->saveMappings($this->request->post['mappings']);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/odoo_attribute_mapping', 'user_token=' . $this->session->data['user_token'], true));
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/odoo_attribute_mapping', 'user_token=' . $this->session->data['user_token'], true)
        );

        // Messages
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

        // Get OpenCart options
        $data['oc_options'] = $this->model_extension_module_odoo_attribute_mapping->getOpenCartOptions();

        // Get Odoo attributes
        $odoo_connection = $this->model_extension_module_odoo_connector->getConnection();
        if ($odoo_connection && isset($odoo_connection['status']) && $odoo_connection['status']) {
            $data['odoo_attributes'] = $this->model_extension_module_odoo_attribute_mapping->getOdooAttributes($odoo_connection);
            $data['odoo_connected'] = true;
        } else {
            $data['odoo_attributes'] = array();
            $data['odoo_connected'] = false;
            $data['error_warning'] = $this->language->get('error_odoo_connection');
        }

        // Get existing mappings
        $data['mappings'] = $this->model_extension_module_odoo_attribute_mapping->getMappings();

        // URLs
        $data['action'] = $this->url->link('extension/module/odoo_attribute_mapping', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);
        $data['refresh_odoo'] = $this->url->link('extension/module/odoo_attribute_mapping/refreshOdoo', 'user_token=' . $this->session->data['user_token'], true);

        // Language strings
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_odoo_attribute'] = $this->language->get('text_odoo_attribute');
        $data['text_opencart_option'] = $this->language->get('text_opencart_option');
        $data['text_no_mapping'] = $this->language->get('text_no_mapping');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_refresh'] = $this->language->get('button_refresh');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['user_token'] = $this->session->data['user_token'];

        $this->response->setOutput($this->load->view('extension/module/odoo_attribute_mapping', $data));
    }

    public function refreshOdoo() {
        $this->load->language('extension/module/odoo_attribute_mapping');

        $this->session->data['success'] = $this->language->get('text_refreshed');
        $this->response->redirect($this->url->link('extension/module/odoo_attribute_mapping', 'user_token=' . $this->session->data['user_token'], true));
    }

    protected function validateForm() {
        if (!$this->user->hasPermission('modify', 'extension/module/odoo_attribute_mapping')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
