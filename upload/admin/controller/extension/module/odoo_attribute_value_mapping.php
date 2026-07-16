<?php
/**
 * Odoo Attribute Value Mapping Admin Controller
 *
 * Provides the admin UI to manage per-vendor size/attribute value overrides.
 * Each entry maps an Odoo attribute value code (e.g. "260") to a specific
 * OpenCart option_value_id (e.g. "US 8"), bypassing the default LIKE-based
 * matching used during product sync and quantity sync.
 */
class ControllerExtensionModuleOdooAttributeValueMapping extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/odoo_attribute_value_mapping');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/odoo_attribute_value_mapping');
        $this->model_extension_module_odoo_attribute_value_mapping->ensureTable();

        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
            ),
            array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/odoo_attribute_value_mapping', 'user_token=' . $this->session->data['user_token'], true)
            ),
        );

        // Flash messages
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        if (isset($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];
            unset($this->session->data['error']);
        } elseif (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        // Handle add POST
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateAdd()) {
            $this->model_extension_module_odoo_attribute_value_mapping->saveMapping(
                (int)$this->request->post['odoo_attribute_id'],
                trim($this->request->post['odoo_value_code']),
                (int)$this->request->post['opencart_option_value_id']
            );

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/odoo_attribute_value_mapping', 'user_token=' . $this->session->data['user_token'], true));
        }

        $attribute_sections = $this->model_extension_module_odoo_attribute_value_mapping->getMappedAttributes();

        // Enrich sections with the Odoo attribute display name (requires Odoo connection)
        $this->load->model('extension/module/odoo_connector');
        $this->load->model('extension/module/odoo_attribute_mapping');
        $odoo_connection = $this->model_extension_module_odoo_connector->getConnection();
        $odoo_attr_names = array(); // keyed by odoo_attribute_id
        if ($odoo_connection && !empty($odoo_connection['status'])) {
            $odoo_attributes = $this->model_extension_module_odoo_attribute_mapping->getOdooAttributes($odoo_connection);
            foreach ($odoo_attributes as $attr) {
                $odoo_attr_names[(int)$attr['id']] = !empty($attr['display_name']) ? $attr['display_name'] : $attr['name'];
            }
        }

        foreach ($attribute_sections as &$section) {
            $id = $section['odoo_attribute_id'];
            $section['attribute_name'] = isset($odoo_attr_names[$id]) ? $odoo_attr_names[$id] : null;
        }
        unset($section);

        $data['attribute_sections'] = $attribute_sections;
        $data['odoo_connected']     = !empty($odoo_connection['status']);

        // Size charts for import dropdown
        $data['size_charts'] = $this->model_extension_module_odoo_attribute_value_mapping->getSizeCharts();

        $data['action']      = $this->url->link('extension/module/odoo_attribute_value_mapping', 'user_token=' . $this->session->data['user_token'], true);
        $data['import_url']  = $this->url->link('extension/module/odoo_attribute_value_mapping/importFromChart', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel']      = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);
        $data['delete_url']  = $this->url->link('extension/module/odoo_attribute_value_mapping/delete', 'user_token=' . $this->session->data['user_token'], true);

        $data['heading_title']              = $this->language->get('heading_title');
        $data['text_odoo_attribute']        = $this->language->get('text_odoo_attribute');
        $data['text_odoo_value_code']       = $this->language->get('text_odoo_value_code');
        $data['text_opencart_option_value'] = $this->language->get('text_opencart_option_value');
        $data['text_no_mappings']           = $this->language->get('text_no_mappings');
        $data['text_no_attributes']         = $this->language->get('text_no_attributes');
        $data['text_add_mapping']           = $this->language->get('text_add_mapping');
        $data['column_vendor_code']         = $this->language->get('column_vendor_code');
        $data['column_option_value']        = $this->language->get('column_option_value');
        $data['column_action']              = $this->language->get('column_action');
        $data['button_add']                 = $this->language->get('button_add');
        $data['button_delete']              = $this->language->get('button_delete');
        $data['button_cancel']              = $this->language->get('button_cancel');
        $data['user_token']                 = $this->session->data['user_token'];

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/odoo_attribute_value_mapping', $data));
    }

    public function importFromChart() {
        $this->load->language('extension/module/odoo_attribute_value_mapping');

        if (!$this->user->hasPermission('modify', 'extension/module/odoo_attribute_value_mapping')) {
            $this->session->data['error'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link('extension/module/odoo_attribute_value_mapping', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $odoo_attribute_id    = (int)($this->request->post['odoo_attribute_id'] ?? 0);
        $opencart_option_id   = (int)($this->request->post['opencart_option_id'] ?? 0);
        $table_id             = (int)($this->request->post['table_id'] ?? 0);

        if (!$odoo_attribute_id || !$opencart_option_id || !$table_id) {
            $this->session->data['error'] = 'Missing required parameters for import.';
            $this->response->redirect($this->url->link('extension/module/odoo_attribute_value_mapping', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $this->load->model('extension/module/odoo_attribute_value_mapping');
        $this->model_extension_module_odoo_attribute_value_mapping->ensureTable();

        // Clear existing mm mappings for this attribute before re-importing
        $this->model_extension_module_odoo_attribute_value_mapping->clearMappingsForAttribute($odoo_attribute_id);

        $result = $this->model_extension_module_odoo_attribute_value_mapping->importFromSizeChart(
            $odoo_attribute_id,
            $opencart_option_id,
            $table_id
        );

        $msg = 'Imported: ' . $result['imported'] . ', Skipped: ' . $result['skipped'];
        if ($result['errors']) {
            $msg .= ' | ' . implode('; ', array_slice($result['errors'], 0, 3));
        }

        if ($result['imported'] > 0) {
            $this->session->data['success'] = $msg;
        } else {
            $this->session->data['error'] = $msg;
        }

        $this->response->redirect($this->url->link('extension/module/odoo_attribute_value_mapping', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function delete() {
        $this->load->language('extension/module/odoo_attribute_value_mapping');

        if (!$this->user->hasPermission('modify', 'extension/module/odoo_attribute_value_mapping')) {
            $this->session->data['error'] = $this->language->get('error_permission');
        } elseif (isset($this->request->get['id'])) {
            $this->load->model('extension/module/odoo_attribute_value_mapping');
            $this->model_extension_module_odoo_attribute_value_mapping->deleteMapping((int)$this->request->get['id']);
            $this->session->data['success'] = $this->language->get('text_success_delete');
        }

        $this->response->redirect($this->url->link('extension/module/odoo_attribute_value_mapping', 'user_token=' . $this->session->data['user_token'], true));
    }

    protected function validateAdd() {
        if (!$this->user->hasPermission('modify', 'extension/module/odoo_attribute_value_mapping')) {
            $this->error['warning'] = $this->language->get('error_permission');
            return false;
        }

        if (empty($this->request->post['odoo_attribute_id']) || (int)$this->request->post['odoo_attribute_id'] <= 0) {
            $this->error['warning'] = $this->language->get('error_attribute');
            return false;
        }

        if (empty(trim($this->request->post['odoo_value_code']))) {
            $this->error['warning'] = $this->language->get('error_code');
            return false;
        }

        if (empty($this->request->post['opencart_option_value_id']) || (int)$this->request->post['opencart_option_value_id'] <= 0) {
            $this->error['warning'] = $this->language->get('error_option_value');
            return false;
        }

        return true;
    }
}
