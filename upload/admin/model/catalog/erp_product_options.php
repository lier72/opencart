<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpProductOptions extends Model {

    public function getErpProductArray(){

    }

    public function check_all_options($userId, $client, $context){
        $is_error      = 0;
        $error_message = '';
        $ids           = '';

        $data = $this->db->query("SELECT a.`option_id`,a.`sort_order`,a.`type`, d.`name`, erp.`is_synch`, erp.`erp_option_id` FROM `". DB_PREFIX ."option` a LEFT JOIN `".DB_PREFIX."option_description` d ON (a.`option_id` = d.option_id )
        LEFT JOIN `".DB_PREFIX."erp_product_option_merge` erp ON (erp.`opencart_option_id` = a.option_id)
        WHERE  erp.`is_synch` IS NULL or erp.`is_synch` =1")->rows;

        if (count($data) == 0) {
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                'ids' => $ids
            );
        }

        foreach ($data as $option_data){
            $option_name = str_replace('+',' ',html_entity_decode($option_data['name']));
            $key     = array(
                'name' => new xmlrpcval($option_name, "string"),
            );
            $context_erp = array(
                'opencart' => new xmlrpcval('opencart', "string"),
                'opencart_id' => new xmlrpcval($option_data['option_id'], "string")
            );

            if ($option_data['is_synch']==NULL){
                $create = $this->create_product_option($key, $option_data['option_id'], $userId, $client, $context, $context_erp);
                if (!$create['erp_id'] > 0){
                    $is_error = 1;
                    $error_message .= $create['error_message'] . ',';
                    $ids .= $option_data['option_id'] . ',';
                }
            }
            else{
                $update = $this->update_product_option($key, $option_data['erp_option_id'], $option_data['option_id'],  $userId, $client, $context, $context_erp);
            }

        }

        return array(
            'is_error' => $is_error,
            'error_message' => $error_message,
            'value' => 1,
            'ids' => $ids
        );
    }

    public function create_product_option($key, $option_id, $userId, $client, $context, $context_erp){

        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($context['db'], "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg_ser->addParam(new xmlrpcval("product.attribute", "string"));
        $msg_ser->addParam(new xmlrpcval("create", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
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
            $cart_user = $context['cart_user'];

            $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_product_option_merge` SET erp_option_id = '$erp_id' , opencart_option_id = '$option_id' ,created_by = '$cart_user' ");

            return array(
                'erp_id' => $erp_id
            );
        }
    }

    public function update_product_option($key, $erp_option_id, $id_option, $userId, $client, $context, $context_erp){

        $erp_id = array(
            new xmlrpcval($erp_option_id, 'int')
        );

        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($context['db'], "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg_ser->addParam(new xmlrpcval("product.attribute", "string"));
        $msg_ser->addParam(new xmlrpcval("write", "string"));
        $msg_ser->addParam(new xmlrpcval($erp_id, "array"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
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
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_option_merge` set `is_synch`=0 where `opencart_option_id`='$id_option'");
        }
    }

    public function addto_option_merge($oc_option_id, $erp_option_id, $oc_user = 'Front End'){
        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_product_option_merge` SET erp_option_id = '$erp_option_id' , opencart_option_id = '$oc_option_id' ,created_by = '$oc_user' ");
    }

    public function check_all_option_values($userId, $client, $context){
        $db = $context['db'];
        $pwd = $context['pwd'];
        $cart_user = $context['cart_user'];
        $id_lang = $context['id_lang'];
        $is_error      = 0;
        $error_message = '';
        $ids           = '';

        $data = $this->db->query("SELECT a.`option_id`,a.`option_value_id`, d.`name`, erp.`is_synch`, erp.`erp_option_value_id` FROM `". DB_PREFIX ."option_value` a LEFT JOIN `".DB_PREFIX."option_value_description` d ON (a.`option_value_id` = d.option_value_id and d.`language_id`= ".$id_lang." )
        LEFT JOIN `".DB_PREFIX."erp_product_option_value_merge` erp ON (erp.`opencart_option_value_id` = a.option_value_id)
        WHERE  erp.`is_synch` IS NULL or erp.`is_synch` =1")->rows;

        if (count($data) == 0) {
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                'ids' => $ids
            );
        }

        foreach ($data as $value_data){
            $erp_option_id = $this->check_specific_options($userId, $client, $value_data['option_id'], $context);
            if ($erp_option_id){
                $value_name = str_replace('+',' ',html_entity_decode($value_data['name']));
                $key     = array(
                    'name' => new xmlrpcval($value_name, "string"),
                    'attribute_id' => new xmlrpcval($erp_option_id, "int"),
                );
                $context_erp = array(
                    'opencart' => new xmlrpcval('opencart', "string"),
                    'opencart_id' => new xmlrpcval($value_data['option_value_id'], "string"),
                    'oc_attr_id' => new xmlrpcval($value_data['option_id'], "string")
                );
                if ($value_data['is_synch']==NULL){
                    $create = $this->create_product_option_value($key, $value_data['option_value_id'], $value_data['option_id'], $userId, $client, $context, $context_erp);
                    if (!$create['erp_id'] > 0){
                        $is_error = 1;
                        $error_message .= $create['error_message'] . ',';
                        $ids .= $value_data['option_id'] . ',';
                    }
                }
                else{
                    $update = $this->update_product_option_value($key, $value_data['option_value_id'], $value_data['erp_option_value_id'], $userId, $client, $context, $context_erp);
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

    public function check_specific_options($userId, $client, $option_id, $context){
        $id_lang = $context['id_lang'];
        $erp_id = false;
        $erp_option_id = $this->check_product_options($option_id);
        if ($erp_option_id[0] > 0)
            $erp_id = $erp_option_id[0];
        else{
            $data = $this->db->query("SELECT a.`option_id`,a.`sort_order`,a.`type`, d.`name`, erp.`is_synch`, erp.`erp_option_id` FROM `". DB_PREFIX ."option` a LEFT JOIN `".DB_PREFIX."option_description` d ON (a.`option_id` = d.option_id )
            LEFT JOIN `".DB_PREFIX."erp_product_option_merge` erp ON (erp.`opencart_option_id` = a.option_id)
            WHERE  a.`option_id`=".$option_id."")->row;

            $key     = array(
                'name' => new xmlrpcval($data['name'], "string"),
            );

            $context_erp = array(
                'opencart' => new xmlrpcval('opencart', "string"),
                'opencart_id' => new xmlrpcval($data['option_id'], "string")
            );

            if ($data['is_synch']==NULL){
                $create = $this->create_product_option($key, $data['option_id'], $userId, $client, $context, $context_erp);
                $erp_id = $create['erp_id'];
            }
            else{
                $erp_id = $data['erp_option_id'];
                $update = $this->update_product_option($key, $data['erp_option_id'], $data['option_id'],  $userId, $client, $context, $context_erp);
            }
        }

        return $erp_id;
    }

    public function check_specific_options_value($userId, $client, $option_value_id, $context){
        $id_lang = $context['id_lang'];
        $erp_id = false;
        $erp_value_id = $this->check_option_value($option_value_id);
        if ($erp_value_id[0] > 0)
            $erp_id = $erp_value_id[0];
        else{
            $data = $this->db->query("SELECT d.`option_id`,d.`option_value_id`, d.`name`, erp.`is_synch`, erp.`erp_option_value_id` FROM `". DB_PREFIX ."option_value_description` d
            LEFT JOIN `".DB_PREFIX."erp_product_option_value_merge` erp ON (erp.`opencart_option_value_id` = d.option_value_id)
            WHERE  d.`option_value_id`=".$option_value_id." and d.`language_id`= ".$id_lang."")->row;

           $erp_option_id = $this->check_specific_options($userId, $client, $data['option_id'], $context);
            if ($erp_option_id){
                $value_name = str_replace('+',' ',html_entity_decode($data['name']));
                $key     = array(
                    'name' => new xmlrpcval($value_name, "string"),
                    'attribute_id' => new xmlrpcval($erp_option_id, "int"),
                );
                $context_erp = array(
                    'opencart' => new xmlrpcval('opencart', "string"),
                    'opencart_id' => new xmlrpcval($data['option_value_id'], "string"),
                    'oc_attr_id' => new xmlrpcval($data['option_id'], "string")
                );
                if ($data['is_synch']==NULL){
                    $create = $this->create_product_option_value($key, $data['option_value_id'], $data['option_id'], $userId, $client, $context, $context_erp);
                    $erp_id = $create['erp_id'];
                }
                else{
                    $update = $this->update_product_option_value($key, $data['option_value_id'], $data['erp_option_value_id'], $userId, $client, $context, $context_erp);
                    $erp_id = $data['erp_option_value_id'];
                }
            }
        }

        return $erp_id;
    }

    public function create_product_option_value($key, $oc_value_id, $oc_option_id, $userId, $client, $context, $context_erp){

        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($context['db'], "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg_ser->addParam(new xmlrpcval("product.attribute.value", "string"));
        $msg_ser->addParam(new xmlrpcval("create", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
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
            $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_product_option_value_merge` SET erp_option_value_id = '$erp_id' , opencart_option_value_id = '$oc_value_id' ,option_id = '$oc_option_id' ");
            return array(
                'erp_id' => $erp_id
            );
        }
    }

    public function update_product_option_value($key, $option_value_id, $erp_option_value_id, $userId, $client, $context, $context_erp){
        $erp_id = array(
            new xmlrpcval($erp_option_value_id, 'int')
        );

        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($context['db'], "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($context['pwd'], "string"));
        $msg_ser->addParam(new xmlrpcval("product.variant.dimension.option", "string"));
        $msg_ser->addParam(new xmlrpcval("write", "string"));
        $msg_ser->addParam(new xmlrpcval($erp_id, "array"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
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
           $this->db->query("UPDATE  `" . DB_PREFIX . "erp_product_option_value_merge` set `is_synch`=0 where `opencart_option_value_id`='$option_value_id'");
            return array(
                'value' => True
            );
        }
    }

    public function check_product_options($id_option){
        $check_erp_id = $this->db->query("SELECT  `is_synch`, `erp_option_id` FROM `". DB_PREFIX ."erp_product_option_merge` where opencart_option_id='".$id_option."'")->row;
        if (!isset($check_erp_id['erp_option_id']))
            return array(
                0
            );

        if ($check_erp_id['erp_option_id'] > 0) {
            if ($check_erp_id['is_synch'] == 1)
                return array(
                    -1,
                    $check_erp_id['erp_option_id']
                );
            else
                return array(
                    $check_erp_id['erp_option_id']
                );
        }

    }

	public function check_option_value($id_option_value){
        $check_erp_id = $this->db->query("SELECT  `is_synch`, `erp_option_value_id` FROM `". DB_PREFIX ."erp_product_option_value_merge` WHERE opencart_option_value_id='".$id_option_value."'")->row;

        if (!isset($check_erp_id['erp_option_value_id']))
            return array(
                0
            );

        if ($check_erp_id['erp_option_value_id'] > 0) {
            if ($check_erp_id['is_synch'] == 1)
                return array(
                    -1,
                    $check_erp_id['erp_option_value_id']
                );
            else
                return array(
                    $check_erp_id['erp_option_value_id']
                );
        } else
            return array(
                0
            );
    }

    public function check_option_value_merge($oc_option_value,$erp_value_id){
        $check = $this->db->query("SELECT  * FROM `". DB_PREFIX ."erp_product_option_value_merge` WHERE opencart_option_value_id='".$oc_option_value."' and erp_option_value_id='".$erp_value_id."' ")->row;
        if($check)
            return false;
        return true;
    }

    public function getErpProductOptionValueArray($userId,$client,$db,$pwd){

        $OptionValues = array();
        $key     = array();
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("product.attribute.value", "string"));
        $msg_ser->addParam(new xmlrpcval("search", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "array"));
        $resp0    = $client->send($msg_ser);
        if ($resp0->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp0->raw_data,'CRITICAL: ');
            array_push($OptionValues, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
            return $OptionValues;
        }else{
            $val    = $resp0->value()->me['array'];
            $key1 = array(new xmlrpcval('id','int') , new xmlrpcval('name', 'string'));
            $msg_ser1 = new xmlrpcmsg('execute');
            $msg_ser1->addParam(new xmlrpcval($db, "string"));
            $msg_ser1->addParam(new xmlrpcval($userId, "int"));
            $msg_ser1->addParam(new xmlrpcval($pwd, "string"));
            $msg_ser1->addParam(new xmlrpcval("product.attribute.value", "string"));
            $msg_ser1->addParam(new xmlrpcval("read", "string"));
            $msg_ser1->addParam(new xmlrpcval($val, "array"));
            $msg_ser1->addParam(new xmlrpcval($key1, "array"));
            $resp1   = $client->send($msg_ser1);
            if ($resp1->faultCode()) {
                $log = new oob_log();
                $log->logMessage(__FILE__,__LINE__,$resp1->raw_data,'CRITICAL: ');
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($OptionValues, array('label' => $msg, 'id' => ''));
                return $OptionValues;
            }else {
                $value_array=$resp1->value()->scalarval();

                $count = count($value_array);

                for($x=0;$x<$count;$x++){
                   $id = $value_array[$x]->me['struct']['id']->me['int'];
                   $name = $value_array[$x]->me['struct']['name']->me['string'];
                   array_push($OptionValues,
                    array(
                            'id' => $id,
                            'name'=>$name )
                    );
                }
            }
        }

        return $OptionValues;
    }

    public function getErpProductOptionArray($userId, $client, $db,$pwd){

        $Option = array();
        $key     = array();
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("product.attribute", "string"));
        $msg_ser->addParam(new xmlrpcval("search", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "array"));
        $resp0    = $client->send($msg_ser);
        if ($resp0->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp0->raw_data,'CRITICAL: ');
            array_push($Option, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
            return $Option;
        }else{
            $val    = $resp0->value()->me['array'];
            $key1 = array(new xmlrpcval('id','int') , new xmlrpcval('name', 'string'));
            $msg_ser1 = new xmlrpcmsg('execute');
            $msg_ser1->addParam(new xmlrpcval($db, "string"));
            $msg_ser1->addParam(new xmlrpcval($userId, "int"));
            $msg_ser1->addParam(new xmlrpcval($pwd, "string"));
            $msg_ser1->addParam(new xmlrpcval("product.attribute", "string"));
            $msg_ser1->addParam(new xmlrpcval("read", "string"));
            $msg_ser1->addParam(new xmlrpcval($val, "array"));
            $msg_ser1->addParam(new xmlrpcval($key1, "array"));
            $resp1   = $client->send($msg_ser1);
            if ($resp1->faultCode()) {
                $log = new oob_log();
                $log->logMessage(__FILE__,__LINE__,$resp1->raw_data,'CRITICAL: ');
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Option, array('label' => $msg, 'id' => ''));
                return $Option;
            }else {
                $value_array=$resp1->value()->scalarval();

                $count = count($value_array);

                for($x=0;$x<$count;$x++){
                   $id = $value_array[$x]->me['struct']['id']->me['int'];
                   $name = $value_array[$x]->me['struct']['name']->me['string'];
                   array_push($Option,
                    array(
                            'id' => $id,
                            'name'=>$name )
                    );
                }
            }
        }

        return $Option;
    }
	public function check_option_merge($oc_option_value,$erp_value_id){
        $check = $this->db->query("SELECT  * FROM `". DB_PREFIX ."erp_product_option_merge` WHERE opencart_option_id='".$oc_option_value."' and erp_option_id='".$erp_value_id."' ")->row;
        if($check)
            return false;
        return true;
    }

	public function addto_option_value_merge($option_value_id, $erp_option_value_id,$opencart_option_id){

        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_product_option_value_merge` SET erp_option_value_id = '$erp_option_value_id' , opencart_option_value_id = '$option_value_id' ,option_id = '$opencart_option_id' ");

    }


}
?>