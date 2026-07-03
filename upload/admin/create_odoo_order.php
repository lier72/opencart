<?php

/**
 *
 * User: Maxim Surdu
 * Date: 07.06.2023
 * Time: 11:06
 */
class ExportOpenCartOdoo
{
    /**
     * ExportOpenCartOdoo constructor.
     * @throws Exception
     */
    protected $userData = array();
    protected $test_environment = true;
    protected $db; // Add this line
    protected $connection; // Also add this since it's used but not declared

    function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "odoo_config (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `key` varchar(255) NOT NULL,
            `value` varchar(255),
            PRIMARY KEY (`id`)
          ) DEFAULT CHARSET=utf8");

    }

    static function isUser()
    {
        /*       if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true) {
                   echo "Welcome to the member's area, " . htmlspecialchars($_SESSION['username']) . "!";
                   return true;
               } else {
                   echo "Please log in first to see this page.";
                   return false;
               }*/
        print_r("Session : " . $_SESSION);
    }

    function __construct()
    {
// Configuration
        if (is_file('config.php')) {
            require_once('config.php');
        }
        $this->db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
        if ($this->db->connect_errno) {
            throw new Exception ("Failed to connect to MySQL: " . $this->db->connect_error);
        }
        // Getting Odoo connection configuration
        $res = $this->db->query("SELECT * FROM " . DB_PREFIX . "odoo_config");
        while ($row = $res->fetch_assoc()) {
            $this->userData[$row['key']] = $row['value'];
        }
//       print_r($this->userData);
        $this->connection = $this->connectRpc();
        if ($this->userData['url'] == "https://portal.uniqsport.ru") $this->test_environment = false;
    }

    function __destruct()
    {
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

    /**
     * Defines odoo price list it
     * @param $oc_customer_group
     * @return array Odoo Price list ID
     */
    function getOdooPricelistId($oc_customer_group)
    {
        // We have different  pricelists in testing and deployed databases
        $odoo_pricelist = array();
        switch ($oc_customer_group) {
            case 1: //Default = RSP
                $odoo_pricelist["price_id"] = ($this->test_environment) ? 1 : 2794;// '__export__.product_pricelist_2794_eb98f495'
                $odoo_pricelist["team_id"] = ($this->test_environment) ? 1 : 4;// Розницв
                break;
            case 8: //Disc-5% = RSP-5%
                $odoo_pricelist ["price_id"] = ($this->test_environment) ? 1 : 2796;//'__export__.product_pricelist_2796_9e80ded2';
                $odoo_pricelist["team_id"] = ($this->test_environment) ? 1 : 4;// Розницв
                break;
            case 2: // Sportsmen
                $odoo_pricelist ["price_id"] = ($this->test_environment) ? 4 : 4; //'__export__.product_pricelist_4';
                $odoo_pricelist["team_id"] = ($this->test_environment) ? 1 : 1;// Прямые
                break;
            case 3: // Dealer_D0
                $odoo_pricelist ["price_id"] = ($this->test_environment) ? 2 : 2791;//'__export__.product_pricelist_2791_47d3857e';
                $odoo_pricelist["team_id"] = ($this->test_environment) ? 1 : 1;// Прямые
                break;
            case 6: // Friends-15 4real RSP - 10%
                $odoo_pricelist ["price_id"] = ($this->test_environment) ? 2787 : 2787;//'__export__.product_pricelist_2787';
                $odoo_pricelist["team_id"] = ($this->test_environment) ? 1 : 4;// Розницв
                break;
            case 7: // Dealer D010
                $odoo_pricelist ["price_id"] = ($this->test_environment) ? 2788 : 2792; //'__export__.product_pricelist_2792_6838b656';
                $odoo_pricelist["team_id"] = ($this->test_environment) ? 1 : 1;// Прямые
                break;
        }
        return $odoo_pricelist;
    }


    /**
     * @param bool $test_environment
     * @return ExportOpenCartOdoo
     */
    public function setTestEnvironment(bool $test_environment)
    {
        $this->test_environment = $test_environment;
        return $this;
    }


}

class ExportOpenCartOdooOrder extends ExportOpenCartOdoo
{
    private $oc; //instantiation of ExportOpenCartOdooCistomer class

    function __construct(ExportOpenCartOdooCustomer $oc)
    {
        parent::__construct();
        $this->oc = $oc;
    }

