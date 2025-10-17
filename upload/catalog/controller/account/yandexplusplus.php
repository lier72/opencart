<?php
class ControllerAccountyandexplusplus extends Controller {
    private $error = array();
    public function index() {
      $this->load->language('account/yandexplusplus');
      $data['button_pay'] = $this->language->get('button_pay');
  		$data['button_back'] = $this->language->get('button_back');
      $data['heading_title'] = $this->language->get('heading_title');
      if (isset($this->request->post['order_id']) || isset($this->request->get['order_id'])){
        if (isset($this->request->post['order_id'])){$inv_id = $this->request->post['order_id'];}
        if (isset($this->request->get['order_id'])){$inv_id = $this->request->get['order_id'];}
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($inv_id);
        if ($order_info['order_id'] == 0){$this->response->redirect($this->url->link('error/not_found'));}
        if (!$this->customer->isLogged()) {
          $data['back'] = $this->url->link('common/home');
        }
        else{
          $data['back'] = $this->url->link('account/order');
        }
	      $action = $this->url->link('account/yandexplusplus/pay');
    
    		$data['merchant_url'] = $action .

							'&order_id=' 		. $inv_id .
              '&paymentType=' . $order_info['payment_code'];

        $this->load->model('account/yandexplusplus');
        $paystat = $this->model_account_yandexplusplus->getPaymentStatus($inv_id);
        if (!isset($paystat['status'])){$paystat['status'] = 0;}
        $data['paystat'] = $paystat['status'];
        if ($paystat['status'] != 1){
	        if ($order_info['payment_code'] == 'yandexplusplus'){
	        	if ($this->config->get('yandexplusplus_fixen')) {
					if ($this->config->get('yandexplusplus_fixen') == 'fix'){
					    $out_summ = $this->config->get('yandexplusplus_fixen_amount');
					}
					else{
					    $out_summ = $order_info['total'] * $this->config->get('yandexplusplus_fixen_amount') / 100;
					}
				}
				else{
					$out_summ = $order_info['total'];
				}
		        if ($this->config->get('yandexplusplus_hrefpage_text_attach')) {
		          $data['hrefpage_text'] = '';

		          $instros = explode('$', ($this->config->get('yandexplusplus_hrefpage_text_' . $this->config->get('config_language_id'))));
		                  $instroz = "";
		                  foreach ($instros as $instro) {
		                    if ($instro == 'orderid' ||  $instro == 'itogo' || $instro == 'komis' || $instro == 'total-komis' || $instro == 'plus-komis'){
		                      
		                        if ($instro == 'orderid'){
		                            $instro_other = $order_info['order_id'];
		                      }
		                      if ($instro == 'itogo'){
		                          $instro_other = $this->currency->format($out_summ, $order_info['currency_code'], $order_info['currency_value'], true);
		                      }
		                      if ($instro == 'komis'){
		                        if($this->config->get('yandexplusplus_komis')){
		                            $instro_other = $this->config->get('yandexplusplus_komis') . '%';
		                        }
		                        else{$instro_other = '';}
		                      }
		                      if ($instro == 'total-komis'){
		                        if($this->config->get('yandexplusplus_komis')){
		                            $instro_other = $this->currency->format($out_summ * $this->config->get('yandexplusplus_komis')/100, $order_info['currency_code'], $order_info['currency_value'], true);
		                        }
		                        else{$instro_other = '';}
		                      }
		                      if ($instro == 'plus-komis'){
		                        if($this->config->get('yandexplusplus_komis')){
		                            $instro_other = $this->currency->format($out_summ + ($out_summ * $this->config->get('yandexplusplus_komis')/100), $order_info['currency_code'], $order_info['currency_value'], true);
		                        }
		                        else{$instro_other = '';}
		                      }
		                    }
		                    else {
		                      $instro_other = nl2br(htmlspecialchars_decode($instro));
		                    }
		                    $instroz .=  $instro_other;
		                  }

		          $data['hrefpage_text'] .= $instroz;
		        }
		        else{        
		        $data['send_text'] = $this->language->get('send_text');
		        $data['send_text2'] = $this->language->get('send_text2');
		        $data['inv_id'] = $inv_id;
		        $data['out_summ'] = $this->currency->format($out_summ, $order_info['currency_code'], $order_info['currency_value'], true);
		        }
		    }
		    if ($order_info['payment_code'] == 'yandexplusplus_card'){
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
		    	if ($this->config->get('yandexplusplus_card_hrefpage_text_attach')) {
		          $data['hrefpage_text'] = '';

		          $instros = explode('$', ($this->config->get('yandexplusplus_card_hrefpage_text_' . $this->config->get('config_language_id'))));
		                  $instroz = "";
		                  foreach ($instros as $instro) {
		                    if ($instro == 'orderid' ||  $instro == 'itogo' || $instro == 'komis' || $instro == 'total-komis' || $instro == 'plus-komis'){
		                      
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

		          $data['hrefpage_text'] .= $instroz;
		        }
		        else{        
		        $data['send_text'] = $this->language->get('send_text');
		        $data['send_text2'] = $this->language->get('send_text2');
		        $data['inv_id'] = $inv_id;
		        $data['out_summ'] = $this->currency->format($out_summ, $order_info['currency_code'], $order_info['currency_value'], true);
		        }
		    }
		    else if ($order_info['payment_code'] != 'yandexplusplus_card' && $order_info['payment_code'] != 'yandexplusplus'){
		    	$this->response->redirect($this->url->link('error/not_found'));
		    }
		}
		else{
			$data['hrefpage_text'] = $this->language->get('oplachen');
		}


        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');


        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/account/yandexplusplus.tpl')) {
			$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/account/yandexplusplus.tpl', $data));
		} else {
			$this->response->setOutput($this->load->view('default/template/account/yandexplusplus.tpl', $data));
		}
    
      }
      else{
        echo "No data";
      }
    }

  public function pay() {
  	  if (isset($this->session->data['order_id'])){
  	  	$sesionord = $this->session->data['order_id'];
  	  }
  	  else{
  	  	$sesionord = '';
  	  }

      if ($this->request->get['order_id'] != $sesionord){
        if(!isset($this->request->post['nesyandexa'])){
        	if(isset($this->request->get['paymentType'])){
        		if(isset($this->request->get['first'])){$first = '&first=1';}else{$first = '';}
        		if($this->request->get['paymentType'] == 'yandexplusplus'){
        			$this->response->redirect($this->url->link('account/yandexplusplus/status&order_id='.$this->request->get['order_id'].$first));
        		}
        		if($this->request->get['paymentType'] == 'yandexplusplus_card'){
        			$this->response->redirect($this->url->link('account/yandexplusplus/status&order_id='.$this->request->get['order_id'].$first));
        		}
        	}
        	else{
        		$this->response->redirect($this->url->link('common/home'));
        	}
        }
      }
      
      if ($this->request->get['paymentType'] == 'yandexplusplus' || $this->request->get['paymentType'] == 'yandexplusplus_card'){

      	$this->load->model('checkout/order');
      	$order_info = $this->model_checkout_order->getOrder($this->request->get['order_id']);

      	$data['confname'] = $this->config->get('config_name');

        if ($this->request->get['paymentType'] == 'yandexplusplus'){

      	  $this->language->load('account/yandexplusplus');
      	  $data['order_text'] = $this->language->get('pay_order_text');
      	  $data['order_text_target'] = $this->language->get('pay_order_text_target');
          $data['paymentType'] = 'PC';
          $data['receiver'] = $this->config->get('yandexplusplus_login');
          if(!$this->config->get('yandexplusplus_createorder_or_notcreate')){
	          if (isset($this->session->data['order_id'])){
		        if ($this->request->get['order_id'] == $this->session->data['order_id']){
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
		        }
		      }
		  }
          if ($this->config->get('yandexplusplus_komis')){$proc=$this->config->get('yandexplusplus_komis');}
	      if ($this->config->get('yandexplusplus_fixen')) {
				if ($this->config->get('yandexplusplus_fixen') == 'fix'){
				    $out_summ = $this->config->get('yandexplusplus_fixen_amount');
				}
				else{
				    $out_summ = $order_info['total'] * $this->config->get('yandexplusplus_fixen_amount') / 100;
				}
		  }
		  else{
				$out_summ = $order_info['total'];
		  }
        }
        if ($this->request->get['paymentType'] == 'yandexplusplus_card'){

      	  $this->language->load('account/yandexplusplus_card');
      	  $data['order_text'] = $this->language->get('pay_order_text');
      	  $data['order_text_target'] = $this->language->get('pay_order_text_target');
          $data['paymentType'] = 'AC';
          $data['receiver'] = $this->config->get('yandexplusplus_card_login');
          if(!$this->config->get('yandexplusplus_card_createorder_or_notcreate')){
	          if (isset($this->session->data['order_id'])){
		        if ($this->request->get['order_id'] == $this->session->data['order_id']){
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
		        }
		      }
		  }
          if ($this->config->get('yandexplusplus_card_komis')){$proc=$this->config->get('yandexplusplus_card_komis');}
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
          }
      }
      else{
        echo 'error: no payment method';
        exit();
      }
      if (is_numeric($out_summ)){
        if ($this->currency->has('RUB')){
          $totalrub = $out_summ;
          if (isset($proc)){$data['total'] = $this->currency->format(($totalrub*$proc/100)+$totalrub, 'RUB', $this->currency->getValue('RUB'), false);}
          else{$data['total'] = $this->currency->format($totalrub, 'RUB', $this->currency->getValue('RUB'), false);}
        }
        else{echo 'No currency RUB'; exit();}
      }
      else{
        echo 'error: no total sum';
        exit();
      }

      if (is_numeric($this->request->get['order_id'])){
        $data['order_id'] = $this->request->get['order_id'];
      }
      else{
        echo 'error: no order id';
        exit();
      }


      if (isset($this->session->data['order_id'])){
        if ($this->request->get['order_id'] == $this->session->data['order_id']){
		    unset($this->session->data['order_id']); 
		}
      }



      if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/account/yandexplusplus_pay.tpl')) {
			$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/account/yandexplusplus_pay.tpl', $data));
		} else {
			$this->response->setOutput($this->load->view('default/template/account/yandexplusplus_pay.tpl', $data));
		}
    }

