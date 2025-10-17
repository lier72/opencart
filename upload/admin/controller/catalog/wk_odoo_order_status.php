<?php
################################################################################################
# Webservices Order Status xmlrpc Opencart 3.x.x.x From Webkul  http://webkul.com 	#
################################################################################################
class ControllerCatalogwkodooOrderStatus extends Controller {

	private $error = array();
	private $data = array();

	public function index() {
		$this->language->load('catalog/wk_odoo_order_status');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('catalog/wkodoo');
		$this->getlist();
  	}

	protected function getList() {
		$this->language->load('catalog/wk_odoo_order_status');

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

		if (isset($this->request->get['filter_erpname'])) {
			$filter_erpname = $this->request->get['filter_erpname'];
		} else {
			$filter_erpname = null;
		}

		if (isset($this->request->get['filter_opname'])) {
			$filter_opname = $this->request->get['filter_opname'];
		} else {
			$filter_opname = null;
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
			$url .= '&filter_erpid=' . urlencode(html_entity_decode($this->request->get['filter_erpid'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_opid'])) {
			$url .= '&filter_opid=' . $this->request->get['filter_opid'];
		}

		if (isset($this->request->get['filter_erpname'])) {
			$url .= '&filter_erpname=' . urlencode(html_entity_decode($this->request->get['filter_erpname'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_opname'])) {
			$url .= '&filter_opname=' . urlencode(html_entity_decode($this->request->get['filter_opname'], ENT_QUOTES, 'UTF-8'));
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
			'filter_date'              => $filter_date,
			'filter_erpname'              => $filter_erpname,
			'filter_opname'            => $filter_opname,
			'filter_by'              => $filter_by,
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
			'href'      => $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);

    	$this->data['insert'] = $this->url->link('catalog/wk_odoo_order_status/insert', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['delete'] = $this->url->link('catalog/wk_odoo_order_status/delete', 'user_token=' . $this->session->data['user_token'].$url , true);

		$this->data['order_status'] = array();
		$product_total = $this->model_catalog_wkodoo->getOrderStatusTotal($data);
		$results = $this->model_catalog_wkodoo->getOrderStatus($data);
	    foreach ($results as $result) {
      		$this->data['order_status'][] = array(
							'selected'=>False,
							'id' => $result['id'],
							'erpid' => $result['erp_order_status_id'],
							'cartid' => $result['opencart_order_status_id'],
							'createddate' => $result['created_on'],
							'createdby' => $result['created_by'],
							'opname' => $result['opname'],
							'erpname' => $result['erpname'],
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

		$this->data['sort_id'] = $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token']. '&sort=id'.$url , true);
		$this->data['sort_erpid'] = $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token']. '&sort=erp_order_status_id'.$url , true);
		$this->data['sort_opid'] = $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token']. '&sort=opencart_order_status_id'.$url , true);
		$this->data['sort_date'] = $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token']. '&sort=created_on'.$url , true);
		$this->data['sort_by'] = $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token']. '&sort=created_by'.$url , true);
		$this->data['sort_opname'] = $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token'] . '&sort=opname' . $url, true);
		$this->data['sort_erpname'] = $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token'] . '&sort=erpname' . $url, true);
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

		if (isset($this->request->get['filter_erpname'])) {
			$url .= '&filter_erpname=' . urlencode(html_entity_decode($this->request->get['filter_erpname'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_opname'])) {
			$url .= '&filter_opname=' . urlencode(html_entity_decode($this->request->get['filter_opname'], ENT_QUOTES, 'UTF-8'));
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
		$pagination->url = $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token']  . $url . '&page={page}', true);

		$this->data['pagination'] = $pagination->render();
		$this->data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($product_total - $this->config->get('config_limit_admin'))) ? $product_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $product_total, ceil($product_total / $this->config->get('config_limit_admin')));

		$this->data['sort'] = $sort;
		$this->data['order'] = $order;
		$this->data['filter_id'] = $filter_id;
		$this->data['filter_erpid'] = $filter_erpid;
		$this->data['filter_opid'] = $filter_opid;
		$this->data['filter_date'] = $filter_date;
		$this->data['filter_by'] = $filter_by;
		$this->data['filter_opname'] = $filter_opname;
		$this->data['filter_erpname'] = $filter_erpname;
		$this->data['header'] = $this->load->controller('common/header');
		$this->data['column_left'] = $this->load->controller('common/column_left');
		$this->data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('catalog/wk_odoo_order_status', $this->data));
  	}

  	public function insert() {
    	$this->language->load('catalog/wk_odoo_order_status');
    	$this->document->setTitle($this->language->get('heading_title_insert'));
		$this->load->model('catalog/wkodoo');
    	if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateDelete()) {
    		if ((utf8_strlen($this->request->post['opOrderstatusId']) < 1)) {
				$error = $this->language->get('error_oporderstatus');
			}
			if ((utf8_strlen($this->request->post['erpOrderstatusId']) < 1)) {
				$error = $this->language->get('error_erporderstatus');
			}
			if (!isset($error)) {
				$data = $this->request->post;
				$opExplode = explode('/',$data['opOrderstatusId']);
				$opName = $opExplode[1];
				$opId = $opExplode[0];
				$erpExplode = explode('/',$data['erpOrderstatusId']);
				$erpName = $erpExplode[1];
				$erpId = $erpExplode[0];
	  			$chk = $this->model_catalog_wkodoo->addto_order_status_merge($opName, $opId, $erpName, $erpId,'Manual Mappping');
	  			if($chk){
					$this->session->data['success'] = $this->language->get('text_success_insert');
					$this->response->redirect($this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token'] , true));
				}else{
					$error = $this->language->get('error_mapped');
					$this->error['warning'] = $error;
				}
			}
    	}
    	$this->getForm();
  	}

  	protected function getForm() {
  		$this->load->model('catalog/wkodoo');
		$this->language->load('catalog/wk_odoo_order_status');
		$this->data['heading_title'] = $this->language->get('heading_title_insert');
		$this->data['button_save'] = $this->language->get('button_save');
		$this->data['button_cancel'] = $this->language->get('button_cancel');
		$this->data['wk_erporderstatus'] = $this->language->get('wk_erporderstatus');
		$this->data['wk_opencartorderstatus'] = $this->language->get('wk_opencartorderstatus');
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
			'href'      => $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);
   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title_insert'),
			'href'      => $this->url->link('catalog/wk_odoo_order_status/insert', 'user_token=' . $this->session->data['user_token'].$url , true),
      		'separator' => ' :: '
   		);

		$this->data['action'] = $this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token'].$url , true);
    	$this->data['insert'] = $this->url->link('catalog/wk_odoo_order_status/insert', 'user_token=' . $this->session->data['user_token'].$url , true);

		$this->data['erpOrderstatus'] =  array( array('id'=>'paid' , 'name' => 'PAID'),
												array('id'=>'cancel' , 'name' => 'CANCEL'),
												array('id'=>'delivered' , 'name' => 'DELIVERED'),
												array('id'=>'manual' , 'name' => 'Confirm'),
											  );


		$this->data['opOrderstatus'] = $this->model_catalog_wkodoo->getOpOrderStatus();

 		$this->data['user_token'] = $this->session->data['user_token'];

 		if (isset($this->request->post['opOrderstatusId'])) {
			$this->data['opOrderstatusId'] = $this->request->post['opOrderstatusId'];
		}else{
			$this->data['opOrderstatusId'] = '';
		}

		if (isset($this->request->post['erpOrderstatusId'])) {
			$this->data['erpOrderstatusId'] = $this->request->post['erpOrderstatusId'];
		}else{
			$this->data['erpOrderstatusId'] = '';
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
		$this->response->setOutput($this->load->view('catalog/wk_odoo_order_status_manual', $this->data));
  	}

  	public function delete() {
    	$this->language->load('catalog/wk_odoo_order_status');
    	$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('catalog/wkodoo');
		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $id) {
				$this->model_catalog_wkodoo->deleteOrderStatus($id);
	  		}
			$this->session->data['success'] = $this->language->get('text_success');
			$url='';
			if (isset($this->request->get['page'])){
				$url .= '&page=' . $this->request->get['page'];
			}
			$this->response->redirect($this->url->link('catalog/wk_odoo_order_status', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}
    	$this->getList();
  	}

	protected function validateDelete() {
    	if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_order_status')) {
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
