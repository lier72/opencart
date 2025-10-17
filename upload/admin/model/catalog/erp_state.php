<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 1.5.1.x From Webkul  http://webkul.com    #
################################################################################################
class ModelCatalogErpState extends Model {      
    //iso code of state
    public function get_state_dtls($id_state){
        $state_dtls = $this->db->query("SELECT * from `" . DB_PREFIX . "zone` where `zone_id`='$id_state' ")->row;
        return $state_dtls;
    }
    public function check_state($userId, $client, $iso_code, $name, $id_country, $db, $pwd){
        $erp_state_id = $this->search_state($iso_code, $userId, $client, $db, $pwd);
        if ($erp_state_id > 0)
            return $erp_state_id;
        else {
            $erp_state_id = $this->create_state($userId, $client, $iso_code, $name, $id_country, $db, $pwd);
            return $erp_state_id;
        }
    }
    private function search_state($code, $userId, $client, $db, $pwd){
        $key     = array(
            new xmlrpcval(array(
                new xmlrpcval("code", "string"),
                new xmlrpcval("=", "string"),
                new xmlrpcval($code, "string")
            ), "array")
        );
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("res.country.state", "string"));
        $msg_ser->addParam(new xmlrpcval("search", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "array"));
        $resp    = $client->send($msg_ser);
        $val_ser = $resp->value();
        $ids     = $val_ser->scalarval();
        if($ids)
        $id      = $ids[0]->me;
        if (isset($id) AND $id > 0)
            return $id['int'];
        else
            return 0;
    }
    private function create_state($userId, $client, $code, $name, $id_country, $db, $pwd){
        $key     = array(
            'name' => new xmlrpcval($name, "string"),
            'code' => new xmlrpcval($code, "string"),
            'country_id' => new xmlrpcval($id_country, "string")
        );
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("res.country.state", "string"));
        $msg_ser->addParam(new xmlrpcval("create", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
        $resp     = $client->send($msg_ser);
        $st_id    = $resp->value()->me;
        $state_id = $st_id["int"];
        return $state_id;
    }
}
?>