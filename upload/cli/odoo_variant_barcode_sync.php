#!/usr/bin/env php
<?php
/**
 * Backfill per-variant EAN barcodes from Odoo into the ocus_odoo_variant_barcode cache (CLI).
 *
 * The EAN-first scan routing on the ТС ПИоТ order panel needs each size's own barcode, but ocus_product.ean
 * holds only one value per product (and the live Odoo→OpenCart sync overwrites it per variant). This script
 * resolves the barcode per product_option_value: it reads ocus_odoo_product_variant_map (OpenCart option value
 * ↔ Odoo product.product id), asks Odoo over JSON-RPC for each variant's `barcode`, and upserts it keyed by
 * product_option_value_id. The order panel then matches a scanned EAN to the exact order line.
 *
 * Scope: run on demand / nightly cron. Single-variant goods have no option row and keep using ocus_product.ean,
 * which the existing api/product.php sync already maintains — this script does not touch that.
 *
 * Usage:
 *   php cli/odoo_variant_barcode_sync.php [--dry-run] [--limit=N] [--chunk=200] [--verbose]
 *       [--proxy=socks5h://127.0.0.1:1080]   # egress via the RU VPS when Odoo is not reachable directly
 *
 * Exit codes: 0 ok, 1 bootstrap/usage error, 2 Odoo connection/auth error.
 */

$args = array();
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([^=]+)(?:=(.*))?$/s', $arg, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : true; }
}
$dryRun  = !empty($args['dry-run']);
$verbose = !empty($args['verbose']);
$limit   = isset($args['limit']) ? (int) $args['limit'] : 0;
$chunk   = isset($args['chunk']) ? max(1, (int) $args['chunk']) : 200;
$proxy   = isset($args['proxy']) && is_string($args['proxy']) ? $args['proxy'] : '';

/* ---- bootstrap OpenCart (admin config: DB + DIR_SYSTEM) ---- */
$root = dirname(__DIR__) . '/';
if (!is_file($root . 'config.php')) { fwrite(STDERR, "ERROR: config.php not found\n"); exit(1); }
require_once($root . 'config.php');
if (!defined('VERSION')) { define('VERSION', '3.0.3.6'); }
require_once(DIR_SYSTEM . 'startup.php');
require_once(DIR_SYSTEM . 'library/orangedata/OrangeDataRepository.php');

$db   = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$repo = new OrangeDataRepository($db);
$repo->install(); // ensure the cache table exists (idempotent)

/* ---- Odoo connection parameters ---- */
// The modern integration keeps them in the key/value table ocus_odoo_config (url, db_name, user, password, port)
// — same row the api/product price/stock sync uses — not the legacy erp table ocus_odoo_confg.
$cfg = array();
foreach ($db->query("SELECT `key`, `value` FROM `" . DB_PREFIX . "odoo_config` WHERE `key` IN ('url','port','db_name','user','password')")->rows as $r) {
    $cfg[$r['key']] = $r['value'];
}
if (empty($cfg['url'])) { fwrite(STDERR, "ERROR: no Odoo connection configured in " . DB_PREFIX . "odoo_config\n"); exit(2); }

$base = rtrim($cfg['url'], '/');
$port = isset($cfg['port']) ? (int) $cfg['port'] : 0;
// Append an explicit port only when it is non-default and not already present in the URL.
if ($port && !in_array($port, array(80, 443), true) && !preg_match('~:\d+$~', $base)) {
    $base .= ':' . $port;
}
$endpoint = $base . '/jsonrpc';
$odooDb   = $cfg['db_name'];
$odooUser = $cfg['user'];
$odooPass = $cfg['password'];

/**
 * odooRpc — one Odoo JSON-RPC call. Scope: this script only.
 *
 * @param string $endpoint full /jsonrpc URL
 * @param string $service  'common' | 'object'
 * @param string $method   e.g. 'authenticate' | 'execute_kw'
 * @param array  $rpcArgs  positional args for that method
 * @param string $proxy    optional CURLOPT_PROXY (e.g. socks5h://127.0.0.1:1080)
 * @return mixed decoded `result`
 * @throws RuntimeException on transport or Odoo fault
 */
