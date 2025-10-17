<?php
################################################################################################
# Webservices Product xmlrpc Opencart 3.x.x.x From Webkul  http://webkul.com 	#
################################################################################################
class ControllerCatalogwkodooProduct extends Controller {

	private $error = array();
	private $data = array();

	public function index() {

		$this->language->load('catalog/wk_odoo_product');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/wkodoo');

		$this->getlist();
  	}

	protected function getList() {

		$this->load->model('catalog/wkodoo');

		$this->language->load('catalog/wk_odoo_product');

		$this->data['heading_title'] = $this->language->get('heading_title');

		$this->data['button_insert'] = $this->language->get('button_insert');
		$this->data['button_delete'] = $this->language->get('button_delete');
		$this->data['button_sync'] = $this->language->get('button_sync');
		$this->data['button_filter'] = $this->language->get('button_filter');

		$this->data['wkid'] = $this->language->get('wkid');
		$this->data['wk_erpid'] = $this->language->get('wk_erpid');
		$this->data['wk_productName'] = $this->language->get('wk_productName');
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

		if (isset($this->request->get['filter_productName'])) {
			$filter_productName = $this->request->get['filter_productName'];
		} else {
			$filter_productName = null;
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

		if (isset($this->request->get['filter_productName'])) {
			$url .= '&filter_productName=' . urlencode(html_entity_decode($this->request->get['filter_productName'], ENT_QUOTES, 'UTF-8'));
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
			'filter_id'              => $filter_id,
			'filter_erpid'              => $filter_erpid,
			'filter_opid'              => $filter_opid,
			'filter_productName'       => $filter_productName,
			'filter_date'              => $filter_date,
			'filter_by'              => $filter_by,
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
			'href'      => $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);


		$this->data['action'] = $this->url->link('catalog/wk_odoo_product/sync', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['insert'] = $this->url->link('catalog/wk_odoo_product/insert', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['delete'] = $this->url->link('catalog/wk_odoo_product/delete', 'user_token=' . $this->session->data['user_token'].$url , true);

		$this->data['product'] = array();

		$product_total = $this->model_catalog_wkodoo->getProductTotal($data);

		$results = $this->model_catalog_wkodoo->getProduct($data);

	    foreach ($results as $result) {

      		$this->data['product'][] = array(
						'selected'=>False,
						'id' => $result['id'],
						'erpid' => $result['erp_template_id'],
						'productName' => $result['name'],
						'cartid' => $result['opencart_product_id'],
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

		$this->data['sort_id'] = $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token']. '&sort=id'.$url , true);
		$this->data['sort_erpid'] = $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token']. '&sort=erp_product_id'.$url , true);
		$this->data['sort_opid'] = $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token']. '&sort=opencart_product_id'.$url , true);
		$this->data['sort_productName'] = $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token']. '&sort=pd.name'.$url , true);
		$this->data['sort_date'] = $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token']. '&sort=created_on'.$url , true);
		$this->data['sort_by'] = $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token']. '&sort=created_by'.$url , true);
		$this->data['sort_status'] = $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token'] . '&sort=is_synch' . $url, true);

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

		if (isset($this->request->get['filter_productName'])) {
			$url .= '&filter_productName=' . urlencode(html_entity_decode($this->request->get['filter_productName'], ENT_QUOTES, 'UTF-8'));
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
		$pagination->url = $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token']  . $url . '&page={page}', true);

		$this->data['pagination'] = $pagination->render();
		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

		$this->data['sort'] = $sort;
		$this->data['order'] = $order;
		$this->data['filter_id'] = $filter_id;
		$this->data['filter_erpid'] = $filter_erpid;
		$this->data['filter_opid'] = $filter_opid;
		$this->data['filter_productName'] = $filter_productName;
		$this->data['filter_date'] = $filter_date;
		$this->data['filter_by'] = $filter_by;
		$this->data['filter_status'] = $filter_status;

		$this->data['header'] = $this->load->controller('common/header');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wk_odoo_product', $this->data));
  	}

  	public function insert() {

    	$this->language->load('catalog/wk_odoo_product');

    	$this->document->setTitle($this->language->get('heading_title_insert'));

		$this->load->model('catalog/wkodoo');

    	if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

    		if ((utf8_strlen($this->request->post['opProductId']) < 1)) {
				$error = $this->language->get('error_oppro');
			}

			if ((utf8_strlen($this->request->post['erpProductId']) < 1)) {
				$error = $this->language->get('error_erppro');
			}

			if (!isset($error)) {
				$data = $this->request->post;
				$this->load->model('catalog/erp_product');
				$status = $this->model_catalog_erp_product->chk_product_merge($data['opProductId'],$data['erpProductId']);

		  		if($status){
		  			$this->model_catalog_erp_product->addto_product_merge($data['erpProductId'],$data['opProductId'],'Manual Mappping');
		  			$this->mergeAtOdoo($data['erpProductId'],$data['opProductId']);
					$this->session->data['success'] = $this->language->get('text_success_insert');
					$this->response->redirect($this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token'] , true));
		  		}
				else
					$error = $this->language->get('error_mapped');

			}

			$this->session->data['error_warning'] = $error ;

    	}

    	$this->getForm();
  	}

  	public function mergeAtOdoo($erp_id, $opencart_id){
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
	        $erp_pro_id = array(
	            new xmlrpcval($erp_id, 'int')
	        );
	        $arrayVal   = array(
	            'opencart_id' => new xmlrpcval($opencart_id, "int")
	        );
	        $msg_ser    = new xmlrpcmsg('execute');
	        $msg_ser->addParam(new xmlrpcval($db, "string"));
	        $msg_ser->addParam(new xmlrpcval($userId, "int"));
	        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
	        $msg_ser->addParam(new xmlrpcval("product.product", "string"));
	        $msg_ser->addParam(new xmlrpcval("write", "string"));
	        $msg_ser->addParam(new xmlrpcval($erp_pro_id, "array"));
	        $msg_ser->addParam(new xmlrpcval($arrayVal, "struct"));
	        $msg_ser->addParam(new xmlrpcval($context, "struct"));
	        $resp = $client->send($msg_ser);
		}
  	}

  	protected function getForm() {

  		$this->load->model('catalog/wkodoo');

		$this->language->load('catalog/wk_odoo_product');

		$this->data['heading_title'] = $this->language->get('heading_title_insert');
		$this->data['button_save'] = $this->language->get('button_save');
		$this->data['button_cancel'] = $this->language->get('button_cancel');
		$this->data['wk_erpproduct'] = $this->language->get('wk_erpproduct');
		$this->data['wk_opencartproduct'] = $this->language->get('wk_opencartproduct');

		$url = '';

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'].$url, true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title_bread'),
			'href'      => $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);
   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title_insert'),
			'href'      => $this->url->link('catalog/wk_odoo_product/insert', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);

		$this->data['action'] = $this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['insert'] = $this->url->link('catalog/wk_odoo_product/insert', 'user_token=' . $this->session->data['user_token'].$url , true);

		$this->data['erpProduct'][] =  array('name' => 'Not Available(Connection Error)', 'id' => '');

		//$this->data['erpCurrency'] = $this->model_catalog_wkodoo->getTaxTotal();

		$this->load->model('catalog/connection');

		$data = $this->model_catalog_connection->make();

		if(isset($data['status']) AND $data['status']){
			$userId = $data['userId'];
			$client = $data['client'];
			$db= $data['db'];
			$pwd= $data['pwd'];

			$this->load->model('catalog/erp_product');
			$this->data['erpProduct'] = $this->model_catalog_erp_product->getErpProductArray($userId, $client, $db, $pwd);
		}

		$this->data['opProduct'] = $this->model_catalog_wkodoo->getOpProducts();

 		$this->data['user_token'] = $this->session->data['user_token'];

 		if (isset($this->request->post['opProductId'])) {
			$this->data['opProductId'] = $this->request->post['opProductId'];
		}else{
			$this->data['opProductId'] = '';
		}

		if (isset($this->request->post['erpProductId'])) {
			$this->data['erpProductId'] = $this->request->post['erpProductId'];
		}else{
			$this->data['erpProductId'] = '';
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

		if (isset($this->session->data['success'])) {
			$this->data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$this->data['success'] = '';
		}


		$this->data['header'] = $this->load->controller('common/header');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wk_odoo_product_manual', $this->data));

  	}


  	public function syncProduct() {
  		$this->language->load('catalog/wk_odoo_product');

    	$this->document->setTitle($this->language->get('heading_title'));

  		$this->load->model('catalog/erp_product');

  		if ($this->request->server['REQUEST_METHOD'] == 'POST' AND isset($this->request->post['selected']) AND $this->request->post['selected']) {

			$this->load->model('catalog/connection');

			$data = $this->model_catalog_connection->make();

			if(isset($data['status']) AND $data['status']){
				$userId = $data['userId'];
				$client = $data['client'];
				$db = $data['db'];
				$pwd = $data['pwd'];

				$language_id = $this->config->get("config_language_id");
				$cart_user = $this->user->getUserName() ? $this->user->getUserName() : $this->config->get('config_name');

				$context = array(
					'db'=>$db,
					'pwd'=>$pwd,
					'cart_user'=>$cart_user,
					'lang_id'=>$language_id,
					'wkproducttype'=>$data['wkproducttype'],
				);

				foreach ($this->request->post['selected'] as $product_id) {
					$this->model_catalog_erp_product->check_specific_product($product_id, false, $userId, $client, $context);
				}

				$this->session->data['success'] = $this->language->get('text_success');

				$this->response->redirect($this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token'], true));

			}else{
				$error = array(0,0,'Warning No Connection Available !! ');
			}
  		}
  		$this->getList();
  	}

  	public function sync() {

    	$this->language->load('catalog/wk_odoo_product');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/erp_product');

		$this->load->model('catalog/connection');

		$data = $this->model_catalog_connection->make();

		if(isset($data['status']) AND $data['status']){
			$userId = $data['userId'];
			$client = $data['client'];
			$db= $data['db'];
			$pwd= $data['pwd'];

			$language_id = $this->config->get("config_language_id");
			$cart_user = $this->user->getUserName() ? $this->user->getUserName() : $this->config->get('config_name');

			$context = array(
				'db'=>$db,
				'pwd'=>$pwd,
				'cart_user'=>$cart_user,
				'id_lang'=>$language_id,
				'wkproducttype'=>$data['wkproducttype'],
			);

			$this->model_catalog_erp_product->check_all_products($userId, $client, $context);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token'], true));
		}else{
			$this->error['warning'] = 'Warning No Connection Available !! ';
		}

    	$this->getList();
  	}

  	public function delete() {

    	$this->language->load('catalog/wk_odoo_product');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/wkodoo');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {

			foreach ($this->request->post['selected'] as $id) {
				$this->model_catalog_wkodoo->deleteProduct($id);
	  		}

			$this->session->data['success'] = $this->language->get('text_success');

			$url='';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('catalog/wk_odoo_product', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

    	$this->getList();
  	}


	private function validateForm() {
		$this->language->load('catalog/wk_odoo_product');

		if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_product')) {
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
    	if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_product')) {
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
