<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpAddress extends Model {

    public function getCustomerAddress($customerId){

    	$results = $this->db->query("SELECT * FROM ".DB_PREFIX."address WHERE customer_id = '".(int)$customerId."'")->rows;

    	return $results;
    }

    public function getErpAddressArray($erp_customer_id, $userId, $client, $db, $pwd) {
        $Address = array();
        $key     = array();
        $key = array(new xmlrpcval(
                        array(  new xmlrpcval('parent_id' , "string"),
                                new xmlrpcval('=',"string"),
                                new xmlrpcval($erp_customer_id,"int")),"array"),
                    // new xmlrpcval(
                    //     array(  new xmlrpcval('customer' , "string"),
                    //             new xmlrpcval('=',"string"),
                    //             new xmlrpcval(true,"boolean")),"array"),
                );
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("res.partner", "string"));
        $msg_ser->addParam(new xmlrpcval("search", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "array"));
        $resp0    = $client->send($msg_ser);
        if ($resp0->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp0->raw_data,'CRITICAL: ');
            array_push($Address, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
            return $Address;
        }else{
            $val    = $resp0->value()->me['array'];
            $key1 = array(
                            new xmlrpcval('id','int'),
                            new xmlrpcval('name', 'string'),
                            new xmlrpcval('street', 'string'),
                            new xmlrpcval('city', 'string'),
                    );
            $msg_ser1 = new xmlrpcmsg('execute');
            $msg_ser1->addParam(new xmlrpcval($db, "string"));
            $msg_ser1->addParam(new xmlrpcval($userId, "int"));
            $msg_ser1->addParam(new xmlrpcval($pwd, "string"));
            $msg_ser1->addParam(new xmlrpcval("res.partner", "string"));
            $msg_ser1->addParam(new xmlrpcval("read", "string"));
            $msg_ser1->addParam(new xmlrpcval($val, "array"));
            $msg_ser1->addParam(new xmlrpcval($key1, "array"));
            $resp1   = $client->send($msg_ser1);
            if ($resp1->faultCode()) {
                $log = new oob_log();
                $log->logMessage(__FILE__,__LINE__,$resp1->raw_data,'CRITICAL: ');
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Address, array('label' => $msg, 'id' => ''));
                return $Address;
            }else {
                $value_array=$resp1->value()->scalarval();
                $count = count($value_array);
                if($count>0){
                    for($x=0;$x<$count;$x++){
                       $id = $value_array[$x]->me['struct']['id']->me['int'];
                       $name = $value_array[$x]->me['struct']['name']->me['string'];
                       $street = $value_array[$x]->me['struct']['street']->me['string'];
                       $city = $value_array[$x]->me['struct']['city']->me['string'];
                       array_push($Address,
                        array(
                                'id' => $id,
                                'name'=>$name.', '.$street.', '.$city
                            )
                        );
                    }
                }
            }
        }
        return $Address;
    }
}
?>
