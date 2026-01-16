<?php
/**
 * Adaptive Filter - Catalog Model
 * Core preference tracking and scoring logic
 */

class ModelExtensionModuleAdaptiveFilter extends Model {

    // Signal weights per XML spec
    const WEIGHT_PRODUCT_VIEW = 1;
    const WEIGHT_CATEGORY_VIEW = 0.5;
    const WEIGHT_FILTER_USAGE = 4;
    const WEIGHT_ADD_TO_CART = 6;
    const WEIGHT_PURCHASE = 8;

    /**
     * Check if debug mode is enabled via admin settings
     * @return bool
     */
    public function isDebugMode() {
        return (bool)$this->config->get('module_adaptive_filter_debug_mode');
    }

    // Scoring weights for personalization (SIZE is highest priority)
    // NOTE: Scores and weights are now configurable in admin settings
    // These constants are kept for backwards compatibility but are no longer used
    // See: module_adaptive_filter_score_* and module_adaptive_filter_weight_* config values

    /**
     * Check if current request is from a bot/crawler
     */
    private function isBot() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return true; // No user agent = likely a bot
        }

        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);

        // Common bot patterns
        $bot_patterns = array(
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'apis-google',
            'bingpreview', 'msnbot', 'yandex', 'baidu', 'duckduck', 'teoma',
            'yahoo', 'google', 'scrapy', 'curl', 'wget', 'python', 'java',
            'scan', 'check', 'monitor', 'lighthouse', 'gtmetrix', 'pingdom',
            'uptimerobot', 'dataprovider', 'facebookexternalhit', 'whatsapp',
            'telegram', 'slack', 'discord', 'preview', 'link', 'validator'
        );

        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user identifier (customer_id or guest_hash)
     * Returns null for bots to prevent tracking them
     */
    private function getUserIdentifier() {
        // Skip bots - don't track their preferences
        if ($this->isBot()) {
            return null;
        }

        if ($this->customer->isLogged()) {
            $customer_id = $this->customer->getId();

            // Auto-merge guest preferences on first request after login
            // Check if there's a guest_hash in session (user just logged in from guest session)
            // The merge flag prevents multiple merge attempts
            if (isset($this->session->data['guest_hash']) && !isset($this->session->data['adaptive_filter_merged'])) {
                $this->mergeGuestToUser($customer_id);

                // Mark merge as completed to prevent re-running
                $this->session->data['adaptive_filter_merged'] = true;
            }

            return array(
                'type' => 'user',
                'id' => $customer_id
            );
        } else {
            // Generate or retrieve guest hash
            if (!isset($this->session->data['guest_hash'])) {
                $this->session->data['guest_hash'] = hash('sha256', session_id() . time() . rand());
            }

            return array(
                'type' => 'guest',
                'id' => $this->session->data['guest_hash']
            );
        }
    }

    /**
     * Get current preferences for user
     */
    public function getPreferences() {
        $user = $this->getUserIdentifier();

        // Return empty preferences for bots
        if ($user === null) {
            return array(
                'sizes' => array(),
                'colors' => array(),
                'genders' => array(),
                'sports' => array()
            );
        }

        if ($user['type'] == 'user') {
            $query = $this->db->query("
                SELECT * FROM `" . DB_PREFIX . "user_preferences`
                WHERE user_id = '" . (int)$user['id'] . "'
            ");
        } else {
            $query = $this->db->query("
                SELECT * FROM `" . DB_PREFIX . "guest_preferences`
                WHERE guest_hash = '" . $this->db->escape($user['id']) . "'
            ");
        }

        if ($query->num_rows) {
            // Limit to top 3 per category
            $sizes = json_decode($query->row['sizes'] ?? '{}', true);
            $colors = json_decode($query->row['colors'] ?? '{}', true);
            $genders = json_decode($query->row['genders'] ?? '{}', true);
            $sports = json_decode($query->row['sports'] ?? '{}', true);

            // Sort by weight (descending) and keep top 3
            arsort($sizes);
            arsort($colors);
            arsort($genders);
            arsort($sports);

            return array(
                'sizes' => array_slice($sizes, 0, 3, true),
                'colors' => array_slice($colors, 0, 3, true),
                'genders' => array_slice($genders, 0, 3, true),
                'sports' => array_slice($sports, 0, 3, true)
            );
        }

        return array(
            'sizes' => array(),
            'colors' => array(),
            'genders' => array(),
            'sports' => array()
        );
    }

    /**
     * Record a signal (product view, cart add, etc.)
     */
    public function recordSignal($type, $data, $weight = 1) {
        $preferences = $this->getPreferences();

        // Only record explicitly selected attributes
        // Do NOT record all available options - only what user explicitly chose

        if (isset($data['size']) && $data['size']) {
            $preferences['sizes'] = $this->incrementCounter($preferences['sizes'], $data['size'], $weight, 5);
        }

        if (isset($data['color']) && $data['color']) {
            $preferences['colors'] = $this->incrementCounter($preferences['colors'], $data['color'], $weight, 5);
        }

        // Handle both single gender (legacy) and multiple genders (new)
        if (isset($data['genders']) && is_array($data['genders'])) {
            // New behavior: record all genders separately
            foreach ($data['genders'] as $gender) {
                $preferences['genders'] = $this->incrementCounter($preferences['genders'], $gender, $weight, 4);
            }
        } elseif (isset($data['gender']) && $data['gender']) {
            // Legacy behavior: single gender
            $preferences['genders'] = $this->incrementCounter($preferences['genders'], $data['gender'], $weight, 4);
        }

        if (isset($data['sport']) && $data['sport']) {
            $preferences['sports'] = $this->incrementCounter($preferences['sports'], $data['sport'], $weight, 3);
        }

        $this->savePreferences($preferences);
    }

    /**
     * Increment counter with max_keys limit
     */
    private function incrementCounter($counters, $key, $weight, $max_keys) {
        if (!isset($counters[$key])) {
            $counters[$key] = 0;
        }

        $counters[$key] += $weight;

        // Sort by value descending
        arsort($counters);

        // Keep only top max_keys
        if (count($counters) > $max_keys) {
            $counters = array_slice($counters, 0, $max_keys, true);
        }

        return $counters;
    }

    /**
     * Save preferences to database
     */
    private function savePreferences($preferences) {
        $user = $this->getUserIdentifier();

        // Don't save preferences for bots
        if ($user === null) {
            return;
        }

        $sizes_json = json_encode($preferences['sizes']);
        $colors_json = json_encode($preferences['colors']);
        $genders_json = json_encode($preferences['genders']);
        $sports_json = json_encode($preferences['sports']);

        if ($user['type'] == 'user') {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "user_preferences`
                SET user_id = '" . (int)$user['id'] . "',
                    sizes = '" . $this->db->escape($sizes_json) . "',
                    colors = '" . $this->db->escape($colors_json) . "',
                    genders = '" . $this->db->escape($genders_json) . "',
                    sports = '" . $this->db->escape($sports_json) . "',
                    last_updated = NOW()
                ON DUPLICATE KEY UPDATE
                    sizes = '" . $this->db->escape($sizes_json) . "',
                    colors = '" . $this->db->escape($colors_json) . "',
                    genders = '" . $this->db->escape($genders_json) . "',
                    sports = '" . $this->db->escape($sports_json) . "',
                    last_updated = NOW()
            ");
        } else {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "guest_preferences`
                SET guest_hash = '" . $this->db->escape($user['id']) . "',
                    sizes = '" . $this->db->escape($sizes_json) . "',
                    colors = '" . $this->db->escape($colors_json) . "',
                    genders = '" . $this->db->escape($genders_json) . "',
                    sports = '" . $this->db->escape($sports_json) . "',
                    last_seen = NOW()
                ON DUPLICATE KEY UPDATE
                    sizes = '" . $this->db->escape($sizes_json) . "',
                    colors = '" . $this->db->escape($colors_json) . "',
                    genders = '" . $this->db->escape($genders_json) . "',
                    sports = '" . $this->db->escape($sports_json) . "',
                    last_seen = NOW()
            ");
        }
    }

    /**
     * Get product attributes (color, gender, sport)
     *
     * Note: Size fetching was removed as it's not used by any callers.
     * Bulk scoring uses getBulkProductAttributes() which is optimized.
     *
     * Simplified logic using only configured IDs:
     * - Color: From configured product attribute IDs (not attribute names)
     * - Gender: Detected from product categories (configured category mappings)
     * - Sport: Detected from product categories (configured category mappings)
     */
    public function getProductAttributes($product_id) {
        $attributes = array();

        // Note: Size fetching removed - not used by any callers
        // Bulk scoring uses getBulkProductAttributes() instead
        // Product view/cart events don't need sizes

        // 1. Get COLOR from Product Attributes (using configured attribute IDs)
        $color_attribute_ids = $this->config->get('module_adaptive_filter_color_attribute_ids') ?? '';
        $color_attribute_ids_array = array_filter(array_map('trim', explode(',', $color_attribute_ids)));

        if (!empty($color_attribute_ids_array)) {
            $color = $this->getProductAttributeValuesByIds($product_id, $color_attribute_ids_array);
            if ($color) {
                $attributes['color'] = $color;
            }
        }

        // 2. Get GENDER from product categories (using configured category mappings)
        $genders = $this->detectGenderFromCategories($product_id);
        if (!empty($genders)) {
            $attributes['genders'] = $genders; // Changed to 'genders' (plural) and pass array
        }

        // 3. Get SPORT from product categories (using configured category mappings)
        $sport = $this->inferSportFromProduct($product_id);
        if ($sport) {
            $attributes['sport'] = $sport;
        }

        return $attributes;
    }

    /**
     * Get product attribute values by attribute IDs (for Color)
     * Returns first matching attribute value
     */
    private function getProductAttributeValuesByIds($product_id, $attribute_ids = array()) {
        if (empty($attribute_ids)) {
            return null;
        }

        // Get product attributes matching the configured attribute IDs
        $query = $this->db->query("
            SELECT pa.text
            FROM `" . DB_PREFIX . "product_attribute` pa
            WHERE pa.product_id = '" . (int)$product_id . "'
                AND pa.attribute_id IN (" . implode(',', array_map('intval', $attribute_ids)) . ")
                AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "'
            LIMIT 1
        ");

        if ($query->num_rows) {
            return $query->row['text'];
        }

        return null;
    }

    /**
     * Detect gender based on product categories and their parents
     * Returns: array of genders ['Men', 'Women', 'Children'], or empty array if none
     */
    private function detectGenderFromCategories($product_id) {
        // Get all categories this product belongs to
        $query = $this->db->query("
            SELECT category_id
            FROM " . DB_PREFIX . "product_to_category
            WHERE product_id = '" . (int)$product_id . "'
        ");

        if (!$query->num_rows) {
            return array();
        }

        $product_categories = array_column($query->rows, 'category_id');

        // Get all parent categories for each product category
        $all_category_ids = array();
        foreach ($product_categories as $cat_id) {
            $parents = $this->getCategoryParents($cat_id);
            $all_category_ids = array_merge($all_category_ids, $parents);
        }
        $all_category_ids = array_unique($all_category_ids);

        // Get configured gender category mappings (root/parent categories)
        $men_categories = $this->config->get('module_adaptive_filter_gender_men_categories') ?? '';
        $women_categories = $this->config->get('module_adaptive_filter_gender_women_categories') ?? '';
        $children_categories = $this->config->get('module_adaptive_filter_gender_children_categories') ?? '';

        $men_cats = array_filter(array_map('trim', explode(',', $men_categories)));
        $women_cats = array_filter(array_map('trim', explode(',', $women_categories)));
        $children_cats = array_filter(array_map('trim', explode(',', $children_categories)));

        // Check which gender categories this product or its parents belong to
        $genders = array();

        if (array_intersect($all_category_ids, $men_cats)) {
            $genders[] = 'Men';
        }

        if (array_intersect($all_category_ids, $women_cats)) {
            $genders[] = 'Women';
        }

        if (array_intersect($all_category_ids, $children_cats)) {
            $genders[] = 'Children';
        }

        // Return all detected genders
        return $genders;
    }

    /**
     * Get all parent categories for a given category (including the category itself)
     */
    private function getCategoryParents($category_id) {
        $parents = array($category_id);

        $query = $this->db->query("
            SELECT parent_id
            FROM " . DB_PREFIX . "category
            WHERE category_id = '" . (int)$category_id . "'
        ");

        if ($query->num_rows && $query->row['parent_id'] > 0) {
            // Recursively get parent's parents
            $parent_parents = $this->getCategoryParents($query->row['parent_id']);
            $parents = array_merge($parents, $parent_parents);
        }

        return $parents;
    }

    /**
     * Detect gender using cached product categories (no database query)
     */
    private function detectGenderFromCategoriesWithCache($product_id, $product_categories, $hierarchy) {
        // Get categories for this product from cache
        if (!isset($product_categories[$product_id]) || empty($product_categories[$product_id])) {
            return array();
        }

        $categories = $product_categories[$product_id];

        // Get all parent categories for each product category
        $all_category_ids = array();
        foreach ($categories as $cat_id) {
            $parents = $this->getCategoryParentsWithCache($cat_id, $hierarchy);
            $all_category_ids = array_merge($all_category_ids, $parents);
        }
        $all_category_ids = array_unique($all_category_ids);

        // Get configured gender category mappings (root/parent categories)
        $men_categories = $this->config->get('module_adaptive_filter_gender_men_categories') ?? '';
        $women_categories = $this->config->get('module_adaptive_filter_gender_women_categories') ?? '';
        $children_categories = $this->config->get('module_adaptive_filter_gender_children_categories') ?? '';

        $men_cats = array_filter(array_map('trim', explode(',', $men_categories)));
        $women_cats = array_filter(array_map('trim', explode(',', $women_categories)));
        $children_cats = array_filter(array_map('trim', explode(',', $children_categories)));

        // Check which gender categories this product or its parents belong to
        $genders = array();

        if (array_intersect($all_category_ids, $men_cats)) {
            $genders[] = 'Men';
        }

        if (array_intersect($all_category_ids, $women_cats)) {
            $genders[] = 'Women';
        }

        if (array_intersect($all_category_ids, $children_cats)) {
            $genders[] = 'Children';
        }

        // Return all detected genders
        return $genders;
    }

    /**
     * Get all parent categories using cached hierarchy (no database queries)
     */
    private function getCategoryParentsWithCache($category_id, $hierarchy) {
        $parents = array($category_id);

        if (isset($hierarchy[$category_id]) && $hierarchy[$category_id] > 0) {
            $parent_id = $hierarchy[$category_id];
            // Recursively get parent's parents using cached hierarchy
            $parent_parents = $this->getCategoryParentsWithCache($parent_id, $hierarchy);
            $parents = array_merge($parents, $parent_parents);
        }

        return $parents;
    }

    /**
     * Infer sport using cached product categories and sport mappings (no database queries)
     */
    private function inferSportFromProductWithCache($product_id, $product_categories, $hierarchy, $all_sport_mappings) {
        // Get categories for this product from cache
        if (!isset($product_categories[$product_id]) || empty($product_categories[$product_id])) {
            return null;
        }

        $categories = $product_categories[$product_id];

        // Get all parent categories for each product category
        $all_category_ids = array();
        foreach ($categories as $cat_id) {
            $parents = $this->getCategoryParentsWithCache($cat_id, $hierarchy);
            $all_category_ids = array_merge($all_category_ids, $parents);
        }
        $all_category_ids = array_unique($all_category_ids);

        // Find sport mapping for any of these categories (including parents) from cached data
        if (empty($all_category_ids)) {
            return null;
        }

        // Find best matching sport from pre-loaded mappings
        $best_sport = null;
        $best_weight = -1;

        foreach ($all_category_ids as $cat_id) {
            if (isset($all_sport_mappings[$cat_id])) {
                $mapping = $all_sport_mappings[$cat_id];
                if ($mapping['weight'] > $best_weight) {
                    $best_sport = $mapping['sport'];
                    $best_weight = $mapping['weight'];
                }
            }
        }

        return $best_sport;
    }

    /**
     * Get all sport mappings indexed by category_id (1 query for all mappings)
     */
    private function getAllSportMappings() {
        $query = $this->db->query("
            SELECT category_id, sport, weight
            FROM `" . DB_PREFIX . "sport_mapping`
        ");

        $mappings = array();
        foreach ($query->rows as $row) {
            // If multiple mappings exist for same category, keep the highest weight
            $cat_id = (int)$row['category_id'];
            if (!isset($mappings[$cat_id]) || $row['weight'] > $mappings[$cat_id]['weight']) {
                $mappings[$cat_id] = array(
                    'sport' => $row['sport'],
                    'weight' => (int)$row['weight']
                );
            }
        }

        return $mappings;
    }

    /**
     * Infer sport from product categories and their parents
     */
    private function inferSportFromProduct($product_id) {
        // Get all categories this product belongs to
        $query = $this->db->query("
            SELECT category_id
            FROM " . DB_PREFIX . "product_to_category
            WHERE product_id = '" . (int)$product_id . "'
        ");

        if (!$query->num_rows) {
            return null;
        }

        $product_categories = array_column($query->rows, 'category_id');

        // Get all parent categories for each product category
        $all_category_ids = array();
        foreach ($product_categories as $cat_id) {
            $parents = $this->getCategoryParents($cat_id);
            $all_category_ids = array_merge($all_category_ids, $parents);
        }
        $all_category_ids = array_unique($all_category_ids);

        // Find sport mapping for any of these categories (including parents)
        if (empty($all_category_ids)) {
            return null;
        }

        $category_ids_str = implode(',', array_map('intval', $all_category_ids));

        $query = $this->db->query("
            SELECT sm.sport, sm.weight
            FROM `" . DB_PREFIX . "sport_mapping` sm
            WHERE sm.category_id IN (" . $category_ids_str . ")
            ORDER BY sm.weight DESC
            LIMIT 1
        ");

        if ($query->num_rows) {
            return $query->row['sport'];
        }

        return null;
    }

    // User overrides feature removed - not used

    // Decay system removed - using simple weighted counting

    /**
     * Merge guest preferences to user on login
     */
    public function mergeGuestToUser($customer_id) {
        if (!isset($this->session->data['guest_hash'])) {
            return;
        }

        $guest_hash = $this->session->data['guest_hash'];

        // Get guest preferences
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "guest_preferences`
            WHERE guest_hash = '" . $this->db->escape($guest_hash) . "'
        ");

        if (!$query->num_rows) {
            return;
        }

        $guest_prefs = array(
            'sizes' => json_decode($query->row['sizes'] ?? '{}', true),
            'colors' => json_decode($query->row['colors'] ?? '{}', true),
            'genders' => json_decode($query->row['genders'] ?? '{}', true),
            'sports' => json_decode($query->row['sports'] ?? '{}', true)
        );

        // Get user preferences
        $user_query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "user_preferences`
            WHERE user_id = '" . (int)$customer_id . "'
        ");

        if ($user_query->num_rows) {
            $user_prefs = array(
                'sizes' => json_decode($user_query->row['sizes'] ?? '{}', true),
                'colors' => json_decode($user_query->row['colors'] ?? '{}', true),
                'genders' => json_decode($user_query->row['genders'] ?? '{}', true),
                'sports' => json_decode($user_query->row['sports'] ?? '{}', true)
            );

            // Merge with 0.5 weight for guest data
            $merged = array(
                'sizes' => $this->mergeCounters($user_prefs['sizes'], $guest_prefs['sizes'], 0.5),
                'colors' => $this->mergeCounters($user_prefs['colors'], $guest_prefs['colors'], 0.5),
                'genders' => $this->mergeCounters($user_prefs['genders'], $guest_prefs['genders'], 0.5),
                'sports' => $this->mergeCounters($user_prefs['sports'], $guest_prefs['sports'], 0.5)
            );
        } else {
            $merged = $guest_prefs;
        }

        // Save merged preferences
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "user_preferences`
            SET user_id = '" . (int)$customer_id . "',
                sizes = '" . $this->db->escape(json_encode($merged['sizes'])) . "',
                colors = '" . $this->db->escape(json_encode($merged['colors'])) . "',
                genders = '" . $this->db->escape(json_encode($merged['genders'])) . "',
                sports = '" . $this->db->escape(json_encode($merged['sports'])) . "',
                last_updated = NOW()
            ON DUPLICATE KEY UPDATE
                sizes = '" . $this->db->escape(json_encode($merged['sizes'])) . "',
                colors = '" . $this->db->escape(json_encode($merged['colors'])) . "',
                genders = '" . $this->db->escape(json_encode($merged['genders'])) . "',
                sports = '" . $this->db->escape(json_encode($merged['sports'])) . "',
                last_updated = NOW()
        ");

        // Delete guest preferences
        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "guest_preferences`
            WHERE guest_hash = '" . $this->db->escape($guest_hash) . "'
        ");

        unset($this->session->data['guest_hash']);
    }

    /**
     * Merge two counter arrays
     */
    private function mergeCounters($counters1, $counters2, $weight2) {
        foreach ($counters2 as $key => $value) {
            if (isset($counters1[$key])) {
                $counters1[$key] += $value * $weight2;
            } else {
                $counters1[$key] = $value * $weight2;
            }
        }

        arsort($counters1);

        return $counters1;
    }

    /**
     * Get personalized products for a category
     * @param array $filter_data Filter parameters (category, filters, etc.)
     * @param int $limit Number of products to return
     * @param int $start Offset for pagination
     * @return array Products sorted by personalization score
     */
    public function getPersonalizedProducts($filter_data, $limit = 20, $start = 0) {
        // Start performance timer
        $perf_start = microtime(true);

        // Get user preferences
        $preferences = $this->getPreferences();

        // Get ALL products matching the filters
        $this->load->model('catalog/product');

        // Prepare filter data - keep structure for Journal3 compatibility
        $filter_data_all = $filter_data;
        unset($filter_data_all['sort']); // Remove sort parameter
        unset($filter_data_all['order']); // Remove order parameter

        if ($this->isDebugMode()) {
           // Log filter data for debugging
            $this->log->write('[Adaptive Filter] getPersonalizedProducts called with filter_data: ' . json_encode($filter_data_all));
            $this->log->write('[Adaptive Filter] URL parameters: ' . http_build_query($this->request->get));

            // DEBUG: Log user preferences
            $user_type = $this->customer->isLogged() ? 'user' : 'guest';
            $user_id = $this->customer->isLogged() ? $this->customer->getId() : (isset($this->session->data['guest_hash']) ? $this->session->data['guest_hash'] : 'none');
            $this->log->write('=== USER PREFERENCES DEBUG ===');
            $this->log->write('User Type: ' . $user_type . ' | ID: ' . $user_id);
            $this->log->write('Sizes: ' . json_encode($preferences['sizes'] ?? []));
            $this->log->write('Colors: ' . json_encode($preferences['colors'] ?? []));
            $this->log->write('Genders: ' . json_encode($preferences['genders'] ?? []));
            $this->log->write('Sports: ' . json_encode($preferences['sports'] ?? []));
            $this->log->write('==============================');
        }

        // Log Journal3 filter parameters if present
        $journal3_filters = array();
        foreach ($this->request->get as $key => $value) {
            if (strlen($key) >= 2 && ($key[0] == 'f')) {
                $journal3_filters[$key] = $value;
            }
        }
        
        if ($this->isDebugMode()) {
            if (!empty($journal3_filters)) {
                $this->log->write('[Adaptive Filter] Journal3 filter parameters detected: ' . json_encode($journal3_filters));
            } else {
                $this->log->write('[Adaptive Filter] No Journal3 filter parameters in URL');
            }
        }

        // Check if we need to get special products instead of all products
        $is_special = isset($filter_data_all['filter_special']) && $filter_data_all['filter_special'];

        // Get total count first
        if ($is_special) {
            $total_count = $this->model_catalog_product->getTotalProductSpecials($filter_data_all);
        } else {
            $total_count = $this->model_catalog_product->getTotalProducts($filter_data_all);
        }
        if ($this->isDebugMode()) {
            $this->log->write('[Adaptive Filter] Total products after filtering: ' . $total_count . ($is_special ? ' (specials only)' : ''));
        }

        // Now fetch ALL products with filtering applied
        $filter_data_all['start'] = 0;
        $filter_data_all['limit'] = $total_count; // Fetch exactly the number we need

        // IMPORTANT: Journal3's getFilterData() reads limit from $this->request->get, not from $args
        // So we need to temporarily override the request limit to fetch all products
        $original_limit = isset($this->request->get['limit']) ? $this->request->get['limit'] : null;
        $original_page = isset($this->request->get['page']) ? $this->request->get['page'] : null;

        $this->request->get['limit'] = $total_count;
        $this->request->get['page'] = 1;

        if ($this->isDebugMode()) {
            $this->log->write('[Adaptive Filter] Calling ' . ($is_special ? 'getProductSpecials' : 'getProducts') . ' with filter_data: ' . json_encode($filter_data_all));
        }
        // Measure standard get time
        $standard_start = microtime(true);
        if ($is_special) {
            $products = $this->model_catalog_product->getProductSpecials($filter_data_all);
        } else {
            $products = $this->model_catalog_product->getProducts($filter_data_all);
        }
        $standard_time = microtime(true) - $standard_start;

        // Restore original request parameters
        if ($original_limit !== null) {
            $this->request->get['limit'] = $original_limit;
        } else {
            unset($this->request->get['limit']);
        }
        if ($original_page !== null) {
            $this->request->get['page'] = $original_page;
        } else {
            unset($this->request->get['page']);
        }
        if ($this->isDebugMode()) {
            $this->log->write('[Adaptive Filter] getProducts returned ' . count($products) . ' products (expected: ' . $total_count . ')');
        }

        // Separate in-stock and out-of-stock products
        $in_stock_products = array();
        $out_of_stock_products = array();

        foreach ($products as $product) {
            if (isset($product['quantity']) && $product['quantity'] > 0) {
                $in_stock_products[] = $product;
            } else {
                $out_of_stock_products[] = $product;
            }
        }

        // Score and sort ONLY in-stock products (major optimization)
        $scoring_start = microtime(true);
        $scored_products = array();

        // Check if user has preferences
        $has_preferences = !empty($preferences['sizes']) || !empty($preferences['colors']) ||
                          !empty($preferences['genders']) || !empty($preferences['sports']);

        // BULK LOAD all product attributes at once (instead of per-product queries)
        $product_ids = array_map(function($p) { return $p['product_id']; }, $in_stock_products);
        $bulk_attributes = $this->getBulkProductAttributes($product_ids);

        // Store debug info temporarily for top 5 logging
        $debug_data_temp = array();

        foreach ($in_stock_products as $product) {
            $result = $this->scoreProductWithBulkAttributes(
                $product['product_id'],
                $preferences,
                $bulk_attributes[$product['product_id']]
            );

            $score = $result['score'];

            // Store debug info for this product if in debug mode
            if ($this->isDebugMode() && $result['debug']) {
                $debug_data_temp[$product['product_id']] = $result['debug'];
            }

            // Only include products that match at least one preference (score > 0)
            // OR if there are no preferences at all (show everything)
            if ($has_preferences && $score == 0) {
                continue; // Skip products with no matching preferences
            }

            $product['personalization_score'] = $score;
            $scored_products[] = $product;
        }
        $scoring_time = microtime(true) - $scoring_start;

        // Sort in-stock products by personalization score
        $sorting_start = microtime(true);
        usort($scored_products, function($a, $b) {
            // Score comparison (highest score first)
            $score_diff = $b['personalization_score'] - $a['personalization_score'];
            if ($score_diff != 0) {
                return $score_diff;
            }

            // Product ID for consistency
            return $a['product_id'] - $b['product_id'];
        });
        $sorting_time = microtime(true) - $sorting_start;

        // Apply smart interleaving for product diversity (only for parent categories)
        $interleaving_start = microtime(true);
        $category_id = isset($filter_data['filter_category_id']) ? (int)$filter_data['filter_category_id'] : 0;
        $scored_products = $this->applySmartInterleaving($scored_products, $category_id, $limit);
        $interleaving_time = microtime(true) - $interleaving_start;

        // Append out-of-stock products at the end (no scoring/sorting needed)
        $final_products = array_merge($scored_products, $out_of_stock_products);

        // Store total count for pagination
        $total = count($final_products);

        // Store in session since OpenCart may reload the model instance
        $this->session->data['adaptive_filter_personalized_total'] = $total;

        // Apply pagination - return the requested slice
        $result = array_slice($final_products, $start, $limit);
        if($this->isDebugMode()){
            // Calculate total time and log performance comparison
            $total_time = microtime(true) - $perf_start;
            $personalized_overhead = $total_time - $standard_time;
            $overhead_percent = $standard_time > 0 ? ($personalized_overhead / $standard_time * 100) : 0;

            $this->log->write('=== PERFORMANCE COMPARISON ===');
            $this->log->write(sprintf('Standard getProducts(): %.4f sec', $standard_time));
            $this->log->write(sprintf('Personalized scoring: %.4f sec', $scoring_time));
            $this->log->write(sprintf('Personalized sorting: %.4f sec', $sorting_time));
            $this->log->write(sprintf('Smart interleaving: %.4f sec', $interleaving_time));
            $this->log->write(sprintf('Total personalized: %.4f sec', $total_time));
            $this->log->write(sprintf('Overhead: %.4f sec (+%.1f%%)', $personalized_overhead, $overhead_percent));
            $this->log->write(sprintf('Total products: %d, In-stock: %d, Out-of-stock: %d, Returned: %d',
                count($products), count($in_stock_products), count($out_of_stock_products), count($result)));
            $this->log->write('==============================');
        }
        // DEBUG: Log top 5 scored products with breakdown
        if ($this->isDebugMode() && !empty($scored_products)) {
            $this->log->write('=== TOP 5 SCORED PRODUCTS ===');
            $top_5 = array_slice($scored_products, 0, 5);

            foreach ($top_5 as $index => $product) {
                $rank = $index + 1;
                $product_id = $product['product_id'];
                $score = $product['personalization_score'];

                $this->log->write("#{$rank} Product ID: {$product_id} | Model: {$product['model']} | Score: {$score}");
                $this->log->write("    Name: {$product['name']}");

                // Show breakdown if available
                if (isset($debug_data_temp[$product_id]) && !empty($debug_data_temp[$product_id]['score_breakdown'])) {
                    $breakdown = array();
                    foreach ($debug_data_temp[$product_id]['score_breakdown'] as $category => $details) {
                        $breakdown[] = "{$category}:+{$details['total']}";
                    }
                    $this->log->write("    Breakdown: " . implode(', ', $breakdown));
                }
                $this->log->write('');
            }
            $this->log->write('==============================');
        }

        return $result;
    }

    /**
     * Get total count of personalized products (call after getPersonalizedProducts)
     * @return int Total product count
     */
    public function getPersonalizedProductsTotal() {
        // Retrieve from session (since model instance may be reloaded)
        $total = isset($this->session->data['adaptive_filter_personalized_total']) ? $this->session->data['adaptive_filter_personalized_total'] : 0;
        return $total;
    }

    /**
     * Sort an array of products by personalization score (lightweight version)
     * Used for product/special and other pages that already have products loaded
     * @param array $products Array of product data
     * @return array Sorted products
     */
    public function sortProductsByPersonalization($products) {
        if (empty($products)) {
            return $products;
        }

        // Get user preferences
        $preferences = $this->getPreferences();

        // If no preferences, return products as-is
        if (empty($preferences['sizes']) && empty($preferences['colors']) &&
            empty($preferences['genders']) && empty($preferences['sports'])) {
            return $products;
        }

        // Get product IDs
        $product_ids = array_map(function($p) { return $p['product_id']; }, $products);

        // Bulk load attributes
        $bulk_attributes = $this->getBulkProductAttributes($product_ids);

        // Score each product
        foreach ($products as &$product) {
            $result = $this->scoreProductWithBulkAttributes(
                $product['product_id'],
                $preferences,
                $bulk_attributes[$product['product_id']]
            );
            $product['personalization_score'] = $result['score'];
        }

        // Sort by score (highest first), then by default sort order
        usort($products, function($a, $b) {
            $score_diff = $b['personalization_score'] - $a['personalization_score'];
            if ($score_diff != 0) {
                return $score_diff;
            }
            // If scores are equal, maintain original order
            return 0;
        });

        return $products;
    }

    /**
     * Score a single product based on user preferences
     * @param int $product_id Product ID
     * @param array $preferences User preferences
     * @return int Score (higher = better match)
     */
    private function scoreProduct($product_id, $preferences) {
        $score = 0;

        // Get configured scores
        $score_size = (int)($this->config->get('module_adaptive_filter_score_size') ?? 10);
        $score_color = (int)($this->config->get('module_adaptive_filter_score_color') ?? 5);
        $score_gender = (int)($this->config->get('module_adaptive_filter_score_gender') ?? 2);
        $score_sport = (int)($this->config->get('module_adaptive_filter_score_sport') ?? 1);

        // Get product attributes
        $attributes = $this->getProductAttributes($product_id);

        // Disabled verbose logging for performance - uncomment for debugging
        // $this->log->write('[Adaptive Filter] Scoring product ' . $product_id);
        // $this->log->write('[Adaptive Filter] Available sizes: ' . json_encode($attributes['sizes_available'] ?? []));
        // $this->log->write('[Adaptive Filter] Preferred sizes: ' . json_encode(array_keys($preferences['sizes'] ?? [])));

        // Score size matches - check if ANY preferred size is available in this product
        if (!empty($attributes['sizes_available']) && !empty($preferences['sizes'])) {
            foreach ($preferences['sizes'] as $size => $count) {
                // Try exact match first
                if (in_array($size, $attributes['sizes_available'])) {
                    $score += $score_size * $count;
                    // $this->log->write('[Adaptive Filter] EXACT SIZE MATCH: Product ' . $product_id . ' has size "' . $size . '"');
                } else {
                    // Try fuzzy matching - check if preference size is contained in any available size
                    foreach ($attributes['sizes_available'] as $available_size) {
                        // Normalize both sizes for comparison (lowercase, remove extra spaces)
                        $normalized_pref = strtolower(trim($size));
                        $normalized_avail = strtolower(trim($available_size));

                        // Check if they match when normalized, or if one contains the other
                        if ($normalized_pref === $normalized_avail ||
                            stripos($available_size, $size) !== false ||
                            stripos($size, $available_size) !== false) {
                            $score += $score_size * $count;
                            // $this->log->write('[Adaptive Filter] FUZZY SIZE MATCH: Product ' . $product_id . ' - Preference "' . $size . '" matched with available "' . $available_size . '"');
                            break; // Only count once per preferred size
                        }
                    }
                }
            }
        }

        // Score color matches
        if (!empty($attributes['color']) && !empty($preferences['colors'])) {
            foreach ($preferences['colors'] as $color => $count) {
                // Extract color name without hex code for comparison
                $color_name = trim(preg_replace('/\(#[A-F0-9]{6}\)/i', '', $color));
                $attr_color_name = trim(preg_replace('/\(#[A-F0-9]{6}\)/i', '', $attributes['color']));

                if (stripos($attr_color_name, $color_name) !== false || stripos($color_name, $attr_color_name) !== false) {
                    $score += $score_color * $count;
                }
            }
        }

        // Score gender matches
        if (!empty($attributes['genders']) && !empty($preferences['genders'])) {
            foreach ($preferences['genders'] as $gender => $count) {
                // Check if this gender is in the product's gender list
                if (in_array($gender, $attributes['genders'])) {
                    $score += $score_gender * $count;
                }
            }
        }

        // Score sport matches
        if (!empty($attributes['sport']) && !empty($preferences['sports'])) {
            foreach ($preferences['sports'] as $sport => $count) {
                if ($sport == $attributes['sport']) {
                    $score += $score_sport * $count;
                }
            }
        }

        // Disabled final score logging for performance
        // $this->log->write('[Adaptive Filter] Product ' . $product_id . ' final score: ' . $score);

        return $score;
    }

    /**
     * Bulk load product attributes for multiple products at once
     * Reduces N queries to 3-5 bulk queries for massive performance improvement
     *
     * @param array $product_ids Array of product IDs to load attributes for
     * @return array Associative array of product_id => attributes
     */
    private function getBulkProductAttributes($product_ids) {
        if (empty($product_ids)) {
            return array();
        }

        $bulk_attributes = array();

        // Initialize empty arrays for each product
        foreach ($product_ids as $product_id) {
            $bulk_attributes[$product_id] = array(
                'sizes_available' => array(),
                'color' => null,
                'genders' => array(), // Changed to plural and array
                'sport' => null
            );
        }

        // 1. BULK LOAD SIZE OPTIONS (2 queries instead of 500-750)
        $size_option_ids = $this->config->get('module_adaptive_filter_size_option_ids') ?? '';
        $size_option_ids_array = array_filter(array_map('trim', explode(',', $size_option_ids)));

        if (!empty($size_option_ids_array)) {
            // First query: Get all product_option_id for all products
            $query = $this->db->query("
                SELECT po.product_id, po.product_option_id, po.option_id
                FROM `" . DB_PREFIX . "product_option` po
                WHERE po.product_id IN (" . implode(',', array_map('intval', $product_ids)) . ")
                    AND po.option_id IN (" . implode(',', array_map('intval', $size_option_ids_array)) . ")
            ");

            $product_option_ids = array();
            $option_to_product = array();

            foreach ($query->rows as $row) {
                $product_option_ids[] = $row['product_option_id'];
                $option_to_product[$row['product_option_id']] = $row['product_id'];
            }

            if (!empty($product_option_ids)) {
                // Second query: Get all option values for all products
                $value_query = $this->db->query("
                    SELECT pov.product_option_id, ovd.name, pov.quantity, pov.subtract
                    FROM `" . DB_PREFIX . "product_option_value` pov
                    LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd
                        ON pov.option_value_id = ovd.option_value_id
                        AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'
                    WHERE pov.product_option_id IN (" . implode(',', array_map('intval', $product_option_ids)) . ")
                    ORDER BY pov.quantity DESC, ovd.name ASC
                ");

                foreach ($value_query->rows as $value_row) {
                    $product_id = $option_to_product[$value_row['product_option_id']];
                    $is_in_stock = !$value_row['subtract'] || ($value_row['quantity'] > 0);

                    if ($is_in_stock) {
                        $bulk_attributes[$product_id]['sizes_available'][] = $value_row['name'];
                    }
                }

                // Deduplicate sizes per product
                foreach ($product_ids as $product_id) {
                    $bulk_attributes[$product_id]['sizes_available'] = array_unique($bulk_attributes[$product_id]['sizes_available']);
                }
            }
        }

        // 2. BULK LOAD COLOR ATTRIBUTES (1 query instead of 250)
        $color_attribute_ids = $this->config->get('module_adaptive_filter_color_attribute_ids') ?? '';
        $color_attribute_ids_array = array_filter(array_map('trim', explode(',', $color_attribute_ids)));

        if (!empty($color_attribute_ids_array)) {
            $query = $this->db->query("
                SELECT pa.product_id, pa.text
                FROM `" . DB_PREFIX . "product_attribute` pa
                WHERE pa.product_id IN (" . implode(',', array_map('intval', $product_ids)) . ")
                    AND pa.attribute_id IN (" . implode(',', array_map('intval', $color_attribute_ids_array)) . ")
                    AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "'
            ");

            foreach ($query->rows as $row) {
                $bulk_attributes[$row['product_id']]['color'] = $row['text'];
            }
        }

        // 3. BULK LOAD PRODUCT CATEGORIES (1 query instead of 173)
        $product_categories = $this->getProductCategories($product_ids);

        // 4. BULK LOAD ALL SPORT MAPPINGS (1 query for all products)
        $all_sport_mappings = $this->getAllSportMappings();

        // 5. BULK LOAD GENDER AND SPORT (using cached category data)
        $hierarchy = $this->getCategoryHierarchy();
        foreach ($product_ids as $product_id) {
            $bulk_attributes[$product_id]['genders'] = $this->detectGenderFromCategoriesWithCache($product_id, $product_categories, $hierarchy);
            $bulk_attributes[$product_id]['sport'] = $this->inferSportFromProductWithCache($product_id, $product_categories, $hierarchy, $all_sport_mappings);
        }

        return $bulk_attributes;
    }

    /**
     * Score a single product using pre-loaded bulk attributes
     * Same logic as scoreProduct() but without database calls
     *
     * @param int $product_id Product ID
     * @param array $preferences User preferences
     * @param array $attributes Pre-loaded product attributes
     * @return int Score (higher = better match)
     */
    private function scoreProductWithBulkAttributes($product_id, $preferences, $attributes) {
        $score = 0;
        $debug_info = array();

        // Get configured scores
        $score_size = (int)($this->config->get('module_adaptive_filter_score_size') ?? 10);
        $score_color = (int)($this->config->get('module_adaptive_filter_score_color') ?? 5);
        $score_gender = (int)($this->config->get('module_adaptive_filter_score_gender') ?? 2);
        $score_sport = (int)($this->config->get('module_adaptive_filter_score_sport') ?? 1);

        if ($this->isDebugMode()) {
            $debug_info['product_id'] = $product_id;
            $debug_info['attributes'] = $attributes;
            $debug_info['score_breakdown'] = array();
        }

        // Score size matches - check if ANY preferred size is available in this product
        if (!empty($attributes['sizes_available']) && !empty($preferences['sizes'])) {
            $size_score = 0;
            $size_matches = array();
            foreach ($preferences['sizes'] as $size => $count) {
                // Try exact match first
                if (in_array($size, $attributes['sizes_available'])) {
                    $points = $score_size * $count;
                    $size_score += $points;
                    if ($this->isDebugMode()) $size_matches[] = "$size (exact match, count: $count, +$points)";
                } else {
                    // Try fuzzy matching - check if preference size is contained in any available size
                    foreach ($attributes['sizes_available'] as $available_size) {
                        // Normalize both sizes for comparison (lowercase, remove extra spaces)
                        $normalized_pref = strtolower(trim($size));
                        $normalized_avail = strtolower(trim($available_size));

                        // Check if they match when normalized, or if one contains the other
                        if ($normalized_pref === $normalized_avail ||
                            stripos($available_size, $size) !== false ||
                            stripos($size, $available_size) !== false) {
                            $points = $score_size * $count;
                            $size_score += $points;
                            if ($this->isDebugMode()) $size_matches[] = "$size ≈ $available_size (fuzzy match, count: $count, +$points)";
                            break; // Only count once per preferred size
                        }
                    }
                }
            }
            $score += $size_score;
            if ($this->isDebugMode() && $size_score > 0) {
                $debug_info['score_breakdown']['size'] = array(
                    'total' => $size_score,
                    'matches' => $size_matches
                );
            }
        }

        // Score color matches
        if (!empty($attributes['color']) && !empty($preferences['colors'])) {
            $color_score = 0;
            $color_matches = array();
            foreach ($preferences['colors'] as $color => $count) {
                // Extract color name without hex code for comparison
                $color_name = trim(preg_replace('/\(#[A-F0-9]{6}\)/i', '', $color));
                $attr_color_name = trim(preg_replace('/\(#[A-F0-9]{6}\)/i', '', $attributes['color']));

                if (stripos($attr_color_name, $color_name) !== false || stripos($color_name, $attr_color_name) !== false) {
                    $points = $score_color * $count;
                    $color_score += $points;
                    if ($this->isDebugMode()) $color_matches[] = "$color_name (count: $count, +$points)";
                }
            }
            $score += $color_score;
            if ($this->isDebugMode() && $color_score > 0) {
                $debug_info['score_breakdown']['color'] = array(
                    'total' => $color_score,
                    'matches' => $color_matches
                );
            }
        }

        // Score gender matches
        if (!empty($attributes['genders']) && !empty($preferences['genders'])) {
            $gender_score = 0;
            $gender_matches = array();
            foreach ($preferences['genders'] as $gender => $count) {
                // Check if this gender is in the product's gender list
                if (in_array($gender, $attributes['genders'])) {
                    $points = $score_gender * $count;
                    $gender_score += $points;
                    if ($this->isDebugMode()) $gender_matches[] = "$gender (count: $count, +$points)";
                }
            }
            $score += $gender_score;
            if ($this->isDebugMode() && $gender_score > 0) {
                $debug_info['score_breakdown']['gender'] = array(
                    'total' => $gender_score,
                    'matches' => $gender_matches
                );
            }
        }

        // Score sport matches
        if (!empty($attributes['sport']) && !empty($preferences['sports'])) {
            $sport_score = 0;
            $sport_matches = array();
            foreach ($preferences['sports'] as $sport => $count) {
                if ($sport == $attributes['sport']) {
                    $points = $score_sport * $count;
                    $sport_score += $points;
                    if ($this->isDebugMode()) $sport_matches[] = "$sport (count: $count, +$points)";
                }
            }
            $score += $sport_score;
            if ($this->isDebugMode() && $sport_score > 0) {
                $debug_info['score_breakdown']['sport'] = array(
                    'total' => $sport_score,
                    'matches' => $sport_matches
                );
            }
        }

        // Return score and debug info for logging
        if ($this->isDebugMode()) {
            $debug_info['total_score'] = $score;
            return array('score' => $score, 'debug' => $debug_info);
        }

        return array('score' => $score, 'debug' => null);
    }

    /**
     * Apply smart interleaving to diversify product results by subcategory
     * Uses sales percentages to determine optimal product mix per page
     * Only applies when viewing a parent category with multiple subcategories
     *
     * @param array $products Sorted products with scores
     * @param int $category_id Current category being viewed
     * @param int $products_per_page Number of products displayed per page (e.g., 12, 25, 50)
     * @return array Interleaved products
     */
    private function applySmartInterleaving($products, $category_id, $products_per_page = 12) {
        if (empty($products) || !$category_id || $products_per_page < 1) {
            return $products;
        }

        // Get all subcategories of the current category
        $subcategories = $this->getSubcategories($category_id);

        // If there are no subcategories (leaf category), return as-is
        if (count($subcategories) < 2) {
            return $products;
        }

        // Get sales mix percentages for this category
        $sales_mix = $this->getSalesMixPercentages($category_id);

        // OPTIMIZATION: Load ALL product-to-category mappings in ONE query
        $product_ids = array_column($products, 'product_id');
        $product_categories = $this->getProductCategories($product_ids);

        // OPTIMIZATION: Load entire category hierarchy in ONE query
        $category_hierarchy = $this->getCategoryHierarchy();

        // Group products by their immediate subcategory
        $buckets = array();
        foreach ($products as $product) {
            $subcategory = $this->getProductSubcategoryFromCache(
                $product['product_id'],
                $subcategories,
                $product_categories,
                $category_hierarchy
            );

            if (!isset($buckets[$subcategory])) {
                $buckets[$subcategory] = array();
            }

            $buckets[$subcategory][] = $product;
        }

        // If all products are in one subcategory, no need to interleave
        if (count($buckets) < 2) {
            return $products;
        }

        // Calculate how many slots each subcategory gets per page
        $slots_per_cycle = array();

        foreach ($sales_mix as $subcat_id => $percentage) {
            if (isset($buckets[$subcat_id])) {
                $slots = round(($percentage / 100) * $products_per_page);
                $slots_per_cycle[$subcat_id] = max(1, $slots); // Minimum 1 slot
            }
        }

        // Adjust for rounding errors to ensure total = products_per_page
        $total_allocated = array_sum($slots_per_cycle);
        if ($total_allocated != $products_per_page) {
            // Find subcategory with highest percentage to adjust
            arsort($sales_mix);
            $highest_subcat = key($sales_mix);
            if (isset($slots_per_cycle[$highest_subcat])) {
                $slots_per_cycle[$highest_subcat] += ($products_per_page - $total_allocated);
            }
        }

        // Sort subcategories by percentage (highest first) for pattern building
        $sorted_subcats = array();
        foreach ($sales_mix as $subcat_id => $percentage) {
            if (isset($slots_per_cycle[$subcat_id])) {
                $sorted_subcats[] = array(
                    'id' => $subcat_id,
                    'percentage' => $percentage,
                    'slots' => $slots_per_cycle[$subcat_id]
                );
            }
        }
        usort($sorted_subcats, function($a, $b) {
            return $b['percentage'] - $a['percentage'];
        });

        // Interleave products in cycles (based on products_per_page)
        $interleaved = array();
        $indices = array_fill_keys(array_keys($buckets), 0);
        $total_products = count($products);

        while (count($interleaved) < $total_products) {
            // For each cycle, add products according to slot allocation
            foreach ($sorted_subcats as $subcat_info) {
                $subcat_id = $subcat_info['id'];
                $slots = $subcat_info['slots'];

                // Add this subcategory's products for this cycle
                for ($i = 0; $i < $slots && count($interleaved) < $total_products; $i++) {
                    if ($indices[$subcat_id] < count($buckets[$subcat_id])) {
                        $interleaved[] = $buckets[$subcat_id][$indices[$subcat_id]];
                        $indices[$subcat_id]++;
                    } else {
                        // This subcategory is exhausted, try to fill with another
                        $filled = false;
                        foreach ($buckets as $other_id => $other_bucket) {
                            if ($indices[$other_id] < count($other_bucket)) {
                                $interleaved[] = $other_bucket[$indices[$other_id]];
                                $indices[$other_id]++;
                                $filled = true;
                                break;
                            }
                        }
                        if (!$filled) {
                            break 2; // Exit both loops if no more products
                        }
                    }
                }
            }
        }

        return $interleaved;
    }

    /**
     * Get sales mix percentages for a parent category
     *
     * @param int $parent_category_id Parent category ID
     * @return array Subcategory ID => percentage mapping
     */
    private function getSalesMixPercentages($parent_category_id) {
        $query = $this->db->query("
            SELECT subcategory_id, sales_percentage
            FROM " . DB_PREFIX . "category_sales_mix
            WHERE parent_category_id = '" . (int)$parent_category_id . "'
        ");

        $mix = array();
        foreach ($query->rows as $row) {
            $mix[$row['subcategory_id']] = (float)$row['sales_percentage'];
        }

        return $mix;
    }

    /**
     * Calculate 12-product interleaving pattern based on sales percentages
     * Determines which subcategory should appear at each position in a 12-product cycle
     *
     * @param array $sales_mix Subcategory ID => percentage mapping
     * @param array $buckets Product buckets by subcategory
     * @return array Pattern array with 12 subcategory IDs
     */
    private function calculateInterleavingPattern($sales_mix, $buckets) {
        $pattern = array();

        // If no sales mix data, use equal distribution
        if (empty($sales_mix)) {
            $subcategory_ids = array_keys($buckets);
            for ($i = 0; $i < 12; $i++) {
                $pattern[] = $subcategory_ids[$i % count($subcategory_ids)];
            }
            return $pattern;
        }

        // Convert percentages to counts in a 12-product cycle
        $allocations = array();
        $total_allocated = 0;

        foreach ($sales_mix as $subcat_id => $percentage) {
            // Skip if this subcategory has no products
            if (!isset($buckets[$subcat_id]) || empty($buckets[$subcat_id])) {
                continue;
            }

            // Calculate slots for this subcategory (round)
            $slots = round(($percentage / 100) * 12);
            $allocations[$subcat_id] = max(1, $slots); // Ensure at least 1 slot
            $total_allocated += $allocations[$subcat_id];
        }

        // Adjust if total doesn't equal 12
        if ($total_allocated > 12) {
            // Reduce highest percentage category
            arsort($allocations);
            $keys = array_keys($allocations);
            $allocations[$keys[0]] -= ($total_allocated - 12);
        } elseif ($total_allocated < 12) {
            // Add remaining slots to highest percentage category
            arsort($allocations);
            $keys = array_keys($allocations);
            $allocations[$keys[0]] += (12 - $total_allocated);
        }

        // Build the pattern array by distributing subcategories
        // Strategy: spread categories evenly rather than clustering
        $sorted_allocs = $allocations;
        arsort($sorted_allocs);

        $pattern = array_fill(0, 12, null);
        $position = 0;

        foreach ($sorted_allocs as $subcat_id => $count) {
            $step = 12 / $count;

            for ($i = 0; $i < $count; $i++) {
                $target_pos = round($position + ($i * $step));
                $target_pos = $target_pos % 12;

                // Find next available slot
                while ($pattern[$target_pos] !== null) {
                    $target_pos = ($target_pos + 1) % 12;
                }

                $pattern[$target_pos] = $subcat_id;
            }

            $position++;
        }

        // Fill any remaining nulls with the most common category
        $most_common = array_keys($sorted_allocs)[0];
        for ($i = 0; $i < 12; $i++) {
            if ($pattern[$i] === null) {
                $pattern[$i] = $most_common;
            }
        }

        return $pattern;
    }

    /**
     * Get immediate subcategories of a category
     *
     * @param int $category_id Parent category ID
     * @return array Subcategory IDs
     */
    private function getSubcategories($category_id) {
        $query = $this->db->query("
            SELECT category_id
            FROM " . DB_PREFIX . "category
            WHERE parent_id = '" . (int)$category_id . "'
        ");

        $subcategories = array();
        foreach ($query->rows as $row) {
            $subcategories[] = $row['category_id'];
        }

        return $subcategories;
    }

    /**
     * OPTIMIZED: Load all product-to-category mappings in ONE query
     *
     * @param array $product_ids Array of product IDs
     * @return array Map of product_id => array of category_ids
     */
    private function getProductCategories($product_ids) {
        if (empty($product_ids)) {
            return array();
        }

        $product_categories = array();

        $query = $this->db->query("
            SELECT product_id, category_id
            FROM " . DB_PREFIX . "product_to_category
            WHERE product_id IN (" . implode(',', array_map('intval', $product_ids)) . ")
        ");

        foreach ($query->rows as $row) {
            $product_id = $row['product_id'];
            if (!isset($product_categories[$product_id])) {
                $product_categories[$product_id] = array();
            }
            $product_categories[$product_id][] = $row['category_id'];
        }

        return $product_categories;
    }

    /**
     * OPTIMIZED: Load entire category hierarchy in ONE query
     *
     * @return array Map of category_id => parent_id
     */
    private function getCategoryHierarchy() {
        static $hierarchy = null;

        if ($hierarchy === null) {
            $hierarchy = array();

            $query = $this->db->query("
                SELECT category_id, parent_id
                FROM " . DB_PREFIX . "category
            ");

            foreach ($query->rows as $row) {
                $hierarchy[(int)$row['category_id']] = (int)$row['parent_id'];
            }
        }

        return $hierarchy;
    }

    /**
     * OPTIMIZED: Get which subcategory a product belongs to (using cached data)
     *
     * @param int $product_id Product ID
     * @param array $subcategories List of valid subcategory IDs
     * @param array $product_categories Cached product-to-category mappings
     * @param array $category_hierarchy Cached category hierarchy (category_id => parent_id)
     * @return int Subcategory ID (or 0 if not found)
     */
    private function getProductSubcategoryFromCache($product_id, $subcategories, $product_categories, $category_hierarchy) {
        if (!isset($product_categories[$product_id])) {
            return 0;
        }

        // Find first matching subcategory
        foreach ($product_categories[$product_id] as $category_id) {
            if (in_array($category_id, $subcategories)) {
                return $category_id;
            }

            // Also check if this category is a child of one of the subcategories
            foreach ($subcategories as $subcat) {
                if ($this->isCategoryChildCached($category_id, $subcat, $category_hierarchy)) {
                    return $subcat;
                }
            }
        }

        return 0; // Not in any recognized subcategory
    }

    /**
     * DEPRECATED: Get which subcategory a product belongs to (old method - kept for compatibility)
     *
     * @param int $product_id Product ID
     * @param array $subcategories List of valid subcategory IDs
     * @return int Subcategory ID (or 0 if not found)
     */
    private function getProductSubcategory($product_id, $subcategories) {
        // Get product categories
        $query = $this->db->query("
            SELECT category_id
            FROM " . DB_PREFIX . "product_to_category
            WHERE product_id = '" . (int)$product_id . "'
        ");

        // Find first matching subcategory
        foreach ($query->rows as $row) {
            if (in_array($row['category_id'], $subcategories)) {
                return $row['category_id'];
            }

            // Also check if this category is a child of one of the subcategories
            foreach ($subcategories as $subcat) {
                if ($this->isCategoryChild($row['category_id'], $subcat)) {
                    return $subcat;
                }
            }
        }

        return 0; // Not in any recognized subcategory
    }

    /**
     * OPTIMIZED: Check if a category is a child/descendant of another category (using cached hierarchy)
     *
     * @param int $category_id Category to check
     * @param int $parent_id Potential parent category
     * @param array $hierarchy Cached category hierarchy (category_id => parent_id)
     * @return bool True if category_id is a descendant of parent_id
     */
    private function isCategoryChildCached($category_id, $parent_id, $hierarchy) {
        if (!isset($hierarchy[$category_id])) {
            return false;
        }

        $current_parent = $hierarchy[$category_id];

        if ($current_parent == $parent_id) {
            return true;
        }

        if ($current_parent > 0) {
            // Recursive check using cached hierarchy (no database queries!)
            return $this->isCategoryChildCached($current_parent, $parent_id, $hierarchy);
        }

        return false;
    }

    /**
     * DEPRECATED: Check if a category is a child/descendant of another category (old method - kept for compatibility)
     *
     * @param int $category_id Category to check
     * @param int $parent_id Potential parent category
     * @return bool True if category_id is a descendant of parent_id
     */
    private function isCategoryChild($category_id, $parent_id) {
        $query = $this->db->query("
            SELECT parent_id
            FROM " . DB_PREFIX . "category
            WHERE category_id = '" . (int)$category_id . "'
        ");

        if (!$query->num_rows) {
            return false;
        }

        $current_parent = $query->row['parent_id'];

        if ($current_parent == $parent_id) {
            return true;
        }

        if ($current_parent > 0) {
            return $this->isCategoryChild($current_parent, $parent_id);
        }

        return false;
    }

    /**
     * Remove a specific preference value
     * @param string $type Preference type (size, color, gender, sport)
     * @param string $value Preference value to remove
     * @return bool Success status
     */
    public function removePreference($type, $value) {
        $user = $this->getUserIdentifier();

        // Get current preferences
        $preferences = $this->getPreferences();

        // Map type to plural form (size -> sizes, color -> colors, etc.)
        $type_key = $type . 's';

        // Validate type_key exists
        if (!isset($preferences[$type_key])) {
            return false;
        }

        // Remove the value from the preference array
        if (isset($preferences[$type_key][$value])) {
            unset($preferences[$type_key][$value]);

            // Save all preferences (this ensures consistency)
            $this->savePreferences($preferences);

            return true;
        }

        return false;
    }

    /**
     * Clear all preferences for the current user
     * Used when user disables Smart Sorting
     * @return bool Success status
     */
    public function clearAllPreferences() {
        $user = $this->getUserIdentifier();

        // Create empty preferences structure
        $empty_preferences = array(
            'sizes' => array(),
            'colors' => array(),
            'genders' => array(),
            'sports' => array()
        );

        // Save empty preferences
        $this->savePreferences($empty_preferences);

        return true;
    }

    /**
     * Check if Smart Sorting is enabled for the current user
     * @return bool True if enabled, false otherwise
     */
    public function isSmartSortingEnabled() {
        $user = $this->getUserIdentifier();

        // Return default for bots
        if ($user === null) {
            return true;
        }

        if ($user['type'] == 'user') {
            $query = $this->db->query("
                SELECT smart_sorting_enabled FROM `" . DB_PREFIX . "user_preferences`
                WHERE user_id = '" . (int)$user['id'] . "'
            ");
        } else {
            $query = $this->db->query("
                SELECT smart_sorting_enabled FROM `" . DB_PREFIX . "guest_preferences`
                WHERE guest_hash = '" . $this->db->escape($user['id']) . "'
            ");
        }

        if ($query->num_rows) {
            return (bool)$query->row['smart_sorting_enabled'];
        }

        // Default to enabled if no record exists yet
        return true;
    }

    /**
     * Enable Smart Sorting for the current user
     * Creates a record if it doesn't exist
     * @return bool Success status
     */
    public function enableSmartSorting() {
        $user = $this->getUserIdentifier();

        if ($this->isDebugMode()) {
            $this->log->write('[enableSmartSorting] User: ' . $user['type'] . ' | ID: ' . $user['id']);
        }

        if ($user['type'] == 'user') {
            // Check existing preferences first
            $check = $this->db->query("
                SELECT * FROM `" . DB_PREFIX . "user_preferences`
                WHERE user_id = '" . (int)$user['id'] . "'
            ");

            if ($this->isDebugMode()) {
                $this->log->write('[enableSmartSorting] User record exists: ' . ($check->num_rows ? 'yes' : 'no'));
                if ($check->num_rows) {
                    $this->log->write('[enableSmartSorting] Current preferences: ' . json_encode($check->row));
                }
            }

            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "user_preferences`
                SET user_id = '" . (int)$user['id'] . "',
                    smart_sorting_enabled = 1,
                    sizes = '{}',
                    colors = '{}',
                    genders = '{}',
                    sports = '{}',
                    last_updated = NOW()
                ON DUPLICATE KEY UPDATE
                    smart_sorting_enabled = 1,
                    last_updated = NOW()
            ");
        } else {
            // Check existing preferences first
            $check = $this->db->query("
                SELECT * FROM `" . DB_PREFIX . "guest_preferences`
                WHERE guest_hash = '" . $this->db->escape($user['id']) . "'
            ");

            if ($this->isDebugMode()) {
                $this->log->write('[enableSmartSorting] Guest record exists: ' . ($check->num_rows ? 'yes' : 'no'));
                if ($check->num_rows) {
                    $this->log->write('[enableSmartSorting] Current preferences: ' . json_encode($check->row));
                }
            }

            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "guest_preferences`
                SET guest_hash = '" . $this->db->escape($user['id']) . "',
                    smart_sorting_enabled = 1,
                    sizes = '{}',
                    colors = '{}',
                    genders = '{}',
                    sports = '{}',
                    last_seen = NOW()
                ON DUPLICATE KEY UPDATE
                    smart_sorting_enabled = 1,
                    last_seen = NOW()
            ");
        }

        if ($this->isDebugMode()) {
            $this->log->write('[enableSmartSorting] Smart sorting enabled successfully');
        }

        return true;
    }

    /**
     * Disable Smart Sorting for the current user
     * Clears all preferences and sets enabled flag to 0
     * @return bool Success status
     */
    public function disableSmartSorting() {
        $user = $this->getUserIdentifier();

        if ($user['type'] == 'user') {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "user_preferences`
                SET user_id = '" . (int)$user['id'] . "',
                    smart_sorting_enabled = 0,
                    sizes = '{}',
                    colors = '{}',
                    genders = '{}',
                    sports = '{}',
                    last_updated = NOW()
                ON DUPLICATE KEY UPDATE
                    smart_sorting_enabled = 0,
                    sizes = '{}',
                    colors = '{}',
                    genders = '{}',
                    sports = '{}',
                    last_updated = NOW()
            ");
        } else {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "guest_preferences`
                SET guest_hash = '" . $this->db->escape($user['id']) . "',
                    smart_sorting_enabled = 0,
                    sizes = '{}',
                    colors = '{}',
                    genders = '{}',
                    sports = '{}',
                    last_seen = NOW()
                ON DUPLICATE KEY UPDATE
                    smart_sorting_enabled = 0,
                    sizes = '{}',
                    colors = '{}',
                    genders = '{}',
                    sports = '{}',
                    last_seen = NOW()
            ");
        }

        return true;
    }

    /**
     * Get available attribute values for autocomplete
     * Returns sizes, colors, genders, and sports
     */
    public function getAvailableAttributeValues($search = '') {
        $results = array();
        $search = trim(mb_strtolower($search, 'UTF-8'));

        // Check cache first (10 minute cache)
        $cache_key = 'adaptive_filter.autocomplete.' . md5($search . '_' . $this->config->get('config_language_id'));
        $cached = $this->cache->get($cache_key);
        if ($cached !== false && $cached !== null) {
            return $cached;
        }

        // Load language file for gender translations
        $this->load->language('extension/module/adaptive_filter');

        // Get configured attribute IDs
        $color_attribute_ids = $this->config->get('module_adaptive_filter_color_attribute_ids') ?? '';
        $color_attribute_ids_array = array_filter(array_map('trim', explode(',', $color_attribute_ids)));

        $size_option_ids = $this->config->get('module_adaptive_filter_size_option_ids') ?? '';
        $size_option_ids_array = array_filter(array_map('trim', explode(',', $size_option_ids)));

        // 1. GENDERS FROM LANGUAGE FILES
        $genders = array(
            'Men' => $this->language->get('gender_men'),
            'Women' => $this->language->get('gender_women'),
            'Children' => $this->language->get('gender_children')
        );

        foreach ($genders as $gender_key => $gender_label) {
            // Search in both the key and the localized label (case-insensitive, UTF-8 safe)
            $search_in_key = mb_strtolower($gender_key, 'UTF-8');
            $search_in_label = mb_strtolower($gender_label, 'UTF-8');

            if (!$search || strpos($search_in_key, $search) !== false || strpos($search_in_label, $search) !== false) {
                // Select appropriate gender icon
                $gender_icon = '⚧'; // Default for Children
                if ($gender_key === 'Men') {
                    $gender_icon = '♂';
                } elseif ($gender_key === 'Women') {
                    $gender_icon = '♀';
                }

                $results[] = array(
                    'type' => 'gender',
                    'value' => $gender_key,  // Store internal key (English)
                    'label' => $gender_icon . ' ' . $gender_label  // Display localized label with icon
                );
            }
        }

        // 2. SPORTS FROM CATEGORY MAPPINGS
        $sport_mappings_query = $this->db->query("
            SELECT sport
            FROM " . DB_PREFIX . "sport_mapping
            GROUP BY sport
            ORDER BY sport
        ");

        // Get configured default sport icon
        $default_sport_icon = $this->config->get('module_adaptive_filter_sport_icon') ?? '🎾';

        foreach ($sport_mappings_query->rows as $row) {
            if (!empty($row['sport'])) {
                // Case-insensitive UTF-8 safe search
                if (!$search || mb_stripos($row['sport'], $search, 0, 'UTF-8') !== false) {
                    // Try to get sport-specific icon from language file
                    $sport_key = 'sport_icon_' . $row['sport'];
                    $sport_icon = $this->language->get($sport_key);

                    // If no specific icon found, use default
                    if ($sport_icon === $sport_key) {
                        $sport_icon = $this->language->get('sport_icon_default');
                        if ($sport_icon === 'sport_icon_default') {
                            $sport_icon = $default_sport_icon;
                        }
                    }

                    $results[] = array(
                        'type' => 'sport',
                        'value' => $row['sport'],
                        'label' => $sport_icon . ' ' . $row['sport']
                    );
                }
            }
        }

        // 3. COLORS FROM PRODUCT ATTRIBUTES
        // Get configured color icon
        $color_icon = $this->config->get('module_adaptive_filter_color_icon') ?? '🎨';

        if (!empty($color_attribute_ids_array)) {
            $search_condition = $search ? "AND LOWER(pa.text) LIKE '%" . $this->db->escape($search) . "%'" : "";

            $color_query = $this->db->query("
                SELECT DISTINCT pa.text
                FROM " . DB_PREFIX . "product_attribute pa
                LEFT JOIN " . DB_PREFIX . "product_to_store p2s
                    ON pa.product_id = p2s.product_id
                WHERE pa.attribute_id IN (" . implode(',', array_map('intval', $color_attribute_ids_array)) . ")
                    AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
                    AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "'
                    " . $search_condition . "
                ORDER BY pa.text
                LIMIT 20
            ");

            foreach ($color_query->rows as $row) {
                if (!empty($row['text'])) {
                    $results[] = array(
                        'type' => 'color',
                        'value' => $row['text'],
                        'label' => $color_icon . ' ' . $row['text']
                    );
                }
            }
        }

        // 4. SIZES FROM PRODUCT OPTIONS
        // Get configured size icons
        $shoe_size_icon = $this->config->get('module_adaptive_filter_shoe_size_icon') ?? '👟';
        $apparel_size_icon = $this->config->get('module_adaptive_filter_apparel_size_icon') ?? '👕';

        if (!empty($size_option_ids_array)) {
            $search_condition = $search ? "AND LOWER(ovd.name) LIKE '%" . $this->db->escape($search) . "%'" : "";

            $size_query = $this->db->query("
                SELECT DISTINCT ovd.name
                FROM " . DB_PREFIX . "product_option_value pov
                LEFT JOIN " . DB_PREFIX . "option_value_description ovd
                    ON pov.option_value_id = ovd.option_value_id
                    AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'
                LEFT JOIN " . DB_PREFIX . "product_to_store p2s
                    ON pov.product_id = p2s.product_id
                WHERE pov.option_id IN (" . implode(',', array_map('intval', $size_option_ids_array)) . ")
                    AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
                    " . $search_condition . "
                ORDER BY ovd.name
                LIMIT 20
            ");

            foreach ($size_query->rows as $row) {
                if (!empty($row['name'])) {
                    // Detect shoe size vs apparel size based on "US(" pattern (case insensitive)
                    $is_shoe_size = preg_match('/US\s*\(/i', $row['name']);
                    $size_icon = $is_shoe_size ? $shoe_size_icon : $apparel_size_icon;

                    $results[] = array(
                        'type' => 'size',
                        'value' => $row['name'],
                        'label' => $size_icon . ' ' . $row['name']
                    );
                }
            }
        }

        // Store in cache for 10 minutes (600 seconds)
        $this->cache->set($cache_key, $results, 600);

        return $results;
    }

}
