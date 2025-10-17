<?php
/**
 * Расчёт стоимости доставки СДЭК
 * Модуль для интернет-магазинов (ИМ)
 *
 * @version 1.0
 * @since 21.06.2012
 * @link http://www.edostavka.ru/integrator/
 * @see 3197
 * @author Tatyana Shurmeleva
 */
class CalculatePriceDeliveryCdek {

	//версия модуля
	private $version = "2.0";
	//url для получения данных по отправке
    private $jsonUrl = 'https://api.cdek.ru/v2/calculator/tariff';
	//авторизация ИМ
	private $auth_data;

	//id города-отправителя
	private $senderCityId;
	//id города-получателя
	private $receiverCityId;
	//id тарифа
	private $tariffId;
	//id способа доставки (склад-склад, склад-дверь)
	private $modeId;
	//массив мест отправления
	public $goodsList;
	//массив id тарифов
	public $tariffList;
	//результат расчёта стоимости отправления ИМ
	private $result;
	//результат в случае ошибочного расчёта
	
    private $error;
	//планируемая дата заказа
	public $dateExecute;

	/**
	 * конструктор
	 */
	public function __construct($initiator = null) {
	    $this->dateExecute = date('Y-m-d');
	    if($initiator) {
			$this->jsonUrl = $initiator->getInfo()->getAjaxUrl().'v2/calculator/tariff';
			$this->auth_data = $initiator->getInfo()->getAuthToken();
	    } else {
			$this->jsonUrl = 'https://api.cdek.ru/v2/calculator/tariff';
		}
		
	}

	/**
	 * Установка планируемой даты отправки
	 *
	 * @param string $date дата планируемой отправки, например '2012-06-25'
	 */
	public function setDateExecute($date) {
		$this->dateExecute = date($date);
	}

	/**
	 * Город-отправитель
	 *
	 * @param int $id города
	 */
	public function setSenderCityId($id) {
		$id = (int) $id;
		if($id == 0) {
			throw new Exception("Неправильно задан город-отправитель.");
		}
		$this->senderCityId = $id;
	}

	/**
	 * Город-получатель
	 *
	 * @param int $id города
	 */
	public function setReceiverCityId($id) {
		$id = (int) $id;
		if($id == 0) {
			throw new Exception("Неправильно задан город-получатель.");
		}
		$this->receiverCityId = $id;
	}

	/**
	 * Устанавливаем тариф
	 *
	 * @param int $id тарифа
	 */
	public function setTariffId($id) {
		$id = (int) $id;
		if($id == 0) {
			throw new Exception("Неправильно задан тариф.");
		}
		$this->tariffId = $id;
	}

	/**
	 * Устанавливаем режим доставки (дверь-дверь=1, дверь-склад=2, склад-дверь=3, склад-склад=4)
	 *
	 * @param int $id режим доставки
	 */
	public function setModeDeliveryId($id) {
		$id = (int) $id;
		if(!in_array($id, array(1,2,3,4))) {
			throw new Exception("Неправильно задан режим доставки.");
		}
		$this->modeId = $id;
	}

	/**
	 * Добавление места в отправлении
	 *
	 * @param int $weight вес, килограммы
	 * @param int $length длина, сантиметры
	 * @param int $width ширина, сантиметры
	 * @param int $height высота, сантиметры
	 */
	public function addGoodsItemBySize($weight, $length, $width, $height) {
		//проверка веса
		$weight = (float) $weight;
		if ($weight == 0.00) {
			throw new Exception("Неправильно задан вес места № " . (count($this->getGoodslist())+1) . ".");
		}
		//проверка остальных величин
		$paramsItem = array("длина" 	=> $length,
							"ширина" 	=> $width,
							"высота" 	=> $height);
		foreach ($paramsItem as $k => $param) {
			$param = (int) $param;
			if ($param == 0) {
				throw new Exception("Неправильно задан параметр '" . $k . "' места № " . (count($this->getGoodslist())+1) . ".");
			}
		}
		$this->goodsList[] = array( 'weight' 	=> $weight,
									'length' 	=> $length,
									'width' 	=> $width,
									'height' 	=> $height);
	}

	/**
	 * Получение массива мест отправления
	 *
	 * @return array
	 */
	public function getGoodslist() {
		if (!isset($this->goodsList)) {
			return NULL;
		}
		return $this->goodsList;
	}

