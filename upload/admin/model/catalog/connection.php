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

            function handleError($errno, $errstr, $errfile, $errline, array $errcontext)
			{
			    // error was suppressed with the @-operator
			    if (0 === error_reporting()) {
			        return false;
			    }
			    //throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
			}
			set_error_handler('handleError');

			try {
			    $resp = $sock->send($msg);
			}
			catch (Exception $e) {
			    // ...
			}

            //$resp = $sock->send($msg);
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
				}else {
					return array(
					'status'=>true,
					'sock'=>false,
					'client'=>$client,
					'userId'=>$userId,
					'pwd'=>$userData['password'],
					'wkproducttype'=>$userData['wkproducttype'],
					'url'=>$userData['url'],
					'port'=>$userData['port'],
					'db'=>$userData['db_name'],
					);
				}
			}
		}else{
			return array(
				'status'=>False,
			);
		}

    }

    public function chkConnection($userData)
    {
		if($userData){

			if (!class_exists('xmlrpc_client'))
	            include_once 'xmlrpc.inc';
	            $client = new xmlrpc_client($userData['wkurl'].":".$userData['wkport']."/xmlrpc/object");
	            $sock   = new xmlrpc_client($userData['wkurl'].":".$userData['wkport']."/xmlrpc/common");
	            $msg    = new xmlrpcmsg('login');
	            $msg->addParam(new xmlrpcval($userData['wkdb_name'], 'string'));
	            $msg->addParam(new xmlrpcval($userData['wkuser'], 'string'));
	            $msg->addParam(new xmlrpcval($userData['wkpassword'], 'string'));

	            function handleError($errno, $errstr, $errfile, $errline, array $errcontext)
				{
				    // error was suppressed with the @-operator
				    if (0 === error_reporting()) {
				        return false;
				    }
				    //throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
				}
				set_error_handler('handleError');

				try {
				    $resp = $sock->send($msg);
				}
				catch (Exception $e) {
				    // ...
				}

	            //$resp = $sock->send($msg);
	            if ($resp->faultCode()) {
	                $error_message  = $resp->faultString();
					return array(
						'error'=>$error_message,
						);
	            } else {
	                $userId = $resp->value()->scalarval();
					if ($userId <= 0){
						return array(
						'error'=>False,
						);
					}else {
						return array(
							'success'=>' Congratulation, It\'s successfully connected with Odoo through XML-RPC. ',
						);
					}
				}
		}else{
				return array(
				'error'=>False,
				);

		}

    }
}
?>
