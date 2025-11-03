<?php
/**
 *
 * User: Maxim Surdu
 * Date: 07.05.2023
 *
 */

class OpencartOdooStockModel {

    protected $userData = array();
    protected $test_environment = true;
    private $debug = false;
    protected $db;
    public $connection;

    function __construct(){
// Configuration
        if (is_file('config.php')) {
            require_once('config.php');
        }
        $this->db = new mysqli(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE, DB_PORT);
        if ($this->db -> connect_errno) {
            echo "Failed to connect to MySQL: " . $this->db -> connect_error;
            exit();
        } // Getting Odoo connection configuration
        $res = $this->db->query("SELECT * FROM ". DB_PREFIX . "odoo_config");
        while($row = $res->fetch_assoc()){
            $this->userData [$row['key']]=$row['value'];
        } //       print_r($this->userData);
        $this->connection = $this->connectRpc();
        if ($this->userData['url']=="https://portal.uniqsport.ru") $this->test_environment = false;
    }

    function __destruct(){
        $this->db->close();
    }

    /**
     * Connects to odoo rpc using hardwritten config
     * @return array of conenction data
     */
    function connectRpc()
    {
        if (!class_exists('\Ripcord\Ripcord::client'))
            require_once(DIR_SYSTEM . 'library/odoo_connector/vendor/autoload.php');
            // include_once DIR_SYSTEM . "library/ripcord-master/ripcord.php";

        $common = \Ripcord\Ripcord::client($this->userData['url'] . "/xmlrpc/common");
        $uid = $common->authenticate($this->userData['db_name'], $this->userData['user'], $this->userData['password'], array());
        $models = \Ripcord\Ripcord::client($this->userData['url'] . "/xmlrpc/object");


        if ($uid <= 0) {
            return array(
                'status' => False,
            );
        } else {
            return array(
                'status' => True,
                'client' => $models,
                'userId' => $uid,
                'pwd' => $this->userData['password'],
                'url' => $this->userData['url'],
                'port' => $this->userData['port'],
                'db' => $this->userData['db_name'],
            );
        }
    }


