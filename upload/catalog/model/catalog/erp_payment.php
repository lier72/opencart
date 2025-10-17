<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.x.x.x. From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpPayment extends Model {

	public function check_all_payment_methods($userId, $client, $db, $pwd, $cart_user){

        $is_error      = 0;
        $error_message = '';
        $ids           = '';
        $count         = 0;

        $data = $this->db->query("SELECT `code` from `" . DB_PREFIX . "extension` WHERE `code` NOT IN (SELECT `opencart_payment_cod` FROM `" . DB_PREFIX . "erp_payment_merge` WHERE `is_synch` = 0) AND type = 'payment' ")->rows;

        if (count($data) == 0) {

            //Nothing to export
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                'ids' => $ids
            );
        }

        foreach ($data as $payment) {

        	$extension = basename($payment['code'], '.php');
            $this->language->load('payment/' . $extension);
            $payment['name']  = $this->language->get('heading_title');

            $opencart_id = $payment['code'];
            $check         = $this->db->query("SELECT * from `" . DB_PREFIX . "erp_payment_merge` where `opencart_payment_cod`='$opencart_id'")->row;

            $module = $payment['name'];
            if (isset($check['erp_payment_id']) AND $check['erp_payment_id'] <= 0) {
                if ($module == false) {
                    continue;
                }
            }else{
                $create = $this->merge_payment_method($module, $userId, $client, $db, $pwd);
                if ($create['erp_id'] > 0) {
                    $this->addto_payment_merge($create['erp_id'], $payment['name'], $opencart_id, $cart_user);
                    $count = 1;
                } else {
                    $is_error = 1;
                    $error_message .= $create['error_message'] . ',';
                    $ids .= $opencart_id . ',';
                }
            }
        }
        return array(
            'is_error' => $is_error,
            'error_message' => $error_message,
            'value' => $count,
            'ids' => $ids
        );
    }

    public function merge_payment_method($name, $userId, $client, $db, $pwd){
        $payment_array = array(
            'name'=>new xmlrpcval($name, "string"),
            'type'=>new xmlrpcval('cash', "string")
        );
        $payment     = new xmlrpcmsg('execute');
        $payment->addParam(new xmlrpcval($db, "string"));
        $payment->addParam(new xmlrpcval($userId, "int"));
        $payment->addParam(new xmlrpcval($pwd, "string"));
        $payment->addParam(new xmlrpcval("extra.function", "string"));
        $payment->addParam(new xmlrpcval("create_payment_method", "string"));
        $payment->addParam(new xmlrpcval($payment_array, "struct"));
        $resp = $client->send($payment);

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

    public function addto_payment_merge($erp_id, $name, $op_id, $cart_user = 'Front End'){
        $data = array(
            'erp_payment_id' => $erp_id,
            'opencart_payment_id' => $op_id,
            'created_by' => $cart_user,
            'name' => $name
        );
        $this->db->query("INSERT INTO  `" . DB_PREFIX . "erp_payment_merge` SET erp_payment_id = '$erp_id' , opencart_payment_cod = '$op_id' , name = '$name', created_by = '$cart_user', created_on = NOW() ");

    }

    public function check_specific_payment_method($name, $userId, $client, $db, $pwd){
        $check =  $this->db->query("SELECT * from `" . DB_PREFIX . "erp_payment_merge` where `name`='$name'")->row;
        if (isset($check['erp_payment_id']) AND $check['erp_payment_id'] <= 0) {

            $extension = basename($check['code'], '.php');
            $this->language->load('payment/' . $extension);
            $code  = $this->language->get('heading_title');

            $create = $this->merge_payment_method($name, $userId, $client, $db, $pwd);
            $this->addto_payment_merge($create['erp_id'], $name, $code );
            return $create['erp_id'];
        } else {
            return $check['erp_payment_id'];
        }
    }

    public function chk_payment_merge($payment_code){

        $chk = $this->db->query("SELECT `id` from `" . DB_PREFIX . "erp_payment_merge` WHERE `opencart_payment_cod`='$payment_code'")->row;
        return $chk;
    }

    public function getErpPaymentArray($userId, $client, $db, $pwd, $cart_user) {

        $Payment = array();

        if ($userId == "error"){
            array_push($Payment, array('name' => 'Not Available(Connection Error)', 'id' => ''));
            return $Payment;
        }else{

            $key1 = array(  new xmlrpcval('bank', 'string'),
                            new xmlrpcval('cash', 'string') );
            $key = array(new xmlrpcval(
                        array(  new xmlrpcval('type' , "string"),
                                new xmlrpcval('in',"string"),
                                new xmlrpcval($key1,"array")),"array"),
                );
            $msg_ser = new xmlrpcmsg('execute');
            $msg_ser->addParam(new xmlrpcval($db, "string"));
            $msg_ser->addParam(new xmlrpcval($userId, "int"));
            $msg_ser->addParam(new xmlrpcval($pwd, "string"));
            $msg_ser->addParam(new xmlrpcval("account.journal", "string"));
            $msg_ser->addParam(new xmlrpcval("search", "string"));
            $msg_ser->addParam(new xmlrpcval($key, "array"));
            $resp0    = $client->send($msg_ser);
            if ($resp0->faultCode()) {
							$log = new oob_log();
							$log->logMessage(__FILE__,__LINE__,$resp0->raw_data,'CRITICAL: ');
                array_push($Payment, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
                return $Payment;
            }
            else
            {
                $val    = $resp0->value()->me['array'];
                $key1 = array(new xmlrpcval('id','int') , new xmlrpcval('name', 'string'));
                $msg_ser1 = new xmlrpcmsg('execute');
                $msg_ser1->addParam(new xmlrpcval($db, "string"));
                $msg_ser1->addParam(new xmlrpcval($userId, "int"));
                $msg_ser1->addParam(new xmlrpcval($pwd, "string"));
                $msg_ser1->addParam(new xmlrpcval("account.journal", "string"));
                $msg_ser1->addParam(new xmlrpcval("read", "string"));
                $msg_ser1->addParam(new xmlrpcval($val, "array"));
                $msg_ser1->addParam(new xmlrpcval($key1, "array"));
                $resp1   = $client->send($msg_ser1);
                if ($resp1->faultCode()) {
									$log = new oob_log();
									$log->logMessage(__FILE__,__LINE__,$resp1->raw_data,'CRITICAL: ');
                    $msg = 'Not Available- Error: '.$resp1->faultString();
                    array_push($Payment, array('name' => $msg, 'id' => ''));
                    return $Payment;
                }
                else
                {
                    $value_array=$resp1->value()->scalarval();
                    $count = count($value_array);

                    for($x=0;$x<$count;$x++)
                    {
                       $id = $value_array[$x]->me['struct']['id']->me['int'];
                       $name = $value_array[$x]->me['struct']['name']->me['string'];
                       array_push($Payment,
                        array(
                                'id' => $id,
                                'name'=>$name )
                        );
                    }
                }
            }

            return $Payment;
        }
    }
}