function odooRpc($endpoint, $service, $method, array $rpcArgs, $proxy = '') {
    $payload = json_encode(array(
        'jsonrpc' => '2.0', 'method' => 'call',
        'params'  => array('service' => $service, 'method' => $method, 'args' => $rpcArgs),
        'id'      => mt_rand(),
    ));
    $ch = curl_init($endpoint);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 120,
    );
    if ($proxy !== '') { $opts[CURLOPT_PROXY] = $proxy; }
    curl_setopt_array($ch, $opts);
    $raw  = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_errno($ch) ? curl_error($ch) : '';
    curl_close($ch);
    if ($err) { throw new RuntimeException("transport error: $err"); }
    if ($http !== 200) { throw new RuntimeException("HTTP $http: " . substr((string) $raw, 0, 300)); }
    $json = json_decode($raw, true);
    if (isset($json['error'])) {
        $msg = isset($json['error']['data']['message']) ? $json['error']['data']['message'] : json_encode($json['error']);
        throw new RuntimeException("Odoo fault: $msg");
    }
    return isset($json['result']) ? $json['result'] : null;
}

/* ---- authenticate ---- */
echo "Odoo: $endpoint  db=$odooDb  user=$odooUser\n";
try {
    $uid = odooRpc($endpoint, 'common', 'authenticate', array($odooDb, $odooUser, $odooPass, new stdClass()), $proxy);
} catch (Exception $e) {
    fwrite(STDERR, "ERROR: authenticate failed: " . $e->getMessage() . "\n"); exit(2);
}
if (!$uid) { fwrite(STDERR, "ERROR: authentication returned no uid (check db/user/password)\n"); exit(2); }
echo "Authenticated as uid=$uid\n";

/* ---- map rows: product_option_value_id ↔ odoo_product_id ---- */
$rows = $repo->getVariantMapRows();
if ($limit > 0) { $rows = array_slice($rows, 0, $limit); }
$total = count($rows);
echo "Variant map rows with an option value: $total" . ($limit ? " (limited)" : "") . "\n";
if (!$total) { echo "Nothing to do.\n"; exit(0); }

// odoo_product_id => list of {pov_id, product_id}; one Odoo variant can back several OpenCart option values.
$byOdoo = array();
foreach ($rows as $r) {
    $byOdoo[(int) $r['odoo_product_id']][] = array((int) $r['product_option_value_id'], (int) $r['opencart_product_id']);
}
$odooIds = array_keys($byOdoo);

/* ---- fetch barcodes in chunks and upsert ---- */
$fetched = $withBarcode = $updated = $noBarcode = 0;
foreach (array_chunk($odooIds, $chunk) as $batch) {
    try {
        $records = odooRpc($endpoint, 'object', 'execute_kw',
            array($odooDb, $uid, $odooPass, 'product.product', 'read', array($batch), array('fields' => array('id', 'barcode'))), $proxy);
    } catch (Exception $e) {
        fwrite(STDERR, "ERROR: read product.product failed: " . $e->getMessage() . "\n"); exit(2);
    }
    foreach ((array) $records as $rec) {
        $fetched++;
        $odooId  = (int) $rec['id'];
        $barcode = isset($rec['barcode']) && $rec['barcode'] !== false ? trim((string) $rec['barcode']) : '';
        if ($barcode === '') { $noBarcode++; continue; }
        $withBarcode++;
        foreach ($byOdoo[$odooId] as $pair) {
            list($povId, $productId) = $pair;
            if ($verbose) { echo "  pov {$povId} (product {$productId}) ← odoo {$odooId} = {$barcode}\n"; }
            if (!$dryRun) { $repo->saveVariantBarcode($povId, $productId, $odooId, $barcode); }
            $updated++;
        }
    }
}

echo "\nDone" . ($dryRun ? " (dry-run, nothing written)" : "") . ".\n";
echo "  Odoo variants requested : " . count($odooIds) . "\n";
echo "  Odoo records returned   : $fetched\n";
echo "  with barcode            : $withBarcode\n";
echo "  without barcode         : $noBarcode\n";
echo "  option values " . ($dryRun ? "to update" : "updated") . "  : $updated\n";
exit(0);
