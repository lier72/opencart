<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpOrder extends Model {

    public function check_spec_order($order_id, $userId, $client, $db, $pwd, $cart_user,$wkproducttype){
        $check_order_isSync = $this->db->query("SELECT id FROM ".DB_PREFIX."erp_order_merge WHERE opencart_order_id = '".$order_id."'")->row;
        $log = new oob_log();

        if($check_order_isSync AND isset($check_order_isSync['id']))
            return;

        //load opencart order model and get order info
        $this->load->model('sale/order');
        $This_order = $this->model_sale_order->getOrder($order_id);

        //get currency id from orderdata
        $currency_id = $This_order['currency_id'];

        //load currency model and check specific
        $this->load->model('catalog/erp_currency');
        $pricelist_id = $this->model_catalog_erp_currency->check_specific_currency($currency_id, $userId, $client , $db, $pwd);

        if(!$pricelist_id){
            return array(0,0,"Odoo pricelist id not found");
        }

        //it will return an array of (partner_id, partner_invoice_id, partner_shipping_id)
        $erpAddressArray = $this->getErpOrderAddresses($This_order, $userId, $client, $db, $pwd, $cart_user);
        if(count(array_filter($erpAddressArray)) == 3 AND $erpAddressArray[1] > 0 AND $erpAddressArray[2] > 0){
            $odoo_order_id = 0;
            $partner_id = $erpAddressArray[0];
            $partner_invoice_id = $erpAddressArray[1];
            $partner_shipping_id = $erpAddressArray[2];
            $erp_carrier_id = false;
            $odoo_order_name = '';
            //get carrier code for erp is shipping exists
            if($This_order['shipping_code']){
                $this->load->model('catalog/erp_carrier');
                $erp_carrier = $this->model_catalog_erp_carrier->check_specific_carrier($This_order['shipping_code'], $userId, $client, $db, $pwd);
                $erp_carrier_id  = $erp_carrier['erp_id'];
            }
            $order_array =  array(
                        'partner_id'=>new xmlrpcval($partner_id,"int"),
                        'partner_invoice_id'=>new xmlrpcval($partner_invoice_id,"int"),
                        'partner_shipping_id'=>new xmlrpcval($partner_shipping_id,"int"),
                        'pricelist_id'=>new xmlrpcval($pricelist_id,"int"),
                        'date_order'=>new xmlrpcval($This_order['date_added'],"string"),
                        'ecommerce_order_id' => new xmlrpcval($order_id, "int") ,
                        'carrier_id' => new xmlrpcval($erp_carrier_id, "int"),
                        'ecommerce_channel'=>new xmlrpcval('opencart', "string"),
                        'origin'=>new xmlrpcval($order_id,"string")
                    );

            $msg = new xmlrpcmsg('execute');
            $msg->addParam(new xmlrpcval($db, "string"));
            $msg->addParam(new xmlrpcval($userId, "int"));
            $msg->addParam(new xmlrpcval($pwd, "string"));
            $msg->addParam(new xmlrpcval("wk.skeleton", "string"));
            $msg->addParam(new xmlrpcval("create_order", "string"));
            $msg->addParam(new xmlrpcval($order_array, "struct"));
            // $msg->addParam(new xmlrpcval($context, "struct"));
            $resp = $client->send($msg);
            if($resp->faultcode()){
                $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
                return 'error while syncing product!!!';
            }else{
                $odoo_order_id = $resp->value()->me["struct"]["order_id"]->me["int"];
                $odoo_order_name = $resp->value()->me["struct"]["order_name"]->me["string"];
            }

            //make shipping ,payment, store array for get tax
            $shipping_address = array(
                'country_id' => $This_order['shipping_country_id'],
                'zone_id'    => $This_order['shipping_zone_id']
            );

            $payment_address = array(
                'country_id' => $This_order['payment_country_id'],
                'zone_id'    => $This_order['payment_zone_id']
            );

            $store_address = array(
                'country_id' => $this->config->get('config_country_id'),
                'zone_id'    => $this->config->get('config_zone_id')
            );

            $line_ids = '';
            $This_order_products = $this->model_sale_order->getOrderProducts($order_id);
            foreach ($This_order_products as $key => $value) {
                $This_order_products[$key]['options'] = $this->model_sale_order->getOrderOptions($order_id,$value['order_product_id']);
            }
            $currency_code = $This_order['currency_code'];
            $config_currency_code = $this->config->get('config_currency');
            foreach($This_order_products as $itm){
                $product_option_id = 0;
                $erp_tax_array = array();
                $item_desc = $itm['name'];

                if ($itm['options'])
                {
                    $product_option_id = $itm['options'][0]['product_option_value_id'];
                }
                $ItemBasePrice = $this->currency->convert($itm['price'], $config_currency_code, $currency_code);

                $context = array(
                            'db'  => $db,
                            'pwd' => $pwd,
                            'cart_user'  => $cart_user,
                            'lang_id' => $this->config->get("config_language_id"),
                            'wkproducttype'=>$wkproducttype,
                        );
                //load product model and get product info
                $this->load->model('catalog/erp_product');

                //load tax model and get tax info
                $this->load->model('catalog/erp_tax');

                $product_response = $this->model_catalog_erp_product->check_specific_product($itm['product_id'] , $product_option_id, $userId, $client, $context);

                if(!$product_response){
                    return 'error product specific sync';
                }
                $erp_product_id = $product_response['erp_id'];
                //add product options details in product description
                foreach($itm['options'] as $value){
                    $item_desc = $item_desc .' name - ' . $value['name'] .' , value - '. $value['value'];
                }

                //get tax from function
                $tax_class_id = $this->db->query("SELECT tax_class_id FROM ".DB_PREFIX."product WHERE product_id = '".$itm['product_id']."'")->row;

                if($tax_class_id)
                $tax_per_product = $this->getRates($ItemBasePrice, $tax_class_id['tax_class_id'], $This_order['customer_group_id'],$shipping_address,$payment_address,$store_address);

                foreach ($tax_per_product as $key => $value) {
                    $erp_tax_id = $this->model_catalog_erp_tax->check_specific_tax($key, $client, $userId, $db, $pwd);
                    if($erp_tax_id)
                        $erp_tax_array[] = new xmlrpcval($erp_tax_id,"int");
                }

                $context_line = array(
        				 'ecommerce'=>new xmlrpcval("opencart", "string")
        				);

                $Order_line_array =  array(
                        'order_id'=>new xmlrpcval($odoo_order_id,"int"),
                        'product_id'=>new xmlrpcval($erp_product_id,"int"),
                        'price_unit'=>new xmlrpcval($ItemBasePrice,"string"),
                        'product_uom_qty'=>new xmlrpcval($itm['quantity'],"string"),
                        'name'=>new xmlrpcval($item_desc,"string"),
                        'tax_id'=>new xmlrpcval($erp_tax_array, "array"),
                    );

                $line_create = new xmlrpcmsg('execute');
                $line_create->addParam(new xmlrpcval($db, "string"));
                $line_create->addParam(new xmlrpcval($userId, "int"));
                $line_create->addParam(new xmlrpcval($pwd, "string"));
                $line_create->addParam(new xmlrpcval("wk.skeleton", "string"));
                $line_create->addParam(new xmlrpcval("create_sale_order_line", "string"));
                $line_create->addParam(new xmlrpcval($Order_line_array, "struct"));
                $line_create->addParam(new xmlrpcval($context_line, "struct"));
                $line_resp = $client->send($line_create);
                if ($line_resp->faultCode()){
                    $log->logMessage(__FILE__,__LINE__,$line_resp->raw_data,'CRITICAL: ');
                    continue;
                }
                $line_id = $line_resp->value()->me["struct"]["order_line_id"]->me["int"];
                $line_ids .= $line_id.",";
            }

            /******************** For Voucher ******************/
            $voucher_code = $this->model_sale_order->getOrderVouchers($order_id);
            if ($voucher_code){
                if(!$voucher_code['code'])
                    $code = "Discount";
                else
                    $code = $code['code'];
                $code = html_entity_decode($code);
                $voucher_line_array =  array(
                            'order_id'=>new xmlrpcval($odoo_order_id,"int"),
                            'name'=>new xmlrpcval('Discount',"string"),
                            'price_unit'=>new xmlrpcval($voucher_code['amount'],"double"),
                            'description'=>new xmlrpcval($code,"string"),
                            'ecommerce_channel'=>new xmlrpcval('opencart',"string")
                    );
                $msg = new xmlrpcmsg('execute');
                $msg->addParam(new xmlrpcval($db, "string"));
                $msg->addParam(new xmlrpcval($userId, "int"));
                $msg->addParam(new xmlrpcval($pwd, "string"));
                $msg->addParam(new xmlrpcval("wk.skeleton", "string"));
                $msg->addParam(new xmlrpcval("create_order_shipping_and_voucher_line", "string"));
                $msg->addParam(new xmlrpcval($voucher_line_array, "struct"));
                $msg->addParam(new xmlrpcval($context_line, "struct"));
                $resp = $client->send($msg);
                if ($resp->faultCode()){
                    $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
                    $error = "Order Id ".$order_id." Error While syncing voucher!!!";
                }else{
                    $voucher_line_id = $resp->value()->me["struct"]["order_line_id"]->me["int"];
                    $line_ids .= $voucher_line_id.",";
                }
            }

            /******************** For Coupon ******************/
			// New Code
			$coupon_info = $this->db->query("SELECT `title`, `value` FROM ".DB_PREFIX."order_total WHERE code='coupon' and order_id = '".$order_id."'")->row;
			if ($coupon_info){
				$code = html_entity_decode($coupon_info['title']);
				$coupon_line_array =  array(
								'order_id'=>new xmlrpcval($odoo_order_id,"int"),
								'name'=>new xmlrpcval('Voucher',"string"),
								'type' =>  new xmlrpcval('Voucher', "string"),
								'description'=>new xmlrpcval($code,"string"),
								'price_unit'=> new xmlrpcval($coupon_info['value'],"double"),
								'ecommerce_channel'=>new xmlrpcval('opencart',"string")
							);
				$msg = new xmlrpcmsg('execute');
				$msg->addParam(new xmlrpcval($db, "string"));
				$msg->addParam(new xmlrpcval($userId, "int"));
				$msg->addParam(new xmlrpcval($pwd, "string"));
				$msg->addParam(new xmlrpcval("wk.skeleton", "string"));
				$msg->addParam(new xmlrpcval("create_order_shipping_and_voucher_line", "string"));
				$msg->addParam(new xmlrpcval($coupon_line_array, "struct"));
				$msg->addParam(new xmlrpcval($context_line, "struct"));
				$resp = $client->send($msg);
				if ($resp->faultCode()){
					$log = new oob_log();
					$log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
					$error = "Order Id ".$order_id." Error While syncing coupon info";
				}else{
					$coupon_line_id = $resp->value()->me["struct"]["order_line_id"]->me["int"];
				}
			}

            //for shipping and tax
            $order_total = $this->model_sale_order->getOrderTotals($order_id);

            /******************** For Shipping ******************/
            if ($This_order['shipping_firstname']){
                $shipping_cost = 0.0;
                $erp_tax_array = array();
                $shipping_description = html_entity_decode($This_order['shipping_method']);
                if($order_total){
                    foreach($order_total as $value){
                        if($value['code']=='shipping'){
                            $order_shipping_title = $value['title'];
                            $shipping_cost = $this->currency->convert($value['value'], $config_currency_code, $currency_code);
                        }
                    }
                }
                $shipping_code_array = explode('.',$This_order['shipping_code']);
                $shipping_code = $shipping_code_array[0];
                $shipping_tax_class_id = $this->config->get('shipping_'.$shipping_code.'_tax_class_id');
                if($shipping_tax_class_id){
                    $shipping_tax = $this->getRates($shipping_cost, $shipping_tax_class_id, $This_order['customer_group_id'], $shipping_address, $payment_address, $store_address);
                    foreach ($shipping_tax as $key => $value) {
                       $erp_tax_id = $this->model_catalog_erp_tax->check_specific_tax($key, $client, $userId, $db, $pwd);
                        if($erp_tax_id)
                            $erp_tax_array[] = new xmlrpcval($erp_tax_id,"int");
                    }
                }
                $shipping_line_array = array(
                            'order_id'=>new xmlrpcval($odoo_order_id,"int"),
                            'name'=>new xmlrpcval('Shipping',"string"),
                            'type' =>  new xmlrpcval('Shipping', "string"),
                            'is_delivery' =>  new xmlrpcval(true,"boolean"),
                            'price_unit'=>new xmlrpcval($shipping_cost,"double"),
                            'tax_id'=>new xmlrpcval($erp_tax_array, "array"),
                            'description'=>new xmlrpcval($shipping_description,"string"),
                            'ecommerce_channel'=>new xmlrpcval('opencart',"string")
                        );
                $context = array(
        						'ecommerce'  => new xmlrpcval('opencart', "string"),
        						'type'       =>  new xmlrpcval('shipping', "string"),
        						'carrier_id' => new xmlrpcval($erp_carrier_id, "int"),
        				);
                $msg = new xmlrpcmsg('execute');
                $msg->addParam(new xmlrpcval($db, "string"));
                $msg->addParam(new xmlrpcval($userId, "int"));
                $msg->addParam(new xmlrpcval($pwd, "string"));
                $msg->addParam(new xmlrpcval("wk.skeleton", "string"));
                $msg->addParam(new xmlrpcval("create_order_shipping_and_voucher_line", "string"));
                $msg->addParam(new xmlrpcval($shipping_line_array, "struct"));
                $msg->addParam(new xmlrpcval($context, "struct"));
                $resp = $client->send($msg);
                if ($resp->faultCode()){
                    $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
                    $error = "Order Id ".$order_id." Error While syncing shipping";
                }else{
                    $shipping_line_id = $resp->value()->me["struct"]["order_line_id"]->me["int"];
                    $line_ids .= $shipping_line_id.",";
                }
            }

            $this->db->query("INSERT INTO ".DB_PREFIX."erp_order_merge SET erp_order_id='$odoo_order_id',opencart_order_id='$order_id',created_by='$cart_user',customer_id = '".$partner_id."'");

            $this->addErpOrderHistory($odoo_order_id, $order_id, $partner_id, $This_order['order_status_id'], $userId, $client, $db, $pwd);


            return array($order_id, $partner_id);
        }
        return array(0,0,"error occured during creating an Odoo customer.".''.$erpAddressArray[0]);
    }

    public function getErpOrderAddresses($This_order, $userId, $client, $db, $pwd, $cart_user = 'cart user'){
        $partner_id = 0;
        $partner_shipping_id = 0;
        $partner_invoice_id = 0;

        $erp_country_id = false;
        $erp_state_id   = false;


        $s = $p = array();

        if ($This_order['shipping_country_id']) {

            $s = array(
                'firstname' => $This_order['shipping_firstname'],
                'lastname' => $This_order['shipping_lastname'],
                'address_1' => $This_order['shipping_address_1'],
                'address_2' => $This_order['shipping_address_2'],
                'email' => $This_order['email'],
                'telephone' => $This_order['telephone'],
                'zip' => $This_order['shipping_postcode'],
                'city' => $This_order['shipping_city'],
                'country_id' => $This_order['shipping_country_id'],
                'state_id' => $This_order['shipping_zone_id'],
                'customer_id' => $This_order['customer_id'],
            );
        }

        if ($This_order['payment_zone_id']) {

            $p = array(
                        'firstname' => $This_order['payment_firstname'],
                        'lastname' => $This_order['payment_lastname'],
                        'address_1' => $This_order['payment_address_1'],
                        'address_2' => $This_order['payment_address_2'],
                        'email' => $This_order['email'],
                        'telephone' => $This_order['telephone'],
                        'zip' => $This_order['payment_postcode'],
                        'city' => $This_order['payment_city'],
                        'country_id' => $This_order['payment_country_id'],
                        'state_id' => $This_order['payment_zone_id'],
                        'customer_id' => $This_order['customer_id'],
                   );
        }

        //if shipping is not added than shipping = payment
        if(!$s)
            $s = $p;
        // if customer is guest
        $isDifferent = $this->checkAddresses($s,$p);

        if(!$This_order['customer_id']){
            $customer_arr =  array(
                    'is_company'=>new xmlrpcval(true,"boolean"),
                    'name'=>new xmlrpcval(html_entity_decode($This_order['firstname'].''.$This_order['lastname']),"string"),
                    'email'=>new xmlrpcval(html_entity_decode($This_order['email']),"string"),
                );

            $erp_customer_id = $this->AddGuestCustomerToErp($customer_arr, $userId, $client, $db, $pwd, $cart_user);

            if(isset($erp_customer_id['error_message'])){
                return array($erp_customer_id['error_message']);
            }

            $partner_id = $erp_customer_id['erp_id'];

            if($isDifferent == true){
                $partner_shipping_id = $this->createErpAddress($s, $partner_id, $userId, $client, $db, $pwd, $cart_user);
            }
            $partner_invoice_id = $this->createErpAddress($p, $partner_id, $userId, $client, $db, $pwd, $cart_user );
        }
        // if customer is login
        if($This_order['customer_id'] > 0){

            //load opencart order model and get order info
            $this->load->model('catalog/erp_customer');

            $partner_id = $this->model_catalog_erp_customer->check_specific_customer($This_order['customer_id'], $client, $userId, $cart_user='Front End', $db, $pwd);
            if(!$partner_id)
                return array('Error customer chk specific');

			$isDifferent = $this->checkAddresses($s, $p);
			if($isDifferent == true){
				$shipping_address_id = $this->getAddressId($s);
				if($shipping_address_id){
					$partner_shipping_id = $this->model_catalog_erp_customer->check_specific_address($shipping_address_id, $This_order['customer_id'], $userId, $client, $db, $pwd);
				}
				//Added Feature
				else
					$partner_shipping_id = $this->createErpAddress($s, $partner_id, $userId, $client, $db, $pwd, $cart_user);
			}
			$invoice_address_id = $this->getAddressId($p);
			if($invoice_address_id){
				$partner_invoice_id = $this->model_catalog_erp_customer->check_specific_address($invoice_address_id, $This_order['customer_id'], $userId, $client, $db, $pwd);
			}
			//Added feature
			else
				$partner_invoice_id = $this->createErpAddress($p, $partner_id, $userId, $client, $db, $pwd, $cart_user);
		}

        if($partner_invoice_id > 0 AND $partner_shipping_id > 0)
            return array($partner_id, $partner_invoice_id, $partner_shipping_id);
        else
            return array($partner_id, $partner_invoice_id, $partner_invoice_id);
    }

    public function checkAddresses($shipping,$payment){
        $flag = false;

        if($shipping['state_id'] and $payment['state_id'] ){
            $s = '';
            $b = '';

            if($shipping[$s.'firstname'] != $payment[$b.'firstname'])
                $flag = true;
            if($shipping[$s.'lastname'] != $payment[$b.'lastname'])
                $flag = true;
            if($shipping[$s.'address_1'] != $payment[$b.'address_1'])
                $flag = true;
            if($shipping[$s.'state_id'] != $payment[$b.'state_id'])
                $flag = true;
            if($shipping[$s.'country_id'] != $payment[$b.'country_id'])
                $flag = true;
            if($shipping[$s.'city'] != $payment[$b.'city'])
                $flag = true;
            if($shipping[$s.'zip'] != $payment[$b.'zip'])
                $flag = true;
        }
        return $flag;
    }

    public function getAddressId($data){
        $address_id = $this->db->query("SELECT address_id FROM ".DB_PREFIX."address WHERE firstname = '".$this->db->escape($data['firstname'])."' AND lastname = '". $this->db->escape($data['lastname'])."' AND address_1 = '".$this->db->escape($data['address_1'])."' AND address_2 = '".$this->db->escape($data['address_2'])."' AND  postcode = '".$data['zip']."' AND city = '".$this->db->escape($data['city'])."' AND country_id = '".$data['country_id']."' AND zone_id = '".$data['state_id']."' AND customer_id = '".$data['customer_id']."'")->row;
        if($address_id)
            return $address_id['address_id'];
        else
            false;
    }

    //used to add guest customer to erp (nik added)
    public function AddGuestCustomerToErp($key, $userId, $client, $db, $pwd, $cart_user){
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
            if(isset($val['int']) AND $val['int']){
                return array(
                    'erp_id' => $val['int'],
                );
            }else{
                return array(
                    'error_message' => 'No epr_id Returned',
                );
            }
        }else{
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            $error_message = $resp->faultString();
            return array(
                'error_message' => $error_message,
            );
        }
    }

    public function createErpAddress($data, $partner_id, $userId, $client, $db, $pwd, $cart_user ){

         // load another model functions

        $this->load->model('catalog/erp_country');
        // load another model functions
        $this->load->model('catalog/erp_state');



        $context = array(
                    'opencart' => new xmlrpcval('opencart', "string")
                );

        $country_iso_code = $this->model_catalog_erp_country->get_iso($data['country_id']);
        $country_name     = $this->model_catalog_erp_country->get_country_name($data['country_id']);
        $erp_country_id   = $this->model_catalog_erp_country->get_country($country_iso_code, $country_name, $userId, $client, $db, $pwd);

        $state_dtls   = $this->model_catalog_erp_state->get_state_dtls($data['state_id']);
        $erp_state_id = $this->model_catalog_erp_state->check_state($userId, $client, $state_dtls['code'], $state_dtls['name'], $state_dtls['country_id'], $db, $pwd);

        $key = array(
                    'parent_id' => new xmlrpcval($partner_id, "int"),
                    'name' => new xmlrpcval($data['firstname'], "string"),
                    'email' => new xmlrpcval($data['email'], "string"),
                    'street' => new xmlrpcval($data['address_1'], "string"),
                    'street2' => new xmlrpcval($data['address_2'], "string"),
                    'phone' => new xmlrpcval($data['telephone'], "int"),
                    'zip' => new xmlrpcval($data['zip'], "string"),
                    'city' => new xmlrpcval($data['city'], "string"),
                    'country_id' => new xmlrpcval($erp_country_id, "int"),
                    'state_id' => new xmlrpcval($erp_state_id, "int"),
                    'customer' => new xmlrpcval(false, "boolean"),
                    'use_parent_address' => new xmlrpcval(false, "boolean")
                );
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
            return $val['int'];
        } else {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            return $resp->faultString();
        }
    }

    public function getRates($value, $tax_class_id, $customer_group_id,$shipping_address,$payment_address,$store_address) {
        $tax_rates = array();

        if (!$customer_group_id) {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }

        if ($shipping_address) {
            $tax_query = $this->db->query("SELECT tr2.tax_rate_id, tr2.name, tr2.rate, tr2.type, tr1.priority FROM " . DB_PREFIX . "tax_rule tr1 LEFT JOIN " . DB_PREFIX . "tax_rate tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id) INNER JOIN " . DB_PREFIX . "tax_rate_to_customer_group tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id) LEFT JOIN " . DB_PREFIX . "zone_to_geo_zone z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id) LEFT JOIN " . DB_PREFIX . "geo_zone gz ON (tr2.geo_zone_id = gz.geo_zone_id) WHERE tr1.tax_class_id = '" . (int)$tax_class_id . "' AND tr1.based = 'shipping' AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "' AND z2gz.country_id = '" . (int)$shipping_address['country_id'] . "' AND (z2gz.zone_id = '0' OR z2gz.zone_id = '" . (int)$shipping_address['zone_id'] . "') ORDER BY tr1.priority ASC");

            foreach ($tax_query->rows as $result) {
                $tax_rates[$result['tax_rate_id']] = array(
                    'tax_rate_id' => $result['tax_rate_id'],
                    'name'        => $result['name'],
                    'rate'        => $result['rate'],
                    'type'        => $result['type'],
                    'priority'    => $result['priority']
                );
            }
        }

        if ($payment_address) {
            $tax_query = $this->db->query("SELECT tr2.tax_rate_id, tr2.name, tr2.rate, tr2.type, tr1.priority FROM " . DB_PREFIX . "tax_rule tr1 LEFT JOIN " . DB_PREFIX . "tax_rate tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id) INNER JOIN " . DB_PREFIX . "tax_rate_to_customer_group tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id) LEFT JOIN " . DB_PREFIX . "zone_to_geo_zone z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id) LEFT JOIN " . DB_PREFIX . "geo_zone gz ON (tr2.geo_zone_id = gz.geo_zone_id) WHERE tr1.tax_class_id = '" . (int)$tax_class_id . "' AND tr1.based = 'payment' AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "' AND z2gz.country_id = '" . (int)$payment_address['country_id'] . "' AND (z2gz.zone_id = '0' OR z2gz.zone_id = '" . (int)$payment_address['zone_id'] . "') ORDER BY tr1.priority ASC");

            foreach ($tax_query->rows as $result) {
                $tax_rates[$result['tax_rate_id']] = array(
                    'tax_rate_id' => $result['tax_rate_id'],
                    'name'        => $result['name'],
                    'rate'        => $result['rate'],
                    'type'        => $result['type'],
                    'priority'    => $result['priority']
                );
            }
        }

        if ($store_address) {
            $tax_query = $this->db->query("SELECT tr2.tax_rate_id, tr2.name, tr2.rate, tr2.type, tr1.priority FROM " . DB_PREFIX . "tax_rule tr1 LEFT JOIN " . DB_PREFIX . "tax_rate tr2 ON (tr1.tax_rate_id = tr2.tax_rate_id) INNER JOIN " . DB_PREFIX . "tax_rate_to_customer_group tr2cg ON (tr2.tax_rate_id = tr2cg.tax_rate_id) LEFT JOIN " . DB_PREFIX . "zone_to_geo_zone z2gz ON (tr2.geo_zone_id = z2gz.geo_zone_id) LEFT JOIN " . DB_PREFIX . "geo_zone gz ON (tr2.geo_zone_id = gz.geo_zone_id) WHERE tr1.tax_class_id = '" . (int)$tax_class_id . "' AND tr1.based = 'store' AND tr2cg.customer_group_id = '" . (int)$customer_group_id . "' AND z2gz.country_id = '" . (int)$store_address['country_id'] . "' AND (z2gz.zone_id = '0' OR z2gz.zone_id = '" . (int)$store_address['zone_id'] . "') ORDER BY tr1.priority ASC");

            foreach ($tax_query->rows as $result) {
                $tax_rates[$result['tax_rate_id']] = array(
                    'tax_rate_id' => $result['tax_rate_id'],
                    'name'        => $result['name'],
                    'rate'        => $result['rate'],
                    'type'        => $result['type'],
                    'priority'    => $result['priority']
                );
            }
        }

        $tax_rate_data = array();

        foreach ($tax_rates as $tax_rate) {
            if (isset($tax_rate_data[$tax_rate['tax_rate_id']])) {
                $amount = $tax_rate_data[$tax_rate['tax_rate_id']]['amount'];
            } else {
                $amount = 0;
            }

            if ($tax_rate['type'] == 'F') {
                $amount += $tax_rate['rate'];
            } elseif ($tax_rate['type'] == 'P') {
                $amount += ($value / 100 * $tax_rate['rate']);
            }

            $tax_rate_data[$tax_rate['tax_rate_id']] = array(
                'tax_rate_id' => $tax_rate['tax_rate_id'],
                'name'        => $tax_rate['name'],
                'rate'        => $tax_rate['rate'],
                'type'        => $tax_rate['type'],
                'amount'      => $amount
            );
        }

        return $tax_rate_data;
    }


    public function addErpOrderHistory($erp_order_id, $oc_order_id, $erp_customer_id, $oc_status_id, $userId, $client, $db, $pwd){

      $status_details = $this->db->query("SELECT erp_order_status_id from `" . DB_PREFIX . "erp_order_status_merge` WHERE opencart_order_status_id = '$oc_status_id' ")->row;

      if($status_details){
        $erp_status = $status_details['erp_order_status_id'];
        if($erp_status == 'manual'){
          $this->confirmOdooOrder($erp_order_id, $userId, $client, $db, $pwd);
        }
        if($erp_status == 'cancel'){
          $this->cancelOdooOrder($erp_order_id, $userId, $client, $db, $pwd);
        }
        if($erp_status == 'delivered'){
          $this->deliverOdooOrder($erp_order_id, $userId, $client, $db, $pwd);
        }
        if($erp_status == 'paid'){
          $this->load->model('sale/order');
          $This_order = $this->model_sale_order->getOrder($order_id);
          $this->load->model('catalog/erp_payment');
          $erp_payment_id = $this->model_catalog_erp_payment->check_specific_payment_method($This_order['payment_method'], $userId, $client, $db, $pwd);
          $this->makepaidOdooOrder($erp_order_id, $erp_payment_id, $userId, $client, $db, $pwd);
        }
        if($erp_status == 'invoiced'){
          $this->doErpOrderInvoice($erp_order_id, $userId, $client, $db, $pwd);
        }
        $this->add_message_to_order($oc_order_id, $erp_order_id, $oc_status_id, $userId, $client, $db, $pwd);
        return true;
      }
      return;
    }

    // To add status message in sale order in Odoo
    public function add_message_to_order($oc_order_id, $erp_order_id, $oc_status_id, $userId, $client, $db, $pwd){
        $message = $this->db->query("SELECT comment from `" . DB_PREFIX . "order_history` WHERE order_id = '$oc_order_id' and order_status_id='$oc_status_id' ")->row;
        if ($message['comment']){
            $order_id = array(
                    new xmlrpcval($erp_order_id, 'int')
                );

            $msg         = new xmlrpcmsg('execute');
            $msg->addParam(new xmlrpcval($db, "string"));
            $msg->addParam(new xmlrpcval($userId, "int"));
            $msg->addParam(new xmlrpcval($pwd, "string"));
            $msg->addParam(new xmlrpcval("sale.order", "string"));
            $msg->addParam(new xmlrpcval("message_post", "string"));
            $msg->addParam(new xmlrpcval($order_id, "array"));
            $msg->addParam(new xmlrpcval($message['comment'], "string"));
            $msg->addParam(new xmlrpcval('Order Status Update:', "string"));
            $msg->addParam(new xmlrpcval("comment", "string"));
            $response  = $client->send($msg);
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$response->raw_data,'CRITICAL: ');
        }
    }


    public function confirmOdooOrder($erp_order_id, $userId, $client, $db, $pwd){
  		$method = new xmlrpcmsg('execute');
  		$method->addParam(new xmlrpcval($db, "string"));
  		$method->addParam(new xmlrpcval($userId, "int"));
  		$method->addParam(new xmlrpcval($pwd, "string"));
  		$method->addParam(new xmlrpcval("wk.skeleton", "string"));
  		$method->addParam(new xmlrpcval("confirm_odoo_order", "string"));
  		$method->addParam(new xmlrpcval($erp_order_id, "int"));
  		$method_resp = $client->send($method);
  		$log = new oob_log();
  		$log->logMessage(__FILE__,__LINE__,$method_resp->raw_data,'CRITICAL: ');
  		}


  		public function deliverOdooOrder($erp_order_id, $userId, $client, $db, $pwd){
  			$context = array(
  				'opencart' => new xmlrpcval('opencart', "string")
  				);
  			$msg2 = new xmlrpcmsg('execute');
  			$msg2->addParam(new xmlrpcval($db, "string"));
  			$msg2->addParam(new xmlrpcval($userId, "int"));
  			$msg2->addParam(new xmlrpcval($pwd, "string"));
  			$msg2->addParam(new xmlrpcval("wk.skeleton", "string"));
  			$msg2->addParam(new xmlrpcval("set_order_shipped", "string"));
  			$msg2->addParam(new xmlrpcval($erp_order_id,"int"));
  			$msg2->addParam(new xmlrpcval($context,"struct"));
  			$msg2_resp = $client->send($msg2);
  			$log = new oob_log();
  			$log->logMessage(__FILE__,__LINE__,$msg2_resp->raw_data,'CRITICAL: ');
  		}

  		public function cancelOdooOrder($erp_order_id, $userId, $client, $db, $pwd){
  		$msg2 = new xmlrpcmsg('execute');
  		$msg2->addParam(new xmlrpcval($db, "string"));
  		$msg2->addParam(new xmlrpcval($userId, "int"));
  		$msg2->addParam(new xmlrpcval($pwd, "string"));
  		$msg2->addParam(new xmlrpcval("wk.skeleton", "string"));
  		$msg2->addParam(new xmlrpcval("set_order_cancel", "string"));
  		$msg2->addParam(new xmlrpcval($erp_order_id,"int"));
  		$msg2_resp = $client->send($msg2);
  		$log = new oob_log();
  		$log->logMessage(__FILE__,__LINE__,$msg2_resp->raw_data,'CRITICAL: ');
  		}

  		public function doErpOrderInvoice($erpOrderId, $userId, $client, $db, $pwd){
  		$context = array(
  				'opencart' => new xmlrpcval('opencart', "string")
  				);
  		$msg2 = new xmlrpcmsg('execute');
  		$msg2->addParam(new xmlrpcval($db, "string"));
  		$msg2->addParam(new xmlrpcval($userId, "int"));
  		$msg2->addParam(new xmlrpcval($pwd, "string"));
  		$msg2->addParam(new xmlrpcval("wk.skeleton", "string"));
  		$msg2->addParam(new xmlrpcval("create_order_invoice", "string"));
  		$msg2->addParam(new xmlrpcval($erpOrderId,"int"));
  		$msg2->addParam(new xmlrpcval($context,"struct"));
  		$msg2_resp = $client->send($msg2);
  		$log = new oob_log();
  		$log->logMessage(__FILE__,__LINE__,$msg2_resp->raw_data,'CRITICAL: ');
  		}


  		public function makepaidOdooOrder($erpOrderId, $erp_payment_id, $userId, $client, $db, $pwd){
  		$Order_payment_arr = array(
  					'journal_id'    =>new xmlrpcval($erp_payment_id,"int"),
  					// 'partner_id'=>new xmlrpcval($erpCustomerId,"int"),
  					'order_id'=>new xmlrpcval($erpOrderId,"int"),
  					);
  		$context = array(
  				'opencart' => new xmlrpcval('opencart', "string")
  				);

  		$order_payment = new xmlrpcmsg('execute');
  		$order_payment->addParam(new xmlrpcval($db, "string"));
  		$order_payment->addParam(new xmlrpcval($userId, "int"));
  		$order_payment->addParam(new xmlrpcval($pwd, "string"));
  		$order_payment->addParam(new xmlrpcval("wk.skeleton", "string"));
  		$order_payment->addParam(new xmlrpcval("set_order_paid", "string"));
  		$order_payment->addParam(new xmlrpcval($Order_payment_arr, "struct"));
  		$order_payment->addParam(new xmlrpcval($context, "struct"));
  		$order_pay_resp = $client->send($order_payment);
  		$log = new oob_log();
  		$log->logMessage(__FILE__,__LINE__,$msg2_resp->raw_data,'CRITICAL: ');

  		}

}
?>