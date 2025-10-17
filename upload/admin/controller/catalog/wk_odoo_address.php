<?php
################################################################################################
# Webservices Address xmlrpc Opencart 3.x.x.x From Webkul  http://webkul.com 	#
################################################################################################
class ControllerCatalogwkodooAddress extends Controller {

	private $error = array();
	private $data = array();

	public function index() {

		$this->language->load('catalog/wk_odoo_address');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/wkodoo');

		$this->getlist();
  	}

	protected function getList() {

		$this->language->load('catalog/wk_odoo_address');

		$this->data['heading_title'] = $this->language->get('heading_title');

		$this->data['button_insert'] = $this->language->get('button_insert');
		$this->data['button_delete'] = $this->language->get('button_delete');
		$this->data['button_sync'] = $this->language->get('button_sync');
		$this->data['button_filter'] = $this->language->get('button_filter');

		$this->data['wkid'] = $this->language->get('wkid');
		$this->data['wk_erpid'] = $this->language->get('wk_erpid');
		$this->data['wk_opencartid'] = $this->language->get('wk_opencartid');
		$this->data['wk_createdby'] = $this->language->get('wk_createdby');
		$this->data['wk_createdon'] = $this->language->get('wk_createdon');
		$this->data['wk_cid'] = $this->language->get('wk_cid');
		$this->data['wk_issync'] = $this->language->get('wk_issync');
		$this->data['text_syncnreq'] = $this->language->get('text_syncnreq');
		$this->data['text_syncreq'] = $this->language->get('text_syncreq');
		$this->data['text_confirm'] = $this->language->get('text_confirm');
		$this->data['sync_confirm'] = $this->language->get('sync_confirm');

		if(isset($this->request->get['page'])){
			$page = $this->request->get['page'];
		}else{
			$page = 1;
		}

		if (isset($this->request->get['filter_id'])) {
			$filter_id = $this->request->get['filter_id'];
		} else {
			$filter_id = null;
		}

		if (isset($this->request->get['filter_erpid'])) {
			$filter_erpid = $this->request->get['filter_erpid'];
		} else {
			$filter_erpid = null;
		}

		if (isset($this->request->get['filter_opid'])) {
			$filter_opid = $this->request->get['filter_opid'];
		} else {
			$filter_opid = null;
		}

		if (isset($this->request->get['filter_cid'])) {
			$filter_cid = $this->request->get['filter_cid'];
		} else {
			$filter_cid = null;
		}

		if (isset($this->request->get['filter_by'])) {
			$filter_by = $this->request->get['filter_by'];
		} else {
			$filter_by = null;
		}

		if (isset($this->request->get['filter_date'])) {
			$filter_date = $this->request->get['filter_date'];
		} else {
			$filter_date = null;
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = null;
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'id';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'DESC';
		}

		$url = '';

		if (isset($this->request->get['filter_id'])) {
			$url .= '&filter_id=' . $this->request->get['filter_id'];
		}

		if (isset($this->request->get['filter_erpid'])) {
			$url .= '&filter_erpid=' . $this->request->get['filter_erpid'];
		}

		if (isset($this->request->get['filter_opid'])) {
			$url .= '&filter_opid=' . $this->request->get['filter_opid'];
		}

		if (isset($this->request->get['filter_cid'])) {
			$url .= '&filter_cid=' . $this->request->get['filter_cid'];
		}

		if (isset($this->request->get['filter_by'])) {
			$url .= '&filter_by=' . urlencode(html_entity_decode($this->request->get['filter_by'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_date'])) {
			$url .= '&filter_date=' . urlencode(html_entity_decode($this->request->get['filter_date'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		$data = array(
			'filter_id'                => $filter_id,
			'filter_erpid'             => $filter_erpid,
			'filter_opid'              => $filter_opid,
			'filter_cid'               => $filter_cid,
			'filter_date'              => $filter_date,
			'filter_by'                => $filter_by,
			'filter_status'            => $filter_status,
			'sort'                     => $sort,
			'order'                    => $order,
			'start'                    => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'                    => $this->config->get('config_limit_admin')
		);

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'].$url, true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title_bread'),
			'href'      => $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);


		$this->data['action'] = $this->url->link('catalog/wk_odoo_address/sync', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['insert'] = $this->url->link('catalog/wk_odoo_address/insert', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['delete'] = $this->url->link('catalog/wk_odoo_address/delete', 'user_token=' . $this->session->data['user_token'].$url , true);

		$this->data['currency'] = array();

		$product_total = $this->model_catalog_wkodoo->getAddressTotal($data);

		$results = $this->model_catalog_wkodoo->getAddress($data);

	    foreach ($results as $result) {

      		$this->data['currency'][] = array(
							'selected'=>False,
							'id' => $result['id'],
							'erpid' => $result['erp_address_id'],
							'cartid' => $result['opencart_address_id'],
							'cid' => $result['customer_id'],
							'createddate' => $result['created_on'],
							'createdby' => $result['created_by'],
							'sync' => $result['is_synch'],

							);

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

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$this->data['sort_id'] = $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token']. '&sort=id'.$url , true);
		$this->data['sort_erpid'] = $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token']. '&sort=erp_address_id'.$url , true);
		$this->data['sort_opid'] = $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token']. '&sort=opencart_address_id'.$url , true);
		$this->data['sort_cid'] = $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token']. '&sort=customer_id'.$url , true);
		$this->data['sort_date'] = $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token']. '&sort=created_on'.$url , true);
		$this->data['sort_by'] = $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token']. '&sort=created_by'.$url , true);
		$this->data['sort_status'] = $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token'] . '&sort=is_synch' . $url, true);

		$url = '';

		if (isset($this->request->get['filter_id'])) {
			$url .= '&filter_id=' . $this->request->get['filter_id'];
		}

		if (isset($this->request->get['filter_erpid'])) {
			$url .= '&filter_erpid=' . $this->request->get['filter_erpid'];
		}

		if (isset($this->request->get['filter_opid'])) {
			$url .= '&filter_opid=' . $this->request->get['filter_opid'];
		}

		if (isset($this->request->get['filter_cid'])) {
			$url .= '&filter_cid=' . $this->request->get['filter_cid'];
		}

		if (isset($this->request->get['filter_by'])) {
			$url .= '&filter_by=' . urlencode(html_entity_decode($this->request->get['filter_by'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_date'])) {
			$url .= '&filter_date=' . urlencode(html_entity_decode($this->request->get['filter_date'], ENT_QUOTES, 'UTF-8'));
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
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->text = $this->language->get('text_pagination');
		$pagination->url = $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token']  . $url . '&page={page}', true);

		$this->data['pagination'] = $pagination->render();
		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));


		$this->data['sort'] = $sort;
		$this->data['order'] = $order;
		$this->data['filter_id'] = $filter_id;
		$this->data['filter_erpid'] = $filter_erpid;
		$this->data['filter_opid'] = $filter_opid;
		$this->data['filter_cid'] = $filter_cid;
		$this->data['filter_date'] = $filter_date;
		$this->data['filter_by'] = $filter_by;
		$this->data['filter_status'] = $filter_status;

		$this->data['header'] = $this->load->controller('common/header');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wk_odoo_address', $this->data));
  	}



  	protected function getForm() {

  		$this->load->model('catalog/wkodoo');

		$this->language->load('catalog/wk_odoo_address');

		$this->data['heading_title'] = $this->language->get('heading_title_insert');
		$this->data['button_save'] = $this->language->get('button_save');
		$this->data['button_cancel'] = $this->language->get('button_cancel');
		$this->data['wk_opCustomer'] = $this->language->get('wk_opCustomer');
		$this->data['wk_opCustomerAddress'] = $this->language->get('wk_opCustomerAddress');
		$this->data['wk_erpCustomerAddress'] = $this->language->get('wk_erpCustomerAddress');

		$url = '';

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'].$url, true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title_bread'),
			'href'      => $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);
   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title_insert'),
			'href'      => $this->url->link('catalog/wk_odoo_address/insert', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);

		$this->data['action'] = $this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['insert'] = $this->url->link('catalog/wk_odoo_address/insert', 'user_token=' . $this->session->data['user_token'].$url , true);

		$this->data['opCustomer'] = $this->model_catalog_wkodoo->getOpCustomers();

 		$this->data['user_token'] = $this->session->data['user_token'];

 		if (isset($this->request->post['opCustomerId'])) {
			$this->data['opCustomerId'] = $this->request->post['opCustomerId'];
		}else{
			$this->data['opCustomerId'] = '';
		}

		if (isset($this->session->data['error_warning'])) {
			$this->error['warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		}



 		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		if (isset($this->error['error_choose_ocaddress'])) {
			$this->data['error_choose_ocaddress'] = $this->error['error_choose_ocaddress'];
		} else {
			$this->data['error_choose_ocaddress'] = '';
		}

		if (isset($this->error['error_choose_erpaddress'])) {
			$this->data['error_choose_erpaddress'] = $this->error['error_choose_erpaddress'];
		} else {
			$this->data['error_choose_erpaddress'] = '';
		}

		if (isset($this->session->data['success'])) {
			$this->data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$this->data['success'] = '';
		}

		$this->data['header'] = $this->load->controller('common/header');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wk_odoo_address_manual', $this->data));

  	}

  	public function insert() {

    	$this->language->load('catalog/wk_odoo_address');

    	$this->document->setTitle($this->language->get('heading_title_insert'));

		$this->load->model('catalog/erp_address');

    	if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

    		if ((utf8_strlen($this->request->post['opCustomerId']) < 1)) {
				$error = $this->language->get('error_opcust');
			}

			if (!isset($error)) {
				$data = $this->request->post;

				$this->load->model('catalog/erp_customer');
	  			$status = $this->model_catalog_erp_customer->search_address($data['opCustomerAddressId']);
		  		if($status[0] == 0){
		  			$this->model_catalog_erp_customer->addto_address_merge($data['opCustomerAddressId'], $data['erpCustomerAddressId'], $data['opCustomerId'], 'Manual Mappping');

		  			$this->mergeAtOdoo($data['erpCustomerAddressId'], $data['opCustomerId'], $data['opCustomerAddressId']);

					$this->session->data['success'] = $this->language->get('text_success_insert');
					$this->response->redirect($this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token'] , true));
		  		}
				else{
					$error = $this->language->get('error_mapped');
				}
			}

			$this->session->data['error_warning'] = $error ;
    	}

    	$this->getForm();
  	}

  	public function mergeAtOdoo($erpAddress_id, $opCustomer_id, $opAddress_id){
  		$this->load->model('catalog/connection');

		$data = $this->model_catalog_connection->make();

		if(isset($data['status']) AND $data['status']){
			$userId = $data['userId'];
			$client = $data['client'];
			$db= $data['db'];
			$pwd= $data['pwd'];
			$context = array(
            'opencart' => new xmlrpcval('opencart', "string")
	        );
	        $erp_add_id = array(
	            new xmlrpcval($erpAddress_id, 'int')
	        );
	        $arrayVal   = array(
	        	'opencart_address_id' => new xmlrpcval($opAddress_id, "int"),
	            'opencart_customer_id' => new xmlrpcval($opCustomer_id, "int"),
	            'opencart_type' => new xmlrpcval('address', "string"),
	        );
	        $msg_ser    = new xmlrpcmsg('execute');
	        $msg_ser->addParam(new xmlrpcval($db, "string"));
	        $msg_ser->addParam(new xmlrpcval($userId, "int"));
	        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
	        $msg_ser->addParam(new xmlrpcval("res.partner", "string"));
	        $msg_ser->addParam(new xmlrpcval("write", "string"));
	        $msg_ser->addParam(new xmlrpcval($erp_add_id, "array"));
	        $msg_ser->addParam(new xmlrpcval($arrayVal, "struct"));
	        $msg_ser->addParam(new xmlrpcval($context, "struct"));
	        $resp = $client->send($msg_ser);
		}
  	}

  	public function getaddress(){
  		$json = array();

  		if(isset($this->request->post['customer_id'])){

	  		$customerId = $this->request->post['customer_id'];

	  		$this->load->model('catalog/erp_customer');
	  		$erpCustomerId = $this->model_catalog_erp_customer->search_customer($customerId);

			$this->load->model('catalog/connection');

			$data = $this->model_catalog_connection->make();
			$erpAddress = array();
			if(isset($data['status']) AND $data['status']){
				$userId = $data['userId'];
				$client = $data['client'];
				$db= $data['db'];
				$pwd= $data['pwd'];
				$this->load->model('catalog/erp_address');
				$erpAddress = $this->model_catalog_erp_address->getErpAddressArray($erpCustomerId[0], $userId, $client, $db, $pwd);
			}
			foreach ($erpAddress as $key => $add) {
				$json['erpAddress'][] = array(
					'erpAddressId'	=>	$add['id'],
					'erpAddressName'	=>	$add['name'],
					);
			}

	  		$this->load->model('catalog/erp_address');
	  		$result = $this->model_catalog_erp_address->getCustomerAddress($customerId);
	  		foreach ($result as $key => $address) {

	  			$json['opAddress'][]	=	array(
	  				'opaddressId'	=> $address['address_id'],
	  				'opcustomerId'	=> $address['customer_id'],
	  				'opaddressFirst'	=> $address['address_1'].','.$address['city'],
	  				);
	  		}

  		}
  		$this->response->setOutput(json_encode($json));

  	}

  	public function sync() {
    	$this->language->load('catalog/wk_odoo_address');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/erp_product_category');

		if (($this->request->server['REQUEST_METHOD'] == 'POST')) {

			$this->load->model('catalog/connection');

			$data = $this->model_catalog_connection->make();

			if(isset($data['status']) AND $data['status']){
				$userId = $data['userId'];
				$client = $data['client'];
				// $sock= $data['sock'];
				$db= $data['db'];
				$pwd= $data['pwd'];
				$cart_user= 'Testing';
			}

			$this->model_catalog_erp_product_category->check_all_categories($userId,$client,$db,$pwd,$cart_user);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

    	$this->getList();
  	}

  	public function delete() {

    	$this->language->load('catalog/wk_odoo_address');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/wkodoo');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {

			foreach ($this->request->post['selected'] as $id) {
				$this->model_catalog_wkodoo->deleteAddress($id);
	  		}

			$this->session->data['success'] = $this->language->get('text_success');

			$url='';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('catalog/wk_odoo_address', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

    	$this->getList();
  	}

	protected function validateDelete() {
    	if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_address')) {
      		$this->error['warning'] = $this->language->get('error_permission');
    	}

		if (!$this->error) {
	  		return true;
		} else {
	  		return false;
		}
  	}

  	public function validateForm(){
  		$this->language->load('catalog/wk_odoo_address');

		if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_address')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if(!isset($this->request->post['opCustomerAddressId'])){
				$this->error['error_choose_ocaddress'] = $this->language->get('error_choose_ocaddress');
		}

		if(!isset($this->request->post['erpCustomerAddressId'])){
				$this->error['error_choose_erpaddress'] = $this->language->get('error_choose_erpaddress');
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

}
?>
