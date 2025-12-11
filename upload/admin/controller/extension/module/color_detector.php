<?php
class ControllerExtensionModuleColorDetector extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/color_detector');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_color_detector', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
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
			'href' => $this->url->link('extension/module/color_detector', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/color_detector', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		$data['process'] = $this->url->link('extension/module/color_detector/process', 'user_token=' . $this->session->data['user_token'], true);
		$data['color_mapping'] = $this->url->link('extension/module/color_detector/colorMapping', 'user_token=' . $this->session->data['user_token'], true);

		$data['user_token'] = $this->session->data['user_token'];

		// Get settings
		if (isset($this->request->post['module_color_detector_status'])) {
			$data['module_color_detector_status'] = $this->request->post['module_color_detector_status'];
		} else {
			$data['module_color_detector_status'] = $this->config->get('module_color_detector_status');
		}

		if (isset($this->request->post['module_color_detector_categories'])) {
			$data['module_color_detector_categories'] = $this->request->post['module_color_detector_categories'];
		} else {
			$data['module_color_detector_categories'] = $this->config->get('module_color_detector_categories');
		}

		if (isset($this->request->post['module_color_detector_force'])) {
			$data['module_color_detector_force'] = $this->request->post['module_color_detector_force'];
		} else {
			$data['module_color_detector_force'] = $this->config->get('module_color_detector_force');
		}

		if (isset($this->request->post['module_color_detector_attribute_id'])) {
			$data['module_color_detector_attribute_id'] = $this->request->post['module_color_detector_attribute_id'];
		} else {
			$data['module_color_detector_attribute_id'] = $this->config->get('module_color_detector_attribute_id');
		}

		// Load categories
		$this->load->model('catalog/category');
		$data['categories'] = $this->model_catalog_category->getCategories(array());

		// Load attributes
		$this->load->model('catalog/attribute');
		$data['attributes'] = $this->model_catalog_attribute->getAttributes(array());

		// Get color attribute if set
		if ($data['module_color_detector_attribute_id']) {
			$attribute_info = $this->model_catalog_attribute->getAttribute($data['module_color_detector_attribute_id']);
			if ($attribute_info) {
				$data['color_attribute_name'] = $attribute_info['name'];
			} else {
				$data['color_attribute_name'] = '';
			}
		} else {
			$data['color_attribute_name'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/color_detector', $data));
	}

	public function install() {
		$this->load->model('extension/module/color_detector');
		$this->model_extension_module_color_detector->install();
	}

	public function uninstall() {
		$this->load->model('extension/module/color_detector');
		$this->model_extension_module_color_detector->uninstall();
	}

	public function colorMapping() {
		$this->load->language('extension/module/color_detector');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/color_detector');

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
			'href' => $this->url->link('extension/module/color_detector', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_color_mapping'),
			'href' => $this->url->link('extension/module/color_detector/colorMapping', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['add'] = $this->url->link('extension/module/color_detector/addColor', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete'] = $this->url->link('extension/module/color_detector/deleteColor', 'user_token=' . $this->session->data['user_token'], true);
		$data['back'] = $this->url->link('extension/module/color_detector', 'user_token=' . $this->session->data['user_token'], true);

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}

		// Get all color mappings
		$data['colors'] = $this->model_extension_module_color_detector->getColors();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/color_detector_mapping', $data));
	}

	public function addColor() {
		$this->load->language('extension/module/color_detector');
		$this->load->model('extension/module/color_detector');

		$json = array();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateColor()) {
			$this->model_extension_module_color_detector->addColor($this->request->post);

			$json['success'] = $this->language->get('text_success_color');
		} else {
			$json['error'] = isset($this->error['warning']) ? $this->error['warning'] : 'Validation failed';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function editColor() {
		$this->load->language('extension/module/color_detector');
		$this->load->model('extension/module/color_detector');

		$json = array();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateColor()) {
			$this->model_extension_module_color_detector->editColor($this->request->get['color_id'], $this->request->post);

			$json['success'] = $this->language->get('text_success_color');
		} else {
			$json['error'] = isset($this->error['warning']) ? $this->error['warning'] : 'Validation failed';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteColor() {
		$this->load->language('extension/module/color_detector');
		$this->load->model('extension/module/color_detector');

		if (isset($this->request->post['selected']) && $this->validate()) {
			foreach ($this->request->post['selected'] as $color_id) {
				$this->model_extension_module_color_detector->deleteColor($color_id);
			}

			$this->session->data['success'] = $this->language->get('text_success_delete');

			$this->response->redirect($this->url->link('extension/module/color_detector/colorMapping', 'user_token=' . $this->session->data['user_token'], true));
		}

		$this->colorMapping();
	}

	public function process() {
		@set_time_limit(300);
		@ini_set('memory_limit', '256M');

		$this->load->language('extension/module/color_detector');
		$this->load->model('extension/module/color_detector');
		$this->load->model('catalog/product');

		$json = array();

		try {
			if (!$this->user->hasPermission('modify', 'extension/module/color_detector')) {
				$json['error'] = $this->language->get('error_permission');
			} else {
				$categories = $this->config->get('module_color_detector_categories');
				$force = $this->config->get('module_color_detector_force');
				$attribute_id = $this->config->get('module_color_detector_attribute_id');

				if (!$categories || !is_array($categories)) {
					$json['error'] = $this->language->get('error_no_categories');
				} elseif (!$attribute_id) {
					$json['error'] = $this->language->get('error_no_attribute');
				} else {
					$processed = 0;
					$updated = 0;
					$skipped = 0;

					foreach ($categories as $category_id) {
						$products = $this->model_catalog_product->getProductsByCategoryId($category_id);

						foreach ($products as $product) {
							$processed++;

							$product_info = $this->model_catalog_product->getProduct($product['product_id']);

							if (!$product_info) {
								continue;
							}

							// Check if product already has color attribute
							$has_color = false;
							$product_attributes = $this->model_catalog_product->getProductAttributes($product['product_id']);

							foreach ($product_attributes as $attr) {
								if ($attr['attribute_id'] == $attribute_id) {
									$has_color = true;
									break;
								}
							}

							if ($has_color && !$force) {
								$skipped++;
								continue;
							}

							// Detect color from product name and image
							$color_data = $this->model_extension_module_color_detector->detectColor($product_info);

							if ($color_data) {
								$this->model_extension_module_color_detector->setProductColor($product['product_id'], $attribute_id, $color_data);
								$updated++;
							} else {
								$skipped++;
							}
						}
					}

					$json['success'] = sprintf($this->language->get('text_process_complete'), $processed, $updated, $skipped);
				}
			}
		} catch (Exception $e) {
			$json['error'] = 'Error: ' . $e->getMessage();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/color_detector')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function validateColor() {
		if (!$this->user->hasPermission('modify', 'extension/module/color_detector')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (utf8_strlen($this->request->post['keyword']) < 1) {
			$this->error['keyword'] = $this->language->get('error_keyword');
		}

		if (utf8_strlen($this->request->post['color_name_ru']) < 1) {
			$this->error['color_name_ru'] = $this->language->get('error_color_name');
		}

		if (utf8_strlen($this->request->post['color_name_en']) < 1) {
			$this->error['color_name_en'] = $this->language->get('error_color_name');
		}

		if (!preg_match('/^#[0-9A-F]{6}$/i', $this->request->post['hex_code'])) {
			$this->error['hex_code'] = $this->language->get('error_hex_code');
		}

		return !$this->error;
	}
}
