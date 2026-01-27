<?php
/**
 * Script to migrate custom_field from ocus_address to ocus_customer
 * Copies address custom_field to customer where customer's custom_field is empty
 *
 * USAGE:
 *   1. Place this script in the OpenCart root directory (same level as config.php)
 *   2. Run: php migrate_address_custom_field.php --dry-run    (to see what will be changed)
 *   3. Run: php migrate_address_custom_field.php --execute    (to apply changes)
 *
 * OPTIONS:
 *   --dry-run   Show SQL statements without executing (default)
 *   --execute   Execute the updates directly on the database
 *   --backup    Create backup table before executing (use with --execute)
 */

// Parse command line arguments
$options = getopt('', ['dry-run', 'execute', 'backup']);
$dryRun = !isset($options['execute']);
$createBackup = isset($options['backup']);

// Load OpenCart config from same directory as this script
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    die("ERROR: config.php not found in " . __DIR__ . "\n" .
        "Please place this script in your OpenCart root directory.\n");
}

require_once $configPath;

// Verify DB constants exist
if (!defined('DB_HOSTNAME') || !defined('DB_USERNAME') || !defined('DB_PASSWORD') || !defined('DB_DATABASE')) {
    die("ERROR: Database constants not defined in config.php\n");
}

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error . "\n");
}
$db->set_charset('utf8');

echo "==============================================\n";
echo "OpenCart Address->Customer Custom Field Migrator\n";
echo "==============================================\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no changes will be made)" : "EXECUTE") . "\n";
echo "Database: " . DB_DATABASE . " @ " . DB_HOSTNAME . "\n";
echo "Table prefix: " . DB_PREFIX . "\n";
echo "==============================================\n\n";

/**
 * Count non-empty fields in a custom_field JSON
 * Used to pick the "best" address if customer has multiple
 *
 * @param array $data Decoded JSON custom_field
 * @return int Count of non-empty values
 */
function countFilledFields($data) {
    if (!is_array($data)) return 0;
    $count = 0;
    foreach ($data as $value) {
        if (!empty($value)) $count++;
    }
    return $count;
}

// Query addresses with data where customer has empty custom_field
// Group by customer_id to handle customers with multiple addresses
$sql = "SELECT a.address_id, a.customer_id, a.custom_field as addr_cf, c.custom_field as cust_cf
        FROM " . DB_PREFIX . "address a
        JOIN " . DB_PREFIX . "customer c ON a.customer_id = c.customer_id
        WHERE a.custom_field IS NOT NULL
        AND a.custom_field != ''
        AND a.custom_field != '{}'
        AND a.custom_field != '[]'
        AND (c.custom_field IS NULL OR c.custom_field = '' OR c.custom_field = '{}')
        ORDER BY a.customer_id, a.address_id DESC";

$result = $db->query($sql);

if (!$result) {
    die("Query error: " . $db->error . "\n");
}

// Group by customer and pick the best address (most filled fields)
$customerAddresses = [];
while ($row = $result->fetch_assoc()) {
    $customerId = $row['customer_id'];
    $addrData = json_decode($row['addr_cf'], true);
    $filledCount = countFilledFields($addrData);

    if (!isset($customerAddresses[$customerId]) ||
        $filledCount > $customerAddresses[$customerId]['filled_count']) {
        $customerAddresses[$customerId] = [
            'address_id' => $row['address_id'],
            'custom_field' => $row['addr_cf'],
            'filled_count' => $filledCount
        ];
    }
}

$updates = [];
foreach ($customerAddresses as $customerId => $addrInfo) {
    $updates[] = [
        'customer_id' => $customerId,
        'address_id' => $addrInfo['address_id'],
        'custom_field' => $addrInfo['custom_field'],
        'filled_count' => $addrInfo['filled_count']
    ];
}

// Summary
echo "Total customers to update: " . count($updates) . "\n\n";

if (empty($updates)) {
    echo "No records to migrate.\n";
    $db->close();
    exit(0);
}

if ($dryRun) {
    // DRY RUN: Output SQL statements
    echo "-- =====================================================\n";
    echo "-- SQL STATEMENTS (DRY RUN - not executed)\n";
    echo "-- =====================================================\n\n";

    echo "-- To create backup:\n";
    echo "-- CREATE TABLE " . DB_PREFIX . "customer_backup_cf_migrate AS SELECT customer_id, custom_field FROM " . DB_PREFIX . "customer;\n\n";

    foreach ($updates as $update) {
        $escaped_cf = $db->real_escape_string($update['custom_field']);
        $decoded = json_decode($update['custom_field'], true);
        $preview = is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : $update['custom_field'];

        echo "-- Customer {$update['customer_id']} (from address {$update['address_id']}, {$update['filled_count']} fields filled)\n";
        echo "-- Data: {$preview}\n";
        echo "UPDATE " . DB_PREFIX . "customer SET custom_field = '{$escaped_cf}' WHERE customer_id = {$update['customer_id']};\n\n";
    }

    echo "\n-- To execute these changes, run:\n";
    echo "-- php " . basename(__FILE__) . " --execute --backup\n";

} else {
    // EXECUTE MODE: Apply changes directly

    // Create backup if requested
    if ($createBackup) {
        echo "Creating backup table...\n";
        $backupTable = DB_PREFIX . "customer_backup_cf_migrate_" . date('Ymd_His');
        $backupSql = "CREATE TABLE `{$backupTable}` AS SELECT customer_id, custom_field FROM " . DB_PREFIX . "customer";
        if ($db->query($backupSql)) {
            echo "Backup created: {$backupTable}\n\n";
        } else {
            die("ERROR: Failed to create backup: " . $db->error . "\n");
        }
    }

    // Start transaction
    $db->begin_transaction();

    $success = 0;
    $errors = [];

    foreach ($updates as $update) {
        $escaped_cf = $db->real_escape_string($update['custom_field']);
        $sql = "UPDATE " . DB_PREFIX . "customer SET custom_field = '{$escaped_cf}' WHERE customer_id = {$update['customer_id']}";

        if ($db->query($sql)) {
            $success++;
            echo "Updated customer {$update['customer_id']} (from address {$update['address_id']})\n";
        } else {
            $errors[] = "Customer {$update['customer_id']}: " . $db->error;
        }
    }

    if (empty($errors)) {
        $db->commit();
        echo "\n==============================================\n";
        echo "SUCCESS: Updated {$success} records\n";
        echo "==============================================\n";
    } else {
        $db->rollback();
        echo "\n==============================================\n";
        echo "ROLLED BACK due to errors:\n";
        foreach ($errors as $err) {
            echo "  - {$err}\n";
        }
        echo "==============================================\n";
    }
}

$db->close();

echo "\nDone.\n";
