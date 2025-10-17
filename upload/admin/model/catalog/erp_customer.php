<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 2.3.x.x From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpCustomer extends Model {

    //To check for all customers.
    public function check_all_customers($userId, $client, $db, $pwd, $cart_user){
        $is_error      = 0;
        $error_message = '';
        $ids           = '';
        $data          = $this->db->query("SELECT `customer_id`,`firstname`,`lastname`,`email` FROM `" . DB_PREFIX . "customer`  WHERE `customer_id` NOT IN (SELECT `opencart_customer_id` FROM `" . DB_PREFIX . "erp_customer_merge` WHERE `is_synch` = 0)")->rows;
        if (count($data) == 0) {
            //Nothing to export
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                '$ids' => $ids
            );
        }
        foreach ($data as $customer_data) {
            $id_customer = $this->search_customer($customer_data['customer_id']);

            if ($id_customer[0] <= 0) {
                $customer_data['firstname'] = $customer_data['firstname'];
                $customer_data['lastname']  = $customer_data['lastname'];
                $key                        = array(
                    'name' => new xmlrpcval($customer_data['firstname'] .' '. $customer_data['lastname'], "string"),
                    'email' => new xmlrpcval($customer_data['email'], "string"),
                    'is_company' => new xmlrpcval(true, "boolean")
                );
                $log = new oob_log();
                if ($id_customer[0] == 0) {
        					$context = array(
        						'opencart' => new xmlrpcval('opencart', "string")
        					);
                    $msg_ser = new xmlrpcmsg('execute');
                    $msg_ser->addParam(new xmlrpcval($db, "string"));
                    $msg_ser->addParam(new xmlrpcval($userId, "int"));
                    $msg_ser->addParam(new xmlrpcval($pwd, "string"));
                    $msg_ser->addParam(new xmlrpcval("res.partner", "string"));
                    $msg_ser->addParam(new xmlrpcval("create", "string"));
                    $msg_ser->addParam(new xmlrpcval($key, "struct"));
		                $msg_ser->addParam(new xmlrpcval($context, "struct"));
                    $resp = $client->send($msg_ser);
                    if (!$resp->faultCode()) {
                        $val = $resp->value()->me;
                        //enter in to customer merge table ...........
                        $db_done = $this->addto_customer_merge($val['int'], $customer_data['customer_id'], $cart_user);
                        $add_to_openerp = $this->addto_openerp_merge($val['int'], $customer_data['customer_id'], '-', $userId, $client, $db, $pwd);
                        if ($add_to_openerp['is_error'] == 1) {
                            $is_error = 1;
                            $error_message .= $add_to_openerp['error_message'] . ',';
                            $ids .= $customer_data['customer_id'] . ',';
                        }
                        //when customer added to merge table then enty done in address_merge table .........
                        if ($db_done) {
                            $create_add = $this->check_all_address($val['int'], $customer_data['customer_id'], $userId, $client, $cart_user, $db, $pwd);
                            if ($create_add['is_error'] == 1) {
                                $is_error      = 1;
                                // $error_message .= $create_add['error_message'] . ',';
                                $error_message = 'Error in Address';
                                $ids .= $create_add['ids'] . ',';
                            }
                        }
                    } else {
                        $db_done   = 0;
                        $is_error  = 1;
                        $error_msg = $resp->faultString();
                        $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
                        $error_message .= $error_msg . ',';
                        $ids .= $customer_data['customer_id'] . ',';
                    }
                }

              if ($id_customer[0] == -1) {
                  $update = $this->update_customer($customer_data, $customer_data['customer_id'], $id_customer[1], $userId, $client, $db, $pwd);
                  if ($update['value'] != True) {
                      $is_error = 1;
                      $error_message .= $update['error_message'] . ',';
                      $ids .= $customer_data['customer_id'] . ',';
                  }
				$create_add = $this->check_all_address($id_customer[1], $customer_data['customer_id'], $userId, $client, $cart_user, $db, $pwd);
				if ($create_add['is_error'] == 1) {
                    $is_error      = 1;
                    $error_message .= 'Error in Address';
                    $ids .= $create_add['ids'] . ',';
                }
            }
        }
        }
        return array(
            'is_error' => $is_error,
            'error_message' => $error_message,
            'value' => 1,
            'ids' => $ids
        );
    }


    /**
     * [search_customer to search the customer entry into customer merge
     * if erp_customer_id found and greater than 0, then check for the is_synch. If is_synch is 1 (means need to synch) then return -1 otherwise return erp_customer_id]
     * @param  [type] $customer_id [need merge customer id ]
     * @return [type]              [either array or erp_customer_id]
     */
    public function search_customer($customer_id){
        $check = $this->db->query("SELECT `is_synch`,`erp_customer_id`  from `" . DB_PREFIX . "erp_customer_merge` where opencart_customer_id = '$customer_id'")->row;

        if (isset($check['erp_customer_id']) AND $check['erp_customer_id'] > 0) {
            if ($check['is_synch'] == 1) {
                $check_arr[0] = -1;
                $check_arr[1] = $check['erp_customer_id'];
                return $check_arr;
            } else {
                $check_arr[0] = $check['erp_customer_id'];
                return $check_arr;
            }
        } else {
            $check_arr[0] = 0;
            return $check_arr;
        }
    }

    //To insert data in customer merge table
    public function addto_customer_merge($erp_customer_id, $customer_id, $cart_user = 'Front End'){
        $data = array(
            'erp_customer_id' => $erp_customer_id,
            'customer_id' => $customer_id,
            'created_by' => $cart_user
        );

        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_customer_merge` SET erp_customer_id = '$erp_customer_id' , opencart_customer_id = '$customer_id' , created_by = '$cart_user', created_on = NOW() ");

        return $this->db->getLastId();
    }

    public function chkErpOpencartCustomers($erp_id, $cart_id){
        $chk  = $this->db->query("SELECT `id` from `" . DB_PREFIX . "erp_customer_merge` WHERE opencart_customer_id='$cart_id' or erp_customer_id='$erp_id'")->row;
        if($chk)
            return false;
        return true;
    }

    public function addto_openerp_merge($erp_id, $cart_id, $cart_add_id, $userId, $client, $db, $pwd){
        if ($cart_add_id == '-')
            {
            $cart_add_id = 0;
        }

        $arrayVal = array(
            'odoo_id' => new xmlrpcval($erp_id,'int'),
            'ecommerce_customer_id' => new xmlrpcval($cart_id, "int"),
            'ecommerce_address_id' => new xmlrpcval($cart_add_id, "int"),
            'created_by' => new xmlrpcval('Opencart', "string"),
            'need_sync' => new xmlrpcval('no', "string")
        );
        $msg_ser  = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("opencart.customer", "string"));
        $msg_ser->addParam(new xmlrpcval("create", "string"));
        $msg_ser->addParam(new xmlrpcval($arrayVal, "struct"));
        $resp = $client->send($msg_ser);
        if ($resp->faultCode()) {
            $error_message = $resp->faultString();
            return array(
                'error_message' => $error_message,
                'is_error' => 1
            );
        } else {
            return array(
                'is_error' => 0
            );
        }
    }

    public function check_all_address($erp_cust_id, $cart_customer_id, $userId, $client, $cart_user, $db, $pwd){
        $is_error = 0;
        $error_message = '';
        $ids = '';
        $log = new oob_log();
        $data = $this->db->query("SELECT a.*,c.telephone,c.email from `" . DB_PREFIX . "address` a LEFT JOIN " .DB_PREFIX. "customer c ON (a.customer_id = c.customer_id) where a.customer_id ='$cart_customer_id'")->rows;

        $this->load->model('catalog/erp_country');
        $this->load->model('catalog/erp_state');

        foreach ($data as $address_data) {
            $search_address = $this->search_address($address_data['address_id']);
            if ($search_address[0] <= 0) {
                    $erp_country_id = false;
                    $erp_state_id   = false;
                    if ($address_data['country_id'] != null && $address_data['country_id'] != "" && $address_data['country_id'] != 0) {
                        $country_iso_code = $this->model_catalog_erp_country->get_iso($address_data['country_id']);
                        $country_name     = $this->model_catalog_erp_country->get_country_name($address_data['country_id']);
                        $erp_country_id   = $this->model_catalog_erp_country->get_country($country_iso_code, $country_name, $userId, $client, $db, $pwd);
                    }
                    if ($address_data['zone_id'] != null && $address_data['zone_id'] != "" && $address_data['zone_id'] != 0) {

                        $state_dtls   = $this->model_catalog_erp_state->get_state_dtls($address_data['zone_id']);
                        $erp_state_id = $this->model_catalog_erp_state->check_state($userId, $client, $state_dtls['code'], $state_dtls['name'], $state_dtls['country_id'], $db, $pwd);
                    }
                    $name = $address_data['firstname'].' '.$address_data['lastname'];
                    $address1 = $address_data['address_1'];
                    $address2 = $address_data['address_2'];
                    $city = $address_data['city'];
                    $email = $address_data['email'];
                    $context = array(
                        'opencart' => new xmlrpcval('opencart', "string")
                    );
                    $key = array(
                        'parent_id' => new xmlrpcval($erp_cust_id, "int"),
                        'name' => new xmlrpcval($name, "string"),
                        'email' => new xmlrpcval($email, "string"),
                        'street' => new xmlrpcval($address1, "string"),
                        'street2' => new xmlrpcval($address2, "string"),
                        'phone' => new xmlrpcval($address_data['telephone'], "string"),
                        'zip' => new xmlrpcval($address_data['postcode'], "string"),
                        'city' => new xmlrpcval($city, "string"),
                        'country_id' => new xmlrpcval($erp_country_id, "int"),
                        'state_id' => new xmlrpcval($erp_state_id, "int"),
                        'customer' => new xmlrpcval(false, "boolean"),
                        'use_parent_address' => new xmlrpcval(false, "boolean")
                    );
                    if ($search_address[0] == 0) {
                        $msg_ser = new xmlrpcmsg('execute');
                        $msg_ser->addParam(new xmlrpcval($db, "string"));
                        $msg_ser->addParam(new xmlrpcval($userId, "int"));
                        $msg_ser->addParam(new xmlrpcval($pwd , "string"));
                        $msg_ser->addParam(new xmlrpcval("res.partner", "string"));
                        $msg_ser->addParam(new xmlrpcval("create", "string"));
                        $msg_ser->addParam(new xmlrpcval($key, "struct"));
                        $msg_ser->addParam(new xmlrpcval($context, "struct"));
                        $resp = $client->send($msg_ser);
                        if (!$resp->faultCode()) {
                            $val = $resp->value()->me;
                            $this->addto_address_merge($address_data['address_id'], $val['int'], $address_data['customer_id'], $cart_user);
                            $add_to_openerp = $this->addto_openerp_merge($val['int'], $address_data['customer_id'], $address_data['address_id'], $userId, $client, $db, $pwd);
                            if ($add_to_openerp['is_error'] == 1) {
                                $is_error = 1;
                                $error_message .= $add_to_openerp['error_message'] . ',';
                                $ids .= $address_data['customer_id'] . ',';
                            }
                        } else {
                            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
                            $is_error  = 1;
                            $error_msg = $resp->faultString();
                            $error_message .= $error_msg . ',';
                            $ids .= $address_data['customer_id'] . ',';
                        }
                    }
                    if ($search_address[0] == -1) {
                        $update = $this->update_address($key, $address_data['address_id'], $search_address[1], $userId, $client, $db, $pwd);
                        if ($update['value'] != True) {
                            $is_error = 1;
                            $error_message .= $update['error_message'] . ',';
                            $ids .= $address_data['customer_id'] . ',';
                            }
                }
            }
        }
        return array(
            'is_error' => $is_error,
            'error_message' => $error_message,
            'ids' => $ids
        );
    }

    public function search_address($id_address) {
        $erp_address = $this->db->query("SELECT `is_synch`,`erp_address_id`  from `" . DB_PREFIX . "erp_address_merge` where `opencart_address_id`='$id_address'")->row;
        if (isset($erp_address['erp_address_id']) AND $erp_address['erp_address_id'] > 0) {
            if ($erp_address['is_synch'] == 0) {
                $arr[0] = -1;
                $arr[1] = $erp_address['erp_address_id'];
                return $arr;
            } else {
                $arr[0] = $erp_address['erp_address_id'];
                return $arr;
            }
        } else {
            $arr[0] = 0;
            return $arr;
        }
    }


    public function addto_address_merge($id_address, $erp_address_id, $id_customer, $cart_user = 'Front End'){

        $data = array(
            'erp_address_id' => $erp_address_id,
            'address_id' => $id_address,
            'created_by' => $cart_user,
            'id_customer' => $id_customer
        );

        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_address_merge` SET erp_address_id = '$erp_address_id' , opencart_address_id = '$id_address' ,customer_id = '$id_customer', created_by = '$cart_user', created_on = NOW() ");
    }


    public function update_address($key, $id_address, $erp_id, $userId, $client, $db, $pwd){
        $context = array(
            'opencart' => new xmlrpcval('opencart', "string")
        );
        $erp_cust_id = array(
            new xmlrpcval($erp_id, 'int')
        );
        $log = new oob_log();
        $msg_ser     = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("res.partner", "string"));
        $msg_ser->addParam(new xmlrpcval("write", "string"));
        $msg_ser->addParam(new xmlrpcval($erp_cust_id, "array"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
        $msg_ser->addParam(new xmlrpcval($context, "struct"));
        $resp = $client->send($msg_ser);
        if ($resp->faultCode()) {
            $error_message = $resp->faultString();
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            return array(
                'error_message' => $error_message,
                'value' => False
            );
        } else {
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_address_merge` set `is_synch`=0 where `opencart_address_id`='$id_address'");
            return array(
                'value' => True
            );
        }
    }

    public function update_customer($data, $cart_id, $erp_id, $userId, $client, $db, $pwd){
        $context = array(
            'opencart' => new xmlrpcval('opencart', "string")
        );
        $erp_cust_id = array(
            new xmlrpcval($erp_id, 'int')
        );
        $name = $data['firstname'] .' '. $data['lastname'];
        $email = $data['email'];
        $key     = array(
            'name' => new xmlrpcval($name , "string"),
            'email' => new xmlrpcval($email , "string"),
        );
        $log = new oob_log();
        $msg_ser     = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db , "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd , "string"));
        $msg_ser->addParam(new xmlrpcval("res.partner", "string"));
        $msg_ser->addParam(new xmlrpcval("write", "string"));
        $msg_ser->addParam(new xmlrpcval($erp_cust_id, "array"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
        $msg_ser->addParam(new xmlrpcval($context, "struct"));
        $resp = $client->send($msg_ser);
        if ($resp->faultCode()) {
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            $error_message = $resp->faultString();
            return array(
                'error_message' => $error_message,
                'value' => False
            );
        } else {
            $this->db->query("UPDATE `" . DB_PREFIX . "erp_customer_merge` set `is_synch`= 0 where opencart_customer_id = '$cart_id'");
            return array(
                'value' => True
            );
        }
    }

    public function check_specific_customer($id_customer, $client, $userId,$cart_user='Front End', $db, $pwd){
      $log = new oob_log();
		$check_customer_id = $this->search_customer($id_customer);
		if ($check_customer_id[0] > 0)
			return $check_customer_id[0];
		elseif($check_customer_id[0]==-1)
			return $check_customer_id[1];
		else {
			    $customer_data = $this->db->query("SELECT `customer_id`,`firstname`,`lastname`,`email` FROM `" . DB_PREFIX . "customer`  WHERE `customer_id` = '".$id_customer."'")->row;

                $customer_data['firstname'] = $customer_data['firstname'];
                $customer_data['lastname']  = $customer_data['lastname'];
                $key = array(
                    'name' => new xmlrpcval($customer_data['firstname'] .' '. $customer_data['lastname'], "string"),
                    'email' => new xmlrpcval($customer_data['email'], "string"),
                    'is_company' => new xmlrpcval(true, "boolean")
                );
                $context = array(
                    'opencart' => new xmlrpcval('opencart', "string")
                );
                $msg_ser = new xmlrpcmsg('execute');
                $msg_ser->addParam(new xmlrpcval($db, "string"));
                $msg_ser->addParam(new xmlrpcval($userId, "int"));
                $msg_ser->addParam(new xmlrpcval($pwd, "string"));
                $msg_ser->addParam(new xmlrpcval("res.partner", "string"));
                $msg_ser->addParam(new xmlrpcval("create", "string"));
                $msg_ser->addParam(new xmlrpcval($key, "struct"));
                $msg_ser->addParam(new xmlrpcval($context, "struct"));
                $resp = $client->send($msg_ser);
                if (!$resp->faultCode()) {
                    $val            = $resp->value()->me;
                    //enter in to customer merge table ...........
                    $db_done        = $this->addto_customer_merge($val['int'], $customer_data['customer_id'], $cart_user);
                    $add_to_openerp = $this->addto_openerp_merge($val['int'], $customer_data['customer_id'], '-', $userId, $client, $db, $pwd);
                   return $val['int'];
                }
                else{
                  $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
                }
            }
        return -1;
	}

    public function check_specific_address($id_address = false, $id_customer, $userId, $client, $db, $pwd){
    		$erp_state_id=False;
    		$erp_country_id=False;
        $log = new oob_log();
        $erp_address = $this->search_address($id_address);
        if ($erp_address[0] > 0 AND $id_address) {
            return $erp_address[0];
        } else {
            $erp_cust_id = $this->check_specific_customer($id_customer, $client, $userId, $cart_user='Front End', $db, $pwd);
            if($id_address)
                $add = "AND a.address_id = '".$id_address."'";
            else
                $add = '';
            $data = $this->db->query("SELECT DISTINCT a.address_id, a.country_id, a.zone_id, a.customer_id, a.company, a.lastname, a.firstname, a.address_1, a.address_2, a.postcode, a.city, cu.telephone, cu.email, c.`name` AS country,c.iso_code_3 AS country_iso, s.name AS state, s.code AS state_iso,IFNULL(erp.`is_synch`, 0) AS is_synch, IFNULL(erp.`erp_address_id`, 0) AS erp_address_id
                FROM `".DB_PREFIX."customer` cu
                LEFT JOIN `".DB_PREFIX."address` a ON (a.`customer_id` = cu.`customer_id`)
                LEFT JOIN `".DB_PREFIX."country` c ON (a.`country_id` = c.`country_id`)
                LEFT JOIN `".DB_PREFIX."zone` s ON (s.`zone_id` = a.`zone_id`)
                LEFT JOIN `".DB_PREFIX."erp_address_merge` erp ON (erp.`opencart_address_id` = a.`address_id` AND erp.`customer_id` = a.`customer_id`) WHERE cu.customer_id='".$id_customer."' $add ")->rows;

            $this->load->model('catalog/erp_country');
            $this->load->model('catalog/erp_state');
            foreach ($data as $address_data) {
                $search_address = $this->search_address($address_data['address_id']);
                if ($search_address[0] <= 0) {
                    $erp_country_id = false;
                    $erp_state_id   = false;
                    if ($address_data['country_id']) {
                        $country_iso_code = $this->model_catalog_erp_country->get_iso($address_data['country_id']);
                        $country_name     = $this->model_catalog_erp_country->get_country_name($address_data['country_id']);
                        $erp_country_id   = $this->model_catalog_erp_country->get_country($country_iso_code, $country_name, $userId, $client, $db, $pwd);
                    }
                    if ($address_data['zone_id']) {
                        $state_dtls   = $this->model_catalog_erp_state->get_state_dtls($address_data['zone_id']);
                        $erp_state_id = $this->model_catalog_erp_state->check_state($userId, $client, $state_dtls['code'], $state_dtls['name'], $state_dtls['country_id'], $db, $pwd);
                    }
                    $name = $address_data['firstname'].' '.$address_data['lastname'];
                    $address1 = $address_data['address_1'];
                    $address2 = $address_data['address_2'];
                    $city = $address_data['city'];
                    $email = $address_data['email'];
                    $context = array(
                        'opencart' => new xmlrpcval('opencart', "string")
                    );
                    $key = array(
                        'parent_id' => new xmlrpcval($erp_cust_id, "int"),
                        'name' => new xmlrpcval($name, "string"),
                        'email' => new xmlrpcval($email, "string"),
                        'street' => new xmlrpcval($address1, "string"),
                        'street2' => new xmlrpcval($address2, "string"),
                        'phone' => new xmlrpcval($address_data['telephone'], "string"),
                        'zip' => new xmlrpcval($address_data['postcode'], "string"),
                        'city' => new xmlrpcval($city, "string"),
                        'country_id' => new xmlrpcval($erp_country_id, "int"),
                        'state_id' => new xmlrpcval($erp_state_id, "int"),
                        'customer' => new xmlrpcval(false, "boolean"),
                        'use_parent_address' => new xmlrpcval(false, "boolean")
                    );
                    if ($search_address[0] == 0) {
                        $msg_ser = new xmlrpcmsg('execute');
                        $msg_ser->addParam(new xmlrpcval($db, "string"));
                        $msg_ser->addParam(new xmlrpcval($userId, "int"));
                        $msg_ser->addParam(new xmlrpcval($pwd , "string"));
                        $msg_ser->addParam(new xmlrpcval("res.partner", "string"));
                        $msg_ser->addParam(new xmlrpcval("create", "string"));
                        $msg_ser->addParam(new xmlrpcval($key, "struct"));
                        $msg_ser->addParam(new xmlrpcval($context, "struct"));
                        $resp = $client->send($msg_ser);
                        if (!$resp->faultCode()) {
                            $val = $resp->value()->me;
                            $this->addto_address_merge($address_data['address_id'], $val['int'], $address_data['customer_id']);
                            $add_to_openerp = $this->addto_openerp_merge($val['int'], $address_data['customer_id'], $address_data['address_id'], $userId, $client, $db, $pwd);
                            if ($add_to_openerp['is_error'] == 1) {
                                $error =  'Error Address not adding - specific address'. $address_data['address_id'].' ,';
                            }else{
                                return $val['int'];
                            }
                        } else{
                          $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
                        }
                    }
                    if ($search_address[0] == -1) {
                        $update = $this->update_address($key, $address_data['address_id'], $search_address[1], $userId, $client, $db, $pwd);
                        if ($update['value'] != True) {
                            $error =  'Error Address not updating - specific address'. $address_data['address_id'].' ,';
                        }else{
                            return $search_address[1];
                        }
                    }
                }
            }
        }
    }

    public function getErpCustomerArray($userId, $client, $db, $pwd) {
      $log = new oob_log();
        $Customer = array();
        $key     = array();
        $key = array(new xmlrpcval(
                        array(  new xmlrpcval('is_company' , "string"),
                                new xmlrpcval('=',"string"),
                                new xmlrpcval(true,"boolean")),"array"),
                    new xmlrpcval(
                        array(  new xmlrpcval('customer' , "string"),
                                new xmlrpcval('=',"string"),
                                new xmlrpcval(true,"boolean")),"array"),
                );
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("res.partner", "string"));
        $msg_ser->addParam(new xmlrpcval("search", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "array"));
        $resp0    = $client->send($msg_ser);
        if ($resp0->faultCode()) {
            array_push($Customer, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
            return $Customer;
        }else{
            $val    = $resp0->value()->me['array'];
            $key1 = array(new xmlrpcval('id','int') , new xmlrpcval('name', 'string'));
            $msg_ser1 = new xmlrpcmsg('execute');
            $msg_ser1->addParam(new xmlrpcval($db, "string"));
            $msg_ser1->addParam(new xmlrpcval($userId, "int"));
            $msg_ser1->addParam(new xmlrpcval($pwd, "string"));
            $msg_ser1->addParam(new xmlrpcval("res.partner", "string"));
            $msg_ser1->addParam(new xmlrpcval("read", "string"));
            $msg_ser1->addParam(new xmlrpcval($val, "array"));
            $msg_ser1->addParam(new xmlrpcval($key1, "array"));
            $resp1   = $client->send($msg_ser1);
            if ($resp1->faultCode()) {
              $log->logMessage(__FILE__,__LINE__,$resp1->raw_data,'CRITICAL: ');
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Customer, array('label' => $msg, 'id' => ''));
                return $Customer;
            }else {
                $value_array=$resp1->value()->scalarval();
                $count = count($value_array);

                for($x=0;$x<$count;$x++){
                   $id = $value_array[$x]->me['struct']['id']->me['int'];
                   $name = $value_array[$x]->me['struct']['name']->me['string'];
                   array_push($Customer,
                    array(
                            'id' => $id,
                            'name'=>$name )
                    );
                }
            }
        }
        return $Customer;
    }
}
?>
