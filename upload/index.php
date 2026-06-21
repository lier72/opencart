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

if (ycpFastHealthCheckHandled()) {
	exit;
}

// Check if the current PHP version is 8.2 or higher
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    // Suppress deprecation warnings only for this version and above
	echo 'Inside the IF Current PHP version: ' . PHP_VERSION . "\n";
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
		return false;
	}

	$auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? trim($_SERVER['HTTP_AUTHORIZATION']) : '';

	if (!preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
		ycpFastHealthCheckRespond(401, ['error' => 'Authorization header missing or malformed']);
		return true;
	}

	if (!hash_equals((string)$fast_token, trim($matches[1]))) {
		ycpFastHealthCheckRespond(401, ['error' => 'Invalid API token']);
		return true;
	}

	if (empty($payload['items']) || !is_array($payload['items'])) {
		ycpFastHealthCheckRespond(400, ['error' => 'items array is required']);
		return true;
	}

	ycpFastHealthCheckRespond(200, ['items' => []]);
	return true;
}

function ycpFastHealthCheckRespond($status_code, array $payload) {
	$texts = [
		200 => 'OK',
		400 => 'Bad Request',
		401 => 'Unauthorized'
	];

	$status_text = isset($texts[$status_code]) ? $texts[$status_code] : '';

	header_remove();
	header('Content-Type: application/json');
	header('X-YCP-Fastpath: 1');
	header('HTTP/1.1 ' . (int)$status_code . ' ' . $status_text);

	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
