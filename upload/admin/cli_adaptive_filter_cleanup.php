<?php
/**
 * Adaptive Filter - Daily Guest Cleanup Task
 * Removes old guest preference records (older than 30 days)
 * Run this script daily via cron: 0 2 * * * php /path/to/admin/cli_adaptive_filter_cleanup.php
 */

// Version
define('VERSION', '3.0.3.6');

// Configuration
if (is_file('config.php')) {
	require_once('config.php');
}

// Install check
if (!defined('DIR_APPLICATION')) {
	die('Error: config.php not found or DIR_APPLICATION not defined');
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Config
$config = new Config();
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

// Settings
$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");

foreach ($query->rows as $result) {
	if (!$result['serialized']) {
		$config->set($result['key'], $result['value']);
	} else {
		$config->set($result['key'], json_decode($result['value'], true));
	}
}

// Check if module is enabled
if ($config->get('module_adaptive_filter_status')) {
	echo "[" . date('Y-m-d H:i:s') . "] Starting guest preference cleanup...\n";

	// Get count before
	$before_query = $db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "guest_preferences`");
	$before_count = $before_query->row['total'];

	// Delete guest preferences older than 30 days
	$db->query("
		DELETE FROM `" . DB_PREFIX . "guest_preferences`
		WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 DAY)
	");

	// Get count after
	$after_query = $db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "guest_preferences`");
	$after_count = $after_query->row['total'];

	$deleted = $before_count - $after_count;

	echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed! Deleted " . $deleted . " old guest records (older than 30 days)\n";
	echo "[" . date('Y-m-d H:i:s') . "] Remaining guest records: " . $after_count . "\n";
} else {
	echo "[" . date('Y-m-d H:i:s') . "] Module disabled - skipping\n";
}
