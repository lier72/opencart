<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpProduct extends Model {

    public function check_all_products($userId, $client, $context){
        $db = $context['db'];
        $pwd = $context['pwd'];
        $cart_user = $context['cart_user'];
        $id_lang = $context['id_lang'];
        $is_error      = 0;
        $error_message = '';
        $ids           = '';

        $sql_query = "SELECT  p.product_id, p.sku, p.ean FROM ". DB_PREFIX ."product p WHERE p.product_id NOT IN (SELECT opencart_product_id FROM ".DB_PREFIX."erp_product_template_merge WHERE is_synch = 0) ";

        $data = $this->db->query($sql_query)->rows;

        if (count($data) == 0) {
            //Nothing to export
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                '$ids' => $ids
            );
        }

        foreach ($data as $product_data){
            $erp_attribute_value = array();
            $existing_options = array();
            $ean13 = '';
            if($product_data['ean']){
                $valid = $this->validate_ean13($product_data['ean']);
                if($valid){
                    $ean13 = $product_data['ean'];
                }
            }

            /**
             * [$check_option_value_data check for product options]
             * @var [array of options]
             */
            $check_option_value_data = $this->db->query("SELECT `product_option_value_id`,`option_id`, `option_value_id` FROM `". DB_PREFIX ."product_option_value` where product_id='".$product_data['product_id']."'")->rows;

            if ($context['wkproducttype']=='template'){
              $check_option_value_data = false;
              // $option_id = 0;
            }

            $erp_template_id = $this->check_specific_template($product_data['product_id'], $userId, $client, $context, $check_option_value_data);

            $this->load->model('catalog/erp_product_options');

            if ($check_option_value_data)  {
                foreach ($check_option_value_data as $option_data) {
                    $erp_option_id = $this->model_catalog_erp_product_options->check_specific_options($userId, $client, $option_data['option_id'], $context);
                    $erp_option_value_id = $this->model_catalog_erp_product_options->check_specific_options_value($userId, $client, $option_data['option_value_id'], $context);

                    $product_option_value_id  = $option_data['product_option_value_id'];
                    $option_detail=$this->getOptionsDetails($product_option_value_id);
                    array_push($existing_options, $product_option_value_id);
                    $product_quantity = $option_detail[0]['quantity'];
                    $extra_price = $option_detail[0]['price_prefix'].$option_detail[0]['price'];
                    $attr_value_ids = array();
                    $attr_value_ids[$erp_option_id] = new xmlrpcval(array(
                                                    new xmlrpcval($erp_option_value_id,"int"),
                                                    new xmlrpcval($extra_price,"double")
                                                    ), "array");
                    array_push($erp_attribute_value, new xmlrpcval($erp_option_value_id, 'int'));
                    $erp_attribute = $erp_option_id;


                    $context_erp = array(
                        'oc_variant' => new xmlrpcval('oc_variant', "string"),
                        'opencart' => new xmlrpcval('opencart', "string"),
                        'extra_price' => new xmlrpcval($extra_price, "string"),
                        'opencart_id' => new xmlrpcval($product_data['product_id'], "string"),
                        'oc_option_id' => new xmlrpcval($product_option_value_id, "string"),
                        'attr_value_id' => new xmlrpcval($erp_option_value_id, "int"),
                        'create_product_variant' => new xmlrpcval('create_product_variant', "string"),
                    );

                    $attr_data = array(
                        'product_tmpl_id' => new xmlrpcval($erp_template_id,"int"),
                        'default_code' => new xmlrpcval($product_data['sku'],"string"),
                        'oc_attributes' => new xmlrpcval($attr_value_ids,"struct"),
                    );
                    $status = $this->search_product($product_data['product_id'], $option_data['product_option_value_id']);

                    if ($status[0]==0){
                        if($ean13){
                            $attr_data['ean13'] = new xmlrpcval($ean13,"string");
                        }

                        $response_create = $this->create_product($userId, $client,  $attr_data ,$context, $context_erp);
                        if ($response_create['erp_id']){
                            $this->addto_product_merge($response_create['erp_id'], $product_data['product_id'], $cart_user,$product_option_value_id);
                            $this->create_product_quantity($userId, $client, $response_create['erp_id'], $product_quantity, $context);
                        }
                    }elseif ($status[0]==-1){

                        $response_update = $this->update_product($userId, $client, $status[1], $attr_data ,$context, $context_erp);
                    }else{
                        $attr_data = array();
                        $context_erp = array();
                        continue;
                    }
                }
                // Check for deleted
                $option_ids = join(", ",$existing_options );
                // $to_delete_erp_id = array();
                // $check_merge = $this->db->query("SELECT `erp_product_id` FROM `". DB_PREFIX ."erp_product_variant_merge` where opencart_product_id ='".$product_data['product_id']."' and opencart_product_option_id NOT IN (".$option_ids.")")->rows;
                // foreach ($check_merge as $merge_data) {
                //     array_push($to_delete_erp_id, new xmlrpcval($merge_data['erp_product_id'], 'int'));
                // }
                // if ($to_delete_erp_id){
                //     $delete = $this->check_created_variant($userId, $client, $erp_attribute, $erp_attribute_value,$erp_template_id,$to_delete_erp_id, $context);
                //     if ($delete['status']){
                //         $delete_map = $this->db->query("DELETE  FROM `". DB_PREFIX ."erp_product_variant_merge` where opencart_product_id ='".$product_data['product_id']."' and opencart_product_option_id NOT IN (".$option_ids.")")->rows;
                //     }
                // }
            }else{
                if ($product_data['is_synch']==1){
                    $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_variant_merge` SET `is_synch`=0 where `opencart_product_id`='" . $product_data['product_id'] . "'");
                }
                continue;
            }
        }
    }

    public function check_created_variant($userId, $client, $erp_attribute, $erp_attribute_value, $erp_template_id, $to_delete_erp_id, $context){

        $attr_data = array(
            'product_tmpl_id' => new xmlrpcval($erp_template_id, "int"),
            'erp_attribute_id' => new xmlrpcval($erp_attribute, "int"),
        );
        $msg_ser  = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($context['db'], "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg_ser->addParam(new xmlrpcval("extra.function", "string"));
        $msg_ser->addParam(new xmlrpcval("check_created_variant", "string"));
        $msg_ser->addParam(new xmlrpcval($attr_data,"struct"));
        $msg_ser->addParam(new xmlrpcval($erp_attribute_value, "array"));
        $msg_ser->addParam(new xmlrpcval($to_delete_erp_id, "array"));
        $resp = $client->send($msg_ser);
        if ($resp->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            $error_message = $resp->faultString();
            return array(
                'status' => False
            );
        } else {
            return array(
                'status' => True
            );
        }
    }


    public function create_product($userId, $client, $arrayVal, $context, $context_erp){
        $msg_ser  = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($context['db'], "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg_ser->addParam(new xmlrpcval("product.product", "string"));
        $msg_ser->addParam(new xmlrpcval("create", "string"));
        $msg_ser->addParam(new xmlrpcval($arrayVal,"struct"));
        $msg_ser->addParam(new xmlrpcval($context_erp, "struct"));
        $resp = $client->send($msg_ser);
        if ($resp->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            $error_message = $resp->faultString();
            return array(
                'error_message' => $error_message,
                'erp_id' => -1
            );
        } else {
            $val    = $resp->value()->me;
            $erp_id = $val['int'];
            return array(
                'erp_id' => $erp_id
            );
        }
    }

    // Code for update records
    public function update_product($userId, $client, $erp_id, $arrayVal, $context, $context_erp){
        $erp_product_key = array(
            new xmlrpcval($erp_id, 'int')
        );

        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($context['db'], "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg_ser->addParam(new xmlrpcval("product.product", "string"));
        $msg_ser->addParam(new xmlrpcval("write", "string"));
        $msg_ser->addParam(new xmlrpcval($erp_product_key, "array"));
        $msg_ser->addParam(new xmlrpcval($arrayVal, "struct"));
        $msg_ser->addParam(new xmlrpcval($context_erp, "struct"));

        $resp = $client->send($msg_ser);
        if ($resp->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            $error_message = $resp->faultString();
            return array(
                'error_message' => $error_message,
                'value' => False
            );
        } else {
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_variant_merge` SET `is_synch`=0 where `erp_product_id`='" . $erp_id . "'");

            return array(
                'value' => True
            );
        }
    }


    public function addto_product_merge($erp_product_id, $opencart_product_id,$cart_user = 'Default',$opencart_product_option_id=0){

        $this->db->query("DELETE FROM ".DB_PREFIX."erp_product_variant_merge WHERE  `opencart_product_id`='".$opencart_product_id."'and `opencart_product_option_id`=0 ");

       $this->db->query("INSERT INTO ".DB_PREFIX."erp_product_variant_merge SET `erp_product_id`='".$erp_product_id."', `opencart_product_id`='".$opencart_product_id."',`opencart_product_option_id`='".$opencart_product_option_id."',`created_by`='".$cart_user."'");
    }


    public function addto_product_template_merge($erp_template_id, $opencart_product_id, $cart_user = 'Default'){

        $check_ExistEntry = $this->db->query("SELECT * FROM ".DB_PREFIX."erp_product_template_merge WHERE `erp_template_id`='".$erp_template_id."' AND `opencart_product_id` = '".(int)$opencart_product_id."' AND `created_by`='".$cart_user."' ")->row;

        if(!$check_ExistEntry){
            $this->db->query("INSERT INTO ".DB_PREFIX."erp_product_template_merge SET `erp_template_id`='".$erp_template_id."', `opencart_product_id`='".$opencart_product_id."',`created_by`='".$cart_user."'");
        }
    }

    public function create_product_quantity($userId, $client, $erp_product_id, $product_quantity, $context){
        $arrayVal = array(
            'product_id' => new xmlrpcval($erp_product_id, "int"),
            'new_quantity' => new xmlrpcval($product_quantity, "string")
        );
        $msg_ser  = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($context['db'], "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg_ser->addParam(new xmlrpcval("extra.function", "string"));
        $msg_ser->addParam(new xmlrpcval("update_quantity", "string"));
        $msg_ser->addParam(new xmlrpcval($arrayVal, "struct"));
        $resp = $client->send($msg_ser);
        if ($resp->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            $error_message = $resp->faultString();
            return;
        } else {
            return;
        }
    }

    public function get_erp_category_id($userId, $client, $cart_product_id,$context){

        $categ_data = $this->get_category_from_config($userId, $client, $context);
        if ($categ_data['status']==1){
            $erp_category_id = $categ_data['erp_default_categ_id'];
        }else{
            $erp_category_id = 1;
        }
        $db = $context['db'];
        $pwd = $context['pwd'];
        $cart_user = $context['cart_user'];
        $default_category_id = array(0 => new xmlrpcval($erp_category_id, 'int'));
        $erp_category_ids = array();

        $data = $this->db->query("SELECT `category_id` FROM ". DB_PREFIX ."product_to_category where product_id='".$cart_product_id."'")->rows;

        $this->load->model('catalog/erp_product_category');

        if (empty($data))
            return array('categ_id'=>$erp_category_id, 'categ_ids'=>$default_category_id);
        else{
            foreach ($data as $key => $value) {
                 $erp_category_id = $this->model_catalog_erp_product_category->check_specific_category($value['category_id'], $userId, $client, $db, $pwd,$cart_user);

                 $erp_category_ids[] = new xmlrpcval((int)$erp_category_id, 'int');
            }
            if (!$erp_category_ids)
                return array('categ_id'=>$erp_category_id, 'categ_ids'=>$default_category_id);
            else
                return array('categ_id'=>$erp_category_id, 'categ_ids'=>$erp_category_ids);
        }
    }

    public function get_category_from_config($userId, $client, $context){

        $condition = array(new xmlrpcval(
                        array(  new xmlrpcval('active', "string"),
                                new xmlrpcval('=', "string"),
                                new xmlrpcval(True, "string")),
                            "array"),
                        );
        $msg2   = new xmlrpcmsg('execute');
        $msg2->addParam(new xmlrpcval($context['db'], "string"));
        $msg2->addParam(new xmlrpcval($userId, "int"));
        $msg2->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg2->addParam(new xmlrpcval("opencart.configuration", "string"));
        $msg2->addParam(new xmlrpcval("search", "string"));
        $msg2->addParam(new xmlrpcval($condition,"array"));
        $response  = $client->send($msg2);
        $check = $response->value()->me['array'];
        if (!count($check)==0){
            $id = $check[0]->me['int'];
            $field[0] = new xmlrpcval('oc_default_category', "string");
            $erp_id[0] = new xmlrpcval($id,"int");
            $msg_ser  = new xmlrpcmsg('execute');
            $msg_ser->addParam(new xmlrpcval($context['db'], "string"));
            $msg_ser->addParam(new xmlrpcval($userId, "int"));
            $msg_ser->addParam(new xmlrpcval($context['pwd'], "string"));
            $msg_ser->addParam(new xmlrpcval("opencart.configuration", "string"));
            $msg_ser->addParam(new xmlrpcval("read", "string"));
            $msg_ser->addParam(new xmlrpcval($erp_id, "array"));
            $msg_ser->addParam(new xmlrpcval($field, "array"));
            $resp = $client->send($msg_ser);

            if (!$resp->faultCode()) {
                $val = $resp->value()->me['array'][0]->me['struct'];
                // $oc_default_category = $val['oc_default_category']->me['int'];
                $oc_default_category = $val['oc_default_category']->me['array'][0]->me['int'];
                return array(
                        'erp_default_categ_id' => $oc_default_category,
                        'status' => 1
                    );
            } else{
              $log = new oob_log();
              $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            }
        } else{
          $log = new oob_log();
          $log->logMessage(__FILE__,__LINE__,$response->raw_data,'CRITICAL: ');
          return array(
                      'status' => 0,
                      'message'=> 'NO Active Configuration Found on Odoo End!!!'
                  );
        }
    }

    // check to see if barcode is 13 digits long
    public function validate_ean13($barcode){
        if (!preg_match("/^[0-9]{13}$/", $barcode)) {
            return '';
        }
        $digits = $barcode;
        // 1. Add the values of the digits in the
        // even-numbered positions: 2, 4, 6, etc.
        $even_sum = $digits[1] + $digits[3] + $digits[5] +
                    $digits[7] + $digits[9] + $digits[11];
        // 2. Multiply this result by 3.
        $even_sum_three = $even_sum * 3;
        // 3. Add the values of the digits in the
        // odd-numbered positions: 1, 3, 5, etc.
        $odd_sum = $digits[0] + $digits[2] + $digits[4] +
                   $digits[6] + $digits[8] + $digits[10];
        // 4. Sum the results of steps 2 and 3.
        $total_sum = $even_sum_three + $odd_sum;
        // 5. The check character is the smallest number which,
        // when added to the result in step 4, produces a multiple of 10.
        $next_ten = (ceil($total_sum / 10)) * 10;
        $check_digit = $next_ten - $total_sum;
        // if the check digit and the last digit of the
        // barcode are OK return true;
        if ($check_digit == $digits[12]) {
            return $barcode;
        }
        return '';
    }


    //To search product in product merge table.
    private function search_product($product_id, $option_id){
        $product_check = $this->db->query("SELECT * from `" . DB_PREFIX . "erp_product_variant_merge` where `opencart_product_id`='".$product_id."' AND `opencart_product_option_id`='".$option_id."'")->row;

        if (isset($product_check['erp_product_id']) AND $product_check['erp_product_id'] > 0) {
            if ($product_check['is_synch'] == 1)
                return array(
                    -1,
                    $product_check['erp_product_id'],
                );
            else
                return array(
                    $product_check['erp_product_id'],
                );
        } else
            return array(
                0
            );
    }

    public function base64Image($path){
        $imageData = false;
        $image_url = DIR_IMAGE.$path;
        if (@file_get_contents($image_url)) {
            $content   = file_get_contents($image_url);
            $imageData = base64_encode($content);
        }
        return $imageData;
    }


    public function check_specific_product($product_id, $option_id, $userId, $client, $context){
        $id_lang = $context['lang_id'];
        $context['id_lang'] =  $id_lang;
        $cart_user = $context['cart_user'];

        if($option_id){
            $check_option_value_data = $this->db->query("SELECT `product_option_value_id`,`option_id`, `option_value_id` FROM `". DB_PREFIX ."product_option_value` where product_id='".$product_id."' and product_option_value_id='".$option_id."'")->rows;
        }else{
            $check_option_value_data = $this->db->query("SELECT `product_option_value_id`,`option_id`, `option_value_id` FROM `". DB_PREFIX ."product_option_value` where product_id='".$product_id."'")->rows;
        }
        if ($context['wkproducttype']=='template'){
          $check_option_value_data = false;
          $option_id = 0;
        }

        $erp_template_id = $this->check_specific_template($product_id, $userId, $client, $context, $check_option_value_data);
        $this->load->model('catalog/erp_product_options');

        if ($check_option_value_data){
            foreach ($check_option_value_data as $option_data) {
                $product_option_value_id  = $option_data['product_option_value_id'];

                $product_data = $this->db->query("SELECT p.`weight`,p.`product_id`,p.`quantity`,p.`image`,p.`price`,p.`sku`,pd.`name`,pd.`description`
                    FROM ". DB_PREFIX ."product p
                    LEFT JOIN `".DB_PREFIX."product_description` pd ON (pd.`product_id` = p.`product_id` AND pd.language_id =".$id_lang.") ")->row;

                $erp_option_id = $this->model_catalog_erp_product_options->check_specific_options($userId, $client, $option_data['option_id'], $context);
                $erp_option_value_id = $this->model_catalog_erp_product_options->check_specific_options_value($userId, $client, $option_data['option_value_id'], $context);

                $option_detail = $this->getOptionsDetails($product_option_value_id);
                $product_quantity = $option_detail[0]['quantity'];
                $extra_price = $option_detail[0]['price_prefix'].$option_detail[0]['price'];
                $attr_value_ids = array();
                $attr_value_ids[$erp_option_id] = new xmlrpcval(
                                            array(
                                                new xmlrpcval($erp_option_value_id,"int"),
                                                new xmlrpcval($extra_price,"double")
                                                ), "array");

                $context_erp = array(
                    'oc_variant' => new xmlrpcval('oc_variant', "string"),
                    'extra_price' => new xmlrpcval($extra_price, "string"),
                    'opencart_id' => new xmlrpcval($product_id, "string"),
                    'oc_option_id' => new xmlrpcval($product_option_value_id, "string"),
                    'attr_value_id' => new xmlrpcval($erp_option_value_id, "int"),
                    'create_product_variant' => new xmlrpcval('create_product_variant', "string")
                );

                $attr_data = array(
                    'product_tmpl_id' => new xmlrpcval($erp_template_id,"int"),
                    'default_code' => new xmlrpcval($product_data['sku'],"string"),
                    'oc_attributes' => new xmlrpcval($attr_value_ids,"struct")
                );
                $erp_product_id = 0;

                $status = $this->search_product($product_id, $product_option_value_id);

                if ($status[0]==0){
                    $response_create = $this->create_product($userId, $client, $attr_data ,$context, $context_erp);
                    if ($response_create['erp_id']){
                        $erp_product_id = $response_create['erp_id'];
                        $this->addto_product_merge($response_create['erp_id'], $product_id, $cart_user,$product_option_value_id);
                        $this->create_product_quantity($userId, $client,  $response_create['erp_id'], $product_quantity, $context);
                    }
                }elseif ($status[0] < 0){
                    //Update Option
                    $erp_product_id = $status[1];
                    $response_update = $this->update_product($userId, $client, $status[1], $attr_data ,$context, $context_erp);
                }else{
                    $erp_product_id = $status[0];
                }
                if($option_id){
                    return array(
                        'erp_id' => $erp_product_id
                    );
                }
            }
            return true;
        }else{
            $status = $this->search_product($product_id, $option_id);
            return array(
                    'erp_id' => $status[0],
                    );
        }
    }

    public function getOptionsDetails($product_option_value_id,$option_value_separator = ' - ', $option_separator = ', '){
        $q = "SELECT a.`option_id`, a.`option_value_id`,a.`quantity`,a.`price`,a.`price_prefix`, a.`weight_prefix`,a.`weight`,concat(agl.`name`, '".$option_value_separator."', al.`name`) as option_name
                FROM `". DB_PREFIX ."product_option_value` a

                LEFT JOIN `". DB_PREFIX ."option_description` agl ON (a.`option_id` = agl.`option_id`)

                LEFT JOIN `". DB_PREFIX ."option_value_description` al ON (a.`option_value_id` = al.`option_value_id`)

                WHERE a.product_option_value_id = '$product_option_value_id'";

        $data = $this->db->query($q)->rows;
        return $data;
    }


    public function check_specific_template($product_id, $userId, $client, $context, $option_data=Null){

        $db         = $context['db'];
        $pwd        = $context['pwd'];
        $cart_user  = $context['cart_user'];

        $erp        = $this->check_template($userId, $client, $product_id);

        /**
         * if return array and array[0] index have -1 then resynch option i.e.($erp[0] will be -1)
         */
        if ($erp[0] <= 0){//resynch

            $id_lang = $context['id_lang']; //admin language id

            /**
             * [$product_data get product related info]
             * @var [array of product details]
             */
            $product_data  = $this->db->query("SELECT p.`weight`,p.`product_id`,p.`quantity`,p.`ean`,p.`image`,p.`price`,p.`sku`,pd.`name`,pd.`description` FROM ". DB_PREFIX ."product p
                LEFT JOIN `".DB_PREFIX."product_description` pd ON (p.`product_id` = pd.`product_id` AND pd.language_id =".(int)$id_lang.") WHERE p.product_id =".(int)$product_id."")->row;

            $category_array = $this->get_erp_category_id($userId, $client, $product_data['product_id'],$context);

            $temp_data =array(
                'name' => str_replace('+',' ',html_entity_decode($product_data['name'])),
                'description'=> str_replace('+',' ',strip_tags(html_entity_decode($product_data['description']))),
                'price'=>$product_data['price'],
                'weight'=>$product_data['weight'],
                'image'=>$this->base64Image($product_data['image']),
                'categ_id'=>$category_array['categ_id'],
                'categ_ids'=>$category_array['categ_ids'],
                'default_code'=>$product_data['sku'],
                'ean'=>$product_data['ean'],
                'opencart_id'=>$product_id,
             );
            $product_quantity = $product_data['quantity'];

            /**
             * if return array[0] is equal to zero then create template
             */
            if ($erp[0] == 0) {

                $create = $this->create_template($userId, $client, $temp_data ,$context);

                $erp_template_id = $create['erp_id'];
                $erp_product_id = $create['product_id'];
                if (!$option_data){
                    $this->create_product_quantity($userId, $client, $erp_product_id, $product_quantity, $context);
                }
            }else{
                //Update template
                $this->update_template($userId, $client, $erp[1], $temp_data, $context);
                $erp_template_id = $erp[1];
                if (!$option_data){
                    $vaiant_map = $this->db->query("SELECT `erp_product_id`,`opencart_product_option_id` from `" . DB_PREFIX . "erp_product_variant_merge` where `opencart_product_id`=" . $product_id . "")->row;
                    if($vaiant_map['opencart_product_option_id'] == 0){
                        // $this->create_product_quantity($userId, $client, $vaiant_map['erp_product_id'], $product_quantity, $context);
                        $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_variant_merge` SET `is_synch`=0 where `erp_product_id`='" . $vaiant_map['erp_product_id'] . "'");
                    }
                }
            }
        }else{
           $erp_template_id = $erp[0];
        }
        return $erp_template_id;
    }

    /**
     * [check_template to check synch entry already added into db or resign or not option is needed]
     * @param  [type] $userId     [description]
     * @param  [type] $client     [description]
     * @param  [type] $product_id [description]
     * @return [array]            [erp_template_id, 0 and -1]
     */
    public function check_template($userId, $client, $product_id){
        $check_erp_id = $this->db->query("SELECT `is_synch`,`erp_template_id` from `" . DB_PREFIX . "erp_product_template_merge` where `opencart_product_id`=" . $product_id . "")->row;

        /**
         * check if already synch at erp end and entry found into erp_product_template_merge table
         */
        if (isset($check_erp_id['erp_template_id']) && $check_erp_id['erp_template_id'] > 0) {

            /**
             * if product edit by admin and perform resynch
             */
            if ($check_erp_id['is_synch'] == 1){
                return array(
                    -1,
                    $check_erp_id['erp_template_id']
                );
            }else{
                return array(
                    $check_erp_id['erp_template_id']
                );
            }
        } else{
            /**
             * if not sync at erp end and no record found into erp_product_template_merge table
             */
            return array(
                0
            );
        }
    }

    /**
     * [create_template ]
     * @param  [type] $userId        [description]
     * @param  [type] $client        [description]
     * @param  [type] $template_data [description]
     * @param  [type] $context       [description]
     * @return [type]                [description]
     */
    public function create_template($userId, $client, $template_data ,$context){
        $context_erp = array(
            'opencart' => new xmlrpcval('opencart', "string"),
            'opencart_id' => new xmlrpcval($template_data['opencart_id'], "string")
        );

        if (!$template_data['type']){
            $type = 'product';
        }else{
            $type = $template_data['type'];
        }

        $arrayVal = array(
                'name' => new xmlrpcval($template_data['name'],"string"),
                'description' => new xmlrpcval($template_data['description'],"string"),
                'description_sale' => new xmlrpcval($template_data['description'],"string"),
                'categ_id' => new xmlrpcval($template_data['categ_id'],"int"),
                'list_price' => new xmlrpcval($template_data['price'],"double"),
                'weight' => new xmlrpcval($template_data['weight'],"double"),
                'type' => new xmlrpcval($type, "string"),
                'image' => new xmlrpcval($template_data['image'],"string"),
                'default_code' => new xmlrpcval($template_data['default_code'], "string")
            );

        if ($type=='product'){
            $template_categ = new xmlrpcval(
                        array(
                            new xmlrpcval(
                                array(
                                    new xmlrpcval(6,"int"),
                                    new xmlrpcval(0,"int"),
                                    new xmlrpcval($template_data['categ_ids'],"array")
                                ), "array")
                        ),"array");
            $arrayVal['categ_ids'] = $template_categ;
        }

        if($template_data['ean']){
            $valid = $this->validate_ean13($template_data['ean']);
            if($valid){
                $arrayVal['barcode'] = new xmlrpcval($template_data['ean'],"string");
            }
        }
        $msg_server  = new xmlrpcmsg('execute');
        $msg_server->addParam(new xmlrpcval($context['db'], "string"));
        $msg_server->addParam(new xmlrpcval($userId, "int"));
        $msg_server->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg_server->addParam(new xmlrpcval("product.template", "string"));
        $msg_server->addParam(new xmlrpcval("create_product_template_dict", "string"));
        $msg_server->addParam(new xmlrpcval($arrayVal, "struct"));
        $msg_server->addParam(new xmlrpcval($context_erp, "struct"));
        $resp = $client->send($msg_server);
        if ($resp->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            $error_message = $resp->faultString();
            return array(
                'error_message' => $error_message,
                'erp_id' => -1,
                'product_id' => -1
            );
        } else {
            $temp_dic = $resp->value()->me['struct'];

            $erp_template_id = $temp_dic['template_id']->me['int'];

            $erp_product_id = $temp_dic['product_id']->me['int'];

            $this->addto_product_template_merge($erp_template_id, $template_data['opencart_id'], $context['cart_user']);

            $this->addto_product_merge($erp_product_id, $template_data['opencart_id'], $context['cart_user']);

            return array(
                'erp_id' => $erp_template_id,
                'product_id' => $erp_product_id
            );
        }
    }

    public function update_template($userId, $client, $erp_id, $template_data, $context){

        $erp_product_key = array(
            new xmlrpcval($erp_id, 'int')
        );
        $context_erp = array(
            'opencart' => new xmlrpcval('opencart', "string"),
            'presta_id' => new xmlrpcval($ps_product_id, "string")
        );
        $template_categ = new xmlrpcval(
                        array(
                            new xmlrpcval(
                                array(
                                    new xmlrpcval(6,"int"),
                                    new xmlrpcval(0,"int"),
                                    new xmlrpcval($template_data['categ_ids'],"array")
                                ), "array")
                        ),"array");
        $arrayVal = array(
            'name' => new xmlrpcval($template_data['name'],"string"),
            'description' => new xmlrpcval($template_data['description'],"string"),
            'description_sale' => new xmlrpcval($template_data['description'],"string"),
            'categ_id' => new xmlrpcval($template_data['categ_id'],"int"),
            'categ_ids' => $template_categ,
            'list_price' => new xmlrpcval($template_data['price'],"double"),
            'weight' => new xmlrpcval($template_data['weight'],"double"),
            'image' => new xmlrpcval($template_data['image'],"string"),
            'default_code' => new xmlrpcval($template_data['default_code'], "string")
        );

        if($template_data['ean']){
            $valid = $this->validate_ean13($template_data['ean']);
            if($valid){
                $arrayVal['barcode'] = new xmlrpcval($template_data['ean'],"string");
            }
        }
        $msg_server  = new xmlrpcmsg('execute');
        $msg_server->addParam(new xmlrpcval($context['db'], "string"));
        $msg_server->addParam(new xmlrpcval($userId, "int"));
        $msg_server->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg_server->addParam(new xmlrpcval("product.template", "string"));
        $msg_server->addParam(new xmlrpcval("write", "string"));
        $msg_server->addParam(new xmlrpcval($erp_product_key, "array"));
        $msg_server->addParam(new xmlrpcval($arrayVal, "struct"));
        $msg_server->addParam(new xmlrpcval($context_erp, "struct"));
        $resp = $client->send($msg_server);
        if ($resp->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            $error_message = $resp->faultString();
            return array(
                'error_message' => $error_message,
                'value' => False
            );
        } else {
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_template_merge` SET `is_synch`=0 where `erp_template_id`='" . $erp_id . "'");

            return array(
                'value' => True
            );
        }
    }


}

?>
