<?php  
class order_print extends cdek_integrator implements exchange {
	
	private $post;
	
	protected $method = 'v2/print/orders';
	
	public function setData($data) {
		$this->post = $this->createPost($data);
	}
	
	public function getData(){
		return $this->post;
	}
	
	private function createPost($data = array()) {

		$prints['orders'] = array();
				
		if (!empty($data)) {
			
			if (!empty($data['order'])) {
				
				$prints['copy_count'] = isset($data['copy_count']) ? (int)$data['copy_count'] : 2;
							
				foreach ($data['order'] as $order_info) {
					
					if (isset($order_info['dispatch_number'])) {
						$prints['orders'][] = array('order_uuid' => $order_info['dispatch_number']);
					} else {
						$prints['orders'][] = array('cdek_number' => $order_info['order_id']);
					}
				}
				
			} else {
				throw new Exception('Component "order_print" invalid argument.');
			}
		
		}
		
		return $prints;
	}
	
}

?>