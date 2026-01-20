<?php
/**
 * Quick test to verify cron setup
 */

// Load configuration
require_once(dirname(__FILE__) . '/config.php');

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

// Load settings from database
$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0'");

foreach ($query->rows as $result) {
	if (!$result['serialized']) {
		$config->set($result['key'], $result['value']);
	} else {
		$config->set($result['key'], json_decode($result['value'], true));
	}
}

// Define VERSION constant
define('VERSION', $config->get('config_version') ?: '3.0.0.0');

echo "=== Cron Setup Test ===\n\n";

// Test 1: Database connection
echo "1. Database connection: ";
try {
	$test_query = $db->query("SELECT 1");
	echo "✓ OK\n";
} catch (Exception $e) {
	echo "✗ FAILED: " . $e->getMessage() . "\n";
}

// Test 2: Config loaded
echo "2. Config loaded: ";
if ($config->get('config_name')) {
	echo "✓ OK (Store: " . $config->get('config_name') . ")\n";
} else {
	echo "✗ FAILED\n";
}

// Test 3: Bonus manager settings
echo "3. Bonus manager settings: ";
$expiring_status = $config->get('module_bonus_manager_email_expiring_status');
$warning_days = $config->get('module_bonus_manager_expiration_warning_days');
if ($expiring_status !== null) {
	echo "✓ OK\n";
	echo "   - Email expiring status: " . ($expiring_status ? 'Enabled' : 'Disabled') . "\n";
	echo "   - Warning days: " . ($warning_days ?: 'Not set') . "\n";
} else {
	echo "✗ Module not configured\n";
}

// Test 4: Mail engine
echo "4. Mail configuration: ";
$mail_engine = $config->get('config_mail_engine');
$mail_from = $config->get('config_email');
if ($mail_from) {
	echo "✓ OK\n";
	echo "   - Engine: " . ($mail_engine ?: 'mail') . "\n";
	echo "   - From: " . $mail_from . "\n";
} else {
	echo "✗ No email configured\n";
}

// Test 5: Test data exists
echo "5. Test data: ";
$test_data = $db->query("SELECT COUNT(*) as count FROM " . DB_PREFIX . "customer_reward WHERE description LIKE '%TEST%'");
if ($test_data->row['count'] > 0) {
	echo "✓ Found " . $test_data->row['count'] . " test records\n";
} else {
	echo "⚠ No test data found (run test_bonus_expiration_data.sql first)\n";
}

// Test 6: Find bonuses expiring soon
echo "6. Bonuses expiring in 90 days: ";
$expiring = $db->query("
	SELECT COUNT(*) as count
	FROM " . DB_PREFIX . "customer_reward
	WHERE points > 0
	AND date_expires IS NOT NULL
	AND DATEDIFF(date_expires, NOW()) BETWEEN 89 AND 91
");
echo $expiring->row['count'] . " found\n";

echo "\n=== Test Complete ===\n";
