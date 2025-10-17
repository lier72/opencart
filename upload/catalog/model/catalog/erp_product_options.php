<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.x.x.x. From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpProductOptions extends Model {

    public function getErpProductArray(){

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
        } else
            return array(
                0
            );
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


    public function addto_option_value_merge($option_value_id, $erp_option_value_id,$opencart_option_id){

        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_product_option_value_merge` SET erp_option_value_id = '$erp_option_value_id' , opencart_option_value_id = '$option_value_id' ,option_id = '$opencart_option_id' ");

    }


}
?>
