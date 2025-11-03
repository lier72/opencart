<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 17/01/25
 * Time: 14:30
 */

// model/extension/module/odoo_price_sync.php
class ModelExtensionModuleOdooPriceSync extends Model {
    // Status constants for sync
    const SYNC_STATUS = [
        'pending' => 'pending',
        'synced' => 'synced',
        'error' => 'error'
    ];

    const SYNC_DIRECTION = [
        'to_odoo' => 'to_odoo',
        'from_odoo' => 'from_odoo',
        'bidirectional' => 'bidirectional'
    ];

    const PRICE_TYPES = [
        'special' => 'special',
        'discount' => 'discount',
        'default' => 'default'
    ];

    const MAX_PRICE_VALUE = 999999999.99; // Just to find minimum price

    private $debug;

    function __construct($registry)
    {
        parent::__construct($registry);
        $this->debug = $this->getDebug();
    }


    /**
     * Returns debug configuration parameter
     *
     * @return bool
     */
    private function getDebug(){
        $result = $this->db->query("SELECT `value` FROM " . DB_PREFIX . "odoo_config  WHERE `key` = 'debug'");
        if ($result->row) return $result->row['value'];
        return False;
    }

    /**
     * Get price list mapping by one of price_list ids
     * @param int $odoo_pricelist_id Odoo Price List ID
     * @return int $opencart_customer_group_id  OpenCart Customer Group
     */
    public function getOpenCartGroupId($odoo_pricelist_id) {
        $sql = "SELECT opencart_customer_group_id 
        FROM " . DB_PREFIX . "odoo_pricelist_map 
        WHERE odoo_pricelist_id = '" . (int)$odoo_pricelist_id . "'
        AND is_active = 1";
        $query = $this->db->query($sql);
        return $query->num_rows ? (int)$query->row['opencart_customer_group_id'] : false;
    }

    /**
     * Get single pricelist mapping by ID
     * @param int $mapping_id Mapping ID
     * @return array|bool Mapping data or false if not found
     */
    public function getPricelistMapping($mapping_id) {
        $sql = "SELECT m.*, cg.name as customer_group_name 
            FROM " . DB_PREFIX . "odoo_pricelist_map m
            LEFT JOIN " . DB_PREFIX . "customer_group_description cg 
                ON m.opencart_customer_group_id = cg.customer_group_id 
                AND cg.language_id = '" . (int)$this->config->get('config_language_id') . "'
            WHERE m.id = '" . (int)$mapping_id . "'";

        $query = $this->db->query($sql);
        return $query->num_rows ? $query->row : false;
    }

    // Utility methods

//    /**
//     * Validate price data structure
//     * @param array $price_data Price data to validate
//     * @param string $price_type Price type (special/discount)
//     * @return bool|string True if valid, error message if invalid
//     */
//    public function validatePriceData($price_data, $price_type) {
//        if (!isset($price_data['prices']) || !is_array($price_data['prices'])) {
//            return "Invalid price data structure";
//        }
//
//        foreach ($price_data['prices'] as $price) {
//            if (!isset($price['price']) || !is_numeric($price['price'])) {
//                return "Invalid price value";
//            }
//
//            if ($price_type == self::PRICE_TYPES['discount']) {
//                if (!isset($price['quantity']) || !is_numeric($price['quantity']) || $price['quantity'] < 1) {
//                    return "Invalid quantity for discount price";
//                }
//            }
//
//            if (isset($price['date_start']) && $price['date_start'] != '0000-00-00') {
//                if (!strtotime($price['date_start'])) {
//                    return "Invalid date_start format";
//                }
//            }
//
//            if (isset($price['date_end']) && $price['date_end'] != '0000-00-00') {
//                if (!strtotime($price['date_end'])) {
//                    return "Invalid date_end format";
//                }
//            }
//        }
//
//        return true;
//    }

