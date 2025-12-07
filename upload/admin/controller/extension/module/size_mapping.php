<?php

/**
 * Size Mapping  - Admin Controller
 * Admin interface for configuring size option mappings, category gender assignments, and conversion tables
 */
class  ControllerExtensionModuleSizeMapping extends Controller {

	private $error = array();

	public function index() {
		$this->load->language('extension/module/size_mapping');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/option');
		$this->load->model('extension/module/size_mapping');

		// Handle form submission
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
			$this->model_extension_module_size_mapping->saveMapping($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('extension/module/size_mapping', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data = array();

		// Check if v2 tables are installed
		$data['v2_installed'] = $this->model_extension_module_size_mapping->isV2Installed();

		// Get all OpenCart options
		$options = $this->model_catalog_option->getOptions();

		// Get existing mappings
		$mappings = $this->model_extension_module_size_mapping->getAllMappings();
		$mappings_by_option = array();

		foreach ($mappings as $mapping) {
			$mappings_by_option[$mapping['option_id']] = $mapping;
		}

		// Prepare data for template
		$data['options'] = array();

		foreach ($options as $option) {
			$mapping = isset($mappings_by_option[$option['option_id']]) ? $mappings_by_option[$option['option_id']] : null;

			$data['options'][] = array(
				'option_id' => $option['option_id'],
				'name' => $option['name'],
				'type' => $option['type'],
				'mapped' => $mapping !== null,
				'gender' => $mapping ? $mapping['gender'] : 'unisex',
				'size_type' => $mapping ? $mapping['size_type'] : 'shoes',
				'source_system' => $mapping ? $mapping['source_system'] : 'EU',
				'enabled' => $mapping ? $mapping['enabled'] : 1
			);
		}

		// Get category tree for v2
		if ($data['v2_installed']) {
			$data['categories'] = $this->model_extension_module_size_mapping->getCategoriesTree();
			$data['conversion_tables'] = $this->model_extension_module_size_mapping->getConversionTables();
		}

		// Language strings
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_list'] = $this->language->get('text_list');
		$data['text_success'] = $this->language->get('text_success');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		// Tab labels
		$data['tab_option_mapping'] = $this->language->get('tab_option_mapping');
		$data['tab_category_mapping'] = $this->language->get('tab_category_mapping');
		$data['tab_conversion_tables'] = $this->language->get('tab_conversion_tables');

		// Column labels
		$data['column_option'] = $this->language->get('column_option');
		$data['column_type'] = $this->language->get('column_type');
		$data['column_gender'] = $this->language->get('column_gender');
		$data['column_size_type'] = $this->language->get('column_size_type');
		$data['column_source_system'] = $this->language->get('column_source_system');
		$data['column_enabled'] = $this->language->get('column_enabled');
		$data['column_category'] = $this->language->get('column_category');
		$data['column_gender_type'] = $this->language->get('column_gender_type');
		$data['column_age_group'] = $this->language->get('column_age_group');
		$data['column_inherit'] = $this->language->get('column_inherit');
		$data['column_action'] = $this->language->get('column_action');
		$data['column_table_name'] = $this->language->get('column_table_name');

		// Entry labels
		$data['entry_category'] = $this->language->get('entry_category');
		$data['entry_gender_type'] = $this->language->get('entry_gender_type');
		$data['entry_age_group'] = $this->language->get('entry_age_group');
		$data['entry_inherit_children'] = $this->language->get('entry_inherit_children');

		// Gender/age options
		$data['text_men'] = $this->language->get('text_men');
		$data['text_women'] = $this->language->get('text_women');
		$data['text_kids'] = $this->language->get('text_kids');
		$data['text_unisex'] = $this->language->get('text_unisex');
		$data['text_adult'] = $this->language->get('text_adult');
		$data['text_baby'] = $this->language->get('text_baby');
		$data['text_shoes'] = $this->language->get('text_shoes');
		$data['text_apparel'] = $this->language->get('text_apparel');

		// Buttons
		$data['button_add'] = $this->language->get('button_add');
		$data['button_edit'] = $this->language->get('button_edit');
		$data['button_delete'] = $this->language->get('button_delete');
		$data['button_install_v2'] = $this->language->get('button_install_v2');

		// Help text
		$data['help_inherit'] = $this->language->get('help_inherit');
		$data['help_category_mapping'] = $this->language->get('help_category_mapping');

		// Errors
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

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/size_mapping', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/size_mapping', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);

		// AJAX URLs
		$data['save_option_url'] = $this->url->link('extension/module/size_mapping/saveOptions', 'user_token=' . $this->session->data['user_token'], true);
		$data['save_category_url'] = $this->url->link('extension/module/size_mapping/saveCategory', 'user_token=' . $this->session->data['user_token'], true);
		$data['delete_category_url'] = $this->url->link('extension/module/size_mapping/deleteCategory', 'user_token=' . $this->session->data['user_token'], true);
		$data['save_table_url'] = $this->url->link('extension/module/size_mapping/saveConversionTable', 'user_token=' . $this->session->data['user_token'], true);
		$data['get_table_url'] = $this->url->link('extension/module/size_mapping/getConversionTable', 'user_token=' . $this->session->data['user_token'], true);
		$data['install_v2_url'] = $this->url->link('extension/module/size_mapping/installV2', 'user_token=' . $this->session->data['user_token'], true);

		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/size_mapping', $data));
	}

	public function saveOptions() {
		$this->load->language('extension/module/size_mapping');
		$this->load->model('extension/module/size_mapping');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
			// Save each mapping
			if (isset($this->request->post['mappings'])) {
				foreach ($this->request->post['mappings'] as $option_id => $mapping_data) {
					if (isset($mapping_data['enabled']) && $mapping_data['enabled']) {
						$this->model_extension_module_size_mapping->saveMapping(array(
							'option_id' => $option_id,
							'gender' => $mapping_data['gender'],
							'size_type' => $mapping_data['size_type'],
							'source_system' => $mapping_data['source_system'],
							'enabled' => 1
						));
					} else {
						// Delete mapping if disabled
						$this->model_extension_module_size_mapping->deleteMapping($option_id);
					}
				}
			}

			$json['success'] = $this->language->get('text_success');
		} else {
			$json['error'] = isset($this->error['warning']) ? $this->error['warning'] : 'Error saving mappings';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveCategory() {
		$this->load->language('extension/module/size_mapping');
		$this->load->model('extension/module/size_mapping');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
			$category_id = isset($this->request->post['category_id']) ? (int)$this->request->post['category_id'] : 0;
			$gender_type = isset($this->request->post['gender_type']) ? $this->request->post['gender_type'] : 'unisex';
			$age_group = isset($this->request->post['age_group']) ? $this->request->post['age_group'] : 'adult';
			$inherit_children = isset($this->request->post['inherit_children']) ? (int)$this->request->post['inherit_children'] : 1;

			if ($category_id) {
				$this->model_extension_module_size_mapping->saveCategoryMapping(array(
					'category_id' => $category_id,
					'gender_type' => $gender_type,
					'age_group' => $age_group,
					'inherit_children' => $inherit_children
				));

				$json['success'] = $this->language->get('text_success');
			} else {
				$json['error'] = $this->language->get('error_category_required');
			}
		} else {
			$json['error'] = isset($this->error['warning']) ? $this->error['warning'] : 'Error saving category mapping';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteCategory() {
		$this->load->language('extension/module/size_mapping');
		$this->load->model('extension/module/size_mapping');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
			$category_id = isset($this->request->post['category_id']) ? (int)$this->request->post['category_id'] : 0;

			if ($category_id) {
				$this->model_extension_module_size_mapping->deleteCategoryMapping($category_id);
				$json['success'] = $this->language->get('text_success');
			} else {
				$json['error'] = $this->language->get('error_category_required');
			}
		} else {
			$json['error'] = isset($this->error['warning']) ? $this->error['warning'] : 'Permission denied';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getConversionTable() {
		$this->load->model('extension/module/size_mapping');

		$json = array();

		$table_id = isset($this->request->get['table_id']) ? (int)$this->request->get['table_id'] : 0;

		if ($table_id) {
			$table = $this->model_extension_module_size_mapping->getConversionTable($table_id);

			if ($table) {
				$json['success'] = true;
				$json['table'] = $table;
			} else {
				$json['error'] = 'Table not found';
			}
		} else {
			$json['error'] = 'Table ID required';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveConversionTable() {
		$this->load->language('extension/module/size_mapping');
		$this->load->model('extension/module/size_mapping');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
			$data = array(
				'table_id' => isset($this->request->post['table_id']) ? (int)$this->request->post['table_id'] : 0,
				'gender_type' => isset($this->request->post['gender_type']) ? $this->request->post['gender_type'] : '',
				'size_type' => isset($this->request->post['size_type']) ? $this->request->post['size_type'] : '',
				'table_name' => isset($this->request->post['table_name']) ? $this->request->post['table_name'] : '',
				'table_data' => isset($this->request->post['table_data']) ? $this->request->post['table_data'] : '',
				'enabled' => isset($this->request->post['enabled']) ? (int)$this->request->post['enabled'] : 1
			);

			if ($data['gender_type'] && $data['size_type'] && $data['table_name']) {
				$table_id = $this->model_extension_module_size_mapping->saveConversionTable($data);
				$json['success'] = $this->language->get('text_success');
				$json['table_id'] = $table_id;
			} else {
				$json['error'] = $this->language->get('error_table_required');
			}
		} else {
			$json['error'] = isset($this->error['warning']) ? $this->error['warning'] : 'Error saving conversion table';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/size_mapping')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function install() {
		$this->load->model('extension/module/size_mapping');

		// Run installation SQL
		$sql_file = DIR_APPLICATION . '../install/size_selector_install.sql';

		if (file_exists($sql_file)) {
			$sql = file_get_contents($sql_file);

			// Split by semicolon and execute each statement
			$statements = explode(';', $sql);

			foreach ($statements as $statement) {
				$statement = trim($statement);

				if (!empty($statement)) {
					$this->db->query($statement);
				}
			}

			$this->session->data['success'] = 'Size Selector module installed successfully!';
		} else {
			$this->session->data['error'] = 'Installation SQL file not found!';
		}

		$this->response->redirect($this->url->link('extension/module/size_mapping', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function installV2() {
		$this->load->language('extension/module/size_mapping');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/size_mapping')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			// Run v2 installation SQL
			$sql_file = DIR_APPLICATION . '../install/size_selector_v2_install.sql';

			if (file_exists($sql_file)) {
				$sql = file_get_contents($sql_file);

				// Replace table prefix placeholder
				$sql = str_replace('ocus_', DB_PREFIX, $sql);

				// Split by semicolon and execute each statement
				$statements = explode(';', $sql);

				foreach ($statements as $statement) {
					$statement = trim($statement);

					if (!empty($statement)) {
						try {
							$this->db->query($statement);
						} catch (Exception $e) {
							// Ignore errors for already existing tables/columns
						}
					}
				}

				$json['success'] = $this->language->get('text_v2_installed');
			} else {
				$json['error'] = $this->language->get('error_sql_not_found');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}

class_alias('ControllerExtensionModuleSizeMapping', '\Opencart\Admin\Controller\Extension\Module\SizeMapping');
