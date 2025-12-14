<?php
/**
 * CDEK Migration Script - Remove History Tables
 *
 * This script removes obsolete CDEK history tables that are no longer needed
 * because history data is now fetched from CDEK API on-demand.
 *
 * IMPORTANT: Run this script ONCE after deploying the updated CDEK integration.
 *
 * Tables to be removed:
 * - cdek_order_add_service
 * - cdek_order_call
 * - cdek_order_call_history_delay
 * - cdek_order_call_history_fail
 * - cdek_order_call_history_good
 * - cdek_order_courier
 * - cdek_order_delay_history
 * - cdek_order_package
 * - cdek_order_package_item
 * - cdek_order_reason
 * - cdek_order_schedule
 * - cdek_order_schedule_delay
 * - cdek_order_status_history
 *
 * Usage: Run this script from the command line or access via browser
 * Command line: php migrate_cdek_remove_history_tables.php
 * Browser: http://yourdomain.com/migrate_cdek_remove_history_tables.php
 */

// Configuration
if (is_file('config.php')) {
	require_once('config.php');
}

// Install
if (!defined('DIR_APPLICATION')) {
	die('Error: Could not load config.php');
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

echo "CDEK History Tables Migration Script\n";
echo "=====================================\n\n";

$tables_to_drop = array(
	'cdek_order_add_service',
	'cdek_order_call',
	'cdek_order_call_history_delay',
	'cdek_order_call_history_fail',
	'cdek_order_call_history_good',
	'cdek_order_courier',
	'cdek_order_delay_history',
	'cdek_order_package',
	'cdek_order_package_item',
	'cdek_order_reason',
	'cdek_order_schedule',
	'cdek_order_schedule_delay',
	'cdek_order_status_history'
);

echo "Tables to be dropped:\n";
foreach ($tables_to_drop as $table) {
	echo "  - " . DB_PREFIX . $table . "\n";
}

echo "\n";

// Check if tables exist
$existing_tables = array();
foreach ($tables_to_drop as $table) {
	$result = $db->query("SHOW TABLES LIKE '" . DB_PREFIX . $table . "'");
	if ($result->num_rows > 0) {
		$existing_tables[] = $table;
	}
}

if (empty($existing_tables)) {
	echo "No tables to drop. All history tables have already been removed.\n";
	exit(0);
}

echo "Found " . count($existing_tables) . " tables to drop:\n";
foreach ($existing_tables as $table) {
	echo "  - " . DB_PREFIX . $table . "\n";
}
echo "\n";

// Optionally backup data before dropping
$backup_data = false; // Set to true if you want to backup data to files

if ($backup_data) {
	echo "Backing up data...\n";
	$backup_dir = DIR_STORAGE . 'logs/cdek_migration_backup_' . date('Y-m-d_H-i-s') . '/';
	if (!is_dir($backup_dir)) {
		mkdir($backup_dir, 0755, true);
	}

	foreach ($existing_tables as $table) {
		$full_table = DB_PREFIX . $table;
		$result = $db->query("SELECT * FROM `" . $full_table . "`");

		if ($result->num_rows > 0) {
			$backup_file = $backup_dir . $table . '.json';
			file_put_contents($backup_file, json_encode($result->rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
			echo "  Backed up " . $result->num_rows . " rows from " . $full_table . " to " . $backup_file . "\n";
		}
	}
	echo "Backup completed.\n\n";
}

// Drop tables
echo "Dropping tables...\n";
$dropped = 0;
$errors = array();

foreach ($existing_tables as $table) {
	$full_table = DB_PREFIX . $table;
	try {
		$db->query("DROP TABLE IF EXISTS `" . $full_table . "`");
		echo "  ✓ Dropped " . $full_table . "\n";
		$dropped++;
	} catch (Exception $e) {
		$errors[] = "  ✗ Failed to drop " . $full_table . ": " . $e->getMessage();
		echo $errors[count($errors) - 1] . "\n";
	}
}

echo "\n";
echo "Migration Summary:\n";
echo "  Tables dropped: " . $dropped . "/" . count($existing_tables) . "\n";
if (!empty($errors)) {
	echo "  Errors: " . count($errors) . "\n";
	foreach ($errors as $error) {
		echo $error . "\n";
	}
} else {
	echo "  ✓ All tables successfully dropped!\n";
}

echo "\n";
echo "Migration completed.\n";
echo "\nIMPORTANT: You can now delete this migration script:\n";
echo "  rm " . __FILE__ . "\n";
echo "\n";

?>
