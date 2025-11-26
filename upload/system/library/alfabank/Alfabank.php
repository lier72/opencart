<?php

define('RBSPAYMENT_PAYMENT_NAME', 'Alfa Bank');

define('RBSPAYMENT_PROD_URL' , 'https://payment.alfabank.ru/payment/rest/');
define('RBSPAYMENT_TEST_URL' , 'https://alfa.rbsuat.com/payment/rest/');
define('RBSPAYMENT_PROD_URL_ALTERNATIVE_DOMAIN' , 'https://payment.alfabank.ru/');
define('RBSPAYMENT_PROD_URL_ALT_PREFIX' , 'r-');

define('RBSPAYMENT_ENABLE_LOGGING', true);
define('RBSPAYMENT_ENABLE_CART_OPTIONS', true);
define('RBSPAYMENT_MEASUREMENT_NAME', 'шт');
define('RBSPAYMENT_MEASUREMENT_CODE', 0);
define('RBSPAYMENT_ENABLE_CALLBACK', true);

//this close tag must be here -> ?>
<?php
class Alfabank
{
public $prod_url;
public $test_url;
public $home_url;
public $alternative_domain = null;
public $enable_cart_options;
public $enable_refund_options;
public $logging = RBSPAYMENT_ENABLE_LOGGING;
public $language = 'en';
public $module_version = '3.2.10';
public $token;
public $login;
public $password;
public $mode;
public $stage;
public $currency;
public $send_cart;
public $versionFfd;
public $paymentMethodType;
public $paymentObjectType;
public $paymentMethodTypeDelivery;
public $taxSystem;
public $taxType;
public $discountHelper;
public $allowCallbacks = RBSPAYMENT_ENABLE_CALLBACK;
public $callbackType = "STATIC";
public $enable_cacert = true;
public $cacert_path = null;
public $enable_back_url_settings = false;
public $api_version = 1;
public function __construct()
{
$this->test_url = RBSPAYMENT_TEST_URL;
$this->prod_url = RBSPAYMENT_PROD_URL;
if (defined('RBSPAYMENT_HOME_URL')) {
$this->home_url = RBSPAYMENT_HOME_URL;
}
if (defined('RBSPAYMENT_API_VERSION')) {
$this->api_version = RBSPAYMENT_API_VERSION;
}
if (defined('RBSPAYMENT_PROD_URL_ALTERNATIVE_DOMAIN')) {
$this->alternative_domain = RBSPAYMENT_PROD_URL_ALTERNATIVE_DOMAIN;
}
if (defined('RBSPAYMENT_PROD_URL_ALTERNATIVE_DOMAIN') && defined('RBSPAYMENT_PROD_URL_ALT_PREFIX')) {
$this->allowCallbacks = false;
}
$this->callbackType = defined('RBSPAYMENT_CALLBACK_TYPE') ? RBSPAYMENT_CALLBACK_TYPE : $this->callbackType;
$this->enable_cart_options = defined('RBSPAYMENT_ENABLE_CART_OPTIONS') ? RBSPAYMENT_ENABLE_CART_OPTIONS : false;
if ($this->enable_cart_options === true) {
$this->library('alfabank/AlfabankDiscount');
$this->discountHelper = new AlfabankDiscount();
}
if (file_exists(DIR_SYSTEM . "library/cacert.cer")) {
$this->enable_cacert = true;
$this->cacert_path = DIR_SYSTEM . "library/cacert.cer";
} else {
$this->enable_cacert = false;
}
if (defined('RBSPAYMENT_ENABLE_REFUND_TAB') && RBSPAYMENT_ENABLE_REFUND_TAB === true) {
$this->enable_refund_options = true;
} else {
$this->enable_refund_options = false;
}
if (defined('RBSPAYMENT_ENABLE_BACK_URL_SETTINGS') && RBSPAYMENT_ENABLE_BACK_URL_SETTINGS === true) {
$this->enable_back_url_settings = true;
}
}
/**
* @return string
*/
public function getTestUrl()
{
return $this->test_url;
}
/**
* @return string
*/
public function getProdUrl()
{
return $this->prod_url;
}
/**
* @return string
*/
public function getAlternativeDomain()
{
return $this->alternative_domain;
}
/**
* @return mixed
*/
public function getVersionFfd()
{
return $this->versionFfd;
}
/**
* @param $delivery
* @return mixed
*/
public function getPaymentMethodType($delivery = false)
{
if ($delivery) {
return $this->paymentMethodTypeDelivery;
}
return $this->paymentMethodType;
}
/**
* @return mixed
*/
public function getPaymentObjectType()
{
return $this->paymentObjectType;
}
/**
* @return string
*/
public function getDefaultMeasurement()
{
if ($this->versionFfd == "v1_05") {
return RBSPAYMENT_MEASUREMENT_NAME;
}
return RBSPAYMENT_MEASUREMENT_CODE;
}

/**
* @param $property
* @param $value
* @return $this
*/
public function __set($property, $value)
{
if (property_exists($this, $property)) {
$this->$property = $value;
}
return $this;
}

/**
* @param $data
* @param $action_address
* @param array $headers
* @return string
*/
public function _sendGatewayData($data, $action_address, $headers = array())
{
$curl_opt = array(
CURLOPT_HTTPHEADER => $headers,
CURLOPT_VERBOSE => true,
CURLOPT_SSL_VERIFYHOST => false,
CURLOPT_URL => $action_address,
CURLOPT_RETURNTRANSFER => true,
CURLOPT_POST => true,
CURLOPT_POSTFIELDS => $data,
CURLOPT_HEADER => true,
);
$ssl_verify_peer = false;
if ($this->cacert_path != null && $this->enable_cacert == true) {
$ssl_verify_peer = true;
$curl_opt[CURLOPT_CAINFO] = $this->cacert_path;
}
$curl_opt[CURLOPT_SSL_VERIFYPEER] = $ssl_verify_peer;
$ch = curl_init();
curl_setopt_array($ch, $curl_opt);
$response = curl_exec($ch);
if ($response === false) {
$error = array('errorCode' => 999, "errorMessage" => curl_error($ch));
return json_encode($error);
}
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);
if ($this->logging) {
$this->logger($action_address,'test', serialize($data), $response);
}
return substr($response, $header_size);
}
/**
* @param string $url
* @param string $method
* @param mixed[] $request
* @param mixed[] $response
* @return integer
*/
public function logger($url, $method, $request, $response)
{
$this->library('log');
$file_name = "oc3x_alfabank_" . date("y-m") . ".log";
$logger = new Log($file_name);
$logger->write("Alfabank: " . $url . $method . "\nREQUEST: " . json_encode($request) . "\nRESPONSE: " . $response . "\n\n");
}