    /**
     * Get current prices for a product by mapping
     * @param int $product_id Product ID
     * @param int $opencart_group_id Oencart Group ID
     * @return array Current prices data
     */
    public function getActualProductPrice($product_id, $opencart_group_id) {

        $result = [
            'product_id' => $product_id,
            'price_type' => 'default',
            'customer_group_id' => $opencart_group_id,
            'price' => 0
        ];

        // Get standard price
        $default = $this->db->query("SELECT price FROM " . DB_PREFIX . "product WHERE product_id = " . (int)$product_id);

        // Get special price
        $special = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special 
        WHERE product_id = '" . (int)$product_id . "' 
        AND customer_group_id = '" . (int)$opencart_group_id . "'
        AND ((date_start = '0000-00-00' OR date_start < NOW()) 
        AND (date_end = '0000-00-00' OR date_end > NOW()))
        LIMIT 1");

        // Get discount price
        $discount = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_discount 
        WHERE product_id = '" . (int)$product_id . "'
        AND customer_group_id = '" . (int)$opencart_group_id  . "'
        AND ((date_start = '0000-00-00' OR date_start < NOW())
        AND (date_end = '0000-00-00' OR date_end > NOW()))
        AND quantity < 2 
        LIMIT 1");

        // Initialize prices array
        $prices = array(
            'default' => $default->row ? $default->row['price'] : self::MAX_PRICE_VALUE,
            'special' => $special->row ? $special->row['price'] : self::MAX_PRICE_VALUE,
            'discount' => $discount->row ? $discount->row['price'] : self::MAX_PRICE_VALUE
        );

        // Find minimum price and its type
        $min_price = self::MAX_PRICE_VALUE;;
        $price_type = 'default';

        foreach ($prices as $type => $price) {
            if ($price < $min_price) {
                $min_price = $price;
                $price_type = $type;
            }
        }

        // If no actual price was found, return 0
        if ($min_price === self::MAX_PRICE_VALUE) {
            $min_price = 0;
        }

        $result['price'] = $min_price;
        $result['price_type'] = $price_type;

        return $result;
    }

    /**
     * Update product prices based on mapping
     * @param int $product_id Product ID
     * @param $customer_group_id
     * @param int $price New price
     * @return bool Success status
     */
    public function updateProductPrices($product_id, $customer_group_id, $price)
    {
        try {
            // Validate input
            $product_id = (int)$product_id;
            $customer_group_id = (int)$customer_group_id;
            $price = (float)$price;

            // Get current prices for comparison
            $current_prices = $this->getActualProductPrice($product_id, $customer_group_id);
            if ($price == $current_prices['price']) {
                // Log the synchronization process
                $this->logPriceSync([
                    'product_id' => $product_id,
                    'customer_group_id' => $customer_group_id,
                    'old_price' => $current_prices ? $current_prices['price'] : null,
                    'new_price' => $price,
                    'sync_direction' => self::SYNC_DIRECTION['from_odoo'],
                    'status' => self::SYNC_STATUS['synced'],
                    'message' => 'Price has not changed'
                ]);
                return true;
            }

            // Get the default customer group ID
            $default_group_id = $this->config->get('config_customer_group_id');
            $default_price = $this->getDefaultProductPrice($product_id);
//            $this->log->write("Product ID: " .$product_id. " Price:" . $default_price . " customer_default_group_id: " . $default_group_id );

            $this->db->query("START TRANSACTION");

            if ($customer_group_id == $default_group_id) {
                // Handle default customer group prices
                if ($this->debug) $this->log->write("Handle default Product ID: " .$product_id. " Price:" . $default_price . " current Price: " .$current_prices['price'] );
                $this->handleDefaultGroupPrice($product_id, $price, $default_price, $current_prices);
            } else {
                // Handle non-default customer group prices
                if ($this->debug) $this->log->write("Handle non Default Product ID: " .$product_id. " Price:" . $price . " customer_default_group_id: " .$default_group_id . " customer_group_id: ". $customer_group_id);
                $this->handleNonDefaultGroupPrice($product_id, $customer_group_id, $price, $current_prices);
            }

            // Log the synchronization process
            $this->logPriceSync([
                'product_id' => $product_id,
                'customer_group_id' => $customer_group_id,
                'old_price' => $current_prices ? $current_prices['price'] : null,
                'new_price' => $price,
                'sync_direction' => self::SYNC_DIRECTION['from_odoo'],
                'status' => self::SYNC_STATUS['synced'],
                'message' => 'Price updated successfully'
            ]);

            $this->db->query("COMMIT");
            return true;
        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            $this->log->write("Error updating product prices: " . $e->getMessage());

            $this->logPriceSync([
                'product_id' => $product_id,
                'customer_group_id' => $customer_group_id,
                'old_price' => null,
                'new_price' => null,
                'sync_direction' => self::SYNC_DIRECTION['from_odoo'],
                'status' => self::SYNC_STATUS['error'],
                'message' => $e->getMessage()
            ]);

            return false;
        }
    }

    private function getDefaultProductPrice($product_id)
    {
        $result = $this->db->query("SELECT `price` FROM " . DB_PREFIX . "product WHERE product_id = $product_id");
        return $result->row['price'];
    }

    private function handleDefaultGroupPrice($product_id, $price, $default_price, $current_prices)
    {
        // 1. First remove any discount prices for default group as they shouldn't exist
        $this->removeDiscountPrice($product_id, $this->config->get('config_customer_group_id'));

        // 2. Handle price update based on comparison with default price
        if ($price > $default_price) {
            // Remove special prices and update base price
            $this->removeSpecialPrice($product_id, $this->config->get('config_customer_group_id'));
            $this->updateProductPrice($product_id, $price);
        } else {
            // Remove existing special price and insert new one
            $this->removeSpecialPrice($product_id, $this->config->get('config_customer_group_id'));
            $this->insertSpecialPrice($product_id, $price, $this->config->get('config_customer_group_id'));
        }
    }


