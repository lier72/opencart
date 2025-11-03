<?php
define('DIR_SYSTEM', __DIR__ . '/../system/');
define('DIR_STORAGE', '/Users/max/Sites/storage/');
define('DIR_APPLICATION', __DIR__.'/../catalog/');
define('DIR_MODIFICATION',__DIR__.'/../system/storage/modification/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/theme/');
define('DIR_LOGS', DIR_STORAGE . 'logs/');

define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'opencart_test');
define('DB_PREFIX', 'oc_test_');


require_once DIR_SYSTEM . 'startup.php';
require_once DIR_SYSTEM . 'engine/loader.php';
require_once DIR_SYSTEM . 'engine/registry.php';
require_once DIR_SYSTEM . 'library/db.php';
require_once DIR_SYSTEM . 'library/config.php';
require_once DIR_SYSTEM . 'library/log.php';
require_once DIR_SYSTEM . 'library/session.php';

// Initialize OpenCart registry
$registry = new Registry();
$loader = new Loader($registry);
$registry->set('load', $loader);