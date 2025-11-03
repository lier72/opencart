<?php  
class orders extends cdek_integrator implements exchange {
	
	protected $method = 'v2/orders';
	
	private $correct_direction = '+';
	private $correct_time = '00:00';
	
	public $number;
	public $post;
	
	public function setNumber($number) {
		$this->number = $number;
	}
	
	public function setOrders($data) {
		$this->post = $this->createPost($data);
	}
	
	public function setCorrrectTime($time) {
		$this->correct_time = $time;
	}
	
	public function setCorrrectdirection($direction) {
		$this->correct_direction = $direction;
	}
	
	public function getData(){
		return $this->post;
	}
	
	private function createPost($order_info = array()) {
		if (!empty($order_info)) {
			$order = array();
			$order['number'] = $order_info['order_id'];		
			$order['tariff_code'] = $order_info['tariff_id'];	

			if ($order_info['cdek_comment'] != '') {
				$order['comment'] = $order_info['cdek_comment'];
			}

			$order['developer_key'] = "c6e7304995e8e1f513f1ec380ff89779";

			if ($order_info['address']['pvz_code'] != '') {
				$order['delivery_point'] = $order_info['address']['pvz_code'];
			}

			if ($order_info['shipment_point'] != '') {
				$order['shipment_point'] = $order_info['shipment_point'];
			}
			
			if ($order_info['delivery_recipient_cost'] != '') {
				$order['delivery_recipient_cost']['value'] = $order_info['delivery_recipient_cost'];
				$order['delivery_recipient_cost']['vat_sum'] = $order_info['delivery_recipient_vat_sum'];
				$order['delivery_recipient_cost']['vat_rate'] = $order_info['delivery_recipient_vat_rate'];
			}

			if ($order_info['seller_name'] != '') {
				$order['seller']['name'] = $order_info['seller_name'];
			}

			if ($order_info['seller_inn'] != '') {
				$order['seller']['inn'] = $order_info['seller_inn'];
			}

			if ($order_info['seller_telephone'] != '') {
				$order['seller']['phone'] = $order_info['seller_telephone'];
			}

			if ($order_info['seller_ownership'] != '') {
				$order['seller']['ownership_form'] = $order_info['seller_ownership'];
			}

			if ($order_info['seller_address'] != '') {
				$order['seller']['address'] = $order_info['seller_address'];
			}

			if ($order_info['recipient_name'] != '') {
				$order['recipient']['name'] = $order_info['recipient_name'];
			}

			if ($order_info['recipient_email'] != '') {
				$order['recipient']['email'] = $order_info['recipient_email'];
			}

			if ($order_info['recipient_telephone'] != '') {
				$order['recipient']['phones'][] = array('number' => $order_info['recipient_telephone']);
			}

			if (empty($order['shipment_point'])) {

				if ($order_info['city_id'] != '') {
					$order['from_location']['code'] = $order_info['city_id'];
				}

				if ($order_info['city_name'] != '') {
					$order['from_location']['city'] = $order_info['city_name'];
				}

				if ($order_info['sell_address'] != '') {
					$order['from_location']['address'] = $order_info['sell_address'];
				}

			}

			if (empty($order['delivery_point'])) {

				if ($order_info['recipient_city_id'] != '') {
					$order['to_location']['code'] = $order_info['recipient_city_id'];
				}

				if ($order_info['recipient_city_name'] != '') {
					$order['to_location']['city'] = $order_info['recipient_city_name'];
				}

				if (!empty($order_info['address'])) {
					$order['to_location']['address'] = 'улица: ' . $order_info['address']['street'] . ', дом: ' . $order_info['address']['house'] . ', квартира: ' . $order_info['address']['flat'];
				}

			}

			$services = array();

			if (!empty($order_info['add_service'])) {
				foreach ($order_info['add_service'] as $code => $info) {
					$services[] = array('code' => (string)$code);
				}
			}

			if (!empty($services)) {
				$order['services'] = $services;
			}

			$order['packages'] = array();

			foreach ($order_info['package'] as $package_id => $package_info) {
				$package = array();
				$package['number'] = $package_id;
				$package['weight'] = $package_info['weight'];

				$package['length'] = $package_info['size_a'];
				$package['width'] = $package_info['size_b'];
				$package['height'] = $package_info['size_c'];

				$package['items'] = array();
				foreach ($package_info['item'] as $item) {
					$item_info = array();
					$item_info['name'] = trim(strip_tags(html_entity_decode($item['comment'], ENT_QUOTES, 'UTF-8')));
					$item_info['ware_key'] = $item['ware_key'];
					$item_info['payment']['value'] = $this->normalizePrice($item['payment']);
					$item_info['payment']['vat_sum'] = $this->normalizePrice($item['payment_vat_sum']);
					$item_info['payment']['vat_rate'] = $item['payment_vat_rate'];
					$item_info['cost'] = $this->normalizePrice($item['cost']);
					$item_info['weight'] = (int)$item['weight'];
					$item_info['amount'] = (int)$item['amount'];
					$package['items'][] = $item_info;
				}
				$order['packages'][] = $package;
			}

			return $order;
		}
	}
	
	private function normalizePrice($price) {
		return (float)round(str_replace(',', '.', $price), 4);
	}
}

?>