    function install(){
        $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."odoo_product_variant_map (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `odoo_product_id` int(11) NOT NULL UNIQUE,
            `opencart_product_id` int(11) NOT NULL,
            `opencart_product_option_id` int(11) NOT NULL,
            `opencart_product_name` varchar(128),
            `opencart_product_option_description` varchar(255),
            `odoo_product_tmpl_id` int(11) NOT NULL,
            `created_by` varchar(128) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) DEFAULT CHARSET=utf8");

    }

    function CreateProductMappingTable($params){
        // I use specific default_code for product matching. So we match product once.
        //  1-st we need to find OpenCart product corresponding to existing Odoo product.product based od default_code
        $nn=0; //iterator for counting not found products
        $newproducts=array(); //Array to store products not found in OpenCart products

        foreach ($params as $item) {
            // First we look if the product has been already mapped so we spare time and electricity
            $ocprodid = $this->getOpencartProduct($item['id']);
            if ($this->debug) { echo "The product exists "; print_r($ocprodid);echo "\n"; }
            if (!$ocprodid['opencart_product_id']) {

                // All odoo products with options have "_" int their "defailt_code" field,
                // so we check OpenCart products for the options if we find the "_" in "default_code" field
                if (strpos($item['default_code'], "_")) { // if product has variants
                    $parse = $this->parseOdooDefaultCode($item['default_code']);
                    if ($this->debug) { print_r($parse); }
                    $model = $parse[0];
                    $option = $parse[1];
                    $sql = "SELECT DISTINCT pov.product_option_value_id, pov.product_id, p.model, pd.name, podv.name,
                        pov.quantity FROM " . DB_PREFIX . "product_option_value
                        AS pov LEFT JOIN " . DB_PREFIX . "option_value_description AS podv
                        ON podv.option_value_id = pov.option_value_id LEFT JOIN " . DB_PREFIX . "product AS p
                        ON p.product_id=pov.product_id LEFT JOIN " . DB_PREFIX . "product_description AS pd
                        ON p.product_id = pd.product_id WHERE p.model = '$model' AND podv.name LIKE '%$option%'";
                    $result = $this->db->query($sql) or die("Error in Selecting product variant" . mysqli_error($this->db));
                } else { // If the product has NO variants
                    $sql = "SELECT p.product_id, p.model, p.quantity, pd.name FROM " . DB_PREFIX . "product AS p LEFT JOIN "
                        . DB_PREFIX . "product_description AS pd ON p.product_id = pd.product_id WHERE model = '"
                        . (string)$item['default_code'] . "'";
                    $result = $this->db->query($sql) or die("Error in Selecting product" . mysqli_error($this->db));;
                }
                if ($result) {
                    $prodid = mysqli_fetch_assoc($result);

                    if ($this->debug) {
                        print_r($sql);
                        echo "\n";
                        echo "Result : ";
                        print_r($result);
                        echo "\n";
                        echo "Product id: ";
                        print_r($prodid);
                        echo "\n";
                        print_r($item['product_tmpl_id'][0]);
                    }

                    if (isset($prodid['product_id'])) { // This means that we have found Opencart product based on model/option
                        if (!isset($prodid['product_option_value_id'])) $prodid['product_option_value_id'] = -1;
                        $sql = "INSERT INTO " . DB_PREFIX . "odoo_product_variant_map SET odoo_product_id = " . $item['id'] .
                            ", opencart_product_id = " . $prodid['product_id'] .
                            ", opencart_product_option_id =" . $prodid['product_option_value_id'] .
                            ", opencart_product_name ='" . $prodid['model'] .
                            "', opencart_product_option_description= '" . $prodid['name'] .
                            "', odoo_product_tmpl_id = '" . $item['product_tmpl_id'][0] .
                            "', created_by ='oc_odoo_sync', created_on = NOW(), is_synch = 0 ON DUPLICATE KEY UPDATE " .
                            "opencart_product_id = '" . $prodid['product_id'] .
                            "', opencart_product_option_id ='" . $prodid['product_option_value_id'] .
                            "', opencart_product_name = '" . $prodid['model'] .
                            "', opencart_product_option_description= '" . $prodid['name'] .
                            "', odoo_product_tmpl_id = '" . $item['product_tmpl_id'][0] .
                            "', created_by ='oc_odoo_sync', is_synch = 0";
//                print_r($sql); echo "\n";
                        $result = $this->db->query($sql) or die("Error in Adding map product " . mysqli_error($this->db));
                    } else {
                        $newproducts [$nn]['odoo_product_id'] = $item['id'];
                        $newproducts [$nn]["model"] = $item['default_code'];
                        $nn++;
                    }
                }
            }
        }
        echo "There are ". ($nn) ." products not found in OpenCart.\n";
        file_put_contents("new_products.json",json_encode($newproducts));
//        print_r($newproducts);
    }

    function getOdooProduct($item)
    {
        $sql = "SELECT odoo_product_tmpl_id  FROM " . DB_PREFIX . "odoo_product_variant_map
                WHERE opencart_product_id = " . (int)$item . ";";
        $result = $this->db->query($sql);
        return mysqli_fetch_assoc($result);
    }

    function getOpencartProduct($item){
        $sql = "SELECT opencart_product_id, opencart_product_option_id FROM " . DB_PREFIX . "odoo_product_variant_map
                WHERE odoo_product_id = " . (int)$item . ";";
        $result = $this->db->query($sql);
        return mysqli_fetch_assoc($result);
    }

    function UpdateOpenCartProductQty ($params){
//        $status = 5;
        $debug_array=array(); //array(3329,3326,3325,3327,3328); array(6839,6853,6860,6861);
        $total_odoo_qty = 0;
        $total_opencart_qty = 0;
        foreach ($params as $item) {
            if (in_array($item['id'],$debug_array)) {$this->debug=true;}
            else{$this->debug=false;}
            $total_odoo_qty += $item['qty_available'] - $item['outgoing_qty'];

            $sql = "SELECT opencart_product_id, opencart_product_option_id FROM " . DB_PREFIX . "odoo_product_variant_map
            WHERE odoo_product_id = " . (int)$item['id'] . ";";
            $result = $this->db->query($sql);
            $update_product_variant = mysqli_fetch_assoc($result);
            if($this->debug){ print_r($update_product_variant); echo "\n";}
            if ($update_product_variant['opencart_product_id']) { //there would be an empty array if no corresponding prod found
                if ($update_product_variant['opencart_product_option_id'] == -1) { // The product has no options
                    $sql = "UPDATE " . DB_PREFIX . "product SET quantity =" . (int)($item['qty_available'] - $item['outgoing_qty']).
                        " WHERE product_id =" . (int)$update_product_variant['opencart_product_id'] . ";";
          if($this->debug) {
              echo $update_product_variant["opencart_product_id"] . " -> ";
              echo $item["id"] . "\n";
              echo "Quantity =" . $item['qty_available'] . " - " . $item['outgoing_qty'] . "\n";
              print_r($sql);
              echo "\n";
          }
                    $result=$this->db->query($sql) or die("Error in modifying product quantity" . mysqli_error($this->db));
                    if( $this->debug ) { echo "Result -> "; print_r (serialize($result)); echo "\n";}
                    $total_opencart_qty += (int)($item['qty_available'] - $item['outgoing_qty']);
                } else {
                    $sql = "UPDATE ".DB_PREFIX."product_option_value SET quantity =".(int)($item['qty_available'] - $item['outgoing_qty']).
                        " WHERE product_option_value_id =".$update_product_variant['opencart_product_option_id'].";";
             if($this->debug) { print_r($sql); echo "\n"; }
                    $total_opencart_qty += (int)($item['qty_available'] - $item['outgoing_qty']);
                    $result=$this->db->query($sql) or die("Error in modifying product variant quantity" . mysqli_error($this->db));
             if( $this->debug ) { echo "Result -> "; print_r (serialize($result)); echo "\n";}
                }
            }
        }
        $txt = sprintf("There are %s pieces of products available in Odoo and %s pieces of products were stored in Opencart\n", $total_odoo_qty, $total_opencart_qty);
        echo $txt;
        //    $product_id = $params['default_code'];
//    $quantity = $params['virtual_available'];
//    if ($quantity > 0) {
//        $status = 7;

//  WHEN someone updates option for rackets and do not set them to be NOT SUBSTRACT - YOU will get 0 quantity as a result
        // SO be very careful when updating rackets with stringing option!

        $sql = "UPDATE ".DB_PREFIX."product AS p, (SELECT product_id, sum(quantity) AS varsum, sum(subtract) AS subsum
                   FROM ".DB_PREFIX."product_option_value GROUP BY product_id) as s
                   SET p.quantity = s.varsum WHERE p.product_id = s.product_id AND s.subsum > 0";
