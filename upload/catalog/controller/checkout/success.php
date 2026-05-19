<?php
class ControllerCheckoutSuccess extends Controller {
	public function index() {
		$this->load->language('checkout/success');

		$order_id = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;

		if ($order_id) {
			$this->cart->clear();

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['guest']);
			unset($this->session->data['comment']);
			unset($this->session->data['order_id']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
			unset($this->session->data['voucher']);
			unset($this->session->data['vouchers']);
			unset($this->session->data['totals']);
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_success'),
			'href' => $this->url->link('checkout/success')
		);

		if ($this->customer->isLogged()) {
			$data['text_message'] = sprintf($this->language->get('text_customer'), $this->url->link('account/account', '', true), $this->url->link('account/order', '', true), $this->url->link('account/download', '', true), $this->url->link('information/contact'));
		} else {
			$data['text_message'] = sprintf($this->language->get('text_guest'), $this->url->link('information/contact'));
		}

		$data['gcr_optin'] = false;

		$gcr_merchant_id = (string)$this->config->get('config_gcr_merchant_id');
		if ($gcr_merchant_id === '') {
			$gcr_merchant_id = '116034203';
		}

		$gcr_allowed = true;
		$gcr_customer_id = 0;

		if ($this->customer->isLogged()) {
			$gcr_customer_id = (int)$this->customer->getId();
			$gcr_allowed = ((int)$this->customer->getGroupId() === (int)$this->config->get('config_customer_group_id'));

			if ($gcr_allowed) {
				$this->load->model('account/customer');

				$gcr_allowed = !$this->model_account_customer->hasGcrOptinShown($gcr_customer_id);
			}
		}

		if ($order_id && $gcr_merchant_id !== '' && $gcr_allowed) {
			$this->load->model('checkout/order');

			$order_info = $this->model_checkout_order->getOrder($order_id);

			if ($order_info) {
				$delivery_country = $order_info['shipping_iso_code_2'] ? $order_info['shipping_iso_code_2'] : $order_info['payment_iso_code_2'];
				$estimated_days = (int)$this->config->get('config_gcr_estimated_delivery_days');

				if ($estimated_days <= 0) {
					$estimated_days = 5;
				}

				if (!empty($order_info['email']) && !empty($delivery_country)) {
					$data['gcr_optin'] = array(
						'merchant_id' => $gcr_merchant_id,
						'order_id' => (string)$order_id,
						'email' => $order_info['email'],
						'delivery_country' => $delivery_country,
						'estimated_delivery_date' => date('Y-m-d', strtotime($order_info['date_added'] . ' +' . $estimated_days . ' days'))
					);

					if ($gcr_customer_id) {
						$this->model_account_customer->markGcrOptinShown($gcr_customer_id);
					}
				}
			}
		}

		$data['continue'] = $this->url->link('common/home');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/success', $data));
	}
}
