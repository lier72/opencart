<?php
/**
 * Admin controller for the Racket Finder module.
 *
 * Scope: lives in Extensions > Modules alongside the other OpenCart 3 modules.
 * It stores a small settings record (status, the "rackets" category id, the
 * live-data cache lifetime) through model/setting/module, exactly like the
 * stock modules. It holds NO racket data of its own: the racket character data
 * is a static file shipped with the module, and price/stock are read live by
 * the catalog controller. So this controller is only the settings form.
 *
 * Methods:
 *  - index():   render and save the settings form (called by the admin router
 *               when the module is opened or a layout saves it).
 *  - validate(): permission check used by index() before a save.
 */
class ControllerExtensionModuleRacketFinder extends Controller {
	private $error = array();

	/**
	 * Render the settings form, and on POST save it as a module instance.
	 * Fields: name (module instance label), status, category_id (the catalog
	 * category that holds rackets, used by the catalog controller to decide
	 * which products to expose), cache_hours (live-data cache lifetime).
	 */
	public function index() {
		$this->load->language('extension/module/racket_finder');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/module');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('racket_finder', $this->request->post);
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
			}

			// Drop any cached live-data file so new settings take effect at once.
			$cache_file = DIR_CACHE . 'racket_finder.json';
			if (is_file($cache_file)) {
				unlink($cache_file);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_name']    = isset($this->error['name']) ? $this->error['name'] : '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/racket_finder', 'user_token=' . $this->session->data['user_token'], true)
			);

			$data['action'] = $this->url->link('extension/module/racket_finder', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/racket_finder', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);

			$data['action'] = $this->url->link('extension/module/racket_finder', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		// Pre-fill: POST (failed validation) > saved value > default.
		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($module_info['name'])) {
			$data['name'] = $module_info['name'];
		} else {
			$data['name'] = $this->language->get('heading_title');
		}

		if (isset($this->request->post['category_id'])) {
			$data['category_id'] = $this->request->post['category_id'];
		} elseif (!empty($module_info['category_id'])) {
			$data['category_id'] = $module_info['category_id'];
		} else {
			$data['category_id'] = 0;
		}

		if (isset($this->request->post['cache_hours'])) {
			$data['cache_hours'] = $this->request->post['cache_hours'];
		} elseif (isset($module_info['cache_hours'])) {
			$data['cache_hours'] = $module_info['cache_hours'];
		} else {
			$data['cache_hours'] = 1;
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (isset($module_info['status'])) {
			$data['status'] = $module_info['status'];
		} else {
			$data['status'] = 0;
		}

		// Flat category list (names carry the full path) for the picker.
		$this->load->model('catalog/category');
		$data['categories'] = $this->model_catalog_category->getCategories();

		// Public page URL, shown as a hint in the form.
		$data['page_url'] = HTTPS_CATALOG . 'index.php?route=extension/module/racket_finder.page';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/racket_finder', $data));
	}

	/**
	 * Permission guard for saving. Returns true when the user may modify this
	 * module. Called only from index() on POST.
	 */
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/racket_finder')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
