<?php
class ModelCatalogConnection extends Model {

public function make()
    {
		$userData = $this->db->query("SELECT * FROM ".DB_PREFIX."odoo_confg")->row;
		if($userData){
			if (!class_exists('xmlrpc_client'))
	            include_once 'xmlrpc.inc';
	            $client = new xmlrpc_client($userData['url'].":".$userData['port']."/xmlrpc/object");
	            $sock   = new xmlrpc_client($userData['url'].":".$userData['port']."/xmlrpc/common");
	            $msg    = new xmlrpcmsg('login');
	            $msg->addParam(new xmlrpcval($userData['db_name'], 'string'));
	            $msg->addParam(new xmlrpcval($userData['user'], 'string'));
	            $msg->addParam(new xmlrpcval($userData['password'], 'string'));
	            $resp = $sock->send($msg);
	            if ($resp->faultCode()) {
	                $error_message  = $resp->faultString();
					return array(
						'status'=>False,
						);
	            } else {
	                $userId = $resp->value()->scalarval();
					if ($userId <= 0){
						return array(
							'status'=>False,
						);
					}
	                else {
						return array(
						'status'=>True,
						'client'=>$client,
						'userId'=>$userId,
						'pwd'=>$userData['password'],
						'url'=>$userData['url'],
						'port'=>$userData['port'],
						'db'=>$userData['db_name'],
            'wkproducttype'=>$userData['wkproducttype'],
						);
					}
			}
		}else{
			return array(
				'status'=>False,
			);
		}
    }
}
?>
