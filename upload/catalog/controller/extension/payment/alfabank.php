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
		if (
			$this->method_library->enable_back_url_settings
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
					'itemAmount' => (int)round($product_amount * 100),
					'itemCode' => $product['product_id'] . "_" . $product['cart_id'], //fix by PLUG-1740, PLUG-2620
					'tax' => array(
						'taxType' => $tax_type,
					),
					'itemPrice' => (int)round((round($product['price'] + $product_taxSum, 2)) * 100),
				);
				if ($tax_type != "0" && $product_taxSum != "0") {
					$product_data['tax']['taxSum'] = (int)round($product_taxSum * 100);
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
				$delivery['itemAmount'] = (int)round($this->session->data['shipping_method']['cost'] * 100);
				$delivery['quantity']['value'] = 1;
				$delivery['quantity']['measure'] = $this->method_library->getDefaultMeasurement(); //todo?
				$delivery['itemCode'] = $this->session->data['shipping_method']['code'];
				$delivery['tax']['taxType'] = $this->config->get('payment_alfabank_taxType');
				$delivery['itemPrice'] = (int)round($this->session->data['shipping_method']['cost'] * 100);
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
						'itemAmount' => (int)round($voucher['amount'] * 100),
						'quantity' => array(
							'value' => 1,
							'measure' => $this->method_library->getDefaultMeasurement(),
						),
						'itemCode' => 'voucher_' . $key,
						'tax' => array(
							'taxType' => $this->config->get('payment_alfabank_taxType'),
						),
						'itemPrice' => (int)round($voucher['amount'] * 100)
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
			$args['dynamicCallbackUrl'] = $this->url->link('extension/payment/alfabank/callback') . "&order_id=" . $order_number;
		}
		if (defined('RBSPAYMENT_MANDATORY_CURRENCY') && RBSPAYMENT_MANDATORY_CURRENCY === true) {
			$currency_code = $this->method_library->get_numeric_currency_code($order_info['currency_code']);
			if (!empty($currency_code)) {
				$args['currency'] = $currency_code;
			}
		}
		if (!empty($order_info['customer_id'] && $order_info['customer_id'] > 0)) {
			$client_email = !empty($order_info['email']) ? $order_info['email'] : "";
			$args['clientId'] = md5($order_info['customer_id']  .  $client_email  . $order_info['store_url']);
		}
		if (defined('RBSPAYMENT_SEND_CLIENT_FULL_INFO') && RBSPAYMENT_SEND_CLIENT_FULL_INFO === true) {
			$billingPayerData = $this->_getBillingPayerData($order_info);
			if (!empty($billingPayerData)) {
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
			$currency_symbol = $this->getCurrencySymbol($order_info['currency_code']);
			$comment = sprintf(
				"Платежный заказ создан в шлюзе Alfabank\n" .
				"ID заказа в шлюзе: %s\n" .
				"Сумма: %s %s\n" .
				"Ссылка для оплаты сформирована",
				$response['orderId'],
				number_format($amount / 100, 2, '.', ' '),
				$currency_symbol
			);
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
		$this->load->model('extension/payment/alfabank');
		$order_info = $this->model_checkout_order->getOrder($order_number);
		if ($order_info) {
			if (($response['errorCode'] == 0) && (($response['orderStatus'] == 1) || ($response['orderStatus'] == 2))) {
				// Check if this order has already been marked as paid to prevent duplicate history entries
				$completed_status_id = $this->config->get('payment_alfabank_order_status_completed_id');
				$already_paid = $this->model_extension_payment_alfabank->get_oc_paid_status($order_number, array($completed_status_id));

				if (!$already_paid) {
					$this->_storeGatewayOrderData($order_id, $order_info, $response);

					$payment_type = $response['orderStatus'] == 2
						? 'Полная авторизация'
						: 'Предавторизация (Двухстадийный платеж)';

					$comment = sprintf(
						"Платеж подтвержден через callback\n" .
						"ID транзакции в шлюзе: %s\n" .
						"Тип платежа: %s\n" .
						"Сумма оплаты: %s\n" .
						"Код действия: %s - %s\n" .
						"Дата обработки: %s",
						$response['orderId'],
						$payment_type,
						number_format($response['amount'] / 100, 2, '.', ' '),
						$response['actionCode'] ?? 'N/A',
						$response['actionCodeDescription'] ?? 'Успешно',
						date('Y-m-d H:i:s')
					);

					// Add card info if available (masked)
					if (isset($response['cardAuthInfo']['pan'])) {
						$comment .= "\nКарта: " . $response['cardAuthInfo']['pan'];
					}

					$this->model_checkout_order->addOrderHistory($order_number, $completed_status_id, $comment, false);
				}
				$this->response->redirect($this->url->link('checkout/success', '', true));
			} elseif ($response['errorCode'] == 0 && $response['orderStatus'] == 4) {
				$is_part_refunted = $response['paymentAmountInfo']['approvedAmount'] === $response['amount'] && $response['paymentAmountInfo']['refundedAmount'] != 0;
				$is_full_refunded = $response['paymentAmountInfo']['approvedAmount'] === $response['paymentAmountInfo']['refundedAmount'];
				if ($is_full_refunded) {
					$refund_amount = $response['amount'] / 100;
					$refund_massage = 'REFUNDED_FULL_MESSAGE ' . $refund_amount;
				} else if ($is_part_refunted) {
					$refund_amount = $response['paymentAmountInfo']['refundedAmount'] / 100;
					$refund_massage = 'REFUNDED_MESSAGE ' . $refund_amount;
				}
				$refunded_state = $this->config->get('payment_alfabank_order_status_refunded_id') ?? 11;

				$this->model_checkout_order->addOrderHistory($order_number, $refunded_state, $refund_massage, false);
			} elseif ($response['errorCode'] == 0 && $response['orderStatus'] == 3) {
				$is_part_cancel = $response['paymentAmountInfo']['approvedAmount'] > 0 && $response['paymentAmountInfo']['approvedAmount'] < $response['amount'];
				$is_full_cancel = $response['paymentAmountInfo']['approvedAmount'] === 0;
				if ($is_full_cancel) {
					$cancel_amount = '';
					$cancel_massage = 'CANCEL_FULL_MESSAGE ' . $cancel_amount;
				} else if ($is_part_cancel) {
					$cancel_amount = $response['amount'] - $response['paymentAmountInfo']['approvedAmount'];
					$cancel_massage = 'CANCEL_MESSAGE ' . ($cancel_amount / 100);
				}
				$reversed_state = $this->config->get('payment_alfabank_order_status_reversed_id') ?? 12;
				$this->model_checkout_order->addOrderHistory($order_number, $reversed_state, $cancel_massage, false);
			} elseif ($response['errorCode'] == 0 && $response['orderStatus'] == 6) {
				$comment = "Incoming callback declinedByTimeOut";
				$this->model_checkout_order->addOrderHistory($order_number, 14, $comment, false); //14 system status CMS
			} else {
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
		$this->load->model('extension/payment/alfabank');
		$order_info = $this->model_checkout_order->getOrder($order_number);
		if ($order_info) {
			if (($response['errorCode'] == 0) && (($response['orderStatus'] == 1) || ($response['orderStatus'] == 2))) {
				if ($this->method_library->allowCallbacks == false) {
					// Check if this order has already been marked as paid to prevent duplicate history entries
					$completed_status_id = $this->config->get('payment_alfabank_order_status_completed_id');
					$already_paid = $this->model_extension_payment_alfabank->get_oc_paid_status($order_number, array($completed_status_id));

					if (!$already_paid) {
						$this->_storeGatewayOrderData($order_id, $order_info, $response);

						$payment_status = $response['orderStatus'] == 2
							? 'Полная авторизация'
							: 'Предавторизация';

						$comment = sprintf(
							"Платеж подтвержден через return URL\n" .
							"ID транзакции в шлюзе: %s\n" .
							"Статус платежа: %s\n" .
							"Сумма: %s\n" .
							"Покупатель вернулся в магазин",
							$order_id,
							$payment_status,
							number_format($response['amount'] / 100, 2, '.', ' ')
						);

						$this->model_checkout_order->addOrderHistory($order_number, $completed_status_id, $comment, false);
					}
				}
				$this->response->redirect($this->url->link('checkout/success', '', true));
			} else {
				$this->response->redirect($this->url->link('checkout/failure', '', true));
			}
		}
	}
	public function _storeGatewayOrderData($order_id, $order_info, $response)
	{
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
	private function _getBillingPayerData($orderData)
	{
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
	private function getTaxType($rate)
	{
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
	private function cleanPhoneNumber($telephone)
	{
		return substr(preg_replace('/\D+/', '', $telephone), 0, 15);
	}

	/**
	 * Get human-readable status name from Alfabank order status code
	 * @param int $status Status code
	 * @return string Status name
	 */
	private function getStatusName($status)
	{
		$statuses = [
			-1 => 'Error occurred',
			0 => 'Registered, not paid',
			1 => 'Pre-authorized (held)',
			2 => 'Fully authorized',
			3 => 'Authorization cancelled',
			4 => 'Refunded',
			5 => 'ACS authorization initiated',
			6 => 'Authorization declined'
		];
		return $statuses[$status] ?? 'Unknown status';
	}

	/**
	 * Get currency symbol from currency code
	 * @param string $currency_code Currency code
	 * @return string Currency symbol or code
	 */
	private function getCurrencySymbol($currency_code)
	{
		$symbols = [
			'RUB' => '₽',
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£'
		];
		return $symbols[$currency_code] ?? $currency_code;
	}

	/**
	 * Re-payment method for unpaid orders from order history
	 * Works with stored order data instead of session/cart
	 */
	public function repay()
	{
		if (!$this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/login', '', true));
			return;
		}

		if (!isset($this->request->get['order_id'])) {
			$this->response->redirect($this->url->link('account/order', '', true));
			return;
		}

		$order_id = (int)$this->request->get['order_id'];

		$this->load->model('account/order');
		$order_info = $this->model_account_order->getOrder($order_id);

		if (!$order_info) {
			$this->response->redirect($this->url->link('account/order', '', true));
			return;
		}

		// Verify payment method is alfabank
		if ($order_info['payment_code'] != 'alfabank') {
			$this->response->redirect($this->url->link('account/order/info', 'order_id=' . $order_id, true));
			return;
		}

		// Verify order is in pending payment status
		$pending_status_id = $this->config->get('payment_alfabank_order_status_before_id');
		if ($order_info['order_status_id'] != $pending_status_id) {
			$this->session->data['error'] = $this->language->get('error_order_already_processed');
			$this->response->redirect($this->url->link('account/order/info', 'order_id=' . $order_id, true));
			return;
		}

		$this->initializeGatewayLibrary();
		$this->load->model('checkout/order');

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

		if (!empty($order_info['telephone'])) {
			$jsonParams['phone'] = $this->cleanPhoneNumber($order_info['telephone']);
		}

		if (
			$this->method_library->enable_back_url_settings
			&& !empty($this->config->get('payment_alfabank_backToShopURL'))
		) {
			$jsonParams['backToShopUrl'] = $this->config->get('payment_alfabank_backToShopURL');
		}

		// Build order bundle from stored order data
		if ($this->method_library->enable_cart_options && $this->method_library->send_cart) {
			$orderBundle = array();
			$orderBundle['customerDetails']['email'] = $order_info['email'];

			if (!empty($order_info['telephone'])) {
				$orderBundle['customerDetails']['phone'] = $this->cleanPhoneNumber($order_info['telephone']);
			}

			// Get order products from database
			$order_products = $this->model_checkout_order->getOrderProducts($order_id);

			foreach ($order_products as $product) {
				$product_price = $product['price'];
				$product_tax = $product['tax'];
				$product_amount = (round($product_price + $product_tax, 2)) * $product['quantity'];

				$tax_type = $this->config->get('payment_alfabank_taxType');
				if ($product_tax > 0 && $product_price > 0) {
					$item_rate = $product_tax / $product_price * 100;
					$tax_type = $this->getTaxType($item_rate);
				}

				$product_data = array(
					'positionId' => $product['order_product_id'],
					'name' => $product['name'],
					'quantity' => array(
						'value' => $product['quantity'],
						'measure' => $this->method_library->getDefaultMeasurement(),
					),
					'itemAmount' => (int)round($product_amount * 100),
					'itemCode' => $product['product_id'] . "_" . $product['order_product_id'],
					'tax' => array(
						'taxType' => $tax_type,
					),
					'itemPrice' => (int)round((round($product_price + $product_tax, 2)) * 100),
				);

				if ($tax_type != "0" && $product_tax != "0") {
					$product_data['tax']['taxSum'] = (int)round($product_tax * 100);
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

			// Get shipping cost from order totals
			$order_totals = $this->model_checkout_order->getOrderTotals($order_id);
			foreach ($order_totals as $total) {
				if ($total['code'] == 'shipping' && $total['value'] > 0) {
					$delivery = array(
						'positionId' => 'delivery',
						'name' => $total['title'],
						'itemAmount' => (int)round($total['value'] * 100),
						'quantity' => array(
							'value' => 1,
							'measure' => $this->method_library->getDefaultMeasurement(),
						),
						'itemCode' => 'shipping',
						'tax' => array(
							'taxType' => $this->config->get('payment_alfabank_taxType'),
						),
						'itemPrice' => (int)round($total['value'] * 100),
					);

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
					break;
				}
			}

			// Get vouchers from order
			$order_vouchers = $this->model_checkout_order->getOrderVouchers($order_id);
			foreach ($order_vouchers as $key => $voucher) {
				$itemVoucher = array(
					'positionId' => 'voucher_' . $key,
					'name' => $voucher['description'],
					'itemAmount' => (int)round($voucher['amount'] * 100),
					'quantity' => array(
						'value' => 1,
						'measure' => $this->method_library->getDefaultMeasurement(),
					),
					'itemCode' => 'voucher_' . $key,
					'tax' => array(
						'taxType' => $this->config->get('payment_alfabank_taxType'),
					),
					'itemPrice' => (int)round($voucher['amount'] * 100)
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

			// Handle discount
			if (isset($orderBundle['cartItems']['items'])) {
				$discount = $this->method_library->discountHelper->discoverDiscount($amount, $orderBundle['cartItems']['items']);
				if ($discount > 0) {
					$this->method_library->discountHelper->setOrderDiscount($discount);
					$recalculatedPositions = $this->method_library->discountHelper->normalizeItems($orderBundle['cartItems']['items']);
					$orderBundle['cartItems']['items'] = $recalculatedPositions;
				}
			}
		}

		$args = array(
			'orderNumber' => $order_number . "_" . time(),
			'amount' => $amount,
			'returnUrl' => $return_url,
			'jsonParams' => json_encode($jsonParams),
		);

		if (!empty($order_info['telephone'])) {
			$args['orderPayerData'] = json_encode(array(
				"mobilePhone" => $this->cleanPhoneNumber($order_info['telephone'])
			));
		}

		if ($this->method_library->callbackType == "DYNAMIC") {
			$args['dynamicCallbackUrl'] = $this->url->link('extension/payment/alfabank/callback') . "&order_id=" . $order_number;
		}

		if (defined('RBSPAYMENT_MANDATORY_CURRENCY') && RBSPAYMENT_MANDATORY_CURRENCY === true) {
			$currency_code = $this->method_library->get_numeric_currency_code($order_info['currency_code']);
			if (!empty($currency_code)) {
				$args['currency'] = $currency_code;
			}
		}

		if (!empty($order_info['customer_id']) && $order_info['customer_id'] > 0) {
			$client_email = !empty($order_info['email']) ? $order_info['email'] : "";
			$args['clientId'] = md5($order_info['customer_id'] . $client_email . $order_info['store_url']);
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
			$comment = "Re-payment initiated from order history";
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
			$data['continue'] = $this->url->link('account/order/info', 'order_id=' . $order_id, true);
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$this->response->setOutput($this->get_template('error/alfabank', $data));
		} else {
			$this->response->redirect($response['formUrl']);
		}
	}

	public function cron()
	{
		$debug = $this->config->get('payment_alfabank_logging');
		// orderStatus - По значению этого параметра определяется состояние заказа в платёжной системе.
		// Список возможных значений:
		// 0 - Заказ зарегистрирован, но не оплачен;
		// 1 - Предавторизованная сумма захолдирована (для двухстадийных платежей);
		// 2 - Проведена полная авторизация суммы заказа;
		// 3 - Авторизация отменена;
		// 4 - По транзакции была проведена операция возврата;
		// 5 - Инициирована авторизация через ACS банка-эмитента;
		// 6 - Авторизация отклонена.
		// -1 - There was an Error Status that has been set to eliminate the necessity to check errorCode once more

		// Check payments in these states: error, not paid, pre-authorized (held), ACS authorization
		$order_in_payment_states = array(-1, 0, 1, 5);
		$this->load->model('extension/payment/alfabank');

		// Timeout for stuck payments
		$stuck_timeout = 3600; // 1 hour - payment stuck in processing

		// Get the list of all payments
		$result = $this->model_extension_payment_alfabank->get_alfabank_current_payment_list();

		if ($debug && count($result) > 0) {
			$this->log->write(sprintf("Alfabank cron: Processing %d payment records", count($result)));
		}

		foreach ($result as $item) {
			// Use Unix timestamp from database to avoid timezone issues
			$time_since_update = time() - (int)$item['date_updated_timestamp'];

			if ($debug) {
				$this->log->write(sprintf(
					"Alfabank cron: Order #%s | Current time: %d | Last update: %d | Time since update: %d seconds",
					$item['order_id'],
					time(),
					(int)$item['date_updated_timestamp'],
					$time_since_update
				));
			}

			if (in_array($item['status_deposited'], $order_in_payment_states)) {
				$check = $this->model_extension_payment_alfabank->check_payment_status($item['gateway_order_reference']);

				switch ($check['orderStatus']) {
					case 1: // Предавторизованная сумма захолдирована (для двухстадийных платежей)
					case 2: // Проведена полная авторизация суммы заказа
						$this->model_extension_payment_alfabank->update_opencart_order_history($item['order_id'], $check);
						if ($debug) {
							$this->log->write(sprintf(
								"Alfabank cron: Order #%s | Gateway: %s | Status: %s (%s) | Amount: %s | Action: %s - %s",
								$item['order_id'],
								$item['gateway_order_reference'],
								$check['orderStatus'],
								$this->getStatusName($check['orderStatus']),
								number_format($check['amount'] / 100, 2),
								$check['actionCode'] ?? 'N/A',
								$check['actionCodeDescription'] ?? 'Success'
							));
						}
						break;

					case 0: // Заказ зарегистрирован, но не оплачен
						// Check if payment is stuck (no activity for over 1 hour)
						if ($time_since_update > $stuck_timeout) {
							if ($debug) {
								$this->log->write(sprintf(
									"Alfabank cron: Order #%s payment timeout (>1h) | Gateway: %s | Status: Abandoned - no payment received",
									$item['order_id'],
									$item['gateway_order_reference']
								));
							}
						}
						break;

					case 5: // Инициирована авторизация через ACS банка-эмитента
						// Check if ACS authorization is stuck
						if ($time_since_update > $stuck_timeout) {
							if ($debug) {
								$this->log->write(sprintf(
									"Alfabank cron: Order #%s ACS authorization stuck (>1h) | Gateway: %s | Possible 3DS timeout",
									$item['order_id'],
									$item['gateway_order_reference']
								));
							}
						}
						break;

					case 3: // Авторизация отменена
						if ($debug) {
							$this->log->write(sprintf(
								"Alfabank cron: Order #%s payment cancelled | Gateway: %s | Reason: %s",
								$item['order_id'],
								$item['gateway_order_reference'],
								$check['actionCodeDescription'] ?? 'Authorization cancelled'
							));
						}
						break;

					case 6: // Авторизация отклонена
						if ($debug) {
							$this->log->write(sprintf(
								"Alfabank cron: Order #%s payment declined | Gateway: %s | Status: %s | Reason: %s",
								$item['order_id'],
								$item['gateway_order_reference'],
								$this->getStatusName($check['orderStatus']),
								$check['actionCodeDescription'] ?? 'Payment declined'
							));
						}
						break;

					case 4: // По транзакции была проведена операция возврата
						if ($debug) {
							$this->log->write(sprintf(
								"Alfabank cron: Order #%s refunded | Gateway: %s | Amount: %s",
								$item['order_id'],
								$item['gateway_order_reference'],
								number_format($check['amount'] / 100, 2)
							));
						}
						break;

					case -1: // Error status
						if ($debug) {
							$this->log->write(sprintf(
								"Alfabank cron: Order #%s error checking status | Gateway: %s | Error: Gateway communication failed",
								$item['order_id'],
								$item['gateway_order_reference']
							));
						}
						break;
				}

				// Always update the alfabank_order record with latest status
				$this->model_extension_payment_alfabank->update_alfabank_order($check);
			} else {
				// Payment is in completed state (2) or other final state - no action needed
				// Keep the record for payment history tracking
				if ($debug) {
					$this->log->write(sprintf(
						"Alfabank cron: Order #%s in final state (%s) | Time since update: %d seconds | Record preserved for history",
						$item['order_id'],
						$this->getStatusName($item['status_deposited']),
						$time_since_update
					));
				}
			}
		}

		if ($debug) {
			$this->log->write("Alfabank cron: Processing completed");
		}
	}
}
