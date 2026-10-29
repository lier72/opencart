<?php
/**
 * ТС ПИоТ ПМСР certification scenarios (CLI) — test layer for solution 3 (ТС ПИоТ sandboxes).
 *
 * Runs the АО ЕСП «Checkmarks Emulator» (https://esm-emu.ao-esp.ru, open, no auth, reachable from outside RU)
 * scenarios for лёгкая промышленность (#LI, = обувь/одежда, ПМСР test programme §5.1–5.15) plus the generic
 * failure modes, pushes every answer through PiotLocalResponse → MarkingCheckResult::normalize →
 * MarkingVerdict, and asserts the expected sale decision. The same verdict code serves the True API
 * backend, so this is the realistic-payload regression test for all self-derived verdicts.
 *
 * The emulator implements the ЕСМ local API a physical POS calls at https://localhost:51401. This store uses
 * the OrangeData cloud kassa, so the emulator is a test oracle here, not a production backend.
 *
 * Usage:
 *   php cli/orangedata_esm_scenarios.php [--url=https://esm-emu.ao-esp.ru] [--api=v3] [--only=201,214]
 *       [--proxy=socks5h://127.0.0.1:1080] [--save-fixtures=tests/unit/library/orangedata/fixtures/esm]
 *
 * Exit codes: 0 all scenarios met their expectation, 1 usage/transport error, 3 at least one expectation failed.
 * LOCAL rows (5.8 duplicate in receipt, 5.9 repeat sale) are enforced by the ПМСР itself, not by the check
 * answer; they are reported but never fail the run.
 */

require __DIR__ . '/../system/library/orangedata/PiotLocalResponse.php';

$args = array();
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/s', $arg, $m)) {
        $args[$m[1]] = isset($m[2]) ? $m[2] : true;
    }
}
$url   = rtrim(isset($args['url']) ? $args['url'] : 'https://esm-emu.ao-esp.ru', '/');
$api   = isset($args['api']) ? $args['api'] : 'v3';
$proxy = isset($args['proxy']) ? $args['proxy'] : '';
$only  = isset($args['only']) ? array_map('trim', explode(',', $args['only'])) : array();

/*
 * expect: allow = sale allowed with tag1265; deny = denied with reason; emergency = 203 (allowed, no tag1265);
 *         unavailable = backend failure → failover (-1 / 514). offline = expected isCheckedOffline.
 * Codes 'e203'/'e514'/'e500' are the emulator's generic error marks, keyed by OBSERVED behaviour (the error
 * table on the emulator page labels them inconsistently).
 */
