<?php
################################################################################################
# Webservices Tab Applicants Opencart 3.x.x.x From Webkul  http://webkul.com 	#
################################################################################################
class ControllerCatalogWkWebservicesTab extends Controller {

	private $error = array();
	private $data = array();

	public function index() {

		$this->language->load('catalog/wk_webservices_tab');
		$this->load->model('catalog/wk_webservices_tab');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->getlist();
  	}


	protected function getList() {
		$this->language->load('catalog/wk_webservices_tab');

		if(isset($this->request->get['filter_user'])){
			$filter_user = $this->request->get['filter_user'];
		}else{
			$filter_user = null;
		}

		if(isset($this->request->get['filter_email'])){
			$filter_email = $this->request->get['filter_email'];
		}else{
			$filter_email = null;
		}

		if(isset($this->request->get['filter_name'])){
			$filter_name = $this->request->get['filter_name'];
		}else{
			$filter_name = null;
		}

		if(isset($this->request->get['filter_status'])){
			$filter_status = $this->request->get['filter_status'];
		}else{
			$filter_status = null;
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'user';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if(isset($this->request->get['filter_user'])){
			$url .= '&filter_user=' . urlencode(html_entity_decode($this->request->get['filter_user'], ENT_QUOTES, 'UTF-8'));
		}

		if(isset($this->request->get['filter_email'])){
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if(isset($this->request->get['filter_name'])){
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] , true),
      		'separator' => ' :: '
   		);

		$this->data['action'] = $this->url->link('catalog/wk_webservices_tab/delete', 'user_token=' . $this->session->data['user_token'] , true);
    	$this->data['insert'] = $this->url->link('catalog/wk_webservices_tab/insert', 'user_token=' . $this->session->data['user_token'] , true);

		$this->data['keys'] = array();

		$data = array(
			'filter_user' => $filter_user,
			'filter_email' => $filter_email,
			'filter_name' => $filter_name,
			'filter_status' => $filter_status,
			'sort' => $sort,
			'order' => $order,
			'page' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin'),
			);

		$product_total = $this->model_catalog_wk_webservices_tab->getTotalKeys($data);

		$results = $this->model_catalog_wk_webservices_tab->getKeys($data);

		$this->data['heading_title'] = $this->language->get('heading_title');
		$this->data['entry_name'] = $this->language->get('entry_name');
		$this->data['button_insert'] = $this->language->get('button_insert');
		$this->data['button_delete'] = $this->language->get('button_delete');
		$this->data['wk_key_description'] = $this->language->get('wk_key_description');
		$this->data['wk_status'] = $this->language->get('wk_status');
		$this->data['wk_user'] = $this->language->get('wk_user');
		$this->data['entry_name'] = $this->language->get('entry_name');
		$this->data['wk_email'] = $this->language->get('wk_email');
		$this->data['entry_enabled'] = $this->language->get('entry_enabled');
		$this->data['entry_disabled'] = $this->language->get('entry_disabled');
		$this->data['entry_action'] = $this->language->get('entry_action');
		$this->data['entry_no_result'] = $this->language->get('entry_no_result');

	    foreach ($results as $result) {
			$action = array();

			$action[] = array(
				'text' => $this->language->get('text_edit'),
				'href' => $this->url->link('catalog/wk_webservices_tab/update&id='.$result['id'], 'user_token=' . $this->session->data['user_token'] , true)
				);

      		$this->data['keys'][] = array(
							'selected'=>False,
							'id' => $result['id'],
							'user' => $result['user'],
							'name' => $result['firstname'].' '.$result['lastname'],
							'email' => $result['email'],
							'key_description' => $result['key_description'],
							'status' => $result['status'],
							'action'     => $action
							);


		}

 		$this->data['user_token'] = $this->session->data['user_token'];


		if (isset($this->session->data['error_warning'])) {
			$this->error['warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		}

		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$this->data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$this->data['success'] = '';
		}

		$url = '';

		if(isset($this->request->get['filter_user'])){
			$url .= '&filter_user=' . urlencode(html_entity_decode($this->request->get['filter_user'], ENT_QUOTES, 'UTF-8'));
		}

		if(isset($this->request->get['filter_email'])){
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if(isset($this->request->get['filter_name'])){
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}


		if($order == 'ASC'){
			$url .= '&order=DESC';
		} else{
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$this->data['sort_user'] = $this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] . '&sort=user' . $url, true);
		$this->data['sort_email'] = $this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] . '&sort=email' . $url, true);
		$this->data['sort_name'] = $this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url, true);
		$this->data['sort_status'] = $this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] . '&sort=status' . $url, true);

		$url = '';

		if(isset($this->request->get['filter_user'])){
			$url .= '&filter_user=' . urlencode(html_entity_decode($this->request->get['filter_user'], ENT_QUOTES, 'UTF-8'));
		}

		if(isset($this->request->get['filter_email'])){
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if(isset($this->request->get['filter_name'])){
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $product_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_admin_limit');
		$pagination->text = $this->language->get('text_pagination');
		$pagination->url = $this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$this->data['pagination'] = $pagination->render();

		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

		$this->data['filter_user'] = $filter_user;
		$this->data['filter_email'] = $filter_email;
		$this->data['filter_name'] = $filter_name;
		$this->data['filter_status'] = $filter_status;
		$this->data['sort'] = $sort;
		$this->data['order'] = $order;

		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['header'] = $this->load->controller('common/header');
		$this->data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wk_webservices_tab', $this->data));
  	}

  	public function insert() {
    	$this->language->load('catalog/wk_webservices_tab');

    	$this->document->setTitle($this->language->get('heading_title_insert'));

		$this->load->model('catalog/wk_webservices_tab');

    	if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

    		if ((utf8_strlen($this->request->post['wk_user_val']) < 6) || (utf8_strlen($this->request->post['wk_user_val']) > 50)) {
				$error = $this->language->get('error_user');
			}

			if ((utf8_strlen($this->request->post['wk_email_val']) > 96) || !preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $this->request->post['wk_email_val'])) {
				$error = $this->language->get('error_email');
			}

			if ((utf8_strlen($this->request->post['wk_fname_val']) < 1) || (utf8_strlen($this->request->post['wk_fname_val']) > 100)) {
				$error = $this->language->get('error_fname');
			}

			if ((utf8_strlen($this->request->post['wk_lname_val']) < 1) || (utf8_strlen($this->request->post['wk_lname_val']) > 100)) {
				$error = $this->language->get('error_lname');
			}

			if ((utf8_strlen($this->request->post['wk_key_val']) < 7) || (utf8_strlen($this->request->post['wk_key_val']) > 100)) {
				$error = $this->language->get('error_key');
			}

			if ( $this->request->post['wk_key_val'] != $this->request->post['wk_cnfrmKey_val'] ) {
				$error = $this->language->get('error_keyMatch');
			}


			$this->load->model('catalog/wk_webservices_tab');
			$userchk = $this->model_catalog_wk_webservices_tab->userChk($this->request->post['wk_user_val']);

			if (isset($userchk['id'])){
				$error = $this->language->get('error_user_exists');
			}

			if (!isset($error)) {
				$this->model_catalog_wk_webservices_tab->addKeys($this->request->post);

				$this->session->data['success'] = $this->language->get('text_success_insert');

				$this->response->redirect($this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] , true));

			}

			$this->session->data['error_warning'] = $error ;

			if (isset($this->session->data['error_warning'])) {
			$this->error['warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
			}

			if(isset($this->session->data['error'])){
				$this->data['warning']	=	$this->session->data['error'];
				unset($this->session->data['error']);
			}

	 		if (isset($this->error['warning'])) {
				$this->data['error_warning'] = $this->error['warning'];
			} else {
				$this->data['error_warning'] = '';
			}

			if(isset($this->session->data['success'])){
				$this->data['success']	=	$this->session->data['success'];
				unset($this->session->data['success']);
			} else{
				$this->data['success'] = '';
			}

			//$this->redirect($this->url->link('catalog/wk_webservices_tab/insert', 'user_token=' . $this->session->data['user_token'] , true));


    	}

    	$this->getForm();
  	}

  	public function update() {
    	$this->language->load('catalog/wk_webservices_tab');

    	$this->document->setTitle($this->language->get('heading_title_update'));

		$this->load->model('catalog/wk_webservices_tab');

    	if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

    		if ((utf8_strlen($this->request->post['wk_user_val']) < 6) || (utf8_strlen($this->request->post['wk_user_val']) > 50)) {
				$error = $this->language->get('error_user');
			}

			if ((utf8_strlen($this->request->post['wk_email_val']) > 96) || !preg_match('/^[^\@]+@.*\.[a-z]{2,6}$/i', $this->request->post['wk_email_val'])) {
				$error = $this->language->get('error_email');
			}

			if ((utf8_strlen($this->request->post['wk_fname_val']) < 1) || (utf8_strlen($this->request->post['wk_fname_val']) > 100)) {
				$error = $this->language->get('error_fname');
			}

			if ((utf8_strlen($this->request->post['wk_lname_val']) < 1) || (utf8_strlen($this->request->post['wk_lname_val']) > 100)) {
				$error = $this->language->get('error_lname');
			}

			$add = '';
			if(!empty($this->request->post['wk_key_val'])){
				if ((utf8_strlen($this->request->post['wk_key_val']) < 7) || (utf8_strlen($this->request->post['wk_key_val']) > 100)) {
					$error = $this->language->get('error_key');
				}

				if ( $this->request->post['wk_key_val'] != $this->request->post['wk_cnfrmKey_val'] ) {
					$error = $this->language->get('error_keyMatch');
				}

				$add = ", user_key ='".md5($this->request->post['wk_key_val'])."'";
			}

			$this->load->model('catalog/wk_webservices_tab');

			$userchk = $this->model_catalog_wk_webservices_tab->userChk($this->request->post['wk_user_val'],$this->request->post['wk_key_id']);

			if (isset($userchk['id'])){
				$error = $this->language->get('error_user_exists');
			}

			if (!isset($error)) {

				$this->model_catalog_wk_webservices_tab->updateKeys($this->request->post,$add);

				$this->session->data['success'] = $this->language->get('text_success_update');

				$this->response->redirect($this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] , true));

			}

			$this->session->data['error_warning'] = $error ;

			//$this->redirect($this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] , true));

    	}

    	$this->getForm();
  	}

  	protected function getForm() {

		$this->language->load('catalog/wk_webservices_tab');

		$this->data['heading_title'] = $this->language->get('heading_title_insert');
		$this->data['entry_name'] = $this->language->get('entry_name');
		$this->data['button_save'] = $this->language->get('button_save');
        $this->data['button_cancel'] = $this->language->get('button_cancel');
        $this->data['wk_key'] = $this->language->get('wk_key');
		$this->data['wk_key_description'] = $this->language->get('wk_key_description');
		$this->data['wk_status'] = $this->language->get('wk_status');
		$this->data['wk_user'] = $this->language->get('wk_user');
		$this->data['wk_key_size'] = $this->language->get('wk_key_size');
		$this->data['wk_star'] = $this->language->get('wk_star');
		$this->data['wk_fname'] = $this->language->get('wk_fname');
		$this->data['wk_lname'] = $this->language->get('wk_lname');
		$this->data['wk_email'] = $this->language->get('wk_email');
		$this->data['success'] = $this->language->get('success');
		$this->data['error_warning'] = $this->language->get('error_warning');
		$this->data['wk_cnfrmkey'] = $this->language->get('wk_cnfrmkey');

	    $config_data = array(
				'wk_key_id',
				'wk_user_val',
				'wk_fname_val',
				'wk_lname_val',
				'wk_email_val',
				'wk_key_val',
				'wk_cnfrmKey_val',
				'wk_key_description_val',
				'wk_status_val',
		);


		foreach ($config_data as $conf) {
			if (isset($this->request->post[$conf])) {
				$this->data[$conf] = $this->request->post[$conf];
			}
		}

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], true),
			'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] , true),
      		'separator' => ' :: '
   		);

		$this->data['cancel'] = $this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'], true);

		$this->data['action'] = $this->url->link('catalog/wk_webservices_tab/insert', 'user_token=' . $this->session->data['user_token'],true);

		if(isset($this->request->get['id'])){

			$oid = $this->request->get['id'];
			$results = $this->model_catalog_wk_webservices_tab->viewtotalBy($oid);

			if(isset($results['user'])){

				$this->data['wk_key_id'] = $results['id'];
				$this->data['wk_user_val'] = $results['user'];
				$this->data['wk_fname_val'] = $results['firstname'];
				$this->data['wk_lname_val'] = $results['lastname'];
				$this->data['wk_email_val'] = $results['email'];
				$this->data['wk_key_description_val'] = $results['key_description'];
				$this->data['wk_status_val'] = $results['status'];
			}
			$this->data['required'] = '?';
			$this->data['save'] = $this->url->link('catalog/wk_webservices_tab/update', 'user_token=' . $this->session->data['user_token'], true);
			$this->data['heading_title'] = $this->language->get('heading_title_update').' : '.$results['user'];
			$this->data['action'] = $this->url->link('catalog/wk_webservices_tab/update', 'user_token=' . $this->session->data['user_token'] ,true);

		}
		if(!empty($this->data['wk_key_id'])){
			$this->data['action'] = $this->url->link('catalog/wk_webservices_tab/update', 'user_token=' . $this->session->data['user_token'] ,true);
		}

		$this->data['user_token'] = $this->session->data['user_token'];

		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$this->data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$this->data['success'] = '';
		}


		$this->load->model('design/layout');

		$this->data['layouts'] = $this->model_design_layout->getLayouts();

		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['header'] = $this->load->controller('common/header');
		$this->data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wk_webservices_tab_form', $this->data));
  	}

  	public function delete() {
    	$this->language->load('catalog/wk_webservices_tab');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/wk_webservices_tab');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {

			foreach ($this->request->post['selected'] as $id) {
				$this->model_catalog_wk_webservices_tab->deleteKeys($id);
	  		}

			$this->session->data['success'] = $this->language->get('text_success');

			$url='';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('catalog/wk_webservices_tab', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

    	$this->getList();
  	}

	private function validateForm() {
		$this->language->load('catalog/wk_webservices_tab');

		if (!$this->user->hasPermission('modify', 'catalog/wk_webservices_tab')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		if (!$this->error) {
				return true;
		} else {
			return false;
		}
	}

	protected function validateDelete() {
    	if (!$this->user->hasPermission('modify', 'catalog/wk_webservices_tab')) {
      		$this->error['warning'] = $this->language->get('error_permission');
    	}

		if (!$this->error) {
	  		return true;
		} else {
	  		return false;
		}
  	}

}
?>
