<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.x.x.x. From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpCarrier extends Model {

    public function check_specific_carrier($carrier_id, $userId, $client, $db, $pwd){

        $carrier_id = explode('.', $carrier_id);
        $erp   = $this->check_carrier($carrier_id[0]);
        $check = $this->db->query("SELECT `code` from `" . DB_PREFIX . "extension` WHERE `code` = '".$carrier_id[0]."' AND type='shipping'")->row;

        $extension = basename($check['code'], '.php');
        $this->language->load('shipping/' . $extension);
        $check['name']  = $this->language->get('heading_title');

        if ($erp[0] == 0) {
            $create = $this->create_carrier($carrier_id[0], $check['code'], $userId, $client, $cart_user = 'Front End', $db, $pwd);
            $this->addto_carrier_merge($carrier_id[0], $create['erp_id'], $check['code']);
            $erp_id=$create['erp_id'];
        }
        if ($erp[0] < 0) {
            $update = $this->update_carriers($erp[1], $check['code'], $check['name'], $userId, $client, $cart_user = 'Front End', $db, $pwd);
            $erp_id = $erp[1];
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

    public function create_carrier($id_carrier, $name, $userId, $client, $cart_user = 'Front End', $db, $pwd){

        $key = array(
            'name' => new xmlrpcval($name, "string"),
            'opencart' => new xmlrpcval('opencart', "string"),
        );
        $context_erp = array(
            'opencart' => new xmlrpcval('opencart', "string"),
        );
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("delivery.carrier", "string"));
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

    public function update_carriers($erp_carrier_id, $id_carrier, $name, $userId, $client,$cart_user = 'Front End',$db, $pwd){

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
}
?>
