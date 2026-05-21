<?php
/**
 * Adaptive Filter - Signal Capture Controller
 * Handles event captures for product views, cart adds, purchases
 */

class ControllerExtensionModuleAdaptiveFilter extends Controller {

    /**
     * Intercept product listing controllers to apply sort persistence and personalized sorting
     * This runs BEFORE the controller executes, allowing us to modify $this->request->get
     */
    public function beforeProductListing(&$route, &$args) {
        if (!$this->config->get('module_adaptive_filter_status')) {
            return;
        }

        // Only apply to product listing pages
        // Note: manufacturer has /info suffix, so we check with strpos
        $listing_routes = array('product/category', 'product/special', 'product/search', 'product/manufacturer');
        $is_listing_page = false;
        foreach ($listing_routes as $listing_route) {
            if (strpos($route, $listing_route) === 0) {
                $is_listing_page = true;
                break;
            }
        }

        if (!$is_listing_page) {
            return;
        }

        $this->load->model('extension/module/adaptive_filter');

        // Clean up if 'enable-personalized' is stuck in session (from previous bug)
        if (isset($this->session->data['user_sort_preference']) &&
            $this->session->data['user_sort_preference'] === 'enable-personalized') {
            unset($this->session->data['user_sort_preference']);
            unset($this->session->data['user_order_preference']);
        }

        // Handle sort persistence
        if (isset($this->request->get['sort'])) {
            // User explicitly selected a sort - store it (but not the 'enable-personalized' trigger)
            if ($this->request->get['sort'] !== 'enable-personalized' &&
                !($this->request->get['sort'] === 'personalized' &&
                !$this->model_extension_module_adaptive_filter->isSmartSortingEnabled())) {
                $this->session->data['user_sort_preference'] = $this->request->get['sort'];
                if (isset($this->request->get['order'])) {
                    $this->session->data['user_order_preference'] = $this->request->get['order'];
                }
            }
        } else {
            // No explicit sort in URL - check for stored preference
            if (isset($this->session->data['user_sort_preference'])) {
                if ($this->session->data['user_sort_preference'] === 'personalized' &&
                    !$this->model_extension_module_adaptive_filter->isSmartSortingEnabled()) {
                    unset($this->session->data['user_sort_preference']);
                    unset($this->session->data['user_order_preference']);
                } else {
                    // Apply stored sort preference
                    $this->request->get['sort'] = $this->session->data['user_sort_preference'];
                    if (isset($this->session->data['user_order_preference'])) {
                        $this->request->get['order'] = $this->session->data['user_order_preference'];
                    }
                }
            } else {
                // No stored preference - check if we should default to personalized
                if ($this->model_extension_module_adaptive_filter->hasActivePreferences()) {
                    // User has preferences - default to personalized sort
                    $this->request->get['sort'] = 'personalized';
                    $this->request->get['order'] = 'DESC';
                }
            }
        }
    }

    /**
     * Capture product view signal (weight: 1)
     * Records color, gender, sport (NOT sizes - only on explicit action)
     */
    public function captureProductView(&$route, &$data, &$output) {
        if (!$this->config->get('module_adaptive_filter_status')) {
            return;
        }

        $product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;

        if (!$product_id) {
            return;
        }

        $this->load->model('extension/module/adaptive_filter');

        // Get product attributes (color, gender, sport)
        // Note: getProductAttributes() no longer returns size data
        $attributes = $this->model_extension_module_adaptive_filter->getProductAttributes($product_id);

        // Record color, gender, sport with configured weight
        $weight = (int)($this->config->get('module_adaptive_filter_weight_view') ?? 1);
        $this->model_extension_module_adaptive_filter->recordSignal('product_view', $attributes, $weight);
    }

