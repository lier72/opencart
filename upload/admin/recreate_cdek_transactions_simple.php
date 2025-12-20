<?php
/**
 * Simple Interactive CDEK Transaction Recreation Tool
 *
 * This script allows you to:
 * 1. List all COD CDEK orders
 * 2. Recreate transactions for specific order IDs
 * 3. Recreate transactions for orders within a date range
 */

// Bootstrap OpenCart
define('VERSION', '3.0.2.0');
if (is_file('config.php')) {
    require_once('config.php');
}
if (!defined('DIR_APPLICATION')) {
    die('Config not loaded');
}

$_SERVER['SERVER_PORT'] = 443;
require_once(DIR_SYSTEM . 'startup.php');
require_once(DIR_SYSTEM . 'library/cdek_integrator/class.cdek_integrator.php');

// Setup
$registry = new Registry();
$config = new Config();
$config->load('default');
$config->load('admin');
$registry->set('config', $config);

$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0'");
foreach ($query->rows as $setting) {
    if (!$setting['serialized']) {
        $config->set($setting['key'], $setting['value']);
    } else {
        $config->set($setting['key'], json_decode($setting['value'], true));
    }
}

$log = new Log($config->get('error_filename'));
$registry->set('log', $log);

$loader = new Loader($registry);
$registry->set('load', $loader);

// Event
$event = new Event($registry);
$registry->set('event', $event);

// Helper functions
function listCodCdekOrders($db) {
    echo "\n=== COD CDEK Orders ===\n\n";

    $query = $db->query(
        "SELECT o.order_id, o.date_added, o.total, o.email,
                co.dispatch_number, co.cdek_number, co.status_id,
                oom.odoo_order_id,
                (SELECT COUNT(*) FROM " . DB_PREFIX . "cdek_order_status_history cosh
                 WHERE cosh.order_id = o.order_id
                 AND cosh.status_id = 'DELIVERED') as is_delivered
        FROM " . DB_PREFIX . "order o
        LEFT JOIN " . DB_PREFIX . "cdek_order co ON o.order_id = co.order_id
        LEFT JOIN " . DB_PREFIX . "odoo_order_map oom ON o.order_id = oom.opencart_order_id
        WHERE o.payment_code = 'cod_cdek'
        ORDER BY o.order_id DESC
        LIMIT 20"
    );

    if (!$query->num_rows) {
        echo "No COD CDEK orders found.\n";
        return;
    }

    printf("%-8s %-12s %-10s %-15s %-15s %-30s %-12s\n",
        "Order ID", "Date", "Total", "CDEK Number", "Status", "Email", "In Odoo?"
    );
    echo str_repeat("-", 120) . "\n";

    foreach ($query->rows as $row) {
        printf("%-8s %-12s %-10s %-15s %-15s %-30s %-12s\n",
            $row['order_id'],
            date('Y-m-d', strtotime($row['date_added'])),
            number_format($row['total'], 2),
            $row['cdek_number'] ?: 'N/A',
            $row['status_id'] ?: 'N/A',
            substr($row['email'], 0, 28),
            $row['odoo_order_id'] ? 'Yes' : 'No'
        );
    }

    echo "\nShowing last 20 orders\n";
}

