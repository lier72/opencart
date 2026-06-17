<?php
class ControllerExtensionFeedYandexMarket extends Controller {

	private $error = array();

	public function index() {
		$this->load->language('extension/feed/yandex_market');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
			if (isset($this->request->post['feed_yandex_market_categories'])) {
				$this->request->post['feed_yandex_market_categories'] = implode(',', $this->request->post['feed_yandex_market_categories']);
			}

			$this->model_setting_setting->editSetting('feed_yandex_market', $this->request->post);
			$this->model_setting_setting->editSetting('feed_cache', array('feed_cache_ttl' => max(1, (int)($this->request->post['feed_cache_ttl'] ?? 1))));

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true));
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_select_all'] = $this->language->get('text_select_all');
		$data['text_unselect_all'] = $this->language->get('text_unselect_all');

		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_data_feed'] = $this->language->get('entry_data_feed');
		$data['entry_shopname'] = $this->language->get('entry_shopname');
		$data['entry_company'] = $this->language->get('entry_company');
		$data['entry_category'] = $this->language->get('entry_category');
		$data['entry_currency'] = $this->language->get('entry_currency');
		$data['entry_in_stock'] = $this->language->get('entry_in_stock');
		$data['entry_out_of_stock'] = $this->language->get('entry_out_of_stock');

		$data['help_shopname'] = $this->language->get('help_shopname');
		$data['help_company'] = $this->language->get('help_company');
		$data['help_category'] = $this->language->get('help_category');
		$data['help_currency'] = $this->language->get('help_currency');
		$data['help_in_stock'] = $this->language->get('help_in_stock');
		$data['help_out_of_stock'] = $this->language->get('help_out_of_stock');
		$data['help_yandex_market'] = $this->language->get('help_yandex_market');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['tab_general'] = $this->language->get('tab_general');

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
			'href' => $this->url->link('extension/feed/yandex_market', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/feed/yandex_market', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true);

		if (isset($this->request->post['feed_yandex_market_status'])) {
			$data['feed_yandex_market_status'] = $this->request->post['feed_yandex_market_status'];
		} else {
			$data['feed_yandex_market_status'] = $this->config->get('feed_yandex_market_status');
		}

		$data['feed_cache_ttl'] = isset($this->request->post['feed_cache_ttl'])
			? (int)$this->request->post['feed_cache_ttl']
			: ((int)$this->config->get('feed_cache_ttl') ?: 1);

		$data['entry_cache_ttl'] = $this->language->get('entry_cache_ttl');
		$data['help_cache_ttl']  = $this->language->get('help_cache_ttl');

		$data['data_feed'] = HTTPS_CATALOG . 'index.php?route=extension/feed/yandex_market';

		if (isset($this->request->post['feed_yandex_market_shopname'])) {
			$data['feed_yandex_market_shopname'] = $this->request->post['feed_yandex_market_shopname'];
		} else {
			$data['feed_yandex_market_shopname'] = $this->config->get('feed_yandex_market_shopname');
		}

		if (isset($this->request->post['feed_yandex_market_company'])) {
			$data['feed_yandex_market_company'] = $this->request->post['feed_yandex_market_company'];
		} else {
			$data['feed_yandex_market_company'] = $this->config->get('feed_yandex_market_company');
		}

		if (isset($this->request->post['feed_yandex_market_currency'])) {
			$data['feed_yandex_market_currency'] = $this->request->post['feed_yandex_market_currency'];
		} else {
			$data['feed_yandex_market_currency'] = $this->config->get('feed_yandex_market_currency');
		}

		if (isset($this->request->post['feed_yandex_market_in_stock'])) {
			$data['feed_yandex_market_in_stock'] = $this->request->post['feed_yandex_market_in_stock'];
		} elseif ($this->config->get('feed_yandex_market_in_stock')) {
			$data['feed_yandex_market_in_stock'] = $this->config->get('feed_yandex_market_in_stock');
		} else {
			$data['feed_yandex_market_in_stock'] = 7;
		}

		if (isset($this->request->post['feed_yandex_market_out_of_stock'])) {
			$data['feed_yandex_market_out_of_stock'] = $this->request->post['feed_yandex_market_out_of_stock'];
		} elseif ($this->config->get('feed_yandex_market_in_stock')) {
			$data['feed_yandex_market_out_of_stock'] = $this->config->get('feed_yandex_market_out_of_stock');
		} else {
			$data['feed_yandex_market_out_of_stock'] = 5;
		}

		$this->load->model('localisation/stock_status');

		$data['stock_statuses'] = $this->model_localisation_stock_status->getStockStatuses();

		$this->load->model('catalog/category');

		$data['categories'] = $this->model_catalog_category->getCategories(0);

		if (isset($this->request->post['feed_yandex_market_categories'])) {
			$data['feed_yandex_market_categories'] = $this->request->post['feed_yandex_market_categories'];
		} elseif ($this->config->get('feed_yandex_market_categories') != '') {
			$data['feed_yandex_market_categories'] = explode(',', $this->config->get('feed_yandex_market_categories'));
		} else {
			$data['feed_yandex_market_categories'] = array();
		}

		$this->load->model('localisation/currency');
		$currencies = $this->model_localisation_currency->getCurrencies();
		$allowed_currencies = array_flip(array('RUR', 'RUB', 'BYR', 'KZT', 'UAH'));
		$data['currencies'] = array_intersect_key($currencies, $allowed_currencies);

		// Pass user_token to template for AJAX requests
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/feed/yandex_market', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/feed/yandex_market')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Clear feed cache
	 */
	public function clearCache() {
		$this->load->language('extension/feed/yandex_market');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/feed/yandex_market')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$cache_file = DIR_CACHE . 'yandex_market_feed.xml';
			$cache_hash_file = DIR_CACHE . 'yandex_market_feed.hash';

			$deleted = 0;

			if (file_exists($cache_file)) {
				unlink($cache_file);
				$deleted++;
			}

			if (file_exists($cache_hash_file)) {
				unlink($cache_hash_file);
				$deleted++;
			}

			if ($deleted > 0) {
				$json['success'] = 'Cache cleared successfully! ' . $deleted . ' file(s) deleted.';
			} else {
				$json['success'] = 'No cache files found.';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Get cache info
	 */
	public function cacheInfo() {
		$json = array();

		$cache_file = DIR_CACHE . 'yandex_market_feed.xml';
		$cache_hash_file = DIR_CACHE . 'yandex_market_feed.hash';

		$json['cache_exists'] = file_exists($cache_file);
		$json['hash_exists'] = file_exists($cache_hash_file);

		if (file_exists($cache_file)) {
			$json['cache_size'] = $this->formatBytes(filesize($cache_file));
			$json['cache_modified'] = date('Y-m-d H:i:s', filemtime($cache_file));
		}

		if (file_exists($cache_hash_file)) {
			$json['hash_value'] = file_get_contents($cache_hash_file);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Format bytes to human-readable size
	 */
	private function formatBytes($bytes, $precision = 2) {
		$units = array('B', 'KB', 'MB', 'GB', 'TB');

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);

		$bytes /= pow(1024, $pow);

		return round($bytes, $precision) . ' ' . $units[$pow];
	}
}