/**
*
* @param string $orderId
* @return mixed[]
*/
public function _getGatewayOrderStatus($orderId)
{
if ($this->mode == 'test') {
$action_address = $this->test_url . "getOrderStatusExtended.do";
} else {
$action_address = $this->prod_url . "getOrderStatusExtended.do";
}
if (!empty($this->token)) {
$decoded_credentials = base64_decode($this->token);
list($l, $p) = explode(':', $decoded_credentials);
$data['userName'] = $l;
$data['password'] = $p;
} else {
$data['userName'] = $this->login;
$data['password'] = $this->password;
}
$data['orderId'] = $orderId;
return $this->_sendGatewayData($data, $action_address);
}
/**
* in oc 2.1 no Loader::library()
* self realization
* @param $library
*/
public function library($library)
{
$file = DIR_SYSTEM . 'library/' . str_replace('../', '', (string)$library) . '.php';
if (file_exists($file)) {
include_once($file);
} else {
trigger_error('Error: Could not load library ' . $file . '!');
exit();
}
}
function get_numeric_currency_code($currency_code)
{
$currency_codes = array(
'BYN' => '933',
'BHD' => '048',
'BYR' => '974',
'CAD' => '124',
'CNY' => '156',
'EUR' => '978',
'GBP' => '826',
'HKD' => '344',
'HUF' => '348',
'ILS' => '376',
'JPY' => '392',
'KGS' => '417',
'KRW' => '410',
'KZT' => '398',
'MDL' => '498',
'MYR' => '458',
'OMR' => '512',
'PHP' => '608',
'RON' => '946',
'RUB' => '643',
'RUR' => '810',
'SGD' => '702',
'UAH' => '980',
'USD' => '840',
'NGN' => '566',
'MZN' => '943',
'BGN' => '975',
'BZD' => '084',
'GHS' => '936',
'GNF' => '324',
'XOF' => '952',
'PLN' => '985',
'LSL' => '426',
'TZS' => '834',
'NZD' => '554',
'KHR' => '116',
'TRY' => '949',
'AMD' => '051',
'SAR' => '682',
'AED' => '784',
'COP' => '170',
'AUD' => '036',
'IDR' => '360',
'KWD' => '414',
'JOD' => '400',
'INR' => '356'
);
return isset($currency_codes[$currency_code]) ? $currency_codes[$currency_code] : null;
}
}