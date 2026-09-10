#!/usr/bin/env php
<?php

/**
 * Rebuilds the filesystem catalog snapshot used by YCP health checks.
 * Recommended cron: once daily, after catalog/stock imports have completed.
 */

$catalog_dir = dirname(__DIR__) . '/';

if (!is_file($catalog_dir . 'config.php')) {
	fwrite(STDERR, "ERROR: Cannot access catalog config.php\n");
	exit(1);
}

require_once($catalog_dir . 'config.php');

if (!defined('VERSION')) {
	define('VERSION', '3.0.3.6');
}

require_once(DIR_SYSTEM . 'startup.php');
require_once(DIR_SYSTEM . 'library/ycp_basket_cache.php');

$registry = new Registry();
$config = new Config();
$registry->set('config', $config);

$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

$settings = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");
foreach ($settings->rows as $setting) {
	$config->set(
		$setting['key'],
		$setting['serialized'] ? json_decode($setting['value'], true) : $setting['value']
	);
}

$config->set('config_store_id', 0);

if (!$config->get('config_language_id')) {
	$language = $db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE code = '" . $db->escape((string)$config->get('config_language')) . "' LIMIT 1");
	$config->set('config_language_id', $language->num_rows ? (int)$language->row['language_id'] : 1);
}

$event = new Event($registry);
$registry->set('event', $event);

$loader = new Loader($registry);
$registry->set('load', $loader);

$request = new Request();
$request->server['HTTPS'] = false;
$registry->set('request', $request);
$registry->set('url', new Url(HTTP_SERVER, HTTPS_SERVER));

$refresh_lock = YcpBasketCache::acquireRefreshLock();
$loader->model('api/ycp');
$offers = $registry->get('model_api_ycp')->buildHealthSnapshot();

if ($offers === false || !YcpBasketCache::writeOffers($offers)) {
	YcpBasketCache::releaseRefreshLock($refresh_lock);
	fwrite(STDERR, "ERROR: Could not build the YCP health-check snapshot\n");
	exit(1);
}

YcpBasketCache::releaseRefreshLock($refresh_lock);
echo 'YCP health-check snapshot updated: ' . count($offers) . " offers\n";
