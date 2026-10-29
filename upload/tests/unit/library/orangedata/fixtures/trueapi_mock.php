<?php
/**
 * True API v4 mock for unit tests (run with `php -S 127.0.0.1:<port> trueapi_mock.php`).
 *
 * Mirrors the sandbox behaviour the module relies on:
 *   GET  /api/v4/true-api/cdn/info     → 200 without hosts (the client then checks on the base host itself)
 *   POST /api/v4/true-api/codes/check  → ГИС МТ check answer for codes[0]
 * Auth: header X-API-KEY must equal "test-key", otherwise 401 (like a rejected key).
 * Markers in the code: BLOCKED → isBlocked=true, GONE → 410 (key switched off by ЦРПТ). Anything else is allowed.
 */

function reply($code, $body) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return true;
}

$key  = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($key !== 'test-key') {
    return reply(401, array('error_message' => 'Invalid X-Api-Key'));
}

if ($path === '/api/v4/true-api/cdn/info') {
    return reply(200, array('code' => 0, 'description' => 'ok'));
}

if ($path === '/api/v4/true-api/codes/check') {
    $in  = json_decode(file_get_contents('php://input'), true);
    $cis = isset($in['codes'][0]) ? (string) $in['codes'][0] : '';
    if (strpos($cis, 'GONE') !== false) {
        return reply(410, array('error_message' => 'Устаревшее API'));
    }
    $code = array(
        'cis' => $cis, 'gtin' => '04600000000001', 'groupIds' => array(2),
        'valid' => true, 'found' => true, 'utilised' => true, 'verified' => true, 'realizable' => true,
        'sold' => false, 'isBlocked' => strpos($cis, 'BLOCKED') !== false,
    );
    return reply(200, array(
        'code' => 0, 'description' => 'ok',
        'reqId' => '11111111-2222-4333-8444-555555555555', 'reqTimestamp' => 1790000000000,
        'codes' => array($code),
    ));
}

return reply(404, array('error_message' => 'not found: ' . $path));