    public function install()
    {
        parent::install();

        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "odoo_order_map (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `opencart_order_id` int(11) NOT NULL,
            `odoo_order_id` int(11) NOT NULL UNIQUE,
            `opencart_order_state` varchar(255) NOT NULL,
            `odoo_order_state` varchar(128),
            `created_by` varchar(128) NOT NULL,
            `modified_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_sync` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
            )DEFAULT CHARSET=utf8");
    }


    /**
     * Creates new Sales Order in odoo based on Open Cart order data
     * it checks if the opencart client is mapped in odoo_client_map mapping table
     * if it is new client it adds new client to odoo
     * @param $oc_order_id
     * @throws Exception
     */
    function createOdooOrder($oc_order_id)
    {
        // Proceed  only of the order is not already mapped
        if ($this->checkOdooOrder($oc_order_id)) return;
        $odoo_connect = $this->connection;
        if ($odoo_connect['status']) {
            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];

            // Check the clinet information from order data
            $sql = "SELECT customer_id, firstname,lastname, email, telephone, customer_group_id, payment_zone_id, payment_city, 
                payment_postcode, payment_country_id, payment_address_1, payment_address_2, comment, total, order_status_id 
                FROM " . DB_PREFIX . "order 
                WHERE order_id =" . $oc_order_id;
            $result = $this->db->query($sql) or die("Error in Selecting order " . mysqli_error($this->db));

            if ($result->num_rows) {
                $customer_data = mysqli_fetch_assoc($result);
                $customer_data ['opencart_order_id'] = $oc_order_id;
                $clean = preg_replace('/\s*/', '', $customer_data['email']);
                $customer_data['email'] = strtolower($clean);

                // Check if the partner exists in mapping table
                $odoo_partner = $this->oc->checkClient($customer_data['email']);
                if (!$odoo_partner) {
                    // Create new partner in Odoo
                    $odoo_partner = $this->oc->createOdooPartner($customer_data);
                    if (!$odoo_partner) {
                        throw new Exception ("Failed to create new partner! Check connection etc.\n");
                    }
                }

                $odoo_pricelist_id = $this->getOdooPricelistId($customer_data['customer_group_id']);
                $order_data['note'] = 'Создан из заказа в ИМ Uniqsport.ru ' . $oc_order_id;
                // Create new empty order only if it does not exists otherwise assign odoo_order_id
                // from mapping table
                $odoo_order_id = $models->execute_kw($db_name, $uid, $password,
                    'sale.order', 'create',
                    array(array(//'create_uid' => 43, //Administrator
                        'partner_id' => (int)$odoo_partner,
                        'origin' => $oc_order_id,
                        'team_id' => $odoo_pricelist_id['team_id'],
                        'pricelist_id' => $odoo_pricelist_id['price_id']))
                );
                if ($odoo_order_id['faultCode']) {
                    print_r(array(array(//'create_uid' => 43, //Administrator
                            'partner_id' => (int)$odoo_partner,
                            'origin' => $oc_order_id,
                            'team_id' => $odoo_pricelist_id['team_id'],
                            'pricelist_id' => $odoo_pricelist_id['price_id']))
                    );
                    throw new Exception ($odoo_order_id['faultString']);
                }//$odoo_order_id if rpc call was successful contains the odoo's (int) ID of created sale.order

                $this->mapOdooOrder($odoo_order_id, $customer_data);

                $sql = "SELECT product_id, name, order_product_id, quantity, price FROM " . DB_PREFIX . "order_product WHERE order_id =" . $oc_order_id;
                $result = $this->db->query($sql) or die("Error in Selecting products from order. " . mysqli_error($this->db));
                if ($result->num_rows) {
                    // output data of each row
                    while ($row = $result->fetch_assoc()) {
                        // Select option for the product
                        $sql = "SELECT a.product_option_value_id, a.name, a.value, a.order_id, b.required FROM " . DB_PREFIX .
                            "order_option AS a LEFT JOIN " . DB_PREFIX . "product_option AS b ON a.product_option_id=b.product_option_id WHERE a.order_id =" .
                            $oc_order_id . " AND a.order_product_id =" . $row['order_product_id'];
                        $option = $this->db->query($sql) or die("Error in Selecting product option from order. " . mysqli_error($this->db));
                        // if product option exists modify SQL query to pick odoo product_id based on this particular option
                        if ($option->num_rows > 0) {
                            $oc_option = $option->fetch_assoc();
//                            print_r($oc_option); echo "\n";
                            // If the option is not required then product_variant_option_id to -1 so we can use the same SQL query
                            // we write the option  to the sale.order.line comment after the product line.
                            if ($oc_option['required'] == 0) $oc_option['product_option_value_id'] = -1;
                            $sql = "SELECT odoo_product_id  FROM " . DB_PREFIX . "odoo_product_variant_map WHERE 
                        opencart_product_id = " . $row['product_id'] . " AND opencart_product_option_id = " . $oc_option['product_option_value_id'];
                        } else {
                            $sql = "SELECT odoo_product_id  FROM " . DB_PREFIX . "odoo_product_variant_map WHERE opencart_product_id = " . (int)$row['product_id'];
                        }
                        $res = $this->db->query($sql) or die("Error in Selecting specific product from order. " . mysqli_error($this->db));
                        print_r($row);
                        if ($res->num_rows > 0) { // product exists in the Mapping Table
                            $odoo_product = $res->fetch_row();
                            // Write Odoo order.product.line
                            $response = $models->execute_kw($db_name, $uid, $password,
                                'sale.order.line', 'create',
                                array(array(
//                                'create_uid' => 43, //Administrator
                                    'order_id' => (int)$odoo_order_id,
                                    'name' => $row['name'],
                                    'product_id' => (int)$odoo_product[0],
                                    'order_partner_id' => (int)$odoo_partner,
                                    'product_uom_qty' => (int)$row['quantity'],
                                    'price_unit' => (float)$row['price']
                                ))
                            );
                            if ($response['faultCode']) {
                                print_r($response['faultString']);
                            }
                            // Write the comment in order.product.line about NOT required option after the product
                            if ($option->num_rows > 0 && $oc_option['product_option_value_id'] == -1) {
                                $response = $models->execute_kw($db_name, $uid, $password,
                                    'sale.order.line', 'create',
                                    array(array(
                                        "display_type" => 'line_note',
//                                    'create_uid' => 43, //Administrator
                                        'order_id' => (int)$odoo_order_id,
                                        'order_partner_id' => (int)$odoo_partner,
                                        'name' => $oc_option['name'] . " " . $oc_option['value'],
                                    ))
                                );
                                if ($response['faultCode']) {
                                    print_r($response['faultString']);
                                }
                            }
                        } else { // The product is not in mapping table
                            $order_data['note'] = $order_data['note'] . "\n" . 'НЕ НАЙДЕН Продукт: ' .
                                $row['name'] . ' Количество: ' . $row['quantity'] . ' Цена: ' . $row['price'];
                        }
                    }
                } else {
                    throw new Exception ("The order " . $oc_order_id . " seems to be an empty order");
                }


                // Get delivery and other order options influencing to total amount
                $order_delivery = false;
                $sql = "SELECT code, title, value FROM " . DB_PREFIX . "order_total WHERE order_id =" . $oc_order_id;
                $result = $this->db->query($sql) or die("Error in Selecting order " . mysqli_error($this->db));
                if ($result->num_rows) {
                    while ($total_data = $result->fetch_assoc()) {
                        switch ($total_data['code']) {
                            // In case of free delivery the following condition does not work!
                            /*                        case 'sub_total': // If order total equals to subtottal that means no other options were selected
                                                        if ($total_data['value'] == $customer_data['total']) $order_delivery = false;
                                                        break;*/
                            case 'shipping':
                                if ($total_data['title'] == 'Самовывоз из магазина') $order_delivery = false; // Нет доставки
                                else {
                                    $order_delivery = true;
                                    $delivery['product_id'] = 2; // id=2 Доставка СДЭК в odoo
                                    $delivery['price_unit'] = round($total_data['value']);
                                }
                                break;
                            case 'cod_cdek_total':
                                $cod['product_id'] = 3892; // id=3892 - Product variant ! Оплата при доставке СДЭК в odoo
                                $cod['price_unit'] = round($total_data['value']);
                                break;
                        }
                    }
                }

                // Add delivery options if delivery is ordered
                if ($order_delivery) {
                    $response = $models->execute_kw($db_name, $uid, $password,
                        'sale.order.line', 'create',
                        array(array(
                            //                      'create_uid' => 43, //Administrator
                            'order_id' => (int)$odoo_order_id,
                            'product_id' => (int)$delivery['product_id'],
                            'order_partner_id' => (int)$odoo_partner,
                            'product_uom_qty' => 1,
                            'price_unit' => (float)$delivery['price_unit'],
                            'purchase_price' => (float)$delivery['price_unit']
                        ))
                    );
                    if ($response['faultCode']) {
                        print_r($response['faultString']);
                    }
                    if (isset($cod['product_id'])) {
                        $response = $models->execute_kw($db_name, $uid, $password,
                            'sale.order.line', 'create',
                            array(array(
//                            'create_uid' => 43, //Administrator
                                'order_id' => (int)$odoo_order_id,
                                'product_id' => (int)$cod['product_id'],
                                'order_partner_id' => (int)$odoo_partner,
                                'product_uom_qty' => 1,
                                'price_unit' => (float)$cod['price_unit'],
                                'purchase_price' => (float)$cod['price_unit']
                            ))
                        );
                        if ($response['faultCode']) {
                            print_r($response['faultString']);
                        }
                    }
                }
            }
            // Write comment that should consist of OpenCrat order number and
            // the products that were not found in odoo_product_variant map
            if (isset ($odoo_order_id)) {
                $response = $models->execute_kw($db_name, $uid, $password,
                    'sale.order', 'write',
                    array(array($odoo_order_id), array(
                        'note' => $order_data['note'] . "\n" . $customer_data['comment'],
                    ))
                );

                if ($response['faultCode']) {
                    print_r($response['faultString']);
                }
            }

        }
    }

    /**
     * Check if the Client exists in the Client mapping table
     * @param $oc_order_id
     * @return bool
     */
    public function checkOdooOrder($oc_order_id)
    {
        $sql = "SELECT `odoo_order_id` FROM " . DB_PREFIX . "odoo_order_map WHERE opencart_order_id = " . $oc_order_id;
        $result = $this->db->query($sql) or die("Error in Selecting customer " . mysqli_error($this->db));
        if ($result->num_rows) {
            $odoo_order = mysqli_fetch_assoc($result);
            return $odoo_order['odoo_order_id'];
        }
        return false;
    }

    /**
     * mapOdoo order takes id of sale.order model from Odoo and fills new record in
     * odoo_order_map database. Returns true if successful.
     * THIS function WILL add new mapping client even if there is a same opencart client
     * because odoo_client_id will be different every time createOdooPartner is called
     * @param $odoo_order
     * @param $oc_order
     * @return bool
     */
    function mapOdooOrder($odoo_order, $oc_order)
    {
        // Add new mapping data if the odoo_order is NOT found in mapping table
        $sql = "INSERT INTO " . DB_PREFIX . "odoo_order_map SET opencart_order_id = " . $oc_order['opencart_order_id'] .
            ", odoo_order_id = '" . $odoo_order .
            "', opencart_order_state = " . $oc_order['order_status_id'] .
            ", odoo_order_state ='draft', 
            created_by ='oc_odoo_sync', modified_on = NOW(), is_sync = 0 ON DUPLICATE KEY UPDATE
             opencart_order_id = " . $oc_order['opencart_order_id'] .
            ", odoo_order_id = '" . $odoo_order .
            "', opencart_order_state = " . $oc_order['order_status_id'] .
            ", created_by ='oc_odoo_sync', modified_on = NOW() , is_sync = 0";
        $res = $this->db->query($sql) or die("Error in adding map order! \n" . mysqli_error($this->db));
//        }
        return true;
    }

    /**
     * DO some logic to synchronise Odoo and Opencart order state
     * 1st idea is to get orders from odoo_order_map that are in state 'is_sync = 0'
     * then get these orders states from Odoo
     * Compare each odoo state to Opencart state
     * of all invoices from Odoo order are paid then change opencart order status
     * update opencart order_history etc.
     */
    /**
     * +-----------------+-------------+--------------------------------------------------------------+
     * | order_status_id | language_id | name                                                         |
     * +-----------------+-------------+--------------------------------------------------------------+
     * |               1 |           1 | Сборка заказа                                                |
     * |               2 |           1 | Передан в доставку (СДЭк)                                    |
     * |               3 |           1 | Доставлено                                                   |
     * |               5 |           1 | Сделка завершена                                             |
     * |               8 |           1 | Частичный возврат                                            |
     * |               9 |           1 | Заказ отменен                                                |
     * |              10 |           1 | Не доставленный заказ                                        |
     * |              12 |           1 | Заказ изменен (нами)                                         |
     * |              13 |           1 | Заказ полностью возврщен                                     |
     * |              14 |           1 | Оплачено картой                                              |
     * |              15 |           1 | Оплачено на счет ИП                                          |
     * |              16 |           1 | Будет оплачено при доставке                                  |
     * |              17 |           1 | Ожидание оплаты                                              |
     * |              18 |           1 | Ожидание в Пункте Выдачи Заказов                             |
     * |              19 |           1 | Передан в доставку (Данченко)                                |
     * |              20 |           1 | Передан в доставку (Сурду)                                   |
     * |              21 |           1 | Заказ к возврату                                             |
     * |              22 |           1 | Оплачено наличными                                           |
     * |              23 |           1 | Оплачено на Сбер                                             |
     * |              24 |           1 | Передан в доставку (Ольга)                                   |
     * |              25 |           1 | Передан в доставку (Мякишев)                                 |
     * +-----------------+-------------+--------------------------------------------------------------+
     */

    function syncOpenCartOrderState($debug = true)
    {

//        $initial_statuses = array(12, 16, 17);
//        $in_delivery_statuses = array(8, 19, 20, 21, 24, 25);
//        $paid_statuses = array(14, 15, 22, 23);
//        $delivered_statuses = array(3);
//        $done_statuses = array(5);

        $odoo_ids = array();

        $oc_cdek_delivery_statuses = array(1, 2, 10, 18);
        $oc_done = 5; // Сделка завершена
        $oc_payed = 15; // Оплачено на счет ИП
        $oc_cancelled = 9; // Заказ отменен
        $cancel_statuses = array(9, 13);

        // Form odoo order ids to update statuses
        $sql = "SELECT opencart_order_id, odoo_order_id, opencart_order_state, odoo_order_state, is_sync FROM " . DB_PREFIX .
            "odoo_order_map WHERE is_sync = 0";
        $res = $this->db->query($sql) or die("Error selecting order ids from order_map! \n" . mysqli_error($this->db));
        // output data of each row
        while ($mapping_order_id = $res->fetch_assoc()) {
            array_push($odoo_ids, (int)$mapping_order_id['odoo_order_id']);
        }

        $odoo_connect = $this->connection;

        // Request order statuses of Odoo in $oodoo_ids
        if ($odoo_connect['status']) {
            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];
            $odoo_order_ids = $models->execute_kw($db_name, $uid, $password,
                'sale.order', 'read',
                array($odoo_ids), // take ids form SQL request to get only those odoo_order_ids that have not sync statuses
                array('fields' => array('state', 'message_partner_ids', 'invoice_status', 'delivery_count', 'invoice_ids', 'picking_ids')
                )
            );
            if (isset($odoo_order_ids['faultCode'])) {
                throw new Exception ($odoo_order_ids['faultString']);
            }

            // Iterate through odoo order IDs
            foreach ($odoo_order_ids as $odoo_order_id) {
                //todo  Rewrite this Function in Obejct Oriented method like Polymorfism

                // Select mapping order state with the last opencart order state from opencart order history
                // So we check if we need to update either order status if there was change
                $sql = "SELECT a.opencart_order_id AS opencart_order_id, a.odoo_order_state AS odoo_order_state, a.opencart_order_state 
                        AS opencart_order_state, DATE(a.modified_on) < DATE(NOW()) AS is_old, b.order_status_id AS oc_order_status_history_id, b.comment AS `comment`, c.name AS `name` FROM " . DB_PREFIX . "odoo_order_map AS a 
                        LEFT JOIN " . DB_PREFIX . "order_history AS b ON a.opencart_order_id=b.order_id 
                        LEFT JOIN " . DB_PREFIX . "order_status AS c ON b.order_status_id = c.order_status_id 
                        WHERE a.odoo_order_id =" . $odoo_order_id['id'] . " ORDER BY b.date_added DESC LIMIT 1";
                $res = $this->db->query($sql) or die("Error selecting order ids from order_map! \n" . mysqli_error($this->db));
                $mapping_order_id = $res->fetch_assoc();
                // oc_order_id,
                // odoo_order_state,
                // oc_order_status_id,
                // is_old
                // oc_order_status_history_id,
                // comment,
                // name
                $opencart_order_id = $mapping_order_id['opencart_order_id'];

                // Get OpenCart status from `order` Table to check against mappiing table order IDs
                $sql = "SELECT order_status_id FROM " . DB_PREFIX . "order WHERE order_id =" . $opencart_order_id;
                $res = $this->db->query($sql) or die("Error selecting order sate from ocus_order! \n" . mysqli_error($this->db));
                $opencart_order = $res->fetch_assoc();
                $oc_order_state = $opencart_order['order_status_id'];

                if ($debug){
                    echo "<p> OC order id <b>"; print_r($opencart_order_id); echo "</b>, ";
                    echo " OC order status_id: "; print_r($oc_order_state); echo ", ";
                    echo " Odoo order data: "; print_r($odoo_order_id); echo ",<br/> ";
                }

                // IF Opencart order is not CANCELLED

                if (!in_array($oc_order_state, $cancel_statuses)) {
                    // Odoo Order status is  CANCELLED
                    if ($odoo_order_id['state']=='cancel'){
                        if ($debug) {
                            echo "Odoo Order is CANCELLED<br/>";
                        }
                        $this->update_odoo_order_map($opencart_order_id,
                            $mapping_order_id['odoo_order_state'], "cancel",
                            $mapping_order_id['opencart_order_state'],strval($oc_cancelled). ", is_sync=1", $debug);
                        $this->update_opencart_order_history($opencart_order_id,$oc_cancelled,$debug);
                    } else {
                        $odoo_invoice_status = $this->get_odoo_invoice_status($odoo_order_id);
                        $odoo_delivery_status = $this->get_odoo_delivery_status($odoo_order_id);
                        if ($debug) {
                            echo "Odoo Invoice: <b>";
                            print_r($odoo_invoice_status);
                            echo "</b>, ";
                            echo "Odoo Delivery: <b>";
                            print_r($odoo_delivery_status);
                            echo "</b>, <br/>";
                        }
                        // We update OpenCart status based on Odoo status if OpenCart status is in manual delivery or Delivered
                        if (!in_array($oc_order_state, $oc_cdek_delivery_statuses)) {
                            if ($debug) {
                                echo " OpenCart Payment: <b>";
                                print_r($this->get_oc_paid_status($opencart_order_id));
                                echo "</b>, ";
                                echo " OpenCart Delivery: <b>";
                                print_r($this->get_oc_delivery_satus($opencart_order_id));
                                echo "</b>, <br/>";
                            }
                            if ($this->get_oc_paid_status($opencart_order_id)) {// Check if order is paid in Opencart

                                // OpenCart Order is (NOT in CDEK DELIVERY) and PAYED
                                if ($debug) {
                                    echo "OpenCart Order is (NOT in CDEK DELIVERY) and PAYED<br/>";
                                }

                                if ($odoo_invoice_status == 'paid') {
                                    if ($odoo_delivery_status == 'done') {
                                        if ($debug) {
                                            echo "Odoo Order is DELIVERED and PAYED<br/>";
                                        }
                                        //Check if we need to update order_map_satus
                                        if ($mapping_order_id['odoo_order_state'] != 'done' || $mapping_order_id['opencart_order_state'] != $oc_order_state)
                                            // The order should be done in mapping and is_sync set to 1
                                            $this->update_odoo_order_map($opencart_order_id,
                                                $mapping_order_id['odoo_order_state'], "done",
                                                $mapping_order_id['opencart_order_state'], strval($oc_done) . ", is_sync=1", $debug);
                                        $this->update_opencart_order_history($opencart_order_id, $oc_done, $debug);
                                    } else {
                                        if ($debug) {
                                            echo "Odoo Order is NOT DELIVERED but PAYED<br/>";
                                        }
                                        $this->update_odoo_order_map($opencart_order_id,
                                            $mapping_order_id['odoo_order_state'], $odoo_order_id['state'] . '+' . $odoo_invoice_status . '+' . $odoo_delivery_status,
                                            $mapping_order_id['opencart_order_state'], $oc_order_state, $debug);
                                    }
                                } elseif ($odoo_invoice_status != 'paid') { // The order is paid, (delivered or in_delivery) in Opencart but has no paid status in Odoo
                                    if ($debug) {
                                        echo "Odoo Order is NOT PAYED<br/>";
                                    }
                                    // Update order_map_satus
                                    if ($this->update_odoo_order_map($opencart_order_id,
                                        $mapping_order_id['odoo_order_state'], $odoo_order_id['state'].'+'
                                        .$odoo_invoice_status.'+'.$odoo_delivery_status,
                                        $mapping_order_id['opencart_order_state'], $oc_order_state, $debug))
                                        // Update order_map_satus
                                        $this->write_chatter_note($odoo_order_id, "Этот заказ значится : <b>" . $mapping_order_id['name'] .
                                            "</b> Проверьте требуемые изменения!");
                                } elseif ($odoo_delivery_status != 'done') {// The order has paid and delivered in Opencart but has no delivered  status in Odoo
                                    if ($debug) {
                                        echo "Odoo Order is NOT DELIVERED<br/>";
                                    }
                                    if ($this->update_odoo_order_map($opencart_order_id,
                                        $mapping_order_id['odoo_order_state'], $odoo_order_id['state'].'+'
                                        .$odoo_invoice_status.'+'.$odoo_delivery_status,
                                        $mapping_order_id['opencart_order_state'], $oc_order_state, $debug))
                                        // Update order_map_satus
                                        $this->write_chatter_note($odoo_order_id, "Этот заказ значится : <b>" . $mapping_order_id['name'] .
                                            "</b> Проверьте требуемые изменения!");
                                }
                            } else {

                                // OpenCart Order is (NOT in CDEK DELIVERY) and NOT PAYED
                                if ($debug) {
                                    echo "OpenCart Order is (NOT in CDEK DELIVERY) and NOT PAYED <br/>";
                                }
                                if ($odoo_invoice_status == 'paid' && $odoo_delivery_status == 'done') {
                                    if ($debug) {
                                        echo "Odoo Order is PAYED and DELIVERED <br/>";
                                    }
                                    // The order should be done in mapping and is_sync set to 1
                                    // We do not need to check order mapping state as we are going to change opencart_order_state
                                    $this->update_odoo_order_map($opencart_order_id,
                                        $mapping_order_id['odoo_order_state'], "done",
                                        $mapping_order_id['opencart_order_state'], strval($oc_done) . ", is_sync=1", $debug);
                                    // Update oc_order_history first write the payment then write DONE and update order state to done
                                    $this->update_opencart_order_history($opencart_order_id, $oc_payed, $debug); sleep(1);
                                    $this->update_opencart_order_history($opencart_order_id, $oc_done, $debug);
                                } elseif ($odoo_delivery_status != 'done') {
                                    if ($odoo_invoice_status == 'paid') {

                                        // OpenCart Order is (NOT in CDEK DELIVERY) and NOT PAYED
                                        // Odoo Order is PAYED but not DELIVERED

                                        if ($debug) {
                                            echo "Odoo Order is PAYED but not DELIVERED <br/>";
                                        }
                                        //Check if we need to update order_map_satus
                                        if ($this->update_odoo_order_map($opencart_order_id,
                                            $mapping_order_id['odoo_order_state'], $odoo_order_id['state'] . '+' . $odoo_invoice_status . '+' . $odoo_delivery_status,
                                            $mapping_order_id['opencart_order_state'], $oc_order_state, $debug)) {
                                            $this->write_chatter_note($odoo_order_id, "Этот заказ значится : <b>" . $mapping_order_id['name'] .
                                                "</b> Проверьте требуемые изменения!");
                                            // Update oc_order_history and order set status - payed
                                            $this->update_opencart_order_history($opencart_order_id, $oc_payed, $debug);
                                        }
                                    } else {

                                        // OpenCart Order is (NOT in CDEK DELIVERY) and NOT PAYED
                                        // Odoo Order is neither PAYED nor DELIVERED
                                        if ($debug) {
                                            echo "Odoo Order is NEITHER PAYED NOR DELIVERED<br/>";
                                        }

                                        if ($this->update_odoo_order_map($opencart_order_id,
                                            $mapping_order_id['odoo_order_state'], $odoo_order_id['state'] . '+' . $odoo_invoice_status . '+' . $odoo_delivery_status,
                                            $mapping_order_id['opencart_order_state'], $oc_order_state, $debug)) {

                                            $this->write_chatter_note($odoo_order_id, "Этот заказ значится : <b>" . $mapping_order_id['name'] .
                                                "</b> Проверьте требуемые изменения!");
                                        }
                                    } // End of Odoo Order is neither PAYED nor DELIVERED
                                } else {// End of if Odoo delivery is NOT DONE
                                    // Odoo Order is DELIVERED but NOT PAYED
                                    if ($debug) {
                                        echo "Odoo Order is DELIVERED but NOT PAYED !!!!! <br/>";
                                    }

                                    if ($this->update_odoo_order_map($opencart_order_id,
                                        $mapping_order_id['odoo_order_state'], $odoo_order_id['state'] . '+' . $odoo_invoice_status . '+' . $odoo_delivery_status,
                                        $mapping_order_id['opencart_order_state'], $oc_order_state, $debug)) ;
                                }
                            } // End of if OpenCart Order is (NOT in CDEK DELIVERY) and NOT PAYED
                        } elseif (in_array($oc_order_state, $oc_cdek_delivery_statuses)) {
                            // Lets check it against oc_actual status then compare it against mapping state
                            if ($debug) {
                                echo "OpenCart Order is in CDEK DELIVERY, <br/>";
                            }
                            // While in CDEK Delivery than just wait and check mapping if mapping needs update
                            if ($this->update_odoo_order_map($opencart_order_id,
                                $mapping_order_id['odoo_order_state'], $odoo_order_id['state'] . '+' . $odoo_invoice_status . '+' . $odoo_delivery_status,
                                $mapping_order_id['opencart_order_state'], $oc_order_state, $debug)) {
                                $this->write_chatter_note($odoo_order_id, "Этот заказ значится : <b>" . $mapping_order_id['name']
                                    . ", " . $mapping_order_id['comment'] . "</b>");
                            }
                        }
                    }// End of Odoo order in NOT CANCELLED Sate
                } else { // Opencart order has CANCELLED State
                    // Lets check it against oc_actual status then compare it against mapping state
                    if ($debug) {
                        echo "OpenCart Order is CANCELLED, <br/>";
                    }
                    if ($odoo_order_id['state'] != 'cancel') {
                        if ($debug) {
                            echo "Odoo Order is NOT CANCELLED, <br/>";
                        }
                        // Write message to 'Cancel Odoo order'
                        $this->write_chatter_note($odoo_order_id, "Этот заказ значится : <b>" . $mapping_order_id['name'] .
                            "</b> Проверьте требуемые изменения!");
                    }
                    if ($debug) {
                        echo "Odoo Order is CANCELLED, <br/>";
                    }
                    // The order should be done in mapping and is_sync set to 1
                    // We do not need to check order mapping state as we are going to change opencart_order_state
                    $this->update_odoo_order_map($opencart_order_id,
                        $mapping_order_id['odoo_order_state'], "cancel",
                        $mapping_order_id['opencart_order_state'],strval($oc_cancelled). ", is_sync=1", $debug);
                }
            }
        }
    }

    private function update_odoo_order_map($oc_order_id, $odoo_order_map_state,
                                           $odoo_order_state,
                                           $oc_order_map_state, $oc_order_state, $debug){
        static $run_count =0;
        $run_count++;
        if ($odoo_order_map_state != $odoo_order_state || $oc_order_map_state != $oc_order_state) {
            $sql = "UPDATE " . DB_PREFIX . "odoo_order_map SET 
                `opencart_order_state` =" . $oc_order_state . ", " .
                "`odoo_order_state` = '" . $odoo_order_state . "', 
                `modified_on` = NOW()  WHERE `opencart_order_id` = " . $oc_order_id;
            if ($debug) {
                echo "Updating  mapping table SQL:";
                print_r($sql);
                echo "<br/>update_odoo_order_map RunCount = ".$run_count.", <br/>";
            }
            $ret = $this->db->query($sql) or die("Error updating mapping table! \n" . mysqli_error($this->db));
            return $ret;
        }
        if ($debug) {
            echo "No update was made in the mapping table!, <br/>";
            echo "update_odoo_order_map RunCount = ".$run_count.", <br/>";
        }
        return false;
    }

    private function update_opencart_order_history($oc_order_id, $oc_order_state, $debug){
        $sql = "INSERT INTO " . DB_PREFIX . "order_history SET order_id = " . $oc_order_id . ", 
                order_status_id = " . $oc_order_state . ", `comment` = 'Синхронизирован из ERP', date_added = NOW()";
        $res = $this->db->query($sql);
        $sql1 = "UPDATE " . DB_PREFIX . "order SET `order_status_id` =" . $oc_order_state . ", `date_modified` = NOW() 
                WHERE `order_id` = " . $oc_order_id;
        $res1 = $this->db->query($sql1);
        if($debug){
            echo "Inserting in OpenCart order history table SQL:";
            print_r($sql);
            echo "<br/>";
            echo "Updating in OpenCart order status SQL:";
            print_r($sql1);
            echo "<br/>";
            }
    return $res1;
    }

    private function write_chatter_note($odoo_order_id, $message)
    {
        $odoo_connect = $this->connection;

        // Request statuses of Odoo order $oodoo_ids
        if ($odoo_connect['status']) {
            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];
            $res = $models->execute_kw($db_name, $uid, $password,
                'mail.message', 'create',
                array(array(
                    'create_uid' => 43, //Administrator
                    'res_id' => (int)$odoo_order_id['id'],
                    'model' => 'sale.order',
                    'partners_id' => array($odoo_order_id['message_partner_ids']),
                    'message_type' => 'notification',
//                            'subtype' => 'mail.mt_comment',
                    'subtype_id' => 2,
                    'body' => $message,
                ))
            );
            if (isset($res['faultCode'])) {
                print_r($res['faultString']);
            }
        }
    }

    /**
     * get_odoo_invoice_status
     * gets array of odoo_order_id as a parameter that has odoo order ID
     * returns
     * paid - if all invoices in the order are paid or cancelled
     * open - if any of delivery is in open state
     * to invoice - if there are positions which are not invoiced
     * @param $odoo_order_id
     * @return string
     */
    private function get_odoo_invoice_status($odoo_order_id)
    {
        $odoo_connect = $this->connection;
        $odoo_invoice_status = 'open';
        $paymet_open = ['draft','open','in_payment'];

        // Request statuses of Odoo order $oodoo_ids
        if ($odoo_connect['status']) {
            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];
            $odoo_invoice_status = 'to invoice';
            if ($odoo_order_id['state'] == 'sale') {
                // There are only two invoice statuses 'invoiced' or 'to invoice'
                switch ($odoo_order_id['invoice_status']) {
                    case 'invoiced':
                        $odoo_invoice_status = 'paid'; // If no open invoices than paid
                        // Check what are the invoice statuses
                        $res = $models->execute_kw($db_name, $uid, $password,
                            'account.invoice', 'read',
                            array($odoo_order_id['invoice_ids']),
                            // draft, open, in_payment, paid, cancel
                            array('fields' => array('state')
                            ));
                        foreach ($res as $invoice) {
                            if (in_array($invoice['state'], $paymet_open)) { // If alt least 1 Invoice is open - then Order is not paid
                                $odoo_invoice_status = 'open';
                            }
                        }
                        break;
                    default:
                        $odoo_invoice_status = 'open';
                        break;
                }
            }
        }
        return $odoo_invoice_status;
    }

    /**
     * get_odoo_delivery_status
     * gets array of odoo_order_id as a parameter that has odoo order ID
     * returns
     * done - if all delivery in the order are in state of done or cancelled
     * in_delivery - if any of delivery is in ready state
     * @param $odoo_order_id
     * @return string
     */
    private function get_odoo_delivery_status($odoo_order_id)
    {
        $in_delivery = ['draft','waiting','confirmed','assigned'];
        $odoo_connect = $this->connection;
        $odoo_delivery_status = 'draft';

        // Request statuses of Odoo order $oodoo_ids
        if ($odoo_connect['status']) {
            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];
            if ($odoo_order_id['delivery_count'] > 0) {
                $odoo_delivery_status = 'done'; // If no ready deliveries than deliveredd
                // Check what are the invoice statuses
                $res = $models->execute_kw($db_name, $uid, $password,
                    'stock.picking', 'read',
                    array($odoo_order_id['picking_ids']),
                    array('fields' => array('state')
                    ));
                foreach ($res as $delivery) {
                    if (in_array($delivery['state'] , $in_delivery)) { // If alt least 1 Delivery is not done - then Order is not delivered
                        $odoo_delivery_status = 'in_delivery';
                    }
                }
            }
        }
        return $odoo_delivery_status;
    }

    private function get_oc_paid_status($oc_order_id, $paid_statuses = array(14, 15, 22, 23))
    {
        $paid = false;
        // Go throught order history to chek if there was a payment status there
        $sql = "SELECT order_status_id FROM " . DB_PREFIX . "order_history WHERE `order_id` = " . $oc_order_id;
        $res = $this->db->query($sql) or die("get_oc_paid_status: Error Getting order History! \n" . mysqli_error($this->db));
        while ($invoice = $res->fetch_assoc()) {
            if (in_array($invoice['order_status_id'], $paid_statuses)) {
                $paid = 'paid';
            }
        }
        return $paid;
    }
    private function get_oc_delivery_satus($oc_order_id, $delivered_statuses = array(3))
    {
        $delivered = false;
        $res = $this->db->query("SELECT order_status_id FROM `".DB_PREFIX."order` WHERE `order_id` = ".$oc_order_id)
            or die("get_oc_delivery_status: Error Getting order status! \n" . mysqli_error($this->db));
        $delivery = $res->fetch_assoc();
        if (in_array($delivery['order_status_id'], $delivered_statuses)) {
            $delivered = 'done';
        }

        return $delivered;
    }

}

