<?php
class ControllerPaymentyandexpluspluscard extends Controller {
	public function index() {
		$this->language->load('payment/yandexplusplus_card');
		$data['instructionat'] = $this->config->get('yandexplusplus_card_instruction_attach');
		$data['btnlater'] = $this->config->get('yandexplusplus_card_button_later');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$action= $this->url->link('account/yandexplusplus/pay');
		$paymentredir = $action .
				'&paymentType=' . $order_info['payment_code'] .
				'&order_id='	. $order_info['order_id'] . 
				'&first=1';

		$online_url = $this->url->link('account/yandexplusplus') .
					'&order_id='	. $order_info['order_id'];

	  	$data['continue'] = $this->url->link('checkout/success');

		$this->load->language('account/yandexplusplus_card');

		if ($this->config->get('yandexplusplus_card_fixen')) {
			if ($this->config->get('yandexplusplus_card_fixen') == 'fix'){
			    $out_summ = $this->config->get('yandexplusplus_card_fixen_amount');
			}
			else{
			    $out_summ = $order_info['total'] * $this->config->get('yandexplusplus_card_fixen_amount') / 100;
			}
		}
		else{
			$out_summ = $order_info['total'];
		}

		$data['pay_url'] = $paymentredir;
		$data['button_confirm'] = $this->language->get('button_confirm');
		$data['payment_url'] = $this->url->link('checkout/success');
		$data['button_later'] = $this->language->get('button_pay_later');

		if ($this->config->get('yandexplusplus_card_instruction_attach')){
			$data['text_instruction'] = $this->language->get('text_instruction');

			$instros = explode('$', ($this->config->get('yandexplusplus_card_instruction_' . $this->config->get('config_language_id'))));
			$instroz = "";
			foreach ($instros as $instro) {
				if ($instro == 'href' || $instro == 'orderid' ||  $instro == 'itogo' || $instro == 'komis' || $instro == 'total-komis' || $instro == 'plus-komis'){
				    if ($instro == 'href'){
				        $instro_other = $online_url;
				    }
				    if ($instro == 'orderid'){
				        $instro_other = $order_info['order_id'];
					}
					if ($instro == 'itogo'){
					    $instro_other = $this->currency->format($out_summ, $order_info['currency_code'], $order_info['currency_value'], true);
					}
					if ($instro == 'komis'){
						if($this->config->get('yandexplusplus_card_komis')){
					    	$instro_other = $this->config->get('yandexplusplus_card_komis') . '%';
						}
						else{$instro_other = '';}
					}
					if ($instro == 'total-komis'){
						if($this->config->get('yandexplusplus_card_komis')){
					    	$instro_other = $this->currency->format($out_summ * $this->config->get('yandexplusplus_card_komis')/100, $order_info['currency_code'], $order_info['currency_value'], true);
						}
						else{$instro_other = '';}
					}
					if ($instro == 'plus-komis'){
						if($this->config->get('yandexplusplus_card_komis')){
					    	$instro_other = $this->currency->format($out_summ + ($out_summ * $this->config->get('yandexplusplus_card_komis')/100), $order_info['currency_code'], $order_info['currency_value'], true);
						}
						else{$instro_other = '';}
					}
				}
				else {
				    $instro_other = nl2br(htmlspecialchars_decode($instro));
				}
				    $instroz .=  $instro_other;
			}
				$data['yandexplusplus_cardi'] = $instroz;
		}

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/yandexplusplus_card.tpl')) {
            return $this->load->view($this->config->get('config_template') . '/template/payment/yandexplusplus_card.tpl', $data);
		} else {
            return $this->load->view('default/template/payment/yandexplusplus_card.tpl', $data);
        }		 
	}
	
	public function confirm() {
		if ($this->config->get('yandexplusplus_card_createorder_or_notcreate')){ exit(); }
  		$this->language->load('payment/yandexplusplus_card');
		$this->load->model('checkout/order');
			if ($this->config->get('yandexplusplus_card_mail_instruction_attach')){
				$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
				$inv_id = $this->session->data['order_id'];
				if ($this->config->get('yandexplusplus_card_fixen')) {
					if ($this->config->get('yandexplusplus_card_fixen') == 'fix'){
					    $out_summ = $this->config->get('yandexplusplus_card_fixen_amount');
					}
					else{
					    $out_summ = $order_info['total'] * $this->config->get('yandexplusplus_card_fixen_amount') / 100;
					}
				}
				else{
					$out_summ = $order_info['total'];
				}
				$action= $order_info['store_url'] . 'index.php?route=account/yandexplusplus';
				$online_url = $action .

				'&order_id='	. $order_info['order_id'];

		    	$comment  = $this->language->get('text_instruction') . "\n\n";
		    	$instros = explode('$', ($this->config->get('yandexplusplus_card_mail_instruction_' . $order_info['language_id'])));
				      $instroz = "";
				      foreach ($instros as $instro) {
				      	if ($instro == 'href' || $instro == 'orderid' ||  $instro == 'itogo' || $instro == 'komis' || $instro == 'total-komis' || $instro == 'plus-komis'){
				      		if ($instro == 'href'){
				            	$instro_other = $online_url;
				        	}
				            if ($instro == 'orderid'){
				            	$instro_other = $inv_id;
					       	}
					       	if ($instro == 'itogo'){
					            $instro_other = $this->currency->format($out_summ, $order_info['currency_code'], $order_info['currency_value'], true);
					       	}
					       	if ($instro == 'komis'){
								if($this->config->get('yandexplusplus_card_komis')){
							    	$instro_other = $this->config->get('yandexplusplus_card_komis') . '%';
								}
								else{$instro_other = '';}
							}
							if ($instro == 'total-komis'){
								if($this->config->get('yandexplusplus_card_komis')){
							    	$instro_other = $this->currency->format($out_summ * $this->config->get('yandexplusplus_card_komis')/100, $order_info['currency_code'], $order_info['currency_value'], true);
								}
								else{$instro_other = '';}
							}
							if ($instro == 'plus-komis'){
								if($this->config->get('yandexplusplus_card_komis')){
							    	$instro_other = $this->currency->format($out_summ + ($out_summ * $this->config->get('yandexplusplus_card_komis')/100), $order_info['currency_code'], $order_info['currency_value'], true);
								}
								else{$instro_other = '';}
							}
				       	}
				       	else {
				       		$instro_other = nl2br($instro);
				       	}
				       	$instroz .=  $instro_other;
				      }
				$comment .= $instroz;
		    	$comment = htmlspecialchars_decode($comment);
		    	$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('yandexplusplus_card_on_status_id'), $comment, true);
	    	}
	    	else{
				$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('yandexplusplus_card_on_status_id'), true);
			}
	}
}
?>