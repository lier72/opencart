<?php
/**
 * Product Family Module
 * Displays related products with the same model attribute but different variants (color, size, etc.)
 */
class ControllerExtensionModuleProductFamily extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/product_family');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_product_family', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		// Language strings
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');

		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_model_attribute'] = $this->language->get('entry_model_attribute');
		$data['entry_variant_attributes'] = $this->language->get('entry_variant_attributes');
		$data['entry_strict_mode'] = $this->language->get('entry_strict_mode');
		$data['entry_strict_categories'] = $this->language->get('entry_strict_categories');
		$data['entry_show_image'] = $this->language->get('entry_show_image');
		$data['entry_show_image_categories'] = $this->language->get('entry_show_image_categories');
		$data['entry_show_price'] = $this->language->get('entry_show_price');
		$data['entry_image_width'] = $this->language->get('entry_image_width');
		$data['entry_image_height'] = $this->language->get('entry_image_height');
		$data['entry_attribute_config'] = $this->language->get('entry_attribute_config');

		$data['help_model_attribute'] = $this->language->get('help_model_attribute');
		$data['help_variant_attributes'] = $this->language->get('help_variant_attributes');
		$data['help_strict_mode'] = $this->language->get('help_strict_mode');
		$data['help_strict_categories'] = $this->language->get('help_strict_categories');
		$data['help_show_image_categories'] = $this->language->get('help_show_image_categories');
		$data['help_attribute_config'] = $this->language->get('help_attribute_config');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_remove'] = $this->language->get('button_remove');

		// Error handling
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		// Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/product_family', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/product_family', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		// Module settings
		if (isset($this->request->post['module_product_family_status'])) {
			$data['module_product_family_status'] = $this->request->post['module_product_family_status'];
		} else {
			$data['module_product_family_status'] = $this->config->get('module_product_family_status');
		}

		if (isset($this->request->post['module_product_family_model_attribute_id'])) {
			$data['module_product_family_model_attribute_id'] = $this->request->post['module_product_family_model_attribute_id'];
		} else {
			$data['module_product_family_model_attribute_id'] = $this->config->get('module_product_family_model_attribute_id');
		}

		if (isset($this->request->post['module_product_family_variant_attributes'])) {
			$data['module_product_family_variant_attributes'] = $this->request->post['module_product_family_variant_attributes'];
		} elseif ($this->config->get('module_product_family_variant_attributes')) {
			$data['module_product_family_variant_attributes'] = $this->config->get('module_product_family_variant_attributes');
		} else {
			$data['module_product_family_variant_attributes'] = array();
		}

		if (isset($this->request->post['module_product_family_show_image'])) {
			$data['module_product_family_show_image'] = $this->request->post['module_product_family_show_image'];
		} else {
			$data['module_product_family_show_image'] = $this->config->get('module_product_family_show_image');
		}

		if (isset($this->request->post['module_product_family_show_price'])) {
			$data['module_product_family_show_price'] = $this->request->post['module_product_family_show_price'];
		} else {
			$data['module_product_family_show_price'] = $this->config->get('module_product_family_show_price');
		}

		if (isset($this->request->post['module_product_family_image_width'])) {
			$data['module_product_family_image_width'] = $this->request->post['module_product_family_image_width'];
		} elseif ($this->config->get('module_product_family_image_width')) {
			$data['module_product_family_image_width'] = $this->config->get('module_product_family_image_width');
		} else {
			$data['module_product_family_image_width'] = 60;
		}

		if (isset($this->request->post['module_product_family_image_height'])) {
			$data['module_product_family_image_height'] = $this->request->post['module_product_family_image_height'];
		} elseif ($this->config->get('module_product_family_image_height')) {
			$data['module_product_family_image_height'] = $this->config->get('module_product_family_image_height');
		} else {
			$data['module_product_family_image_height'] = 60;
		}

		if (isset($this->request->post['module_product_family_strict_mode'])) {
			$data['module_product_family_strict_mode'] = $this->request->post['module_product_family_strict_mode'];
		} else {
			$data['module_product_family_strict_mode'] = $this->config->get('module_product_family_strict_mode');
		}

		if (isset($this->request->post['module_product_family_strict_categories'])) {
			$data['module_product_family_strict_categories'] = $this->request->post['module_product_family_strict_categories'];
		} elseif ($this->config->get('module_product_family_strict_categories')) {
			$data['module_product_family_strict_categories'] = $this->config->get('module_product_family_strict_categories');
		} else {
			$data['module_product_family_strict_categories'] = array();
		}

		if (isset($this->request->post['module_product_family_show_image_categories'])) {
			$data['module_product_family_show_image_categories'] = $this->request->post['module_product_family_show_image_categories'];
		} elseif ($this->config->get('module_product_family_show_image_categories')) {
			$data['module_product_family_show_image_categories'] = $this->config->get('module_product_family_show_image_categories');
		} else {
			$data['module_product_family_show_image_categories'] = array();
		}

		if (isset($this->request->post['module_product_family_attribute_config'])) {
			$data['module_product_family_attribute_config'] = $this->request->post['module_product_family_attribute_config'];
		} elseif ($this->config->get('module_product_family_attribute_config')) {
			$data['module_product_family_attribute_config'] = $this->config->get('module_product_family_attribute_config');
		} else {
			$data['module_product_family_attribute_config'] = array();
		}

		// Load attributes
		$this->load->model('catalog/attribute');
		$data['attributes'] = $this->model_catalog_attribute->getAttributes();

		// Load categories
		$this->load->model('catalog/category');
		$data['categories'] = $this->model_catalog_category->getCategories(0);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/product_family', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/product_family')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
