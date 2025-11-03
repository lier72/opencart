<?php  
class order_print_info extends cdek_integrator implements exchange {
	
	protected $method = 'v2/print/orders/';
	
	private $json;
	private $metod_pdf;
	
	public function getData() {
		return;
	}
	
	public function setMetod($metod_pdf) {
		$this->method = $this->method . $metod_pdf;
	}	
}

?>