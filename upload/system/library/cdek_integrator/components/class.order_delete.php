<?php  
class order_delete extends cdek_integrator implements exchange {
	
	protected $method = 'v2/orders/';
	
	private $json;
	
	public function setData($data) {
		$this->json = $this->createJson($data);
	}
	
	public function setNumber($number) {
		$this->method = $this->method .  $number;
	}
	
	public function getData(){
		return $this->json;
	}
	
	private function createJson($orders = array()) {
		
		$json['delete'] = true;
		
		return $json;
	}
	
}

?>