    private function handleNonDefaultGroupPrice($product_id, $customer_group_id, $price, $current_prices)
    {
        $default_price = $this->getDefaultProductPrice($product_id);

        // If new price is bigger than default price, log warning and proceed
        if ($price > $default_price) {
            $this->logPriceSync([
                'product_id' => $product_id,
                'customer_group_id' => $customer_group_id,
                'old_price' => $current_prices['price'],
                'new_price' => $price,
                'sync_direction' => self::SYNC_DIRECTION['from_odoo'],
                'status' => self::SYNC_STATUS['synced'],
                'message' => 'Warning: Non-default group price ' . $price . ' is higher than default price ' . $default_price
            ]);
        }

        // Handle based on current price type
        switch ($current_prices['price_type']) {
            case 'special':
                $this->removeSpecialPrice($product_id, $customer_group_id);
                $this->insertSpecialPrice($product_id, $price, $customer_group_id);
                break;

            case 'discount':
                $this->removeDiscountPrice($product_id, $customer_group_id);
                $this->insertDiscountPrice($product_id, $price, $customer_group_id);
                break;

            default:
                // If no existing special/discount price, create discount
                $this->removeDiscountPrice($product_id, $customer_group_id);
                $this->insertDiscountPrice($product_id, $price, $customer_group_id);
        }
    }


    private function updateProductPrice($product_id, $price)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product SET `price` = $price WHERE product_id = $product_id");
    }

    private function removeSpecialPrice($product_id, $customer_group_id = null)
    {
        $query = "DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = $product_id";
        if ($customer_group_id) {
            $query .= " AND customer_group_id = $customer_group_id";
        }
        $this->db->query($query);
    }

    private function updateSpecialPrice($product_id, $price, $customer_group_id = null)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product_special SET `price` = $price, date_start = NOW()  WHERE product_id = $product_id" . ($customer_group_id ? " AND customer_group_id = $customer_group_id" : ""));
    }

    private function insertSpecialPrice($product_id, $price, $customer_group_id)
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET `price` = $price, product_id = $product_id, customer_group_id = $customer_group_id, date_start = NOW()");
    }

    private function removeDiscountPrice($product_id, $customer_group_id = null)
    {
        $query = "DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = $product_id";
        if ($customer_group_id) {
            $query .= " AND customer_group_id = $customer_group_id";
        }
        $this->db->query($query);
    }

    private function insertDiscountPrice($product_id, $price, $customer_group_id)
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET `price` = $price, product_id = $product_id, quantity = 1, customer_group_id = $customer_group_id, date_start = NOW()");
    }

    /**
     * Log price sync event with deduplication
     * @param array $data Log data
     * @return int|bool Log ID on success, false on failure
     */
    public function logPriceSync($data) {
        try {
            // Get the last log entry for this product/pricelist combination
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "odoo_price_sync_log 
            WHERE product_id = '" . (int)$data['product_id'] . "' 
            AND customer_group_id = '" . (int)$data['customer_group_id'] . "' 
            ORDER BY last_check DESC LIMIT 1");

            if ($query->num_rows) {
                $last_log = $query->row;

                // Check if this is a repeated message
                if ($last_log['old_price'] == $data['old_price'] &&
                    $last_log['new_price'] == $data['new_price'] &&
                    $last_log['status'] == $data['status'] &&
                    $last_log['sync_direction'] == $data['sync_direction']) {

                    // Update existing entry
                    $this->db->query("UPDATE " . DB_PREFIX . "odoo_price_sync_log SET 
                    last_check = NOW(),
                    repeat_count = repeat_count + 1,
                    message = CONCAT('" . $this->db->escape($data['message']) . " (Repeated check #', repeat_count + 1, ')')
                    WHERE id = '" . (int)$last_log['id'] . "'");

                    return $last_log['id'];
                }
            }

            // If not a repeat or no previous entry exists, create new log entry
            $this->db->query("INSERT INTO " . DB_PREFIX . "odoo_price_sync_log SET 
            product_id = '" . (int)$data['product_id'] . "',
            customer_group_id = '" . (int)$data['customer_group_id'] . "',
            old_price = '" . (float)$data['old_price'] . "',
            new_price = '" . (float)$data['new_price'] . "',
            sync_direction = '" . $this->db->escape($data['sync_direction']) . "',
            status = '" . $this->db->escape($data['status']) . "',
            message = '" . $this->db->escape($data['message']) . "',
            first_check = NOW(),
            last_check = NOW(),
            repeat_count = 0");

            return $this->db->getLastId();
        } catch (Exception $e) {
            $this->log->write("Error logging price sync: " . $e->getMessage());
            return false;
        }
    }

}