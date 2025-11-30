<?php
class ControllerExtensionShippingCdek extends Controller {

	public function dev() {
		if(!isset($this->request->get['show'])) {
			echo '<pre>'.print_r($this->session->data['cdek'],1).'</pre>';
			exit;
		}

		switch ($this->request->get['show']) {
			case 'all':
				echo '<pre>'.print_r($this->session->data,1).'</pre>';
				break;
			
			default:
				echo '<pre>'.print_r($this->session->data[$this->request->get['show']],1).'</pre>';
				break;
		}
		
	}
	public function del() {
		if(isset($this->request->get['key'])) {
			unset($this->session->data['cdek'][$this->request->get['key']]);
		} else {
			unset($this->session->data['cdek']);
		}
		
	}

	public function __construct($registry) {
		parent::__construct($registry);
		
		if(!isset($this->session->data['cdek'])) {
			$this->session->data['cdek'] = array();
		}
	}

	public function getPvzList() {
		if(!isset($this->request->post['tariff_type']) || !$this->request->post['tariff_type']) {
			$this->jsonRespone(false, 'Не получен тип тарифа');
		}

		$tariff_type = $this->request->post['tariff_type'];

		// Read PVZ data from shared cache files (using cache key from session)
		if ($tariff_type == 'PVZ') {
			$cache_key = isset($this->session->data['cdek']['pvz_cache_key']) ?
						 $this->session->data['cdek']['pvz_cache_key'] :
						 'cdek_pvz_' . $this->session->data['cdek']['city'];
			$cache_file = DIR_CACHE . $cache_key . '.cache';

			if (file_exists($cache_file)) {
				$pvz_list = unserialize(file_get_contents($cache_file));
				$this->jsonRespone(true, 'Список пунктов выдачи', $pvz_list);
			} else {
				$this->jsonRespone(false, 'Нет списка пвз в кеше');
			}
		} elseif ($tariff_type == 'POSTAMAT') {
			$cache_key = isset($this->session->data['cdek']['postamat_cache_key']) ?
						 $this->session->data['cdek']['postamat_cache_key'] :
						 'cdek_postamat_' . $this->session->data['cdek']['city'];
			$cache_file = DIR_CACHE . $cache_key . '.cache';

			if (file_exists($cache_file)) {
				$postamat_list = unserialize(file_get_contents($cache_file));
				$this->jsonRespone(true, 'Список пунктов выдачи', $postamat_list);
			} else {
				$this->jsonRespone(false, 'Нет списка постаматов в кеше');
			}
		} else {
			$this->jsonRespone(true, 'Список пунктов выдачи', array());
		}
	}

	public function selectPvz() {
		$json = array();
		$json['status'] = false;
		$json['message'] = 'Неизвестная ошибка';
		$json['data'] = array();

		if(!isset($this->request->post['pvz_code']) || !$this->request->post['pvz_code']) {
			$this->jsonRespone(false, 'Не получен код пвз');
		}

		$pvz_code = $this->request->post['pvz_code'];

		$tariff = '';
		if(isset($this->request->post['tariff'])) {
			$tariff = $this->request->post['tariff'];
		}

		$this->session->data['cdek']['tariff'] = $tariff;

		$pvz_code = $this->request->post['pvz_code'];

		// Read PVZ data from shared cache files (using cache key from session)
		$pvz_cache_key = isset($this->session->data['cdek']['pvz_cache_key']) ?
						 $this->session->data['cdek']['pvz_cache_key'] :
						 'cdek_pvz_' . $this->session->data['cdek']['city'];
		$postamat_cache_key = isset($this->session->data['cdek']['postamat_cache_key']) ?
							  $this->session->data['cdek']['postamat_cache_key'] :
							  'cdek_postamat_' . $this->session->data['cdek']['city'];

		$pvz_cache_file = DIR_CACHE . $pvz_cache_key . '.cache';
		$postamat_cache_file = DIR_CACHE . $postamat_cache_key . '.cache';

		if (!file_exists($pvz_cache_file) && !file_exists($postamat_cache_file)) {
			$this->jsonRespone(false, 'Нет списка пвз в кеше');
		}

		$this->session->data['cdek']['pvz'] = $pvz_code;

		$pvz_info = '';
		$pvz_address = '';

		// Search in PVZ cache
		if (file_exists($pvz_cache_file)) {
			$pvz_list = unserialize(file_get_contents($pvz_cache_file));
			if (!empty($pvz_list)) {
				foreach ($pvz_list as $pvz) {
					if($pvz['Code'] == $pvz_code) {
						$pvz_info = $pvz['Address'].' Телефон: '.$pvz['Phone'];
						$pvz_address = $pvz['Address'];
					}
				}
			}
		}

		// Search in POSTAMAT cache
		if (file_exists($postamat_cache_file)) {
			$postamat_list = unserialize(file_get_contents($postamat_cache_file));
			if (!empty($postamat_list)) {
				foreach ($postamat_list as $pvz) {
					if($pvz['Code'] == $pvz_code) {
						$pvz_info = $pvz['Address'].' Телефон: '.$pvz['Phone'];
						$pvz_address = $pvz['Address'];
					}
				}
			}
		}

		$this->session->data['cdek']['pvzaddress'] = $pvz_address;
		$this->session->data['cdek']['pvzinfo'] = $pvz_info;


		$this->jsonRespone(true, 'Пвз выбран', array('address' => $pvz_address));
	}

	private function jsonRespone($status, $message, $data = array()) {
		$json = array();
		$json['status'] = $status;
		$json['message'] = $message;
		$json['data'] = $data;

		echo json_encode($json);
		exit;
	}

	public function checkTariffPvz() {
		$tariff = '';
		if(!isset($this->request->post['tariff']) || !$this->request->post['tariff']) {
			$this->jsonRespone(true, 'Тариф не выбран');
		}

		$tariff = $this->request->post['tariff'];
		if(stripos($tariff, 'MRG') !== false) 
		{
			if(!isset($this->session->data['cdek'])) {
				$this->jsonRespone(false, 'Для выбранного тарифа нужно выбрать пвз');
			}

			if(!isset($this->session->data['cdek']['pvz']) || !$this->session->data['cdek']['pvz']) {
				$this->jsonRespone(false, 'Для выбранного тарифа нужно выбрать пвз');
			}
		}

		$this->jsonRespone(true, 'Ок', array('tariff'=>$tariff));
	}

	public function renderTpl($data) {

		$tpl = '';

		$data['selected_pvz'] = array();
		if(isset($this->session->data['cdek'])) 
		{
			if(isset($this->session->data['cdek']['tariff']) && $this->session->data['cdek']['tariff']) 
			{
				if(isset($this->session->data['cdek']['pvz']) && $this->session->data['cdek']['pvz'] && isset($this->session->data['cdek']['pvzaddress']) && $this->session->data['cdek']['pvzaddress']) 
				{
					$data['selected_pvz'][$this->session->data['cdek']['tariff']] = $this->session->data['cdek']['pvzaddress'];
				}
			}
		}
        //xdebug_var_dump($this->load->view('extension/shipping/sdek', $data));
		return $this->load->view('extension/shipping/sdek', $data);
	}
}
?>