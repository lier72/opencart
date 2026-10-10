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
			// Split positions into two groups for AlfabankDiscount::distribute(): $goods receive the
			// whole-order discount; $fullPrice (delivery / COD fee) stay at full price so returns are not
			// over-refunded. Both the Alfabank check and the OrangeData зачёт-аванса check use this split.
			$goods = array();
			$fullPrice = array();
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
				$goods[] = $product_data;
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
				$fullPrice[] = $delivery;
			}
			if (isset($this->session->data['vouchers']) && count($this->session->data['vouchers']) > 0) {
				foreach ($this->session->data['vouchers'] as $key => $voucher) {
					$itemVoucher = array(
						'positionId' => 'v' . $key,
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
					$goods[] = $itemVoucher;
				}
			}

			// Spread the whole-order discount across $goods only, keeping $fullPrice (delivery) at full
			// value. distribute() returns the merged, reconciled positions (Σ == $amount to the kopeck).
			$orderBundle['cartItems']['items'] = $this->method_library->discountHelper->distribute($goods, $fullPrice, $amount);
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

			$this->_storeInitialGatewayOrderData($order_info, $response, $args['orderNumber'], $amount);
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
	protected function initializeGatewayLibrary()
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
        $reference = $this->request->get['mdOrder'] ?? '';
        if (!is_string($reference) || $reference === '') {
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
            return;
        }
        try {
            $this->initializeGatewayLibrary();
            // Never trust callback operation/amount parameters: read the gateway itself.
            $response = json_decode($this->method_library->_getGatewayOrderStatus($reference), true);
            require_once DIR_SYSTEM . 'library/alfabank/AlfabankGatewayState.php';
            if (!isset($response['errorCode']) || !AlfabankGatewayState::normalize($response) || empty($response['orderNumber'])) {
                $this->response->addHeader('HTTP/1.1 503 Service Unavailable');
                return;
            }
            $response['orderId'] = $reference;
            $order_number = (int)explode('_', $response['orderNumber'])[0];
            $this->load->model('checkout/order');
            $this->load->model('extension/payment/alfabank');
            $order = $this->model_checkout_order->getOrder($order_number);
            if ($order) {
                if (in_array((int)$response['orderStatus'], array(1, 2), true)) {
                    $this->_storeGatewayOrderData($reference, $order, $response);
                    $this->model_extension_payment_alfabank->update_opencart_order_history($order_number, $response);
                } else {
                    $this->model_extension_payment_alfabank->update_alfabank_order($response);
                    $this->model_extension_payment_alfabank->syncAdjustmentHistory($order_number, $response);
                }
            } else {
                // Retain financial evidence even if the OpenCart order was removed.
                $this->model_extension_payment_alfabank->update_alfabank_order($response);
            }
            $this->response->setOutput('OK');
        } catch (Exception $e) {
            $this->log->write('AlfaBank callback processing failed: ' . $e->getMessage());
            $this->response->addHeader('HTTP/1.1 503 Service Unavailable');
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
        require_once DIR_SYSTEM . 'library/alfabank/AlfabankGatewayState.php';
        if (!isset($response['errorCode']) || !AlfabankGatewayState::normalize($response) || empty($response['orderNumber'])) {
            $this->response->redirect($this->url->link('checkout/failure', '', true));
            return;
        }
		$ex = explode("_", $response['orderNumber']);
		$order_number = $ex[0];
		$this->load->model('checkout/order');
		$this->load->model('extension/payment/alfabank');
		$order_info = $this->model_checkout_order->getOrder($order_number);
		if ($order_info) {
			if (($response['errorCode'] == 0) && (($response['orderStatus'] == 1) || ($response['orderStatus'] == 2))) {
				$this->_storeGatewayOrderData($order_id, $order_info, $response);

                $response['orderId'] = $order_id;
                $this->model_extension_payment_alfabank->update_opencart_order_history($order_number, $response);
				$this->response->redirect($this->url->link('checkout/success', '', true));
			} else {
				$this->response->redirect($this->url->link('checkout/failure', '', true));
			}
		}
	}
	public function _storeGatewayOrderData($order_id, $order_info, $response)
	{
		$this->load->model('extension/payment/alfabank');
		// Ensure currency is stored in numeric format
		$currency_value = isset($response['currency']) ? $response['currency'] : $order_info['currency_code'];
		$numeric_currency = $this->method_library->normalize_currency_to_numeric($currency_value);

		// Extract payment way and payment system from response
		$payment_way = isset($response['paymentWay']) ? $response['paymentWay'] : '';
		$payment_system = '';
		if (isset($response['cardAuthInfo']['paymentSystem'])) {
			$payment_system = $response['cardAuthInfo']['paymentSystem'];
		}

		$data = array(
			'order_id' => (int)$order_info['order_id'],
			'gateway_order_reference' => $order_id,
			'tx_url' => isset($response['formUrl']) ? $response['formUrl'] : '',
			'order_number' => isset($response['orderNumber']) ? $response['orderNumber'] : '',
			'currency' => $numeric_currency,
			'payment_way' => $payment_way,
			'payment_system' => $payment_system,
			'order_amount' => $response['amount'],
			'order_amount_deposited' => $response['paymentAmountInfo']['approvedAmount'] ?? ($response['orderStatus'] == 2 ? $response['amount'] : 0),
			// Store the AlfaBank orderStatus code (-1..6), never a local boolean.
			'status_deposited' => isset($response['orderStatus']) ? (int)$response['orderStatus'] : 0,
		);
		$this->model_extension_payment_alfabank->storeGatewayOrder($data);
        $response['orderId'] = $order_id;
        $this->model_extension_payment_alfabank->update_alfabank_order($response);
	}

	private function _storeInitialGatewayOrderData($order_info, $response, $order_number, $amount)
	{
		$this->load->model('extension/payment/alfabank');

		$currency_value = isset($response['currency']) ? $response['currency'] : $order_info['currency_code'];
		$numeric_currency = $this->method_library->normalize_currency_to_numeric($currency_value);

		$data = array(
			'order_id' => (int)$order_info['order_id'],
			'gateway_order_reference' => $response['orderId'],
			'tx_url' => isset($response['formUrl']) ? $response['formUrl'] : '',
			'order_number' => $order_number,
			'currency' => $numeric_currency,
			'order_amount' => $amount,
			'order_amount_deposited' => 0,
			'status_deposited' => 0,
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

			// Split positions into two groups for AlfabankDiscount::distribute(): $goods receive the
			// whole-order discount; $fullPrice (delivery / COD fee) stay at full price so returns are not
			// over-refunded. Must mirror the OrangeData зачёт-аванса check built in OrangeDataService.
			$goods = array();
			$fullPrice = array();

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
				$goods[] = $product_data;
			}

			// Shipping kept at full price (never discounted). The CDEK cash-on-delivery fee cannot occur on
			// an Alfabank (card-prepaid) order, so only 'shipping' is handled here; OrangeDataService adds the
			// COD fee for the paths where it applies.
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
					$fullPrice[] = $delivery;
					break;
				}
			}

			// Get vouchers from order
			$order_vouchers = $this->model_checkout_order->getOrderVouchers($order_id);
			foreach ($order_vouchers as $key => $voucher) {
				$itemVoucher = array(
					'positionId' => 'v' . $key,
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
				$goods[] = $itemVoucher;
			}

			// Spread the whole-order discount across $goods only, keeping $fullPrice (delivery / COD fee)
			// at full value. distribute() returns the merged, reconciled positions (Σ == $amount).
			if ($goods) {
				$orderBundle['cartItems']['items'] = $this->method_library->discountHelper->distribute($goods, $fullPrice, $amount);
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
			$this->_storeInitialGatewayOrderData($order_info, $response, $args['orderNumber'], $amount);
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
        $this->load->model('extension/payment/alfabank');
        foreach ($this->model_extension_payment_alfabank->getPaymentsForReconciliation() as $attempt) {
            try {
                $response = $this->model_extension_payment_alfabank->check_payment_status($attempt['gateway_order_reference']);
                if ((int)$response['orderStatus'] >= 0 &&
                    (!isset($response['orderNumber']) || (int)explode('_', $response['orderNumber'])[0] !== (int)$attempt['order_id'])) {
                    $this->log->write('AlfaBank reconciliation: gateway order mismatch for attempt ' . (int)$attempt['gateway_order_id']);
                    $this->model_extension_payment_alfabank->update_alfabank_order(array('orderId' => $attempt['gateway_order_reference'], 'orderStatus' => -1));
                    continue;
                }
                if (in_array((int)$response['orderStatus'], array(1,2), true)) {
                    $this->model_extension_payment_alfabank->update_opencart_order_history($attempt['order_id'], $response);
                } elseif (in_array((int)$response['orderStatus'], array(3,4), true)) {
                    $this->model_extension_payment_alfabank->syncAdjustmentHistory($attempt['order_id'], $response);
                }
                $this->model_extension_payment_alfabank->update_alfabank_order($response);
            } catch (Exception $e) {
                $this->log->write('AlfaBank reconciliation failed for attempt ' . (int)$attempt['gateway_order_id'] . ': ' . $e->getMessage());
            }
        }
	}
}