function processOrders($db, $registry, $order_ids) {
    $loader = $registry->get('load');
    $config = $registry->get('config');
    $log = $registry->get('log');

    // Load models
    $loader->model('extension/module/odoo_connector');
    $odoo_model = $registry->get('model_extension_module_odoo_connector');

    // Get CDEK credentials
    $cdek_settings = $config->get('cdek_integrator_setting');
    $cdek_api = new cdek_integrator($cdek_settings['account'], $cdek_settings['secure_password']);
    $info_component = $cdek_api->loadComponent('info');
    $auth_data = $info_component->getAuthToken();

    if (empty($auth_data['access_token'])) {
        die("ERROR: Failed to authenticate with CDEK API\n");
    }

    $success = 0;
    $failed = 0;
    $skipped = 0;

    foreach ($order_ids as $order_id) {
        echo "\n--- Order #$order_id ---\n";

        // Get order with CDEK data
        $query = $db->query(
            "SELECT o.payment_code, co.dispatch_number, co.cdek_number
            FROM " . DB_PREFIX . "order o
            LEFT JOIN " . DB_PREFIX . "cdek_order co ON o.order_id = co.order_id
            WHERE o.order_id = " . (int)$order_id
        );

        if (!$query->num_rows) {
            echo "✗ Order not found\n";
            $failed++;
            continue;
        }

        $order = $query->row;

        if ($order['payment_code'] != 'cod_cdek') {
            echo "⊘ Not a COD CDEK order\n";
            $skipped++;
            continue;
        }

        if (empty($order['cdek_number'])) {
            echo "✗ No CDEK tracking number\n";
            $failed++;
            continue;
        }

        // Get from CDEK API
        $api_url = 'https://api.cdek.ru/v2/orders?cdek_number=' . $order['cdek_number'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $auth_data['access_token'],
            'Content-Type: application/json'
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || empty($response)) {
            echo "✗ Failed to get CDEK data (HTTP $http_code)\n";
            $failed++;
            continue;
        }

        $cdek_data = json_decode($response, true);
        if (empty($cdek_data['entity'])) {
            echo "✗ Invalid CDEK response\n";
            $failed++;
            continue;
        }

        $payment_sum = $cdek_data['entity']['delivery_detail']['payment_sum'] ?? 0;
        echo "Payment sum: $payment_sum руб\n";

        // Update Odoo transaction
        $result = $odoo_model->updateCdekPaymentTransaction(
            $order_id,
            $cdek_data,
            $order['dispatch_number'],
            $order['cdek_number']
        );

        if ($result['success']) {
            echo "✓ {$result['message']}\n";
            $success++;
        } else {
            echo "✗ {$result['message']}\n";
            $failed++;
        }
    }

    echo "\n=== Summary ===\n";
    echo "Success: $success\n";
    echo "Failed: $failed\n";
    echo "Skipped: $skipped\n";
}

// Main menu
echo "=== CDEK Transaction Recreation Tool ===\n\n";
echo "1. List recent COD CDEK orders\n";
echo "2. Process specific order IDs\n";
echo "3. Process all delivered COD CDEK orders\n";
echo "4. Exit\n\n";
echo "Enter choice (1-4): ";

$choice = trim(fgets(STDIN));

switch ($choice) {
    case '1':
        listCodCdekOrders($db);
        break;

    case '2':
        echo "\nEnter order IDs (comma-separated): ";
        $input = trim(fgets(STDIN));
        $order_ids = array_map('intval', explode(',', $input));
        $order_ids = array_filter($order_ids);

        if (empty($order_ids)) {
            echo "No valid order IDs provided.\n";
            break;
        }

        echo "\nProcessing " . count($order_ids) . " order(s)...\n";
        processOrders($db, $registry, $order_ids);
        break;

    case '3':
        echo "\nFetching all delivered COD CDEK orders...\n";

        $query = $db->query(
            "SELECT DISTINCT o.order_id
            FROM " . DB_PREFIX . "order o
            INNER JOIN " . DB_PREFIX . "cdek_order co ON o.order_id = co.order_id
            WHERE o.payment_code = 'cod_cdek'
            AND co.status_id = 'DELIVERED'
            AND co.cdek_number IS NOT NULL
            AND co.cdek_number != ''
            ORDER BY o.order_id DESC"
        );

        if (!$query->num_rows) {
            echo "No delivered COD CDEK orders found.\n";
            break;
        }

        $order_ids = array();
        foreach ($query->rows as $row) {
            $order_ids[] = $row['order_id'];
        }

        echo "Found " . count($order_ids) . " orders.\n";
        echo "Proceed? (y/n): ";
        $confirm = trim(fgets(STDIN));

        if (strtolower($confirm) === 'y') {
            processOrders($db, $registry, $order_ids);
        } else {
            echo "Cancelled.\n";
        }
        break;

    case '4':
        echo "Goodbye!\n";
        exit(0);

    default:
        echo "Invalid choice.\n";
}
