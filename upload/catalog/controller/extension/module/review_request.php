<?php
class ControllerExtensionModuleReviewRequest extends Controller {
	public function index($setting = array()) {
		$this->load->language('extension/module/review_request');
		$this->load->model('extension/module/review_request');

		if (!$this->model_extension_module_review_request->isEnabled()) {
			return '';
		}

		$data = $this->buildViewData(array(), true);

		if (!$data) {
			return '';
		}

		return $this->load->view('extension/module/review_request', $data);
	}

	public function order() {
		$this->load->language('extension/module/review_request');
		$this->load->model('extension/module/review_request');

		if (!$this->model_extension_module_review_request->canShowOnOrderPage()) {
			return '';
		}

		if (!$this->customer->isLogged() || !isset($this->request->get['order_id'])) {
			return '';
		}

		$this->load->model('account/order');

		$order_id = (int)$this->request->get['order_id'];
		$order_info = $this->model_account_order->getOrder($order_id);

		if (!$order_info) {
			return '';
		}

		$order_products = $this->model_account_order->getOrderProducts($order_id);
		$product_review_links = $this->getProductReviewLinks($order_products);
		$channels = $this->model_extension_module_review_request->getChannels();

		if (!$this->model_extension_module_review_request->canAskOrganizationReview($order_info['email'])) {
			$channels = array();
		}

		$data = $this->buildViewData($product_review_links, false, $channels);

		if (!$data) {
			return '';
		}

		return $this->load->view('extension/module/review_request', $data);
	}

	public function queueOrder($route, $args, $output) {
		if (!$this->config->get('module_review_request_status') || !$this->config->get('module_review_request_email_status')) {
			return;
		}

		$order_id = isset($args[0]) ? (int)$args[0] : 0;
		$order_status_id = isset($args[1]) ? (int)$args[1] : 0;

		if (!$order_id || !$order_status_id) {
			return;
		}

		$this->load->model('checkout/order');
		$this->load->model('extension/module/review_request');

		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			return;
		}

		$this->model_extension_module_review_request->queueOrder($order_info, $order_status_id);
	}

	public function redirect() {
		$this->load->model('extension/module/review_request');

		$review_request_id = isset($this->request->get['review_request_id']) ? (int)$this->request->get['review_request_id'] : 0;
		$channel = isset($this->request->get['channel']) ? preg_replace('/[^a-z]/', '', (string)$this->request->get['channel']) : '';
		$target_url = $this->model_extension_module_review_request->getTrackedOrganizationUrl($review_request_id, $channel);

		if (!$target_url) {
			$target_url = $this->url->link('common/home');
		} elseif ($this->isReviewClickTrackingEnabled()) {
			$this->model_extension_module_review_request->trackOrganizationReviewClick($review_request_id, $channel);
		}

		$this->response->redirect($target_url);
	}

	private function buildViewData($product_review_links, $show_widgets, $channels = null) {
		if ($channels === null) {
			$channels = $this->model_extension_module_review_request->getChannels();
		}

		$decorated_channels = array();
		$has_actions = false;
		$has_widgets = false;

		foreach ($channels as $channel) {
			$button_class = $channel['code'] == 'yandex' ? 'btn-danger' : 'btn-primary';
			$button_text = $channel['code'] == 'yandex' ? $this->language->get('text_yandex') : $this->language->get('text_google');

			if (!empty($channel['url'])) {
				$has_actions = true;
			}

			if ($show_widgets && !empty($channel['widget_code'])) {
				$has_widgets = true;
			}

			$decorated_channels[] = array(
				'code' => $channel['code'],
				'button_class' => $button_class,
				'button_text' => $button_text,
				'url' => $channel['url'],
				'widget_code' => $channel['widget_code']
			);
		}

		if (!$has_actions && !$has_widgets && !$product_review_links) {
			return array();
		}

		if ($product_review_links && $has_actions) {
			$text_intro = $this->language->get('text_intro_order');
		} elseif ($product_review_links) {
			$text_intro = $this->language->get('text_intro_order_product_only');
		} else {
			$text_intro = $this->language->get('text_intro');
		}

		return array(
			'heading_title' => $this->language->get('heading_title'),
			'text_intro' => $text_intro,
			'text_widgets' => $this->language->get('text_widgets'),
			'text_product_reviews' => $this->language->get('text_product_reviews'),
			'text_write_review' => $this->language->get('text_write_review'),
			'channels' => $decorated_channels,
			'product_review_links' => $product_review_links,
			'show_widgets' => $show_widgets
		);
	}

	private function getProductReviewLinks($order_products) {
		if (!$this->config->get('module_review_request_include_product_reviews') || !$this->config->get('config_review_status')) {
			return array();
		}

		if (!$this->customer->isLogged() && !$this->config->get('config_review_guest')) {
			return array();
		}

		$review_links = array();
		$seen = array();

		foreach ($order_products as $order_product) {
			$product_id = (int)$order_product['product_id'];

			if (isset($seen[$product_id])) {
				continue;
			}

			$seen[$product_id] = true;
			$review_links[] = array(
				'name' => $order_product['name'],
				'url' => $this->url->link('product/product', 'product_id=' . $product_id, true) . '#tab-review'
			);
		}

		return $review_links;
	}

	private function isReviewClickTrackingEnabled() {
		$value = $this->config->get('module_review_request_track_review_clicks');

		if ($value === null || $value === '') {
			return true;
		}

		return (bool)$value;
	}
}
