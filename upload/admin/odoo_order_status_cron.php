<?php
/**
 * Odoo Order Status Check Cron Job
 *
 * This cron script checks order statuses and sends email notifications
 * for orders that need attention.
 *
 * Usage:
 * Add to crontab to run daily (adjust path as needed):
 * 0 9 * * * /usr/bin/php /path/to/admin/odoo_order_status_cron.php >> /path/to/logs/odoo_cron.log 2>&1
 *
 * Or run manually for testing:
 * php admin/odoo_order_status_cron.php
 * php admin/odoo_order_status_cron.php debug
 */

// Version
define('VERSION', '3.0.3.6');

// Configuration
if (is_file('config.php')) {
    require_once('config.php');
}

// Install check
if (!defined('DIR_APPLICATION')) {
    header('Location: ../install/index.php');
    exit;
}

// Set SERVER_PORT for HTTPS URLs
$_SERVER['SERVER_PORT'] = 443;

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Config
$config = new Config();
$config->load('default');
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

// Load settings from database (store_id = 0 for default store)
$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0'");
foreach ($query->rows as $setting) {
    if (!$setting['serialized']) {
        $config->set($setting['key'], $setting['value']);
    } else {
        $config->set($setting['key'], json_decode($setting['value'], true));
    }
}

// Log
$log = new Log($config->get('error_filename'));
$registry->set('log', $log);

// Request
$registry->set('request', new Request());

// Response
$response = new Response();
$response->addHeader('Content-Type: text/html; charset=utf-8');
$registry->set('response', $response);

// Session (needed for some models)
$session = new Session($config->get('session_engine'), $registry);
$registry->set('session', $session);

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);

// Event
$event = new Event($registry);
$registry->set('event', $event);

// Load events from database (admin events only)
$query = $db->query("SELECT * FROM " . DB_PREFIX . "event WHERE `status` = 1");
foreach ($query->rows as $result) {
    if (substr($result['trigger'], 0, 6) == 'admin/') {
        $event->register(substr($result['trigger'], 6), new Action($result['action']), $result['sort_order']);
    }
}

// Check if debug mode is enabled via command line argument
$debug = false;
if (isset($argv[1]) && $argv[1] === 'debug') {
    $debug = true;
}

// Output start message
echo "====================================\n";
echo "Odoo Order Status Cron Job\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "Debug mode: " . ($debug ? 'ON' : 'OFF') . "\n";
echo "====================================\n\n";

// Load the odoo_connector model
$loader->model('extension/module/odoo_connector');
$odoo_connector = $registry->get('model_extension_module_odoo_connector');

// Run the cron check
try {
    echo "Running order status check...\n";
    $result = $odoo_connector->cronCheckOrderStatuses($debug);

    // Display results
    echo "\n====================================\n";
    echo "RESULTS:\n";
    echo "====================================\n";
    echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "Message: " . $result['message'] . "\n\n";

    if (!empty($result['alfabank_transactions'])) {
        $alfabank = $result['alfabank_transactions'];
        echo "AlfaBank transactions: " .
            "orders=" . (int)$alfabank['orders_checked'] .
            ", attempts=" . (int)$alfabank['attempts'] .
            ", created=" . (int)$alfabank['created'] .
            ", existing=" . (int)$alfabank['existing'] .
            ", errors=" . (int)$alfabank['errors'] . "\n\n";
    }

    if (!empty($result['orders_not_in_odoo'])) {
        echo "Orders NOT in Odoo: " . count($result['orders_not_in_odoo']) . "\n";
        foreach ($result['orders_not_in_odoo'] as $order) {
            echo "  - Order #" . $order['order_id'] . " (" . $order['customer_name'] . ")\n";
        }
        echo "\n";
    }

    if (!empty($result['orders_draft_in_odoo'])) {
        echo "Orders in DRAFT state: " . count($result['orders_draft_in_odoo']) . "\n";
        foreach ($result['orders_draft_in_odoo'] as $order) {
            echo "  - Order #" . $order['opencart_order_id'] . " (Odoo: " . $order['odoo_order_id'] . ")\n";
        }
        echo "\n";
    }

    if (!empty($result['orders_cdek_not_received'])) {
        echo "CDEK orders not received: " . count($result['orders_cdek_not_received']) . "\n";
        foreach ($result['orders_cdek_not_received'] as $order) {
            echo "  - Order #" . $order['order_id'] . " (CDEK: " . $order['cdek_number'] . ", Status: " . $order['status_id'] . ")\n";
        }
        echo "\n";
    }

    if (!empty($result['errors'])) {
        echo "ERRORS:\n";
        foreach ($result['errors'] as $error) {
            echo "  - " . $error . "\n";
        }
        echo "\n";
    }

    echo "====================================\n";
    echo "Completed: " . date('Y-m-d H:i:s') . "\n";
    echo "====================================\n";

    // Exit with appropriate code
    exit($result['success'] ? 0 : 1);

} catch (Exception $e) {
    echo "\n====================================\n";
    echo "FATAL ERROR:\n";
    echo "====================================\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    echo "====================================\n";

    $log->write('Odoo Order Status Cron FATAL ERROR: ' . $e->getMessage());
    exit(1);
}
