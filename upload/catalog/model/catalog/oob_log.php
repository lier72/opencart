<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.x.x.x. From Webkul  http://webkul.com    #
################################################################################################
class oob_log{

	public function getFilename(){
		$name='oob_log_'.date('m-d-Y').'.log';
		return $name;
	}

	public function logMessage($fname,$lineno,$message,$level='ERROR'){
		$filename = $this->getFilename();
    $log = new Log($filename);

		$formatted_message = '*'.$level.'*'."\t".date('Y/m/d - H:i:s').': '.'File name: '.basename($fname)." - ".'Line No: '.$lineno."\r\nMessage: ".$message."\r\n";
    $log->write($formatted_message);
	}
}
