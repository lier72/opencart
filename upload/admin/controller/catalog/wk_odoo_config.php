<?php
#################################################################################################
# Webservices xmlrpc Tab Opencart 3.x.x.x From Webkul  http://webkul.com                        #
#################################################################################################
class ControllerCatalogwkodooconfig extends Controller {

    private $error = array();

    public function index()
    {

        $this->language->load('catalog/wk_odoo_config');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('catalog/wkodoo');

        $this->getform();
    }


    public function insert()
    {
        $this->language->load('catalog/wk_odoo_config');

        $this->document->setTitle($this->language->get('heading_title_update'));

        $this->load->model('catalog/wkodoo');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

            if ((utf8_strlen($this->request->post['wkurl']) < 5)) {
                $error = $this->language->get('error_url');
            }

            if ((utf8_strlen((int)$this->request->post['wkport']) < 1)) {
                $error = $this->language->get('error_port');
            }

            if ((utf8_strlen($this->request->post['wkdb_name']) < 1)) {
                $error = $this->language->get('error_db');
            }


            if ((utf8_strlen($this->request->post['wkuser']) < 3)) {
                $error = $this->language->get('error_user');
            }

            if ((utf8_strlen($this->request->post['wkpassword']) < 6)) {
                $error = $this->language->get('error_pass');
            }

            if (!isset($error)) {

                if (!empty($this->request->post['wkid'])) {
                    $this->model_catalog_wkodoo->insertConfg($this->request->post);

                    $this->session->data['success'] = $this->language->get('text_success_update');
                } else {
                    $this->model_catalog_wkodoo->insertConfg($this->request->post);

                    $this->session->data['success'] = $this->language->get('text_success_insert');
                }

                $this->response->redirect($this->url->link('catalog/wk_odoo_config', 'user_token=' . $this->session->data['user_token'], true));

            }

            $this->session->data['error_warning'] = $error ;
        }

        $this->getForm();
    }

    protected function getForm()
    {

        $this->load->model('catalog/wkodoo');

        $this->language->load('catalog/wk_odoo_config');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['heading_title'] = $this->language->get('heading_title_insert');
        $data['entry_name'] = $this->language->get('entry_name');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['url'] = $this->language->get('url');
        $data['port'] = $this->language->get('port');
        $data['db_name'] = $this->language->get('db_name');
        $data['user'] = $this->language->get('user');
        $data['password'] = $this->language->get('password');
        $data['connection_status'] = $this->language->get('connection_status');
        $data['warehouse'] = $this->language->get('warehouse');
        $data['product_sync_selection'] = $this->language->get('product_sync_selection');
        // $data['connection_success'] = $this->language->get('connection_success');
        $data['connection_success'] = $this->language->get('success');
        $data['text_wait'] = $this->language->get('text_wait');

        $data['wk_star'] = $this->language->get('wk_star');
        $data['wk_cnfrmkey'] = $this->language->get('wk_cnfrmkey');

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_home'),
            'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], true),
            'separator' => false
        );

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('heading_title'),
            'href'      => $this->url->link('catalog/wk_odoo_config', 'user_token=' . $this->session->data['user_token'] , true),
            'separator' => ' :: '
        );

        $data['action'] = $this->url->link('catalog/wk_odoo_config/insert', 'user_token=' . $this->session->data['user_token'],true);
        $data['chkconnection'] = $this->url->link('catalog/wk_odoo_config/chkConnection&user_token=' . $this->session->data['user_token'].'&SSL');

        $results = $this->model_catalog_wkodoo->viewDetails($this->session->data['user_id']);

        if(isset($results['id'])){
            $data['wkid'] = $results['id'];
            $data['wkuser'] = $results['user'];
            $data['wkurl'] = $results['url'];
            $data['wkport'] = $results['port'];
            $data['wkdb_name'] = $results['db_name'];
            $data['wkpassword'] = $results['password'];
            $data['wkstatus'] = $results['status'];
            $data['wkproducttype'] = $results['wkproducttype'];
        }

        $config_data = array(
                'wkid',
                'wkuser',
                'wkurl',
                'wkport',
                'wkdb_name',
                'wkpassword',
                'wkstatus',
                'wkproducttype',
                // 'wkwarehouse',
        );

        foreach ($config_data as $conf) {
            if (isset($this->request->post[$conf])) {
                $data[$conf] = $this->request->post[$conf];
            }
        }


        $data['user_token'] = $this->session->data['user_token'];

        if (isset($this->session->data['error_warning'])) {
            $this->error['warning'] = $this->session->data['error_warning'];
            unset($this->session->data['error_warning']);
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('catalog/wk_odoo_config', $data));
    }


    private function validateForm() {

        $this->language->load('catalog/wk_odoo_config');

        if (!$this->user->hasPermission('modify', 'catalog/wk_odoo_config')) {
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

    public function chkConnection() {

        $this->language->load('catalog/wk_odoo_config');

        $this->load->model('catalog/connection');

        $this->load->model('catalog/wkodoo');

        $json = array();

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {

            if ((utf8_strlen($this->request->post['wkurl']) < 3) || (utf8_strlen($this->request->post['wkurl']) > 100)) {
                $this->error['warning'] = $this->language->get('error_url');
            }

            if ((utf8_strlen($this->request->post['wkport']) < 1) || (utf8_strlen($this->request->post['wkport']) > 100)) {
                $this->error['warning'] = $this->language->get('error_port');
            }

            if ((utf8_strlen($this->request->post['wkdb_name']) < 1) || (utf8_strlen($this->request->post['wkdb_name']) > 100)) {
                $this->error['warning'] = $this->language->get('error_db');
            }

            if ((utf8_strlen($this->request->post['wkuser']) < 1) || (utf8_strlen($this->request->post['wkuser']) > 100)) {
                $this->error['warning'] = $this->language->get('error_user');
            }

            if ((utf8_strlen($this->request->post['wkpassword']) < 1) || (utf8_strlen($this->request->post['wkpassword']) > 100)) {
                $this->error['warning'] = $this->language->get('error_pass');
            }

            if (!isset($this->error['warning'])) {
                $json = $this->model_catalog_connection->chkConnection($this->request->post);

                if(isset($json['error'])){
                    if(!$json['error'])
                        $this->error['warning'] = $data['mesg'] = $this->language->get('text_error_access');
                    else
                        $this->error['warning'] = $data['mesg'] = $json['error'];
                    $this->request->post['wkstatus'] = $this->language->get('connection_failure');
                }else{
                    $this->session->data['success'] = $data['mesg'] = $json['success'];
                    $this->request->post['wkstatus'] = $this->language->get('connection_success');
                    $lang = $this->model_catalog_wkodoo->getDefaultLanguageName();
                    if ($lang){
                        $this->request->post['wkstatus'] .= " (Default Language ".$lang.")";
                    }
                }
                if (isset($this->request->post['wkstatus']))  {
                    $this->model_catalog_wkodoo->updateConfg($this->request->post);
                }

            }
        }
        $this->getForm();
        //$this->response->setOutput(json_encode($json));
    }

}
?>
