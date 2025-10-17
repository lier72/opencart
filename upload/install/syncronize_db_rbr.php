<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 12/07/24
 * Time: 13:23
 */
// Read current config

define('OLD_DB_DATABASE', 'a1627-unqs-oc');
define('OLD_DB_USERNAME', 'a1627-unqs-oc');
//define('OLD_DB_USERNAME', 'root');
define('OLD_DB_PREFIX', 'ocus_');
define('OLD_DB_PASSWORD','StsZmRVqnWcXF2XA');
//define('OLD_DB_PASSWORD','');
//$drop_tables = true;


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
        'ocus_gcrdev_sitemap',
        'ocus_google_base_category',
        'ocus_google_base_category_to_category',
        'ocus_information',
        'ocus_information_description',
        'ocus_information_to_layout',
        'ocus_information_to_store',
        'ocus_layout',
        'ocus_setting',
        'ocus_url_alias',
        'ocus_weight_class',
        'ocus_weight_class_description',
        'ocus_language',
        'ocus_currency',
        'ocus_modification',
        'ocus_module'
        );

    $result = $old_db->query("SHOW TABLES") or die("Error SHOW TABLES " . mysqli_error($old_db));
    while($row = mysqli_fetch_array($result)){
        array_push($old_tables, $row[0]);
    }
//    print_r( $old_tables);

    $result = $new_db->query("SHOW TABLES") or die("Error SHOW TABLES " . mysqli_error($new_db));
    while($row=mysqli_fetch_array($result)){
    array_push($new_tables, $row[0]);
    }
//    print_r( $new_tables);
// Look if the table has different structure
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
    echo "Time elapsed for banner_image table: " . $time_elapsed_secs;


/*  {"table":"ocus_cart","nf_old_table_fields":[],"nf_new_table_fields":{"1":"api_id"}},*/
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

/*  {"table":"ocus_custom_field","nf_old_table_fields":[],"nf_new_table_fields":{"3":"validation"}},
  {"table":"ocus_customer","nf_old_table_fields":[],"nf_new_table_fields":{"3":"language_id","20":"code"}},*/
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
/*
{"table":"ocus_event","nf_old_table_fields":[],"nf_new_table_fields":{"4":"status","5":"sort_order"}},
*/
//    $sql = "REPLACE INTO ocus_event (`event_id`,`code`, `trigger`, `action`, `status`, `sort_order`) SELECT
//            `event_id`,`code`, `trigger`, `action`, 0, 0 FROM `".OLD_DB_DATABASE."`.ocus_event" ;
//    print_r($sql);
//    $new_db->query($sql) or die("\n Error replacing into ocus_event " . mysqli_error($new_db));

// Absolutely no reson to transfer MODIFIACTIONS from OC2 to OC3
/*  {"table":"ocus_modification","nf_old_table_fields":[],"nf_new_table_fields": {"1":"extension_install_id","10":"extension_download_id"}}]
*/
//$identical_tables = array_slice($identical_tables,0,1);
//$identical_tables = array('ocus_zone');
foreach ($identical_tables as $table){
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
| a1627-unqs-oc3 | ocus_erp_carrier_merge                  |
| a1627-unqs-oc3 | ocus_erp_category_merge                 |
| a1627-unqs-oc3 | ocus_erp_order_status_merge             |
| a1627-unqs-oc3 | ocus_erp_payment_merge                  |
| a1627-unqs-oc3 | ocus_erp_product_option_merge           |
| a1627-unqs-oc3 | ocus_erp_product_option_value_merge     |
| a1627-unqs-oc3 | ocus_erp_product_template_merge         |
| a1627-unqs-oc3 | ocus_erp_product_variant_merge          |
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

function full_update(){

}

//Close all database connections

    $new_db->close();
    $old_db->close();
