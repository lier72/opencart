<?php
################################################################################################
# Webservices Address xmlrpc Opencart 3.x.x.x From Webkul  http://webkul.com    #
################################################################################################
class ControllerCatalogwkodooOrder extends Controller {

    private $error = array();
    private $data = array();

    public function index() {

        $this->language->load('catalog/wk_odoo_order');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/wkodoo');

        $this->getlist();
    }

    protected function getList() {

        $this->load->model('catalog/wkodoo');

        $this->language->load('catalog/wk_odoo_order');

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
            'href'      => $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token'].$url , true),
            'separator' => ' :: '
        );


        $this->data['action'] = $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token'].$url , true);
        $this->data['insert'] = $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token'].$url , true);
        $this->data['delete'] = $this->url->link('catalog/wk_odoo_order/delete', 'user_token=' . $this->session->data['user_token'].$url , true);

        $this->data['orders'] = array();

        $product_total = $this->model_catalog_wkodoo->getOrdersTotal($data);

        $results = $this->model_catalog_wkodoo->getOrders($data);

        foreach ($results as $result) {

            $this->data['orders'][] = array(
                            'selected'=>False,
                            'id' => $result['id'],
                            'erpid' => $result['erp_order_id'],
                            'cartid' => $result['opencart_order_id'],
                            'cid' => $result['customer_id'],
                            'createddate' => $result['created_on'],
                            'createdby' => $result['created_by'],
                            'sync' => $result['is_synch'],

                            );

        }


        $this->data['user_token'] = $this->session->data['user_token'];

        if (isset($this->session->data['warning'])) {
            $this->error['warning'] = $this->session->data['warning'];
            unset($this->session->data['warning']);
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

        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }

        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }

        $this->data['sort_id'] = $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token']. '&sort=id'.$url , true);
        $this->data['sort_erpid'] = $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token']. '&sort=erp_order_id'.$url , true);
        $this->data['sort_opid'] = $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token']. '&sort=opencart_order_id'.$url , true);
        $this->data['sort_cid'] = $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token']. '&sort=customer_id'.$url , true);
        $this->data['sort_date'] = $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token']. '&sort=created_on'.$url , true);
        $this->data['sort_by'] = $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token']. '&sort=created_by'.$url , true);
        $this->data['sort_status'] = $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token'] . '&sort=is_synch' . $url, true);

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
        $pagination->url = $this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token']  . $url . '&page={page}', true);

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

        $this->response->setOutput($this->load->view('catalog/wk_odoo_order', $this->data));
    }

    public function sync() {
        $this->language->load('catalog/wk_odoo_order');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/erp_order');

        if ($this->request->server['REQUEST_METHOD'] == 'POST' AND isset($this->request->post['selected']) AND $this->request->post['selected']) {

            $this->load->model('catalog/connection');

            $data = $this->model_catalog_connection->make();

            if(isset($data['status']) AND $data['status']){
                $userId = $data['userId'];
                $client = $data['client'];
                // $sock= $data['sock'];
                $db= $data['db'];
                $pwd= $data['pwd'];
                $wkproducttype = $data['wkproducttype'];

                $this->load->model('user/user');
                $user_info = $this->model_user_user->getUser($this->session->data['user_id']);

                if($user_info)
                    $cart_user = $user_info['username'];
                else
                    $cart_user = $this->config->get('config_name');

                foreach ($this->request->post['selected'] as $order_id) {
                    $error = $this->model_catalog_erp_order->check_spec_order($order_id,$userId,$client,$db,$pwd,$cart_user,$wkproducttype);
                }

            }else{
                $error = array(0,0,'Warning No Connection Available !! ');
            }

            $this->language->load('catalog/wk_odoo_order');

            if(isset($error) AND is_array($error) AND isset($error[2])){
                $this->session->data['warning'] = $error[2];
            }else{
                $this->session->data['success'] = $this->language->get('text_success');
            }

            $this->response->redirect($this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token'] , true));
        }

        $this->getList();
    }

    public function delete() {

        $this->language->load('catalog/wk_odoo_order');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/wkodoo');

        if (isset($this->request->post['selected']) && $this->validateDelete()) {

            foreach ($this->request->post['selected'] as $id) {
                $this->model_catalog_wkodoo->deleteOrder($id);
            }

            $this->session->data['success'] = $this->language->get('text_success');

            $url='';

            if (isset($this->request->get['page'])) {
                $url .= '&page=' . $this->request->get['page'];
            }

            $this->response->redirect($this->url->link('catalog/wk_odoo_order', 'user_token=' . $this->session->data['user_token'] . $url, true));
        }

        $this->getList();
    }


    private function validateForm() {
        $this->language->load('catalog/wk_odoo_order');

        if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_order')) {
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
        if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_order')) {
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
