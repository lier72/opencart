<?php
class ModelPaymentyandexpluspluscard extends Model { 
public function getMethod($address, $total) {
		$this->load->language('payment/yandexplusplus_card');
    if ($this->config->get('yandexplusplus_card_status')) {
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('yandexplusplus_card_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

			if (!$this->config->get('yandexplusplus_card_geo_zone_id')) {
				$status = TRUE;
				if ($this->config->get('yandexplusplus_card_maxpay')){
						if ($this->currency->format($total, 'RUB', $this->currency->getValue('RUB'), false) <= $this->config->get('yandexplusplus_card_maxpay')) {
							$status = true;
							if ($total > 0) {
								$status = true;
							} else {
								$status = false;
							}
						} else {
							$status = false;
						}
				}
				else{
					if ($total > 0) {
						$status = true;
					} else {
						$status = false;
					}
				}
			} elseif ($query->num_rows) {
				$status = TRUE;
				if ($this->config->get('yandexplusplus_card_maxpay')){
						if ($this->currency->format($total, 'RUB', $this->currency->getValue('RUB'), false) <= $this->config->get('yandexplusplus_card_maxpay')) {
							$status = true;
							if ($total > 0) {
								$status = true;
							} else {
								$status = false;
							}
						} else {
							$status = false;
						}
				}
				else{
					if ($total > 0) {
						$status = true;
					} else {
						$status = false;
					}
				}
			} else {
				$status = FALSE;
			}
		} else {
			$status = FALSE;
		}
    
		$method_data = array();
		if ($status) {  
			if ($this->config->get('yandexplusplus_card_name_attach')) {
				$metname = htmlspecialchars_decode($this->config->get('yandexplusplus_card_name_' . $this->config->get('config_language_id')));
			}
			else{
				$metname = $this->language->get('text_title');
			}
			$method_data = array( 
				'code'       => 'yandexplusplus_card',
				'title'      => $metname,
				'terms'      => '',
				'sort_order' => $this->config->get('yandexplusplus_card_sort_order')
			);
		}
		
    	return $method_data;
  	}
}
?>