<?php
/**
 * Catalog (storefront) controller for the AI Chatbot racket advisor.
 *
 * This module is additive to, and independent from, the Racket Finder module:
 * it renders a floating chat bubble and relays browser messages to the
 * ai-chatbot Node service (services/ai-chatbot/), which answers using an LLM
 * with MCP tools reading the SAME racket data Racket Finder already serves
 * publicly (finder-data.json + extension/module/racket_finder/data) — so
 * there is one source of truth, not two.
 *
 * All secrets (the LLM API key, entirely) live only on the Node service.
 * This controller only holds the shared HMAC secret used to authenticate
 * itself to that service — never an LLM key.
 *
 * Methods:
 *  - index($setting): renders the floating bubble markup for a layout
 *              position (site-wide footer, typically).
 *  - chat():   AJAX proxy. Route: extension/module/ai_chatbot/chat
 *              Verifies status + a cheap disk-cache rate limit, HMAC-signs
 *              the browser's raw JSON body, and forwards it server-side to
 *              the Node service's POST /chat. The response is relayed back
 *              to the browser verbatim (it is already plain JSON).
 */
class ControllerExtensionModuleAiChatbot extends Controller {

	/** Guard so the fatal-error shutdown handler is registered only once. */
	private static $shutdownRegistered = false;

	/**
	 * Read settings from the first enabled ai_chatbot module instance.
	 * Mirrors ControllerExtensionModuleRacketFinder::settings().
	 */
	private function settings() {
		$out = array(
			'status'               => 0,
			'chat_service_url'     => '',
			'shared_secret'        => '',
			'rate_limit_per_minute' => 10,
			'welcome_message'      => '',
			'debug'                => 0,
		);

		$q = $this->db->query("SELECT `setting` FROM `" . DB_PREFIX . "module` WHERE `code` = 'ai_chatbot'");
		foreach ($q->rows as $row) {
			$s = json_decode($row['setting'], true);
			if (!empty($s['status'])) {
				$out['status']               = 1;
				$out['chat_service_url']     = isset($s['chat_service_url']) ? rtrim($s['chat_service_url'], '/') : '';
				$out['shared_secret']        = isset($s['shared_secret']) ? $s['shared_secret'] : '';
				$out['rate_limit_per_minute'] = isset($s['rate_limit_per_minute']) ? (int)$s['rate_limit_per_minute'] : 10;
				$out['welcome_message']      = isset($s['welcome_message']) ? $s['welcome_message'] : '';
				$out['debug']                = !empty($s['debug']) ? 1 : 0;
				break;
			}
		}

		return $out;
	}

	/**
	 * Floating chat bubble, placed via a layout position (e.g. site-wide
	 * footer) the same way the Racket Finder button is placed — no theme
	 * template edits needed. $setting is the layout module row (unused: all
	 * real config comes from settings(), shared with chat()).
	 */
	public function index($setting) {
		$conf = $this->settings();

		if (empty($conf['status'])) {
			return '';
		}

		$this->load->language('extension/module/ai_chatbot');

		$this->document->addStyle('catalog/view/stylesheet/ai_chatbot/widget.css?v=' . $this->assetVersion('stylesheet/ai_chatbot/widget.css'));
		$this->document->addScript('catalog/view/javascript/ai_chatbot/widget.js?v=' . $this->assetVersion('javascript/ai_chatbot/widget.js'));

		$data['chat_url']        = $this->url->link('extension/module/ai_chatbot/chat', '', true);
		$data['welcome_message'] = $conf['welcome_message'] !== '' ? $conf['welcome_message'] : $this->language->get('text_welcome_default');
		$data['heading_title']   = $this->language->get('heading_title');
		$data['input_placeholder'] = $this->language->get('text_input_placeholder');
		$data['send_button']     = $this->language->get('button_send');
		$data['open_button']     = $this->language->get('button_open');

		return $this->load->view('extension/module/ai_chatbot', $data);
	}

	/**
	 * AJAX proxy: forwards the browser's chat message to the Node service,
	 * signed so the service can trust it came from this store. Never exposes
	 * the Node service URL, the shared secret, or any LLM key to the browser.
	 */
	public function chat() {
		$conf = $this->settings();
		$this->bootLogging($conf['debug']);

		try {

		if (empty($conf['status']) || $conf['chat_service_url'] === '' || $conf['shared_secret'] === '') {
			$this->response->addHeader('HTTP/1.1 404 Not Found');
			return;
		}

		if (!$this->checkRateLimit($conf['rate_limit_per_minute'])) {
			$this->response->addHeader('HTTP/1.1 429 Too Many Requests');
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode(array('error' => 'rate_limited')));
			return;
		}

		$body = (string)file_get_contents('php://input');
		if ($body === '' || strlen($body) > 8192) {
			$this->response->addHeader('HTTP/1.1 400 Bad Request');
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode(array('error' => 'invalid_body')));
			return;
		}

