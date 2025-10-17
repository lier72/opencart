<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 2.3.x.x From Webkul  http://webkul.com    #
################################################################################################
class ModelCatalogErpCountry extends Model {   
    // iso code of country.
    public function get_iso($id_country){
        $country_iso_code = $this->db->query("SELECT `iso_code_2` from `" . DB_PREFIX . "country` where country_id='$id_country'")->row;
        return $country_iso_code['iso_code_2'];
    }
    // name of country.
    public function get_country_name($id_country){
        $country_name = $this->db->query("SELECT `name` from `" . DB_PREFIX . "country` where `country_id`='$id_country'")->row;
        return $country_name['name'];
    }
    public function get_country($iso_code, $name, $userId, $client, $db, $pwd){
        $country_id = $this->search_country($iso_code, $userId, $client, $db, $pwd);
        if ($country_id > 0) {
            return $country_id;
        } else {
            $country_id = $this->create_country($name, $iso_code, $userId, $client, $db, $pwd);
            return $country_id;
        }
    }
    //To search country at erp end.
    private function search_country($code, $userId, $client, $db, $pwd){
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
        $msg_ser->addParam(new xmlrpcval("res.country", "string"));
        $msg_ser->addParam(new xmlrpcval("search", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "array"));
        $resp    = $client->send($msg_ser);
        $val_ser = $resp->value();
        $ids     = $val_ser->scalarval();
        $id      = $ids[0]->me;
        if ($id > 0)
            return $id['int'];
        else
            return 0;
    }
    
    //To create country country at erp end.
    private function create_country($name, $code, $userId, $client, $db, $pwd){
        $key     = array(
            'name' => new xmlrpcval($name, "string"),
            'code' => new xmlrpcval($code, "string")
        );
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("res.country", "string"));
        $msg_ser->addParam(new xmlrpcval("create", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
        $resp       = $client->send($msg_ser);
        $ct_id      = $resp->value()->me;
        $country_id = $ct_id["int"];
        return $country_id;
    }
}
?>