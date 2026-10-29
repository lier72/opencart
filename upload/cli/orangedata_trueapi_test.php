<?php
/**
 * True API v4 разрешительный-режим check (CLI) — test layer for solution 1 (direct ГИС МТ, no ТС ПИоТ).
 *
 * Steps: cdn/info → health/check per host → pick the fastest → codes/check → normalized verdict (the same
 * MarkingVerdict rules as the module's check backend). Without a key it only proves the endpoints are reachable (expects 401).
 *
 * Usage:
 *   php cli/orangedata_trueapi_test.php [--env=sandbox|prod] [--base=https://...]
 *       [--key=<X-API-KEY> | --key-file=/path/to/key | env TRUE_API_KEY]
 *       [--cis='<raw KM, GS as \x1d or <GS>>'] [--b64] [--fn=<ФН serial>] [--proxy=socks5h://127.0.0.1:1080]
 *       [--expect-allow|--expect-deny]
 *
 * Keep the key out of the repo, e.g. /Users/max/Sites/storage/orangedata/trueapi_sandbox.key (chmod 600).
 *
 * Exit codes: 0 ok (and expectation met), 1 usage/transport error, 2 not authorized / no host, 3 expectation failed.
 */

require __DIR__ . '/../system/library/orangedata/TrueApiClient.php';

$args = array();
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/s', $arg, $m)) {
        $args[$m[1]] = isset($m[2]) ? $m[2] : true;
    }
}

$key = '';
if (!empty($args['key'])) {
    $key = $args['key'];
} elseif (!empty($args['key-file'])) {
    if (!is_readable($args['key-file'])) {
        fwrite(STDERR, "Key file not readable: {$args['key-file']}\n");
        exit(1);
    }
    $key = trim(file_get_contents($args['key-file']));
} elseif (getenv('TRUE_API_KEY')) {
    $key = getenv('TRUE_API_KEY');
}

$client = new TrueApiClient(array(
    'env'      => isset($args['env']) ? $args['env'] : 'sandbox',
    'base_url' => isset($args['base']) ? $args['base'] : '',
    'api_key'  => $key,
    'proxy'    => isset($args['proxy']) ? $args['proxy'] : '',
    'timeout'  => 15,
));

echo "== cdn/info + host selection ==" . ($key === '' ? '  (no X-API-KEY: reachability only, 401 expected)' : '') . "\n";
$sel  = $client->selectHost();
$info = $sel['info'];
echo "HTTP {$info['http_code']}" . ($info['error'] ? "  ERROR {$info['error']}" : '') . "\n" . $info['raw'] . "\n";
if ($info['error']) {
    exit(1);
}
foreach ($sel['candidates'] as $c) {
    printf("  %-32s code=%s avgTimeMs=%s%s\n", $c['host'], var_export($c['code'], true), var_export($c['avgTimeMs'], true), $c['error'] ? ' ERROR ' . $c['error'] : '');
}
if ($key === '') {
    $ok = (int) $info['http_code'] === 401;
    echo $ok ? "\nReachable; endpoint demands a key as expected. Re-run with --key-file to check codes.\n" : "\nUnexpected answer without a key.\n";
    exit($ok ? 0 : 1);
}
if (!$sel['host']) {
    echo "\nNo healthy host (check the key / env).\n";
    exit(2);
}
echo "Selected host: {$sel['host']}\n";

if (empty($args['cis'])) {
    echo "\nNo --cis given; host selection only.\n";
    exit(0);
}

$cis = str_replace(array('\\u001d', '\\x1d', '<GS>'), "\x1d", $args['cis']);
if (!empty($args['b64'])) {
    $cis = base64_encode($cis);
}
echo "\n== codes/check ==\n";
$resp = $client->checkCodes(array($cis), null, isset($args['fn']) ? $args['fn'] : '');
echo "HTTP {$resp['http_code']}" . ($resp['error'] ? "  ERROR {$resp['error']}" : '') . "\n" . $resp['raw'] . "\n";
if ((int) $resp['http_code'] === 401 || (int) $resp['http_code'] === 403) {
    exit(2);
}

$n = TrueApiClient::normalizeResponse($resp);
echo "\n== normalized (as stored per unit) ==\n";
foreach (array('status_code', 'is_sale_allowed', 'reason_code', 'reason_text', 'tag1265', 'is_checked_offline', 'gtin', 'pg') as $k) {
    printf("  %-18s %s\n", $k, var_export($n[$k], true));
}

if (!empty($args['expect-allow']) && $n['is_sale_allowed'] !== 1) exit(3);
if (!empty($args['expect-deny']) && $n['is_sale_allowed'] !== 0) exit(3);
exit($n['status_code'] === 200 ? 0 : 1);