/**
 * Class OpenCart Odoo customer makes mapping table, creates, deletes, updates mapping table where OpenCart customers
 * correspond to Odoo customers using email as uniq identifier
 */
class ExportOpenCartOdooCustomer extends ExportOpenCartOdoo
{
    public function install()
    {
        parent::install();

        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "odoo_client_map (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `odoo_client_id` int(11) NOT NULL UNIQUE,
            `odoo_client_name` varchar(255) NOT NULL,
            `opencart_client_id` int(11) NOT NULL,
            `opencart_client_name` varchar(255) NOT NULL,
            `opencart_email` varchar(128),
            `opencart_delivery_address` varchar(255),
            `created_by` varchar(128) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_sync` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) DEFAULT CHARSET=utf8");

    }

    /**
     * Retrives odoo partners which 1 - are customers; 2 - have non 0 sales; 3 - do not have parent customers
     * @return mixed|null Partner list if succesful
     */
    function getOdooPartnersList()
    {
        if (!file_exists("clients.json") or (time() - filemtime("clients.json")) > 900) {
            $odoo_connect = $this->connection;
            if ($odoo_connect['status']) {
                $models = $odoo_connect['client'];
                $db_name = $odoo_connect['db'];
                $uid = $odoo_connect['userId'];
                $password = $odoo_connect['pwd'];

                $part = $models->execute_kw($db_name, $uid, $password,
                    'res.partner', 'search_read',
                    array(array(array('customer', '=', true),
                        array('sale_order_ids', '!=', false),
                        // we do not want to map clients that are connected to a parent client
                        array('parent_id', '=', false)
                    )),
                    array('fields' => array('name',
                        "phone",
                        'email'),
                    ));
                file_put_contents("clients.json", json_encode($part, JSON_UNESCAPED_UNICODE));
            } else {
                throw new Exception ("Can not connect to odoo server!");
            }
        } else {
//            echo "Partners file exists!\n";
            $part = json_decode(file_get_contents('clients.json'), true);
        }
        return $part;
    }

    /**
     * This function probably require only once to create the table,
     * It calls getOdooPartners to get a list of odoo partners
     * then it queries OpenCart for a corresponding client mapping it by unique email.
     */
    function createClientMappingTable()
    {
        $nf_clients = array();
        $nf_count = 0;

        $partners = $this->getOdooPartnersList();
        foreach ($partners as $partner) {
            // Find corresponding OpenCart client for each Odoo partner in $partners
            // Not found clients stored in $nf_clinets_array
            $sql = "SELECT a.customer_id, a.firstname, a.lastname, a.email, a.telephone, b.city, b.address_1, b.address_2
                  FROM " . DB_PREFIX . "customer AS a LEFT JOIN " . DB_PREFIX . "address AS b ON a.address_id = b.address_id WHERE a.email = '" . $partner['email'] . "'";
            $result = $this->db->query($sql) or die("Error SQL-ing OpenCart client\n" . mysqli_error($this->db));
            if ($result->num_rows > 0) {
                $oc_client = $result->fetch_assoc();
                // Add new mapping data if the partner is found in OpenCart
                $sql = "INSERT INTO " . DB_PREFIX . "odoo_client_map SET odoo_client_id = " . $partner['id'] .
                    ", odoo_client_name = '" . $partner['name'] .
                    "', opencart_client_id = " . $oc_client['customer_id'] .
                    ", opencart_client_name ='" . $oc_client['lastname'] . " " . $oc_client['firstname'] .
                    "', opencart_email ='" . $oc_client['email'] .
                    "', opencart_delivery_address= '" . $oc_client['city'] . " " . $oc_client['address_1'] .
                    " " . $oc_client['address_2'] . " " .
                    "', created_by ='oc_odoo_sync', created_on = NOW(), is_sync = 0 ON DUPLICATE KEY UPDATE
                    odoo_client_id = " . $partner['id'] .
                    ", odoo_client_name = '" . $partner['name'] .
                    "', opencart_client_id = " . $oc_client['customer_id'] .
                    ", opencart_client_name ='" . $oc_client['lastname'] . " " . $oc_client['firstname'] .
                    "', opencart_email ='" . $oc_client['email'] .
                    "', opencart_delivery_address= '" . $oc_client['city'] . " " . $oc_client['address_1'] .
                    " " . $oc_client['address_2'] . " " .
                    "', created_by ='oc_odoo_sync', is_sync = 0";
                $res = $this->db->query($sql) or die("Error in Adding map client" . mysqli_error($this->db));
            } else {
                $nf_clients [$nf_count]["odoo_id"] = $partner['id'];
                $nf_clients [$nf_count]["email"] = $partner['email'];
                $nf_clients [$nf_count]["odoo_name"] = $partner['name'];
                $nf_clients [$nf_count]["odoo_phone"] = $partner['phone'];
                $nf_count++;
            }

        }
        echo "There are " . ($nf_count) . " Clients  not found in OpenCart.\n";
        file_put_contents("not_found_clients.json", json_encode($nf_clients));
    }

    /**
     * Check if the Client exists in the Client mapping table
     * @param $email
     * @return bool
     */
    public function checkClient($email)
    {
        $sql = "SELECT `odoo_client_id` FROM " . DB_PREFIX . "odoo_client_map WHERE opencart_email = LOWER(TRIM('" . $email . "'))";
//        print_r($sql);
//        echo "\n";
        $result = $this->db->query($sql) or die("Error in Selecting customer " . mysqli_error($this->db));
        if ($result->num_rows) {
            $odoo_client = mysqli_fetch_assoc($result);
//            print_r($odoo_client);
            return $odoo_client['odoo_client_id'];
        }
        return false;
    }

    /**
     * Create Odoo Partner
     * Uses Order data about the client create new odoo partner. Returns true is partner was created.
     * @param array $data
     * @return bool
     */
    function createOdooPartner($data)
    {
        // Select odoo_region_id based on region map
        // map odoo pricelist to upencart_group_id
        $region_id = $this->db->query("SELECT odoo_region_id FROM " . DB_PREFIX . "odoo_region_map WHERE 
                        opencart_region_id =" . (int)$data['payment_zone_id']);
        $region_id = mysqli_fetch_assoc($region_id);
        $odoo_connect = $this->connection;
        if ($odoo_connect['status']) {
            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];
            // Check if this new Partner has not been entered manually in Odoo (so check the email again)
            $res = $models->execute_kw($db_name, $uid, $password,
                'res.partner', 'search_read',
                array(array(array('customer', '=', true),
                    // we do not want to map clients that are connected to a parent client
                    array('parent_id', '=', false),
                    array('email', '=', $data['email']),
                )),
                array('fields' => array('name',
                    "phone",
                    'email'), 'limit' => 1
                ));
            if (empty($res)) { // If there is NO  client with such email in odoo.
                $odoo_pricelist_id = $this->getOdooPricelistId($data['customer_group_id']);
                $res = $models->execute_kw($db_name, $uid, $password,
                    'res.partner', 'create',
                    array(array('name' => $data['lastname'] . " " . $data['firstname'],
                        'city' => $data['payment_city'],
                        'customer' => true,
                        'email' => $data['email'],
                        'mobile' => $data['telephone'],
                        'phone' => $data['telephone'],
                        'state_id' => $region_id['odoo_region_id'],
                        'street' => $data['payment_address_1'],
                        'street2' => $data['payment_address_2'],
                        'zip' => $data['payment_postcode'],
                        'country_id' => 192,
                        'property_product_pricelist' => $odoo_pricelist_id['price_id'],
                    ),
                    ));
                if (isset($res['faultCode'])) {
                    throw new Exception ($res['faultString']);
                } //$res if rpc call was successful contains the odoo's (int) ID of created res.partner
            } else {
                $res = $res[0]['id'];
            }
            $this->mapOdooPartner($res, $data);
            return $res;
        }
        return false;
    }

    /**
     * mapOdoo partner takes id of res.partner model from Odoo and fills new record in
     * odoo_client_map database. Returns true if successful.
     * THIS function WILL add new mapping client even if there is a same opencart client
     * because odoo_client_id will be different every time createOdooPartner is called
     * @param $odoo_partner
     * @param $data
     * @return bool
     */
    function mapOdooPartner($odoo_partner, $data)
    {
        // Add new mapping data if the odoo_partner is found in OpenCart
        $sql = "INSERT INTO " . DB_PREFIX . "odoo_client_map SET odoo_client_id = " . $odoo_partner .
            ", odoo_client_name = '" . $data['lastname'] . " " . $data['firstname'] .
            "', opencart_client_id = " . $data['customer_id'] .
            ", opencart_client_name ='" . $data['lastname'] . " " . $data['firstname'] .
            "', opencart_email ='" . $data['email'] .
            "', opencart_delivery_address= '" . $data['payment_city'] . " " . $data['payment_address_1'] . " " . $data['payment_address_2'] .
            "', created_by ='oc_odoo_sync', created_on = NOW(), is_sync = 0 ON DUPLICATE KEY UPDATE
                odoo_client_id = " . $odoo_partner .
            ", odoo_client_name = '" . $data['lastname'] . " " . $data['firstname'] .
            "', opencart_client_id = " . $data['customer_id'] .
            ", opencart_client_name ='" . $data['lastname'] . " " . $data['firstname'] .
            "', opencart_email ='" . $data['email'] .
            "', opencart_delivery_address= '" . $data['payment_city'] . " " . $data['payment_address_1'] . " " . $data['payment_address_2'] . " " .
            "', created_by ='oc_odoo_sync', is_sync = 0";
        $res = $this->db->query($sql) or die("Error in Adding map client" . mysqli_error($this->db));
//        }
        return true;
    }


}


class ExpotyOpenCartOdooRegions extends ExportOpenCartOdoo
{
    function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "odoo_region_map (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `odoo_region_id` int(11) NOT NULL UNIQUE,
            `odoo_region_name` varchar(255) NOT NULL,
            `opencart_region_id` int(11) NOT NULL,
            `opencart_region_name` varchar(255) NOT NULL,
            `created_by` varchar(128) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_sync` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) DEFAULT CHARSET=utf8");
    }


    function getOdooRegionsList()
    {
        if (!file_exists("regions.json") or (time() - filemtime("clients.json")) > 900) {
            $odoo_connect = $this->connection;
            if ($odoo_connect['status']) {
                $models = $odoo_connect['client'];
                $db_name = $odoo_connect['db'];
                $uid = $odoo_connect['userId'];
                $password = $odoo_connect['pwd'];

                $res = $models->execute_kw($db_name, $uid, $password,
                    'res.country.state', 'search_read',
                    array(array(array('country_id', '=', 192), // Russia = 192 in odoo
                    )),
                    array('fields' => array('name',
                        "code"
                    ),
                    ));
                file_put_contents("regions.json", json_encode($res, JSON_UNESCAPED_UNICODE));
            } else {
                throw new Exception ("Can not connect to odoo server!");
            }
        } else {
            echo "Region file exists!\n";
            $res = json_decode(file_get_contents('regions.json'), true);
        }
        return $res;
    }

    function createRegionMappingTable()
    {
        $nf_regions = array();
        $nf_count = 0;

        $list = $this->getOdooRegionsList();
        foreach ($list as $region) {
            // Find corresponding OpenCart region for each Odoo region in $list
            $sql = "SELECT zone_id, name FROM " . DB_PREFIX . "zone WHERE country_id = 176 AND code = '" . trim($region['code']) . "'";
            $result = $this->db->query($sql) or die("Error SQL-ing OpenCart zone\n" . mysqli_error($this->db));
            if ($result->num_rows > 0) {
                $oc_region = mysqli_fetch_assoc($result);
                // Add new mapping data if the region is found in OpenCart
                $sql = "INSERT INTO " . DB_PREFIX . "odoo_region_map SET odoo_region_id = " . $region['id'] .
                    ", odoo_region_name = '" . $region['name'] .
                    "', opencart_region_id = " . $oc_region['zone_id'] .
                    ", opencart_region_name ='" . $oc_region['name'] .
                    "', created_by ='oc_odoo_sync', created_on = NOW(), is_sync = 0 ON DUPLICATE KEY UPDATE
                    odoo_region_id = " . $region['id'] .
                    ", odoo_region_name = '" . $region['name'] .
                    "', opencart_region_id = " . $oc_region['zone_id'] .
                    ", opencart_region_name ='" . $oc_region['name'] .
                    "', created_by ='oc_odoo_sync', is_sync = 0";
                $res = $this->db->query($sql) or die("Error in Adding map region" . mysqli_error($this->db));
            } else { // Region not found
                $nf_regions [$nf_count]["odoo_region_id"] = $region['id'];
                $nf_regions [$nf_count]["name"] = $region['name'];
                $nf_regions [$nf_count]["code"] = $region['code'];
                $nf_count++;
            }

        }
        echo "There are " . ($nf_count) . " Regions not found in OpenCart.\n";
        file_put_contents("nf_regions.json", json_encode($nf_regions));
    }

}

