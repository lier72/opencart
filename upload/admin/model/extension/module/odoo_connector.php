<?php

/**
 *
 * User: Maxim Surdu
 * Date: 22.09.2024
 * Time: 11:06
 * It is Work in Progress
 *
 */

class ModelExtensionModuleOdooConnector extends Model {

    protected $config_data = array();
    protected $debug = False;
    public static $test_environment = true;
    public $connection;

    // Add getter and setter methods
    public function getConfigData() {
        return $this->config_data;
    }

    public function setConfigData($data) {
        $this->config_data = $data;
    }

    public function getConnection() {
        // If connection doesn't exist, establish it
        if (!$this->connection) {
            $this->getConfig();
        }
        // Ensure we return the connection even if it failed
        // This allows calling code to check the status
        return $this->connection;
    }

    /**
     * Validates if the connection is established and active
     * @return bool true if connection is valid, false otherwise
     */
    public function isConnected() {
        if (!$this->connection || !is_array($this->connection)) {
            return false;
        }
        return isset($this->connection['status']) && $this->connection['status'] === true;
    }

    function getConfig($key = null)
    {
        // Getting Odoo connection configuration
        $res = $this->db->query("SELECT * FROM " . DB_PREFIX . "odoo_config");
        foreach ($res->rows as $row) {
            $this->config_data [$row['key']] = $row['value'];
        }

        // Establish connection to Odoo
        $this->connection = $this->connectRpc();

        // Log connection status for debugging
        if (!$this->connection || !isset($this->connection['status']) || !$this->connection['status']) {
            $error_msg = 'Model odoo_connector getConfig: Failed to establish Odoo connection.';
            if (isset($this->connection['error'])) {
                $error_msg .= ' Error: ' . $this->connection['error'];
            }
            $this->log->write($error_msg);
        }

        // Set test_environment based on Odoo URL
        // Production: https://portal.uniqsport.ru → test_environment = false
        // Test/Dev: https://odoo2.uniqsport.ru or any other → test_environment = true
        if (isset($this->config_data['url']) && $this->config_data['url'] == "https://portal.uniqsport.ru") {
            $this->test_environment = false;
        } else {
            $this->test_environment = true; // Explicitly set to true for test/dev environments
        }

        if (isset($this->config_data['debug']) && $this->config_data['debug']) $this->debug = True;

//        $this->log->write('Model odoo_connector getConfig $key: '. $key . ' isset($userData[$key])' . isset($this->userData[$key]));
        if ($key !== null && isset($this->config_data[$key])) {
            return $this->config_data[$key];
        }
        return $this->config_data;
    }

