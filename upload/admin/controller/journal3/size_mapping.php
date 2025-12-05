<?php

/**
 * Journal3 Size Mapping Admin Controller
 * Admin interface for configuring size option mappings
 */
class ControllerJournal3SizeMapping extends Controller {

	private $error = array();

	public function index() {
		$this->load->language('journal3/size_mapping');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/option');
		$this->load->model('journal3/size_mapping');

		// Handle form submission
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
			$this->model_journal3_size_mapping->saveMapping($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('journal3/size_mapping', 'user_token=' . $this->session->data['user_token'], true));
		}

		$data = array();

		// Get all OpenCart options
		$options = $this->model_catalog_option->getOptions();

		// Get existing mappings
		$mappings = $this->model_journal3_size_mapping->getAllMappings();
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

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_list'] = $this->language->get('text_list');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('journal3/size_mapping', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('journal3/size_mapping', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('journal3/size_mapping', $data));
	}

	public function save() {
		$this->load->language('journal3/size_mapping');
		$this->load->model('journal3/size_mapping');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateForm()) {
			// Save each mapping
			if (isset($this->request->post['mappings'])) {
				foreach ($this->request->post['mappings'] as $option_id => $mapping_data) {
					if (isset($mapping_data['enabled']) && $mapping_data['enabled']) {
						$this->model_journal3_size_mapping->saveMapping(array(
							'option_id' => $option_id,
							'gender' => $mapping_data['gender'],
							'size_type' => $mapping_data['size_type'],
							'source_system' => $mapping_data['source_system'],
							'enabled' => 1
						));
					} else {
						// Delete mapping if disabled
						$this->model_journal3_size_mapping->deleteMapping($option_id);
					}
				}
			}

			$json['success'] = $this->language->get('text_success');
		} else {
			$json['error'] = $this->error;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'journal3/size_mapping')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function install() {
		$this->load->model('journal3/size_mapping');

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

		$this->response->redirect($this->url->link('journal3/size_mapping', 'user_token=' . $this->session->data['user_token'], true));
	}
}

class_alias('ControllerJournal3SizeMapping', '\Opencart\Admin\Controller\Journal3\SizeMapping');