	/**
	 * добавление тарифа в список тарифов с приоритетами
	 *
	 * @param int $id тариф
	 * @param int $priority default false приоритет
	 */
	public function addTariffPriority($id, $priority = 0) {
		$id = (int) $id;
		if ($id == 0) {
			throw new Exception("Неправильно задан id тарифа.");
		}
        $priority = ($priority > 0) ? $priority : count($this->tariffList)+1;
		$this->tariffList[] = array( 'priority' => $priority,
									 'id' 		=> $id);
	}

	/**
	 * Получение массива заданных тарифов
	 *
	 * @return array
	 */
	private function _getTariffList() {
		if(!isset($this->tariffList)) {
			return NULL;
		}
		return $this->tariffList;
	}

	/**
	 * Выполнение POST-запроса на сервер для получения данных
	 * по запрашиваемым параметрам.
	 *
	 *
	 */
	private function _getRemoteData($data) {
				
		$data_string = json_encode($data);

		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $this->jsonUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
											'Authorization: Bearer ' . $this->auth_data['access_token'],
		                                    'Content-Type: application/json')
		                                    );
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		$result = curl_exec($ch);			
		return json_decode($result, true);
	}

	/**
	 * Расчет стоимости доставки
	 *
	 * @return bool
	 */
	public function calculate() {
		//формируем массив для отправки curl-post-запроса
		//передаём только явно заданные параметры, установленные ИМ
		//всю проверку и обработку будем делать на стороне сервера
		$data = array();
		//получение всех свойств текущего объекта не работает, т.к. у нас свойства private
		//поэтому определим массив $data явно
		//проверяем на установленную переменную и не-NULL-значение

		//версия модуля
		isset($this->version) ? $data['version'] = $this->version : '';
		//дата планируемой доставки, если не установлено, берётся сегодняшний день
		$data['dateExecute'] = $this->dateExecute;
		//город-отправитель
		isset($this->senderCityId) ? $data['from_location']['code'] = $this->senderCityId : '';
		//город-получатель
		isset($this->receiverCityId) ? $data['to_location']['code'] = $this->receiverCityId : '';
		//выбранный тариф
		isset($this->tariffId) ? $data['tariff_code'] = $this->tariffId : '';
		//список тарифов с приоритетами
		( isset($this->tariffList)  ) ? $data['tariffList'] = $this->tariffList : '';
		//режим доставки
		isset($this->modeId) ? $data['modeId'] = $this->modeId : '';

		//список мест
		if (isset($this->goodsList)) {
			foreach ($this->goodsList as $idGoods => $goods) {
				$data['packages'][$idGoods] = array();
				//вес
				(isset($goods['weight']) && $goods['weight'] <> '' && $goods['weight'] > 0.00) ? $data['packages'][$idGoods]['weight'] = $goods['weight'] : '';
				//длина
				(isset($goods['length']) && $goods['length'] <> '' && $goods['length'] > 0) ? $data['packages'][$idGoods]['length'] = $goods['length'] : '';
				//ширина
				(isset($goods['width']) && $goods['width'] <> '' && $goods['width'] > 0) ? $data['packages'][$idGoods]['width'] = $goods['width'] : '';
				//высота
				(isset($goods['height']) && $goods['height'] <> '' && $goods['height'] > 0) ? $data['packages'][$idGoods]['height'] = $goods['height'] : '';
			}
		}
		//проверка на подключние библиотеки curl
		if (!extension_loaded('curl')) {
			throw new Exception("Не подключена библиотека CURL");
		}

		$response = $this->_getRemoteData($data);
		$response['tariffId'] = $data['tariff_code'];

        if (!empty($response) && !isset($response['errors'])) {
            $this->result = $response;
            return true;
        } else {
        	$err = $response;
        	$err['senddata'] = print_r($data,1);
        	//$err = 'Запрос: '.json_encode($data).', Ответ:'.$response;
            $this->error = $err;
            return false;
        }
	}

	/**
	 * Получить результаты подсчета
	 *
	 * @return array
	 */
	public function getResult() {
		return $this->result;
	}

	/**
	 * получить код и текст ошибки
	 *
	 * @return object
	 */
	public function getError() {
		return $this->error;
	}

}

?>