//        echo "Summariaze quantity -> "; print_r ($sql); echo "\n";
            //}
        $result=$this->db->query($sql) or die("Error in modifying product sum quantity" . mysqli_error($this->db));
    }

     function parseOdooDefaultCode($data){
        $parts = explode("_",$data);
         if (is_numeric($parts[1])){
             if($parts[1]>100){ // Kids sizes 120,130..160
                 $option = $parts[1];
             }
             else{//Numeric less 100 that must be a shoes size Kids inclusive 1,2,..13
                 $option = 'us('.$parts[1].')';
             }
         }
         else { // This is when it is not numeric
             if ($this->debug) {
                 echo "Parsing default_code :"; print_r($parts); echo "\n";
             }
             if (strpos($parts[1],',')){ //Sizes with halves i.e 4,5 .. 11,5
                 $option = 'us('.$parts[1].')';
             } else if (strpos($parts[1],'C')){//Sizes with 'C' must be baby 7C, 8C, .. 12TC
                 $option = 'us('.$parts[1].')';
                }
                else { //Otherwise must be an apparel size i.e XS,S,..4XL
                     $option = 'Asia ('.$parts[1].')';
                }
         }
         $parts[1]=$option;
         return $parts;
     }


}

// Configuration
    if (is_file('config.php')) {
    require_once('config.php');
    }

    $oc_odoo = new OpencartOdooStockModel();

    ob_start();

    if (!file_exists("array.json")
        or (time() - filemtime("array.json"))>900
    )
    {
        $odoo_connect = $oc_odoo->connection;
        if ($odoo_connect['status']) {
            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];

            $ids = $models->execute_kw($db_name, $uid, $password,
                'product.product', 'search_read',
                array(array(array('active', '=', true),
                    array('sale_ok', '=', true))),
                array('fields' => array('qty_available', 'default_code', 'outgoing_qty', 'virtual_available', 'product_tmpl_id')));
            file_put_contents("array.json", json_encode($ids));
        }
    }
    else{
        echo "file exists!\n";
        $ids = json_decode(file_get_contents('array.json'), true);
    }


    $oc_odoo = new OpencartOdooStockModel();
// Create tables needed for mapping table
//    $oc_odoo->install();
// Create/update mapping tables
    $oc_odoo->CreateProductMappingTable($ids);

    $oc_odoo->UpdateOpenCartProductQty ($ids);
    $output = ob_get_clean();
    echo json_encode([
        'status' => 'success',
        'output' => $output
    ]);
//
//    $oc_odoo->exportProduct(9018);
    unset($oc_odoo);
//    echo "the script has ended\n";
    exit;