class exportOpencartOdooNewslist extends ExportOpenCartOdoo
{

    function getOdoomaillist($nl_id)
    {
        $odoo_connect = $this->connection;
//        if (!file_exists("regions.json") or (time() - filemtime("newslistretail.json")) > 900) {
        if ($odoo_connect['status']) {
            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];
            $res = $models->execute_kw($db_name, $uid, $password,
                'mail.mass_mailing.contact', 'search_read',
                array(array(array('list_ids', 'in', (int)$nl_id),
                )),
                array('fields' => array(
                    //'name',
                    "email",
                    //"opt_out",
                ),
                ));
            if (isset($res['faultCode'])) {
                print_r($res['faultString']);
            }
        }
        /*            file_put_contents("newslistretail.json", json_encode($res, JSON_UNESCAPED_UNICODE));
                } else {
                    echo "Newsletter file exists!\n";
                    $res = json_decode(file_get_contents('newslistretail.json'), true);
                }*/
        return $res;
    }

    // NewsRetail_uniqsport.ru = 10 in odoo - production and 5 is Newsletter_uniqsport.ru
    function syncOpencartOdooNewslist($newslist_id = 10)
    {
        $odoo_newslist = $this->getOdoomaillist($newslist_id);
        $odoo_email = array_map('strtolower', array_column($odoo_newslist, 'email'));
        $odoo_connect = $this->connection;
        $result = $this->db->query("SELECT `name`, `email` FROM ((SELECT concat(`firstname`,' ', `lastname`) as `name`, `email` 
                                          FROM " . DB_PREFIX . "customer WHERE `newsletter` = 1 ) UNION (SELECT '' as `name`, `email` 
                                          FROM " . DB_PREFIX . "journal3_newsletter)) TEMP");
        while ($subscriber = $result->fetch_assoc()) {
            if (!in_array(strtolower($subscriber['email']), $odoo_email)) {
                if ($odoo_connect['status']) {
                    $models = $odoo_connect['client'];
                    $db_name = $odoo_connect['db'];
                    $uid = $odoo_connect['userId'];
                    $password = $odoo_connect['pwd'];
                    $res = $models->execute_kw($db_name, $uid, $password,
                        'mail.mass_mailing.contact', 'create',
                        array(array(
                            'create_uid' => 43, //Administrator
                            'name' => $subscriber['name'],
                            'email' => $subscriber['email'],
                            'list_ids' => [[4, (int)$newslist_id]], // Link to the existing mailing list Thanks to chat GPT
                            /*
                             * Summary of list_ids Field: to modify many-to-many fields in Odoo
                             * (0, 0, values): Add a new record to the list.
                             * (1, id, values): Update an existing record with the specified ID.
                             * (4, id): Add an existing record by ID.
                             * (3, id): Unlink the specified ID from the list.
                             * (6, 0, ids): Replace the entire list with the specified IDs.
                             * In this case, using [(4, list_id)] is appropriate, as it adds the contact to an existing mailing list.
                             */
                        ))
                    );
                    if (isset($res['faultCode'])) {
                        print_r($res['faultString']);
//                    } else {
//                        array_push($odoo_newslist, array(
//                            'id'    => $res,
//                            'email' => $subscriber['email'],
//                            ));
                    }
                }
            }
        }
