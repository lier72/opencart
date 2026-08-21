<?php  
class info extends cdek_integrator {

	private static $auth_token_cache = array();
	
	public function getTariffMode() {
		return array(
			1 => 'дверь-дверь (Д-Д)',
			2 => 'дверь-склад (Д-С)',
			3 => 'склад-дверь (С-Д)',
			4 => 'склад-склад (С-С)',
			6 => 'дверь-постамат (Д-П)',
			7 => 'склад-постамат (С-П)',
			8 => 'постамат-дверь (П-Д)',
			9 => 'постамат-склад (П-С)',
			10 => 'постамат-постамат (П-П)'
		);
	}

	public function getOwnershipForm() {
		return array(
			9 => 'Акционерное общество',
			61 => 'Закрытое акционерное общество',
			63 => 'Индивидуальный предприниматель',
			119 => 'Открытое акционерное общество',
			137 => 'Общество с ограниченной ответственностью',
			147 => 'Публичное акционерное общество'
		);
	}

	public function getVatRates() {
		
		$vatRates = array();
		$vatRates['null'] = 'Без НДС';
		$vatRates['0'] = '0%';
		$vatRates['10'] = '10%';
		$vatRates['18'] = '18%';
		$vatRates['20'] = '20%';

		return $vatRates;
	}
	
	public function getTariffInfo($tariff_id) {
		
		$list = $this->getTariffList();
		return isset($list[$tariff_id]) ? $list[$tariff_id] : FALSE;
	}
	
	public function getTariffList() {
		
		return array(
			'3' => array(
				'title'		=> 'Супер-экспресс до 18 (Д-Д)',
				'mode_id'	=> 1
			),
			'8' => array(
				'title'		=> 'Международный экспресс грузы (Д-Д)',
				'mode_id'	=> 1,
                'im'		=> 1
			), 
			'57' => array(
				'title'		=> 'Супер-экспресс до 9 (Д-Д)',
				'mode_id'	=> 1
			),
			'58' => array(
				'title'		=> 'Супер-экспресс до 10 (Д-Д)',
				'mode_id'	=> 1
			), 
			'59' => array(
				'title'		=> 'Супер-экспресс до 12 (Д-Д)',
				'mode_id'	=> 1
			), 
			'60' => array(
				'title'		=> 'Супер-экспресс до 14 (Д-Д)',
				'mode_id'	=> 1
			),
			'61' => array(
				'title'		=> 'Супер-экспресс до 16 (Д-Д)',
				'mode_id'	=> 1
			),
			'62' => array(
				'title'		=> 'Магистральный экспресс (С-С)',
				'mode_id'	=> 4
			),
			'63' => array(
				'title'		=> 'Магистральный супер-экспресс (С-С)',
				'mode_id'	=> 4
			),
            '121' => array(
                'title'		=> 'Магистральный экспресс дверь-дверь (Д-Д)',
                'mode_id'	=> 1
            ),
            '122' => array(
                'title'		=> 'Магистральный экспресс склад-дверь (С-Д)',
                'mode_id'	=> 3
            ),
            '123' => array(
                'title'		=> 'Магистральный экспресс дверь-склад (Д-С)',
                'mode_id'	=> 2
            ),
            '124' => array(
                'title'		=> 'Магистральный супер-экспресс дверь-дверь (Д-Д)',
                'mode_id'	=> 1
            ),
            '125' => array(
                'title'		=> 'Магистральный супер-экспресс склад-склад (С-Д)',
                'mode_id'	=> 3
            ),
            '126' => array(
                'title'		=> 'Магистральный супер-экспресс дверь-склад (Д-С)',
                'mode_id'	=> 2
            ),
			'136' => array(
				'title'		=> 'Посылка (С-С)',
				'mode_id'	=> 4,
				'im'		=> 1
			),
			'137' => array(
				'title'		=> 'Посылка (С-Д)',
				'mode_id'	=> 3,
				'im'		=> 1
			),
			'138' => array(
				'title'		=> 'Посылка (Д-С)',
				'mode_id'	=> 2,
				'im'		=> 1
			),
			'139' => array(
				'title'		=> 'Посылка (Д-Д)',
				'mode_id'	=> 1,
				'im'		=> 1
			),
            '231' => array(
                'title'		=> 'Экономичная посылка (Д-Д)',
                'mode_id'	=> 1,
                'im'		=> 1
			),
            '232' => array(
                'title'		=> 'Экономичная посылка (Д-С)',
                'mode_id'	=> 2,
                'im'		=> 1
			),
			'233' => array(
				'title'		=> 'Экономичная посылка (С-Д)',
				'mode_id'	=> 3,
				'im'		=> 1
			),
			'234' => array(
				'title'		=> 'Экономичная посылка (С-С)',
				'mode_id'	=> 4,
				'im'		=> 1
			),	
			'291' => array(
				'title'		=> 'E-com Express (С-С)',
				'mode_id'	=> 4,
				'im'		=> 1
			),
			'293' => array(
				'title'		=> 'E-com Express (Д-Д)',
				'mode_id'	=> 1,
				'im'		=> 1
			),
			'294' => array(
				'title'		=> 'E-com Express (С-Д)',
				'mode_id'	=> 3,
				'im'		=> 1
			),
			'295' => array(
				'title'		=> 'E-com Express (Д-С)',
				'mode_id'	=> 2,
				'im'		=> 1
			),
            '366' => array(
                'title'		=> 'Посылка (Д-П)',
                'mode_id'	=> 5,
                'im'		=> 1
            ),
            '368' => array(
                'title'		=> 'Посылка (С-П)',
                'mode_id'	=> 6,
                'im'		=> 1
			),
            '378' => array(
                'title'		=> 'Экономичная посылка (С-П)',
                'mode_id'	=> 7,
                'im'		=> 1
            ),
            '480' => array(
                'title'		=> 'Экспресс (Д-Д)',
                'mode_id'	=> 1
            ),
            '481' => array(
                'title'		=> 'Экспресс (Д-С)',
                'mode_id'	=> 2
            ),
            '482' => array(
                'title'		=> 'Экспресс (С-Д)',
                'mode_id'	=> 3
			),
            '483' => array(
                'title'		=> 'Экспресс (С-С)',
                'mode_id'	=> 4
            ),
            '485' => array(
                'title'		=> 'Экспресс (Д-П)',
                'mode_id'	=> 6
            ),
            '486' => array(
                'title'		=> 'Экспресс (С-П)',
                'mode_id'	=> 7
			),
		);
	}