$scenarios = array(
    array('id' => '201', 'title' => '5.1 Нет сведений о нанесении (utilised=false)', 'expect' => 'deny', 'reason' => 2, 'offline' => 0,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNW9NaCxyemJXSXRYRh05MUZGRDAdOTJkR1Z6ZElxcUdkVmRzRXJpQXNXM1pXZXQ4QTVrZWVxcEcyNkkwdDhSRE5rPQ=='),
    array('id' => '202', 'title' => '5.2 Не введён в оборот (realizable=false)', 'expect' => 'deny', 'reason' => 5, 'offline' => 0,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNVh4LFRlPk82OTNGdx05MUZGRDAdOTJkR1Z6ZER6ZjROTlZtYlF3NWQxYW5UN1FKNGVNQjE1M2xkN0RHOFl5WnBFPQ=='),
    array('id' => '203', 'title' => '5.3 Выведен из оборота (sold=true)', 'expect' => 'deny', 'reason' => 4, 'offline' => 0,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNXZYWGg8RWsoO1FzRh05MUZGRDAdOTJkR1Z6ZERleXdHTEF6VkhJaWQza3V5TXloUjBHMlMvKzdFVGVQdG15a1pvPQ=='),
    array('id' => '204', 'title' => '5.4 Заблокирован ОГВ (isBlocked=true)', 'expect' => 'deny', 'reason' => 6, 'offline' => 0,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNVEpSi9CJ2NQMylILh05MUZGRDAdOTJkR1Z6ZEo0aE1ZYlgzaHF5NXcva3FyVnBxU1ZraEoxeENvRklPaTZrUUtZPQ=='),
    array('id' => '205', 'title' => '5.5 Нет сведений о маркировке (found=false)', 'expect' => 'deny', 'reason' => 1, 'offline' => 0,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNW8zM0tGbVhzPiVPRh05MUZGRDAdOTJkR1Z6ZEwwWkoxa0VreEN0SVRGYU16VjFPWW1jTlhaQm5tTFNlOG9kM2FJPQ=='),
    array('id' => '206', 'title' => '5.6 DataMatrix без EAN — штатная продажа', 'expect' => 'allow', 'offline' => 0,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNTwoTyFtRUMqakliMx05MUZGRDAdOTJkR1Z6ZER5dnRxdWlXUEV3cStiaGVmNjd5Qm11K3BpQTI4em9PZHBXYkNZPQ=='),
    array('id' => '207', 'title' => '5.7 Некорректный код проверки (verified=false)', 'expect' => 'deny', 'reason' => 3, 'offline' => 0,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNWd1S0pLZEk9ISlGJh05MUZGRDAdOTJkR1Z6ZEExWTNLWUZZNGpPNzFKK0toYVEwVEh2UmVzVlVHQ3pVSzd0YVYwPQ=='),
    array('id' => '208', 'title' => '5.8 Дубль КМ в чеке', 'expect' => 'allow', 'offline' => 0,
          'local' => 'ПМСР must reject the 2nd scan of the same КМ in one order',
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNTwoTyFtRUMqakliMx05MUZGRDAdOTJkR1Z6ZER5dnRxdWlXUEV3cStiaGVmNjd5Qm11K3BpQTI4em9PZHBXYkNZPQ=='),
    array('id' => '209', 'title' => '5.9 Повторная продажа', 'expect' => 'allow', 'offline' => 0,
          'local' => 'ПМСР must reject a КМ already fiscalized in another order',
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNTwoTyFtRUMqakliMx05MUZGRDAdOTJkR1Z6ZER5dnRxdWlXUEV3cStiaGVmNjd5Qm11K3BpQTI4em9PZHBXYkNZPQ=='),
    array('id' => '210', 'title' => '5.10 Офлайн, нет в чёрном списке', 'expect' => 'allow', 'offline' => 1,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNTwoTyFtRUMqajRMTR05MUZGRDAdOTJkR1Z6ZER5dnRxdWlXUEV3cStiaGVmNjd5Qm11K3BpQTI4em9PZHBXYkNZPQ=='),
    array('id' => '211', 'title' => '5.11 Офлайн, нет в чёрном списке (ГИС МТ 5 с)', 'expect' => 'allow', 'offline' => 1,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNWcldVBJd3ErNVJYUR05MUZGRDAdOTJkR1Z6ZEtRa3gydEh5N3pEV1FSUHpOVGR5WWU3ZlJ3WElMTS80TzZaenBnPQ=='),
    array('id' => '212', 'title' => '5.12 Офлайн, в чёрном списке', 'expect' => 'deny', 'reason' => 6, 'offline' => 1,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNWcldURPaW8rNVJYUR05MUZGRDAdOTJkR1Z6ZEtRa3gydEh5N3pEV1FSUHpOVGR5WWU3ZlJ3WElMTS80TzZaenBnPQ=='),
    array('id' => '213', 'title' => '5.13 Офлайн, в чёрном списке (ГИС МТ 5 с)', 'expect' => 'deny', 'reason' => 6, 'offline' => 1,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNWcldURPaW8rNVJMTR05MUZGRDAdOTJkR1Z6ZEtRa3gydEh5N3pEV1FSUHpOVGR5WWU3ZlJ3WElMTS80TzZaenBnPQ=='),
    array('id' => '214', 'title' => '5.14 Ошибка 203 → аварийный режим', 'expect' => 'emergency', 'offline' => 0,
          'b64' => 'MDEwNDYwNzgwOTU5NjIzNzIxNWNocFZLdk1PRkl0TB05MUZGRDAdOTJkR1Z6ZEZ4dS9MOTYwTVc2SU5rbzhMWjBMbk1mQldwTmo0SHh1VlNSaDRZPQ=='),
    array('id' => '215', 'title' => '5.15 Продажа в поставарийном режиме (code 204)', 'expect' => 'allow', 'offline' => 0,
          'b64' => 'MDEwNDgxNTcxOTA2OTU0NzIxNWYsYU5vU0FrRTVrah05MUZGRDAdOTJkR1Z6ZENOZjB3eDZwTFJvNTVoOHdRMjVuYVZqY2d6djZudTZ1YWVBVzl3PQ=='),
    array('id' => 'e203', 'title' => 'Общая: аварийный режим (HTTP 203)', 'expect' => 'emergency', 'offline' => 0,
          'b64' => 'MDEwNDY3MDU0MDE3NjA5OTIxNUxwR0t5HTkzZEdWeg=='),
    array('id' => 'e514', 'title' => 'Общая: нет ответа ГИС МТ/ЛМ ЧЗ за 1800 мс (514)', 'expect' => 'unavailable', 'offline' => 0,
          'b64' => 'MDEwNDgxMzQ0NTAwMzI5MzIxNVRtaVYsZ1x1MDAxZDkzZEdWeg=='),
    array('id' => 'e500', 'title' => 'Общая: HTTP 500 от ТС ПИоТ', 'expect' => 'unavailable', 'offline' => 0,
          'b64' => 'MDEwNDY3MDU0MDE3NjA5OTIxNVBwR0t5HTkzZEdWeg=='),
);

/** POST one code to the emulator; returns the same array shape as the module's HTTP clients. */
function esm_check($url, $api, $b64, $proxy) {
    $client = array('name' => 'uniqsport-opencart-test', 'version' => '1.0', 'id' => 'test', 'token' => 'test');
    $codes  = $api === 'v3' ? array(array('cis' => $b64, 'pg' => 1)) : array($b64);
    $ch = curl_init($url . '/api/' . $api . '/codes/check');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(array('codes' => $codes, 'client_info' => $client)),
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'Accept: application/json'),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
    ));
    if ($proxy !== '') curl_setopt($ch, CURLOPT_PROXY, $proxy);
    $raw  = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);
    $body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    return array('http_code' => $http, 'body' => is_array($body) ? $body : null, 'raw' => is_string($raw) ? $raw : '', 'error' => $err);
}

/** Compares a normalized result with a scenario expectation; returns '' when met, else the mismatch. */
function esm_mismatch(array $s, array $n) {
    switch ($s['expect']) {
        case 'allow':
            if ($n['status_code'] !== 200 || $n['is_sale_allowed'] !== 1) return 'expected allowed, got status ' . $n['status_code'] . ' allowed=' . var_export($n['is_sale_allowed'], true);
            if ($n['tag1265'] === '') return 'allowed but no tag1265';
            break;
        case 'deny':
            if ($n['status_code'] !== 200 || $n['is_sale_allowed'] !== 0) return 'expected denied, got status ' . $n['status_code'] . ' allowed=' . var_export($n['is_sale_allowed'], true);
            if ($n['reason_code'] !== $s['reason']) return 'expected reason ' . $s['reason'] . ', got ' . var_export($n['reason_code'], true);
            break;
        case 'emergency':
            if ($n['status_code'] !== 203 || $n['is_sale_allowed'] !== 1 || $n['tag1265'] !== '') return 'expected 203 allowed without tag1265, got status ' . $n['status_code'];
            break;
        case 'unavailable':
            if (!in_array($n['status_code'], array(-1, 514), true)) return 'expected -1/514 (failover), got ' . $n['status_code'];
            break;
    }
    if ($s['expect'] === 'allow' || $s['expect'] === 'deny') {
        if ($n['is_checked_offline'] !== $s['offline']) return 'expected is_checked_offline=' . $s['offline'] . ', got ' . $n['is_checked_offline'];
    }
    return '';
}

$fixtures = array();
$failed   = 0;
echo "ESM emulator: $url  api=$api" . ($proxy ? "  proxy=$proxy" : '') . "\n\n";
foreach ($scenarios as $s) {
    if ($only && !in_array($s['id'], $only, true)) continue;
    $t0   = microtime(true);
    $resp = esm_check($url, $api, $s['b64'], $proxy);
    $ms   = (int) round((microtime(true) - $t0) * 1000);
    if ($resp['error']) {
        fwrite(STDERR, "[{$s['id']}] transport error: {$resp['error']}\n");
        exit(1);
    }
    $n    = PiotLocalResponse::normalize($resp);
    $miss = esm_mismatch($s, $n);
    $mark = $miss === '' ? (isset($s['local']) ? 'LOCAL' : 'PASS') : 'FAIL';
    if ($miss !== '') $failed++;

    printf("%-5s %-4s HTTP %-3d %5dms  %s\n", $mark, $s['id'], $resp['http_code'], $ms, $s['title']);
    printf("            → status=%s allowed=%s reason=%s offline=%s tag1265=%s%s\n",
        var_export($n['status_code'], true), var_export($n['is_sale_allowed'], true), var_export($n['reason_code'], true),
        $n['is_checked_offline'], $n['tag1265'] !== '' ? 'yes' : 'no', $n['reason_text'] !== '' ? ' «' . $n['reason_text'] . '»' : '');
    if ($miss !== '')         echo "            ✗ $miss\n";
    if (isset($s['local']))   echo "            ! {$s['local']}\n";

    $fixtures[] = array('scenario' => $s, 'http_code' => $resp['http_code'], 'raw' => $resp['raw']);
}

if (!empty($args['save-fixtures'])) {
    $dir = rtrim($args['save-fixtures'], '/');
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        fwrite(STDERR, "Cannot create $dir\n");
        exit(1);
    }
    $file = $dir . '/esm_' . $api . '_scenarios.json';
    file_put_contents($file, json_encode(array('source' => $url . '/api/' . $api . '/codes/check', 'recorded' => date('c'), 'cases' => $fixtures),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    echo "\nFixtures saved: $file\n";
}

echo "\n" . ($failed ? "$failed scenario(s) FAILED" : 'All scenarios met expectations') . "\n";
exit($failed ? 3 : 0);
