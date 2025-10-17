<?php
################################################################################################
# Webservices Product xmlrpc Opencart 3.x.x.x. From Webkul  http://webkul.com 	#
################################################################################################
class ControllerCatalogwkodooProductOptionValue extends Controller {

	private $error = array();
	private $data = array();

	public function index() {

		$this->language->load('catalog/wk_odoo_product_option_value');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/wkodoo');

		$this->getlist();
  	}

	protected function getList() {

		$this->load->model('catalog/wkodoo');

		$this->language->load('catalog/wk_odoo_product_option_value');

		$this->data['heading_title'] = $this->language->get('heading_title');

		$this->data['button_insert'] = $this->language->get('button_insert');
		$this->data['button_delete'] = $this->language->get('button_delete');
		$this->data['button_sync'] = $this->language->get('button_sync');
		$this->data['button_filter'] = $this->language->get('button_filter');

		$this->data['wkid'] = $this->language->get('wkid');
		$this->data['wk_erpid'] = $this->language->get('wk_erpid');
		$this->data['wk_opencartid'] = $this->language->get('wk_opencartid');
		$this->data['wk_opencartname'] = $this->language->get('wk_opencartname');
		$this->data['wk_option_id'] = $this->language->get('wk_option_id');
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

		if (isset($this->request->get['filter_opname'])) {
			$filter_opname = $this->request->get['filter_opname'];
		} else {
			$filter_opname = null;
		}

		if (isset($this->request->get['filter_oid'])) {
			$filter_oid = $this->request->get['filter_oid'];
		} else {
			$filter_oid = null;
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'id';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
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

		if (isset($this->request->get['filter_opname'])) {
			$url .= '&filter_opname=' . urlencode(html_entity_decode($this->request->get['filter_opname'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_oid'])) {
			$url .= '&filter_oid=' . $this->request->get['filter_oid'];
		}

		$data = array(
			'filter_id'                => $filter_id,
			'filter_erpid'             => $filter_erpid,
			'filter_opid'              => $filter_opid,
			'filter_opname'            => $filter_opname,
			'filter_oid'               => $filter_oid,
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
			'href'      => $this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);


		$this->data['action'] = $this->url->link('catalog/wk_odoo_product_option_value/sync', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['delete'] = $this->url->link('catalog/wk_odoo_product_option_value/delete', 'user_token=' . $this->session->data['user_token'].$url , true);
		$this->data['insert'] = $this->url->link('catalog/wk_odoo_product_option_value/insert', 'user_token=' . $this->session->data['user_token'].$url , true);

		$this->data['product_option_value'] = array();

		$product_total = $this->model_catalog_wkodoo->getProductOptionValueTotal($data);

		$results = $this->model_catalog_wkodoo->getProductOptionValue($data);

	    foreach ($results as $result) {

      		$this->data['product_option_value'][] = array(
							'selected'=>False,
							'id' => $result['id'],
							'erpid' => $result['erp_option_value_id'],
							'cartid' => $result['opencart_option_value_id'],
							'name' => $result['name'],
							'option_id' => $result['option_id'],
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

		$this->data['sort_id'] = $this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token']. '&sort=id'.$url , true);
		$this->data['sort_erpid'] = $this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token']. '&sort=erp_option_value_id'.$url , true);
		$this->data['sort_opid'] = $this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token']. '&sort=opencart_option_value_id'.$url , true);
		$this->data['sort_opname'] = $this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token']. '&sort=name'.$url , true);
		$this->data['sort_oid'] = $this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token']. '&sort=option_id'.$url , true);
		$this->data['sort_status'] = $this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token'] . '&sort=is_synch' . $url, true);
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

		if (isset($this->request->get['filter_opname'])) {
			$url .= '&filter_opname=' . urlencode(html_entity_decode($this->request->get['filter_opname'], ENT_QUOTES, 'UTF-8'));
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
		$pagination->url = $this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token']  . $url . '&page={page}', true);

		$this->data['pagination'] = $pagination->render();
		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

		$this->data['sort'] = $sort;
		$this->data['order'] = $order;
		$this->data['filter_id'] = $filter_id;
		$this->data['filter_erpid'] = $filter_erpid;
		$this->data['filter_opid'] = $filter_opid;
		$this->data['filter_opname'] = $filter_opname;
		$this->data['filter_oid'] = $filter_oid;


		$this->data['header'] = $this->load->controller('common/header');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wk_odoo_product_option_value', $this->data));
  	}

  	public function sync() {

  		$this->language->load('catalog/wk_odoo_product_option');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/erp_product_options');

		$this->load->model('catalog/connection');

		$data = $this->model_catalog_connection->make();

		if(isset($data['status']) AND $data['status']){
			$userId = $data['userId'];
			$client = $data['client'];
			$pwd = $data['pwd'];
			$cart_user = $this->user->getUserName() ? $this->user->getUserName() : $this->config->get('config_name');
			$language_id = $this->config->get("config_language_id");

			$context = array(
				'db'=>$data['db'],
				'pwd'=>$data['pwd'],
				'cart_user'=>$cart_user,
				'id_lang'=>$language_id
			);



			$this->model_catalog_erp_product_options->check_all_option_values($userId, $client, $context);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}else{
			$this->error['warning'] = 'Warning No Connection Available !! ';
		}

    	$this->getList();
  	}
	protected function getForm() {

  		$this->load->model('catalog/wkodoo');

		$this->language->load('catalog/wk_odoo_product_option_value');

		$this->data['heading_title'] = $this->language->get('heading_title_insert');
		$this->data['button_save'] = $this->language->get('button_save');
		$this->data['button_cancel'] = $this->language->get('button_cancel');
		$this->data['wk_erpproduct_option_value'] = $this->language->get('wk_erpproduct_option_value');
		$this->data['wk_opencartproduct_option_value'] = $this->language->get('wk_opencartproduct_option_value');

		$url = '';

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'].$url, true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title_bread'),
			'href'      => $this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);
   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title_insert'),
			'href'      => $this->url->link('catalog/wk_odoo_product_option_value/insert', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);

		$this->data['action'] = $this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['insert'] = $this->url->link('catalog/wk_odoo_product_option_value/insert', 'user_token=' . $this->session->data['user_token'].$url , true);

		$this->data['erpOptionValue'][] =  array('name' => 'Not Available(Connection Error)', 'id' => '');

		//$this->data['erpCurrency'] = $this->model_catalog_wkodoo->getTaxTotal();

		$this->load->model('catalog/connection');

		$data = $this->model_catalog_connection->make();

		if(isset($data['status']) AND $data['status']){
			$userId = $data['userId'];
			$client = $data['client'];
			$db= $data['db'];
			$pwd= $data['pwd'];

			$this->load->model('catalog/erp_product_options');
			$this->data['erpOptionValue'] = $this->model_catalog_erp_product_options->getErpProductOptionValueArray($userId,$client,$db,$pwd);
			// echo "<pre>";
			// var_dump($this->request->post);
			// var_dump($this->data['erpOptionValue']);
			// die;

		}

		$this->data['opProductOptionValue'] = $this->model_catalog_wkodoo->getOpProductsOptionValue();


 		$this->data['user_token'] = $this->session->data['user_token'];

 		if (isset($this->request->post['erpOptionId'])) {
			$this->data['erpOptionId'] = $this->request->post['erpOptionId'];
		}else{
			$this->data['erpOptionId'] = '';
		}

		if (isset($this->request->post['erpOptionValueId'])) {
			$this->data['erpOptionValueId'] = $this->request->post['erpOptionValueId'];
		}else{
			$this->data['erpOptionValueId'] = '';
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

		$this->data['header'] = $this->load->controller('common/header');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wk_odoo_product_option_value_manual', $this->data));
  	}


	public function insert() {

    	$this->language->load('catalog/wk_odoo_product_option_value');

    	$this->document->setTitle($this->language->get('heading_title_insert'));

		$this->load->model('catalog/erp_product_options');

    	if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

    		if ((utf8_strlen($this->request->post['opOptionValueId']) < 1)) {
				$error = $this->language->get('error_oppro');
			}

			if ((utf8_strlen($this->request->post['erpOptionId']) < 1)) {
				$error = $this->language->get('error_erppro');
			}

			if (!isset($error)) {
				$data = $this->request->post;


				$this->load->model('catalog/erp_product_options');
				$status = $this->model_catalog_erp_product_options->check_option_value_merge($data['opOptionValueId'],$data['erpOptionId']);

		  		if($status){
		  			$check = $this->db->query("SELECT option_id  FROM `" . DB_PREFIX . "option_value_description` WHERE option_value_id = '".$data['opOptionValueId']."' ")->row;
		  			$this->model_catalog_erp_product_options->addto_option_value_merge($data['opOptionValueId'],$data['erpOptionId'],$check['option_id']);

		  			$this->mergeAtOdoo($data['erpOptionId'],$data['opOptionValueId'],$check['option_id']);
					$this->session->data['success'] = $this->language->get('text_success_insert');
					$this->response->redirect($this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token'] , true));
		  		}
				else
					$error = $this->language->get('error_mapped');

			}

			$this->session->data['error_warning'] = $error ;

    	}

    	$this->getForm();
  	}

  	public function mergeAtOdoo($erp_id, $opencart_id, $oc_option_id){
  		$this->load->model('catalog/connection');

		$data = $this->model_catalog_connection->make();

		if(isset($data['status']) AND $data['status']){
			$userId = $data['userId'];
			$client = $data['client'];
			$db= $data['db'];
			$pwd= $data['pwd'];

	        $arrayVal   = array(
	            'opencart_value_id' => new xmlrpcval($opencart_id, "int"),
	            'erp_id' => new xmlrpcval($erp_id, "int"),
	            'name' => new xmlrpcval($erp_id, "int"),
	            'opencart_option_id' => new xmlrpcval($oc_option_id, "int")

	        );

	        $msg_ser    = new xmlrpcmsg('execute');
	        $msg_ser->addParam(new xmlrpcval($db, "string"));
	        $msg_ser->addParam(new xmlrpcval($userId, "int"));
	        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
	        $msg_ser->addParam(new xmlrpcval("opencart.product.option.value", "string"));
	        $msg_ser->addParam(new xmlrpcval("create", "string"));
	        $msg_ser->addParam(new xmlrpcval($arrayVal, "struct"));
	        $msg_ser->addParam(new xmlrpcval($context, "struct"));
	        $resp = $client->send($msg_ser);
		}
  	}


	private function validateForm() {
		$this->language->load('catalog/wk_odoo_product_option_value');

		if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_product_option_value')) {
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
  	public function delete() {

    	$this->language->load('catalog/wk_odoo_product_option_value');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/wkodoo');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {

			foreach ($this->request->post['selected'] as $id) {
				$this->model_catalog_wkodoo->deleteProductOptionValue($id);
	  		}

			$this->session->data['success'] = $this->language->get('text_success');

			$url='';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('catalog/wk_odoo_product_option_value', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

    	$this->getList();
  	}

	protected function validateDelete() {
    	if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_product_option_value')) {
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