	public function getInpostTariffs() {
		$postomats_id = array('361','363','366','368','376','378');
		return $postomats_id;
	}
	
	public function getAddService($service_id) {
		
		$all = $this->getAddServices();
		return array_key_exists($service_id, $all) ? $all[$service_id] : FALSE;
	}
	
	public function getAuthToken($force_refresh = false) {
		$cache_key = hash('sha256', $this->ajax_url . '|' . $this->account . '|' . $this->secure_password);

		if (!$force_refresh && isset(self::$auth_token_cache[$cache_key]) && $this->isAuthTokenValid(self::$auth_token_cache[$cache_key])) {
			return self::$auth_token_cache[$cache_key];
		}

		$cache_file = $this->getAuthTokenCacheFile($cache_key);

		if ($cache_file) {
			$handle = @fopen($cache_file, 'c+');

			if ($handle && flock($handle, LOCK_EX)) {
				@chmod($cache_file, 0660);

				if (!$force_refresh) {
					rewind($handle);
					$cached_token = json_decode(stream_get_contents($handle), true);

					if ($this->isAuthTokenValid($cached_token)) {
						flock($handle, LOCK_UN);
						fclose($handle);
						self::$auth_token_cache[$cache_key] = $cached_token;
						return $cached_token;
					}
				}

				$auth_token = $this->requestAuthToken();

				if ($this->isAuthTokenValid($auth_token)) {
					ftruncate($handle, 0);
					rewind($handle);
					fwrite($handle, json_encode($auth_token));
					fflush($handle);
					self::$auth_token_cache[$cache_key] = $auth_token;
				}

				flock($handle, LOCK_UN);
				fclose($handle);

				return $this->isAuthTokenValid($auth_token) ? $auth_token : array();
			}

			if ($handle) {
				fclose($handle);
			}
		}

		$auth_token = $this->requestAuthToken();

		if ($this->isAuthTokenValid($auth_token)) {
			self::$auth_token_cache[$cache_key] = $auth_token;
			return $auth_token;
		}

		return array();
	}

