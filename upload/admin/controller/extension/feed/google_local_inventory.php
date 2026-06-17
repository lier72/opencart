<?php
class ControllerExtensionFeedGoogleLocalInventory extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/feed/google_local_inventory');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('feed_google_local_inventory', $this->request->post);
			$this->model_setting_setting->editSetting('feed_cache', array('feed_cache_ttl' => max(1, (int)($this->request->post['feed_cache_ttl'] ?? 1))));

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/feed/google_local_inventory', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/feed/google_local_inventory', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true);

		$data['data_feed'] = HTTPS_CATALOG . 'index.php?route=extension/feed/google_local_inventory';

		if (isset($this->request->post['feed_google_local_inventory_status'])) {
			$data['feed_google_local_inventory_status'] = $this->request->post['feed_google_local_inventory_status'];
		} else {
			$data['feed_google_local_inventory_status'] = $this->config->get('feed_google_local_inventory_status');
		}

		if (isset($this->request->post['feed_google_local_inventory_store_code'])) {
			$data['feed_google_local_inventory_store_code'] = $this->request->post['feed_google_local_inventory_store_code'];
		} else {
			$data['feed_google_local_inventory_store_code'] = $this->config->get('feed_google_local_inventory_store_code');
		}

		$data['feed_cache_ttl'] = isset($this->request->post['feed_cache_ttl'])
			? (int)$this->request->post['feed_cache_ttl']
			: ((int)$this->config->get('feed_cache_ttl') ?: 1);

		$data['entry_cache_ttl'] = $this->language->get('entry_cache_ttl');
		$data['help_cache_ttl']  = $this->language->get('help_cache_ttl');

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/feed/google_local_inventory', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/feed/google_local_inventory')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
