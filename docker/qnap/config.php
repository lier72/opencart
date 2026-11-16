<?php
// Shared by the generated catalog and admin configuration files.
$base = rtrim(getenv('OPENCART_URL') ?: '', '/') . '/';
if (!filter_var($base, FILTER_VALIDATE_URL) || !preg_match('~^https?://~', $base)) {
    throw new RuntimeException('Set OPENCART_URL to the complete store URL.');
}
$admin = defined('OPENCART_ADMIN') && OPENCART_ADMIN;
$root = '/var/www/html/';
define('HTTP_SERVER', $base . ($admin ? 'admin/' : ''));
define('HTTPS_SERVER', HTTP_SERVER);
define('HTTP_CATALOG', $base);
define('HTTPS_CATALOG', $base);
define('DIR_APPLICATION', $root . ($admin ? 'admin/' : 'catalog/'));
if ($admin) define('DIR_CATALOG', $root . 'catalog/');
define('DIR_EXTENSION', $root . 'extension/');
define('DIR_SYSTEM', $root . 'system/');
define('DIR_IMAGE', $root . 'image/');
define('DIR_STORAGE', '/var/www/storage/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . ($admin ? 'view/template/' : 'view/theme/'));
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
foreach (['cache', 'download', 'logs', 'modification', 'session', 'upload'] as $dir) {
    define('DIR_' . strtoupper($dir), DIR_STORAGE . $dir . '/');
}
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', getenv('DB_HOSTNAME') ?: 'db');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'opencart');
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'opencart');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_PREFIX', getenv('DB_PREFIX') ?: 'ocus_');