	private function getAuthTokenCacheFile($cache_key) {
		if (!defined('DIR_CACHE') || !is_dir(DIR_CACHE) || !is_writable(DIR_CACHE)) {
			return '';
		}

		return DIR_CACHE . 'cdek_auth_' . $cache_key . '.json';
	}

	private function isAuthTokenValid($auth_token) {
		return is_array($auth_token)
			&& !empty($auth_token['access_token'])
			&& !empty($auth_token['expires_at'])
			&& (int)$auth_token['expires_at'] > time() + 60;
	}

	private function requestAuthToken() {
		$data = array(
			'grant_type' => 'client_credentials',
			'client_id' => $this->account,
			'client_secret' => $this->secure_password
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->ajax_url . 'v2/oauth/token');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

		$result = curl_exec($ch);
		$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);
		curl_close($ch);

		$auth_token = json_decode($result, true);

		if ($http_code < 200 || $http_code >= 300 || empty($auth_token['access_token'])) {
			if ($this->logger) {
				$this->logger->write('CDEK OAuth error: HTTP ' . $http_code . ($curl_error ? ', ' . $curl_error : ''));
			}

			return array();
		}

		$expires_in = !empty($auth_token['expires_in']) ? (int)$auth_token['expires_in'] : 3600;
		$auth_token['expires_at'] = time() + max(120, $expires_in);

