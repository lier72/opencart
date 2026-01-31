<?php
/**
 * Adaptive Filter - Admin Model
 */

class ModelExtensionModuleAdaptiveFilter extends Model {

    /**
     * Install database tables
     */
    public function install() {
        // User preferences table (logged in users)
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "user_preferences` (
                `user_id` INT(11) NOT NULL,
                `sizes` TEXT DEFAULT NULL,
                `colors` TEXT DEFAULT NULL,
                `genders` TEXT DEFAULT NULL,
                `sports` TEXT DEFAULT NULL,
                `smart_sorting_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `last_updated` DATETIME NOT NULL,
                PRIMARY KEY (`user_id`),
                INDEX `idx_last_updated` (`last_updated`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // Guest preferences table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "guest_preferences` (
                `guest_hash` VARCHAR(64) NOT NULL,
                `sizes` TEXT DEFAULT NULL,
                `colors` TEXT DEFAULT NULL,
                `genders` TEXT DEFAULT NULL,
                `sports` TEXT DEFAULT NULL,
                `smart_sorting_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `last_seen` DATETIME NOT NULL,
                PRIMARY KEY (`guest_hash`),
                INDEX `idx_last_seen` (`last_seen`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // Sport mapping for categories
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "sport_mapping` (
                `mapping_id` INT(11) NOT NULL AUTO_INCREMENT,
                `category_id` INT(11) NOT NULL,
                `sport` VARCHAR(100) NOT NULL,
                `weight` INT(11) NOT NULL DEFAULT 1,
                PRIMARY KEY (`mapping_id`),
                UNIQUE INDEX `idx_category_sport` (`category_id`, `sport`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // Category sales percentages for smart interleaving
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "category_sales_mix` (
                `parent_category_id` INT(11) NOT NULL,
                `subcategory_id` INT(11) NOT NULL,
                `calculated_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `manual_percentage` DECIMAL(5,2) NULL DEFAULT NULL,
                `sales_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `total_quantity` INT(11) NOT NULL DEFAULT 0,
                `total_revenue` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
                `is_manual` TINYINT(1) NOT NULL DEFAULT 0,
                `last_calculated` DATETIME NOT NULL,
                `last_modified` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`parent_category_id`, `subcategory_id`),
                INDEX `idx_parent` (`parent_category_id`),
                INDEX `idx_last_calculated` (`last_calculated`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // Add events for signal capture
        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE 'adaptive_filter%'");

        // Event 1: Capture product view (weight: 1)
        // Records color, gender, sport from product browsing
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "event` SET
                `code` = 'adaptive_filter_product_view',
                `trigger` = 'catalog/controller/product/product/after',
                `action` = 'extension/module/adaptive_filter/captureProductView',
                `status` = 1,
                `sort_order` = 1
        ");

        // Event 2: Capture add to cart (weight: 5)
        // Records size, color, gender, sport when user adds product to cart
        // Uses /after event to read from cart table (source of truth)
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "event` SET
                `code` = 'adaptive_filter_add_to_cart',
                `trigger` = 'catalog/controller/checkout/cart/add/after',
                `action` = 'extension/module/adaptive_filter/captureAddToCart',
                `status` = 1,
                `sort_order` = 1
        ");

        // Note: Login merge is now handled automatically in getUserIdentifier()
        // No event registration needed - merge happens on first request after login

        // Note: Filter selection capture (weight: 3) is handled directly in
        // catalog/controller/journal3/filter.php without an event hook

        // Event 3: Sort persistence and personalized sorting for product listings
        // Intercepts product/category, product/special, product/search, product/manufacturer
        // This must use /before to modify request BEFORE controller reads it
        $listing_routes = array('product/category', 'product/special', 'product/search', 'product/manufacturer');
        foreach ($listing_routes as $route) {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "event` SET
                    `code` = 'adaptive_filter_sort_" . str_replace('/', '_', $route) . "',
                    `trigger` = 'catalog/controller/" . $route . "/before',
                    `action` = 'extension/module/adaptive_filter/beforeProductListing',
                    `status` = 1,
                    `sort_order` = 1
            ");
        }
    }

    /**
     * Uninstall - Drop tables and remove events
     */
    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "user_preferences`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "guest_preferences`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "sport_mapping`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "category_sales_mix`");

        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE 'adaptive_filter%'");
    }

    /**
     * Get sport mappings for admin management
     */
    public function getSportMappings() {
        $query = $this->db->query("
            SELECT sm.category_id, sm.sport, sm.weight, cd.name as category_name
            FROM `" . DB_PREFIX . "sport_mapping` sm
            LEFT JOIN `" . DB_PREFIX . "category_description` cd
                ON sm.category_id = cd.category_id
                AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
            ORDER BY sm.sport, cd.name
        ");

        return $query->rows;
    }

    /**
     * Add sport mapping
     * Also clears autocomplete cache so new sports appear in search immediately
     */
    public function addSportMapping($category_id, $sport, $weight = 1) {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "sport_mapping`
            SET category_id = '" . (int)$category_id . "',
                sport = '" . $this->db->escape($sport) . "',
                weight = '" . (int)$weight . "'
            ON DUPLICATE KEY UPDATE weight = '" . (int)$weight . "'
        ");

        // Clear autocomplete cache so new sport appears in search results
        $this->cache->delete('adaptive_filter.autocomplete');
    }

    /**
     * Delete sport mapping
     * Also clears autocomplete cache so deleted sports no longer appear in search
     */
    public function deleteSportMapping($mapping_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "sport_mapping` WHERE mapping_id = '" . (int)$mapping_id . "'");

        // Clear autocomplete cache so deleted sport is removed from search results
        $this->cache->delete('adaptive_filter.autocomplete');
    }

    /**
     * Calculate sales percentages for all parent categories
     * Analyzes order data to determine optimal product mix
     *
     * @param int $days Number of days to analyze (default: 90)
     * @return array Results with categories processed
     */
    public function calculateSalesMix($days = 90) {
        $results = array(
            'categories_processed' => 0,
            'subcategories_updated' => 0,
            'errors' => array()
        );

        // Get all parent categories (categories with children)
        $parent_categories = $this->getParentCategories();

        foreach ($parent_categories as $parent) {
            try {
                $this->calculateCategorySalesMix($parent['category_id'], $days);
                $results['categories_processed']++;
            } catch (Exception $e) {
                $results['errors'][] = "Category {$parent['category_id']}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Calculate sales mix for a specific parent category
     *
     * @param int $parent_category_id Parent category ID
     * @param int $days Number of days to analyze
     * @return void
     */
    private function calculateCategorySalesMix($parent_category_id, $days = 90) {
        // Get immediate subcategories
        $subcategories = $this->getSubcategories($parent_category_id);

        if (empty($subcategories)) {
            return; // No subcategories, nothing to calculate
        }

        // Calculate sales for each subcategory (including nested children)
        $sales_data = array();
        $total_revenue = 0;

        foreach ($subcategories as $subcat) {
            $sales = $this->getSubcategorySales($subcat['category_id'], $days);
            $sales_data[$subcat['category_id']] = $sales;
            $total_revenue += $sales['revenue'];
        }

        // If no sales data, use equal distribution
        if ($total_revenue == 0) {
            $equal_percentage = 100.0 / count($subcategories);
            foreach ($subcategories as $subcat) {
                $this->saveSalesMix($parent_category_id, $subcat['category_id'], $equal_percentage, 0, 0);
            }
            return;
        }

        // Calculate percentages based on revenue
        foreach ($sales_data as $subcategory_id => $sales) {
            $percentage = ($sales['revenue'] / $total_revenue) * 100;
            $this->saveSalesMix($parent_category_id, $subcategory_id, $percentage, $sales['quantity'], $sales['revenue']);
        }
    }

    /**
     * Get all parent categories (categories with children)
     *
     * @return array Parent categories
     */
    private function getParentCategories() {
        $query = $this->db->query("
            SELECT DISTINCT c.category_id, cd.name
            FROM " . DB_PREFIX . "category c
            LEFT JOIN " . DB_PREFIX . "category_description cd
                ON c.category_id = cd.category_id
                AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
            WHERE c.category_id IN (
                SELECT DISTINCT parent_id
                FROM " . DB_PREFIX . "category
                WHERE parent_id > 0
            )
            ORDER BY c.sort_order, cd.name
        ");

        return $query->rows;
    }

    /**
     * Get immediate subcategories of a parent category
     *
     * @param int $parent_id Parent category ID
     * @return array Subcategories
     */
    private function getSubcategories($parent_id) {
        $query = $this->db->query("
            SELECT c.category_id, cd.name
            FROM " . DB_PREFIX . "category c
            LEFT JOIN " . DB_PREFIX . "category_description cd
                ON c.category_id = cd.category_id
                AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
            WHERE c.parent_id = '" . (int)$parent_id . "'
            ORDER BY c.sort_order, cd.name
        ");

        return $query->rows;
    }

    /**
     * Get total sales for a subcategory and all its descendants
     *
     * @param int $category_id Category ID
     * @param int $days Number of days to analyze
     * @return array Array with 'quantity' and 'revenue'
     */
    private function getSubcategorySales($category_id, $days) {
        // Get this category and all descendant category IDs
        $category_ids = $this->getAllDescendantCategories($category_id);
        $category_ids[] = $category_id; // Include the category itself

        if (empty($category_ids)) {
            return array('quantity' => 0, 'revenue' => 0);
        }

        // Calculate date threshold
        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Get total sales (quantity and revenue) from order_product joined with product_to_category
        $query = $this->db->query("
            SELECT
                SUM(op.quantity) as total_quantity,
                SUM(op.total) as total_revenue
            FROM " . DB_PREFIX . "order_product op
            INNER JOIN " . DB_PREFIX . "order o
                ON op.order_id = o.order_id
            INNER JOIN " . DB_PREFIX . "product_to_category ptc
                ON op.product_id = ptc.product_id
            WHERE ptc.category_id IN (" . implode(',', array_map('intval', $category_ids)) . ")
                AND o.date_added >= '" . $this->db->escape($date_threshold) . "'
                AND o.order_status_id > 0
        ");

        return array(
            'quantity' => (int)($query->row['total_quantity'] ?? 0),
            'revenue' => (float)($query->row['total_revenue'] ?? 0)
        );
    }

    /**
     * Get all descendant category IDs recursively
     *
     * @param int $category_id Parent category ID
     * @return array Array of descendant category IDs
     */
    private function getAllDescendantCategories($category_id) {
        $descendants = array();

        $query = $this->db->query("
            SELECT category_id
            FROM " . DB_PREFIX . "category
            WHERE parent_id = '" . (int)$category_id . "'
        ");

        foreach ($query->rows as $row) {
            $descendants[] = $row['category_id'];
            // Recursively get children of this category
            $children = $this->getAllDescendantCategories($row['category_id']);
            $descendants = array_merge($descendants, $children);
        }

        return $descendants;
    }

    /**
     * Save sales mix data for a subcategory
     *
     * @param int $parent_category_id Parent category ID
     * @param int $subcategory_id Subcategory ID
     * @param float $percentage Sales percentage (calculated)
     * @param int $total_quantity Total quantity sold
     * @param float $total_revenue Total revenue
     * @return void
     */
    private function saveSalesMix($parent_category_id, $subcategory_id, $percentage, $total_quantity, $total_revenue) {
        $this->db->query("
            INSERT INTO " . DB_PREFIX . "category_sales_mix
            SET parent_category_id = '" . (int)$parent_category_id . "',
                subcategory_id = '" . (int)$subcategory_id . "',
                calculated_percentage = '" . (float)$percentage . "',
                sales_percentage = '" . (float)$percentage . "',
                total_quantity = '" . (int)$total_quantity . "',
                total_revenue = '" . (float)$total_revenue . "',
                is_manual = 0,
                last_calculated = NOW()
            ON DUPLICATE KEY UPDATE
                calculated_percentage = '" . (float)$percentage . "',
                sales_percentage = IF(is_manual = 1, sales_percentage, '" . (float)$percentage . "'),
                total_quantity = '" . (int)$total_quantity . "',
                total_revenue = '" . (float)$total_revenue . "',
                last_calculated = NOW()
        ");
    }

    /**
     * Get sales mix for a specific parent category
     *
     * @param int $parent_category_id Parent category ID
     * @return array Sales mix data with subcategory details
     */
    public function getCategorySalesMix($parent_category_id) {
        $query = $this->db->query("
            SELECT csm.*, cd.name as subcategory_name
            FROM " . DB_PREFIX . "category_sales_mix csm
            LEFT JOIN " . DB_PREFIX . "category_description cd
                ON csm.subcategory_id = cd.category_id
                AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
            WHERE csm.parent_category_id = '" . (int)$parent_category_id . "'
            ORDER BY csm.sales_percentage DESC
        ");

        return $query->rows;
    }

    /**
     * Get all sales mix data (for admin overview)
     *
     * @return array All sales mix data grouped by parent category
     */
    public function getAllSalesMix() {
        $query = $this->db->query("
            SELECT
                csm.*,
                pcd.name as parent_name,
                scd.name as subcategory_name
            FROM " . DB_PREFIX . "category_sales_mix csm
            LEFT JOIN " . DB_PREFIX . "category_description pcd
                ON csm.parent_category_id = pcd.category_id
                AND pcd.language_id = '" . (int)$this->config->get('config_language_id') . "'
            LEFT JOIN " . DB_PREFIX . "category_description scd
                ON csm.subcategory_id = scd.category_id
                AND scd.language_id = '" . (int)$this->config->get('config_language_id') . "'
            ORDER BY csm.parent_category_id, pcd.name, csm.sales_percentage DESC
        ");

        return $query->rows;
    }

    /**
     * Update manual percentage for a subcategory
     *
     * @param int $parent_category_id Parent category ID
     * @param int $subcategory_id Subcategory ID
     * @param float $percentage Manual percentage (0-100)
     * @return bool Success
     */
    public function updateManualPercentage($parent_category_id, $subcategory_id, $percentage) {
        $this->db->query("
            UPDATE " . DB_PREFIX . "category_sales_mix
            SET manual_percentage = '" . (float)$percentage . "',
                sales_percentage = '" . (float)$percentage . "',
                is_manual = 1,
                last_modified = NOW()
            WHERE parent_category_id = '" . (int)$parent_category_id . "'
                AND subcategory_id = '" . (int)$subcategory_id . "'
        ");

        return true;
    }

    /**
     * Reset to calculated percentage for a subcategory
     *
     * @param int $parent_category_id Parent category ID
     * @param int $subcategory_id Subcategory ID
     * @return bool Success
     */
    public function resetToCalculated($parent_category_id, $subcategory_id) {
        $this->db->query("
            UPDATE " . DB_PREFIX . "category_sales_mix
            SET manual_percentage = NULL,
                sales_percentage = calculated_percentage,
                is_manual = 0,
                last_modified = NOW()
            WHERE parent_category_id = '" . (int)$parent_category_id . "'
                AND subcategory_id = '" . (int)$subcategory_id . "'
        ");

        return true;
    }
}
