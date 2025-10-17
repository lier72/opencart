<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpCarrier extends Model {

    public function check_all_carriers($userId, $client, $db, $pwd, $cart_user){
        $is_error      = 0;
        $error_message = '';
        $ids           = '';
        $check         = $this->db->query("SELECT `code` from `" . DB_PREFIX . "extension` WHERE `code` NOT IN (SELECT `opencart_carrier_cod` FROM `" . DB_PREFIX . "erp_carrier_merge` WHERE `is_synch` = 0) AND type = 'shipping' ")->rows;

        if (count($check) == 0) {
            //Nothing to export
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                'ids' => $ids
            );
        }

        foreach ($check as $data) {
            $erp_carrier_id = $this->check_carrier($data['code']);

            $extension = basename($data['code'], '.php');
            $this->language->load('shipping/' . $extension);
            $data['name']  = $this->language->get('heading_title');

            if ($erp_carrier_id[0] == 0) {
                $create = $this->create_carrier($data['code'], $userId, $client, $cart_user ,$db, $pwd);

                if ($create['erp_id'] > 0)
                    $this->addto_carrier_merge($data['code'], $create['erp_id'], $data['code'], $cart_user);
                else {
                    $is_error = 1;
                    $error_message .= $create['error_message'] . ',';
                    $ids .= $data['code'] . ',';
                }
            }
            if ($erp_carrier_id[0] < 0) {
                $update = $this->update_carriers($erp_carrier_id[1], $data['code'], $data['name'], $userId, $client, $cart_user,$db, $pwd);
                if ($update['value'] != True) {
                    $is_error = 1;
                    $error_message .= $update['error_message'] . ',';
                    $ids .= $data['code'] . ',';
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

    public function check_specific_carrier($carrier_id, $userId, $client, $db, $pwd){

        $carrier_id = explode('.', $carrier_id);
        $erp = $this->check_carrier($carrier_id[0]);
        $check = $this->db->query("SELECT `code` from `" . DB_PREFIX . "extension` WHERE `code` = '".$carrier_id[0]."' AND type='shipping'")->row;

        $extension = basename($check['code'], '.php');
        $this->language->load('shipping/' . $extension);
        $check['name']  = $this->language->get('heading_title');

        if ($erp[0] == 0) {
            $create = $this->create_carrier($check['code'], $userId, $client, $cart_user = 'Front End', $db, $pwd);
            $this->addto_carrier_merge($carrier_id[0], $create['erp_id'], $check['code']);
            $erp_id = $create['erp_id'];
        }
        if ($erp[0] < 0) {
            $update = $this->update_carriers($erp[1], $check['code'], $check['name'], $userId, $client, $cart_user = 'Front End', $db, $pwd);
            $erp_id=$erp[1];
        }
        if ($erp[0] > 0)
            $erp_id=$erp[0];
        return array('name'=>$check['code'],'erp_id'=>$erp_id);
    }

    public function check_carrier($id_carrier){
        $check_erp_id = $this->db->query("SELECT `is_synch`,`erp_carrier_id` from `" . DB_PREFIX . "erp_carrier_merge` where `opencart_carrier_cod`= '$id_carrier'")->row;
        if (isset($check_erp_id['erp_carrier_id']) AND $check_erp_id['erp_carrier_id'] > 0) {
            if ($check_erp_id['is_synch'] == 1)
                return array(
                    -1,
                    $check_erp_id['erp_carrier_id']
                );
            else
                return array(
                    $check_erp_id['erp_carrier_id']
                );
        } else
            return array(
                0
            );
    }

    public function create_carrier($name, $userId, $client, $cart_user, $db, $pwd){
        $key = array(
            'name' => new xmlrpcval($name, "string"),
            'opencart' => new xmlrpcval('opencart', "string"),
        );

        // $context_erp = array(
        //     'opencart_carrier' => new xmlrpcval('opencart', "string"),
        // );

        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("delivery.carrier", "string"));
        $msg_ser->addParam(new xmlrpcval("create", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
        // $msg_ser->addParam(new xmlrpcval($context_erp, "struct"));
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

    public function addto_carrier_merge($carrier_id, $erp_carrier_id, $name, $cart_user = 'Front End'){
        $data = array(
            'erp_carrier_id' => $erp_carrier_id,
            'opencart_carrier_cod' => $carrier_id,
            'created_by' => $cart_user,
            'name' => $name
        );

        $this->db->query("INSERT INTO " . DB_PREFIX . "erp_carrier_merge SET erp_carrier_id = '$erp_carrier_id' , opencart_carrier_cod = '$carrier_id' , name = '$name', created_by = '$cart_user', created_on = NOW() ");

    }

    public function update_carriers($erp_carrier_id, $id_carrier, $name, $userId, $client, $cart_user = 'Front End', $db, $pwd){

        $erp_carrier_list = array(
            new xmlrpcval($erp_carrier_id, 'int')
        );

        $key     = array(
            'name' => new xmlrpcval($name, "string"),
        );
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("delivery.carrier", "string"));
        $msg_ser->addParam(new xmlrpcval("write", "string"));
        $msg_ser->addParam(new xmlrpcval($erp_carrier_list, "array"));
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
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_carrier_merge` SET `is_synch`=0 where `opencart_carrier_cod`= '$id_carrier'");
            return array(
                'value' => True
            );
        }
    }


    public function chk_carrier_merge($cart_id, $erp_id){

        $check = $this->db->query("SELECT `id` from `" . DB_PREFIX . "erp_carrier_merge` WHERE `opencart_carrier_cod` = '".$cart_id."' OR erp_carrier_id = '$erp_id'")->row;
        if($check)
            return False;
        else
            return true;
    }

    public function getErpCarrierArray($userId, $client, $db, $pwd) {
        $Carrier = array();

        $key     = array();
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("delivery.carrier", "string"));
        $msg_ser->addParam(new xmlrpcval("search", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "array"));

        $resp0    = $client->send($msg_ser);
        if ($resp0->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp0->raw_data,'CRITICAL: ');
            array_push($Carrier, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
            return $Carrier;
        }else{
            $val    = $resp0->value()->me['array'];
            $key1 = array(new xmlrpcval('id','int') , new xmlrpcval('name', 'string'));
            $msg_ser1 = new xmlrpcmsg('execute');
            $msg_ser1->addParam(new xmlrpcval($db, "string"));
            $msg_ser1->addParam(new xmlrpcval($userId, "int"));
            $msg_ser1->addParam(new xmlrpcval($pwd, "string"));
            $msg_ser1->addParam(new xmlrpcval("delivery.carrier", "string"));
            $msg_ser1->addParam(new xmlrpcval("read", "string"));
            $msg_ser1->addParam(new xmlrpcval($val, "array"));
            $msg_ser1->addParam(new xmlrpcval($key1, "array"));
            $resp1   = $client->send($msg_ser1);
            if ($resp1->faultCode()) {
                $log = new oob_log();
                $log->logMessage(__FILE__,__LINE__,$resp1->raw_data,'CRITICAL: ');
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Carrier, array('label' => $msg, 'id' => ''));
                return $Carrier;
            }else {
                $value_array=$resp1->value()->scalarval();
                $count = count($value_array);

                for($x=0;$x<$count;$x++){
                   $id = $value_array[$x]->me['struct']['id']->me['int'];
                   $name = $value_array[$x]->me['struct']['name']->me['string'];
                   array_push($Carrier,
                    array(
                            'id' => $id,
                            'name'=>$name )
                    );
                }
            }
        }

        return $Carrier;
    }
}
?>
