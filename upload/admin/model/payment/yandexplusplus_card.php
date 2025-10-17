<?php
class ModelPaymentyandexpluspluscard extends Model {
	private $key;
	
	public function encrypt($value, $key) {
		$key = hash('sha256', $key, true);
		return strtr(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, hash('sha256', $key, true), $value, MCRYPT_MODE_ECB)), '+/=', '-_,');
	}
	
	public function decrypt($value, $key) {
		$key = hash('sha256', $key, true);
		return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, hash('sha256', $key, true), base64_decode(strtr($value, '-_,', '+/=')), MCRYPT_MODE_ECB));
	}
}
?>