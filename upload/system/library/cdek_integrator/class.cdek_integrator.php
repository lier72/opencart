<?php
class cdek_integrator {

	protected $base_url = 'https://integration.cdek.ru/'; // Базовый URL API


	protected $ajax_url = 'https://api.cdek.ru/'; //URL API для ajax запросов

	protected $account; // Учетная запись
	protected $secure_password; // Cекретный код
	protected $date;
	public $logger;

	private $version = "1.0"; // Версия модуля
	private static $ext_dir;

	public $error; // Ошибки при выполнении метода
	public $curl_url;
	public $curl_data;
	public $curl_success;

	const TEST_ACCOUNT = 'wqGwiQx0gg8mLtiEKsUinjVSICCjtTEP';
	const TEST_SECURE_PASSWORD = 'RmAmgvSgSl1yirlz9QupbzOJVqhCxcP5';

	// api.edu.cdek.ru

	const BASE_TEST_URL = 'https://integration.cdek.ru/';
	const AJAX_TEST_URL = 'https://api.edu.cdek.ru/';


	public function __construct($account = '', $secure_password = '', $date = '') {

		if (!empty($account) &&  !empty($secure_password)) {
			$this->setAuth($account, $secure_password);
		}

		if ($this->isTestingApiKeys($account, $secure_password)) {

			$this->base_url = self::BASE_TEST_URL;
			$this->ajax_url = self::AJAX_TEST_URL;
		}

		if (!$date) {

			$default_timezone = date_default_timezone_get();

			date_default_timezone_set('UTC');

			$date = date('Y-m-d', time() + 10800);

			date_default_timezone_set($default_timezone);

		}

		$this->setDate($date);

		$this->init();
	}

	/**
	 * Установка планируемой даты отправки
	 *
	 * @param string $date дата планируемой отправки, например '2014-06-25'
	 */
	public function setDate($date) {
		$this->date = $date;
	}

	/**
	 * Авторизация ИМ
	 *
	 * @param string $account логин
	 * @param string $secure_password пароль
	 */
	public function setAuth($account, $secure_password) {
		$this->account = $account;
		$this->secure_password = $secure_password;
	}

	protected function setLogger($filename){
	    $this->logger = new cdek_logger($filename);
    }

	/**
	 * Защифрованный пароль для передачи на сервер
	 *
	 * @return string
	 */
	protected function getSecure() {
		return md5($this->date . '&' . $this->secure_password);
	}

	public function loadComponent($component) {
		if (!class_exists($component)) return null;
		return new $component($this->account, $this->secure_password, $this->date);
	}

	public function sendData(exchange $component) {

		$action = $this->ajax_url . $component->getMethod();
		$parser = method_exists($component, 'getParser') ? $component->getParser() : new parser_json();

		$response = $this->getURL($action, $parser, $component->getData());

		// Обнуление массива ошибок
		$this->error = array();

		return method_exists($component, 'prepareResponse') ? $component->prepareResponse($response, $this->error) : $response;
	}

	protected function getURL($url, response_parser $parser, $data = array()) {

		$info = $this->loadComponent('info');

		$auth_data = $info->getAuthToken();
        $headers = array('Authorization: Bearer ' . $auth_data['access_token']);
		
		$ch = curl_init();
		$this->curl_url = $url;
		$this->curl_data = $data;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

		curl_setopt($ch, CURLOPT_TIMEOUT, 20);

		if (!empty($data)) {
		    $headers[]= 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$out = curl_exec($ch);
		curl_close($ch);

		$this->curl_success = $out;
		$parser->setData($out);

		
		return $parser->getData();
	}

	public function getMethod() {
		return $this->method;
	}

	private function init() {

		spl_autoload_register(array($this, 'autoloader'));
		spl_autoload_extensions('.php');
		self::$ext_dir = dirname(__FILE__);
        $this->setLogger('cdek_integrator.log');
	}

	private function isTestingApiKeys($account, $secure) {
		return $account == self::TEST_ACCOUNT && $secure == self::TEST_SECURE_PASSWORD;
	}

	public function getTestAccount() {
		return self::TEST_ACCOUNT;
	}

	public function getTestSecure() {
		return self::TEST_SECURE_PASSWORD;
	}

	static public function autoloader($class_name) {
		if (class_exists($class_name)) return;

		$folders = array(DIR_SYSTEM.'library/cdek_integrator/', DIR_SYSTEM.'library/cdek_integrator/components/');

		foreach ($folders as $folder) {

			foreach (array('class', 'interface') as $type) {

				$file_name = $folder . $type . '.' . $class_name . '.php';

				if (file_exists($file_name)) {
					return require_once $file_name;
				}
			}

		}
	}

}

?>