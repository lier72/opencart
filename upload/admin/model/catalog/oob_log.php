<?php


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
		// return (bool)file_put_contents(_PS_MODULE_DIR_.'prestaerp/logs/'.$filename, $formatted_message, FILE_APPEND);
	}
}
