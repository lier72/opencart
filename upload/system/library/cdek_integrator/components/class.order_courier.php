<?php  
class order_courier extends cdek_integrator implements exchange {
	
	protected $method = 'v2/intakes';

	private $post;

	public $number;
	
	public function setData($data) {
		$this->post = $this->createPost($data);
	}

	public function setNumber($number) {
		$this->number = $number;
	}
	
	public function getData(){
		return $this->post;
	}

	private function createPost($data = array()) {
		
		$post['order_uuid'] = $this->number;

		if ($data['date'] != '') {
			$post['intake_date'] = $data['date'];
		}

		if ($data['time_beg'] != '') {
			$post['intake_time_from'] = $data['time_beg'];
		}

		if ($data['time_end'] != '') {
			$post['intake_time_to'] = $data['time_end'];
		}

		if ($data['lunch_beg'] != '') {
			$post['lunch_time_from'] = $data['lunch_beg'];
		}

		if ($data['lunch_end'] != '') {
			$post['lunch_time_to'] = $data['lunch_end'];
		}
		
		return $post;
	}
	
}

?>