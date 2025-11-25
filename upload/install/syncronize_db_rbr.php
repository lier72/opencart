<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 12/07/24
 * Time: 13:23
 *
 * Database Synchronization Script for OpenCart 2 to OpenCart 3 Migration
 *
 * USAGE MODES:
 * 1. Full Database Transfer: Transfers all tables and data
 *    php syncronize_db_rbr.php full
 *
 * 2. Essential Tables Transfer: Only transfers order, customer, shipment, payment and CDEK data
 *    php syncronize_db_rbr.php essential
 *
 * 3. SEO URL Migration Only: Transfers only SEO URLs from url_alias to seo_url
 *    php syncronize_db_rbr.php seo
 */

// Determine mode from command line argument
$mode = isset($argv[1]) ? strtolower($argv[1]) : 'essential';

if (!in_array($mode, ['full', 'essential', 'seo'])) {
    die("Invalid mode. Use 'full', 'essential', or 'seo'\nUsage: php syncronize_db_rbr.php [full|essential|seo]\n");
}

echo "\n========================================\n";
echo "Database Synchronization Mode: " . strtoupper($mode) . "\n";
echo "========================================\n\n";

// Read current config

define('OLD_DB_DATABASE', 'a1627-unqs-oc');
define('OLD_DB_USERNAME', 'a1627-unqs-oc');
//define('OLD_DB_USERNAME', 'root');
define('OLD_DB_PREFIX', 'ocus_');
define('OLD_DB_PASSWORD','StsZmRVqnWcXF2XA');
//define('OLD_DB_PASSWORD','');
$drop_tables = true;


// Configuration
    if (is_file('config.php')) {
        require_once('config.php');
    }
    $new_db = new mysqli(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE, DB_PORT);
    $old_db = new mysqli(DB_HOSTNAME,OLD_DB_USERNAME,OLD_DB_PASSWORD,OLD_DB_DATABASE, DB_PORT);

    $result =$new_db->query("SET SESSION sql_mode = REPLACE(REPLACE(@@sql_mode,'NO_ZERO_DATE',''),'NO_ZERO_IN_DATE','')") or die("Error Setting NO_ZERO_DATE " . mysqli_error($new_db));
    $result =$new_db->query("SELECT @@sql_mode") or die("Error Setting NO_ZERO_DATE " . mysqli_error($new_db));
    $res = $result->fetch_assoc();
    var_dump($res['@@sql_mode']);

    if ($new_db -> connect_errno || $old_db -> connect_errno) {
        echo "Failed to connect to MySQL: " . $new_db -> connect_error;
        echo "Failed to connect to MySQL: " . $old_db -> connect_error;
        exit();
    }
