<?php
use library\CRBHandler;
class ControllerExtensionPaymentAlfabank extends Controller
{
/**
* @param $registry
*/
public function __construct($registry)
{
parent::__construct($registry);
$this->load->language('extension/payment/alfabank');
if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/alfabank.twig')) {
$this->have_template = true;
}
}
/**
* @return mixed
*/
public function index()
{
$data['text_description'] = $this->language->get('text_description');
$data['text_payment'] = $this->language->get('text_payment');
$data['text_loading'] = $this->language->get('text_loading');
$data['button_confirm'] = $this->language->get('button_confirm');

$data['action'] = $this->url->link('extension/payment/alfabank/payment', '', true);
$data['entry_alfabank_button_confirm'] = $this->language->get('entry_alfabank_button_confirm');
return $this->get_template('extension/payment/alfabank', $data);
}
/**
* @param $template
* @param $data
* @return mixed
*/
private function get_template($template, $data)
{
return $this->load->view($template, $data);
}
public function payment()
{
$this->initializeGatewayLibrary();
$this->load->model('checkout/order');
$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
$order_number = (int)$order_info['order_id'];
$amount = round($order_info['total'] * $order_info['currency_value'], 2) * 100;
$return_url = $this->url->link('extension/payment/alfabank/comeback');
$jsonParams = array(
'CMS' => 'Opencart ' . VERSION,
'Module-Version' => 'Alfabank ' . $this->method_library->module_version,
);
if (!empty($order_info['email'])) {
$jsonParams['email'] = $order_info['email'];
}
#BLOCK_PHONE_TRANSFER_START[builder]
if (!empty($order_info['telephone'])) {
$jsonParams['phone'] = $this->cleanPhoneNumber($order_info['telephone']);
}
#BLOCK_PHONE_TRANSFER_END
if ( $this->method_library->enable_back_url_settings
&& !empty($this->config->get('payment_alfabank_backToShopURL'))
) {
$jsonParams['backToShopUrl'] = $this->config->get('payment_alfabank_backToShopURL');
}
if ($this->method_library->enable_cart_options && $this->method_library->send_cart) {
$orderBundle = array();
$orderBundle['customerDetails']['email'] = $order_info['email'];

#BLOCK_PHONE_TRANSFER_START[builder]
if (!empty($order_info['telephone'])) {
$orderBundle['customerDetails']['phone'] = $this->cleanPhoneNumber($order_info['telephone']);
}
#BLOCK_PHONE_TRANSFER_END
foreach ($this->cart->getProducts() as $product) {
$product_taxSum = $this->tax->getTax($product['price'], $product['tax_class_id']);
$product_amount = (round($product['price'] + $product_taxSum, 2)) * $product['quantity'];
$tax_type = $this->config->get('payment_alfabank_taxType');
if ($product['tax_class_id'] != 0) {
$item_rate = $product_taxSum / $product['price'] * 100;
$tax_type = $this->getTaxType($item_rate);
}
$product_data = array(
'positionId' => $product['cart_id'],
'name' => $product['name'],
'quantity' => array(
'value' => $product['quantity'],
'measure' => $this->method_library->getDefaultMeasurement(),
),
'itemAmount' => $product_amount * 100,
'itemCode' => $product['product_id'] . "_" . $product['cart_id'], //fix by PLUG-1740, PLUG-2620
'tax' => array(
'taxType' => $tax_type,
),
'itemPrice' => round($product['price'] + $product_taxSum, 2) * 100,
);
if ($tax_type != "0" && $product_taxSum != "0") {
$product_data['tax']['taxSum'] = $product_taxSum * 100;
}
$attributes = array();
$attributes[] = array(
"name" => "paymentMethod",
"value" => $this->method_library->getPaymentMethodType()
);
$attributes[] = array(
"name" => "paymentObject",
"value" => $this->method_library->getPaymentObjectType()
);
$product_data['itemAttributes']['attributes'] = $attributes;
$orderBundle['cartItems']['items'][] = $product_data;
}
if (isset($this->session->data['shipping_method']['cost']) && $this->session->data['shipping_method']['cost'] > 0) {
$delivery['positionId'] = 'delivery';
$delivery['name'] = $this->session->data['shipping_method']['title'];
$delivery['itemAmount'] = $this->session->data['shipping_method']['cost'] * 100;
$delivery['quantity']['value'] = 1;
$delivery['quantity']['measure'] = $this->method_library->getDefaultMeasurement(); //todo?
$delivery['itemCode'] = $this->session->data['shipping_method']['code'];
$delivery['tax']['taxType'] = $this->config->get('payment_alfabank_taxType');
$delivery['itemPrice'] = $this->session->data['shipping_method']['cost'] * 100;
$attributes = array();
$attributes[] = array(
"name" => "paymentMethod",
"value" => $this->method_library->getPaymentMethodType(true)
);
$attributes[] = array(
"name" => "paymentObject",
"value" => 4
);
$delivery['itemAttributes']['attributes'] = $attributes;
$orderBundle['cartItems']['items'][] = $delivery;
}
if (isset($this->session->data['vouchers']) && count($this->session->data['vouchers']) > 0) {
foreach ($this->session->data['vouchers'] as $key => $voucher) {
$itemVoucher = array(
'positionId' => 'voucher_' . $key,
'name' => $voucher['description'],
'itemAmount' => $voucher['amount'] * 100,
'quantity' => array(
'value' => 1,
'measure' => $this->method_library->getDefaultMeasurement(),
),
'itemCode' => 'voucher_' . $key,
'tax' => array(
'taxType' => $this->config->get('payment_alfabank_taxType'),
),
'itemPrice' => $voucher['amount'] * 100
);
$attributes = array();
$attributes[] = array(
"name" => "paymentMethod",
"value" => $this->method_library->getPaymentMethodType(),
);
$attributes[] = array(
"name" => "paymentObject",
"value" => 1
);
$itemVoucher['itemAttributes']['attributes'] = $attributes;
$orderBundle['cartItems']['items'][] = $itemVoucher;
}
}

$discount = $this->method_library->discountHelper->discoverDiscount($amount, $orderBundle['cartItems']['items']);
if ($discount > 0) {
$this->method_library->discountHelper->setOrderDiscount($discount);
$recalculatedPositions = $this->method_library->discountHelper->normalizeItems($orderBundle['cartItems']['items']);
$recalculatedAmount = $this->method_library->discountHelper->getResultAmount();
$orderBundle['cartItems']['items'] = $recalculatedPositions;
}
}

$filePath = DIR_SYSTEM . 'library/alfabank/CRBHandler.php';
if (file_exists($filePath)) {
include_once($filePath);
$crbHandler = new CRBHandler($this->tax, $this->config);
$iva_amount_total = 0;
$iac_amount_total = 0;
foreach ($this->cart->getProducts() as $product) {
$taxData = $crbHandler->calculateTaxesForProduct($product);
$iva_amount_total += $taxData['iva'];
$iac_amount_total += $taxData['iac'];
}
$jsonParams = $crbHandler->addJsonParams($jsonParams, $order_info, $iva_amount_total, $iac_amount_total);
}
$args = array(
'orderNumber' => $order_number . "_" . time(),
'amount' => $amount,
'returnUrl' => $return_url,
'jsonParams' => json_encode($jsonParams),
);
#BLOCK_PHONE_TRANSFER_START[builder]
if (!empty($order_info['telephone'])) {
$args['orderPayerData'] = json_encode(array(
"mobilePhone" => $this->cleanPhoneNumber($order_info['telephone'])
));
}
#BLOCK_PHONE_TRANSFER_END
if ($this->method_library->callbackType == "DYNAMIC") {
$args['dynamicCallbackUrl'] = $this->url->link('extension/payment/alfabank/callback'). "&order_id=" . $order_number;
}
if (defined('RBSPAYMENT_MANDATORY_CURRENCY') && RBSPAYMENT_MANDATORY_CURRENCY === true) {
$currency_code = $this->method_library->get_numeric_currency_code($order_info['currency_code']);
if (!empty($currency_code)){
$args['currency'] = $currency_code;
}
}
if (!empty($order_info['customer_id'] && $order_info['customer_id'] > 0)) {
$client_email = !empty($order_info['email']) ? $order_info['email'] : "";
$args['clientId'] = md5($order_info['customer_id']  .  $client_email  . $order_info['store_url']);
}
if (defined('RBSPAYMENT_SEND_CLIENT_FULL_INFO') && RBSPAYMENT_SEND_CLIENT_FULL_INFO === true) {
$billingPayerData = $this->_getBillingPayerData($order_info);
if(!empty($billingPayerData)) {
$args['billingPayerData'] = json_encode($billingPayerData);
}
}
if ($this->method_library->enable_cart_options && $this->method_library->send_cart && !empty($orderBundle)) {
$args['taxSystem'] = $this->method_library->taxSystem;
$args['orderBundle']['orderCreationDate'] = date('c');
$args['orderBundle'] = json_encode($orderBundle);
}
if (!empty($this->method_library->token)) {
$decoded_credentials = base64_decode($this->method_library->token);
list($l, $p) = explode(':', $decoded_credentials);
$args['userName'] = $l;
$args['password'] = $p;
} else {
$args['userName'] = $this->method_library->login;
$args['password'] = $this->method_library->password;
}
if ($this->method_library->mode == 'test') {
$action_address = $this->method_library->test_url;
} else {
$action_address = $this->method_library->prod_url;
if (defined('RBSPAYMENT_PROD_URL_ALTERNATIVE_DOMAIN') && defined('RBSPAYMENT_PROD_URL_ALT_PREFIX')) {
if (substr($this->method_library->login, 0, strlen(RBSPAYMENT_PROD_URL_ALT_PREFIX)) == RBSPAYMENT_PROD_URL_ALT_PREFIX) {
$pattern = '/^https:\/\/[^\/]+/';
$action_address = preg_replace($pattern, rtrim(RBSPAYMENT_PROD_URL_ALTERNATIVE_DOMAIN, '/'), $action_address);
}
}
}
$method = $this->method_library->stage == 'two' ? 'registerPreAuth.do' : 'register.do';
$request = http_build_query($args, '', '&');
$response = $this->method_library->_sendGatewayData($request, $action_address . $method);
if ($this->method_library->logging) {
$this->method_library->logger($action_address, $method, $request, $response);
}
$response = json_decode($response, true);
if (isset($response['orderId'])) {
$comment = "Order created in payment gateway";
$this->model_checkout_order->addOrderHistory($order_number, $this->config->get('payment_alfabank_order_status_before_id'), $comment, false);
}
if (isset($response['errorCode'])) {
$this->document->setTitle($this->language->get('error_title'));
$data['header'] = $this->load->controller('common/header');
$data['column_left'] = $this->load->controller('common/column_left');
$data['column_right'] = $this->load->controller('common/column_right');
$data['content_top'] = $this->load->controller('common/content_top');
$data['button_continue'] = $this->language->get('error_continue');
$data['heading_title'] = $this->language->get('error_title') . ' #' . $response['errorCode'];
$data['text_error'] = $response['errorMessage'];
$data['continue'] = $this->url->link('checkout/cart');
$data['content_bottom'] = $this->load->controller('common/content_bottom');
$data['footer'] = $this->load->controller('common/footer');
$this->response->setOutput($this->get_template('error/alfabank', $data));
} else {
$this->response->redirect($response['formUrl']);
}
}
/**
* Init Library
*/
private function initializeGatewayLibrary()
{
$this->library('alfabank/Alfabank');
$this->method_library = new Alfabank();
$this->method_library->token = $this->config->get('payment_alfabank_merchantToken');
$this->method_library->login = $this->config->get('payment_alfabank_merchantLogin');
$this->method_library->password = htmlspecialchars_decode($this->config->get('payment_alfabank_merchantPassword'));
$this->method_library->stage = $this->config->get('payment_alfabank_stage');
$this->method_library->mode = $this->config->get('payment_alfabank_mode');
$this->method_library->logging = $this->config->get('payment_alfabank_logging');
$this->method_library->taxSystem = $this->config->get('payment_alfabank_taxSystem');
$this->method_library->taxType = $this->config->get('payment_alfabank_taxType');
$this->method_library->send_cart = $this->config->get('payment_alfabank_send_cart');
$this->method_library->versionFfd = $this->config->get('payment_alfabank_versionFfd');
$this->method_library->paymentMethodType = $this->config->get('payment_alfabank_paymentMethodType');
$this->method_library->paymentObjectType = $this->config->get('payment_alfabank_paymentObjectType');
$this->method_library->paymentMethodTypeDelivery = $this->config->get('payment_alfabank_paymentMethodTypeDelivery');
if (file_exists(DIR_SYSTEM . "library/cacert.cer") && $this->config->get('payment_alfabank_enable_cacert') == true) {
$this->method_library->enable_cacert = $this->config->get('payment_alfabank_enable_cacert');
$this->method_library->cacert_path = DIR_SYSTEM . "library/cacert.cer";
} else {
$this->method_library->enable_cacert = (float)$this->config->get('payment_alfabank_enable_cacert');
}
$this->method_library->language = substr($this->language->get('code'), 0, 2);
$this->method_library->backToShopURL = $this->config->get('payment_alfabank_backToShopURL');
}
/**
* in oc 2.1 no Loader::library()
* self realization
* @param $library
*/
private function library($library)
{
$file = DIR_SYSTEM . 'library/' . str_replace('../', '', (string)$library) . '.php';
if (file_exists($file)) {
include_once($file);
} else {
trigger_error('Error: Could not load library ' . $file . '!');
exit();
}
}
public function callback()
{
if (isset($this->request->get['mdOrder'])) {
$order_id = $this->request->get['mdOrder'];
} else {
die('Illegal Access');
}
$this->initializeGatewayLibrary();
$response = $this->method_library->_getGatewayOrderStatus($order_id);
$response = json_decode($response, true);
$ex = explode("_", $response['orderNumber']);
$order_number = $ex[0];
$this->load->model('checkout/order');
$order_info = $this->model_checkout_order->getOrder($order_number);
if ($order_info) {
if (($response['errorCode'] == 0) && (($response['orderStatus'] == 1) || ($response['orderStatus'] == 2))) {
$this->_storeGatewayOrderData($order_id, $order_info, $response);
$comment = "Incoming callback";
$this->model_checkout_order->addOrderHistory($order_number, $this->config->get('payment_alfabank_order_status_completed_id'), $comment, false);
$this->response->redirect($this->url->link('checkout/success', '', true));
} elseif ($response['errorCode'] == 0 && $response['orderStatus'] == 4) {
$is_part_refunted = $response['paymentAmountInfo']['approvedAmount'] === $response['amount'] && $response['paymentAmountInfo']['refundedAmount'] != 0;
$is_full_refunded = $response['paymentAmountInfo']['approvedAmount'] === $response['paymentAmountInfo']['refundedAmount'];
if($is_full_refunded) {
$refund_amount = $response['amount'] / 100;
$refund_massage = 'REFUNDED_FULL_MESSAGE ' . $refund_amount;
} else if($is_part_refunted) {
$refund_amount = $response['paymentAmountInfo']['refundedAmount'] / 100;
$refund_massage = 'REFUNDED_MESSAGE ' . $refund_amount;
}
$refunded_state = $this->config->get('payment_alfabank_order_status_refunded_id') ?? 11;

$this->model_checkout_order->addOrderHistory($order_number, $refunded_state, $refund_massage, false);
} elseif ($response['errorCode'] == 0 && $response['orderStatus'] == 3) {
$is_part_cancel = $response['paymentAmountInfo']['approvedAmount'] > 0 && $response['paymentAmountInfo']['approvedAmount'] < $response['amount'];
$is_full_cancel = $response['paymentAmountInfo']['approvedAmount'] === 0;
if($is_full_cancel) {
$cancel_amount = '';
$cancel_massage = 'CANCEL_FULL_MESSAGE ' . $cancel_amount;
} else if($is_part_cancel) {
$cancel_amount = $response['amount'] - $response['paymentAmountInfo']['approvedAmount'];
$cancel_massage = 'CANCEL_MESSAGE ' . ($cancel_amount / 100);
}
$reversed_state = $this->config->get('payment_alfabank_order_status_reversed_id') ?? 12;
$this->model_checkout_order->addOrderHistory($order_number, $reversed_state, $cancel_massage, false);
} elseif ($response['errorCode'] == 0 && $response['orderStatus'] == 6) {
$comment = "Incoming callback declinedByTimeOut";
$this->model_checkout_order->addOrderHistory($order_number, 14, $comment, false); //14 system status CMS
}
else {
$this->response->redirect($this->url->link('checkout/failure', '', true));
}
}
}
public function comeback()
{
if (isset($this->request->get['orderId'])) {
$order_id = $this->request->get['orderId'];
} else {
die('Illegal Access');
}
$this->initializeGatewayLibrary();
$response = $this->method_library->_getGatewayOrderStatus($order_id);
$response = json_decode($response, true);
$ex = explode("_", $response['orderNumber']);
$order_number = $ex[0];
$this->load->model('checkout/order');
$order_info = $this->model_checkout_order->getOrder($order_number);
if ($order_info) {
if (($response['errorCode'] == 0) && (($response['orderStatus'] == 1) || ($response['orderStatus'] == 2))) {
if ($this->method_library->allowCallbacks == false) {
$this->_storeGatewayOrderData($order_id, $order_info, $response);
$comment = "Incoming ReturnUrl";
$this->model_checkout_order->addOrderHistory($order_number, $this->config->get('payment_alfabank_order_status_completed_id'), $comment, false);
}
$this->response->redirect($this->url->link('checkout/success', '', true));
} else {
$this->response->redirect($this->url->link('checkout/failure', '', true));
}
}
}
public function _storeGatewayOrderData($order_id, $order_info, $response) {
$this->load->model('extension/payment/alfabank');
$data = array(
'order_id' => (int)$order_info['order_id'],
'gateway_order_reference' => $order_id,
'currency' => $response['currency'],
'order_amount' => $response['amount'],
'order_amount_deposited' => $response['amount'],
'status_deposited' => $response['orderStatus'] === 1 ? 0 : 1, //todo
);
$this->model_extension_payment_alfabank->storeGatewayOrder($data);
}
private function _getBillingPayerData($orderData) {
$billingPayerData = array();
$pattern = '/^[A-Za-z0-9\s\'"!#$%&@^~*+=\-_.,:;<>|，΄´–\/?\\\\{}()\[\]\n]+$/';
if (isset($orderData['payment_address_id']) && $orderData['payment_address_id'] != 0) {
if (preg_match($pattern, $orderData['payment_city'])) {
$billingPayerData['billingCity'] = $orderData['payment_city'];
}
if (preg_match($pattern, $orderData['payment_iso_code_2'])) {
$billingPayerData['billingCountry'] = $orderData['payment_iso_code_2'];
}
if (preg_match($pattern, $orderData['payment_address_1'])) {
$billingPayerData['billingAddressLine1'] = $orderData['payment_address_1'];
}
if (preg_match($pattern, $orderData['payment_address_2'])) {
$billingPayerData['billingAddressLine2'] = $orderData['payment_address_2'];
}
if (preg_match($pattern, $orderData['payment_postcode'])) {
$billingPayerData['billingPostalCode'] = $orderData['payment_postcode'];
}
if (preg_match($pattern, $orderData['payment_zone'])) {
$billingPayerData['billingState'] = $orderData['payment_zone'];
}
} else {
if (preg_match($pattern, $orderData['shipping_city'])) {
$billingPayerData['billingCity'] = $orderData['shipping_city'];
}
if (preg_match($pattern, $orderData['shipping_iso_code_2'])) {
$billingPayerData['billingCountry'] = $orderData['shipping_iso_code_2'];
}
if (preg_match($pattern, $orderData['shipping_address_1'])) {
$billingPayerData['billingAddressLine1'] = $orderData['shipping_address_1'];
}
if (preg_match($pattern, $orderData['shipping_address_2'])) {
$billingPayerData['billingAddressLine2'] = $orderData['shipping_address_2'];
}
if (preg_match($pattern, $orderData['shipping_postcode'])) {
$billingPayerData['billingPostalCode'] = $orderData['shipping_postcode'];
}
if (preg_match($pattern, $orderData['shipping_zone'])) {
$billingPayerData['billingState'] = $orderData['shipping_zone'];
}
}
return $billingPayerData;
}
private function getTaxType($rate) {
$taxRates = [
20 => 6,
18 => 3,
10 => 2,
0  => 1,
5  => 10,
7  => 12
];
return $taxRates[$rate] ?? $this->config->get('payment_alfabank_taxType');
}
private function cleanPhoneNumber($telephone) {
return substr(preg_replace('/\D+/', '', $telephone), 0, 15);
}
}