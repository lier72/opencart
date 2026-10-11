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

	// Generation is asynchronous. Keep the UUID so another click resumes the same job.
	public function fetchPdf($api, &$uuid) {
		if (!$uuid) {
			$response = $api->sendData($this);
			$errors = $this->getResponseErrors($response, true);
			if ($errors) throw new RuntimeException(implode('; ', $errors));
			$uuid = $response['entity']['uuid'];
		}
		for ($attempt = 0; $attempt < 3; $attempt++) {
			$info = $api->loadComponent('order_print_info');
			$info->setMetod($uuid);
			$response = $api->sendData($info);
			$errors = $this->getResponseErrors($response, true);
			if ($errors) {
				$uuid = '';
				throw new RuntimeException(implode('; ', $errors));
			}
			$statuses = isset($response['entity']['statuses']) ? $response['entity']['statuses'] : array();
			// Several transitions can occur within one second. Break timestamp ties
			// by lifecycle order so ACCEPTED cannot hide READY (or REMOVED).
			usort($statuses, function ($a, $b) {
				$time_a = isset($a['date_time']) ? strtotime($a['date_time']) : 0;
				$time_b = isset($b['date_time']) ? strtotime($b['date_time']) : 0;
				if ($time_a != $time_b) return $time_a < $time_b ? 1 : -1;
				$rank = array('ACCEPTED' => 1, 'PROCESSING' => 2, 'READY' => 3, 'INVALID' => 4, 'REMOVED' => 5);
				return (isset($rank[$b['code']]) ? $rank[$b['code']] : 0) - (isset($rank[$a['code']]) ? $rank[$a['code']] : 0);
			});
			$state = isset($statuses[0]['code']) ? $statuses[0]['code'] : '';
			if ($state === 'INVALID' || $state === 'REMOVED') {
				$uuid = '';
				throw new RuntimeException('СДЭК: квитанция недоступна (' . $state . '). Повторите запрос.');
			}
			// CDEK documents entity.url as present only when the file is ready.
			// Download via our authenticated endpoint, not an arbitrary returned URL.
			if ($state === 'READY' || !empty($response['entity']['url'])) {
				$download = $api->loadComponent('order_download_pdf');
				$download->setMetod($uuid);
				$pdf = $api->sendData($download);
				if (!self::isPdf($pdf)) {
					$errors = $this->getResponseErrors(is_string($pdf) ? json_decode($pdf, true) : $pdf);
					throw new RuntimeException('СДЭК не вернул PDF. ' . implode('; ', $errors));
				}
				return $pdf;
			}
			if (!in_array($state, array('ACCEPTED', 'PROCESSING'), true)) {
				throw new RuntimeException('СДЭК: неизвестный статус квитанции (' . ($state !== '' ? $state : 'статус отсутствует') . ').');
			}
			if ($attempt < 2) $this->waitForPdf();
		}
		throw new RuntimeException('СДЭК ещё формирует квитанцию. Повторите загрузку через несколько секунд.');
	}

	protected function waitForPdf() {
		usleep(500000);
	}

	public static function isPdf($data) {
		return is_string($data) && strncmp($data, '%PDF-', 5) === 0;
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
