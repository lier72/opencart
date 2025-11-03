<?php  
class order_download_pdf extends cdek_integrator implements exchange {
	
	protected $method = 'v2/print/orders/';
	
	private $json;
	private $metod_download;
	
	public function setMetod($number) {
		$this->method = 'v2/print/orders/' . $number . '.pdf';
	}
	
	public function getData(){
		return;
	}
	
	public function getParser() {
		return new parser_original();
	}	
}

?>