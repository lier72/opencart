<?php
/**
 * Script to fix birthdate custom field formats in OpenCart
 * Converts all date formats to YYYY-MM-DD ISO format
 *
 * Custom field ID 2 = birthdate
 *
 * USAGE:
 *   1. Place this script in the OpenCart root directory (same level as config.php)
 *   2. Run: php fix_birthdate.php --dry-run    (to see what will be changed)
 *   3. Run: php fix_birthdate.php --execute    (to apply changes)
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
echo "OpenCart Birthdate Custom Field Fixer\n";
echo "==============================================\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no changes will be made)" : "EXECUTE") . "\n";
echo "Database: " . DB_DATABASE . " @ " . DB_HOSTNAME . "\n";
echo "Table prefix: " . DB_PREFIX . "\n";
echo "==============================================\n\n";

// Russian month names mapping
$russian_months = [
    'января' => '01', 'февраля' => '02', 'марта' => '03', 'апреля' => '04',
    'мая' => '05', 'июня' => '06', 'июля' => '07', 'августа' => '08',
    'сентября' => '09', 'октября' => '10', 'ноября' => '11', 'декабря' => '12'
];

/**
 * Parse various date formats and convert to YYYY-MM-DD
 *
 * @param string $date The date string in various formats
 * @return string|null Returns ISO date or null if cannot parse
 */
function parseDate($date, $russian_months) {
    $date = trim($date);

    // Already correct format YYYY-MM-DD
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return $date;
    }

    // DD.MM.YYYY format (most common)
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    // DD/MM/YYYY format
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    // DD-MM-YYYY format
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $date, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    // DD.MM.YY format (2-digit year)
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2})$/', $date, $m)) {
        $year = (int)$m[3];
        // Assume 00-30 = 2000s, 31-99 = 1900s
        $year = $year <= 30 ? 2000 + $year : 1900 + $year;
        return sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]);
    }

    // DDMMYYYY format (no separators, 8 digits)
    if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $date, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    // DDMMYY format (no separators, 6 digits)
    if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $date, $m)) {
        $year = (int)$m[3];
        $year = $year <= 30 ? 2000 + $year : 1900 + $year;
        return sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]);
    }

    // DD MM YYYY format (space separated)
    if (preg_match('/^(\d{1,2})\s+(\d{1,2})\s+(\d{4})$/', $date, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    // Russian text format: "29 октября 1983" or "2 марта 2006"
    foreach ($russian_months as $month_name => $month_num) {
        if (preg_match('/(\d{1,2})\s+' . $month_name . '\s+(\d{4})/ui', $date, $m)) {
            return sprintf('%04d-%02d-%02d', $m[2], $month_num, $m[1]);
        }
    }

    // Weird format like "1986. 11-18" => probably 1986-11-18
    if (preg_match('/^(\d{4})\.\s*(\d{1,2})-(\d{1,2})$/', $date, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }

    // DD. MM.YYYY format with extra space
    if (preg_match('/^(\d{1,2})\.\s+(\d{1,2})\.(\d{4})$/', $date, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    // DD.MM YYYY format with space before year
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\s+(\d{4})$/', $date, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }

    return null; // Cannot parse
}

// Query all customers with custom_field
$sql = "SELECT customer_id, custom_field FROM " . DB_PREFIX . "customer
        WHERE custom_field IS NOT NULL AND custom_field != '' AND custom_field != '{}'";
$result = $db->query($sql);

$updates = [];
$failures = [];
$already_correct = 0;

while ($row = $result->fetch_assoc()) {
    $custom_field = json_decode($row['custom_field'], true);

    if (!isset($custom_field['2']) || empty($custom_field['2'])) {
        continue;
    }

    $birthdate = $custom_field['2'];

    // Skip if already correct
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
        $already_correct++;
        continue;
    }

    $parsed = parseDate($birthdate, $russian_months);

    if ($parsed) {
        // Update the custom_field array
        $custom_field['2'] = $parsed;
        // Use plain json_encode() to match OpenCart's native encoding (escapes Cyrillic to \uXXXX)
        $new_json = json_encode($custom_field);

        $updates[] = [
            'customer_id' => $row['customer_id'],
            'old_value' => $birthdate,
            'new_value' => $parsed,
            'new_json' => $new_json
        ];
    } else {
        $failures[] = [
            'customer_id' => $row['customer_id'],
            'value' => $birthdate
        ];
    }
}

// Summary
echo "Total records with birthdate: " . (count($updates) + count($failures) + $already_correct) . "\n";
echo "Already in correct format: " . $already_correct . "\n";
echo "Records to update: " . count($updates) . "\n";
echo "Records that cannot be parsed: " . count($failures) . "\n\n";

if ($dryRun) {
    // DRY RUN: Output SQL statements
    echo "-- =====================================================\n";
    echo "-- SQL STATEMENTS (DRY RUN - not executed)\n";
    echo "-- =====================================================\n\n";

    echo "-- To create backup:\n";
    echo "-- CREATE TABLE " . DB_PREFIX . "customer_backup_birthdate AS SELECT customer_id, custom_field FROM " . DB_PREFIX . "customer;\n\n";

    foreach ($updates as $update) {
        $escaped_json = $db->real_escape_string($update['new_json']);
        echo "-- Customer {$update['customer_id']}: '{$update['old_value']}' => '{$update['new_value']}'\n";
        echo "UPDATE " . DB_PREFIX . "customer SET custom_field = '{$escaped_json}' WHERE customer_id = {$update['customer_id']};\n\n";
    }

    echo "\n-- To execute these changes, run:\n";
    echo "-- php " . basename(__FILE__) . " --execute --backup\n";

} else {
    // EXECUTE MODE: Apply changes directly

    // Create backup if requested
    if ($createBackup) {
        echo "Creating backup table...\n";
        $backupTable = DB_PREFIX . "customer_backup_birthdate_" . date('Ymd_His');
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
        $escaped_json = $db->real_escape_string($update['new_json']);
        $sql = "UPDATE " . DB_PREFIX . "customer SET custom_field = '{$escaped_json}' WHERE customer_id = {$update['customer_id']}";

        if ($db->query($sql)) {
            $success++;
            echo "Updated customer {$update['customer_id']}: '{$update['old_value']}' => '{$update['new_value']}'\n";
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

// Show records that need manual review
if (!empty($failures)) {
    echo "\n-- =====================================================\n";
    echo "-- RECORDS THAT NEED MANUAL REVIEW (could not parse):\n";
    echo "-- =====================================================\n";
    foreach ($failures as $fail) {
        echo "-- Customer ID {$fail['customer_id']}: '{$fail['value']}'\n";
    }
}

$db->close();

echo "\nDone.\n";