// Function to create and populate tables from OpenCart 2.1 that don't exist in OpenCart 3.0
function create_and_populate_missing_tables($old_db, $new_db, $new_db_nf_tables, $exception_tables) {
    foreach ($new_db_nf_tables as $table) {
        if (!in_array($table, $exception_tables)) {
            // Get the create table statement from old database
            $result = $old_db->query("SHOW CREATE TABLE " . $table);
            if ($result && $row = $result->fetch_assoc()) {
                $create_table_sql = $row['Create Table'];
                
                // Create the table in new database
                try {
                    $new_db->query($create_table_sql) or die("Error creating table " . $table . ": " . mysqli_error($new_db));
                    echo "Created table $table successfully\n";
                    
                    // Get column information with complete column details
                    $result = $old_db->query("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_TYPE 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = '" . OLD_DB_DATABASE . "' 
                        AND TABLE_NAME = '" . $table . "'
                        ORDER BY ORDINAL_POSITION");
                    
                    if ($result->num_rows > 0) {
                        // Get data from old table
                        $sql_old = "SELECT * FROM `" . OLD_DB_DATABASE . "`." . $table;
                        $data_result = $old_db->query($sql_old);
                        
                        if ($data_result) {
                            while ($row_data = $data_result->fetch_assoc()) {
                                $columns = array();
                                $values = array();
                                
                                // Reset column info result pointer
                                $result->data_seek(0);
                                
                                while ($col = $result->fetch_assoc()) {
                                    $col_name = $col['COLUMN_NAME'];
                                    $col_type = $col['DATA_TYPE'];
                                    $is_nullable = $col['IS_NULLABLE'];
                                    $value = $row_data[$col_name];
                                    
                                    $columns[] = "`" . $col_name . "`";
                                    
                                    // Handle different data types
                                    if ($value === null && $is_nullable === 'YES') {
                                        $values[] = "NULL";
                                    } else if (strstr($col_name, "date")) {
                                        if ($value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
                                            $values[] = strstr($col_name, "date_end") ? "NULL" : "'1970-01-01 00:00:01'";
                                        } else {
                                            $values[] = "'" . $old_db->real_escape_string($value) . "'";
                                        }
                                    } else if (in_array($col_type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'])) {
                                        // Force numeric values for integer columns
                                        $values[] = is_numeric($value) ? $value : 0;
                                    } else if ($col_type === 'decimal' || $col_type === 'float' || $col_type === 'double') {
                                        // Handle numeric types
                                        $values[] = is_numeric($value) ? $value : 0.0;
                                    } else {
                                        // String types (varchar, text, etc)
                                        $values[] = "'" . $old_db->real_escape_string($value) . "'";
                                    }
                                }
                                
                                // Construct and execute insert statement
                                $sql = "INSERT INTO " . $table . " (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";
                                try {
                                    $new_db->query($sql) or die("Error inserting into " . $table . ": " . mysqli_error($new_db) . "\nQuery: " . $sql);
                                } catch (Exception $e) {
                                    echo "Error inserting row in $table: " . $e->getMessage() . "\n";
                                    continue;
                                }
                            }
                            echo "Populated table $table successfully\n";
                        }
                    }
                } catch (Exception $e) {
                    echo "Error processing table $table: " . $e->getMessage() . "\n";
                    continue;
                }
            }
        } else {
            echo "Skipping table $table (in exception list)\n";
        }
    }
}

// Define essential tables for "essential" mode
// These tables contain order, customer, shipment, payment and CDEK data
$essential_tables = array(
    // Customer related tables
    'ocus_customer',
    'ocus_customer_activity',
    'ocus_customer_affiliate',
    'ocus_customer_group',
    'ocus_customer_group_description',
    'ocus_customer_ip',
    'ocus_customer_login',
    'ocus_customer_reward',
    'ocus_customer_transaction',
    'ocus_customer_wishlist',
    'ocus_address',

    // Order related tables
    'ocus_order',
    'ocus_order_history',
    'ocus_order_option',
    'ocus_order_product',
    'ocus_order_status',
    'ocus_order_total',
    'ocus_order_to_sdek',
    'ocus_odoo_order_map',


    // CDEK shipping tables (excluding cdek_city)
    'ocus_cdek_dispatch',
    'ocus_cdek_order',
    'ocus_cdek_order_add_service',
    'ocus_cdek_order_call_history_delay',
    'ocus_cdek_order_call_history_good',
    'ocus_cdek_order_delay_history',
    'ocus_cdek_order_package',
    'ocus_cdek_order_package_item',
    'ocus_cdek_order_reason',
    'ocus_cdek_order_status_history',

    // Payment related tables
    'ocus_voucher',
    'ocus_voucher_theme',
    'ocus_voucher_theme_description',
    'ocus_coupon',
    'ocus_coupon_category',
    'ocus_coupon_history',
    'ocus_coupon_product',

    // Cart tables
    'ocus_cart',

    // Product pricing tables
    'ocus_product_special',
    'ocus_product_discount',
);

// the idea is to store table names from to databases
// Compare them and then store copmuted data in the new mapping table
// Identical tables will be transfered to new databse wihout modifications
// All the rest tables and data will be manually treated
    $old_tables = array();
    $new_tables = array();
    $identical_tables = array();
    $data_transformed_tables = array();
    $new_db_nf_tables = array();
    $exception_tables = array('ocus_extension',
	'ocus_extension_install',
	'ocus_extension_path',
        'ocus_gcrdev_sitemap',
        'ocus_google_base_category',
        'ocus_google_base_category_to_category',
//        'ocus_information',
//        'ocus_information_description',
//        'ocus_information_to_layout',
//        'ocus_information_to_store',
        'ocus_layout',
        'ocus_setting',
        'ocus_url_alias',
        'ocus_weight_class',
        'ocus_weight_class_description',
        'ocus_language',
        'ocus_currency',
        'ocus_modification',
        'ocus_module',
    	'ocus_cdek_city',
        'ocus_journal2_blog_category',
        'ocus_journal2_blog_category_description',
        'ocus_journal2_blog_category_to_layout',
        'ocus_journal2_blog_category_to_store',
        'ocus_journal2_blog_comments',
        'ocus_journal2_blog_post',
        'ocus_journal2_blog_post_description',
        'ocus_journal2_blog_post_to_category',
        'ocus_journal2_blog_post_to_layout',
        'ocus_journal2_blog_post_to_product',
        'ocus_journal2_blog_post_to_store',
        'ocus_journal2_config',
        'ocus_journal2_modules',
        'ocus_journal2_newsletter',
        'ocus_journal2_settings',
        'ocus_journal2_skins',
        'ocus_event',
       );

    // In essential mode, add all non-essential tables to exception list
    if ($mode === 'essential') {
        echo "Essential mode: Only transferring essential tables\n";
        echo "Essential tables count: " . count($essential_tables) . "\n\n";
    } elseif ($mode === 'seo') {
        echo "SEO mode: Skipping all table transfers, will only migrate SEO URLs\n\n";
    }

    // Skip table analysis in SEO-only mode
    if ($mode !== 'seo') {
        $result = $old_db->query("SHOW TABLES") or die("Error SHOW TABLES " . mysqli_error($old_db));
        while($row = mysqli_fetch_array($result)){
            array_push($old_tables, $row[0]);
        }
    //    print_r( $old_tables);

        $result = $new_db->query("SHOW TABLES") or die("Error SHOW TABLES " . mysqli_error($new_db));
        while($row=mysqli_fetch_array($result)){
        array_push($new_tables, $row[0]);
        }
    }
//    print_r( $new_tables);
// Look if the table has different structure
    if ($mode !== 'seo') {
    foreach ($old_tables as $table){
//        print_r($table); echo "\n";
        if (is_int(array_search($table, $new_tables))){
//          printf ("Both databases have %s \n", $table);
            $new_table_structure = array();
            $old_table_structure = array();
            $result = $old_db->query("DESCRIBE ". $table) or die("Error DESCRIBE " . mysqli_error($old_db));
            while($row=mysqli_fetch_array($result)){
                array_push($old_table_structure, $row[0]);
            }
//            echo "oc table " . $table . " table structure \n";
//            print_r($old_table_structure);
            $result = $new_db->query("DESCRIBE ". $table) or die("Error DESCRIBE " . mysqli_error($new_db));
            while($row=mysqli_fetch_array($result)) {
                array_push($new_table_structure, $row[0]);
            }
//            echo "oc3 table " . $table . " table structure \n";
//            print_r($new_table_structure);
            if (array_diff($old_table_structure,$new_table_structure) == array_diff($new_table_structure,$old_table_structure)){
                array_push($identical_tables,$table);
            }
            else {
                array_push($data_transformed_tables, array("table"=>$table,
                    "nf_old_table_fields"=>array_diff($old_table_structure,$new_table_structure),
                    "nf_new_table_fields"=>array_diff($new_table_structure,$old_table_structure)));
            }

        } else {
//            printf ("New database does not have %s \n", $table);
            array_push( $new_db_nf_tables, $table);
        }
    }
//    echo "oc3 Not Found tables  \n";
//    print_r($new_db_nf_tables);
//    file_put_contents("new_db_nf_tables.json",json_encode($new_db_nf_tables));

    // Create and populate tables that exist in OC2 but not in OC3
    create_and_populate_missing_tables($old_db, $new_db, $new_db_nf_tables, $exception_tables);
    } // End of if ($mode !== 'seo')

// Fix CDEK order table column sizes to accommodate larger COD values (skip in SEO mode)
if ($mode !== 'seo') {
    echo "\nChecking and fixing ocus_cdek_order column sizes...\n";
    $result = $new_db->query("SHOW TABLES LIKE 'ocus_cdek_order'");
    if ($result && $result->num_rows > 0) {
        $new_db->query("ALTER TABLE ocus_cdek_order
            MODIFY COLUMN cod DECIMAL(10,4) DEFAULT 0.0000,
            MODIFY COLUMN cod_fact DECIMAL(10,4) DEFAULT 0.0000")
            or die("Error modifying ocus_cdek_order columns: " . mysqli_error($new_db));
        echo "Successfully updated cod and cod_fact columns to DECIMAL(10,4)\n";
    } else {
        echo "Table ocus_cdek_order not found, skipping column modification\n";
    }
}

//    echo "oc3 tables that need data transformation  \n";
//    print_r($data_transformed_tables);
//    file_put_contents("data_transformed_tables.json",json_encode($data_transformed_tables));

    /* Data transformation
    //  Affiliate activity - wo do not have much of affiliate activities
    [{"table":"ocus_affiliate_activity","nf_old_table_fields":["activity_id"],"nf_new_table_fields":["affiliate_activity_id"]},*/

    // Do we really translate API from old DB to new DB?
/*  {"table":"ocus_api","nf_old_table_fields":{"1":"name"},"nf_new_table_fields":{"1":"username"}},*/
//    $sql = "REPLACE INTO ocus_api (api_id, username, `key`, `status`, date_added, date_modified) SELECT
//            api_id, `name`, `key`, `status`, date_added, date_modified FROM `".OLD_DB_DATABASE."`.ocus_api" ;
//    print_r($sql); echo"\n";
//    $new_db->query($sql) or die("Error replacing into ocus_api " . mysqli_error($new_db));

/*  {"table":"ocus_banner_image","nf_old_table_fields":[],"nf_new_table_fields":{"2":"language_id","3":"title"}},*/
if ($mode !== 'seo' && $mode === 'full') {
    echo "Processing banner_image table (full mode only)...\n";
    $start = microtime(true);
    $sql_odldb = "SELECT a.banner_image_id, a.banner_id, b.language_id, b.title, a.link, a.image, a.sort_order  FROM `".OLD_DB_DATABASE."`.ocus_banner_image a
      LEFT JOIN `".OLD_DB_DATABASE."`.ocus_banner_image_description b ON a.banner_image_id = b.banner_image_id";
    $result=$old_db->query($sql_odldb) or die("Error replacing into ocus_banner_image " . mysqli_error($old_db));
    while($row = $result->fetch_assoc()){
        $sql = "INSERT INTO ocus_banner_image SET `banner_image_id`=".$row['banner_image_id'].", `banner_id`=".$row['banner_id'].", `language_id`=".$row['language_id']."
        , `title`='".$row['title']."', `link`='".$row['link']."', `image`='".$row['image']."', `sort_order`=".$row['sort_order']." ON DUPLICATE KEY UPDATE
        `banner_image_id`=".$row['banner_image_id'].", `banner_id`=".$row['banner_id'].", `language_id`=".$row['language_id']."
        , `title`='".$row['title']."', `link`='".$row['link']."', `image`='".$row['image']."', `sort_order`=".$row['sort_order'];
//        print_r($sql); echo"\n";
//        $new_db->query($sql) or die("Error replacing into ocus_banner_image " . mysqli_error($new_db));
    }
    $time_elapsed_secs=microtime(true)-$start;
    echo "Time elapsed for banner_image table: " . $time_elapsed_secs . "\n";
} elseif ($mode !== 'seo') {
    echo "Skipping banner_image table (essential mode)\n";
}


/*  {"table":"ocus_cart","nf_old_table_fields":[],"nf_new_table_fields":{"1":"api_id"}},*/
// Cart table is in essential_tables, so process in both modes (skip in SEO mode)

if ($mode !== 'seo') {
echo "Processing cart table...\n";
$sql_odldb = "SELECT `cart_id`, `customer_id`, `session_id`, `product_id`, `recurring_id`, `option`, `quantity`, `date_added` FROM `".OLD_DB_DATABASE."`.ocus_cart";
$result=$old_db->query($sql_odldb) or die("Error replacing into ocus_cart " . mysqli_error($old_db));
while($row = $result->fetch_assoc()){
    $sql = "INSERT INTO ocus_cart SET `cart_id`=".$row['cart_id'].", `api_id`= 0, `customer_id`=".$row['customer_id'].
        ", `session_id`='".$row['session_id']."', `product_id`='".$row['product_id']."', `recurring_id`='".$row['recurring_id'].
        "', `option`='".$row['option']."', `quantity`=".$row['quantity'].", `date_added`='".$row['date_added']."' ON DUPLICATE KEY UPDATE 
        `cart_id`=".$row['cart_id'].", `api_id`= 0, `customer_id`=".$row['customer_id'].", `session_id`='".$row['session_id'].
        "', `product_id`='".$row['product_id']."', `recurring_id`=".$row['recurring_id'].
        ", `option`='".$row['option']."', `quantity`=".$row['quantity'].", `date_added`='".$row['date_added']."'";
//        print_r($sql); echo"\n";
    $new_db->query($sql) or die("Error replacing into ocus_cart " . mysqli_error($new_db));
}
echo "Cart table processed successfully.\n";
} // End of if ($mode !== 'seo') for cart

/*  {"table":"ocus_custom_field","nf_old_table_fields":[],"nf_new_table_fields":{"3":"validation"}},
  {"table":"ocus_customer","nf_old_table_fields":[],"nf_new_table_fields":{"3":"language_id","20":"code"}},*/
if ($mode !== 'seo') {
/*    $sql = "REPLACE INTO ocus_customer (`customer_id`,`customer_group_id`, `store_id`, `language_id`,
  `firstname`, `lastname` , `email` , `telephone` , `fax` , `password` , `salt` , `cart`, `wishlist`, `newsletter`, `address_id` ,
  `custom_field`,  `ip`, `status`, `safe`, `token`, `code`, `date_added`, `approved`) SELECT 
  `customer_id`,`customer_group_id`, `store_id`, 0 ,
  `firstname`, `lastname` , `email` , `telephone` , `fax` , `password` , `salt` , `cart`, `wishlist`, `newsletter`, `address_id` ,
  `custom_field`,  `ip`, `status`, `safe`, `token`, 0, IF(date_added LIKE '0000%', '1970-01-01 00:00:01', date_added), `approved` FROM `".OLD_DB_DATABASE."`.ocus_customer" ;*/

    $sql_odldb = "SELECT `customer_id`,`customer_group_id`, `store_id`, `firstname`, `lastname` , `email` , `telephone`, `fax` , `password` , `salt` , `cart`, `wishlist`, `newsletter`, `address_id` ,
  `custom_field`,  `ip`, `status`, `safe`, `token`, IF(date_added LIKE '0000%', '1970-01-01 00:00:01', date_added) AS `date_added`, `approved` FROM `".OLD_DB_DATABASE."`.ocus_customer";
    $result=$old_db->query($sql_odldb) or die("Error replacing into ocus_customer " . mysqli_error($old_db));
    while($row = $result->fetch_assoc()) {
    $sql = "INSERT INTO ocus_customer SET `customer_id`=" . $row['customer_id'] . ", `customer_group_id`= ". $row['customer_group_id'].
        ", `store_id`=" . $row['store_id'] . ", `language_id`= 0" .
        ", `firstname`='" . $row['firstname'] . "', `lastname`='" . $row['lastname'] . "', `email`='" . $row['email'] .
        "', `telephone`='" . $row['telephone'] . "', `fax`='" . $row['fax'] . "', `password`='" . $row['password'] .
        "', `salt`='" . $row['salt'] . "', `cart`='" . $row['cart'] . "', `wishlist`='" . $row['wishlist'] .
        "', `newsletter`='" . $row['newsletter'] . "', `address_id`=" . $row['address_id'] . ", `custom_field`='" . addslashes($row['custom_field']) .
        "', `ip`='" . $row['ip'] . "', `status`=" . $row['status'] . ", `safe`=" . $row['safe'] .
        ", `token`='" . $row['token'] ."', `code`= 0 , `approved`=" . $row['approved'] . ", `date_added`='" . $row['date_added'] . "' ON DUPLICATE KEY UPDATE 
        `customer_id`=" . $row['customer_id'] . ", `customer_group_id`= ". $row['customer_group_id'].
        ", `store_id`=" . $row['store_id'] . ", `language_id`= 0" .
        ", `firstname`='" . $row['firstname'] . "', `lastname`='" . $row['lastname'] . "', `email`='" . $row['email'] .
        "', `telephone`='" . $row['telephone'] . "', `fax`='" . $row['fax'] . "', `password`='" . $row['password'] .
        "', `salt`='" . $row['salt'] . "', `cart`='" . $row['cart'] . "', `wishlist`='" . $row['wishlist'] .
        "', `newsletter`='" . $row['newsletter'] . "', `address_id`=" . $row['address_id'] . ", `custom_field`='" . addslashes($row['custom_field']) .
        "', `ip`='" . $row['ip'] . "', `status`=" . $row['status'] . ", `safe`=" . $row['safe'] .
        ", `token`='" . $row['token'] ."', `code`= 0, `approved`=" . $row['approved'] . ", `date_added`='" . $row['date_added'] . "'";
//    print_r($sql);
    $new_db->query($sql) or die("\n Error replacing into ocus_customer " . mysqli_error($new_db));
    }
/*  {"table":"ocus_customer_activity","nf_old_table_fields":["activity_id"],"nf_new_table_fields":["customer_activity_id"]},
*/
//    $sql = "REPLACE INTO ocus_customer_activity (`customer_activity_id`,`customer_id`, `key`, `data`, `ip`, `date_added`) SELECT
//            `activity_id`, `customer_id`, `key`,`data`, `ip`, `date_added` FROM `".OLD_DB_DATABASE."`.ocus_customer_activity" ;
    $sql_odldb = "SELECT `activity_id`, `customer_id`, `key`,`data`, `ip`, `date_added` FROM `".OLD_DB_DATABASE."`.ocus_customer_activity";
    $result=$old_db->query($sql_odldb) or die("Error replacing into ocus_customer_activity " . mysqli_error($old_db));
    while($row = $result->fetch_assoc()) {
//        print_r($row['data']);
        $sql = "INSERT INTO ocus_customer_activity SET `customer_activity_id`=" . $row['activity_id'] . ", `customer_id`= " . $row['customer_id'] .
            ", `key`='" . $row['key'] . "', `data`='" . addslashes($row['data']). "', `ip`='" . $row['ip'] . "', `date_added`='" . $row['date_added'] . "' ON DUPLICATE KEY UPDATE 
            `customer_activity_id`=" . $row['activity_id'] . ", `customer_id`= " . $row['customer_id'] .
            ", `key`='" . $row['key'] . "', `data`='" . addslashes($row['data']) . "', `ip`='" . $row['ip'] . "', `date_added`='" . $row['date_added'] . "'";
//    print_r($sql); echo "\n";
        $new_db->query($sql) or die("\n Error replacing into ocus_customer_activity " . mysqli_error($new_db));
    }
} // End of if ($mode !== 'seo') for customer data
/*
{"table":"ocus_event","nf_old_table_fields":[],"nf_new_table_fields":{"4":"status","5":"sort_order"}},
*/
// Event table migration - only in full mode
if ($mode !== 'seo' && $mode === 'full') {
    echo "Processing event table migration...\n";

    // First, migrate existing OC2 events with new fields
    $sql_odldb = "SELECT `event_id`, `code`, `trigger`, `action` FROM `".OLD_DB_DATABASE."`.ocus_event";
    $result = $old_db->query($sql_odldb) or die("Error selecting from ocus_event: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        // Convert trigger format from path/to/method to path/to.method if needed
        $trigger = $row['trigger'];
        if (strpos($trigger, '.') === false) {
            $parts = explode('/', $trigger);
            if (count($parts) >= 2) {
                $string_1 = implode('/', array_slice($parts, 0, -2));
                $string_2 = implode('/', array_slice($parts, -2));
                $trigger = $string_1 . '.' . $string_2;
            }
        }

        $sql = "INSERT INTO ocus_event SET `event_id`=" . $row['event_id'] .
            ", `code`='" . $new_db->real_escape_string($row['code']) .
            "', `trigger`='" . $new_db->real_escape_string($trigger) .
            "', `action`='" . $new_db->real_escape_string($row['action']) .
            "', `status`=1, `sort_order`=0 ON DUPLICATE KEY UPDATE " .
            "`code`='" . $new_db->real_escape_string($row['code']) .
            "', `trigger`='" . $new_db->real_escape_string($trigger) .
            "', `action`='" . $new_db->real_escape_string($row['action']) .
            "', `status`=1, `sort_order`=0";

        $new_db->query($sql) or die("\n Error inserting into ocus_event: " . mysqli_error($new_db));
    }

    // Add missing default OC3 events (from upgrade_8.php)
    $oc3_events = array(
        array('code' => 'activity_customer_add', 'trigger' => 'catalog/model/account/customer.addCustomer/after', 'action' => 'event/activity.addCustomer'),
        array('code' => 'activity_customer_edit', 'trigger' => 'catalog/model/account/customer.editCustomer/after', 'action' => 'event/activity.editCustomer'),
        array('code' => 'activity_customer_password', 'trigger' => 'catalog/model/account/customer.editPassword/after', 'action' => 'event/activity.editPassword'),
        array('code' => 'activity_customer_forgotten', 'trigger' => 'catalog/model/account/customer.addToken/after', 'action' => 'event/activity.forgotten'),
        array('code' => 'activity_transaction', 'trigger' => 'catalog/model/account/customer.addTransaction/after', 'action' => 'event/activity.addTransaction'),
        array('code' => 'activity_customer_login', 'trigger' => 'catalog/model/account/customer.deleteLoginAttempts/after', 'action' => 'event/activity.login'),
        array('code' => 'activity_address_add', 'trigger' => 'catalog/model/account/address.addAddress/after', 'action' => 'event/activity.addAddress'),
        array('code' => 'activity_address_edit', 'trigger' => 'catalog/model/account/address.editAddress/after', 'action' => 'event/activity.editAddress'),
        array('code' => 'activity_address_delete', 'trigger' => 'catalog/model/account/address.deleteAddress/after', 'action' => 'event/activity.deleteAddress'),
        array('code' => 'activity_affiliate_add', 'trigger' => 'catalog/model/account/customer.addAffiliate/after', 'action' => 'event/activity.addAffiliate'),
        array('code' => 'activity_affiliate_edit', 'trigger' => 'catalog/model/account/customer.editAffiliate/after', 'action' => 'event/activity.editAffiliate'),
        array('code' => 'activity_order_add', 'trigger' => 'catalog/model/checkout/order.addHistory/before', 'action' => 'event/activity.addHistory'),
        array('code' => 'activity_return_add', 'trigger' => 'catalog/model/account/returns.addReturn/after', 'action' => 'event/activity.addReturn'),
        array('code' => 'mail_transaction', 'trigger' => 'catalog/model/account/customer.addTransaction/after', 'action' => 'mail/transaction'),
        array('code' => 'mail_forgotten', 'trigger' => 'catalog/model/account/customer.addToken/after', 'action' => 'mail/forgotten'),
        array('code' => 'mail_customer_add', 'trigger' => 'catalog/model/account/customer.addCustomer/after', 'action' => 'mail/register'),
        array('code' => 'mail_customer_alert', 'trigger' => 'catalog/model/account/customer.addCustomer/after', 'action' => 'mail/register.alert'),
        array('code' => 'mail_affiliate_add', 'trigger' => 'catalog/model/account/customer.addAffiliate/after', 'action' => 'mail/affiliate'),
        array('code' => 'mail_affiliate_alert', 'trigger' => 'catalog/model/account/customer.addAffiliate/after', 'action' => 'mail/affiliate.alert'),
        array('code' => 'mail_order_add', 'trigger' => 'catalog/model/checkout/order.addHistory/before', 'action' => 'mail/order'),
        array('code' => 'mail_order_alert', 'trigger' => 'catalog/model/checkout/order.addHistory/before', 'action' => 'mail/order.alert'),
        array('code' => 'statistics_review_add', 'trigger' => 'catalog/model/catalog/review.addReview/after', 'action' => 'event/statistics.addReview'),
        array('code' => 'statistics_return_add', 'trigger' => 'catalog/model/account/returns.addReturn/after', 'action' => 'event/statistics.addReturn'),
        array('code' => 'statistics_order_history', 'trigger' => 'catalog/model/checkout/order.addHistory/after', 'action' => 'event/statistics.addHistory'),
        array('code' => 'admin_mail_affiliate_approve', 'trigger' => 'admin/model/customer/customer_approval.approveAffiliate/after', 'action' => 'mail/affiliate.approve'),
        array('code' => 'admin_mail_affiliate_deny', 'trigger' => 'admin/model/customer/customer_approval.denyAffiliate/after', 'action' => 'mail/affiliate.deny'),
        array('code' => 'admin_mail_customer_approve', 'trigger' => 'admin/model/customer/customer_approval.approveCustomer/after', 'action' => 'mail/customer.approve'),
        array('code' => 'admin_mail_customer_deny', 'trigger' => 'admin/model/customer/customer_approval.denyCustomer/after', 'action' => 'mail/customer.deny'),
        array('code' => 'admin_mail_reward', 'trigger' => 'admin/model/customer/customer.addReward/after', 'action' => 'mail/reward'),
        array('code' => 'admin_mail_transaction', 'trigger' => 'admin/model/customer/customer.addTransaction/after', 'action' => 'mail/transaction'),
        array('code' => 'admin_mail_return', 'trigger' => 'admin/model/sale/return.addReturn/after', 'action' => 'mail/returns'),
        array('code' => 'admin_mail_forgotten', 'trigger' => 'admin/model/user/user.addToken/after', 'action' => 'mail/forgotten'),
        array('code' => 'admin_currency_add', 'trigger' => 'admin/model/currency.addCurrency/after', 'action' => 'event/currency'),
        array('code' => 'admin_currency_edit', 'trigger' => 'admin/model/currency.editCurrency/after', 'action' => 'event/currency'),
        array('code' => 'admin_setting', 'trigger' => 'admin/model/setting/setting.editSetting/after', 'action' => 'event/currency'),
    );

    $events_added = 0;
    foreach ($oc3_events as $event) {
        // Check if event already exists
        $check = $new_db->query("SELECT * FROM ocus_event WHERE `code` = '" . $new_db->real_escape_string($event['code']) . "'");

        if (!$check || $check->num_rows == 0) {
            $sql = "INSERT INTO ocus_event SET " .
                "`code` = '" . $new_db->real_escape_string($event['code']) . "', " .
                "`trigger` = '" . $new_db->real_escape_string($event['trigger']) . "', " .
                "`action` = '" . $new_db->real_escape_string($event['action']) . "', " .
                "`status` = 1, `sort_order` = 0";

            $new_db->query($sql) or die("\n Error adding OC3 event: " . mysqli_error($new_db));
            $events_added++;
        }
    }

    // Update specific event triggers (from upgrade_8.php line 266)
    $new_db->query("UPDATE ocus_event SET `trigger` = 'admin/model/sale/returns.addHistory/after' WHERE `code` = 'admin_mail_return'");

    // Remove obsolete events (from upgrade_8.php line 269)
    $new_db->query("DELETE FROM ocus_event WHERE `action` = 'extension/extension/promotion.getList'");

    // Rename subscription to mail_subscription if exists (from upgrade_8.php line 272)
    $new_db->query("UPDATE ocus_event SET `code` = 'mail_subscription' WHERE `code` = 'subscription'");

    echo "Event table migrated successfully. Added $events_added new OC3 events.\n";
} elseif ($mode !== 'seo') {
    echo "Skipping event table migration (essential mode)\n";
}

// Absolutely no reson to transfer MODIFIACTIONS from OC2 to OC3
/*  {"table":"ocus_modification","nf_old_table_fields":[],"nf_new_table_fields": {"1":"extension_install_id","10":"extension_download_id"}}]
*/
//$identical_tables = array_slice($identical_tables,0,1);
//$identical_tables = array('ocus_zone');
if ($mode !== 'seo') {
foreach ($identical_tables as $table){
    // In essential mode, skip tables not in essential_tables list
    if ($mode === 'essential' && !in_array($table, $essential_tables)) {
        echo "Skipping table $table (not in essential tables list)\n";
        continue;
    }

    if(!in_array($table, $exception_tables)){
        $result = $new_db->query("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA ='"
            .DB_DATABASE."' AND TABLE_NAME = '". $table ."'");
        if ($result->num_rows >0 ) {
            $column_types = [];
            $columns1 = [];
            $columns2 = [];
            while ($row = $result->fetch_assoc()) {
                if (strstr($row['COLUMN_NAME'],"date")){
                    if (strstr($row['COLUMN_NAME'],"date_end")) $columns2[]= "IF (".$row['COLUMN_NAME']." LIKE '0000%', NULL, ".$row['COLUMN_NAME'].") AS " .$row['COLUMN_NAME'];
                    else $columns2[]= "IF (".$row['COLUMN_NAME']." LIKE '0000%', '1970-01-01 00:00:01', ".$row['COLUMN_NAME'].") AS " .$row['COLUMN_NAME'];
                } else
                    $columns2[] = "`".$row['COLUMN_NAME']."`";
                $columns1[] = "`".$row['COLUMN_NAME']."`";
                $column_types[] = $row['DATA_TYPE'];
            }
            // Construct the Replace into SQL statement
            $columnList1 = implode(', ', $columns1);
            $columnList2 = implode(', ', $columns2);
            $sql_odldb = "SELECT " . $columnList2 . " FROM ". $table;
            print_r($sql_odldb); echo "\n";
            $result = $old_db->query($sql_odldb) or die("\n Error selecting from  " . $table ." ". mysqli_error($old_db));
            while ($row = $result->fetch_assoc()) {
//                var_dump($row);
                $sql = "INSERT INTO " .$table. " SET ";
                $on_duplicate = " ON DUPLICATE KEY UPDATE ";
                foreach (array_combine($columns1,$column_types) as $column => $type){
//                    echo "type -> "; var_dump($type);
//                    echo "column -> "; var_dump($column);
                    switch($type) {
                        case 'varchar':
                        case 'text':
                            $sql = $sql . $column . " = '" . addslashes($row[substr($column,1,-1)]) . "', ";
                            $on_duplicate = $on_duplicate . $column . " = '" .  addslashes($row[substr($column,1,-1)]) . "', ";
                            break;
                        case 'float':
                        case 'decimal':
                        case 'int':
                            if( $row[substr($column,1,-1)] == NULL) {
                                $sql = $sql . $column . " = NULL, ";
                                $on_duplicate = $on_duplicate . $column . " = NULL, ";
                            } else {
                                $sql = $sql . $column . " = " . $row[substr($column, 1, -1)] . ", ";
                                $on_duplicate = $on_duplicate . $column . " = " . $row[substr($column, 1, -1)] . ", ";
                            }
                            break;
                        case 'date':
                        case 'datetime':
                            if( $row[substr($column,1,-1)] == '' OR $row[substr($column,1,-1)] == NULL) {
                                $sql = $sql . $column . " = DEFAULT, ";
                                $on_duplicate = $on_duplicate . $column . " = DEFAULT, ";
                            } else {
                                $sql = $sql . $column . ' = "' . $row[substr($column,1,-1)] . '", ';
                                $on_duplicate = $on_duplicate  . $column . ' = "' . $row[substr($column,1,-1)] . '", ';
                            }
                            break;
                        default:
                            $sql = $sql . $column . ' = "' . $row[substr($column,1,-1)] . '", ';
                            $on_duplicate = $on_duplicate  . $column . ' = "' . $row[substr($column,1,-1)] . '", ';
                    }

                }
                //removing ending commas
                $sql=substr($sql,0, -2).substr($on_duplicate,0,-2);
//                var_dump($sql);

                // Use REPLACE INTO for tables with unique keys to avoid duplicate key errors
                $use_replace = false;
                $check_unique = $new_db->query("SHOW KEYS FROM " . $table . " WHERE Key_name != 'PRIMARY' AND Non_unique = 0");
                if ($check_unique && $check_unique->num_rows > 0) {
                    $use_replace = true;
                }

                if ($use_replace) {
                    // Convert INSERT ... ON DUPLICATE to REPLACE INTO
                    $sql = str_replace("INSERT INTO", "REPLACE INTO", $sql);
                    $sql = preg_replace('/ ON DUPLICATE KEY UPDATE.*$/', '', $sql);
                }

                $new_db->query($sql) or die("\n Error replacing into " . $table . " -> " . mysqli_error($new_db));
            }
/*               $sql = "REPLACE INTO $table ($column>List1) SELECT $columnList2  FROM `" . OLD_DB_DATABASE . "`." . $table;*/
//               $new_db->query($sql) or die("\n Error replacing into " . $table . " -> " . mysqli_error($new_db));
            echo "Records in $table replaced successfully\n";
        } else {
            echo "Table $table does not have any columns\n";
        }
    }
}
} // End of if ($mode !== 'seo') for identical tables


/*
 * the list of non empty tables which we need to populate from old DATABASE
+----------------+-----------------------------------------+
| TABLE_SCHEMA   | TABLE_NAME                              |
+----------------+-----------------------------------------+
| a1627-unqs-oc3 | ocus_address                            |
| a1627-unqs-oc3 | ocus_api                                |
| a1627-unqs-oc3 | ocus_api_ip                             |
| a1627-unqs-oc3 | ocus_api_session                        |
| a1627-unqs-oc3 | ocus_attribute                          |
| a1627-unqs-oc3 | ocus_attribute_description              |
| a1627-unqs-oc3 | ocus_attribute_group                    |
| a1627-unqs-oc3 | ocus_attribute_group_description        |
| a1627-unqs-oc3 | ocus_banner                             |
| a1627-unqs-oc3 | ocus_banner_image                       |
| a1627-unqs-oc3 | ocus_banner_image_description           |
| a1627-unqs-oc3 | ocus_cart                               |
| a1627-unqs-oc3 | ocus_category                           |
| a1627-unqs-oc3 | ocus_category_description               |
| a1627-unqs-oc3 | ocus_category_filter                    |
| a1627-unqs-oc3 | ocus_category_path                      |
| a1627-unqs-oc3 | ocus_category_to_layout                 |
| a1627-unqs-oc3 | ocus_category_to_store                  |
| a1627-unqs-oc3 | ocus_cdek_city                          |
| a1627-unqs-oc3 | ocus_cdek_dispatch                      |
| a1627-unqs-oc3 | ocus_cdek_order                         |
| a1627-unqs-oc3 | ocus_cdek_order_add_service             |
| a1627-unqs-oc3 | ocus_cdek_order_call_history_delay      |
| a1627-unqs-oc3 | ocus_cdek_order_call_history_good       |
| a1627-unqs-oc3 | ocus_cdek_order_delay_history           |
| a1627-unqs-oc3 | ocus_cdek_order_package                 |
| a1627-unqs-oc3 | ocus_cdek_order_package_item            |
| a1627-unqs-oc3 | ocus_cdek_order_reason                  |
| a1627-unqs-oc3 | ocus_cdek_order_status_history          |
| a1627-unqs-oc3 | ocus_country                            |
| a1627-unqs-oc3 | ocus_coupon                             |
| a1627-unqs-oc3 | ocus_csvprice_pro                       |
| a1627-unqs-oc3 | ocus_csvprice_pro_profiles              |
| a1627-unqs-oc3 | ocus_currency                           |
| a1627-unqs-oc3 | ocus_custom_field                       |
| a1627-unqs-oc3 | ocus_custom_field_customer_group        |
| a1627-unqs-oc3 | ocus_custom_field_description           |
| a1627-unqs-oc3 | ocus_customer                           |
| a1627-unqs-oc3 | ocus_customer_activity                  |
| a1627-unqs-oc3 | ocus_customer_affiliate                 |
| a1627-unqs-oc3 | ocus_customer_group                     |
| a1627-unqs-oc3 | ocus_customer_group_description         |
| a1627-unqs-oc3 | ocus_customer_ip                        |
| a1627-unqs-oc3 | ocus_customer_login                     |
| a1627-unqs-oc3 | ocus_customer_reward                    |
| a1627-unqs-oc3 | ocus_customer_transaction               |
| a1627-unqs-oc3 | ocus_customer_wishlist                  |
| a1627-unqs-oc3 | ocus_extension                          |
| a1627-unqs-oc3 | ocus_extension_install                  |
| a1627-unqs-oc3 | ocus_extension_path                     |
| a1627-unqs-oc3 | ocus_filter                             |
| a1627-unqs-oc3 | ocus_filter_description                 |
| a1627-unqs-oc3 | ocus_filter_group                       |
| a1627-unqs-oc3 | ocus_filter_group_description           |
| a1627-unqs-oc3 | ocus_gcrdev_sitemap                     |
| a1627-unqs-oc3 | ocus_geo_zone                           |
| a1627-unqs-oc3 | ocus_google_base_category               |
| a1627-unqs-oc3 | ocus_information                        |
| a1627-unqs-oc3 | ocus_information_description            |
| a1627-unqs-oc3 | ocus_information_to_layout              |
| a1627-unqs-oc3 | ocus_information_to_store               |
| a1627-unqs-oc3 | ocus_journal3_blog_category             |
| a1627-unqs-oc3 | ocus_journal3_blog_category_description |
| a1627-unqs-oc3 | ocus_journal3_blog_category_to_store    |
| a1627-unqs-oc3 | ocus_journal3_blog_comments             |
| a1627-unqs-oc3 | ocus_journal3_blog_post                 |
| a1627-unqs-oc3 | ocus_journal3_blog_post_description     |
| a1627-unqs-oc3 | ocus_journal3_blog_post_to_category     |
| a1627-unqs-oc3 | ocus_journal3_blog_post_to_product      |
| a1627-unqs-oc3 | ocus_journal3_blog_post_to_store        |
| a1627-unqs-oc3 | ocus_journal3_layout                    |
| a1627-unqs-oc3 | ocus_journal3_module                    |
| a1627-unqs-oc3 | ocus_journal3_newsletter                |
| a1627-unqs-oc3 | ocus_journal3_setting                   |
| a1627-unqs-oc3 | ocus_journal3_skin_setting              |
| a1627-unqs-oc3 | ocus_journal3_style                     |
| a1627-unqs-oc3 | ocus_journal3_variable                  |
| a1627-unqs-oc3 | ocus_language                           |
| a1627-unqs-oc3 | ocus_layout                             |
| a1627-unqs-oc3 | ocus_layout_module                      |
| a1627-unqs-oc3 | ocus_layout_route                       |
| a1627-unqs-oc3 | ocus_length_class                       |
| a1627-unqs-oc3 | ocus_length_class_description           |
| a1627-unqs-oc3 | ocus_manufacturer                       |
| a1627-unqs-oc3 | ocus_manufacturer_to_store              |
| a1627-unqs-oc3 | ocus_marketing                          |
| a1627-unqs-oc3 | ocus_mega_menu                          |
| a1627-unqs-oc3 | ocus_mega_menu_modules                  |
| a1627-unqs-oc3 | ocus_modification                       |
| a1627-unqs-oc3 | ocus_module                             |
| a1627-unqs-oc3 | ocus_oasl_identity                      |
| a1627-unqs-oc3 | ocus_oasl_user                          |
| a1627-unqs-oc3 | ocus_odoo_client_map                    |
| a1627-unqs-oc3 | ocus_odoo_config                        |
| a1627-unqs-oc3 | ocus_odoo_order_map                     |
| a1627-unqs-oc3 | ocus_odoo_product_variant_map           |
| a1627-unqs-oc3 | ocus_odoo_region_map                    |
| a1627-unqs-oc3 | ocus_option                             |
| a1627-unqs-oc3 | ocus_option_description                 |
| a1627-unqs-oc3 | ocus_option_value                       |
| a1627-unqs-oc3 | ocus_option_value_description           |
| a1627-unqs-oc3 | ocus_order                              |
| a1627-unqs-oc3 | ocus_order_history                      |
| a1627-unqs-oc3 | ocus_order_option                       |
| a1627-unqs-oc3 | ocus_order_product                      |
| a1627-unqs-oc3 | ocus_order_status                       |
| a1627-unqs-oc3 | ocus_order_to_sdek                      |
| a1627-unqs-oc3 | ocus_order_total                        |
| a1627-unqs-oc3 | ocus_product                            |
| a1627-unqs-oc3 | ocus_product_attribute                  |
| a1627-unqs-oc3 | ocus_product_description                |
| a1627-unqs-oc3 | ocus_product_discount                   |
| a1627-unqs-oc3 | ocus_product_filter                     |
| a1627-unqs-oc3 | ocus_product_image                      |
| a1627-unqs-oc3 | ocus_product_option                     |
| a1627-unqs-oc3 | ocus_product_option_stock_history       |
| a1627-unqs-oc3 | ocus_product_option_value               |
| a1627-unqs-oc3 | ocus_product_related                    |
| a1627-unqs-oc3 | ocus_product_special                    |
| a1627-unqs-oc3 | ocus_product_stock_history              |
| a1627-unqs-oc3 | ocus_product_to_category                |
| a1627-unqs-oc3 | ocus_product_to_layout                  |
| a1627-unqs-oc3 | ocus_product_to_store                   |
| a1627-unqs-oc3 | ocus_relatedoptions                     |
| a1627-unqs-oc3 | ocus_relatedoptions_option              |
| a1627-unqs-oc3 | ocus_relatedoptions_variant_product     |
| a1627-unqs-oc3 | ocus_return                             |
| a1627-unqs-oc3 | ocus_return_action                      |
| a1627-unqs-oc3 | ocus_return_history                     |
| a1627-unqs-oc3 | ocus_return_reason                      |
| a1627-unqs-oc3 | ocus_return_status                      |
| a1627-unqs-oc3 | ocus_review                             |
| a1627-unqs-oc3 | ocus_seo_url                            |
| a1627-unqs-oc3 | ocus_session                            |
| a1627-unqs-oc3 | ocus_setting                            |
| a1627-unqs-oc3 | ocus_stock_status                       |
| a1627-unqs-oc3 | ocus_tax_rate_to_customer_group         |
| a1627-unqs-oc3 | ocus_tax_rule                           |
| a1627-unqs-oc3 | ocus_url_alias                          |
| a1627-unqs-oc3 | ocus_user                               |
| a1627-unqs-oc3 | ocus_user_group                         |
| a1627-unqs-oc3 | ocus_voucher                            |
| a1627-unqs-oc3 | ocus_voucher_theme                      |
| a1627-unqs-oc3 | ocus_voucher_theme_description          |
| a1627-unqs-oc3 | ocus_weight_class                       |
| a1627-unqs-oc3 | ocus_weight_class_description           |
| a1627-unqs-oc3 | ocus_zone                               |
| a1627-unqs-oc3 | ocus_zone_to_geo_zone                   |
+----------------+-----------------------------------------+
*/

// Function to migrate Journal2 blog data to Journal3 blog tables
function migrate_journal2_to_journal3_blog($old_db, $new_db) {
    echo "\n=== Starting Journal2 to Journal3 Blog Migration ===\n";

    // Check table structures and get column information for Journal3 tables
    echo "Checking Journal3 blog table structures...\n";

    // Get column defaults for journal3_blog_category_description
    $cat_desc_cols = [];
    $result = $new_db->query("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '" . DB_DATABASE . "'
        AND TABLE_NAME = 'ocus_journal3_blog_category_description'");
    while ($row = $result->fetch_assoc()) {
        $cat_desc_cols[$row['COLUMN_NAME']] = $row;
    }

    // Get column defaults for journal3_blog_post_description
    $post_desc_cols = [];
    $result = $new_db->query("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '" . DB_DATABASE . "'
        AND TABLE_NAME = 'ocus_journal3_blog_post_description'");
    while ($row = $result->fetch_assoc()) {
        $post_desc_cols[$row['COLUMN_NAME']] = $row;
    }

    // Get column defaults for journal3_blog_post
    $post_cols = [];
    $result = $new_db->query("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = '" . DB_DATABASE . "'
        AND TABLE_NAME = 'ocus_journal3_blog_post'");
    while ($row = $result->fetch_assoc()) {
        $post_cols[$row['COLUMN_NAME']] = $row;
    }

    // Determine default values based on actual schema
    // Check meta_robots column nullability
    $meta_robots_value = "NULL"; // Default SQL value (not quoted)
    if (isset($cat_desc_cols['meta_robots'])) {
        if ($cat_desc_cols['meta_robots']['IS_NULLABLE'] == 'NO') {
            // NOT NULL constraint - use empty string
            $meta_robots_value = "''";
            echo "  meta_robots: NOT NULL - using empty string\n";
        } else {
            // Nullable - use NULL
            $meta_robots_value = "NULL";
            echo "  meta_robots: NULLABLE - using NULL\n";
        }
    }

    // Check post_data column nullability
    $post_data_value = "'{}'"; // Default to empty JSON
    if (isset($post_cols['post_data'])) {
        if ($post_cols['post_data']['IS_NULLABLE'] == 'NO') {
            // NOT NULL constraint - use empty JSON object
            $post_data_value = "'{}'";
            echo "  post_data: NOT NULL - using '{}'\n";
        } else {
            // Nullable - can use NULL if needed
            $post_data_value = "NULL";
            echo "  post_data: NULLABLE - using NULL\n";
        }
    }

    echo "Schema values set: meta_robots=" . $meta_robots_value . ", post_data=" . $post_data_value . "\n";

    // 1. Migrate blog categories
    echo "Migrating blog categories...\n";
    $sql = "SELECT category_id, image, parent_id, sort_order, status FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_category";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_category: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $insert_sql = "INSERT INTO ocus_journal3_blog_category
            (category_id, parent_id, image, status, sort_order)
            VALUES (
                " . $row['category_id'] . ",
                " . ($row['parent_id'] ? $row['parent_id'] : "NULL") . ",
                " . ($row['image'] ? "'" . $new_db->real_escape_string($row['image']) . "'" : "NULL") . ",
                " . ($row['status'] ? $row['status'] : "0") . ",
                " . ($row['sort_order'] ? $row['sort_order'] : "0") . "
            )
            ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                image = VALUES(image),
                status = VALUES(status),
                sort_order = VALUES(sort_order)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_category: " . mysqli_error($new_db));
    }
    echo "Blog categories migrated successfully.\n";

    // 2. Migrate blog category descriptions
    echo "Migrating blog category descriptions...\n";
    $sql = "SELECT category_id, description, keyword, language_id, meta_description, meta_keywords, meta_title, name
            FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_category_description";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_category_description: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $insert_sql = "INSERT INTO ocus_journal3_blog_category_description
            (category_id, language_id, name, description, meta_title, meta_keywords, meta_robots, meta_description, keyword)
            VALUES (
                " . $row['category_id'] . ",
                " . $row['language_id'] . ",
                " . ($row['name'] ? "'" . $new_db->real_escape_string($row['name']) . "'" : "NULL") . ",
                " . ($row['description'] ? "'" . $new_db->real_escape_string($row['description']) . "'" : "NULL") . ",
                " . ($row['meta_title'] ? "'" . $new_db->real_escape_string($row['meta_title']) . "'" : "NULL") . ",
                " . ($row['meta_keywords'] ? "'" . $new_db->real_escape_string($row['meta_keywords']) . "'" : "NULL") . ",
                " . $meta_robots_value . ",
                " . ($row['meta_description'] ? "'" . $new_db->real_escape_string($row['meta_description']) . "'" : "NULL") . ",
                " . ($row['keyword'] ? "'" . $new_db->real_escape_string($row['keyword']) . "'" : "NULL") . "
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                meta_title = VALUES(meta_title),
                meta_keywords = VALUES(meta_keywords),
                meta_description = VALUES(meta_description),
                keyword = VALUES(keyword)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_category_description: " . mysqli_error($new_db));
    }
    echo "Blog category descriptions migrated successfully.\n";

    // 3. Migrate blog category to layout
    echo "Migrating blog category to layout mappings...\n";
    $sql = "SELECT category_id, layout_id, store_id FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_category_to_layout";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_category_to_layout: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $insert_sql = "INSERT INTO ocus_journal3_blog_category_to_layout
            (category_id, store_id, layout_id)
            VALUES (
                " . $row['category_id'] . ",
                " . $row['store_id'] . ",
                " . ($row['layout_id'] ? $row['layout_id'] : "NULL") . "
            )
            ON DUPLICATE KEY UPDATE
                layout_id = VALUES(layout_id)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_category_to_layout: " . mysqli_error($new_db));
    }
    echo "Blog category to layout mappings migrated successfully.\n";

    // 4. Migrate blog category to store
    echo "Migrating blog category to store mappings...\n";
    $sql = "SELECT DISTINCT category_id, store_id FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_category_to_store";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_category_to_store: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $insert_sql = "INSERT INTO ocus_journal3_blog_category_to_store
            (category_id, store_id)
            VALUES (
                " . $row['category_id'] . ",
                " . $row['store_id'] . "
            )
            ON DUPLICATE KEY UPDATE
                category_id = VALUES(category_id)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_category_to_store: " . mysqli_error($new_db));
    }
    echo "Blog category to store mappings migrated successfully.\n";

    // 5. Migrate blog posts
    echo "Migrating blog posts...\n";
    $sql = "SELECT post_id, author_id, comments, date_created, date_updated, image, sort_order, status, views
            FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_post";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_post: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $date_created = ($row['date_created'] && $row['date_created'] != '0000-00-00 00:00:00')
            ? "'" . $row['date_created'] . "'"
            : "'1970-01-01 00:00:01'";
        $date_updated = ($row['date_updated'] && $row['date_updated'] != '0000-00-00 00:00:00')
            ? "'" . $row['date_updated'] . "'"
            : "'1970-01-01 00:00:01'";

        $insert_sql = "INSERT INTO ocus_journal3_blog_post
            (post_id, author_id, image, comments, status, sort_order, date_created, date_updated, views, post_data)
            VALUES (
                " . $row['post_id'] . ",
                " . ($row['author_id'] ? $row['author_id'] : "NULL") . ",
                " . ($row['image'] ? "'" . $new_db->real_escape_string($row['image']) . "'" : "NULL") . ",
                " . ($row['comments'] ? $row['comments'] : "0") . ",
                " . ($row['status'] ? $row['status'] : "0") . ",
                " . ($row['sort_order'] ? $row['sort_order'] : "0") . ",
                " . $date_created . ",
                " . $date_updated . ",
                " . ($row['views'] ? $row['views'] : "0") . ",
                " . $post_data_value . "
            )
            ON DUPLICATE KEY UPDATE
                author_id = VALUES(author_id),
                image = VALUES(image),
                comments = VALUES(comments),
                status = VALUES(status),
                sort_order = VALUES(sort_order),
                date_created = VALUES(date_created),
                date_updated = VALUES(date_updated),
                views = VALUES(views)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_post: " . mysqli_error($new_db));
    }
    echo "Blog posts migrated successfully.\n";

    // 6. Migrate blog post descriptions
    echo "Migrating blog post descriptions...\n";
    $sql = "SELECT post_id, description, keyword, language_id, meta_description, meta_keywords, meta_title, name, tags
            FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_post_description";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_post_description: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $insert_sql = "INSERT INTO ocus_journal3_blog_post_description
            (post_id, language_id, name, description, meta_title, meta_keywords, meta_robots, meta_description, keyword, tags)
            VALUES (
                " . $row['post_id'] . ",
                " . $row['language_id'] . ",
                " . ($row['name'] ? "'" . $new_db->real_escape_string($row['name']) . "'" : "NULL") . ",
                " . ($row['description'] ? "'" . $new_db->real_escape_string($row['description']) . "'" : "NULL") . ",
                " . ($row['meta_title'] ? "'" . $new_db->real_escape_string($row['meta_title']) . "'" : "NULL") . ",
                " . ($row['meta_keywords'] ? "'" . $new_db->real_escape_string($row['meta_keywords']) . "'" : "NULL") . ",
                " . $meta_robots_value . ",
                " . ($row['meta_description'] ? "'" . $new_db->real_escape_string($row['meta_description']) . "'" : "NULL") . ",
                " . ($row['keyword'] ? "'" . $new_db->real_escape_string($row['keyword']) . "'" : "NULL") . ",
                " . ($row['tags'] ? "'" . $new_db->real_escape_string($row['tags']) . "'" : "NULL") . "
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                meta_title = VALUES(meta_title),
                meta_keywords = VALUES(meta_keywords),
                meta_description = VALUES(meta_description),
                keyword = VALUES(keyword),
                tags = VALUES(tags)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_post_description: " . mysqli_error($new_db));
    }
    echo "Blog post descriptions migrated successfully.\n";

    // 7. Migrate blog post to category
    echo "Migrating blog post to category mappings...\n";
    $sql = "SELECT category_id, post_id FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_post_to_category";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_post_to_category: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $insert_sql = "INSERT INTO ocus_journal3_blog_post_to_category
            (post_id, category_id)
            VALUES (
                " . $row['post_id'] . ",
                " . $row['category_id'] . "
            )
            ON DUPLICATE KEY UPDATE
                post_id = VALUES(post_id)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_post_to_category: " . mysqli_error($new_db));
    }
    echo "Blog post to category mappings migrated successfully.\n";

    // 8. Migrate blog post to layout
    echo "Migrating blog post to layout mappings...\n";
    $sql = "SELECT post_id, layout_id, store_id FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_post_to_layout";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_post_to_layout: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $insert_sql = "INSERT INTO ocus_journal3_blog_post_to_layout
            (post_id, store_id, layout_id)
            VALUES (
                " . $row['post_id'] . ",
                " . $row['store_id'] . ",
                " . ($row['layout_id'] ? $row['layout_id'] : "NULL") . "
            )
            ON DUPLICATE KEY UPDATE
                layout_id = VALUES(layout_id)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_post_to_layout: " . mysqli_error($new_db));
    }
    echo "Blog post to layout mappings migrated successfully.\n";

    // 9. Migrate blog post to product
    echo "Migrating blog post to product mappings...\n";
    $sql = "SELECT post_id, product_id FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_post_to_product";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_post_to_product: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $insert_sql = "INSERT INTO ocus_journal3_blog_post_to_product
            (post_id, product_id)
            VALUES (
                " . $row['post_id'] . ",
                " . $row['product_id'] . "
            )
            ON DUPLICATE KEY UPDATE
                post_id = VALUES(post_id)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_post_to_product: " . mysqli_error($new_db));
    }
    echo "Blog post to product mappings migrated successfully.\n";

    // 10. Migrate blog post to store
    echo "Migrating blog post to store mappings...\n";
    $sql = "SELECT DISTINCT post_id, store_id FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_post_to_store";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_post_to_store: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $insert_sql = "INSERT INTO ocus_journal3_blog_post_to_store
            (post_id, store_id)
            VALUES (
                " . $row['post_id'] . ",
                " . $row['store_id'] . "
            )
            ON DUPLICATE KEY UPDATE
                post_id = VALUES(post_id)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_post_to_store: " . mysqli_error($new_db));
    }
    echo "Blog post to store mappings migrated successfully.\n";

    // 11. Migrate blog comments
    echo "Migrating blog comments...\n";
    $sql = "SELECT comment_id, author_id, comment, customer_id, date, email, name, parent_id, post_id, status, website
            FROM `" . OLD_DB_DATABASE . "`.ocus_journal2_blog_comments";
    $result = $old_db->query($sql) or die("Error selecting from ocus_journal2_blog_comments: " . mysqli_error($old_db));

    while($row = $result->fetch_assoc()) {
        $date = ($row['date'] && $row['date'] != '0000-00-00 00:00:00')
            ? "'" . $row['date'] . "'"
            : "'1970-01-01 00:00:01'";

        $insert_sql = "INSERT INTO ocus_journal3_blog_comments
            (comment_id, parent_id, post_id, customer_id, author_id, name, email, website, comment, status, date)
            VALUES (
                " . $row['comment_id'] . ",
                " . ($row['parent_id'] ? $row['parent_id'] : "NULL") . ",
                " . ($row['post_id'] ? $row['post_id'] : "NULL") . ",
                " . ($row['customer_id'] ? $row['customer_id'] : "NULL") . ",
                " . ($row['author_id'] ? $row['author_id'] : "NULL") . ",
                " . ($row['name'] ? "'" . $new_db->real_escape_string($row['name']) . "'" : "NULL") . ",
                " . ($row['email'] ? "'" . $new_db->real_escape_string($row['email']) . "'" : "NULL") . ",
                " . ($row['website'] ? "'" . $new_db->real_escape_string($row['website']) . "'" : "NULL") . ",
                " . ($row['comment'] ? "'" . $new_db->real_escape_string($row['comment']) . "'" : "NULL") . ",
                " . ($row['status'] ? $row['status'] : "0") . ",
                " . $date . "
            )
            ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                post_id = VALUES(post_id),
                customer_id = VALUES(customer_id),
                author_id = VALUES(author_id),
                name = VALUES(name),
                email = VALUES(email),
                website = VALUES(website),
                comment = VALUES(comment),
                status = VALUES(status),
                date = VALUES(date)";
        $new_db->query($insert_sql) or die("Error inserting into ocus_journal3_blog_comments: " . mysqli_error($new_db));
    }
    echo "Blog comments migrated successfully.\n";

    echo "=== Journal2 to Journal3 Blog Migration Completed ===\n\n";
}