  public function callback() {
  if (isset($this->request->post['operation_id']) && isset($this->request->post['amount']) && ($this->request->post['codepro'] == 'false')){

  	$this->load->model('checkout/order');

	$order_info = $this->model_checkout_order->getOrder($this->request->post['label']);

	if ($this->request->post['notification_type'] == 'p2p-incoming'){
		if ($this->config->get('yandexplusplus_fixen')) {
			if ($this->config->get('yandexplusplus_fixen') == 'fix'){
			    $totalrub = $this->config->get('yandexplusplus_fixen_amount');
			}
			else{
			    $totalrub = $order_info['total'] * $this->config->get('yandexplusplus_fixen_amount') / 100;
			}
		}
		else{
			$totalrub = $order_info['total'];
		}
		$secret_key = $this->config->get('yandexplusplus_password');
		if ($this->config->get('yandexplusplus_komis')){
			$youpayment = $this->currency->format(($totalrub * $this->config->get('yandexplusplus_komis')/100) + $totalrub, 'RUB', $this->currency->getValue('RUB'), false);
		}
		else{
			$youpayment = $this->currency->format($totalrub, 'RUB', $this->currency->getValue('RUB'), false);
		}
	}
	if ($this->request->post['notification_type'] == 'card-incoming'){
		if ($this->config->get('yandexplusplus_card_fixen')) {
			if ($this->config->get('yandexplusplus_card_fixen') == 'fix'){
			    $totalrub = $this->config->get('yandexplusplus_card_fixen_amount');
			}
			else{
			    $totalrub = $order_info['total'] * $this->config->get('yandexplusplus_card_fixen_amount') / 100;
			}
		}
		else{
			$totalrub = $order_info['total'];
		}
		$secret_key = $this->config->get('yandexplusplus_card_password');
		if ($this->config->get('yandexplusplus_card_komis')){
			$youpayment = $this->currency->format(($totalrub * $this->config->get('yandexplusplus_card_komis')/100) + $totalrub, 'RUB', $this->currency->getValue('RUB'), false);
		}
		else{
			$youpayment = $this->currency->format($totalrub, 'RUB', $this->currency->getValue('RUB'), false);
		}
	}

	$payments = number_format($this->request->post['withdraw_amount'],2);
	$youpayment = number_format($youpayment,2);
	$this->load->model('account/yandexplusplus');
	if($youpayment == $payments){
		$yahash = $this->request->post['sha1_hash'];
		$myhash = $this->request->post['notification_type'].'&'.$this->request->post['operation_id'].'&'.$this->request->post['amount'].'&'.$this->request->post['currency'].'&'.$this->request->post['datetime'].'&'.$this->request->post['sender'].'&'.$this->request->post['codepro'].'&'.$this->model_account_yandexplusplus->decrypt($secret_key, $this->config->get('config_encryption')).'&'.$this->request->post['label'];
	  	$myhash = hash("sha1", $myhash);
		

		if($yahash == $myhash) {

			

			$paystat = $this->model_account_yandexplusplus->getPaymentStatus($this->request->post['label']);
		        if (!isset($paystat['status'])){$paystat['status'] = 0;}
		        if ($paystat['status'] != 1){
		  		echo "HTTP 200 OK";
		  		}
		  		else{
		  			exit();
		  		}				
		  		 
			$query = $this->db->query ("INSERT INTO `" . DB_PREFIX . "yandexplusplus` SET `num_order` = '".$order_info['order_id']."' , `sum` = '".$this->request->post['withdraw_amount']."' , `date_enroled` = '".$this->request->post['datetime']."', `date_created` = '".$order_info['date_added']."', `user` = '".$order_info['payment_firstname']." ".$order_info['payment_lastname']."', `email` = '".$order_info['email']."', `status` = '1', `sender` = '".$this->request->post['operation_id']."' ");
			if ($this->request->post['notification_type'] == 'p2p-incoming'){

				if($this->config->get('yandexplusplus_createorder_or_notcreate') && $order_info['order_status_id'] != $this->config->get('yandexplusplus_on_status_id')){
					$this->language->load('payment/yandexplusplus');
						if ($this->config->get('yandexplusplus_mail_instruction_attach')){
							$inv_id = $order_info['order_id'];
							if ($this->config->get('yandexplusplus_fixen')) {
								if ($this->config->get('yandexplusplus_fixen') == 'fix'){
								    $out_summ = $this->config->get('yandexplusplus_fixen_amount');
								}
								else{
								    $out_summ = $order_info['total'] * $this->config->get('yandexplusplus_fixen_amount') / 100;
								}
							}
							else{
								$out_summ = $order_info['total'];
							}
							$action= $order_info['store_url'] . 'index.php?route=account/yandexplusplus';
							$online_url = $action .

							'&order_id='	. $order_info['order_id'];

					    	$comment  = $this->language->get('text_instruction') . "\n\n";
					    	$instros = explode('$', ($this->config->get('yandexplusplus_mail_instruction_' . $order_info['language_id'])));
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
											if($this->config->get('yandexplusplus_komis')){
										    	$instro_other = $this->config->get('yandexplusplus_komis') . '%';
											}
											else{$instro_other = '';}
										}
										if ($instro == 'total-komis'){
											if($this->config->get('yandexplusplus_komis')){
										    	$instro_other = $this->currency->format($out_summ * $this->config->get('yandexplusplus_komis')/100, $order_info['currency_code'], $order_info['currency_value'], true);
											}
											else{$instro_other = '';}
										}
										if ($instro == 'plus-komis'){
											if($this->config->get('yandexplusplus_komis')){
										    	$instro_other = $this->currency->format($out_summ + ($out_summ * $this->config->get('yandexplusplus_komis')/100), $order_info['currency_code'], $order_info['currency_value'], true);
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
					    	$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_order_status_id'), $comment, true);
				    	}
				    	else{
							$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_order_status_id'), true);
						}

						if ($this->config->get('yandexplusplus_success_alert_customer')){
				        	if ($this->config->get('yandexplusplus_success_comment_attach')) {
				          		$instros = explode('$', ($this->config->get('yandexplusplus_success_comment_' . $order_info['language_id'])));
				              	$instroz = "";
				              	foreach ($instros as $instro) {
				                	if ($instro == 'orderid' ||  $instro == 'itogo'){
				                    	if ($instro == 'orderid'){
				                    		$instro_other = $order_info['order_id'];
				                  		}
				                  		if ($instro == 'itogo'){
				                      		$instro_other = $this->currency->format($totalrub, $order_info['currency_code'], $order_info['currency_value'], true);
				                  		}
				                	}
				                	else {
				                  		$instro_other = nl2br(htmlspecialchars_decode($instro));
				                	}
				                	$instroz .=  $instro_other;
				              	}
				          		$message = $instroz;
				          		$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_order_status_id'), $message, true);
				        	}
				        	else{
				          		$message = '';
				          		$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_order_status_id'), $message, true);
				        	}
				    	}
				}

				else{

					$yandexpay_status_id = $this->config->get('yandexplusplus_order_status_id');
			    	if ($this->config->get('yandexplusplus_success_alert_customer')){
			        	if ($this->config->get('yandexplusplus_success_comment_attach')) {
			          		$instros = explode('$', ($this->config->get('yandexplusplus_success_comment_' . $order_info['language_id'])));
			              	$instroz = "";
			              	foreach ($instros as $instro) {
			                	if ($instro == 'orderid' ||  $instro == 'itogo'){
			                    	if ($instro == 'orderid'){
			                    		$instro_other = $order_info['order_id'];
			                  		}
			                  		if ($instro == 'itogo'){
			                      		$instro_other = $this->currency->format($totalrub, $order_info['currency_code'], $order_info['currency_value'], true);
			                  		}
			                	}
			                	else {
			                  		$instro_other = nl2br(htmlspecialchars_decode($instro));
			                	}
			                	$instroz .=  $instro_other;
			              	}
			          		$message = $instroz;
			          		$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_order_status_id'), $message, true);
			        	}
			        	else{
			          		$message = '';
			          		$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_order_status_id'), $message, true);
			        	}
			    	}
			    	else{
			      			$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_order_status_id'), false);
			    	}

		    	}
			

		    	if ($this->config->get('yandexplusplus_success_alert_admin')) {
		      
		       		$subject = sprintf(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'), $order_info['order_id']);
		        
		        	// Text 
			        $this->load->language('account/yandexplusplus');
			        $text = sprintf($this->language->get('success_admin_alert'), $order_info['order_id']) . "\n";
			        
			        
			      
			        $mail = new Mail(); 
			        $mail->protocol = $this->config->get('config_mail_protocol');
			        $mail->parameter = $this->config->get('config_mail_parameter');
			        $mail->hostname = $this->config->get('config_smtp_host');
			        $mail->username = $this->config->get('config_smtp_username');
			        $mail->password = $this->config->get('config_smtp_password');
			        $mail->port = $this->config->get('config_smtp_port');
			        $mail->timeout = $this->config->get('config_smtp_timeout');
			        $mail->setTo($this->config->get('config_email'));
			        $mail->setFrom($this->config->get('config_email'));
			        $mail->setSender($order_info['store_name']);
			        $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
			        $mail->setText(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
			        $mail->send();
		        
		        	// Send to additional alert emails
		        	$emails = explode(',', $this->config->get('config_alert_emails'));
		        
		        	foreach ($emails as $email) {
		          		if ($email && preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $email)) {
		            		$mail->setTo($email);
		            		$mail->send();
		          		}
		        	}
		    	}
		    }
		    if ($this->request->post['notification_type'] == 'card-incoming'){

		    	if($this->config->get('yandexplusplus_card_createorder_or_notcreate') && $order_info['order_status_id'] != $this->config->get('yandexplusplus_card_on_status_id')){

		    		$this->language->load('payment/yandexplusplus_card');
						if ($this->config->get('yandexplusplus_card_mail_instruction_attach')){
							$inv_id = $order_info['order_id'];
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
					    	$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_card_order_status_id'), $comment, true);
				    	}
				    	else{
							$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_card_order_status_id'), true);
						}

						if ($this->config->get('yandexplusplus_card_success_alert_customer')){
				        	if ($this->config->get('yandexplusplus_card_success_comment_attach')) {
				          		$instros = explode('$', ($this->config->get('yandexplusplus_card_success_comment_' . $order_info['language_id'])));
				              	$instroz = "";
				              	foreach ($instros as $instro) {
				                	if ($instro == 'orderid' ||  $instro == 'itogo'){
				                    	if ($instro == 'orderid'){
				                    		$instro_other = $order_info['order_id'];
				                  		}
				                  		if ($instro == 'itogo'){
				                      		$instro_other = $this->currency->format($totalrub, $order_info['currency_code'], $order_info['currency_value'], true);
				                  		}
				                	}
				                	else {
				                  		$instro_other = nl2br(htmlspecialchars_decode($instro));
				                	}
				                	$instroz .=  $instro_other;
				              	}
				          		$message = $instroz;
				          		$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_card_order_status_id'), $message, true);
				        	}
				        else{
				          $message = '';
				          $this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_card_order_status_id'), $message, true);
				        }
				    }

		    	}

		    	else {

			    	$yandexpay_status_id = $this->config->get('yandexplusplus_card_order_status_id');
			    	if ($this->config->get('yandexplusplus_card_success_alert_customer')){
			        	if ($this->config->get('yandexplusplus_card_success_comment_attach')) {
			          		$instros = explode('$', ($this->config->get('yandexplusplus_card_success_comment_' . $order_info['language_id'])));
			              	$instroz = "";
			              	foreach ($instros as $instro) {
			                	if ($instro == 'orderid' ||  $instro == 'itogo'){
			                    	if ($instro == 'orderid'){
			                    		$instro_other = $order_info['order_id'];
			                  		}
			                  		if ($instro == 'itogo'){
			                      		$instro_other = $this->currency->format($totalrub, $order_info['currency_code'], $order_info['currency_value'], true);
			                  		}
			                	}
			                	else {
			                  		$instro_other = nl2br(htmlspecialchars_decode($instro));
			                	}
			                	$instroz .=  $instro_other;
			              	}
			          		$message = $instroz;
			          		$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_card_order_status_id'), $message, true);
			        	}
			        else{
			          $message = '';
			          $this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_card_order_status_id'), $message, true);
			        }
			    }
			    else{
			      	$this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('yandexplusplus_card_order_status_id'), false);
			      	
			    }

			}

		    if ($this->config->get('yandexplusplus_card_success_alert_admin')) {
		      
		        $subject = sprintf(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'), $order_info['order_id']);
		        
		        // Text 
		        $this->load->language('account/yandexplusplus_card');
		        $text = sprintf($this->language->get('success_admin_alert'), $order_info['order_id']) . "\n";
		        
		        
		      
		        $mail = new Mail(); 
		        $mail->protocol = $this->config->get('config_mail_protocol');
		        $mail->parameter = $this->config->get('config_mail_parameter');
		        $mail->hostname = $this->config->get('config_smtp_host');
		        $mail->username = $this->config->get('config_smtp_username');
		        $mail->password = $this->config->get('config_smtp_password');
		        $mail->port = $this->config->get('config_smtp_port');
		        $mail->timeout = $this->config->get('config_smtp_timeout');
		        $mail->setTo($this->config->get('config_email'));
		        $mail->setFrom($this->config->get('config_email'));
		        $mail->setSender($order_info['store_name']);
		        $mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
		        $mail->setText(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
		        $mail->send();
		        
		        // Send to additional alert emails
		        $emails = explode(',', $this->config->get('config_alert_emails'));
		        
		        foreach ($emails as $email) {
		          if ($email && preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $email)) {
		            $mail->setTo($email);
		            $mail->send();
		          }
		        }
		   	} 
		  }
		}
		  else{
		  	echo "bad sign\n";
		  	$this->log->write('YandexPlusPlus Error: Hash not equal');
		  exit();
		  } 
		}
		else{
		echo "Order sum false\n";
		$this->log->write('YandexPlusPlus Error: Amount of payment not equal');
	  exit();
	}
  }
  else{
  echo "No data";
  }
}
  public function status() {
  	if (isset($this->request->get['order_id'])){
   		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->request->get['order_id']);
  	}
  	else{
   		echo 'No order';
   		exit();
  	}

  	if ($this->request->get['order_id'] == $order_info['order_id']){
      $inv_id = $order_info['order_id'];
      $this->load->language('account/yandexplusplus');
      $data['heading_title'] = $this->language->get('heading_title');
      $this->document->setTitle($this->language->get('heading_title'));
      $data['button_ok'] = $this->language->get('button_ok');
      $data['inv_id'] = $order_info['order_id'];

      $action= $this->url->link('account/yandexplusplus');
				$online_url = $action .
				'&order_id=' . $order_info['order_id'];

      $data['success_text'] = '';

      
      if($order_info['payment_code'] == 'yandexplusplus'){
      	if ($this->config->get('yandexplusplus_fixen')) {
			if ($this->config->get('yandexplusplus_fixen') == 'fix'){
			    $out_summ = $this->config->get('yandexplusplus_fixen_amount');
			}
			else{
			    $out_summ = $order_info['total'] * $this->config->get('yandexplusplus_fixen_amount') / 100;
			}
		}
		else{
			$out_summ = $order_info['total'];
		}
      	if ($order_info['order_status_id'] == $this->config->get('yandexplusplus_order_status_id')) {

	      	if (isset($this->request->get['first'])) {
		        $data['success_text'] .=  $this->language->get('success_text_first');
		    }

      	  	if($this->config->get('yandexplusplus_createorder_or_notcreate')  && isset($this->request->get['first'])){
		        
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
		    }

	      if ($this->config->get('yandexplusplus_success_page_text_attach')) {

	      $instros = explode('$', ($this->config->get('yandexplusplus_success_page_text_' . $this->config->get('config_language_id'))));
	              $instroz = "";
	              foreach ($instros as $instro) {
	                if ($instro == 'orderid' ||  $instro == 'itogo' || $instro== 'href' || $instro == 'komis' || $instro == 'total-komis' || $instro == 'plus-komis'){
	                  if ($instro == 'orderid'){
	                    $instro_other = $inv_id;
	                  }
	                  if ($instro == 'itogo'){
	                      $instro_other = $this->currency->format($out_summ, $order_info['currency_code'], $order_info['currency_value'], true);
	                  }
	                  if ($instro == 'href'){
	                      $instro_other = $online_url;
	                  }
	                  if ($instro == 'komis'){
	                        if($this->config->get('yandexplusplus_komis')){
	                            $instro_other = $this->config->get('yandexplusplus_komis') . '%';
	                        }
	                        else{$instro_other = '';}
	                      }
	                      if ($instro == 'total-komis'){
	                        if($this->config->get('yandexplusplus_komis')){
	                            $instro_other = $this->currency->format($out_summ * $this->config->get('yandexplusplus_komis')/100, $order_info['currency_code'], $order_info['currency_value'], true);
	                        }
	                        else{$instro_other = '';}
	                      }
	                      if ($instro == 'plus-komis'){
	                        if($this->config->get('yandexplusplus_komis')){
	                            $instro_other = $this->currency->format($out_summ + ($out_summ * $this->config->get('yandexplusplus_komis')/100), $order_info['currency_code'], $order_info['currency_value'], true);
	                        }
	                        else{$instro_other = '';}
	                      }
	                }
	                else {
	                  $instro_other = nl2br(htmlspecialchars_decode($instro));
	                }
	                $instroz .=  $instro_other;
	              }

	      $data['success_text'] .= $instroz;
	      }
	      else{
	          $data['success_text'] .=  sprintf($this->language->get('success_text'), $inv_id);
	      }
	    }
	    else{

	      	if (isset($this->request->get['first']) && $order_info['order_status_id'] == $this->config->get('yandexplusplus_on_status_id')) {
		        $data['success_text'] .=  $this->language->get('success_text_first');
		    }

	    	if ($this->config->get('yandexplusplus_waiting_page_text_attach')) {

	      		$instros = explode('$', ($this->config->get('yandexplusplus_waiting_page_text_' . $this->config->get('config_language_id'))));
	              $instroz = "";
	              foreach ($instros as $instro) {
	                if ($instro == 'orderid' ||  $instro == 'itogo' || $instro== 'href' || $instro == 'komis' || $instro == 'total-komis' || $instro == 'plus-komis'){
	                  if ($instro == 'orderid'){
	                    $instro_other = $inv_id;
	                  }
	                  if ($instro == 'itogo'){
	                      $instro_other = $this->currency->format($out_summ, $order_info['currency_code'], $order_info['currency_value'], true);
	                  }
	                  if ($instro == 'href'){
	                      $instro_other = $online_url;
	                  }
	                  if ($instro == 'komis'){
	                        if($this->config->get('yandexplusplus_komis')){
	                            $instro_other = $this->config->get('yandexplusplus_komis') . '%';
	                        }
	                        else{$instro_other = '';}
	                      }
	                      if ($instro == 'total-komis'){
	                        if($this->config->get('yandexplusplus_komis')){
	                            $instro_other = $this->currency->format($out_summ * $this->config->get('yandexplusplus_komis')/100, $order_info['currency_code'], $order_info['currency_value'], true);
	                        }
	                        else{$instro_other = '';}
	                      }
	                      if ($instro == 'plus-komis'){
	                        if($this->config->get('yandexplusplus_komis')){
	                            $instro_other = $this->currency->format($out_summ + ($out_summ * $this->config->get('yandexplusplus_komis')/100), $order_info['currency_code'], $order_info['currency_value'], true);
	                        }
	                        else{$instro_other = '';}
	                      }
	                }
	                else {
	                  $instro_other = nl2br(htmlspecialchars_decode($instro));
	                }
	                $instroz .=  $instro_other;
	              }

	      $data['success_text'] .= $instroz;
	      }
	      else{
	      		if($order_info['order_status_id'] == $this->config->get('yandexplusplus_on_status_id')){
	         		$data['success_text'] .=  sprintf($this->language->get('success_text_wait'), $inv_id, $online_url);
	         	}
	         	else{
	          		$data['success_text'] .=  sprintf($this->language->get('success_text_wait_noorder'), $online_url);
	          	}
	      }
	    }
	  }
	  if($order_info['payment_code'] == 'yandexplusplus_card'){
	  	
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
	      if ($order_info['order_status_id'] == $this->config->get('yandexplusplus_card_order_status_id')) {

	      	if (isset($this->request->get['first'])) {
			    $data['success_text'] .=  $this->language->get('success_text_first');
			}

	      	if($this->config->get('yandexplusplus_card_createorder_or_notcreate')){
		        
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
		    		}

	      if ($this->config->get('yandexplusplus_card_success_page_text_attach')) {

	      $instros = explode('$', ($this->config->get('yandexplusplus_card_success_page_text_' . $this->config->get('config_language_id'))));
	              $instroz = "";
	              foreach ($instros as $instro) {
	                if ($instro == 'orderid' ||  $instro == 'itogo' || $instro== 'href' || $instro == 'komis' || $instro == 'total-komis' || $instro == 'plus-komis'){
	                  if ($instro == 'orderid'){
	                    $instro_other = $inv_id;
	                  }
	                  if ($instro == 'itogo'){
	                      $instro_other = $this->currency->format($out_summ, $order_info['currency_code'], $order_info['currency_value'], true);
	                  }
	                  if ($instro == 'href'){
	                      $instro_other = $online_url;
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

	      $data['success_text'] .= $instroz;
	      }
	      else{
	          $data['success_text'] .=  sprintf($this->language->get('success_text'), $inv_id);
	      }
	    }
	    else{

	    	if (isset($this->request->get['first']) && $order_info['order_status_id'] == $this->config->get('yandexplusplus_card_on_status_id')) {
				$data['success_text'] .=  $this->language->get('success_text_first');
			}
			
	    	if ($this->config->get('yandexplusplus_card_waiting_page_text_attach')) {

	      		$instros = explode('$', ($this->config->get('yandexplusplus_card_waiting_page_text_' . $this->config->get('config_language_id'))));
	              $instroz = "";
	              foreach ($instros as $instro) {
	                if ($instro == 'orderid' ||  $instro == 'itogo' || $instro== 'href' || $instro == 'komis' || $instro == 'total-komis' || $instro == 'plus-komis'){
	                  if ($instro == 'orderid'){
	                    $instro_other = $inv_id;
	                  }
	                  if ($instro == 'itogo'){
	                      $instro_other = $this->currency->format($out_summ, $order_info['currency_code'], $order_info['currency_value'], true);
	                  }
	                  if ($instro == 'href'){
	                      $instro_other = $online_url;
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

	      $data['success_text'] .= $instroz;
	      }
	      else{
	      	if($order_info['order_status_id'] == $this->config->get('yandexplusplus_card_on_status_id')){
	        	$data['success_text'] .=  sprintf($this->language->get('success_text_wait'), $inv_id, $online_url);
	      	}
	      	else{
	      		$data['success_text'] .=  sprintf($this->language->get('success_text_wait_noorder'), $online_url);
	      	}
	      }
	    }
	  }
	  
      if ($this->customer->isLogged()) {
        
        		if($order_info['payment_code'] == 'yandexplusplus'){
        			if(!$this->config->get('yandexplusplus_createorder_or_notcreate')){ 
        				$data['success_text'] .=  sprintf($this->language->get('success_text_loged'), $this->url->link('account/order', '', 'SSL'), $this->url->link('account/order/info&order_id=' . $inv_id, '', 'SSL'));
        			}
        			else{
        				if ($order_info['order_status_id'] == $this->config->get('yandexplusplus_order_status_id')) {
        					$data['success_text'] .=  sprintf($this->language->get('success_text_loged'), $this->url->link('account/order', '', 'SSL'), $this->url->link('account/order/info&order_id=' . $inv_id, '', 'SSL'));
        				}
        			}
	        		if ($order_info['order_status_id'] != $this->config->get('yandexplusplus_order_status_id')) {
	        			if($order_info['order_status_id'] == $this->config->get('yandexplusplus_on_status_id')){
	        				$data['success_text'] .=  sprintf($this->language->get('waiting_text_loged'), $this->url->link('account/order', '', 'SSL'));
	        			}
	        		}
	    		}
	    		if($order_info['payment_code'] == 'yandexplusplus_card'){
	    			if(!$this->config->get('yandexplusplus_card_createorder_or_notcreate')){ 
	    				$data['success_text'] .=  sprintf($this->language->get('success_text_loged'), $this->url->link('account/order', '', 'SSL'), $this->url->link('account/order/info&order_id=' . $inv_id, '', 'SSL'));
	    			}
	    			else{
        				if ($order_info['order_status_id'] == $this->config->get('yandexplusplus_card_order_status_id')) {
        					$data['success_text'] .=  sprintf($this->language->get('success_text_loged'), $this->url->link('account/order', '', 'SSL'), $this->url->link('account/order/info&order_id=' . $inv_id, '', 'SSL'));
        				}
        			}
	    			if ($order_info['order_status_id'] != $this->config->get('yandexplusplus_card_order_status_id')) {
	    				if($order_info['order_status_id'] == $this->config->get('yandexplusplus_card_on_status_id')){
	        				$data['success_text'] .=  sprintf($this->language->get('waiting_text_loged'), $this->url->link('account/order', '', 'SSL'));
	        			}
	        		}
	    		}
      }
      
      $data['breadcrumbs'] = array();

      $data['breadcrumbs'][] = array(
        'text'      => $this->language->get('text_home'),
        'href'      => $this->url->link('common/home')
      );
      
      if (isset($this->request->get['first'])) {
        $this->language->load('checkout/success');
        $data['breadcrumbs'][] = array(
          'href'      => $this->url->link('checkout/cart'),
          'text'      => $this->language->get('text_basket')
        );
        
        $data['breadcrumbs'][] = array(
          'href'      => $this->url->link('checkout/checkout', '', 'SSL'),
          'text'      => $this->language->get('text_checkout')
        );
        $data['button_ok_url'] = $this->url->link('common/home');
      }
      else{
        if ($this->customer->isLogged()) {
          $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('lich'),
            'href'      => $this->url->link('account/account', '', 'SSL')
          );

          $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('history'),
            'href'      => $this->url->link('account/order', '', 'SSL')
          );
          $data['button_ok_url'] = $this->url->link('account/order', '', 'SSL');
        }
        else{
          $data['button_ok_url'] = $this->url->link('common/home');
        }
      }

      $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');


        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/account/yandexplusplus_status.tpl')) {
			$this->response->setOutput($this->load->view($this->config->get('config_template') . '/template/account/yandexplusplus_status.tpl', $data));
		} else {
			$this->response->setOutput($this->load->view('default/template/account/yandexplusplus_status.tpl', $data));
		}
    }
    else{
      echo "No data";
    }
  }
}
?>