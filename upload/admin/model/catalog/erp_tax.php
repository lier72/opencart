<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpTax extends Model {

    public function check_all_taxes($userId, $client, $db, $pwd, $op_user){
        $is_error      = 0;
        $error_message = '';
        $ids           = '';
        $data          = $this->db->query("SELECT * FROM " . DB_PREFIX . "tax_rate  WHERE `tax_rate_id` NOT IN (SELECT `opencart_tax_id` FROM `" . DB_PREFIX . "erp_tax_merge` WHERE `is_synch` = 0 )")->rows;

        if (count($data) == 0) {
            //Nothing to export
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                '$ids' => $ids
            );
        }
        foreach ($data as $tax) {
            $check_tax = $this->check_tax($tax['tax_rate_id']);

            if ($check_tax[0] <= 0) {
                if ($check_tax[0] == 0) {
                    $create = $this->create_tax($tax, $client, $userId, $db, $pwd);

                    if ($create['erp_id'] > 0)
                        $this->addto_tax_merge($create['erp_id'], $tax['tax_rate_id'], $op_user);
                    else {
                        $is_error = 1;
                        $error_message .= $create['error_message'] . ',';
                        $ids .= $tax['tax_rate_id'] . ',';
                    }
                } else {
                    $update = $this->update_taxes($check_tax[1], $tax, $client, $userId, $db, $pwd);
                    if ($update['value'] != True) {
                        $is_error = 1;
                        $error_message .= $update['error_message'] . ',';
                        $ids .= $tax['tax_rate_id'] . ',';
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

    public function check_tax($tax_id){
        $check_tax = $this->db->query("SELECT `is_synch`,`erp_tax_id` from `" . DB_PREFIX . "erp_tax_merge` where `opencart_tax_id`='" . $tax_id . "'")->row;
        if ($check_tax) {
            if ($check_tax['is_synch'] == 1)
                return array(
                    -1,
                    $check_tax['erp_tax_id']
                );
            else
                return array(
                    $check_tax['erp_tax_id']
                );
        } else
            return array(
                0
            );
    }

    public function create_tax($tax, $client, $userId, $db, $pwd){
        if($tax['type'] == 'P'){
            $tax_rate = $tax['rate'];
            $tax_type = 'percent';
        }
        elseif($tax['type'] == 'F'){
            $tax_rate = $tax['rate'];
            $tax_type = 'fixed';
        }
        $tax_name = $tax['name'].'_'.$tax['tax_rate_id'];
        $key = array(
            'name' => new xmlrpcval($tax_name, "string"),
            'amount' => new xmlrpcval($tax_rate, "string"),
            'amount_type' => new xmlrpcval($tax_type, "string")
        );

        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("account.tax", "string"));
        $msg_ser->addParam(new xmlrpcval("create", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
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
        return $val['int'];
    }

    public function addto_tax_merge($erp_tax_id, $tax_id, $op_user = 'Front End'){
        $db_tax  = $this->db->query("SELECT `rate`  from `" . DB_PREFIX . "tax_rate` where `tax_rate_id`='" . $tax_id . "'")->row;
        if(isset($db_tax['rate']))
            $rate = $db_tax['rate'];
        else
            return false;

        $data = array(
            'erp_tax_id' => $erp_tax_id,
            'prestashop_tax_id' => $tax_id,
            'created_by' => $op_user,
            'rate'=>$rate
        );

        $chktax_id  = $this->db->query("SELECT `id`  from `" . DB_PREFIX . "erp_tax_merge` where `opencart_tax_id`=" . $tax_id . "")->row;

        if(!$chktax_id){
            $this->db->query("INSERT INTO `".DB_PREFIX. "erp_tax_merge` SET erp_tax_id = '$erp_tax_id' ,opencart_tax_id = '$tax_id', rate = '$rate' , created_by = '$op_user', created_on = NOW() ");
            return true;
        }else{
            return false;
        }
    }

    public function update_taxes($erp_tax_id, $tax, $client, $userId, $db, $pwd){
        $op_tax_id = $tax['tax_rate_id'];
        if($tax['type'] == 'P'){
            $tax_rate = $tax['rate'];
            $tax_type = 'percent';
        }
        elseif($tax['type'] == 'F'){
            $tax_rate = $tax['rate'];
            $tax_type = 'fixed';
        }
        $tax_name = $tax['name'].'_'.$tax['tax_rate_id'];
        $key = array(
            'name' => new xmlrpcval($tax_name, "string"),
            'amount' => new xmlrpcval($tax_rate, "string"),
            'amount_type' => new xmlrpcval($tax_type, "string")
        );
        $erp_tax_list = array(
            new xmlrpcval($erp_tax_id, 'int')
        );
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("account.tax", "string"));
        $msg_ser->addParam(new xmlrpcval("write", "string"));
        $msg_ser->addParam(new xmlrpcval($erp_tax_list, "array"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
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
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_tax_merge` SET `is_synch`=0 where `opencart_tax_id`='" . $op_tax_id . "'");
            return array(
                'value' => True
            );
        }
    }

    public function check_specific_tax($id_tax, $client, $userId, $db, $pwd){
        $check_tax = $this->check_tax($id_tax);
        if ($check_tax[0] > 0)
            return $check_tax[0];
        else {
            $db_tax  = $this->db->query("SELECT * FROM " . DB_PREFIX . "tax_rate  WHERE `tax_rate_id` ='" . $id_tax . "'")->row;
            if ($check_tax[0] == 0) {
                $create = $this->create_tax($db_tax, $client, $userId, $db, $pwd);
                $this->addto_tax_merge($create['erp_id'], $id_tax, 'Front End');
                return $create['erp_id'];
            } else {
                $update = $this->update_taxes($check_tax[1], $db_tax, $client, $userId, $db, $pwd);
                return $check_tax[1];
            }
        }
    }

    public function getErpTaxArray($userId, $client, $db, $pwd) {
        $Tax = array();
        $key     = array();
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("account.tax", "string"));
        $msg_ser->addParam(new xmlrpcval("search", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "array"));

        $resp0    = $client->send($msg_ser);
        if ($resp0->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp0->raw_data,'CRITICAL: ');
            array_push($Tax, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
            return $Tax;
        }else{
            $val    = $resp0->value()->me['array'];
            $key1 = array(new xmlrpcval('id','int') , new xmlrpcval('name', 'string'),new xmlrpcval('amount', 'string'));
            $msg_ser1 = new xmlrpcmsg('execute');
            $msg_ser1->addParam(new xmlrpcval($db, "string"));
            $msg_ser1->addParam(new xmlrpcval($userId, "int"));
            $msg_ser1->addParam(new xmlrpcval($pwd, "string"));
            $msg_ser1->addParam(new xmlrpcval("account.tax", "string"));
            $msg_ser1->addParam(new xmlrpcval("read", "string"));
            $msg_ser1->addParam(new xmlrpcval($val, "array"));
            $msg_ser1->addParam(new xmlrpcval($key1, "array"));
            $resp1   = $client->send($msg_ser1);
            if ($resp1->faultCode()) {
                $log = new oob_log();
                $log->logMessage(__FILE__,__LINE__,$resp1->raw_data,'CRITICAL: ');
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Tax, array('label' => $msg, 'id' => ''));
                return $Tax;
            }else {
                $value_array=$resp1->value()->scalarval();
                $count = count($value_array);

                for($x=0;$x<$count;$x++){
                   $id = $value_array[$x]->me['struct']['id']->me['int'];
                   $name = $value_array[$x]->me['struct']['name']->me['string'].' ('.($value_array[$x]->me['struct']['amount']->me['double']*100).'%)';
                   array_push($Tax,
                    array(
                            'id' => $id,
                            'name'=>$name )
                    );
                }
            }
        }

        return $Tax;
    }

}
?>