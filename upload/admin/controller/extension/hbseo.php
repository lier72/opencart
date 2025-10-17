<?php
class ControllerExtensionHbseo extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/hbseo');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/extension');

		$this->getList();
	}

	public function install() {
		$this->load->language('extension/hbseo');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/extension');

		if ($this->validate()) {
			$this->model_extension_extension->install('hbseo', $this->request->get['extension']);

			$this->load->model('user/user_group');
			if (version_compare(VERSION,'2.0.0.0','=')){
				$this->model_user_user_group->addPermission($this->user->getId(), 'access', 'hbseo/' . $this->request->get['extension']);
				$this->model_user_user_group->addPermission($this->user->getId(), 'modify', 'hbseo/' . $this->request->get['extension']);
			}else{
				$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'hbseo/' . $this->request->get['extension']);
				$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'hbseo/' . $this->request->get['extension']);
			}

			// Call install method if it exsits
			if (is_file(DIR_APPLICATION . 'controller/extension/hbseo/' . $this->request->get['extension'] . '.php')){ 
				$this->load->controller('extension/hbseo/' . $this->request->get['extension'] . '/install');
			}
			if (is_file(DIR_APPLICATION . 'controller/hbseo/' . $this->request->get['extension'] . '.php')){ 
				$this->load->controller('hbseo/' . $this->request->get['extension'] . '/install');
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/hbseo', 'token=' . $this->session->data['token'], 'SSL'));
		}

		$this->getList();
	}

	public function uninstall() {
		$this->load->language('extension/hbseo');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/extension');

		if ($this->validate()) {
			$this->model_extension_extension->uninstall('hbseo', $this->request->get['extension']);

			// Call uninstall method if it exsits
			if (is_file(DIR_APPLICATION . 'controller/extension/hbseo/' . $this->request->get['extension'] . '.php')){ 
				$this->load->controller('extension/hbseo/' . $this->request->get['extension'] . '/uninstall');
			}
			if (is_file(DIR_APPLICATION . 'controller/hbseo/' . $this->request->get['extension'] . '.php')){ 
				$this->load->controller('hbseo/' . $this->request->get['extension'] . '/uninstall');
			}
			
			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/hbseo', 'token=' . $this->session->data['token'], 'SSL'));
		}
	}

	public function getList() {
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/hbseo', 'token=' . $this->session->data['token'], 'SSL')
		);

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_list'] = $this->language->get('text_list');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['text_confirm'] = $this->language->get('text_confirm');

		$data['column_name'] = $this->language->get('column_name');
		$data['column_status'] = $this->language->get('column_status');
		$data['column_action'] = $this->language->get('column_action');

		$data['button_edit'] = $this->language->get('button_edit');
		$data['button_install'] = $this->language->get('button_install');
		$data['button_uninstall'] = $this->language->get('button_uninstall');

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

		$extensions = $this->model_extension_extension->getInstalled('hbseo');

		foreach ($extensions as $key => $value) {
			//if (!file_exists(DIR_APPLICATION . 'controller/hbseo/' . $value . '.php')) {
			if (!is_file(DIR_APPLICATION . 'controller/extension/hbseo/' . $value . '.php') && !is_file(DIR_APPLICATION . 'controller/hbseo/' . $value . '.php')) {
				$this->model_extension_extension->uninstall('hbseo', $value);

				unset($extensions[$key]);
			}
		}
		
		$this->load->model('setting/setting');
		$this->load->model('setting/store');

		$stores = $this->model_setting_store->getStores();
		
		$data['extensions'] = array();
		
		$files = glob(DIR_APPLICATION . 'controller/extension/hbseo/*.php');

		if ($files) {
			foreach ($files as $file) {
				$extension = basename($file, '.php');

				$this->load->language('extension/hbseo/' . $extension);

				$store_data = array();
				
				$store_data[] = array(
					'name'   => $this->config->get('config_name'),
					'edit'   => $this->url->link('extension/hbseo/' . $extension, 'token=' . $this->session->data['token'] . '&store_id=0', 'SSL')
				);
									
				foreach ($stores as $store) {
					$store_data[] = array(
						'name'   => $store['name'],
						'edit'   => $this->url->link('extension/hbseo/' . $extension, 'token=' . $this->session->data['token'] . '&store_id=' . $store['store_id'], 'SSL')
					);
				}
				
				$data['extensions'][] = array(
					'name'      => $this->language->get('heading_title'),
					'install'   => $this->url->link('extension/hbseo/install', 'token=' . $this->session->data['token'] . '&extension=' . $extension, 'SSL'),
					'uninstall' => $this->url->link('extension/hbseo/uninstall', 'token=' . $this->session->data['token'] . '&extension=' . $extension, 'SSL'),
					'installed' => in_array($extension, $extensions),
					'store'     => $store_data
				);
			}
			$data['store_count'] = count($store_data);
		}

		$files = glob(DIR_APPLICATION . 'controller/hbseo/*.php');

		if ($files) {
			foreach ($files as $file) {
				$extension = basename($file, '.php');

				$this->load->language('hbseo/' . $extension);

				$store_data = array();
				
				$store_data[] = array(
					'name'   => $this->config->get('config_name'),
					'edit'   => $this->url->link('hbseo/' . $extension, 'token=' . $this->session->data['token'] . '&store_id=0', 'SSL')
				);
									
				foreach ($stores as $store) {
					$store_data[] = array(
						'name'   => $store['name'],
						'edit'   => $this->url->link('hbseo/' . $extension, 'token=' . $this->session->data['token'] . '&store_id=' . $store['store_id'], 'SSL')
					);
				}
				
				$data['extensions'][] = array(
					'name'      => $this->language->get('heading_title'),
					'install'   => $this->url->link('extension/hbseo/install', 'token=' . $this->session->data['token'] . '&extension=' . $extension, 'SSL'),
					'uninstall' => $this->url->link('extension/hbseo/uninstall', 'token=' . $this->session->data['token'] . '&extension=' . $extension, 'SSL'),
					'installed' => in_array($extension, $extensions),
					'store'     => $store_data
				);
			}
			$data['store_count'] = count($store_data);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/hbseo.tpl', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/hbseo')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
