<?php
/**
 * OCMOD Installer for Odoo Integration
 * Installs multiple OCMOD files for the Odoo Integration extension
 * URL: http://localhost/~max/oc3.uniqsport.ru/install_odoo_menu_mod.php
 */

// Load OpenCart configuration
require_once('config.php');

// Database connection
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Set charset
$db->set_charset("utf8");

echo "<h2>Odoo Integration OCMOD Installer</h2>";
echo "<hr>";

// Define OCMOD files to install
$ocmod_files = [
    [
        'file' => __DIR__ . '/odoo_menu_integration.ocmod.xml',
        'code' => 'odoo_integration_menu',
        'name' => 'Odoo Integration Menu',
        'version' => '2.0',
        'description' => 'Adds Odoo Integration menu to admin sidebar'
    ],
    [
        'file' => __DIR__ . '/order_lsit.ocmod.xml',
        'code' => 'odoo_integration_order_list',
        'name' => 'Create Odoo Order Buttons',
        'version' => '1.0',
        'description' => 'Adds "Create in Odoo" button to order list'
    ]
];

$success_count = 0;
$error_count = 0;

// Install each OCMOD
foreach ($ocmod_files as $index => $ocmod) {
    echo "<h3>" . ($index + 1) . ". Installing: {$ocmod['name']}</h3>";

    if (!file_exists($ocmod['file'])) {
        echo "<div style='color: red;'>❌ Error: OCMOD file not found at: {$ocmod['file']}</div><br>";
        $error_count++;
        continue;
    }

    $xml_content = file_get_contents($ocmod['file']);

    // Parse XML to get actual name and version
    $xml = simplexml_load_string($xml_content);
    if ($xml) {
        $ocmod['name'] = (string)$xml->name;
        $ocmod['version'] = (string)$xml->version;
        $ocmod['code'] = (string)$xml->code;
    }

    echo "<strong>File:</strong> " . basename($ocmod['file']) . "<br>";
    echo "<strong>Code:</strong> {$ocmod['code']}<br>";
    echo "<strong>Version:</strong> {$ocmod['version']}<br>";

    $xml_content_escaped = $db->real_escape_string($xml_content);

    // Check if modification already exists
    $check_query = "SELECT modification_id FROM " . DB_PREFIX . "modification WHERE code = '{$ocmod['code']}'";
    $result = $db->query($check_query);

    if ($result->num_rows > 0) {
        // Update existing modification
        $row = $result->fetch_assoc();
        $modification_id = $row['modification_id'];

        $update_query = "UPDATE " . DB_PREFIX . "modification SET
            name = '{$ocmod['name']}',
            author = 'Max Surdu',
            version = '{$ocmod['version']}',
            link = 'http://uniqsport.ru',
            xml = '{$xml_content_escaped}',
            status = 1,
            date_added = NOW()
            WHERE modification_id = {$modification_id}";

        if ($db->query($update_query)) {
            echo "<div style='color: green;'>✓ Updated successfully (ID: {$modification_id})</div>";
            $success_count++;
        } else {
            echo "<div style='color: red;'>❌ Error updating: " . $db->error . "</div>";
            $error_count++;
        }
    } else {
        // Insert new modification
        $insert_query = "INSERT INTO " . DB_PREFIX . "modification
            (extension_install_id, extension_download_id, name, code, author, version, link, xml, status, date_added)
            VALUES (
                0,
                0,
                '{$ocmod['name']}',
                '{$ocmod['code']}',
                'Max Surdu',
                '{$ocmod['version']}',
                'http://uniqsport.ru',
                '{$xml_content_escaped}',
                1,
                NOW()
            )";

        if ($db->query($insert_query)) {
            $modification_id = $db->insert_id;
            echo "<div style='color: green;'>✓ Installed successfully (ID: {$modification_id})</div>";
            $success_count++;
        } else {
            echo "<div style='color: red;'>❌ Error inserting: " . $db->error . "</div>";
            $error_count++;
        }
    }
    echo "<br>";
}

$db->close();

echo "<hr>";
echo "<h3>Installation Summary</h3>";
echo "<strong>Successful:</strong> {$success_count}<br>";
echo "<strong>Errors:</strong> {$error_count}<br><br>";

if ($success_count > 0) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h3 style='margin-top: 0; color: #155724;'>✓ Installation Complete!</h3>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Go to your admin panel: <a href='admin/' target='_blank'>Admin Panel</a><br>";
    echo "2. Navigate to: <strong>Extensions > Modifications</strong><br>";
    echo "3. Click the <strong>'Refresh'</strong> button (blue circular arrow icon) to apply all modifications<br>";
    echo "4. Clear your browser cache and reload the admin panel<br>";
    echo "5. You should see:<br>";
    echo "&nbsp;&nbsp;&nbsp;• 'Odoo Integration' menu in the left sidebar<br>";
    echo "&nbsp;&nbsp;&nbsp;• 'Create in Odoo' button on each order in the order list<br>";
    echo "</div><br>";
}

echo "<div style='background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px;'>";
echo "<strong>⚠️ IMPORTANT:</strong> Delete this file (<code>install_odoo_menu_mod.php</code>) after installation for security!<br>";
echo "</div>";
?>
