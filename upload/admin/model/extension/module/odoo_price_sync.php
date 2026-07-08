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
    private $price_sync_log_columns = null;

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

    private function debugLog($message) {
        if ($this->debug) {
            $this->log->write($message);
        }
    }

    private function getPriceSyncLogColumns() {
        if ($this->price_sync_log_columns !== null) {
            return $this->price_sync_log_columns;
        }

        $this->price_sync_log_columns = array();

        try {
            $query = $this->db->query("SHOW COLUMNS FROM " . DB_PREFIX . "odoo_price_sync_log");

            foreach ($query->rows as $row) {
                $this->price_sync_log_columns[$row['Field']] = true;
            }
        } catch (Exception $e) {
            $this->log->write("Error reading price sync log schema: " . $e->getMessage());
        }

        return $this->price_sync_log_columns;
    }

    private function getPriceSyncLogDateColumn() {
        $columns = $this->getPriceSyncLogColumns();

        if (isset($columns['created_on'])) {
            return 'created_on';
        }

        if (isset($columns['last_check'])) {
            return 'last_check';
        }

        if (isset($columns['first_check'])) {
            return 'first_check';
        }

        return 'id';
    }

    private function isPriceHistoryData($data) {
        if (!isset($data['product_id'], $data['customer_group_id'], $data['old_price'], $data['new_price'])) {
            return false;
        }

        if ((int)$data['product_id'] <= 0 || (int)$data['customer_group_id'] <= 0) {
            return false;
        }

        if (!isset($data['status']) || $data['status'] !== self::SYNC_STATUS['synced']) {
            return false;
        }

        return abs((float)$data['old_price'] - (float)$data['new_price']) > 0.00001;
    }

    private function getPriceHistoryWhereClause() {
        return " AND l.status = '" . self::SYNC_STATUS['synced'] . "'
            AND l.old_price IS NOT NULL
            AND l.new_price IS NOT NULL
            AND l.old_price <> l.new_price
            AND (l.message IS NULL OR l.message = '' OR l.message NOT LIKE 'Warning:%')";
    }
    /**
     * Create new price list mapping
     * @param array $data Mapping data
     * @return int|bool Mapping ID on success, false on failure
     */
    public function createPricelistMapping($data) {
        try {
            $this->db->query("INSERT INTO " . DB_PREFIX . "odoo_pricelist_map SET 
                odoo_pricelist_id = '" . (int)$data['odoo_pricelist_id'] . "',
                opencart_customer_group_id = '" . (int)$data['opencart_customer_group_id'] . "',
                odoo_pricelist_name = '" . $this->db->escape($data['odoo_pricelist_name']) . "',
                price_type = '" . $this->db->escape($data['price_type']) . "',
                sync_direction = '" . $this->db->escape($data['sync_direction']) . "',
                is_active = '" . (isset($data['is_active']) ? (int)$data['is_active'] : 1) . "',
                created_by = '" . $this->db->escape($data['created_by']) . "'");

            return $this->db->getLastId();
        } catch (Exception $e) {
            $this->log->write("Error creating pricelist mapping: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all price list mappings with optional filters
     * @param array $filters Optional filters
     * @return array Mappings
     */
    public function getPricelistMappings($filters = []) {
        $sql = "SELECT m.*, cg.name as customer_group_name 
            FROM " . DB_PREFIX . "odoo_pricelist_map m
            LEFT JOIN " . DB_PREFIX . "customer_group_description cg 
                ON m.opencart_customer_group_id = cg.customer_group_id 
                AND cg.language_id = '" . (int)$this->config->get('config_language_id') . "'
            WHERE 1=1";

        $sql .= " ORDER BY m.modified_on DESC";

        $query = $this->db->query($sql);
        return $query->rows;
    }

    /**
     * Update price list mapping
     * @param int $mapping_id Mapping ID
     * @param array $data Update data
     * @return bool Success status
     */
    public function updatePricelistMapping($mapping_id, $data) {
        try {
            // Check for duplicate mapping if pricelist_id or customer_group_id is being changed
            if (isset($data['odoo_pricelist_id']) || isset($data['opencart_customer_group_id'])) {
                // Get current mapping
                $current = $this->getPricelistMapping($mapping_id);

                $check_pricelist_id = isset($data['odoo_pricelist_id']) ? (int)$data['odoo_pricelist_id'] : $current['odoo_pricelist_id'];
                $check_customer_group_id = isset($data['opencart_customer_group_id']) ? (int)$data['opencart_customer_group_id'] : $current['opencart_customer_group_id'];
                $check_price_type = isset($data['price_type']) ? $data['price_type'] : $current['price_type'];

                // Check if this combination already exists (excluding current mapping)
                $duplicate_check = $this->db->query("SELECT id FROM " . DB_PREFIX . "odoo_pricelist_map
                    WHERE odoo_pricelist_id = '" . (int)$check_pricelist_id . "'
                    AND opencart_customer_group_id = '" . (int)$check_customer_group_id . "'
                    AND price_type = '" . $this->db->escape($check_price_type) . "'
                    AND id != '" . (int)$mapping_id . "'");

                if ($duplicate_check->num_rows > 0) {
                    throw new Exception("A mapping with this pricelist and customer group combination already exists");
                }
            }

            $update_fields = [];

            if (isset($data['odoo_pricelist_id'])) {
                $update_fields[] = "odoo_pricelist_id = '" . (int)$data['odoo_pricelist_id'] . "'";
            }

            if (isset($data['opencart_customer_group_id'])) {
                $update_fields[] = "opencart_customer_group_id = '" . (int)$data['opencart_customer_group_id'] . "'";
            }

            if (isset($data['odoo_pricelist_name'])) {
                $update_fields[] = "odoo_pricelist_name = '" . $this->db->escape($data['odoo_pricelist_name']) . "'";
            }

            if (isset($data['is_active'])) {
                $update_fields[] = "is_active = '" . (int)$data['is_active'] . "'";
            } else {
                $update_fields[] = "is_active = 0";
            }

            if (isset($data['sync_direction'])) {
                $update_fields[] = "sync_direction = '" . $this->db->escape($data['sync_direction']) . "'";
            }

            if (!empty($update_fields)) {
                $this->db->query("UPDATE " . DB_PREFIX . "odoo_pricelist_map SET
                    " . implode(', ', $update_fields) . "
                    WHERE id = '" . (int)$mapping_id . "'");
            }

            return true;
        } catch (Exception $e) {
            $this->log->write("Error updating pricelist mapping: " . $e->getMessage());
            throw $e; // Re-throw so controller can handle it
        }
    }

    /**
     * Delete pricelist mapping
     * @param int $mapping_id Mapping ID
     * @return bool Success status
     */
    public function deletePricelistMapping($mapping_id) {
        try {
            $this->db->query("DELETE FROM " . DB_PREFIX . "odoo_pricelist_map WHERE id = '" . (int)$mapping_id . "'");
            return true;
        } catch (Exception $e) {
            $this->log->write("Error deleting pricelist mapping: " . $e->getMessage());
            return false;
        }
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

    /**
     * Validate price data structure
     * @param array $price_data Price data to validate
     * @param string $price_type Price type (special/discount)
     * @return bool|string True if valid, error message if invalid
     */
    public function validatePriceData($price_data, $price_type) {
        if (!isset($price_data['prices']) || !is_array($price_data['prices'])) {
            return "Invalid price data structure";
        }

        foreach ($price_data['prices'] as $price) {
            if (!isset($price['price']) || !is_numeric($price['price'])) {
                return "Invalid price value";
            }

            if ($price_type == self::PRICE_TYPES['discount']) {
                if (!isset($price['quantity']) || !is_numeric($price['quantity']) || $price['quantity'] < 1) {
                    return "Invalid quantity for discount price";
                }
            }

            if (isset($price['date_start']) && $price['date_start'] != '0000-00-00') {
                if (!strtotime($price['date_start'])) {
                    return "Invalid date_start format";
                }
            }

            if (isset($price['date_end']) && $price['date_end'] != '0000-00-00') {
                if (!strtotime($price['date_end'])) {
                    return "Invalid date_end format";
                }
            }
        }

        return true;
    }

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
                $this->debugLog("Odoo price sync unchanged: Product ID " . $product_id . ", Customer Group ID " . $customer_group_id . ", Price " . $price);
                return true;
            }

            // Get the default customer group ID
            $default_group_id = $this->config->get('config_customer_group_id');
            $default_price = $this->getDefaultProductPrice($product_id);

            $this->db->query("START TRANSACTION");

            if ($customer_group_id == $default_group_id) {
                // Handle default customer group prices
                $this->debugLog("Handle default Product ID: " .$product_id. " Price:" . $default_price . " current Price: " .$current_prices['price'] );
                $this->handleDefaultGroupPrice($product_id, $price, $default_price, $current_prices);
            } else {
                // Handle non-default customer group prices
                $this->debugLog("Handle non Default Product ID: " .$product_id. " Price:" . $price . " customer_default_group_id: " .$default_group_id . " customer_group_id: ". $customer_group_id);
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

//    private function handleDefaultGroupPrice($product_id, $price, $default_price, $current_prices)
//    {
//        switch ($current_prices['price_type']) {
//            case 'special':
//                if ($price > $default_price) {
//                    $this->removeSpecialPrice($product_id);
//                    $this->updateProductPrice($product_id, $price);
//                } else {
//                    $this->updateSpecialPrice($product_id, $price);
//                }
//                break;
//            case 'discount':
//                $this->removeDiscountPrice($product_id);
//                if ($price > $default_price) {
//                    $this->updateProductPrice($product_id, $price);
//                } else {
//                    $this->insertSpecialPrice($product_id, $default_price, $this->getDefaultProductPrice($product_id));
//                }
//                break;
//            case 'default':
//                $this->updateProductPrice($product_id, $price);
//                break;
//        }
//    }

    private function handleNonDefaultGroupPrice($product_id, $customer_group_id, $price, $current_prices)
    {
        $default_price = $this->getDefaultProductPrice($product_id);

        // If new price is bigger than default price, log warning and proceed
        if ($price > $default_price) {
            $this->debugLog('Warning: Non-default group price ' . $price . ' is higher than default price ' . $default_price . ' for Product ID ' . $product_id . ', Customer Group ID ' . $customer_group_id);
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

//    private function handleNonDefaultGroupPrice($product_id, $customer_group_id, $price, $current_prices)
//    {
//        var_dump($current_prices);
//
//        switch ($current_prices['price_type']) {
//            case 'special':
//                $this->removeSpecialPrice($product_id, $customer_group_id);
//                $this->insertSpecialPrice($product_id, $price, $customer_group_id);
//                break;
//            case 'discount':
//                $this->removeDiscountPrice($product_id, $customer_group_id);
//                $this->insertDiscountPrice($product_id, $price, $customer_group_id);
//                break;
//            case 'default':
//                $this->updateProductPrice($product_id, $price);
//                break;
//        }
//    }

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
     * Store compact price history only for completed price changes.
     * @param array $data Log data
     * @return int|bool Log ID on success, false on failure
     */
    public function logPriceSync($data) {
        try {
            if (!$this->isPriceHistoryData($data)) {
                $this->debugLog("Odoo price sync diagnostic: " . (isset($data['message']) ? $data['message'] : 'No price history row created'));
                return false;
            }

            $columns = $this->getPriceSyncLogColumns();
            $fields = array(
                "product_id = '" . (int)$data['product_id'] . "'",
                "customer_group_id = '" . (int)$data['customer_group_id'] . "'",
                "old_price = '" . (float)$data['old_price'] . "'",
                "new_price = '" . (float)$data['new_price'] . "'",
                "sync_direction = '" . $this->db->escape($data['sync_direction']) . "'",
                "status = '" . self::SYNC_STATUS['synced'] . "'",
                "message = ''"
            );

            if (isset($columns['created_on'])) {
                $fields[] = "created_on = NOW()";
            }

            if (isset($columns['first_check'])) {
                $fields[] = "first_check = NOW()";
            }

            if (isset($columns['last_check'])) {
                $fields[] = "last_check = NOW()";
            }

            if (isset($columns['repeat_count'])) {
                $fields[] = "repeat_count = 0";
            }

            $this->db->query("INSERT INTO " . DB_PREFIX . "odoo_price_sync_log SET " . implode(", ", $fields));

            return $this->db->getLastId();
        } catch (Exception $e) {
            $this->log->write("Error logging price sync history: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get sync history - Universal method for both product and mapping histories
     * @param array $params Parameters including product_id or mapping_id
     * @param array $filters Optional filters
     * @return array History records
     */
    public function getSyncHistory($params = [], $filters = []) {
        $date_column = $this->getPriceSyncLogDateColumn();

        $sql = "SELECT l.*, 
        m.odoo_pricelist_name, 
        m.price_type,
        p.model as product_model,
        pd.name as product_name,
        cgd.name as customer_group_name,
        cgd.description as customer_group_description,
        l." . $date_column . " as created_on,
        CONCAT('Price changed from ', l.old_price, ' to ', l.new_price) as formatted_message
    FROM " . DB_PREFIX . "odoo_price_sync_log l
    LEFT JOIN " . DB_PREFIX . "product p ON l.product_id = p.product_id
    LEFT JOIN " . DB_PREFIX . "product_description pd ON p.product_id = pd.product_id 
        AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
    LEFT JOIN " . DB_PREFIX . "odoo_pricelist_map m ON l.customer_group_id = m.opencart_customer_group_id
    LEFT JOIN " . DB_PREFIX . "customer_group_description cgd 
        ON l.customer_group_id = cgd.customer_group_id 
        AND cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'
    WHERE m.is_active = 1";

        $sql .= $this->getPriceHistoryWhereClause();

        // Add main condition based on params
        if (isset($params['mapping_id'])) {
            $sql .= " AND m.id = '" . (int)$params['mapping_id'] . "'";
        }
        if (isset($params['product_id'])) {
            $sql .= " AND l.product_id = '" . (int)$params['product_id'] . "'";
        }

        // Add filters
        if (isset($filters['filter_product_id'])) {
            $sql .= " AND l.product_id = '" . (int)$filters['filter_product_id'] . "'";
        }

        if (isset($filters['filter_model'])) {
            $sql .= " AND p.model LIKE '%" . $this->db->escape($filters['filter_model']) . "%'";
        }

        if (isset($filters['filter_product_name'])) {
            $sql .= " AND pd.name LIKE '%" . $this->db->escape($filters['filter_product_name']) . "%'";
        }

        if (isset($filters['filter_status'])) {
            $sql .= " AND l.status = '" . $this->db->escape($filters['filter_status']) . "'";
        }

        if (isset($filters['filter_customer_group_id'])) {
            $sql .= " AND l.customer_group_id = '" . $this->db->escape($filters['filter_customer_group_id']) . "'";
        }
        $sql .= " ORDER BY l." . $date_column . " DESC";

        if (isset($filters['start']) || isset($filters['limit'])) {
            if (isset($filters['start']) && isset($filters['limit'])) {
                $sql .= " LIMIT " . (int)$filters['start'] . "," . (int)$filters['limit'];
            } elseif (isset($filters['limit'])) {
                $sql .= " LIMIT " . (int)$filters['limit'];
            }
        }

        $query = $this->db->query($sql);
        return $query->rows;
    }

//    /**
//     * Log price sync event
//     * @param array $data Log data
//     * [product_id (int 11), customer_group_id (int), old_price (float 15,4), new_price (float 15,4), sync_direction (char), status (char), message (text)]
//     * @return int|bool Log ID on success, false on failure
//     */
//    public function logPriceSync($data) {
//        try {
//            $this->db->query("INSERT INTO " . DB_PREFIX . "odoo_price_sync_log SET
//                product_id = '" . (int)$data['product_id'] . "',
//                customer_group_id = '" . (int)$data['customer_group_id'] . "',
//                old_price = '" . (float)$data['old_price'] . "',
//                new_price = '" . (float)$data['new_price'] . "',
//                sync_direction = '" . $this->db->escape($data['sync_direction']) . "',
//                status = '" . $this->db->escape($data['status']) . "',
//                message = '" . $this->db->escape($data['message']) . "'");
//
//            return $this->db->getLastId();
//        } catch (Exception $e) {
//            $this->log->write("Error logging price sync: " . $e->getMessage());
//            return false;
//        }
//    }
//

//    /**
//     * Get sync history for price mapping with optional filters
//     * @param int $mapping_id Mapping ID
//     * @param array $data Optional filters like status, date range etc
//     * @return array History records
//     */
//    public function getSyncHistory($mapping_id, $data = array()) {
//        $sql = "SELECT l.*, pd.name as product_name, p.model as product_model,
//            pm.odoo_pricelist_name, m.name as customer_group_name
//        FROM " . DB_PREFIX . "odoo_price_sync_log l
//        LEFT JOIN " . DB_PREFIX . "product p
//            ON l.product_id = p.product_id
//        LEFT JOIN " . DB_PREFIX . "product_description pd
//            ON l.product_id = pd.product_id
//            AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
//        LEFT JOIN " . DB_PREFIX . "odoo_pricelist_map pm
//            ON l.customer_group_id = pm.opencart_customer_group_id
//        LEFT JOIN " . DB_PREFIX . "customer_group_description m
//            ON pm.opencart_customer_group_id = m.customer_group_id
//            AND m.language_id = '" . (int)$this->config->get('config_language_id') . "'
//        WHERE pm.id = '" . (int)$mapping_id . "'";
//
//        if (!empty($data['filter_product_id'])) {
//            $sql .= " AND l.product_id = '" . (int)$data['filter_product_id'] . "'";
//        }
//
//        if (!empty($data['filter_product_name'])) {
//            $sql .= " AND pd.name LIKE '%" . $this->db->escape($data['filter_product_name']) . "%'";
//        }
//
//        if (!empty($data['filter_status'])) {
//            $sql .= " AND l.status = '" . $this->db->escape($data['filter_status']) . "'";
//        }
//
//        if (!empty($data['filter_pricelist'])) {
//            $sql .= " AND l.customer_group_id = '" . (int)$data['filter_pricelist'] . "'";
//        }
//
//        $sql .= " ORDER BY l.created_on DESC";
//
//        if (isset($data['start']) || isset($data['limit'])) {
//            if ($data['start'] < 0) {
//                $data['start'] = 0;
//            }
//
//            if ($data['limit'] < 1) {
//                $data['limit'] = 20;
//            }
//
//            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
//        }
//
//        $query = $this->db->query($sql);
//
//        $history = array();
//        foreach ($query->rows as $row) {
//            $history[] = array(
//                'product_id' => $row['product_id'],
//                'product_name' => $row['product_name'],
//                'product_model' => $row['product_model'],
//                'old_price' => $this->currency->format($row['old_price'], $this->config->get('config_currency')),
//                'new_price' => $this->currency->format($row['new_price'], $this->config->get('config_currency')),
//                'sync_direction' => $row['sync_direction'],
//                'status' => $row['status'],
//                'message' => $row['message'],
//                'created_on' => date($this->language->get('datetime_format'), strtotime($row['created_on'])),
//                'pricelist_name' => $row['odoo_pricelist_name'],
//                'customer_group' => $row['customer_group_name']
//            );
//        }
//
//        return $history;
//    }

    /**
     * Get total count of sync history records
     * @param array $params Parameters including product_id or mapping_id
     * @param array $filters Optional filters
     * @return int Total count
     */
    public function getSyncHistoryTotal($params = [], $filters = []) {
        $sql = "SELECT COUNT(*) as total 
    FROM " . DB_PREFIX . "odoo_price_sync_log l
    LEFT JOIN " . DB_PREFIX . "product p ON l.product_id = p.product_id
    LEFT JOIN " . DB_PREFIX . "product_description pd ON l.product_id = pd.product_id 
        AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
    LEFT JOIN " . DB_PREFIX . "odoo_pricelist_map m ON l.customer_group_id = m.opencart_customer_group_id
    WHERE  m.is_active = 1";

        $sql .= $this->getPriceHistoryWhereClause();

        // Add main condition based on params
        if (isset($params['mapping_id'])) {
            $sql .= " AND m.id = '" . (int)$params['mapping_id'] . "'";
        }
        if (isset($params['product_id'])) {
            $sql .= " AND l.product_id = '" . (int)$params['product_id'] . "'";
        }

        // Add filters
        if (isset($filters['filter_product_id'])) {
            $sql .= " AND l.product_id = '" . (int)$filters['filter_product_id'] . "'";
        }

        if (isset($filters['filter_model'])) {
            $sql .= " AND p.model LIKE '%" . $this->db->escape($filters['filter_model']) . "%'";
        }

        if (isset($filters['filter_product_name'])) {
            $sql .= " AND pd.name LIKE '%" . $this->db->escape($filters['filter_product_name']) . "%'";
        }

        if (isset($filters['filter_status'])) {
            $sql .= " AND l.status = '" . $this->db->escape($filters['filter_status']) . "'";
        }

        if (isset($filters['filter_customer_group_id'])) {
            $sql .= " AND l.customer_group_id = '" . $this->db->escape($filters['filter_customer_group_id']) . "'";
        }

        $query = $this->db->query($sql);
        return (int)$query->row['total'];
    }

    /**
     * Get sync history for a product
     * @param int $product_id Product ID
     * @param array $filters Optional filters
     * @return array Sync history
     */
    public function getProductPriceSyncHistory($product_id, $filters = []) {
        $date_column = $this->getPriceSyncLogDateColumn();

        $sql = "SELECT l.*, l." . $date_column . " as created_on, m.odoo_pricelist_name, m.price_type 
            FROM " . DB_PREFIX . "odoo_price_sync_log l
            LEFT JOIN " . DB_PREFIX . "odoo_pricelist_map m ON l.customer_group_id = m.opencart_customer_group_id
            WHERE l.product_id = '" . (int)$product_id . "'";

        $sql .= $this->getPriceHistoryWhereClause();

        if (isset($filters['status'])) {
            $sql .= " AND l.status = '" . $this->db->escape($filters['status']) . "'";
        }

        if (isset($filters['sync_direction'])) {
            $sql .= " AND l.sync_direction = '" . $this->db->escape($filters['sync_direction']) . "'";
        }

        if (isset($filters['date_from'])) {
            $sql .= " AND l." . $date_column . " >= '" . $this->db->escape($filters['date_from']) . "'";
        }

        if (isset($filters['date_to'])) {
            $sql .= " AND l." . $date_column . " <= '" . $this->db->escape($filters['date_to']) . "'";
        }

        $sql .= " ORDER BY l." . $date_column . " DESC";

        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        $query = $this->db->query($sql);
        return $query->rows;
    }


//    /**
//     * Get products with pending price syncs
//     * @param array $filters Optional filters
//     * @return array Products needing sync
//     */
//    public function getPendingSyncs($filters = []) {
//        $sql = "SELECT DISTINCT p.product_id, p.model, pd.name, l.status,
//                    l.sync_direction, l.created_on as last_sync_attempt
//            FROM " . DB_PREFIX . "product p
//            LEFT JOIN " . DB_PREFIX . "product_description pd
//                ON p.product_id = pd.product_id
//                AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
//            JOIN " . DB_PREFIX . "odoo_price_sync_log l ON p.product_id = l.product_id
//            WHERE l.status = '" . self::SYNC_STATUS['pending'] . "'";
//
//        if (isset($filters['sync_direction'])) {
//            $sql .= " AND l.sync_direction = '" . $this->db->escape($filters['sync_direction']) . "'";
//        }
//
//        $sql .= " ORDER BY l.created_on ASC";
//
//        if (isset($filters['limit'])) {
//            $sql .= " LIMIT " . (int)$filters['limit'];
//        }
//
//        $query = $this->db->query($sql);
//        return $query->rows;
//    }


    /**
     * Get pricelist data from Odoo via XMLRPC
     * @return array|bool Pricelist data or false on failure
     */
    /**
     * Get pricelist data from Odoo via XMLRPC
     * @return array|bool Pricelist data or false on failure
     */
    public function getOdooPricelistData() {
        try {
            // Initialize cache
            $this->load->model('setting/setting');

            // Define cache key for our module
            $cache_key = 'odoo_pricelist_data';

            // Try to get from cache first
//            $this->load->library('cache');
            $cache = new Cache('file'); // OpenCart's file-based cache
            $result = $cache->get($cache_key);

            if ($result === false || empty($result)) {
                // Load Odoo connector
                $this->load->model('extension/module/odoo_connector');
                $odoo_connect = $this->model_extension_module_odoo_connector->getConnection();

                if (!$odoo_connect['status']) {
                    throw new Exception("Failed to connect to Odoo");
                }

                $models = $odoo_connect['client'];
                $db_name = $odoo_connect['db'];
                $uid = $odoo_connect['userId'];
                $password = $odoo_connect['pwd'];

                // Call get_all_opencart_prices method
                $result = $models->execute_kw($db_name, $uid, $password,
                    'product.pricelist', 'get_all_opencart_prices',
                    array()
                );

                if (isset($result['faultCode'])) {
                    throw new Exception($result['faultString']);
                }

                // Store in cache for 10 hours (36000 seconds)
                $cache->set($cache_key, $result, 36000);
            }

            $this->debugLog("Successfully retrieved pricelist data from Odoo. Pricelists count: " . count($result));

            return $result;

        } catch (Exception $e) {
            $this->log->write("Error getting pricelist data from Odoo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process and sync prices from Odoo
     * @return array Result statistics
     */
    public function processOdooPrices() {
        $stats = [
            'total_pricelists' => 0,
            'processed_pricelists' => 0,
            'total_products' => 0,
            'processed_products' => 0,
            'failed_products' => 0,
            'errors' => []
        ];

        try {
            // Get price data from Odoo
            $price_data = $this->getOdooPricelistData();
            if (!$price_data) {
                throw new Exception("Failed to get price data from Odoo");
            }

            $stats['total_pricelists'] = count($price_data);

            // Process each pricelist
            foreach ($price_data as $pricelist) {
                try {
                    // Get mapping for this pricelist
                    $opencart_group_id = $this->getOpenCartGroupId($pricelist['pricelist_id']);
                    if (!$opencart_group_id) {
                        throw new Exception("No mapping found for pricelist ID: " . $pricelist['pricelist_id']);
                    }

                    $stats['total_products'] += count($pricelist['pricelist_items']);

                    // Process each product in pricelist
                    foreach ($pricelist['pricelist_items'] as $item) {
                        try {
                            // Get product mapping using odoo template id
                            $product_mapping = $this->getProducByOdooTmpl($item['product_tmpl_id']);
                            if (!$product_mapping) {
                                throw new Exception("Product template not mapped: " . $item['default_code']);
                            }
                            // Sync prices to OpenCart
                            if ($this->updateProductPrices($product_mapping['opencart_product_id'], $opencart_group_id, $item['fixed_price'])) {
                                $stats['processed_products']++;
                            } else {
                                $stats['failed_products']++;
                            }

                        } catch (Exception $e) {
                            $stats['failed_products']++;
                            $stats['errors'][] = "Product Error: " . $e->getMessage();
                            $this->debugLog("Error processing product " . $item['product_tmpl_id'] . ": " . $e->getMessage());
                        }
                    }

                    $stats['processed_pricelists']++;

                } catch (Exception $e) {
                    $stats['errors'][] = "Pricelist Error: " . $e->getMessage();
                    $this->debugLog("Error processing pricelist " . $pricelist['pricelist_name'] . ": " . $e->getMessage());
                }
            }

            return $stats;

        } catch (Exception $e) {
            $this->log->write("Error processing Odoo prices: " . $e->getMessage());
            $stats['errors'][] = "Main Error: " . $e->getMessage();
            return $stats;
        }
    }

    private function getProducByOdooTmpl($odoo_product_tmpl_id) {
        $sql = "SELECT `opencart_product_id` FROM ". DB_PREFIX ."odoo_product_variant_map WHERE odoo_product_tmpl_id =". (int)$odoo_product_tmpl_id;

        $query = $this->db->query($sql);
        return $query->num_rows ? $query->row : false;
    }

    /**
     * Get available pricelist data from Odoo via XMLRPC
     * Returns only pricelists marked for OpenCart export
     * @return array|bool Array of pricelists or false on failure
     */
    public function getAvailableOdooPricelists() {
        try {
            // Load Odoo connector
            $this->load->model('extension/module/odoo_connector');
            $odoo_connect = $this->model_extension_module_odoo_connector->getConnection();

            if (!$odoo_connect['status']) {
                throw new Exception("Failed to connect to Odoo");
            }

            $models = $odoo_connect['client'];
            $db_name = $odoo_connect['db'];
            $uid = $odoo_connect['userId'];
            $password = $odoo_connect['pwd'];

            // Direct search_read on product.pricelist with domain filters
            $result = $models->execute_kw($db_name, $uid, $password,
                'product.pricelist', 'search_read',
                array(array(
                    array('active', '=', true),
                    array('opencart_export', '=', true),
                    array('currency_id.name', '=', 'RUB')
                )),
                array('fields' => array('id', 'name'))
            );

            if (isset($result['faultCode'])) {
                throw new Exception($result['faultString']);
            }

            // Transform result to expected format
            $pricelists = array();
            foreach ($result as $pricelist) {
                $pricelists[] = array(
                    'pricelist_id' => $pricelist['id'],
                    'pricelist_name' => $pricelist['name']
                );
            }

            // Cache the results
            if (!empty($pricelists)) {
                $this->cache->set('odoo_pricelists', $pricelists);
            }

            $this->log->write("Successfully retrieved pricelist info from Odoo. Count: " . count($pricelists));
            return $pricelists;

        } catch (Exception $e) {
            $this->log->write("Error getting pricelist info from Odoo: " . $e->getMessage());

            // Try to get data from cache if API call fails
            $cached = $this->cache->get('odoo_pricelists');
            if ($cached) {
                if ($this->debug) $this->log->write("Retrieved pricelist info from cache");
                return $cached;
            }

            return false;
        }
    }
}
