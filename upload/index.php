<?php
// Configuration
if (is_file('config.php')) {
	require_once('config.php');
}

// Install
if (!defined('DIR_APPLICATION')) {
	header('Location: install/index.php');
	exit;
}

require_once(DIR_SYSTEM . 'library/ycp_basket_cache.php');

if (ycpFastHealthCheckHandled()) {
	exit;
}

// Check if the current PHP version is 8.0 or higher
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    // Suppress deprecation warnings only for this version and above
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// Version
define('VERSION', '3.0.3.6');

// Startup
require_once(DIR_SYSTEM . 'startup.php');

start('catalog');

function ycpFastHealthCheckHandled() {
	$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : '';

	if ($request_method !== 'POST') {
		return false;
	}

	$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
	$request_path = (string)parse_url($request_uri, PHP_URL_PATH);
	$route = isset($_GET['route']) ? (string)$_GET['route'] : '';

	$is_pretty_ycp_path = preg_match('~(?:^|/)api/v1/checkout/basket/check$~', $request_path);
	$is_route_ycp_path = ($route === 'api/ycp/basketCheck');

	if (!$is_pretty_ycp_path && !$is_route_ycp_path) {
		return false;
	}

	$raw_body = (string)file_get_contents('php://input');
	$normalized_body = $raw_body;
	$payload = ycpDecodeJsonBody($raw_body, $normalized_body);
	$_SERVER['YCP_RAW_BODY_CACHE'] = $normalized_body;

	if (!is_array($payload) || empty($payload['is_health_check'])) {
		return false;
	}

	$fast_token = getenv('YCP_API_TOKEN_FASTPATH');

	if (($fast_token === false || $fast_token === '') && defined('YCP_API_TOKEN_FASTPATH')) {
		$fast_token = YCP_API_TOKEN_FASTPATH;
	}

	if ($fast_token === false || $fast_token === '') {
		ycpFastJsonRespond(503, ['error' => 'Health-check authentication is not configured']);
		return true;
	}

	$auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim($_SERVER['HTTP_AUTHORIZATION']) : '';

	if (!preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
		ycpFastJsonRespond(401, ['error' => 'Authorization header missing or malformed']);
		return true;
	}

	if (!hash_equals((string)$fast_token, trim($matches[1]))) {
		ycpFastJsonRespond(401, ['error' => 'Invalid API token']);
		return true;
	}

	if (empty($payload['items']) || !is_array($payload['items'])) {
		ycpFastJsonRespond(400, ['error' => 'items array is required']);
		return true;
	}

	// Refreshes belong to the CLI job, never to a health-check request.
	$response = YcpBasketCache::read($payload);

	if ($response === null) {
		ycpFastJsonRespond(503, ['error' => 'Health-check snapshot is unavailable']);
		return true;
	}

	ycpFastJsonRespond(200, $response, true);
	return true;
}

function ycpFastJsonRespond($status_code, array $payload, $cache_hit = false) {
	ycpHealthCheckDebug($status_code, $payload, $cache_hit);

	$texts = [
		200 => 'OK',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		503 => 'Service Unavailable'
	];

	$status_text = isset($texts[$status_code]) ? $texts[$status_code] : '';

	header_remove();
	header('Content-Type: application/json');
	header('X-YCP-Fastpath: ' . ($cache_hit ? 'HIT' : '1'));
	header('HTTP/1.1 ' . (int)$status_code . ' ' . $status_text);

	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Enable with YCP_HEALTH_CHECK_DEBUG=true in config.php or the environment.
 * Append only: health checks must not read/rewrite the entire log or use SQL.
 */
function ycpHealthCheckDebug($status_code, array $response, $cache_hit) {
	$enabled = getenv('YCP_HEALTH_CHECK_DEBUG');
	if ($enabled === false || $enabled === '') {
		$enabled = defined('YCP_HEALTH_CHECK_DEBUG') ? YCP_HEALTH_CHECK_DEBUG : false;
	}

	if (!filter_var($enabled, FILTER_VALIDATE_BOOLEAN) || !defined('DIR_LOGS')) {
		return;
	}

	$request = json_decode(isset($_SERVER['YCP_RAW_BODY_CACHE']) ? $_SERVER['YCP_RAW_BODY_CACHE'] : '', true);
	$offer_ids = [];
	foreach (isset($request['items']) && is_array($request['items']) ? $request['items'] : [] as $item) {
		if (is_array($item) && isset($item['id']) && is_scalar($item['id'])) {
			$offer_ids[] = (string)$item['id'];
		}
	}

	$entry = [
		'is_health_check' => true,
		'offer_ids' => $offer_ids,
		'status' => (int)$status_code,
		'cache_hit' => (bool)$cache_hit,
		'returned_items' => isset($response['items']) ? count($response['items']) : 0,
		'elapsed_ms' => isset($_SERVER['REQUEST_TIME_FLOAT'])
			? round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 3) : null
	];
	if (isset($response['error'])) {
		$entry['error'] = $response['error'];
	}

	$line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	// A logging failure must not corrupt the JSON response or trigger a DB fallback.
	@file_put_contents(DIR_LOGS . 'ycp.log', date('Y-m-d H:i:s') . ' [health_check] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function ycpDecodeJsonBody($raw_body, &$normalized_body = null) {
	$normalized_body = $raw_body;

	$data = json_decode($raw_body, true);

	if (json_last_error() === JSON_ERROR_NONE) {
		return $data;
	}

	if (strpos($raw_body, '&') === false) {
		return null;
	}

	$decoded_body = html_entity_decode($raw_body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

	if ($decoded_body === $raw_body) {
		return null;
	}

	$data = json_decode($decoded_body, true);

	if (json_last_error() === JSON_ERROR_NONE) {
		$normalized_body = $decoded_body;
		return $data;
	}

	return null;
}
