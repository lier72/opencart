<?php  
class order_info extends cdek_integrator implements exchange {
	protected $method = 'v2/orders/';
	
	private $json;
	
	public function getData() {
		return;
	}
	
	public function setMetod($metod_info) {
		$this->method = $this->method . $metod_info;
	}	
	
}

?>