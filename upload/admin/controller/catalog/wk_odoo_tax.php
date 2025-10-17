<?php
################################################################################################
# Webservices tax xmlrpc Opencart 3.x.x.x. From Webkul  http://webkul.com 	#
################################################################################################
class ControllerCatalogwkodootax extends Controller {

	private $error = array();
	private $data = array();

	public function index() {

		$this->language->load('catalog/wk_odoo_tax');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/wkodoo');

		$this->getlist();
  	}

	protected function getList() {

		$this->load->model('catalog/wkodoo');

		$this->language->load('catalog/wk_odoo_tax');

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
		$this->data['wk_issync'] = $this->language->get('wk_issync');
		$this->data['wk_rate'] = $this->language->get('wk_rate');
		$this->data['text_syncnreq'] = $this->language->get('text_syncnreq');
		$this->data['text_syncreq'] = $this->language->get('text_syncreq');
		$this->data['button_manual'] = $this->language->get('button_manual');
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

		if (isset($this->request->get['filter_rate'])) {
			$filter_rate = $this->request->get['filter_rate'];
		} else {
			$filter_rate = null;
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

		if (isset($this->request->get['filter_rate'])) {
			$url .= '&filter_rate=' . $this->request->get['filter_rate'];
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
			'filter_rate' 			  => $filter_rate,
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
			'href'      => $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);


		$this->data['action'] = $this->url->link('catalog/wk_odoo_tax/sync', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['insert'] = $this->url->link('catalog/wk_odoo_tax/insert', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['delete'] = $this->url->link('catalog/wk_odoo_tax/delete', 'user_token=' . $this->session->data['user_token'].$url , true);

		$this->data['category'] = array();

		$product_total = $this->model_catalog_wkodoo->getTaxTotal($data);

		$results = $this->model_catalog_wkodoo->getTax($data);

	    foreach ($results as $result) {

      		$this->data['category'][] = array(
							'selected'=>False,
							'id' => $result['id'],
							'erpid' => $result['erp_tax_id'],
							'cartid' => $result['opencart_tax_id'],
							'rate' => $result['rate'],
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
			$url .= '&order=ASC';
		} else {
			$url .= '&order=DESC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$this->data['sort_id'] = $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token']. '&sort=id'.$url , true);
		$this->data['sort_erpid'] = $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token']. '&sort=erp_tax_id'.$url , true);
		$this->data['sort_opid'] = $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token']. '&sort=opencart_tax_id'.$url , true);
		$this->data['sort_date'] = $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token']. '&sort=created_on'.$url , true);
		$this->data['sort_by'] = $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token']. '&sort=created_by'.$url , true);
		$this->data['sort_status'] = $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token'] . '&sort=is_synch' . $url, true);
		$this->data['sort_rate'] = $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token'] . '&sort=rate' . $url, true);

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

		if (isset($this->request->get['filter_rate'])) {
			$url .= '&filter_rate=' . $this->request->get['filter_rate'];
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
		$pagination->url = $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token']  . $url . '&page={page}', true);

		$this->data['pagination'] = $pagination->render();
		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

		$this->data['sort'] = $sort;
		$this->data['order'] = $order;
		$this->data['filter_id'] = $filter_id;
		$this->data['filter_erpid'] = $filter_erpid;
		$this->data['filter_opid'] = $filter_opid;
		$this->data['filter_rate'] = $filter_rate;
		$this->data['filter_date'] = $filter_date;
		$this->data['filter_by'] = $filter_by;
		$this->data['filter_status'] = $filter_status;

		$this->data['header'] = $this->load->controller('common/header');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/wk_odoo_tax', $this->data));
  	}

  	public function insert() {

    	$this->language->load('catalog/wk_odoo_tax');

    	$this->document->setTitle($this->language->get('heading_title_insert'));

		$this->load->model('catalog/erp_tax');

    	if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

    		if ((utf8_strlen($this->request->post['opTaxId']) < 1)) {
				$error = $this->language->get('error_optax');
			}

			if ((utf8_strlen($this->request->post['erpTaxId']) < 1)) {
				$error = $this->language->get('error_erptax');
			}

			if (!isset($error)) {
				$data = $this->request->post;

				$status = $this->model_catalog_erp_tax->addto_tax_merge($data['erpTaxId'],$data['opTaxId'],'Manual Mappping');

		  		if($status){
					$this->session->data['success'] = $this->language->get('text_success_insert');
					$this->response->redirect($this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token'] , true));
		  		}
				else
					$error = $this->language->get('error_mapped');

			}

			$this->session->data['error_warning'] = $error ;

			//$this->redirect($this->url->link('catalog/wk_webservices_tab/insert', 'user_token=' . $this->session->data['user_token'] , true));

    	}

    	$this->getForm();
  	}

  	protected function getForm() {

  		$this->load->model('catalog/wkodoo');

		$this->language->load('catalog/wk_odoo_tax');

		$this->data['heading_title'] = $this->language->get('heading_title_insert');
		$this->data['button_save'] = $this->language->get('button_save');
		$this->data['button_cancel'] = $this->language->get('button_cancel');
		$this->data['wk_erptax'] = $this->language->get('wk_erptax');
		$this->data['wk_opencarttax'] = $this->language->get('wk_opencarttax');
		$this->data['button_filter'] = $this->language->get('button_filter');

		$url = '';

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'].$url, true),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title_bread'),
			'href'      => $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);
   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title_insert'),
			'href'      => $this->url->link('catalog/wk_odoo_tax/insert', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);

		$this->data['action'] = $this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['insert'] = $this->url->link('catalog/wk_odoo_tax/insert', 'user_token=' . $this->session->data['user_token'].$url , true);

		$this->data['erpTax'][] =  array('name' => 'Not Available(Connection Error)', 'id' => '');

		$this->load->model('catalog/connection');

		$data = $this->model_catalog_connection->make();

		if(isset($data['status']) AND $data['status']){
			$db= $data['db'];
			$pwd= $data['pwd'];
			$userId = $data['userId'];
			$client = $data['client'];
			$this->load->model('catalog/erp_tax');
			$this->data['erpTax'] = $this->model_catalog_erp_tax->getErpTaxArray($userId, $client, $db, $pwd, $cart_user);
		}

		$this->data['opTax'] = $this->model_catalog_wkodoo->getOpTax();

 		$this->data['user_token'] = $this->session->data['user_token'];

 		if (isset($this->request->post['opTaxId'])) {
			$this->data['opTaxId'] = $this->request->post['opTaxId'];
		}else{
			$this->data['opTaxId'] = '';
		}

		if (isset($this->request->post['erpTaxId'])) {
			$this->data['erpTaxId'] = $this->request->post['erpTaxId'];
		}else{
			$this->data['erpTaxId'] = '';
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

		$this->response->setOutput($this->load->view('catalog/wk_odoo_tax_manual', $this->data));
  	}


  	public function sync() {
    	$this->language->load('catalog/wk_odoo_tax');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/erp_tax');

		$this->load->model('catalog/connection');

		$data = $this->model_catalog_connection->make();

		if(isset($data['status']) AND $data['status']){
			$db = $data['db'];
			$pwd = $data['pwd'];
			$userId = $data['userId'];
			$client = $data['client'];

			$cart_user = $this->user->getUserName() ? $this->user->getUserName() : $this->config->get('config_name');

			$this->model_catalog_erp_tax->check_all_taxes($userId, $client, $db, $pwd, $cart_user);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token'] , true));
		}else{
			$this->error['warning'] = 'Warning No Connection Available !! ';
		}

    	$this->getList();
  	}

  	public function delete() {

    	$this->language->load('catalog/wk_odoo_tax');

    	$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/wkodoo');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {

			foreach ($this->request->post['selected'] as $id) {
				$this->model_catalog_wkodoo->deleteTax($id);
	  		}

			$this->session->data['success'] = $this->language->get('text_success');

			$url='';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('catalog/wk_odoo_tax', 'user_token=' . $this->session->data['user_token'] . $url, true));
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
    	if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_tax')) {
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
