<?php
/**
 * Session GC Backport Migration
 *
 * This script applies the supporting database changes for the session
 * garbage-collection backport:
 * 1. Adds the `config_session_expire` setting with the OpenCart default of 86400
 *    when it is missing.
 * 2. Adds an index on `session.expire` to make cleanup queries efficient.
 *
 * Usage:
 *   php migrate_session_gc_backport.php --dry-run
 *   php migrate_session_gc_backport.php --execute
 */

$options = getopt('', ['dry-run', 'execute']);
$dry_run = !isset($options['execute']);

if (is_file(__DIR__ . '/config.php')) {
	require_once(__DIR__ . '/config.php');
}

if (!defined('DB_HOSTNAME') || !defined('DB_USERNAME') || !defined('DB_PASSWORD') || !defined('DB_DATABASE') || !defined('DB_PREFIX')) {
	die("ERROR: Could not load database configuration from config.php\n");
}

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

if ($db->connect_error) {
	die("Connection failed: " . $db->connect_error . "\n");
}

$db->set_charset('utf8');

$session_table = DB_PREFIX . 'session';
$setting_table = DB_PREFIX . 'setting';
$default_session_expire = 86400;

echo "==============================================\n";
echo "OpenCart Session GC Backport Migration\n";
echo "==============================================\n";
echo "Mode: " . ($dry_run ? 'DRY RUN' : 'EXECUTE') . "\n";
echo "Database: " . DB_DATABASE . " @ " . DB_HOSTNAME . "\n";
echo "Table prefix: " . DB_PREFIX . "\n";
echo "==============================================\n\n";

$stores = array();
$result = $db->query("SELECT DISTINCT `store_id` FROM `" . $setting_table . "` ORDER BY `store_id`");

if (!$result) {
	die("ERROR: Failed to read stores from `" . $setting_table . "`: " . $db->error . "\n");
}

while ($row = $result->fetch_assoc()) {
	$stores[] = (int)$row['store_id'];
}

if (!$stores) {
	$stores[] = 0;
}

$queries = array();

foreach ($stores as $store_id) {
	$setting_query = $db->query("SELECT 1 FROM `" . $setting_table . "` WHERE `store_id` = '" . (int)$store_id . "' AND `code` = 'config' AND `key` = 'config_session_expire' LIMIT 1");

	if (!$setting_query) {
		die("ERROR: Failed to inspect config_session_expire for store " . $store_id . ': ' . $db->error . "\n");
	}

	if (!$setting_query->num_rows) {
		$queries[] = "INSERT INTO `" . $setting_table . "` SET `store_id` = '" . (int)$store_id . "', `code` = 'config', `key` = 'config_session_expire', `value` = '" . (int)$default_session_expire . "', `serialized` = '0'";
	}
}

$index_query = $db->query("SHOW INDEX FROM `" . $session_table . "` WHERE `Key_name` = 'idx_expire'");

if (!$index_query) {
	die("ERROR: Failed to inspect indexes for `" . $session_table . "`: " . $db->error . "\n");
}

if (!$index_query->num_rows) {
	$queries[] = "ALTER TABLE `" . $session_table . "` ADD INDEX `idx_expire` (`expire`)";
}

if (!$queries) {
	echo "Nothing to do. The supporting setting and index are already in place.\n";
	$db->close();
	exit(0);
}

echo "Planned statements:\n\n";

foreach ($queries as $query) {
	echo $query . ";\n\n";
}

if ($dry_run) {
	echo "Run again with --execute to apply these statements.\n";
	$db->close();
	exit(0);
}

foreach ($queries as $query) {
	if (!$db->query($query)) {
		die("ERROR: Failed to execute statement:\n" . $query . ";\n\nMySQL error: " . $db->error . "\n");
	}
}

echo "Migration completed successfully.\n";

$db->close();
