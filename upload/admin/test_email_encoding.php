<?php
/**
 * Test script to debug email template encoding issues
 * Access via: http://localhost/~max/oc3.uniqsport.ru/admin/test_email_encoding.php
 */

// Bootstrap OpenCart
require_once(dirname(__FILE__) . '/../config.php');
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Config
$config = new Config();
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Email Encoding Test</title></head><body>";
echo "<h1>Email Template Encoding Test</h1>";

// Test 1: Check what's in the database
echo "<h2>Test 1: Database Content (Raw)</h2>";
$query = $db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `key` = 'module_bonus_manager_email_loyalty_upgrade_body' LIMIT 1");

if ($query->num_rows) {
    $raw_value = $query->row['value'];
    echo "<p><strong>Database value (first 500 chars):</strong></p>";
    echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
    echo htmlspecialchars(substr($raw_value, 0, 500));
    echo "</pre>";

    echo "<p><strong>Value length:</strong> " . strlen($raw_value) . " bytes</p>";
} else {
    echo "<p style='color: red;'>No data found in database for key 'module_bonus_manager_email_loyalty_upgrade_body'</p>";
}

// Test 2: Check if it's serialized
echo "<h2>Test 2: Serialization Check</h2>";
if ($query->num_rows) {
    $unserialized = @unserialize($raw_value);
    if ($unserialized !== false) {
        echo "<p style='color: green;'>Value IS serialized</p>";
        echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
        echo htmlspecialchars(substr($unserialized, 0, 500));
        echo "</pre>";
    } else {
        echo "<p style='color: orange;'>Value is NOT serialized (stored as plain text)</p>";
    }
}

// Test 3: What does config->get return?
echo "<h2>Test 3: Config->get() Output</h2>";
$setting = new Setting($registry);
$registry->set('setting', $setting);

// Manually load setting
$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `key` = 'module_bonus_manager_email_loyalty_upgrade_body'");
if ($query->num_rows) {
    $row = $query->row;
    echo "<p><strong>Serialized flag:</strong> " . $row['serialized'] . "</p>";

    $value = $row['value'];
    if ($row['serialized']) {
        $value = json_decode($row['value'], true);
        if ($value === null) {
            $value = unserialize($row['value']);
        }
    }

    echo "<p><strong>Processed value (first 500 chars):</strong></p>";
    echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
    echo htmlspecialchars(substr($value, 0, 500));
    echo "</pre>";
}

// Test 4: HTML entity test
echo "<h2>Test 4: HTML Entity Rendering</h2>";
$test_string = '&#127881; Test &#128176; Entities &#8381;';
echo "<p><strong>Original:</strong> " . htmlspecialchars($test_string) . "</p>";
echo "<p><strong>html_entity_decode:</strong> " . html_entity_decode($test_string, ENT_QUOTES, 'UTF-8') . "</p>";
echo "<p><strong>Raw output:</strong> " . $test_string . "</p>";

// Test 5: Check character encoding
echo "<h2>Test 5: Character Encoding</h2>";
echo "<p><strong>Default charset:</strong> " . ini_get('default_charset') . "</p>";
echo "<p><strong>MB internal encoding:</strong> " . mb_internal_encoding() . "</p>";

// Test 6: Simulate save and retrieve
echo "<h2>Test 6: Simulate Save/Retrieve Cycle</h2>";
$test_template = '<div>&#127881; Поздравляем! &#128176;</div>';
echo "<p><strong>Original template:</strong></p>";
echo "<pre>" . htmlspecialchars($test_template) . "</pre>";

// Simulate what OpenCart does on save
$serialized = 0; // Check current setting
$query = $db->query("SELECT serialized FROM `" . DB_PREFIX . "setting` WHERE `key` = 'module_bonus_manager_email_loyalty_upgrade_body' LIMIT 1");
if ($query->num_rows) {
    $serialized = $query->row['serialized'];
}
echo "<p><strong>Current serialized flag in DB:</strong> " . $serialized . "</p>";

echo "</body></html>";
