<?php
/**
 * OrangeData ТС ПИоТ smoke test (CLI).
 *
 * Purpose: validate connectivity, mutual-TLS and request signing against the OrangeData TEST contour
 * end-to-end, independently of OpenCart, before wiring the admin/order workflow. It performs one
 * разрешительный режим check (POST /api/v2/piot/codes/check) and prints the parsed result.
 *
 * Scope: developer/QA tool only. Not used at runtime. Run it after dropping the vendor test files from
 * github.com/orangedata-official/API (files_for_test/) onto disk.
 *
 * Usage:
 *   php cli/orangedata_smoke_test.php \
 *       --cert=/path/files_for_test/client.crt \
 *       --key=/path/files_for_test/client.key \
 *       --signxml=/path/files_for_test/private_key.xml \
 *       [--cis=<base64 or raw KM>] [--raw] [--pg=2] [--price=400.45] [--insecure]
 *
 * Options:
 *   --cert       Path to client SSL certificate (PEM).                         [required]
 *   --key        Path to client SSL private key (PEM).                         [required]
 *   --signxml    Path to the .NET RSA XML signing key (test private_key.xml).  [--signxml or --signpem]
 *   --signpem    Path to a PEM signing key (production alternative).
 *   --cis        Marking code. Base64 by default; add --raw if passing a raw scanned code.
 *   --raw        Treat --cis as a raw code and Base64-encode it for the request.
 *   --pg         Optional товарная группа id (2 = footwear, 1 = apparel).
 *   --price      Optional price in rubles.
 *   --base       Override base URL (default: test contour).
 *   --inn --key2 --group   Override credentials (defaults: test contour values).
 *   --insecure   Disable server certificate verification (may be needed on the test contour).
 */

require __DIR__ . '/../system/library/orangedata/OrangeDataClient.php';

$args = array();
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/', $arg, $m)) {
        $args[$m[1]] = isset($m[2]) ? $m[2] : true;
    }
}

function fail($msg) { fwrite(STDERR, "ERROR: $msg\n"); exit(1); }

$certPath = isset($args['cert']) ? $args['cert'] : null;
$keyPath  = isset($args['key'])  ? $args['key']  : null;
if (!$certPath || !is_file($certPath)) fail('--cert path is required and must exist.');
if (!$keyPath  || !is_file($keyPath))  fail('--key path is required and must exist.');

// Resolve the signing key (XML test key -> PEM, or a PEM directly).
if (!empty($args['signxml'])) {
    if (!is_file($args['signxml'])) fail('--signxml file not found.');
    $signPem = OrangeDataClient::xmlRsaPrivateKeyToPem(file_get_contents($args['signxml']));
} elseif (!empty($args['signpem'])) {
    if (!is_file($args['signpem'])) fail('--signpem file not found.');
    $signPem = file_get_contents($args['signpem']);
} else {
    fail('Provide either --signxml (test) or --signpem (production).');
}

// Defaults match the OrangeData TEST contour credentials supplied for this integration.
$config = array(
    'base_url'      => isset($args['base'])  ? $args['base']  : 'https://apip.orangedata.ru:12001/api/v2/',
    'inn'           => isset($args['inn'])   ? $args['inn']   : '7727401209',
    'key'           => isset($args['key2'])  ? $args['key2']  : '770705053301',
    'group'         => isset($args['group']) ? $args['group'] : 'main_2',
    'ssl_cert_path' => $certPath,
    'ssl_key_path'  => $keyPath,
    'ssl_key_pass'  => '',
    'sign_key_pem'  => $signPem,
    'client_info'   => array(
        'name'    => 'UniqSport OpenCart',
        'version' => '1.0.0',
        'id'      => 'uniqsport-oc3',
        'token'   => 'smoke-test',
    ),
    'verify_peer'   => empty($args['insecure']),
    'timeout'       => 30,
);

$logger = function ($level, $message, $context) {
    fwrite(STDERR, sprintf("[%s] %s %s\n", strtoupper($level), $message, json_encode($context, JSON_UNESCAPED_SLASHES)));
};

$client = new OrangeDataClient($config, $logger);

// Build the code to check. Use a sample from the docs unless one is provided.
$rawOrB64 = isset($args['cis']) ? $args['cis'] : 'MDEwNDYyOTMwODg3NzA0NDIxRHprY1l0Mh04MDA1MDkwMDAwHTkzZEdWeg==';
$cis = !empty($args['raw']) ? OrangeDataClient::encodeCisForCheck($rawOrB64) : $rawOrB64;

$code = array('cis' => $cis);
if (isset($args['pg']))    $code['pg']    = (int) $args['pg'];
if (isset($args['price'])) $code['price'] = (float) $args['price'];

echo "Calling {$config['base_url']}piot/codes/check ...\n";
$result = $client->checkPiotCodes(array($code));

echo "HTTP: {$result['http_code']}\n";
if ($result['error']) {
    echo "curl error: {$result['error']}\n";
}
echo "Response:\n" . json_encode($result['body'] ?: $result['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

// Surface the разрешительный режим verdict clearly.
if (!empty($result['body']['result']['codesResponse'][0]['codes'][0]['checkResult'])) {
    $cr = $result['body']['result']['codesResponse'][0]['codes'][0]['checkResult'];
    echo "\nisSaleAllowed: " . (!empty($cr['isSaleAllowed']) ? 'YES' : 'NO');
    if (isset($cr['reason']['description'])) echo " — {$cr['reason']['description']}";
    echo "\n";
    if (isset($result['body']['result']['codesResponse'][0]['tag1265'])) {
        echo "tag1265: {$result['body']['result']['codesResponse'][0]['tag1265']}\n";
    }
}