//        file_put_contents("newslistretail.json", json_encode($odoo_newslist, JSON_UNESCAPED_UNICODE));
    }
}

class testMessage extends ExportOpenCartOdoo
{
    function sendActivty($model_id, $res_id, $body)
    {

        $odoo_connect = $this->connection;

        if ($odoo_connect['status']) {
            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];
            $res = $models->execute_kw($db_name, $uid, $password,
                'mail.activity', 'create',
                array(array(
                    'create_uid' => 43, //Administrator
                    'user_id' => 6, //Maxim Surdu
                    'res_id' => (int)$res_id,
                    'res_model_id' => (int)$model_id,
                    'note' => "Here we write some <i>cool</i>> <b>stuff</b>>",
                    'activity_type_id' => 3,
                    'summary' => $body,
                ))
            );
            if (isset($res['faultCode'])) {
                print_r($res['faultString']);
            }
        }
    }
}

$oc = new ExportOpenCartOdooCustomer();
$or = new ExpotyOpenCartOdooRegions();
$eo = new ExportOpenCartOdooOrder($oc);

$nl = new exportOpencartOdooNewslist();

/*$tm = new testMessage();
$tm->sendActivty(293,3149, " This is a test message!");*/
/**
 *  Create databases calling install();
 */
//    $oc->install();
//    $eo->install();
//    $or->install();