		return $auth_token;
	}

	public function getAddServices() {
		
		return array(
			'TAKE_SENDER' => array(
				'title'			=> 'Забор в городе отправителя', // Только для тарифов от склада
				'description'	=> 'Дополнительная услуга забора груза в городе отправителя'
			),
			'DELIV_RECEIVER' => array(
				'title'			=> 'Доставка в городе получателя',
				'description'	=> 'Дополнительная услуга доставки груза в городе получателя'  // Только для тарифов до склада (только для тарифов «Магистральный», «Магистральный супер-экспресс»)
			),
			'TRYING_ON' => array(
				'title'			=> 'Примерка на дому',
				'description'	=> 'Курьер доставляет покупателю несколько единиц товара (одежда, обувь и пр.) для примерки. Время ожидания курьера в этом случае составляет 30 минут.'
			),
			'PART_DELIV' => array(
				'title'			=> 'Частичная доставка',
				'description'	=> 'Во время доставки товара покупатель может отказаться от одной или нескольких позиций, и выкупить только часть заказа.'
			),
			'INSURANCE' => array(
				'title'			=> 'Страхование',
				'hide'			=> TRUE,
				'description'	=> 'Обеспечение страховой защиты посылки. Размер дополнительного сбора страхования вычисляется от размера объявленной стоимости отправления. Важно: Услуга начисляется автоматически для всех заказов ИМ.'
			)
		);
		
	}
	
	public function getOrderStatus($status_id) {
		
		$all = $this->getOrderStatuses();
		return array_key_exists($status_id, $all) ? $all[$status_id] : FALSE;
	}
	
	public function getOrderStatuses() {
		
		return array(
			'ACCEPTED' => array(
				'title'			=> 'Принят',
				'description'	=> 'Заказ создан в информационной системе СДЭК, но требуются дополнительные валидации'
			),
			'CREATED' => array(
				'title'			=> 'Создан',
				'description'	=> 'Заказ создан в информационной системе СДЭК и прошел необходимые валидации'
			),
			'RECEIVED_AT_SHIPMENT_WAREHOUSE' => array(
				'title'			=> 'Принят на склад отправителя',
				'description'	=> 'Оформлен приход на склад СДЭК в городе-отправителе.'
			),
			'READY_FOR_SHIPMENT_IN_SENDER_CITY' => array(
				'title'			=> 'Выдан на отправку в г. отправителе',
				'description'	=> 'Оформлен расход со склада СДЭК в городе-отправителе. Груз подготовлен к отправке (консолидирован с другими посылками)'
			),
			'RETURNED_TO_SENDER_CITY_WAREHOUSE' => array(
				'title'			=> 'Возвращен на склад отправителя',
				'description'	=> 'Повторно оформлен приход в городе-отправителе (не удалось передать перевозчику по какой-либо причине). Примечание: этот статус не означает возврат груза отправителю.'
			),
			'TAKEN_BY_TRANSPORTER_FROM_SENDER_CITY' => array(
				'title'			=> 'Сдан перевозчику в г. отправителе',
				'description'	=> 'Зарегистрирована отправка в городе-отправителе. Консолидированный груз передан на доставку (в аэропорт/загружен машину)'
			),
			'SENT_TO_TRANSIT_CITY' => array(
				'title'			=> 'Отправлен в г. транзит',
				'description'	=> 'Зарегистрирована отправка в город-транзит. Проставлены дата и время отправления у перевозчика'
			),
			'ACCEPTED_IN_TRANSIT_CITY' => array(
				'title'			=> 'Встречен в г. транзите',
				'description'	=> 'Зарегистрирована встреча в городе-транзите'
			),
			'ACCEPTED_AT_TRANSIT_WAREHOUSE' => array(
				'title'			=> 'Принят на склад транзита',
				'description'	=> 'Оформлен приход в городе-транзите'
			),
			'RETURNED_TO_TRANSIT_WAREHOUSE' => array(
				'title'			=> 'Возвращен на склад транзита',
				'description'	=> 'Повторно оформлен приход в городе-транзите (груз возвращен на склад). Примечание: этот статус не означает возврат груза отправителю.'
			),
			'READY_FOR_SHIPMENT_IN_TRANSIT_CITY' => array(
				'title'			=> 'Выдан на отправку в г. транзите',
				'description'	=> 'Оформлен расход в городе-транзите'
			),
			'TAKEN_BY_TRANSPORTER_FROM_TRANSIT_CITY' => array(
				'title'			=> 'Сдан перевозчику в г. транзите',
				'description'	=> 'Зарегистрирована отправка у перевозчика в городе-транзите'
			),
			'SENT_TO_SENDER_CITY' => array(
				'title'			=> 'Отправлен в г. отправитель',
				'description'	=> 'Зарегистрирована отправка в город-отправитель, груз в пути'
			),
			'SENT_TO_RECIPIENT_CITY' => array(
				'title'			=> 'Отправлен в г. получатель',
				'description'	=> 'Зарегистрирована отправка в город-получатель, груз в пути'
			),
			'ACCEPTED_IN_SENDER_CITY' => array(
				'title'			=> 'Встречен в г. отправителе',
				'description'	=> 'Зарегистрирована встреча груза в городе-отправителе'
			),
			'ACCEPTED_IN_RECIPIENT_CITY' => array(
				'title'			=> 'Встречен в г. получателе',
				'description'	=> 'Зарегистрирована встреча груза в городе-получателе'
			),
			'ACCEPTED_AT_RECIPIENT_CITY_WAREHOUSE' => array(
				'title'			=> 'Принят на склад доставки',
				'description'	=> 'Оформлен приход на склад города-получателя, ожидает доставки до двери'
			),
			'ACCEPTED_AT_PICK_UP_POINT' => array(
				'title'			=> 'Принят на склад до востребования',
				'description'	=> 'Оформлен приход на склад города-получателя. Доставка до склада, посылка ожидает забора клиентом - покупателем ИМ'
			),
			'TAKEN_BY_COURIER' => array(
				'title'			=> 'Выдан на доставку',
				'description'	=> 'Добавлен в курьерскую карту, выдан курьеру на доставку'
			),
			'RETURNED_TO_RECIPIENT_CITY_WAREHOUSE' => array(
				'title'			=> 'Возвращен на склад доставки',
				'description'	=> 'Оформлен повторный приход на склад в городе-получателе. Доставка не удалась по какой-либо причине, ожидается очередная попытка доставки. Примечание: этот статус не означает возврат груза отправителю.'
			),
			'DELIVERED' => array(
				'title'			=> 'Вручен',
				'description'	=> 'Успешно доставлен и вручен адресату (конечный статус).'
			),
			'NOT_DELIVERED' => array(
				'title'			=> 'Не вручен',
				'description'	=> 'Покупатель отказался от покупки, возврат в ИМ (конечный статус).'
			),
			'INVALID' => array(
				'title'			=> 'Некорректный заказ',
				'description'	=> 'Заказ содержит некорректные данные'
			),
			'IN_CUSTOMS_INTERNATIONAL ' => array(
				'title'			=> 'Таможенное оформление в стране отправления',
				'description'	=> 'В процессе таможенного оформления в стране отправителя (для международных заказов).'
			),
			'SHIPPED_TO_DESTINATION ' => array(
				'title'			=> 'Отправлено в страну назначения',
				'description'	=> 'Отправлен в страну назначения, заказ в пути (для международных заказов).'
			),
			'PASSED_TO_TRANSIT_CARRIER ' => array(
				'title'			=> 'Передано транзитному перевозчику',
				'description'	=> 'Передан транзитному перевозчику для доставки в страну назначения (для международных заказов).'
			),
			'IN_CUSTOMS_LOCAL' => array(
				'title'			=> 'Таможенное оформление в стране назначения',
				'description'	=> 'В процессе таможенного оформления в стране назначения (для международных заказов).'
			),
			'CUSTOMS_COMPLETE' => array(
				'title'			=> 'Таможенное оформление завершено',
				'description'	=> 'Завершено таможенное оформление заказа (для международных заказов).'
			),
			'POSTOMAT_POSTED' => array(
				'title'			=> 'Заложен в постамат',
				'description'	=> 'Заложен в постамат, заказ ожидает забора клиентом - покупателем ИМ.'
			),
			'POSTOMAT_SEIZED' => array(
				'title'			=> 'Изъят из постамата курьером',
				'description'	=> 'Истек срок хранения заказа в постамате, возврат в ИМ.'
			),
			'POSTOMAT_RECEIVED' => array(
				'title'			=> 'Изъят из постамата клиентом',
				'description'	=> 'Успешно изъят из постамата клиентом - покупателем ИМ.'
			)
		);
		
	}
	
	public function getCurrencyList() {
		
		return array(
			'RUB' => 'Российский рубль',
			'USD' => 'Доллар США',
			'EUR' => 'Евро',
			'KZT' => 'Тенге',
			'GBP' => 'Фунт стерлингов',
			'CNY' => 'Юань',
			'BYN' => 'Белорусский рубль',
			'UAH' => 'Гривна'
		);
		
	}
	
	public function getCurrency($currency) {
		
		$all = $this->getCurrencyList();
		return array_key_exists($currency, $all) ? $all[$currency] : $currency['RUB'];
	}
	
	public function getPVZData() {
		
		$data = array();
		
		$pvz_list = $this->getURL($this->base_url . 'pvzlist.php?type=ALL', new parser_xml());
		
		if (isset($pvz_list->Pvz)) {
				
			foreach ($pvz_list->Pvz as $pvz_info) {
				
				if (empty($pvz_info['City']) || empty($pvz_info['Address'])) {
					continue;
				}
				
				$key = md5($pvz_info['Address']);
									
				if (array_key_exists($key, $data)) continue;
				
				$info = array(
					'Code'		=> (string)$pvz_info['Code'],
					'City'		=> (string)$pvz_info['City'],
					'CityCode'	=> (string)$pvz_info['CityCode'],
					'Address'	=> (string)$pvz_info['Address'],
					'Name'		=> (string)$pvz_info['Name'],
					'WorkTime'	=> (string)$pvz_info['WorkTime'],
					'Phone'		=> (string)$pvz_info['Phone'],
					'Note'		=> (string)$pvz_info['Note'],
					'x'			=> (string)$pvz_info['coordX'],
					'y'			=> (string)$pvz_info['coordY']
				);
				
				if (isset($pvz_info->WeightLimit)) {
					
					$info['WeightLimit'] = array(
						'WeightMin' => (float)$pvz_info->WeightLimit['WeightMin'],
						'WeightMax' => (float)$pvz_info->WeightLimit['WeightMax']
					);
				
				}
				
				if (empty($data[(int)$pvz_info['CityCode']])) {
					
					$data[(int)$pvz_info['CityCode']] = array(
						'City'	=> $info['City'],
						'List'	=> array()
					);
					
				}
				
				$data[(int)$pvz_info['CityCode']]['List'][$key] = $info;
			}
			
		}
		
		return $data;	
	}

	public function getPVZ($city_id) {
		
		$data = array();
		
		$pvz_list = $this->getURL($this->ajax_url . 'v2/deliverypoints?city_code=' . $city_id.'&type=ALL', new parser_json());
		
		if (isset($pvz_list)) {
				
			 foreach ($pvz_list as $pvz_info) {
				
				if (empty($pvz_info['location']['city']) || empty($pvz_info['location']['address'])) {
					continue;
				}
				
				$key = md5($pvz_info['location']['address']);
									
				if (array_key_exists($key, $data)) continue;

/* 				$addarray['Code'] = ''.$pvz_info['code'];
				$addarray['CityCode'] = ''.$pvz_info['location']['city_code'];
				$addarray['City'] = ''.$pvz_info['location']['city'];
				$addarray['WorkTime'] = ''.$pvz_info['WorkTime'];
				$addarray['Address'] = ''.$pvz_info['location']['address'];
				$addarray['Phone'] = ''.$pvz_info['phones'][0]['number'];
				$addarray['coordX'] = ''.$pvz_info['location']['longitude'];
				$addarray['coordY'] = ''.$pvz_info['location']['latitude']; */
				
				$info = array(
					'Code'		=> (string)$pvz_info['code'],
					'City'		=> (string)$pvz_info['location']['city'],
					'CityCode'	=> (string)$pvz_info['location']['city_code'],
					'Address'	=> (string)$pvz_info['location']['address'],
					'Name'		=> (string)$pvz_info['name'],
					'Phone'		=> (string)$pvz_info['phones'][0]['number'],
					'WorkTime'	=> (string)$pvz_info['work_time'],
//					'Note'		=> (string)$pvz_info['note'],  //TODO nedd to check why is is undefined in production server
					'x'			=> (string)$pvz_info['location']['longitude'],
					'y'			=> (string)$pvz_info['location']['latitude']
				);
				
				if (isset($pvz_info->WeightLimit)) {
					
					$info['WeightLimit'] = array(
						'WeightMin' => (float)$pvz_info['weight_min'],
						'WeightMax' => (float)$pvz_info['weight_max']
					);
				
				}
				
				if (empty($data[(int)$pvz_info['location']['city_code']])) {
					
					$data[(int)$pvz_info['location']['city_code']] = array(
						'City'	=> $info['City'],
						'List'	=> array()
					);
					
				}
				
			 	$data[(int)$pvz_info['location']['city_code']]['List'][$key] = $info;
			 }
			
		}
		
		return $data;	
	}
	
	public function getCityByName($city) {
        $response = $this->getURL($this->ajax_url . 'v2/location/suggest/cities?name=' . urlencode($city), new parser_json());

        if (is_array($response)) {
			$result = array();

			foreach ($response as $item) {
				if (!is_array($item) || empty($item['code']) || empty($item['full_name'])) {
					continue;
				}

				$result[] = array(
					'code' => $item['code'],
					'full_name' => $item['full_name']
				);
			}

			return $result;
        }
				
		return array();
	}

	public function getBaseUrl() {
		return $this->base_url;
	}

	public function getAjaxUrl() {
		return $this->ajax_url;
	}
	
	
}

?>
