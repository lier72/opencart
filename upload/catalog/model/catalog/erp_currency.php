<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.x.x.x. From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpCurrency extends Model {

    public function check_all_currencies($userId, $client, $db, $pwd, $cart_user){

        $is_error      = 0;
        $error_message = '';
        $ids           = '';
        $data          = $this->db->query("SELECT `currency_id`,`code`,`title`  from `" . DB_PREFIX . "currency` WHERE `currency_id` NOT IN (SELECT `opencart_currency_id` FROM `" . DB_PREFIX . "erp_currency_merge`)")->rows;

        if (count($data) == 0) {
            //Nothing to export
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                '$ids' => $ids
            );
        }
        foreach ($data as $currency) {
            $erp_currency_id = $this->check_currency($currency['currency_id']);

            if ($erp_currency_id <= 0) {
                $create = $this->create_currency($currency['code'], $currency['title'], $userId, $client , $db, $pwd);

                if ($create['erp_id'] > 0)
                    $this->addto_currency_merge($create['erp_id'], $currency['currency_id'], $cart_user);
                else {
                    $is_error = 1;
                    $error_message .= $create['error_message'] . ',';
                    $ids .= $currency['currency_id'] . ',';
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

    public function check_currency($id_currency){
        $check = $this->db->query("SELECT `erp_currency_id` from `" . DB_PREFIX . "erp_currency_merge` where `opencart_currency_id`='" . $id_currency . "'")->row;
        if (isset($check['erp_currency_id']) AND $check['erp_currency_id'] > 0)
            return $check['erp_currency_id'];
        else
            return 0;
    }

    public function create_currency($iso_code, $currency_name, $userId, $client, $db, $pwd){
        $pricelist_array =  array(
            'code'=>new xmlrpcval($iso_code, "string")
        );
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("extra.function", "string"));
        $msg_ser->addParam(new xmlrpcval("create_pricelist", "string"));
        // $msg_ser->addParam(new xmlrpcval('Pricelist_' . $currency_name, "string"));
        $msg_ser->addParam(new xmlrpcval($pricelist_array, "struct"));
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

    public function addto_currency_merge($erp_currency_id, $currency_id, $op_user = 'Front End'){
        $data = array(
            'erp_currency_id' => $erp_currency_id,
            'prestashop_currency_id' => $currency_id,
            'created_by' => $op_user
        );

        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_currency_merge` SET erp_currency_id = '$erp_currency_id' , opencart_currency_id = '$currency_id' , created_by = '$op_user', created_on = NOW() ");
    }


    public function check_specific_currency($currency_id, $userId, $client , $db, $pwd){
        $erp_currency_id = $this->check_currency($currency_id);
        if ($erp_currency_id > 0)
            return $erp_currency_id;
        else {
            $check  = $this->db->query("SELECT `code`,`title`  from `" . DB_PREFIX . "currency` where `currency_id` = " . $currency_id . "")->row;
            $create = $this->create_currency($check['code'], $check['title'], $userId, $client, $db, $pwd);
            if ($create['erp_id'] > 0){
                $this->addto_currency_merge($create['erp_id'], $currency_id);
                return $create['erp_id'];
            }else{
                //return 1;
                return 0;
            }
        }
    }
}
?>