// Make mapping tables
//    $oc->createClientMappingTable();
//    $or->createRegionMappingTable();
/*    $data['lastname'] = 'Богданов';
    $data['firstname']= 'Кирилл';
    $data['payment_city']= 'Краснодар';
    $data['email']= 'kirillbogdanov2049@gmail.com';
    $data['telephone']= '79009374653';
    $data['payment_address_1']='ул Войсковая, д 2А';
    $data['payment_address_2']='_';
    $data['payment_postcode']='350902';
    $data['payment_zone_id'] = 74;
    $data['customer_group_id'] = 3;
    $data['customer_id'] = 739;


/*$oc->createOdooPartner($data);*/
/*if(ExportOpenCartOdoo::isUser()){
    print_r("User is logged!");
}*/

if (isset($argc)) {
    for ($i = 1; $i < $argc; $i++) {
        print_r($argv[$i]);
        echo "\n";
        $eo->createOdooOrder($argv[$i]);
    }
}
if (isset($_GET['orderId'])) {
    $eo->createOdooOrder($_GET['orderId']);
}
if (isset($_GET['f']) && $_GET['f'] == 'sync') {
    $eo->syncOpenCartOrderState();
}
if (isset($_GET['nl'])) {
    $nl->syncOpencartOdooNewslist($_GET['nl']);
}

//$eo->syncOpenCartOrderState();

unset($oc);
unset($eo);
unset($nl);
//unset($or);