// Execute the blog migration only in full mode
if ($mode !== 'seo' && $mode === 'full') {
    migrate_journal2_to_journal3_blog($old_db, $new_db);
} elseif ($mode !== 'seo') {
    echo "\nSkipping Journal2 to Journal3 blog migration (essential mode)\n";
}

// Function to migrate SEO URLs from OC2 url_alias to OC3 seo_url
function migrate_seo_urls($old_db, $new_db) {
    echo "\n=== Starting SEO URL Migration (OC2 url_alias -> OC3 seo_url) ===\n";

    // Get the default language_id from OC3
    $lang_result = $new_db->query("SELECT language_id FROM ocus_language ORDER BY sort_order LIMIT 1");
    if (!$lang_result || $lang_result->num_rows == 0) {
        echo "ERROR: No language found in OC3 database. Cannot migrate SEO URLs.\n";
        return;
    }
    $lang_row = $lang_result->fetch_assoc();
    $language_id = $lang_row['language_id'];
    echo "Using language_id: $language_id\n";

    // Get the default store_id (0 for default store)
    $store_id = 0;
    echo "Using store_id: $store_id\n";

    // Count existing URLs
    $count_result = $old_db->query("SELECT COUNT(*) as total FROM `" . OLD_DB_DATABASE . "`.ocus_url_alias");
    $count_row = $count_result->fetch_assoc();
    $total_urls = $count_row['total'];
    echo "Found $total_urls URLs to migrate from OC2 url_alias table.\n";

    // Analyze URL types
    echo "\nAnalyzing URL types...\n";
    $type_result = $old_db->query("
        SELECT
            SUBSTRING_INDEX(query, '=', 1) as query_type,
            COUNT(*) as count
        FROM `" . OLD_DB_DATABASE . "`.ocus_url_alias
        GROUP BY query_type
        ORDER BY count DESC
    ");

    echo "URL Distribution:\n";
    while ($type_row = $type_result->fetch_assoc()) {
        echo "  " . $type_row['query_type'] . ": " . $type_row['count'] . " URLs\n";
    }

    // Start migration
    echo "\nStarting URL migration...\n";
    $sql = "SELECT url_alias_id, query, keyword FROM `" . OLD_DB_DATABASE . "`.ocus_url_alias ORDER BY url_alias_id";
    $result = $old_db->query($sql) or die("Error selecting from ocus_url_alias: " . mysqli_error($old_db));

    $migrated = 0;
    $skipped = 0;
    $errors = 0;

    while($row = $result->fetch_assoc()) {
        // Skip empty keywords (typically for common/home)
        if (empty($row['keyword'])) {
            $skipped++;
            continue;
        }

        $query = $new_db->real_escape_string($row['query']);
        $keyword = $new_db->real_escape_string($row['keyword']);

        // Insert into seo_url table with store_id and language_id
        $insert_sql = "INSERT INTO ocus_seo_url
            (store_id, language_id, query, keyword)
            VALUES (
                $store_id,
                $language_id,
                '$query',
                '$keyword'
            )
            ON DUPLICATE KEY UPDATE
                keyword = VALUES(keyword)";

        if ($new_db->query($insert_sql)) {
            $migrated++;
            if ($migrated % 100 == 0) {
                echo "  Migrated $migrated URLs...\n";
            }
        } else {
            $errors++;
            echo "  ERROR migrating URL (query: $query, keyword: $keyword): " . $new_db->error . "\n";
        }
    }

    echo "\n=== SEO URL Migration Summary ===\n";
    echo "Total URLs in OC2: $total_urls\n";
    echo "Successfully migrated: $migrated\n";
    echo "Skipped (empty keywords): $skipped\n";
    echo "Errors: $errors\n";

    // Verify migration
    $verify_result = $new_db->query("SELECT COUNT(*) as total FROM ocus_seo_url");
    $verify_row = $verify_result->fetch_assoc();
    echo "Total URLs in OC3 after migration: " . $verify_row['total'] . "\n";

    echo "=== SEO URL Migration Completed ===\n\n";
}

// Execute the SEO URL migration
// In SEO mode: only run this migration
// In full/essential modes: also run this migration (SEO URLs are essential)
if ($mode === 'seo') {
    echo "\n========================================\n";
    echo "SEO Mode: Running ONLY SEO URL Migration\n";
    echo "========================================\n\n";
    migrate_seo_urls($old_db, $new_db);
} else {
    // Run SEO migration as part of full/essential sync
    migrate_seo_urls($old_db, $new_db);
}

function full_update(){

}

//Close all database connections

    $new_db->close();
    $old_db->close();