		$timestamp = (string)time();
		$signature = hash_hmac('sha256', $timestamp . "\n" . $body, $conf['shared_secret']);

		$result = $this->postJson($conf['chat_service_url'] . '/chat', $body, array(
			'Content-Type: application/json',
			'X-Chatbot-Timestamp: ' . $timestamp,
			'X-Chatbot-Signature: ' . $signature,
		));

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->setOutput($result);

		} catch (\Throwable $e) {
			$this->handleError($e, $conf['debug']);
		}
	}

	/**
	 * Dedicated, safe HTTP POST for the server-to-server call to the Node
	 * service. Deliberately NOT system/library/curl.php: that wrapper has
	 * SSL verification disabled and no custom-header support, both of which
	 * this call needs (SSL verify on; HMAC headers).
	 */
	private function postJson($url, $body, array $headers) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_TIMEOUT        => 15,
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_POSTFIELDS     => $body,
		));

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);
		curl_close($ch);

		if ($response === false) {
			throw new \RuntimeException('chat service unreachable: ' . $curl_error);
		}
		if ($http_code >= 500) {
			throw new \RuntimeException('chat service returned HTTP ' . $http_code);
		}

		return $response;
	}

	/**
	 * Cheap first line of defense before any call to the chat service: a
	 * disk-cache sliding-window counter per session, same idiom as
	 * ControllerExtensionModuleRacketFinder's liveDataCached(). The Node
	 * service applies its own, stricter per-conversation limit behind this.
	 */
	private function checkRateLimit($max_per_minute) {
		if ($max_per_minute <= 0) {
			return true;
		}

		$key = $this->session->getId() ?: (isset($this->request->server['REMOTE_ADDR']) ? $this->request->server['REMOTE_ADDR'] : 'unknown');
		$dir = DIR_CACHE . 'ai_chatbot_rate/';
		if (!is_dir($dir)) {
			@mkdir($dir, 0777, true);
		}
		$file = $dir . md5($key) . '.json';

		$window_start = time() - 60;
		$hits = array();
		if (is_file($file)) {
			$decoded = json_decode((string)file_get_contents($file), true);
			if (is_array($decoded)) {
				$hits = array_values(array_filter($decoded, function ($t) use ($window_start) {
					return $t >= $window_start;
				}));
			}
		}

		if (count($hits) >= $max_per_minute) {
			return false;
		}

		$hits[] = time();
		@file_put_contents($file, json_encode($hits));

		return true;
	}

	/** Cache-busting token for a static asset (its mtime), same idiom as Racket Finder. */
	private function assetVersion($relative_path) {
		$file = DIR_APPLICATION . '../catalog/view/' . $relative_path;
		return is_file($file) ? (string)filemtime($file) : '0';
	}

	/**
	 * Registers (once per request) a shutdown handler that records PHP fatal
	 * errors the try/catch in chat() cannot catch, mirroring
	 * ControllerExtensionModuleRacketFinder::bootLogging().
	 */
	private function bootLogging($debug) {
		if ($debug) {
			@ini_set('display_errors', '1');
			error_reporting(E_ALL);
		}

		if (self::$shutdownRegistered) {
			return;
		}
		self::$shutdownRegistered = true;

		register_shutdown_function(function () use ($debug) {
			$e = error_get_last();
			$fatal = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);

			if ($e && in_array($e['type'], $fatal, true)) {
				$line = 'PHP Fatal: ' . $e['message'] . ' in ' . $e['file'] . ' on line ' . $e['line'];
				@file_put_contents(DIR_LOGS . 'ai_chatbot.log', date('Y-m-d H:i:s') . ' - ' . $line . "\n", FILE_APPEND);

				if ($debug) {
					echo "\n\nAI Chatbot fatal error\n\n" . $line . "\n";
				}
			}
		});
	}

	/**
	 * Handle a Throwable caught in chat(): always log, and with Debug OFF
	 * fail as a generic JSON error (never leak internals to the browser).
	 * With Debug ON the detail is returned instead, same as Racket Finder's
	 * handleError($e, $debug, true).
	 */
	private function handleError($e, $debug) {
		$detail = get_class($e) . ': ' . $e->getMessage()
			. ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString();

		$log = new Log('ai_chatbot.log');
		$log->write($detail);

		$this->response->addHeader('HTTP/1.1 502 Bad Gateway');
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');

		if ($debug) {
			$this->response->setOutput(json_encode(array('error' => $detail), JSON_UNESCAPED_UNICODE));
		} else {
			$this->response->setOutput(json_encode(array('error' => 'chat_unavailable')));
		}
	}
}