    /**
     * Connects to odoo rpc using hardwritten config
     * @return array of conenction data
     */
    function connectRpc()
    {
        try {
            if (!class_exists('\Ripcord\Ripcord::client'))
                require_once(DIR_SYSTEM . 'library/odoo_connector/vendor/autoload.php');

            $common = \Ripcord\Ripcord::client($this->config_data['url'] . "/xmlrpc/common");
            $uid = $common->authenticate($this->config_data['db_name'], $this->config_data['user'], $this->config_data['password'], array());
            $models = \Ripcord\Ripcord::client($this->config_data['url'] . "/xmlrpc/object");

            if ($uid <= 0 || $uid === false) {
                $this->log->write('Error odoo_connector connectRpc: Authentication failed. UID: ' . var_export($uid, true));
                return array(
                    'status' => False,
                );
            } else {
                return array(
                    'status' => True,
                    'client' => $models,
                    'userId' => $uid,
                    'pwd' => $this->config_data['password'],
                    'url' => $this->config_data['url'],
                    'port' => $this->config_data['port'],
                    'db' => $this->config_data['db_name'],
                );
            }
        } catch (Exception $e) {
            $this->log->write('Error odoo_connector connectRpc: ' . $e->getMessage());
            return array(
                'status' => False,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Defines odoo price list it
     * @param $oc_customer_group
     * @return int Odoo Price list ID
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


    /**
     * Get payment acquirer mapping for OpenCart payment method
     * @param string $payment_code OpenCart payment method code
     * @return array|null Mapping data or null if not found
     */
    private function getPaymentAcquirerMapping($payment_code)
    {
        $sql = "SELECT opencart_payment_code, opencart_payment_name,
                       odoo_acquirer_id, odoo_acquirer_name, is_active
                FROM " . DB_PREFIX . "odoo_payment_acquirer_map
                WHERE opencart_payment_code = '" . $this->db->escape($payment_code) . "'
                AND is_active = 1
                LIMIT 1";
        $result = $this->db->query($sql);

        if ($result->num_rows) {
            return $result->row;
        }
        return null;
    }

    /**
     * Get currency mapping for OpenCart currency code
     * @param string $currency_code OpenCart currency code (e.g., 'RUB', 'USD')
     * @return array|null Mapping data or null if not found
     */
    private function getCurrencyMapping($currency_code)
    {
        $sql = "SELECT opencart_currency_code, opencart_currency_title,
                       odoo_currency_id, odoo_currency_name, is_active
                FROM " . DB_PREFIX . "odoo_currency_map
                WHERE opencart_currency_code = '" . $this->db->escape($currency_code) . "'
                AND is_active = 1
                LIMIT 1";
        $result = $this->db->query($sql);

        if ($result->num_rows) {
            return $result->row;
        }
        return null;
    }

    /**
     * Get Alfabank payment transaction data for an order
     * @param int $oc_order_id OpenCart order ID
     * @return array|null Transaction data or null if not found
     */
    private function getAlfabankTransactionData($oc_order_id)
    {
        $sql = "SELECT gateway_order_reference, tx_url, order_number, order_amount
                FROM " . DB_PREFIX . "alfabank_order
                WHERE order_id = " . (int)$oc_order_id . "
                ORDER BY date_added DESC
                LIMIT 1";
        $result = $this->db->query($sql);

        if ($result->num_rows) {
            return $result->row;
        }
        return null;
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
        $json = array(
            'success' => false,
            'updates' => array(),
            'errors' => array()
        );

        if ($this->debug) $this->log->write('Model odoo_connector createOdooOrder: Starting for order_id: ' . $oc_order_id);

        // Ensure connection is initialized
        if (!$this->connection || !is_array($this->connection)) {
            if ($this->debug) $this->log->write('Model odoo_connector createOdooOrder: Connection not set, calling getConfig()');
            $this->getConfig();
        }

        // Proceed  only of the order is not already mapped
        if ($this->checkOdooOrder($oc_order_id)) {
            $json['success'] = true;
            $json['message'] = 'The order ' .$oc_order_id .' has been already created...';
            if ($this->debug) $this->log->write('Model odoo_connector createOdooOrder: Order already exists in Odoo');
            return $json;
        }

        // Check if connection exists after getConfig
        if (!$this->connection || !is_array($this->connection)) {
            $json['errors'][] = 'Odoo connection not initialized. Please check configuration.';
            $this->log->write('Model odoo_connector createOdooOrder: ERROR - Connection still not initialized after getConfig()');
            return $json;
        }

        $odoo_connect = $this->connection;
        if ($this->debug) $this->log->write('Model odoo_connector createOdooOrder: Connection status: ' . var_export($odoo_connect['status'] ?? 'NOT SET', true));

        if (!isset($odoo_connect['status']) || !$odoo_connect['status']) {
            $error_msg = 'Failed to connect to Odoo server. Please check Odoo configuration.';
            if (isset($odoo_connect['error'])) {
                $error_msg .= ' Error: ' . $odoo_connect['error'];
            }
            $json['errors'][] = $error_msg;
            $this->log->write('Model odoo_connector createOdooOrder: ' . $error_msg);
            return $json;
        }

        if ($odoo_connect['status']) {
            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];

            // Check the clinet information from order data
            $sql = "SELECT customer_id, firstname,lastname, email, telephone, customer_group_id, payment_zone_id, payment_city,
                payment_postcode, payment_country_id, payment_address_1, payment_address_2, comment, total, order_status_id, payment_code
                FROM " . DB_PREFIX . "order
                WHERE order_id =" . $oc_order_id;
            $result = $this->db->query($sql) or die("Error in Selecting order " . mysqli_error($this->db));

            if ($result->num_rows) {
                $customer_data = $result->row;
                $customer_data ['opencart_order_id'] = $oc_order_id;
                $clean = preg_replace('/\s*/', '', $customer_data['email']);
                $customer_data['email'] = strtolower($clean);

                // Check if the partner exists in mapping table
                $odoo_partner = $this->checkClient($customer_data['email']);
                if (!$odoo_partner) {
                    // Create new partner in Odoo
                    $odoo_partner = $this->createOdooPartner($customer_data);
                    if (!$odoo_partner) {
                        $json['errors'][] = 'Failed to create new partner! Check connection etc.';
//                        throw new Exception ("Failed to create new partner! Check connection etc.\n");
                    }
                }

                $odoo_pricelist_id = $this->getOdooPricelistId($customer_data['customer_group_id']);
                if ($this->debug) $this->log->write('Model odoo_connector createOdooOrder: Customer group: ' . $customer_data['customer_group_id'] .
                    ', Pricelist ID: ' . ($odoo_pricelist_id['price_id'] ?? 'NOT SET') .
                    ', Team ID: ' . ($odoo_pricelist_id['team_id'] ?? 'NOT SET') .
                    ', Test environment: ' . ($this->test_environment ? 'TRUE' : 'FALSE'));
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
                if (isset($odoo_order_id['faultCode'])) {
                        $this->log->write("Error odoo_connector createOdooOrder sale.order create: ". $odoo_order_id['faultString'] . "\n" .
                        serialize(array(array(
                            'partner_id' => (int)$odoo_partner,
                            'origin' => $oc_order_id,
                            'team_id' => $odoo_pricelist_id['team_id'],
                            'pricelist_id' => $odoo_pricelist_id['price_id'])))
                    );
                    return $json['errors'][]= $odoo_order_id['faultString'];
//                    throw new Exception ($odoo_order_id['faultString']);
                }//$odoo_order_id if rpc call was successful contains the odoo's (int) ID of created sale.order

                $this->mapOdooOrder($odoo_order_id, $customer_data);

                $sql = "SELECT product_id, name, order_product_id, quantity, price FROM " . DB_PREFIX . "order_product WHERE order_id =" . $oc_order_id;
                $result = $this->db->query($sql) or die("Error in Selecting products from order. " . mysqli_error($this->db));
                if ($result->num_rows) {
                    // output data of each row
                    foreach ($result->rows as $row) {
                        // Select option for the product
                        $sql = "SELECT a.product_option_value_id, a.name, a.value, a.order_id, b.required FROM " . DB_PREFIX .
                            "order_option AS a LEFT JOIN " . DB_PREFIX . "product_option AS b ON a.product_option_id=b.product_option_id WHERE a.order_id =" .
                            $oc_order_id . " AND a.order_product_id =" . $row['order_product_id'];
                        $option = $this->db->query($sql) or die("Error in Selecting product option from order. " . mysqli_error($this->db));
                        // if product option exists modify SQL query to pick odoo product_id based on this particular option
                        if ($option->num_rows > 0) {
                            $oc_option = $option->row;
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
                        $json['message'] = serialize($row);
                        if ($res->num_rows > 0) { // product exists in the Mapping Table
                            $odoo_product = $res->row;
                            // Write Odoo order.product.line
                            $response = $models->execute_kw($db_name, $uid, $password,
                                'sale.order.line', 'create',
                                array(array(
//                                'create_uid' => 43, //Administrator
                                    'order_id' => (int)$odoo_order_id,
                                    'name' => $row['name'],
                                    'product_id' => (int)$odoo_product['odoo_product_id'],
                                    'order_partner_id' => (int)$odoo_partner,
                                    'product_uom_qty' => (int)$row['quantity'],
                                    'price_unit' => (float)$row['price']
                                ))
                            );
                            // Check for Ripcord/XML-RPC errors
                            if (isset($response['faultCode']) && $response['faultCode']) {
                                // Extract error message - Odoo errors can be in faultCode or faultString
                                $error_detail = '';

                                // First try faultString
                                if (!empty($response['faultString'])) {
                                    $error_detail = $response['faultString'];
                                }
                                // If faultString is empty, use faultCode (Odoo often puts the full error here)
                                elseif (!empty($response['faultCode'])) {
                                    $error_detail = $response['faultCode'];
                                }

                                // If still empty, provide context
                                if (empty($error_detail)) {
                                    $error_detail = 'Product not found in Odoo (product.product ID: ' . ($odoo_product['odoo_product_id'] ?? 'unknown') . ')';
                                }

                                $error_msg = 'Error creating order line for product "' . $row['name'] . '" (OC Product ID: ' . $row['product_id'] . ', Odoo Product ID: ' . ($odoo_product['odoo_product_id'] ?? 'N/A') . '): ' . $error_detail;
                                $this->log->write('Error Creating Order Line: ' . $error_detail);
                                $json['errors'][]= $error_msg;
                                $json['success'] = false;
                                return $json;
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
                                if (isset($response['faultCode']) && $response['faultCode']) {
                                    $error_detail = !empty($response['faultString']) ? $response['faultString'] : $response['faultCode'];
                                    $error_msg = 'Error creating option comment: ' . $error_detail;
                                    $this->log->write('Error Creating comment: '. $error_detail);
                                    $json['errors'][]= $error_msg;
                                    $json['success'] = false;
                                    return $json;
                                }
                            }
                        } else { // The product is not in mapping table
                            $order_data['note'] = $order_data['note'] . "\n" . 'НЕ НАЙДЕН Продукт: ' .
                                $row['name'] . ' Количество: ' . $row['quantity'] . ' Цена: ' . $row['price'];
                        }
                    }
                } else {
                    $this->log->write ("The order " . $oc_order_id . " seems to be an empty order");
                    $json['errors'][] = "The order " . $oc_order_id . " seems to be an empty order";
                }


                // Get delivery and other order options influencing to total amount
                $order_delivery = false;
                $sql = "SELECT code, title, value FROM " . DB_PREFIX . "order_total WHERE order_id =" . $oc_order_id;
                $result = $this->db->query($sql) or die("Error in Selecting order " . mysqli_error($this->db));
                if ($result->num_rows) {
                    foreach ($result->rows as $total_data) {
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
                            case 'cdek':
                                $cod['product_id'] = 3892; // id=3892 - Product variant ! Оплата при доставке СДЭК в odoo
                                $cod['price_unit'] = round($total_data['value']);
                                break;
                            case 'cod_cdek_total':
                                $cod['product_id'] = 3892; // the cod_cdek_total is from OC2 - id=3892 - Product variant ! Оплата при доставке Почта России в odoo
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
                    if (isset($response['faultCode']) && $response['faultCode']) {
                        $error_detail = !empty($response['faultString']) ? $response['faultString'] : $response['faultCode'];
                        $error_msg = 'Error creating delivery line: ' . $error_detail;
                        $this->log->write('Error Creating Delivery Line: '. $error_detail);
                        $json['errors'][]= $error_msg;
                        $json['success'] = false;
                        return $json;
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
                        if (isset($response['faultCode']) && $response['faultCode']) {
                            $error_detail = !empty($response['faultString']) ? $response['faultString'] : $response['faultCode'];
                            $error_msg = 'Error creating cash on delivery line: ' . $error_detail;
                            $this->log->write('Error Creating Cash On Delivery Line: '. $error_detail);
                            $json['errors'][]= $error_msg;
                            $json['success'] = false;
                            return $json;
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

                if (isset($response['faultCode']) && $response['faultCode']) {
                    $error_detail = !empty($response['faultString']) ? $response['faultString'] : $response['faultCode'];
                    $error_msg = 'Error creating order comment/note: ' . $error_detail;
                    $this->log->write('Error Creating Comment: '. $error_detail);
                    $json['errors'][]= $error_msg;
                    $json['success'] = false;
                    return $json;
                }

                // Create payment.transaction record if payment method has a mapping
                if (isset($customer_data['payment_code']) && !empty($customer_data['payment_code'])) {
                    // Get payment acquirer mapping
                    $acquirer_mapping = $this->getPaymentAcquirerMapping($customer_data['payment_code']);

                    if ($acquirer_mapping && $acquirer_mapping['odoo_acquirer_id']) {
                        // Get payment-specific transaction data
                        $payment_tx_data = null;
                        if ($customer_data['payment_code'] == 'alfabank') {
                            $payment_tx_data = $this->getAlfabankTransactionData($oc_order_id);
                        }

                        // Get currency from order
                        $currency_code = 'RUB'; // Default
                        $currency_sql = "SELECT currency_code FROM " . DB_PREFIX . "order WHERE order_id = " . (int)$oc_order_id;
                        $currency_result = $this->db->query($currency_sql);
                        if ($currency_result->num_rows) {
                            $currency_code = $currency_result->row['currency_code'];
                        }

                        // Get currency mapping
                        $currency_mapping = $this->getCurrencyMapping($currency_code);
                        $currency_id = $currency_mapping ? (int)$currency_mapping['odoo_currency_id'] : 36;

                        $transaction_data = array(
                            'acquirer_id' => (int)$acquirer_mapping['odoo_acquirer_id'],
                            'partner_id' => (int)$odoo_partner,
                            'amount' => (float)($customer_data['total']),
                            'currency_id' => $currency_id,
                            'state' => 'pending',
                        );

                        // Add payment-specific fields if available
                        if ($payment_tx_data) {
                            if (isset($payment_tx_data['order_number'])) {
                                $transaction_data['reference'] = $payment_tx_data['order_number'];
                            }
                            if (isset($payment_tx_data['gateway_order_reference'])) {
                                $transaction_data['acquirer_reference'] = $payment_tx_data['gateway_order_reference'];
                            }
                            if (isset($payment_tx_data['tx_url'])) {
                                $transaction_data['tx_url'] = $payment_tx_data['tx_url'];
                            }
                        } else {
                            // For payment methods without specific transaction data
                            $transaction_data['reference'] = 'OC-' . $oc_order_id;
                        }

                        // Check if transaction with this reference already exists
                        $existing_tx = $models->execute_kw($db_name, $uid, $password,
                            'payment.transaction', 'search_read',
                            array(array(array('reference', '=', $transaction_data['reference']))),
                            array('fields' => array('id', 'reference', 'state'))
                        );

                        if (!empty($existing_tx)) {
                            // Transaction already exists, skip creation
                            if ($this->debug) {
                                $this->log->write('Model odoo_connector createOdooOrder: payment.transaction already exists for reference: '
                                    . $transaction_data['reference'] . ' (ID: ' . $existing_tx[0]['id'] . ', State: ' . $existing_tx[0]['state'] . ')');
                            }
                        } else {
                            // Create new transaction
                            $transaction_response = $models->execute_kw($db_name, $uid, $password,
                                'payment.transaction', 'create',
                                array($transaction_data)
                            );

                            if (isset($transaction_response['faultCode']) && $transaction_response['faultCode']) {
                                $error_detail = !empty($transaction_response['faultString']) ? $transaction_response['faultString'] : $transaction_response['faultCode'];
                                $error_msg = 'Error creating payment.transaction for ' . $customer_data['payment_code'] . ': ' . $error_detail;
                                $this->log->write('Error Creating payment.transaction: '. $error_detail);
                                $json['errors'][]= $error_msg;
                            } else {
                                if ($this->debug) {
                                    $this->log->write('Model odoo_connector createOdooOrder: Created payment.transaction ID: ' . $transaction_response
                                        . ' for payment method: ' . $customer_data['payment_code']);
                                }
                            }
                        }
                    } else {
                        if ($this->debug) {
                            $this->log->write('Model odoo_connector createOdooOrder: No active payment acquirer mapping found for: ' . $customer_data['payment_code']);
                        }
                    }
                }
            }

        }
        // Only set success to true if we reach this point without errors
        $json['success'] = true;
        return $json;
     }

     /**
     * Check if the Order exists in the Order mapping table
     * @param $oc_order_id
     * @return odoo_order_id or false
     */
    public function checkOdooOrder($oc_order_id)
    {
        $sql = "SELECT `odoo_order_id` FROM " . DB_PREFIX . "odoo_order_map WHERE opencart_order_id = " . $oc_order_id;
        $result = $this->db->query($sql) or die("Error in Selecting customer " . mysqli_error($this->db));
        if ($result->num_rows) {
            $odoo_order = $result->row;
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
+-----------------+-------------+--------------------------------------------------------------+
| order_status_id | language_id | name                                                         |
+-----------------+-------------+--------------------------------------------------------------+
|               1 |           1 | Сборка заказа                                                |
|               2 |           1 | Передан в доставку (СДЭк)                                    |
|               3 |           1 | Доставлено                                                   |
|               5 |           1 | Сделка завершена                                             |
|               8 |           1 | Частичный возврат                                            |
|               9 |           1 | Заказ отменен                                                |
|              10 |           1 | Не доставленный заказ                                        |
|              12 |           1 | Заказ изменен (нами)                                         |
|              13 |           1 | Заказ полностью возврщен                                     |
|              14 |           1 | Оплачено картой                                              |
|              15 |           1 | Оплачено на счет ИП                                          |
|              16 |           1 | Будет оплачено при доставке                                  |
|              17 |           1 | Ожидание оплаты                                              |
|              18 |           1 | Ожидание в Пункте Выдачи Заказов                             |
|              19 |           1 | Передан в доставку (Данченко)                                |
|              20 |           1 | Передан в доставку (Сурду)                                   |
|              21 |           1 | Заказ к возврату                                             |
|              22 |           1 | Оплачено наличными                                           |
|              23 |           1 | Оплачено на Сбер                                             |
|              24 |           1 | Передан в доставку (Ольга)                                   |
|              25 |           1 | Передан в доставку (Мякишев)                                 |
+-----------------+-------------+--------------------------------------------------------------+
*/

    function syncOpenCartOrderState($debug = false)
    {

//        $initial_statuses = array(12, 16, 17);
//        $in_delivery_statuses = array(8, 19, 20, 21, 24, 25);
//        $paid_statuses = array(14, 15, 22, 23);
//        $delivered_statuses = array(3);
//        $done_statuses = array(5);
        $json = array(
            'success' => false,
            'updates' => array(),
            'errors' => array()
        );

        // Ensure connection is initialized
        if (!$this->connection || !is_array($this->connection)) {
            if ($debug) $this->log->write('Model odoo_connector syncOpenCartOrderState: Connection not set, calling getConfig()');
            $this->getConfig();
        }

        // Check if connection exists after getConfig
        if (!$this->connection || !is_array($this->connection)) {
            $json['errors'][] = 'Odoo connection not initialized. Please check configuration.';
            $this->log->write('Model odoo_connector syncOpenCartOrderState: ERROR - Connection still not initialized after getConfig()');
            return $json;
        }

        // Check if connection is successful
        if (!isset($this->connection['status']) || !$this->connection['status']) {
            $json['errors'][] = 'Failed to connect to Odoo server. Please check if Odoo is running and accessible.';
            if (isset($this->connection['error'])) {
                $json['errors'][] = $this->connection['error'];
            }
            $this->log->write('Model odoo_connector syncOpenCartOrderState: ERROR - Odoo connection failed');
            return $json;
        }

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
        foreach ($res->rows as $row) {
            array_push($odoo_ids, (int) $row['odoo_order_id']);
        }

        // Connection already validated above, safe to use directly
        $models = $this->connection['client'];
        $db_name = $this->connection['db'];
        $uid = $this->connection['userId'];
        $password = $this->connection['pwd'];
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
            $mapping_order_id = $res->row;
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
            $opencart_order = $res->row;
            $oc_order_state = $opencart_order['order_status_id'];

            if ($debug){
                echo "<p> OC order id <b>"; print_r($opencart_order_id); echo "</b>, ";
                echo " OC order status_id: "; print_r($oc_order_state); echo ", ";
                echo " Odoo order data: "; print_r($odoo_order_id); echo ",<br/> ";
            } else {
                $json['updates'][] = 'OC Order '. $opencart_order_id . ' state: '.$oc_order_state;
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
        $json['success'] = true;
        $json['message'] = 'Orders were updated!';
        return $json;
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

    /**
     * Updates OpenCart order history via the OpenCart API to properly trigger events.
     * This method uses the same approach as CDEK Integrator's statusApi() to ensure
     * that OpenCart events (like catalog/model/checkout/order/addOrderHistory/after)
     * are properly triggered, which is required for modules like bonus_manager.
     *
     * @param int $oc_order_id The OpenCart order ID
     * @param int $oc_order_state The new order status ID
     * @param bool $debug Enable debug output
     * @param string $comment Comment to add to order history
     * @param int $notify Whether to notify customer (0 or 1)
     * @return mixed API response or false on failure
     */
    private function update_opencart_order_history($oc_order_id, $oc_order_state, $debug, $comment = 'Синхронизирован из ERP', $notify = 0){
        // Debug: Log entry into method
        if ($debug) {
            $this->log->write('Odoo Connector DEBUG: update_opencart_order_history called for order_id=' . $oc_order_id . ', status=' . $oc_order_state);

        // Debug: Check registry state before loading model
            $this->log->write('Odoo Connector DEBUG: registry has load? ' . ($this->registry->has('load') ? 'YES' : 'NO'));
        }

        // Try to load user/api model
        try {
            $this->load->model('user/api');
            if ($debug) {
                $this->log->write('Odoo Connector DEBUG: load->model(user/api) called without exception');
            }
        } catch (Exception $e) {
            if ($debug) {
                $this->log->write('Odoo Connector DEBUG: Exception loading model: ' . $e->getMessage());
            }
            return false;
        }

        // Check if model is registered in registry (don't use isset - it fails with __get magic)
        $model_in_registry = $this->registry->has('model_user_api');
        if ($debug) {
            $this->log->write('Odoo Connector DEBUG: registry has model_user_api? ' . ($model_in_registry ? 'YES' : 'NO'));
        }

        if (!$model_in_registry) {
            if ($debug) {
                $this->log->write('Odoo Connector DEBUG: model_user_api not in registry after load');
            }
            return false;
        }

        // Get config_api_id value
        $config_api_id = $this->config->get('config_api_id');
        if ($debug) {
            $this->log->write('Odoo Connector DEBUG: config_api_id = ' . var_export($config_api_id, true));
        }

        if (empty($config_api_id)) {
            if ($debug) {
                $this->log->write('Odoo Connector DEBUG: config_api_id is empty, trying fallback');
            }
            $query = $this->db->query("SELECT api_id FROM " . DB_PREFIX . "api WHERE status = 1 ORDER BY api_id ASC LIMIT 1");
            if ($query->num_rows) {
                $config_api_id = $query->row['api_id'];
                if ($debug) {
                    $this->log->write('Odoo Connector DEBUG: Using fallback api_id = ' . $config_api_id);
                }
            } else {
                if ($debug) {
                    $this->log->write('Odoo Connector DEBUG: No active API found in database');
                }
                return false;
            }
        }

        // Get API info using model
        $api_info = $this->model_user_api->getApi($config_api_id);
        if ($debug) {
            $this->log->write('Odoo Connector DEBUG: api_info from model = ' . var_export($api_info, true));
        }

        if (!$api_info) {
            $this->log->write('Odoo Connector: API not configured. Please set up API in System > Users > API.');
            if ($debug) {
                echo "API not configured for order " . $oc_order_id . "<br/>";
            }
            return false;
        }

        require_once DIR_SYSTEM . 'library/cdek_integrator/ocapi.php';

        // Prefer HTTPS_CATALOG if defined (production sites should use HTTPS)
        // Fall back to HTTP_CATALOG for local/dev environments
        if (defined('HTTPS_CATALOG')) {
            $site_url = rtrim(HTTPS_CATALOG, '/');
        } else {
            $site_url = rtrim(HTTP_CATALOG, '/');
        }

        $oc = new \OpenCart\OpenCart($site_url);
        $token = $oc->login($api_info['username'], $api_info['key']);

        if (empty($token)) {
            $last_error = $oc->getLastError();
            $this->log->write('Odoo Connector: Failed to authenticate with OpenCart API');
            $this->log->write('Site_url: ' . $site_url . ' | Username: ' . $api_info['username'] . ' | order_id: ' . $oc_order_id . ' | status_id: ' . $oc_order_state);
            if ($last_error) {
                $this->log->write('API Error: ' . print_r($last_error, true));
            }
            $this->log->write('Check: 1) Server IP in ocus_api_ip, 2) HTTP_CATALOG in config.php, 3) API key');
            if ($debug) {
                echo "API authentication failed for order " . $oc_order_id . "<br/>";
            }
            return false;
        }

        $oc->order->setToken($token);
        $response = $oc->order->history((int)$oc_order_id, (int)$oc_order_state, $notify, false, $comment);

        if ($debug) {
            echo "Updated order " . $oc_order_id . " via API to status " . $oc_order_state . "<br/>";
            echo "API Response: ";
            print_r($response);
            echo "<br/>";
        }

        return $response;
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
        foreach ( $res->rows as $invoice) {
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
        $delivery = $res->row;
        if (in_array($delivery['order_status_id'], $delivered_statuses)) {
            $delivered = 'done';
            }

        return $delivered;
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
    public function createClientMappingTable()
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
                $oc_client = $result->row;
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
        $sql = "SELECT `odoo_client_id` FROM " . DB_PREFIX . "odoo_client_map WHERE opencart_email = LOWER(TRIM('" . $email. "'))";
//        print_r($sql);
//        echo "\n";
        $result = $this->db->query($sql) or die("Error in Selecting customer " . mysqli_error($this->db));
        if ($result->num_rows) {
            $odoo_client = $result->row;
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
        $result = $this->db->query("SELECT odoo_region_id FROM " . DB_PREFIX . "odoo_region_map WHERE 
                        opencart_region_id =" . (int)$data['payment_zone_id']);
        $region_id = $result->row;
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
                if (is_array($res) && isset($res['faultCode'])) {
                    $this->log->write('modle odoo_create createOdooPartner: '. serialize($res['faultString']));
                    throw new Exception (json_encode($res['faultString']));
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
                $oc_region = $result->row;
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

    public function testConnection($post_data) {
        $json = array();

//        $this->log->write("POST data: " . print_r($post_data, true));

        if (!empty($post_data)) {
            try {
//                $this->load->model('module/odoo_connector');

                // Get current config and save test config
                $this->getConfigData();
                $original_config = $this->getConfigData();

//                $this->log->write("Original data: " . serialize($original_config));
                $test_config = array(
                    'url' => $this->request->post['odoo_url'],
                    'db_name' => $this->request->post['odoo_db_name'],
                    'user' => $this->request->post['odoo_user'],
                    'password' => $this->request->post['odoo_password'],
                    'port' => $this->request->post['odoo_port']
                );
                $this->setConfigData($test_config);

                // Test connection
                $connection = $this->connectRpc();

                // Restore original config
                $this->setConfigData ($original_config);

                if ($connection['status']) {
                    $json['success'] = 'Connection successful';
                } else {
                    $json['error'][] = 'Connection failed';
                }
            } catch (Exception $e) {
                $json['error'] = $e->getMessage();
            }
        }
        return $json;
    }

    function install(){
// Odoo config table
        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "odoo_config (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `key` varchar(255) NOT NULL,
            `value` varchar(255),
            PRIMARY KEY (`id`)
          ) DEFAULT CHARSET=utf8");
// Product mapping table
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
// Order mapping table
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
// Client mapping table
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
// Region mapping table
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
// Payment acquirer mapping table
        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "odoo_payment_acquirer_map (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `opencart_payment_code` varchar(64) NOT NULL COMMENT 'OpenCart payment method code',
            `opencart_payment_name` varchar(255) NOT NULL COMMENT 'OpenCart payment method name',
            `odoo_acquirer_id` int(11) NOT NULL COMMENT 'Odoo payment acquirer ID',
            `odoo_acquirer_name` varchar(255) NULL COMMENT 'Odoo acquirer name for reference',
            `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is mapping active',
            `created_by` varchar(128) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `modified_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_payment_code` (`opencart_payment_code`)
          ) DEFAULT CHARSET=utf8 COMMENT='Maps OpenCart payment methods to Odoo payment acquirers'");
// Currency mapping table
        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "odoo_currency_map (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `opencart_currency_code` varchar(3) NOT NULL COMMENT 'OpenCart currency code (ISO 4217)',
            `opencart_currency_title` varchar(128) NOT NULL COMMENT 'OpenCart currency title',
            `odoo_currency_id` int(11) NOT NULL COMMENT 'Odoo currency ID',
            `odoo_currency_name` varchar(64) NULL COMMENT 'Odoo currency name (for reference)',
            `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Is mapping active',
            `created_by` varchar(128) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `modified_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_currency_code` (`opencart_currency_code`)
          ) DEFAULT CHARSET=utf8 COMMENT='Maps OpenCart currencies to Odoo currencies'");
    }

    function uninstall()
    {
        $sql  = "DROP TABLE IF EXISTS `" . DB_PREFIX . "odoo_config`, ";
        $sql .= "`" . DB_PREFIX . "odoo_product_variant_map`, ";
        $sql .= "`" . DB_PREFIX . "odoo_order_map`, ";
        $sql .= "`" . DB_PREFIX . "odoo_client_map`, ";
        $sql .= "`" . DB_PREFIX . "odoo_region_map`, ";
        $sql .= "`" . DB_PREFIX . "odoo_payment_acquirer_map`, ";
        $sql .= "`" . DB_PREFIX . "odoo_currency_map`";

        $this->db->query($sql);
    }

    // functions to Show order statuses table
    public function getMappedOrders($data = array()) {

//        $this->log->write("Info odoo_connector getMappedOrders: received data: " . serialize($data));

        $sql = "SELECT oom.*, o.order_status_id, os.name as order_status_name,
            o.firstname, o.lastname, o.total as order_total, o.currency_code,
            o.date_added as order_date 
            FROM " . DB_PREFIX . "odoo_order_map oom 
            LEFT JOIN `" . DB_PREFIX . "order` o ON (oom.opencart_order_id = o.order_id) 
            LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id) 
            WHERE os.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        // Add filters
        if (!empty($data['filter_order_id'])) {
            $sql .= " AND oom.opencart_order_id = '" . (int)$this->db->escape($data['filter_order_id']) . "'";
        }

        if (!empty($data['filter_odoo_id'])) {
            $sql .= " AND oom.odoo_order_id = '" . (int)$this->db->escape($data['filter_odoo_id']) . "'";
        }

        if (!empty($data['filter_oc_status'])) {
            $sql .= " AND o.order_status_id = '" . (int)$this->db->escape($data['filter_oc_status']) . "'";
        }

        if (!empty($data['filter_odoo_state'])) {
            $sql .= " AND oom.odoo_order_state LIKE '%" . $this->db->escape($data['filter_odoo_state']) . "%'";
        }

        // Default to showing only non-synced orders

        if ($data['filter_sync'] !== '2') {
            $sql .= " AND oom.is_sync = '" . (int)$this->db->escape($data['filter_sync']) . "'";
        }

        $sort_data = array(
            'oom.opencart_order_id',
            'oom.odoo_order_id',
            'os.name',
            'oom.odoo_order_state',
            'o.date_added'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY oom.modified_on";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }
//        $this->log->write("Info odoo_connector getMappedOrders: received SQL: " . $sql);
        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getTotalMappedOrders($data = array()) {

//        $this->log->write("Info odoo_connector getTotalMappedOrders: received data: " . serialize($data));

        $sql = "SELECT COUNT(DISTINCT oom.opencart_order_id) AS total 
            FROM " . DB_PREFIX . "odoo_order_map oom 
            LEFT JOIN `" . DB_PREFIX . "order` o ON (oom.opencart_order_id = o.order_id) 
            LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id) 
            WHERE os.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        // Add the same filters as in getMappedOrders
        if (!empty($data['filter_order_id'])) {
            $sql .= " AND oom.opencart_order_id = '" . (int)$this->db->escape($data['filter_order_id']) . "'";
        }

        if (!empty($data['filter_odoo_id'])) {
            $sql .= " AND oom.odoo_order_id = '" . (int)$this->db->escape($data['filter_odoo_id']) . "'";
        }

        if (!empty($data['filter_oc_status'])) {
            $sql .= " AND o.order_status_id = '" . (int)$this->db->escape($data['filter_oc_status']) . "'";
        }

        if (!empty($data['filter_odoo_state'])) {
            $sql .= " AND oom.odoo_order_state LIKE '%" . $this->db->escape($data['filter_odoo_state']) . "%'";
        }


        if ($data['filter_sync'] !== '2') {
            $sql .= " AND oom.is_sync = '" . (int)$this->db->escape($data['filter_sync']) . "'";
        }
//        $this->log->write("Info odoo_connector getTotalMappedOrders: received SQL: " . $sql);
        $query = $this->db->query($sql);

        return $query->row['total'];
    }

    public function getOrderStatuses() {
        $query = $this->db->query("SELECT order_status_id, name FROM " . DB_PREFIX . "order_status
        WHERE language_id = '" . (int)$this->config->get('config_language_id') . "'
        ORDER BY name");

        return $query->rows;
    }

    /**
     * Get all payment acquirer mappings
     * @return array
     */
    public function getPaymentAcquirerMappings() {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "odoo_payment_acquirer_map ORDER BY opencart_payment_name");
        return $query->rows;
    }

    /**
     * Get all currency mappings
     * @return array
     */
    public function getCurrencyMappings() {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "odoo_currency_map ORDER BY opencart_currency_code");
        return $query->rows;
    }

    /**
     * Update payment acquirer mapping
     * @param array $data
     * @return array
     */
    public function updatePaymentAcquirerMapping($data) {
        $json = array('success' => false);

        if (isset($data['id']) && isset($data['odoo_acquirer_id']) && isset($data['is_active'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "odoo_payment_acquirer_map
                SET odoo_acquirer_id = '" . (int)$data['odoo_acquirer_id'] . "',
                    odoo_acquirer_name = '" . $this->db->escape($data['odoo_acquirer_name']) . "',
                    is_active = '" . (int)$data['is_active'] . "',
                    modified_on = NOW()
                WHERE id = '" . (int)$data['id'] . "'");

            $json['success'] = true;
            $json['message'] = 'Payment acquirer mapping updated successfully';
        } else {
            $json['error'] = 'Missing required fields';
        }

        return $json;
    }

    /**
     * Update currency mapping
     * @param array $data
     * @return array
     */
    public function updateCurrencyMapping($data) {
        $json = array('success' => false);

        if (isset($data['id']) && isset($data['odoo_currency_id']) && isset($data['is_active'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "odoo_currency_map
                SET odoo_currency_id = '" . (int)$data['odoo_currency_id'] . "',
                    odoo_currency_name = '" . $this->db->escape($data['odoo_currency_name']) . "',
                    is_active = '" . (int)$data['is_active'] . "',
                    modified_on = NOW()
                WHERE id = '" . (int)$data['id'] . "'");

            $json['success'] = true;
            $json['message'] = 'Currency mapping updated successfully';
        } else {
            $json['error'] = 'Missing required fields';
        }

        return $json;
    }

    /**
     * Fetch payment acquirers from Odoo
     * @return array
     */
    public function fetchOdooPaymentAcquirers() {
        $json = array('success' => false, 'data' => array());

        try {
            // Ensure connection is established
            if (!$this->isConnected()) {
                $this->getConfig();
            }

            if (!$this->isConnected()) {
                $json['error'] = 'Odoo connection not established';
                return $json;
            }

            // Fetch payment acquirers using existing connection
            // Filter for published acquirers (website_published = true)
            $acquirers = $this->connection['client']->execute_kw(
                $this->connection['db'],
                $this->connection['userId'],
                $this->connection['pwd'],
                'payment.acquirer',
                'search_read',
                array(array(array('website_published', '=', true))), // only published acquirers
                array(
                    'fields' => array('id', 'name', 'provider', 'website_published'),
                    'order' => 'name asc'
                )
            );

            if ($acquirers) {
                $json['success'] = true;
                $json['data'] = $acquirers;
            } else {
                $json['error'] = 'No acquirers found';
            }
        } catch (Exception $e) {
            $json['error'] = 'Error fetching acquirers: ' . $e->getMessage();
            $this->log->write('Error fetchOdooPaymentAcquirers: ' . $e->getMessage());
        }

        return $json;
    }

    /**
     * Fetch currencies from Odoo
     * @return array
     */
    public function fetchOdooCurrencies() {
        $json = array('success' => false, 'data' => array());

        try {
            // Ensure connection is established
            if (!$this->isConnected()) {
                $this->getConfig();
            }

            if (!$this->isConnected()) {
                $json['error'] = 'Odoo connection not established';
                return $json;
            }

            // Fetch currencies using existing connection
            $currencies = $this->connection['client']->execute_kw(
                $this->connection['db'],
                $this->connection['userId'],
                $this->connection['pwd'],
                'res.currency',
                'search_read',
                array(array(array('active', '=', true))), // only active currencies
                array(
                    'fields' => array('id', 'name', 'active'),
                    'order' => 'name asc'
                )
            );

            if ($currencies) {
                $json['success'] = true;
                $json['data'] = $currencies;
            } else {
                $json['error'] = 'No currencies found';
            }
        } catch (Exception $e) {
            $json['error'] = 'Error fetching currencies: ' . $e->getMessage();
            $this->log->write('Error fetchOdooCurrencies: ' . $e->getMessage());
        }

        return $json;
    }

    /**
     * Cron method to check order statuses and send notifications for orders needing attention
     * This method:
     * 1. Calls syncOpenCartOrderState to update order statuses
     * 2. Identifies OC orders not in odoo_order_map (not created in Odoo)
     * 3. Identifies OC orders still in "draft" state in Odoo (not confirmed)
     * 4. Checks OC orders in CDEK that haven't reached RECEIVED_AT_SHIPMENT_WAREHOUSE status
     * 5. Sends email notification to admin with all orders needing attention
     *
     * @param bool $debug Enable debug mode
     * @return array Result with success status and message
     */
    public function cronCheckOrderStatuses($debug = false) {
        $json = array(
            'success' => false,
            'message' => '',
            'orders_not_in_odoo' => array(),
            'orders_draft_in_odoo' => array(),
            'orders_cdek_not_received' => array(),
            'errors' => array()
        );

        try {
            if ($debug) {
                $this->log->write('cronCheckOrderStatuses: Starting cron job');
            }

            // Step 1: Call syncOpenCartOrderState to update order statuses
            if ($debug) {
                $this->log->write('cronCheckOrderStatuses: Calling syncOpenCartOrderState');
            }
            $sync_result = $this->syncOpenCartOrderState($debug);

            if (!$sync_result['success']) {
                $json['errors'][] = 'Failed to sync order states: ' . implode(', ', $sync_result['errors']);
                if ($debug) {
                    $this->log->write('cronCheckOrderStatuses: syncOpenCartOrderState failed');
                }
            }

            // Step 2: Find OC orders NOT in odoo_order_map (not created in Odoo)
            // Exclude cancelled and completed orders, focus on active orders
            $sql_not_in_odoo = "SELECT o.order_id, o.order_status_id, o.date_added, o.date_modified,
                                CONCAT(o.firstname, ' ', o.lastname) as customer_name, o.email, o.total,
                                os.name as order_status_name
                                FROM " . DB_PREFIX . "order o
                                LEFT JOIN " . DB_PREFIX . "odoo_order_map m ON o.order_id = m.opencart_order_id
                                LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id AND os.language_id = 1)
                                WHERE m.opencart_order_id IS NULL
                                AND o.order_status_id NOT IN (0, 9, 13)
                                AND o.date_added > DATE_SUB(NOW(), INTERVAL 30 DAY)
                                ORDER BY o.date_added DESC";

            $result = $this->db->query($sql_not_in_odoo);
            if ($result->num_rows > 0) {
                $json['orders_not_in_odoo'] = $result->rows;
                if ($debug) {
                    $this->log->write('cronCheckOrderStatuses: Found ' . $result->num_rows . ' orders not in Odoo');
                }
            }

            // Step 3: Find OC orders still in "draft" state in Odoo (not confirmed)
            $sql_draft_in_odoo = "SELECT m.opencart_order_id, m.odoo_order_id, m.odoo_order_state,
                                  m.opencart_order_state, m.modified_on, o.total,
                                  CONCAT(o.firstname, ' ', o.lastname) as customer_name, o.email
                                  FROM " . DB_PREFIX . "odoo_order_map m
                                  LEFT JOIN " . DB_PREFIX . "order o ON m.opencart_order_id = o.order_id
                                  WHERE m.odoo_order_state LIKE '%draft%'
                                  AND m.modified_on > DATE_SUB(NOW(), INTERVAL 7 DAY)
                                  ORDER BY m.modified_on DESC";

            $result = $this->db->query($sql_draft_in_odoo);
            if ($result->num_rows > 0) {
                $json['orders_draft_in_odoo'] = $result->rows;
                if ($debug) {
                    $this->log->write('cronCheckOrderStatuses: Found ' . $result->num_rows . ' draft orders in Odoo');
                }
            }

            // Step 4: Find OC orders in CDEK that need attention (CREATED or ACCEPTED state)
            // These are orders that have been submitted to CDEK but haven't progressed to warehouse/delivery
            $sql_cdek_not_received = "SELECT c.order_id, c.dispatch_number, c.cdek_number, c.status_id,
                                      c.delivery_date, c.last_exchange, c.recipient_name, c.phone,
                                      o.order_status_id, o.total, o.email, os.name as order_status_name
                                      FROM " . DB_PREFIX . "cdek_order c
                                      LEFT JOIN " . DB_PREFIX . "order o ON c.order_id = o.order_id
                                      LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id AND os.language_id = 1)
                                      WHERE c.status_id IN ('CREATED', 'ACCEPTED')
                                      AND c.delivery_date < DATE_ADD(NOW(), INTERVAL 3 DAY)
                                      AND c.last_exchange > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 14 DAY))
                                      ORDER BY c.delivery_date ASC";

            $result = $this->db->query($sql_cdek_not_received);
            if ($result->num_rows > 0) {
                $json['orders_cdek_not_received'] = $result->rows;
                if ($debug) {
                    $this->log->write('cronCheckOrderStatuses: Found ' . $result->num_rows . ' CDEK orders not received');
                }
            }

            // Step 5: Send email notification if any issues found
            $total_issues = count($json['orders_not_in_odoo']) +
                           count($json['orders_draft_in_odoo']) +
                           count($json['orders_cdek_not_received']);

            if ($total_issues > 0) {
                $email_sent = $this->sendOrderStatusNotificationEmail($json, $debug);
                if ($email_sent) {
                    $json['message'] = 'Found ' . $total_issues . ' orders needing attention. Email notification sent.';
                    $json['success'] = true;
                } else {
                    $json['errors'][] = 'Failed to send email notification';
                    $json['message'] = 'Found ' . $total_issues . ' orders needing attention, but email failed.';
                }
            } else {
                $json['success'] = true;
                $json['message'] = 'All orders are in good status. No issues found.';
                if ($debug) {
                    $this->log->write('cronCheckOrderStatuses: No issues found');
                }
            }

        } catch (Exception $e) {
            $json['errors'][] = 'Exception: ' . $e->getMessage();
            $this->log->write('cronCheckOrderStatuses ERROR: ' . $e->getMessage());
        }

        return $json;
    }

    /**
     * Send email notification to admin about orders needing attention
     *
     * @param array $data Order status data from cronCheckOrderStatuses
     * @param bool $debug Enable debug mode
     * @return bool True if email sent successfully
     */
    private function sendOrderStatusNotificationEmail($data, $debug = false) {
        try {
            // Load store settings from database (for cron context where config may not be fully loaded)
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0'");

            $settings = array();
            foreach ($query->rows as $result) {
                if (!$result['serialized']) {
                    $settings[$result['key']] = $result['value'];
                } else {
                    $settings[$result['key']] = json_decode($result['value'], true);
                }
            }

            // Get admin email from database settings
            $admin_email = isset($settings['config_email']) ? $settings['config_email'] : $this->config->get('config_email');
            if (empty($admin_email)) {
                $this->log->write('sendOrderStatusNotificationEmail: No admin email configured');
                return false;
            }

            // Get mail settings (use database settings with fallback to config)
            $mail_engine = isset($settings['config_mail_engine']) ? $settings['config_mail_engine'] : $this->config->get('config_mail_engine');
            $mail_parameter = isset($settings['config_mail_parameter']) ? $settings['config_mail_parameter'] : $this->config->get('config_mail_parameter');
            $config_name = isset($settings['config_name']) ? $settings['config_name'] : $this->config->get('config_name');

            // Create mail object
            $mail = new \Mail($mail_engine ? $mail_engine : 'mail');
            $mail->parameter = $mail_parameter;

            // Set SMTP settings if engine is smtp
            if ($mail_engine == 'smtp') {
                $mail->smtp_hostname = isset($settings['config_mail_smtp_hostname']) ? $settings['config_mail_smtp_hostname'] : '';
                $mail->smtp_username = isset($settings['config_mail_smtp_username']) ? $settings['config_mail_smtp_username'] : '';
                $mail->smtp_password = isset($settings['config_mail_smtp_password']) ? html_entity_decode($settings['config_mail_smtp_password'], ENT_QUOTES, 'UTF-8') : '';
                $mail->smtp_port = isset($settings['config_mail_smtp_port']) ? $settings['config_mail_smtp_port'] : 25;
                $mail->smtp_timeout = isset($settings['config_mail_smtp_timeout']) ? $settings['config_mail_smtp_timeout'] : 5;
            }

            $mail->setTo($admin_email);
            $mail->setFrom($admin_email);
            $mail->setSender($config_name ? $config_name : 'OpenCart');
            $mail->setSubject('Odoo Connector: Заказы требующие внимания - ' . date('d.m.Y H:i:s'));

            // Build email body
            $message = $this->buildEmailMessage($data);

            $mail->setHtml($message);
            $mail->setText(strip_tags($message));

            $mail->send();

            if ($debug) {
                $this->log->write('sendOrderStatusNotificationEmail: Email sent to ' . $admin_email);
            }

            return true;

        } catch (Exception $e) {
            $this->log->write('sendOrderStatusNotificationEmail ERROR: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Build HTML email message with order details
     *
     * @param array $data Order status data
     * @return string HTML formatted email message
     */
    private function buildEmailMessage($data) {
        // Use HTTPS_SERVER constant which points to admin area
        $admin_url = HTTPS_SERVER;

        $message = '<html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif;">';
        $message .= '<h2>Odoo Connector - Заказы требующие внимания</h2>';
        $message .= '<p>Сформировано: ' . date('d.m.Y H:i:s') . '</p>';

        $total_count = count($data['orders_not_in_odoo']) +
                      count($data['orders_draft_in_odoo']) +
                      count($data['orders_cdek_not_received']);

        $message .= '<p><strong>Всего обнаружено проблем: ' . $total_count . '</strong></p>';
        $message .= '<hr/>';

        // Section 1: Orders not in Odoo
        if (!empty($data['orders_not_in_odoo'])) {
            $message .= '<h3 style="color: #c00;">1. Заказы НЕ созданные в Odoo (' . count($data['orders_not_in_odoo']) . ')</h3>';
            $message .= '<p>Эти заказы OpenCart не найдены в таблице odoo_order_map:</p>';
            $message .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
            $message .= '<tr style="background-color: #f0f0f0;">
                            <th>ID Заказа</th>
                            <th>Клиент</th>
                            <th>Email</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Дата создания</th>
                            <th>Действие</th>
                         </tr>';

            foreach ($data['orders_not_in_odoo'] as $order) {
                $order_link = $admin_url . 'index.php?route=sale/order/info&order_id=' . $order['order_id'];
                $status_display = !empty($order['order_status_name']) ? htmlspecialchars($order['order_status_name']) : 'ID: ' . $order['order_status_id'];
                $message .= '<tr>';
                $message .= '<td><a href="' . $order_link . '">' . $order['order_id'] . '</a></td>';
                $message .= '<td>' . htmlspecialchars($order['customer_name']) . '</td>';
                $message .= '<td>' . htmlspecialchars($order['email']) . '</td>';
                $message .= '<td>' . number_format($order['total'], 2) . ' ₽</td>';
                $message .= '<td>' . $status_display . '</td>';
                $message .= '<td>' . $order['date_added'] . '</td>';
                $message .= '<td><span style="color: #c00;">Создать в Odoo</span></td>';
                $message .= '</tr>';
            }
            $message .= '</table><br/>';
        }

        // Section 2: Orders in draft state in Odoo
        if (!empty($data['orders_draft_in_odoo'])) {
            $message .= '<h3 style="color: #f90;">2. Заказы в статусе ЧЕРНОВИК в Odoo (' . count($data['orders_draft_in_odoo']) . ')</h3>';
            $message .= '<p>Эти заказы созданы в Odoo, но не подтверждены (находятся в черновике):</p>';
            $message .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
            $message .= '<tr style="background-color: #f0f0f0;">
                            <th>ID OpenCart</th>
                            <th>ID Odoo</th>
                            <th>Клиент</th>
                            <th>Email</th>
                            <th>Сумма</th>
                            <th>Статус Odoo</th>
                            <th>Изменен</th>
                            <th>Действие</th>
                         </tr>';

            foreach ($data['orders_draft_in_odoo'] as $order) {
                $order_link = $admin_url . 'index.php?route=sale/order/info&order_id=' . $order['opencart_order_id'];
                $message .= '<tr>';
                $message .= '<td><a href="' . $order_link . '">' . $order['opencart_order_id'] . '</a></td>';
                $message .= '<td>' . $order['odoo_order_id'] . '</td>';
                $message .= '<td>' . htmlspecialchars($order['customer_name']) . '</td>';
                $message .= '<td>' . htmlspecialchars($order['email']) . '</td>';
                $message .= '<td>' . number_format($order['total'], 2) . ' ₽</td>';
                $message .= '<td>' . htmlspecialchars($order['odoo_order_state']) . '</td>';
                $message .= '<td>' . $order['modified_on'] . '</td>';
                $message .= '<td><span style="color: #f90;">Подтвердить в Odoo</span></td>';
                $message .= '</tr>';
            }
            $message .= '</table><br/>';
        }

        // Section 3: CDEK orders in early stages (CREATED/ACCEPTED)
        if (!empty($data['orders_cdek_not_received'])) {
            $message .= '<h3 style="color: #00c;">3. Заказы СДЭК требующие внимания (Статус: CREATED/ACCEPTED) (' . count($data['orders_cdek_not_received']) . ')</h3>';
            $message .= '<p>Эти заказы находятся на ранних стадиях СДЭК (CREATED или ACCEPTED) и требуют внимания:</p>';
            $message .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
            $message .= '<tr style="background-color: #f0f0f0;">
                            <th>ID Заказа</th>
                            <th>Номер СДЭК</th>
                            <th>Получатель</th>
                            <th>Телефон</th>
                            <th>Сумма</th>
                            <th>Статус OpenCart</th>
                            <th>Статус СДЭК</th>
                            <th>Дата доставки</th>
                            <th>Действие</th>
                         </tr>';

            foreach ($data['orders_cdek_not_received'] as $order) {
                $order_link = $admin_url . 'index.php?route=sale/order/info&order_id=' . $order['order_id'];
                $oc_status_display = !empty($order['order_status_name']) ? htmlspecialchars($order['order_status_name']) : 'ID: ' . $order['order_status_id'];
                $message .= '<tr>';
                $message .= '<td><a href="' . $order_link . '">' . $order['order_id'] . '</a></td>';
                $message .= '<td>' . htmlspecialchars($order['cdek_number']) . '</td>';
                $message .= '<td>' . htmlspecialchars($order['recipient_name']) . '</td>';
                $message .= '<td>' . htmlspecialchars($order['phone']) . '</td>';
                $message .= '<td>' . number_format($order['total'], 2) . ' ₽</td>';
                $message .= '<td>' . $oc_status_display . '</td>';
                $message .= '<td>' . htmlspecialchars($order['status_id']) . '</td>';
                $message .= '<td>' . $order['delivery_date'] . '</td>';
                $message .= '<td><span style="color: #00c;">Проверить статус СДЭК</span></td>';
                $message .= '</tr>';
            }
            $message .= '</table><br/>';
        }

        // Footer with errors if any
        if (!empty($data['errors'])) {
            $message .= '<hr/>';
            $message .= '<h3 style="color: #c00;">Ошибки при обработке:</h3>';
            $message .= '<ul>';
            foreach ($data['errors'] as $error) {
                $message .= '<li>' . htmlspecialchars($error) . '</li>';
            }
            $message .= '</ul>';
        }

        $message .= '<hr/>';
        $message .= '<p style="font-size: 12px; color: #666;">Это автоматическое уведомление от задачи Odoo Connector cron.</p>';
        $message .= '</body></html>';

        return $message;
    }

    /**
     * Update payment.transaction in Odoo with actual CDEK payment data
     * Called when CDEK order status changes to ACCEPTED or DELIVERED
     * Does NOT manage transaction state - only updates amount and CDEK reference data
     *
     * @param int $oc_order_id OpenCart order ID
     * @param array $cdek_order_info CDEK API response with delivery_detail
     * @param string $cdek_uuid CDEK order UUID (dispatch_number from cdek_order table)
     * @param string $cdek_number CDEK tracking number
     * @return array Result with success status and message
     */
    public function updateCdekPaymentTransaction($oc_order_id, $cdek_order_info, $cdek_uuid = '', $cdek_number = '')
    {
        $result = array(
            'success' => false,
            'message' => '',
            'transaction_id' => null
        );

        // Get order data
        $order_query = $this->db->query(
            "SELECT payment_code, payment_method, total, currency_code, email
            FROM " . DB_PREFIX . "order
            WHERE order_id = " . (int)$oc_order_id
        );

        if (!$order_query->num_rows) {
            $result['message'] = 'Order #' . $oc_order_id . ' not found';
            return $result;
        }

        $order_data = $order_query->row;

        // Only process cod_cdek orders
        if ($order_data['payment_code'] != 'cod_cdek') {
            $result['message'] = 'Order #' . $oc_order_id . ' is not cod_cdek payment method';
            return $result;
        }

        // Check if delivery_detail has payment_sum
        if (empty($cdek_order_info['entity']['delivery_detail']['payment_sum'])) {
            $result['message'] = 'No payment_sum in CDEK delivery_detail for order #' . $oc_order_id;
            if ($this->debug) {
                $this->log->write('Model odoo_connector updateCdekPaymentTransaction: ' . $result['message']);
            }
            return $result;
        }

        $delivery_detail = $cdek_order_info['entity']['delivery_detail'];
        $payment_sum = (float)$delivery_detail['payment_sum'];

        // Use existing connection if available, otherwise connect
        if (!$this->connection || !isset($this->connection['status']) || !$this->connection['status']) {
            $this->getConfig();
        }

        $odoo_connect = $this->connection;

        if (!$odoo_connect || !$odoo_connect['status']) {
            $result['message'] = 'Failed to connect to Odoo: ' . ($odoo_connect['error'] ?? 'Unknown error');
            $this->log->write('Model odoo_connector updateCdekPaymentTransaction: ' . $result['message']);
            return $result;
        }

        $models = $odoo_connect['client'];
        $uid = $odoo_connect['userId'];
        $password = $odoo_connect['pwd'];
        $db_name = $odoo_connect['db'];

        // Payment transaction reference format: OC-<order_id>
        $tx_reference = 'OC-' . $oc_order_id;

        // Search for existing transaction
        $existing_tx = $models->execute_kw($db_name, $uid, $password,
            'payment.transaction', 'search_read',
            array(array(array('reference', '=', $tx_reference))),
            array('fields' => array('id', 'reference', 'amount', 'acquirer_reference', 'tx_url'))
        );

        // Get currency mapping
        $currency_mapping = $this->getCurrencyMapping($order_data['currency_code']);
        $currency_id = $currency_mapping ? (int)$currency_mapping['odoo_currency_id'] : 36; // Default to RUB

        // Prepare transaction data to update/create
        // NOTE: We do NOT set 'state' - let Odoo manage payment workflow states
        $transaction_data = array(
            'amount' => $payment_sum,
            'acquirer_reference' => $cdek_number ?: '',
            'tx_url' => $cdek_uuid ?: '',
        );

        if (!empty($existing_tx)) {
            // Update existing transaction
            $tx_id = $existing_tx[0]['id'];

            // Only update if amount has changed or CDEK references are missing
            $needs_update = false;
            if ((float)$existing_tx[0]['amount'] != $payment_sum) {
                $needs_update = true;
            }
            if (empty($existing_tx[0]['acquirer_reference']) && !empty($cdek_number)) {
                $needs_update = true;
            }
            if (empty($existing_tx[0]['tx_url']) && !empty($cdek_uuid)) {
                $needs_update = true;
            }

            if ($needs_update) {
                $update_response = $models->execute_kw($db_name, $uid, $password,
                    'payment.transaction', 'write',
                    array(array($tx_id), $transaction_data)
                );

                if (isset($update_response['faultCode']) && $update_response['faultCode']) {
                    $error_detail = !empty($update_response['faultString']) ? $update_response['faultString'] : $update_response['faultCode'];
                    $result['message'] = 'Error updating payment.transaction: ' . $error_detail;
                    $this->log->write('Model odoo_connector updateCdekPaymentTransaction: ' . $result['message']);
                    return $result;
                } else {
                    $result['success'] = true;
                    $result['transaction_id'] = $tx_id;
                    $result['message'] = 'Updated payment.transaction ID: ' . $tx_id . ' with amount: ' . $payment_sum;

                    if ($this->debug) {
                        $this->log->write('Model odoo_connector updateCdekPaymentTransaction: ' . $result['message']);
                    }
                }
            } else {
                $result['success'] = true;
                $result['transaction_id'] = $tx_id;
                $result['message'] = 'Transaction ID: ' . $tx_id . ' already up to date (amount: ' . $payment_sum . ')';

                if ($this->debug) {
                    $this->log->write('Model odoo_connector updateCdekPaymentTransaction: ' . $result['message']);
                }
            }
        } else {
            // Transaction doesn't exist - create it
            // Get payment acquirer mapping for cod_cdek
            $acquirer_mapping = $this->getPaymentAcquirerMapping('cod_cdek');

            if (!$acquirer_mapping || !$acquirer_mapping['odoo_acquirer_id']) {
                $result['message'] = 'No active payment acquirer mapping found for cod_cdek';
                if ($this->debug) {
                    $this->log->write('Model odoo_connector updateCdekPaymentTransaction: ' . $result['message']);
                }
                return $result;
            }

            // Use checkClient to get partner_id (same pattern as createOdooOrder)
            $clean_email = preg_replace('/\s*/', '', $order_data['email']);
            $clean_email = strtolower($clean_email);
            $odoo_partner = $this->checkClient($clean_email);

            if (!$odoo_partner) {
                $result['message'] = 'Partner not found in odoo_client_map for email: ' . $clean_email;
                $this->log->write('Model odoo_connector updateCdekPaymentTransaction: ' . $result['message']);
                return $result;
            }

            $create_data = array(
                'acquirer_id' => (int)$acquirer_mapping['odoo_acquirer_id'],
                'partner_id' => (int)$odoo_partner,
                'reference' => $tx_reference,
                'currency_id' => $currency_id,
            ) + $transaction_data; // Merge with amount, acquirer_reference, tx_url

            $create_response = $models->execute_kw($db_name, $uid, $password,
                'payment.transaction', 'create',
                array($create_data)
            );

            if (isset($create_response['faultCode']) && $create_response['faultCode']) {
                $error_detail = !empty($create_response['faultString']) ? $create_response['faultString'] : $create_response['faultCode'];
                $result['message'] = 'Error creating payment.transaction: ' . $error_detail;
                $this->log->write('Model odoo_connector updateCdekPaymentTransaction: ' . $result['message']);
                return $result;
            } else {
                $result['success'] = true;
                $result['transaction_id'] = $create_response;
                $result['message'] = 'Created payment.transaction ID: ' . $create_response . ' with amount: ' . $payment_sum;

                if ($this->debug) {
                    $this->log->write('Model odoo_connector updateCdekPaymentTransaction: ' . $result['message']);
                }
            }
        }

        return $result;
    }
}
