<?php
/**
 * Debug script to check loyalty levels configuration loading
 */

// Bootstrap OpenCart
require_once(dirname(__FILE__) . '/config.php');
require_once(DIR_SYSTEM . 'startup.php');

// Start the registry
$registry = new Registry();
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);
$config = new Config();
$registry->set('config', $config);

// Load settings from database manually
echo "<h2>Loading Settings from Database</h2>";
$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `key` LIKE '%loyalty%' ORDER BY `key`");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Key</th><th>Value (first 100 chars)</th><th>Serialized</th></tr>";
foreach ($query->rows as $row) {
	echo "<tr>";
	echo "<td>" . htmlspecialchars($row['key']) . "</td>";
	echo "<td>" . htmlspecialchars(substr($row['value'], 0, 100)) . "</td>";
	echo "<td>" . $row['serialized'] . "</td>";
	echo "</tr>";

	// Set in config
	if (!$row['serialized']) {
		$config->set($row['key'], $row['value']);
	} else {
		$config->set($row['key'], json_decode($row['value'], true));
	}
}
echo "</table>";

// Check what's in config now
echo "<h2>Config Values</h2>";
echo "<pre>";
echo "module_bonus_manager_loyalty_status: ";
var_dump($config->get('module_bonus_manager_loyalty_status'));
echo "\n";

echo "module_bonus_manager_loyalty_info_id: ";
var_dump($config->get('module_bonus_manager_loyalty_info_id'));
echo "\n";

echo "module_bonus_manager_loyalty_period_start: ";
var_dump($config->get('module_bonus_manager_loyalty_period_start'));
echo "\n";

echo "module_bonus_manager_loyalty_levels: ";
var_dump($config->get('module_bonus_manager_loyalty_levels'));
echo "\n";
echo "</pre>";

// Load model and test
require_once(DIR_APPLICATION . 'model/extension/module/bonus_manager.php');
$model = new ModelExtensionModuleBonusManager($registry);

echo "<h2>Model getLoyaltyLevels() Result</h2>";
echo "<pre>";
$levels = $model->getLoyaltyLevels();
print_r($levels);
echo "</pre>";