    /**
     * Capture add to cart signal (weight: 5)
     *
     * Reads from cart table after product is added - more reliable than POST data
     */
    public function captureAddToCart(&$route, &$args, &$output) {
        if (!$this->config->get('module_adaptive_filter_status')) {
            return;
        }

        // Try to get product_id from multiple sources
        $product_id = 0;

        // Try args first (for standard calls)
        if (isset($args[0])) {
            $product_id = (int)$args[0];
        }
        // Try POST data (for AJAX calls)
        elseif (isset($this->request->post['product_id'])) {
            $product_id = (int)$this->request->post['product_id'];
        }

        if (!$product_id) {
            return;
        }

        $this->load->model('extension/module/adaptive_filter');

        // Get product base attributes (color, gender, sport)
        $attributes = $this->model_extension_module_adaptive_filter->getProductAttributes($product_id);

        // Get the most recent cart entry for this product to extract size
        // This is more reliable than POST data and works with AJAX, API, etc.
        // IMPORTANT: Filter by session_id to avoid capturing options from old abandoned carts
        $cart_query = $this->db->query("
            SELECT `option`
            FROM " . DB_PREFIX . "cart
            WHERE session_id = '" . $this->db->escape($this->session->getId()) . "'
                AND product_id = '" . (int)$product_id . "'
            ORDER BY date_added DESC
            LIMIT 1
        ");

        if ($cart_query->num_rows && !empty($cart_query->row['option'])) {
            // Parse the JSON options from cart
            // Format: {"product_option_value_id": "product_option_value_id"}
            $options = json_decode($cart_query->row['option'], true);

            if ($options) {
                // Get configured size option IDs
                $size_option_ids = $this->config->get('module_adaptive_filter_size_option_ids') ?? '';
                $size_option_ids_array = array_filter(array_map('trim', explode(',', $size_option_ids)));

                // Cart stores product_option_value_id as the value (not option_value_id)
                // We need to look up the option_id from product_option_value table
                foreach ($options as $cart_key => $product_option_value_id) {
                    // Get the option_id for this product_option_value_id
                    $option_query = $this->db->query("
                        SELECT pov.option_id, ovd.name
                        FROM " . DB_PREFIX . "product_option_value pov
                        LEFT JOIN " . DB_PREFIX . "option_value_description ovd
                            ON pov.option_value_id = ovd.option_value_id
                            AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'
                        WHERE pov.product_option_value_id = '" . (int)$product_option_value_id . "'
                    ");

                    if ($option_query->num_rows) {
                        $option_id = $option_query->row['option_id'];

                        // Check if this option_id is a configured size option
                        if (in_array($option_id, $size_option_ids_array)) {
                            $attributes['size'] = $option_query->row['name'];
                            break; // Only capture first size option
                        }
                    }
                }
            }
        }

        // Record signal with configured weight
        $weight = (int)($this->config->get('module_adaptive_filter_weight_cart') ?? 5);
        $this->model_extension_module_adaptive_filter->recordSignal('add_to_cart', $attributes, $weight);
    }

    /**
     * Get personalized product recommendations
     */
    public function getPersonalizedProducts() {
        $this->load->model('extension/module/adaptive_filter');

        $category_id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;
        $limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;

        $products = $this->model_extension_module_adaptive_filter->getPersonalizedProducts($category_id, $limit);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($products));
    }

    /**
     * Display user preferences (for debugging)
     */
    public function displayPreferences() {
        $this->load->model('extension/module/adaptive_filter');

        $preferences = $this->model_extension_module_adaptive_filter->getPreferences();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($preferences, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Remove a specific preference (AJAX endpoint)
     */
    public function removePreference() {
        $this->load->model('extension/module/adaptive_filter');

        $json = array('success' => false);

        if (isset($this->request->post['type']) && isset($this->request->post['value'])) {
            $type = $this->request->post['type'];
            $value = $this->request->post['value'];

            $result = $this->model_extension_module_adaptive_filter->removePreference($type, $value);

            if ($result) {
                $json['success'] = true;
                $json['message'] = 'Preference removed successfully';
            } else {
                $json['message'] = 'Failed to remove preference';
            }
        } else {
            $json['message'] = 'Missing type or value parameter';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Get available attribute values for autocomplete (AJAX endpoint)
     */
    public function getAvailableValues() {
        // Clean output buffer to prevent any unwanted output
        if (ob_get_level()) {
            ob_clean();
        }

        try {
            $this->load->model('extension/module/adaptive_filter');

            $search = isset($this->request->get['search']) ? $this->request->get['search'] : '';

            // Get all unique attribute values from products
            $values = $this->model_extension_module_adaptive_filter->getAvailableAttributeValues($search);

            // Ensure we always return an array
            if (!is_array($values)) {
                $values = array();
            }

            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode($values, JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode(array('error' => $e->getMessage())));
        }
    }

    /**
     * Add a manual preference (AJAX endpoint)
     */
    public function addPreference() {
        $this->load->language('extension/module/adaptive_filter');
        $this->load->model('extension/module/adaptive_filter');

        $json = array('success' => false);

        if (isset($this->request->post['type']) && isset($this->request->post['value'])) {
            if (!$this->model_extension_module_adaptive_filter->isSmartSortingEnabled()) {
                $json['message'] = $this->language->get('error_smart_sorting_disabled');
            } else {
            $type = $this->request->post['type'];
            $value = $this->request->post['value'];

            // Manual add replaces the touched dimension with the maximum preference value.
            $attributes = array($type => $value);
            $this->model_extension_module_adaptive_filter->recordSignal('manual_add', $attributes);

            $json['success'] = true;
            $json['message'] = 'Preference added successfully';
            }
        } else {
            $json['message'] = 'Missing type or value parameter';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Enable Smart Sorting (AJAX endpoint)
     */
    public function enableSmartSorting() {
        $this->load->model('extension/module/adaptive_filter');

        $json = array('success' => false);

        // Enable Smart Sorting
        $result = $this->model_extension_module_adaptive_filter->enableSmartSorting();

        if ($result) {
            $json['success'] = true;
            $json['message'] = 'Smart Sorting enabled successfully';
        } else {
            $json['message'] = 'Failed to enable Smart Sorting';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Disable Smart Sorting - Clear all preferences and disable personalized sort (AJAX endpoint)
     */
    public function disableSmartSorting() {
        $this->load->model('extension/module/adaptive_filter');

        $json = array('success' => false);

        // Disable Smart Sorting and clear preferences
        $result = $this->model_extension_module_adaptive_filter->disableSmartSorting();

        // Clear sort preference from session
        if (isset($this->session->data['user_sort_preference'])) {
            unset($this->session->data['user_sort_preference']);
        }
        if (isset($this->session->data['user_order_preference'])) {
            unset($this->session->data['user_order_preference']);
        }

        if ($result) {
            $json['success'] = true;
            $json['message'] = 'Smart Sorting disabled successfully';
        } else {
            $json['message'] = 'Failed to disable Smart Sorting';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Render preferences widget
     * Called by other controllers to get the rendered HTML
     */
    public function renderPreferencesWidget() {
        // Only render the widget on product listing pages (category, manufacturer, special)
        // This prevents it from appearing in popups, login pages, account pages, etc.
        $route = isset($this->request->get['route']) ? $this->request->get['route'] : '';
        $allowed_routes = array('product/category', 'product/special', 'product/manufacturer');
        $is_listing_page = false;

        foreach ($allowed_routes as $allowed_route) {
            if (strpos($route, $allowed_route) === 0) {
                $is_listing_page = true;
                break;
            }
        }

        if (!$is_listing_page) {
            return '';
        }

        $this->load->language('extension/module/adaptive_filter');
        $this->load->model('extension/module/adaptive_filter');

        $data['debug_mode'] = $this->model_extension_module_adaptive_filter->isDebugMode();
        $data['user_preferences'] = $this->model_extension_module_adaptive_filter->getActivePreferences();
        $data['raw_preferences'] = $data['debug_mode'] ? $this->model_extension_module_adaptive_filter->getStoredPreferences() : array();

        if (!$this->model_extension_module_adaptive_filter->hasPreferences($data['user_preferences'])) {
            return '';
        }

        $data['text_your_preferences'] = $this->language->get('text_your_preferences');
        $data['text_add_preference'] = $this->language->get('text_add_preference');
        $data['text_disable_smart_sorting'] = $this->language->get('text_disable_smart_sorting');

        // Add gender translations for display
        $data['gender_labels'] = array(
            'Men' => $this->language->get('gender_men'),
            'Women' => $this->language->get('gender_women'),
            'Children' => $this->language->get('gender_children')
        );

        // Add configured icons (use ?: to treat empty string same as null)
        $data['shoe_size_icon'] = $this->config->get('module_adaptive_filter_shoe_size_icon') ?: '👟';
        $data['apparel_size_icon'] = $this->config->get('module_adaptive_filter_apparel_size_icon') ?: '👕';
        $data['color_icon'] = $this->config->get('module_adaptive_filter_color_icon') ?: '🎨';
        $data['default_sport_icon'] = $this->config->get('module_adaptive_filter_sport_icon') ?: '🎾';

        // Build sport icon map from language file
        $data['sport_icons'] = array();
        if (!empty($data['user_preferences']['sports'])) {
            foreach ($data['user_preferences']['sports'] as $sport => $count) {
                $sport_key = 'sport_icon_' . $sport;
                $sport_icon = $this->language->get($sport_key);

                if ($sport_icon === $sport_key) {
                    $sport_icon = $this->language->get('sport_icon_default');
                    if ($sport_icon === 'sport_icon_default') {
                        $sport_icon = $data['default_sport_icon'];
                    }
                }

                $data['sport_icons'][$sport] = $sport_icon;
            }
        }

        return $this->load->view('extension/module/adaptive_filter_preferences', $data);
    }

    /**
     * Render mobile button only (for body-level rendering)
     * Called by footer controller to render mobile button as direct child of body
     */
    public function renderMobileButton() {
        // Only render the mobile button on product listing pages (category, manufacturer, special)
        // This prevents it from appearing in popups, login pages, account pages, etc.
        $route = isset($this->request->get['route']) ? $this->request->get['route'] : '';
        $allowed_routes = array('product/category', 'product/special', 'product/manufacturer');
        $is_listing_page = false;

        foreach ($allowed_routes as $allowed_route) {
            if (strpos($route, $allowed_route) === 0) {
                $is_listing_page = true;
                break;
            }
        }

        if (!$is_listing_page) {
            return '';
        }

        $this->load->language('extension/module/adaptive_filter');
        $this->load->model('extension/module/adaptive_filter');

        $data['debug_mode'] = $this->model_extension_module_adaptive_filter->isDebugMode();
        $data['user_preferences'] = $this->model_extension_module_adaptive_filter->getActivePreferences();
        $data['raw_preferences'] = $data['debug_mode'] ? $this->model_extension_module_adaptive_filter->getStoredPreferences() : array();

        // Only render if user has preferences
        if (!$this->model_extension_module_adaptive_filter->hasPreferences($data['user_preferences'])) {
            return '';
        }

        $data['text_your_preferences'] = $this->language->get('text_your_preferences');
        $data['text_add_preference'] = $this->language->get('text_add_preference');
        $data['text_disable_smart_sorting'] = $this->language->get('text_disable_smart_sorting');

        // Add gender translations for display
        $data['gender_labels'] = array(
            'Men' => $this->language->get('gender_men'),
            'Women' => $this->language->get('gender_women'),
            'Children' => $this->language->get('gender_children')
        );

        // Add configured icons (use ?: to treat empty string same as null)
        $data['shoe_size_icon'] = $this->config->get('module_adaptive_filter_shoe_size_icon') ?: '👟';
        $data['apparel_size_icon'] = $this->config->get('module_adaptive_filter_apparel_size_icon') ?: '👕';
        $data['color_icon'] = $this->config->get('module_adaptive_filter_color_icon') ?: '🎨';
        $data['default_sport_icon'] = $this->config->get('module_adaptive_filter_sport_icon') ?: '🎾';

        // Build sport icon map from language file
        $data['sport_icons'] = array();
        if (!empty($data['user_preferences']['sports'])) {
            foreach ($data['user_preferences']['sports'] as $sport => $count) {
                $sport_key = 'sport_icon_' . $sport;
                $sport_icon = $this->language->get($sport_key);

                if ($sport_icon === $sport_key) {
                    $sport_icon = $this->language->get('sport_icon_default');
                    if ($sport_icon === 'sport_icon_default') {
                        $sport_icon = $data['default_sport_icon'];
                    }
                }

                $data['sport_icons'][$sport] = $sport_icon;
            }
        }

        // Calculate total preferences for badge
        $total_prefs = 0;
        if (!empty($data['user_preferences']['sizes'])) {
            $total_prefs += count($data['user_preferences']['sizes']);
        }
        if (!empty($data['user_preferences']['colors'])) {
            $total_prefs += count($data['user_preferences']['colors']);
        }
        if (!empty($data['user_preferences']['genders'])) {
            $total_prefs += count($data['user_preferences']['genders']);
        }
        if (!empty($data['user_preferences']['sports'])) {
            $total_prefs += count($data['user_preferences']['sports']);
        }
        $data['total_prefs'] = $total_prefs;

        return $this->load->view('extension/module/adaptive_filter_mobile_button', $data);
    }

    /**
     * Render assets (CSS and JavaScript)
     * Called by other controllers to get the rendered HTML
     */
    public function renderAssets() {
        $this->load->language('extension/module/adaptive_filter');
        $this->load->model('extension/module/adaptive_filter');

        $data['user_preferences'] = $this->model_extension_module_adaptive_filter->getActivePreferences();

        if (!$this->model_extension_module_adaptive_filter->hasPreferences($data['user_preferences'])) {
            return '';
        }

        $data['text_no_results_found'] = $this->language->get('text_no_results_found');
        $data['text_disable_confirm'] = $this->language->get('text_disable_confirm');

        // Check if personalized sort is active (either from session or should be default)
        $is_personalized_default = false;

        // Check stored sort preference in session
        if (isset($this->session->data['user_sort_preference'])) {
            $is_personalized_default =
                $this->session->data['user_sort_preference'] === 'personalized' &&
                $this->model_extension_module_adaptive_filter->isSmartSortingEnabled();
        } else {
            $is_personalized_default = $this->model_extension_module_adaptive_filter->hasActivePreferences($data['user_preferences']);
        }

        $data['is_personalized_default'] = $is_personalized_default;

        return $this->load->view('extension/module/adaptive_filter_assets', $data);
    }
}
