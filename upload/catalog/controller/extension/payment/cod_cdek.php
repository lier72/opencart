<?php
class ControllerExtensionPaymentCodCdek extends Controller {
	
	public function index() {

		$this->load->language('extension/payment/cod_cdek');

		$data['text_description'] = $this->language->get('text_description');
		$data['text_payment'] = $this->language->get('text_payment');
		$data['text_loading'] = $this->language->get('text_loading');

    	$data['button_confirm'] = $this->language->get('button_confirm');

		$data['continue'] = $this->url->link('checkout/success');

		return $this->load->view('extension/payment/cod_cdek', $data);
	}
	
	public function confirm() {
		$json = array();

		if ($this->session->data['payment_method']['code'] == 'cod_cdek') {
			$this->load->model('checkout/order');

			$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_cod_cdek_order_status_id'));

			$json['redirect'] = $this->url->link('checkout/success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
?>