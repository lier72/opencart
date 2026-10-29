<?php
/**
 * Reachability matrix for every разрешительный-режим / ТС ПИоТ endpoint the test plan uses (CLI).
 *
 * Russian services may geo-block foreign IPs (the ЧЗ web portal does: DDoS-Guard «CHZgeoblock»). Run this once
 * directly and once through the RU VPS to see which path each service needs:
 *
 *   php cli/marking_reachability.php
 *   ssh -N -D 1080 <vps-alias> &          # SOCKS proxy egressing from the VPS (Moscow)
 *   php cli/marking_reachability.php --proxy=socks5h://127.0.0.1:1080
 *
 * Expected codes are the ones that prove the service answered (401 = up, wants auth). Result per row:
 * OK, GEOBLOCK (body says so), UNEXPECTED (answered, other code), DOWN (no TCP/TLS answer).
 *
 * Exit codes: 0 all OK, 4 at least one row not OK.
 */

$args = array();
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/s', $arg, $m)) {
        $args[$m[1]] = isset($m[2]) ? $m[2] : true;
    }
}
$proxy = isset($args['proxy']) ? $args['proxy'] : '';

// [solution, label, method, url, expected HTTP codes, POST body]
$targets = array(
    array('0 OD',  'OrangeData test contour',             'GET',  'https://apip.orangedata.ru:12001/swagger', array(200, 301, 302)),
    array('0 OD',  'OrangeData prod',                     'GET',  'https://api.orangedata.ru:12003/swagger', array(200, 301, 302)),
    array('1 TA',  'True API sandbox auth/key',           'GET',  'https://markirovka.sandbox.crptech.ru/api/v3/true-api/auth/key', array(200)),
    array('1 TA',  'True API sandbox v4 cdn/info',        'GET',  'https://markirovka.sandbox.crptech.ru/api/v4/true-api/cdn/info', array(401, 200)),
    array('1 TA',  'True API sandbox v4 codes/check',     'POST', 'https://markirovka.sandbox.crptech.ru/api/v4/true-api/codes/check', array(401), '{"codes":["x"]}'),
    array('1 TA',  'True API prod auth/key',              'GET',  'https://markirovka.crpt.ru/api/v3/true-api/auth/key', array(200)),
    array('1 TA',  'True API prod v4 cdn/info',           'GET',  'https://cdn.crpt.ru/api/v4/true-api/cdn/info', array(401, 200)),
    array('1 TA',  'ЧЗ portal (ЛК, ЛМ ЧЗ downloads)',     'GET',  'https://xn--80ajghhoc2aj1c8b.xn--p1ai/', array(200)),
    array('3 SB',  'АО ЕСП ТС ПИоТ emulator /api/v3/info', 'GET', 'https://esm-emu.ao-esp.ru/api/v3/info', array(200)),
    array('3 SB',  'OFD.ru Ferma test auth',              'POST', 'https://ferma-test.ofd.ru/api/Authorization/CreateAuthToken', array(200, 401), '{"Login":"probe","Password":"probe"}'),
);

$ip = @file_get_contents('https://ipinfo.io/country', false, stream_context_create(array('http' => array('timeout' => 5))));
echo 'Egress: ' . ($proxy ? "proxy $proxy" : 'direct' . ($ip ? ' (' . trim($ip) . ')' : '')) . "\n\n";

$bad = 0;
foreach ($targets as $t) {
    $ch = curl_init($t[3]);
    $opts = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (uniqsport marking probe)');
    if ($t[2] === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $t[5];
        $opts[CURLOPT_HTTPHEADER] = array('Content-Type: application/json');
    }
    if ($proxy !== '' && empty($t[6])) $opts[CURLOPT_PROXY] = $proxy;
    curl_setopt_array($ch, $opts);
    $t0   = microtime(true);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_errno($ch) ? curl_error($ch) : '';
    $ms   = (int) round((microtime(true) - $t0) * 1000);
    curl_close($ch);

    if ($err !== '' || $http === 0)                                   $res = 'DOWN';
    elseif (is_string($body) && stripos($body, 'geoblock') !== false) $res = 'GEOBLOCK';
    elseif (in_array($http, $t[4], true))                             $res = 'OK';
    else                                                              $res = 'UNEXPECTED';
    if ($res !== 'OK') $bad++;

    $label = $t[1] . str_repeat(' ', max(0, 40 - mb_strlen($t[1], 'UTF-8'))); // printf pads bytes, not Cyrillic chars
    printf("%-4s  %-10s %s HTTP %-3s %5dms  %s\n", $t[0], $res, $label, $http ?: '-', $ms, $err !== '' ? $err : '');
}
exit($bad ? 4 : 0);
