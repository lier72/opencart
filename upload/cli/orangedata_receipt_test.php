<?php
/**
 * OrangeData full-flow test (CLI): check -> fiscalize -> poll status -> cheque URL.
 *
 * Purpose: validate the WHOLE разрешительный режим data path against the TEST contour, i.e. that a
 * fiscal receipt carrying itemCode (tag 1163) + industryAttribute (tag 1260 with tag 1265) is accepted
 * and processed. Uses --ignorecode so the ФН marking-validity check (tag 2106) does not reject a
 * synthetic test code — we are validating the разрешительный режим transmission, not code authenticity.
 *
 * Scope: developer/QA only; not used at runtime. The reusable receipt builder is extracted from this
 * once the flow is confirmed.
 *
 * Usage: same cert/key/signxml options as orangedata_smoke_test.php, plus:
 *   --itemcode=<raw KM>   Raw marking code for the position (default: documented FFD 1.2 example).
 *   --price=<float>       Position price (default 1990.00).
 *   --ignorecode          Set ignoreItemCodeCheck=true (recommended on the test contour).
 *   --noind               Do NOT attach industryAttribute (to compare behaviour).
 */

require __DIR__ . '/../system/library/orangedata/OrangeDataClient.php';

$args = array();
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/', $arg, $m)) {
        $args[$m[1]] = isset($m[2]) ? $m[2] : true;
    }
}
function fail($m) { fwrite(STDERR, "ERROR: $m\n"); exit(1); }

foreach (array('cert', 'key') as $req) {
    if (empty($args[$req]) || !is_file($args[$req])) fail("--$req path required and must exist.");
}
if (!empty($args['signxml'])) {
    $signPem = OrangeDataClient::xmlRsaPrivateKeyToPem(file_get_contents($args['signxml']));
} elseif (!empty($args['signpem'])) {
    $signPem = file_get_contents($args['signpem']);
} else {
    fail('Provide --signxml (test) or --signpem (production).');
}

$config = array(
    'base_url'      => isset($args['base']) ? $args['base'] : 'https://apip.orangedata.ru:12001/api/v2/',
    'inn'           => '7727401209',
    'key'           => '770705053301',
    'group'         => 'main_2',
    'ssl_cert_path' => $args['cert'],
    'ssl_key_path'  => $args['key'],
    'sign_key_pem'  => $signPem,
    'client_info'   => array('name' => 'UniqSport OpenCart', 'version' => '1.0.0', 'id' => 'uniqsport-oc3', 'token' => 'flow-test'),
    'verify_peer'   => empty($args['insecure']),
    'timeout'       => 30,
);
$logger = function ($lvl, $msg, $ctx) { fwrite(STDERR, sprintf("[%s] %s %s\n", strtoupper($lvl), $msg, json_encode($ctx, JSON_UNESCAPED_SLASHES))); };
$client = new OrangeDataClient($config, $logger);

// A documented FFD 1.2 itemCode (raw, with GS \x1d separators) — has the 91/92 crypto tail.
$defaultItemCode = "010460406000600021N4N57RSCBUZTQ\x1d2403004002910161218\x1d1724010191ffd0\x1d92tIAF/YVoU4roQS3M/m4z78yFq0fc/WsSmLeX5QkF/YVWwy8IMYAeiQ91Xa2z/fFSJcOkb2N+uUUmfr4n0mOX0Q==";
$itemCode = isset($args['itemcode']) ? $args['itemcode'] : $defaultItemCode;
$price    = isset($args['price']) ? (float) $args['price'] : 1990.00;

/* ---- Step 1: разрешительный режим check to obtain a real tag1265 ---- */
echo "== Step 1: ТС ПИоТ check ==\n";
$check = $client->checkPiotCodes(array(array('cis' => OrangeDataClient::encodeCisForCheck($itemCode), 'price' => $price)));
echo "check HTTP: {$check['http_code']}\n";
$cr = isset($check['body']['result']['codesResponse'][0]) ? $check['body']['result']['codesResponse'][0] : null;
$tag1265 = $cr && isset($cr['tag1265']) ? $cr['tag1265'] : null;
$allowed = $cr && isset($cr['codes'][0]['checkResult']['isSaleAllowed']) ? $cr['codes'][0]['checkResult']['isSaleAllowed'] : null;
echo "isSaleAllowed: " . var_export($allowed, true) . "\n";
echo "tag1265: " . var_export($tag1265, true) . "\n";
if (!$tag1265) {
    echo "No tag1265 (statusCode " . (isset($check['body']['statusCode']) ? $check['body']['statusCode'] : '?') . "). Continuing to test fiscalization transmission anyway.\n";
}

/* ---- Step 2: build FFD 1.2 receipt with itemCode + industryAttribute (tag 1260/1265) ---- */
echo "\n== Step 2: createDocument ==\n";
$docId = 'ocflow-' . date('YmdHis') . '-' . substr(md5(mt_rand()), 0, 6);
$position = array(
    'quantity'                => 1.000,
    'price'                   => $price,
    'tax'                     => 6,        // НДС не облагается (УСН)
    'text'                    => 'Кроссовки (тест)',
    'paymentMethodType'       => 4,        // Полный расчет
    'paymentSubjectType'      => 33,       // ТМ — маркированный товар (обязательно при наличии itemCode)
    'itemCode'                => $itemCode, // tag 1163 (raw KM with GS)
    'plannedStatus'           => 1,        // Штучный товар реализован
    'quantityMeasurementUnit' => 0,        // штука
);
if ($tag1265 && empty($args['noind'])) {
    $position['industryAttribute'] = OrangeDataClient::buildIndustryAttribute($tag1265); // tag 1260
}
$document = array(
    'id'      => $docId,
    'content' => array(
        'type'       => 1,                 // Приход
        'positions'  => array($position),
        'checkClose' => array(
            'payments'       => array(array('type' => isset($args['paytype']) ? (int)$args['paytype'] : 2, 'amount' => $price)),
            'taxationSystem' => 1,         // УСН доход
        ),
        'customerContact' => 'none',
        'ffdVersion'      => 4,            // ФФД 1.2
    ),
    'ignoreItemCodeCheck' => !empty($args['ignorecode']),
);
echo "document id: $docId\n";
$create = $client->createDocument($document);
echo "createDocument HTTP: {$create['http_code']} (201=queued, 409=dup, 400=validation)\n";
if ($create['http_code'] !== 201) {
    echo "Response: " . json_encode($create['body'] ?: $create['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    if ($create['http_code'] !== 409) exit(1);
}

/* ---- Step 3: poll status ---- */
echo "\n== Step 3: poll status ==\n";
$status = null;
for ($i = 0; $i < 15; $i++) {
    sleep(2);
    $status = $client->getDocumentStatus($docId);
    echo "  poll " . ($i + 1) . ": HTTP {$status['http_code']}\n";
    if ($status['http_code'] !== 202) break;
}
echo "Final status body:\n" . json_encode($status['body'] ?: $status['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

/* ---- Step 4: cheque viewer URL ---- */
echo "\n== Step 4: view receipt ==\n";
echo 'https://cheques-lk.orangedata.ru/' . $config['inn'] . '/' . rawurlencode($docId) . "\n";
