<?php
/**
 * Bonus Display Widget Controller
 * Renders bonus information for products and cart
 */
class ControllerExtensionModuleBonusDisplay extends Controller {

	/**
	 * Display bonus for a single product
	 * @param array $args Array with 'product_id', 'price', 'special' (optional)
	 * @return string HTML output
	 */
	public function product($args = array()) {
		// Check if module is enabled
		if (!$this->config->get('module_bonus_manager_status')) {
			return '';
		}

		$this->load->model('extension/module/bonus_manager');
		$this->load->language('extension/module/bonus_manager');

		$data = array();

		// Get product info
		$product_id = isset($args['product_id']) ? (int)$args['product_id'] : 0;
		$price = isset($args['price']) ? (float)$args['price'] : 0;
		$special = isset($args['special']) ? (float)$args['special'] : 0;

		if (!$product_id || !$price) {
			return '';
		}

		// Determine customer group
		$customer_group_id = $this->customer->isLogged() ? $this->customer->getGroupId() : $this->config->get('config_customer_group_id');

		// Calculate final price
		$final_price = $special > 0 ? $special : $price;

		$data['bonus_amount'] = 0;
		$data['bonus_text'] = '';
		$data['has_heavy_discount'] = false;

		// Check if product category is excluded first
		$excluded_categories = $this->config->get('module_bonus_manager_excluded_categories') ?: array();
		$is_excluded = $this->isProductInExcludedCategory($product_id, $excluded_categories);

		// If category is excluded, don't show any bonus info at all
		if ($is_excluded) {
			// Return empty - no messages for excluded categories
			return '';
		}

		// Check if product has heavy discount
		$data['has_heavy_discount'] = $this->model_extension_module_bonus_manager->hasHeavyDiscount($product_id, $final_price);

		if ($data['has_heavy_discount']) {
			$data['no_bonus_text'] = $this->language->get('text_no_bonus_discount');
		} else {
			$bonus_amount = $this->model_extension_module_bonus_manager->getProductBonus($product_id, $customer_group_id, $final_price);

			if ($bonus_amount > 0) {
				$data['bonus_amount'] = $bonus_amount;
				$data['bonus_text'] = sprintf($this->language->get('text_bonus_earned'), number_format($bonus_amount, 0, '.', ' '));
			}
		}

		return $this->load->view('extension/module/bonus_display_product', $data);
	}

	/**
	 * Display total bonuses available in cart
	 *
	 * This method serves TWO different purposes depending on user login status:
	 *
	 * 1. FOR LOGGED-IN USERS:
	 *    - Calculates total bonus points that will be earned from current cart items
	 *    - Shows current bonus balance
	 *    - Displays flip-card widget with "Earn" side and "Spend" side
	 *    - Allows applying bonus points for payment (up to configured max %)
	 *    - Shows warnings about not earning bonuses if spending bonuses
	 *
	 * 2. FOR GUESTS (NOT LOGGED IN):
	 *    - Delegates to registerWidget() method
	 *    - Shows attractive registration widget encouraging sign-up
	 *    - Displays benefits of joining loyalty program
	 *    - Provides "Register Now" button that opens Journal3 modal
	 *
	 * SCOPE & USAGE:
	 * - Called from cart page template to display bonus information
	 * - Typically embedded in cart sidebar or below cart totals
	 * - Template location: catalog/view/theme/journal3/template/checkout/cart.twig
	 * - Can be called from any template using: {{ bonus_widget }}
	 *
	 * GUEST DETECTION LOGIC:
	 * The method checks $this->customer->isLogged() to determine user status:
	 * - isLogged() = false → Show registration widget (encourage sign-up)
	 * - isLogged() = true → Show bonus balance & earning potential
	 *
	 * This ensures guests are always presented with a call-to-action rather than
	 * empty space, improving conversion rates for loyalty program enrollment.
	 *
	 * @return string HTML output (either bonus balance widget or registration widget)
	 */
	public function cart() {
		// Check if bonus manager module is enabled globally
		// If disabled, return empty string (no widget displays)
		// Admin setting: Extensions → Modules → Bonus Manager → Status
		if (!$this->config->get('module_bonus_manager_status')) {
			return '';
		}

		// === GUEST DETECTION & ROUTING ===
		// Check if customer is logged in using OpenCart's customer library
		// $this->customer->isLogged() returns boolean:
		//   true = user authenticated with valid session
		//   false = guest visitor (no account or not logged in)
		if (!$this->customer->isLogged()) {
			// User is a GUEST - show registration widget instead of bonus balance
			// This encourages guests to register and join the loyalty program
			// Delegates to registerWidget() method which handles all guest UI logic
			return $this->registerWidget();
		}

		// === USER IS LOGGED IN - Continue with normal bonus display logic ===
		// From this point forward, we know the user has an account and is logged in
		// Calculate and display their bonus points and earning potential

		$this->load->model('extension/module/bonus_manager');
		$this->load->language('extension/module/bonus_manager');
		$this->load->model('catalog/product');

		$data = array();

		// Calculate total bonuses that would be earned from current cart
		$total_bonus = 0;
		$discount_threshold = (float)$this->config->get('module_bonus_manager_discount_threshold') ?: 15.0;
		$customer_group_id = $this->customer->getGroupId();
		$excluded_categories = $this->config->get('module_bonus_manager_excluded_categories') ?: array();

		// Get cart products
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			// Check if product category is excluded
			if ($this->isProductInExcludedCategory($product['product_id'], $excluded_categories)) {
				continue;
			}

			// Get product info
			$product_info = $this->model_catalog_product->getProduct($product['product_id']);

			if (!$product_info) {
				continue;
			}

			$base_price = (float)$product_info['price'];
			$final_price = (float)$product['price'];

			// Calculate product discount percentage
			$product_discount_percent = 0;
			if ($base_price > 0) {
				$product_discount_percent = (($base_price - $final_price) / $base_price) * 100;
			}

			// If product has >threshold discount, skip it
			if ($product_discount_percent > $discount_threshold) {
				continue;
			}

			// Get bonus percentage
			$bonus_percent = $this->model_extension_module_bonus_manager->getBonusPercent($customer_group_id, $product['product_id']);

			if ($bonus_percent <= 0) {
				continue;
			}

			// Calculate bonus for this product line
			$product_subtotal = $final_price * (int)$product['quantity'];
			$product_bonus = round($product_subtotal * $bonus_percent / 100, 2);

			$total_bonus += $product_bonus;
		}

		// Get customer's current bonus balance
		$current_balance = (int)$this->customer->getRewardPoints();

		// Calculate cart subtotal and max allowed bonus spending
		$cart_subtotal = 0;
		foreach ($products as $product) {
			$cart_subtotal += $product['total'];
		}

		$max_usage_percent = (float)$this->config->get('module_bonus_manager_max_usage_percent') ?: 30;
		$max_allowed_spend = ($cart_subtotal * $max_usage_percent) / 100;
		$max_can_spend = min($current_balance, $max_allowed_spend);

		$data['total_bonus'] = round($total_bonus);
		$data['current_balance'] = $current_balance;
		$data['new_balance'] = $current_balance + round($total_bonus);
		$data['cart_subtotal'] = $cart_subtotal;
		$data['max_can_spend'] = (int)$max_can_spend;
		$data['max_usage_percent'] = (int)$max_usage_percent;
		$data['applied_reward'] = isset($this->session->data['reward']) ? (int)$this->session->data['reward'] : 0;

		// Loyalty level name + widget gradient (matches badge colors from account/reward page)
		$current_level = $this->model_extension_module_bonus_manager->getLoyaltyLevel($customer_group_id);
		$loyalty_level_name = ($current_level && !empty($current_level['display_name'])) ? trim($current_level['display_name']) : '';
		if (!$loyalty_level_name) {
			$group_q = $this->db->query("SELECT name FROM " . DB_PREFIX . "customer_group_description WHERE customer_group_id = '" . (int)$customer_group_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
			if ($group_q->num_rows) {
				$loyalty_level_name = $group_q->row['name'];
			}
		}
		$color_name = $this->getLoyaltyLevelColor($current_level);
		$gradient_map = array(
			'silver'    => 'linear-gradient(135deg, #636e72 0%, #95a5a6 50%, #bdc3c7 100%)',
			'gold'      => 'linear-gradient(135deg, #b8860b 0%, #c9921a 50%, #e8c84d 100%)',
			'platinum'  => 'linear-gradient(135deg, #1a6fa8 0%, #4a90e2 50%, #87bbf0 100%)',
			'warning'   => 'linear-gradient(135deg, #cd7f32 0%, #d4935c 50%, #f0c080 100%)',
			'secondary' => 'linear-gradient(135deg, #4a4a4a 0%, #6c757d 50%, #8e8e8e 100%)',
		);
		$loyalty_gradient = isset($gradient_map[$color_name]) ? $gradient_map[$color_name] : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
		$data['loyalty_level_name'] = $loyalty_level_name;
		$data['loyalty_gradient'] = $loyalty_gradient;

		// Pass translation variables to template
		$data['text_bonus_earned'] = $this->language->get('text_bonus_earned');
		$data['text_bonus_balance'] = $this->language->get('text_bonus_balance');
		$data['text_balance'] = $this->language->get('text_balance');
		$data['text_you_will_earn'] = $this->language->get('text_you_will_earn');
		$data['text_balance_bonuses'] = $this->language->get('text_balance_bonuses');
		$data['text_your_balance'] = $this->language->get('text_your_balance');
		$data['text_bonuses_awarded'] = $this->language->get('text_bonuses_awarded');
		$data['text_bonuses_will_be_awarded'] = $this->language->get('text_bonuses_will_be_awarded');
		$data['text_max_usage_percent'] = sprintf($this->language->get('text_max_usage_percent'), (int)$max_usage_percent);
		$data['text_pay_up_to'] = sprintf($this->language->get('text_pay_up_to'), (int)$max_can_spend);
		$data['button_pay_with_bonuses'] = $this->language->get('button_pay_with_bonuses');
		$data['button_apply'] = $this->language->get('button_apply');
		$data['button_back'] = $this->language->get('button_back');
		$data['warning_no_earn_if_spend'] = sprintf($this->language->get('warning_no_earn_if_spend'), round($total_bonus));

		$data['link_checkout'] = $this->url->link('checkout/checkout');

		return $this->load->view('extension/module/bonus_display_cart', $data);
	}

	/**
	 * Display registration widget for guests
	 *
	 * This method generates an attractive widget that encourages non-logged-in visitors
	 * to register for an account and start earning bonus points. It displays:
	 * - Customizable heading and description
	 * - Dynamic benefits list based on current bonus settings
	 * - Register button that opens Journal3 modal popup
	 * - Login link for existing members
	 *
	 * The widget automatically pulls bonus percentages, expiration days, and other
	 * settings from the bonus manager configuration to show accurate benefits.
	 *
	 * SCOPE & USAGE:
	 * - Called automatically by cart() method when user is NOT logged in
	 * - Should NOT be called directly from templates or other controllers
	 * - Only displays if module_bonus_manager_status is enabled
	 * - Integrates with Journal3 theme's open_register_popup() JavaScript function
	 *
	 * CONFIGURATION:
	 * All widget content is configurable via Admin Panel:
	 * Extensions → Modules → Bonus Manager → Notifications Tab → Registration Widget
	 *
	 * Settings include:
	 * - module_bonus_manager_register_widget_heading (default: language file)
	 * - module_bonus_manager_register_widget_description (default: language file)
	 * - module_bonus_manager_register_widget_button_text (default: "Register Now")
	 * - module_bonus_manager_register_widget_icon (default: "fa-gift")
	 * - module_bonus_manager_register_widget_show_details (default: true)
	 *
	 * TEMPLATE RENDERING:
	 * Renders: catalog/view/theme/journal3/template/extension/module/bonus_display_register.twig
	 *
	 * RETURN VALUE:
	 * @return string Rendered HTML widget ready for display in cart, or empty string on error
	 *
	 * BENEFITS LIST AUTO-GENERATION:
	 * The method automatically builds a benefits array from current bonus settings:
	 * 1. "Earn up to X% bonus points" (from module_bonus_manager_bonus_percent)
	 * 2. "Use up to Y% for payment" (from module_bonus_manager_max_usage_percent)
	 * 3. "Valid for Z days" or "Never expire" (from module_bonus_manager_expiration_days)
	 * 4. "Exclusive offers for members" (static benefit)
	 *
	 * JOURNAL3 MODAL INTEGRATION:
	 * - Register link: javascript:open_register_popup() (defined in common.js)
	 * - Login link: javascript:open_login_popup() (for existing members)
	 * - Both functions are globally available in Journal3 theme
	 *
	 * EXAMPLE USAGE FLOW:
	 * 1. Guest adds items to cart
	 * 2. Views cart page
	 * 3. bonus_display::cart() detects guest status
	 * 4. Calls this registerWidget() method
	 * 5. Widget displays with "Register Now" button
	 * 6. Guest clicks button → Journal3 modal opens
	 * 7. Guest registers → page reloads with user logged in
	 * 8. cart() now shows normal bonus balance widget instead
	 *
	 * @return string HTML output
	 */
	public function registerWidget() {
		// Load language file for translation strings
		$this->load->language('extension/module/bonus_manager');

		$data = array();

		// === WIDGET CONTENT CONFIGURATION ===
		// Priority: Admin settings > Language file defaults
		// Admin can customize all text via: Extensions → Modules → Bonus Manager → Notifications

		// Widget heading (main title)
		// Example: "Станьте участником программы лояльности!" or "Join Our Loyalty Program!"
		$data['heading_title'] = $this->config->get('module_bonus_manager_register_widget_heading')
			?: $this->language->get('text_register_widget_heading');

		// Widget description (subtitle/pitch text)
		// Example: "Регистрируйтесь и получайте бонусные баллы за каждую покупку!"
		$data['description'] = $this->config->get('module_bonus_manager_register_widget_description')
			?: $this->language->get('text_register_widget_description');

		// === BONUS SYSTEM SETTINGS ===
		// Pull current bonus configuration to show accurate benefits to guests
		// These values are used to auto-generate the benefits list

		// Percentage of purchase amount awarded as bonus points (e.g., "10" = 10%)
		$data['bonus_percent'] = $this->config->get('module_bonus_manager_bonus_percent') ?: '10';

		// Maximum percentage of order that can be paid with bonus points (e.g., "30" = 30%)
		$data['max_bonus_usage'] = $this->config->get('module_bonus_manager_max_usage_percent') ?: '30';

		// Number of days before bonus points expire (0 = never expire)
		$data['expiration_days'] = $this->config->get('module_bonus_manager_expiration_days') ?: '365';

		// === DISPLAY OPTIONS ===

		// Toggle to show/hide detailed benefits list
		// If false, widget shows only heading, description, and button (simpler design)
		// Note: Using !== false instead of (bool) because null/empty should default to true
		$data['show_details'] = $this->config->get('module_bonus_manager_register_widget_show_details') !== false;

		// Register button text
		// Example: "Зарегистрироваться" or "Register Now"
		$data['button_text'] = $this->config->get('module_bonus_manager_register_widget_button_text')
			?: $this->language->get('button_register');

		// Font Awesome icon class for visual appeal
		// Admin can choose any FA 4.7 icon: fa-gift, fa-star, fa-trophy, fa-diamond, etc.
		// See: https://fontawesome.com/v4.7.0/icons/
		$data['icon'] = $this->config->get('module_bonus_manager_register_widget_icon') ?: 'fa-gift';

		// === AUTO-GENERATE BENEFITS LIST ===
		// Build an array of benefit strings based on current bonus settings
		// This ensures guests see accurate, up-to-date information

		$benefits = array();

		// Only generate benefits if show_details is enabled
		if ($data['show_details']) {
			// Benefit 1: Earning percentage
			// sprintf() injects the actual bonus_percent value into the translated string
			// Language string contains placeholder: "Получайте до %s%% от суммы покупки"
			$benefits[] = sprintf($this->language->get('text_benefit_earn'), $data['bonus_percent']);

			// Benefit 2: Usage/spending percentage
			// Shows how much of order total can be paid with bonus points
			// Language string: "Используйте до %s%% бонусов для оплаты"
			$benefits[] = sprintf($this->language->get('text_benefit_use'), $data['max_bonus_usage']);

			// Benefit 3: Validity period (conditional on expiration setting)
			if ($data['expiration_days'] > 0) {
				// Points DO expire - show validity period
				// Language string: "Бонусы действительны %s дней"
				$benefits[] = sprintf($this->language->get('text_benefit_validity'), $data['expiration_days']);
			} else {
				// Points NEVER expire - show that as a benefit
				// Language string: "Бонусы не сгорают"
				$benefits[] = $this->language->get('text_benefit_no_expiry');
			}

			// Benefit 4: Static marketing benefit
			// Generic perk to make program more attractive
			// Language string: "Специальные предложения для участников"
			$benefits[] = $this->language->get('text_benefit_special_offers');
		}

		// Store benefits array for template rendering
		$data['benefits'] = $benefits;

		// === JOURNAL3 MODAL INTEGRATION ===
		// Journal3 theme provides global JavaScript functions for auth modals
		// These functions are defined in: catalog/view/theme/journal3/js/common.js

		// Register modal trigger
		// Clicking this link calls: window.open_register_popup()
		// Opens registration form in an iframe modal overlay
		$data['register_link'] = 'javascript:open_register_popup()';

		// === ADDITIONAL UI ELEMENTS ===

		// "Already a member?" text for existing users
		$data['text_already_member'] = $this->language->get('text_already_member');

		// "Login" link text
		$data['text_login'] = $this->language->get('text_login');

		// Login modal trigger (for existing members who haven't logged in yet)
		// Clicking this calls: window.open_login_popup()
		$data['login_link'] = 'javascript:open_login_popup()';

		// === LOYALTY PROGRAM INFORMATION PAGE LINK ===
		// Add link to loyalty program levels information page if configured
		$loyalty_info_id = $this->config->get('module_bonus_manager_loyalty_info_id');
		if ($loyalty_info_id) {
			$data['loyalty_info_link'] = $this->url->link('information/information', 'information_id=' . (int)$loyalty_info_id);
		} else {
			$data['loyalty_info_link'] = '';
		}

		// Current language code for template conditional text
		$data['language_code'] = $this->config->get('config_language');

		// === RENDER TEMPLATE ===
		// Load and render the registration widget template with all data
		// Template path: catalog/view/theme/journal3/template/extension/module/bonus_display_register.twig
		// Returns rendered HTML string ready for output
		return $this->load->view('extension/module/bonus_display_register', $data);
	}

	/**
	 * Returns color name for a loyalty level — mirrors account/reward getLoyaltyLevelColor().
	 * Used to select widget background gradient.
	 */
	private function getLoyaltyLevelColor($level) {
		if (empty($level) || !isset($level['display_name'])) {
			return 'default';
		}
		$name = mb_strtolower($level['display_name'], 'UTF-8');
		$map = array(
			'базов' => 'secondary', 'бронз' => 'warning', 'серебр' => 'silver',
			'золот' => 'gold', 'платин' => 'platinum', 'алмаз' => 'primary',
			'basic' => 'secondary', 'bronze' => 'warning', 'silver' => 'silver',
			'gold' => 'gold', 'platinum' => 'platinum', 'diamond' => 'primary',
		);
		foreach ($map as $keyword => $color) {
			if (strpos($name, $keyword) !== false) {
				return $color;
			}
		}
		return 'primary';
	}

	/**
	 * Check if product is in excluded category
	 */
	private function isProductInExcludedCategory($product_id, $excluded_categories) {
		if (empty($excluded_categories)) {
			return false;
		}

		$query = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category
			WHERE product_id = '" . (int)$product_id . "'");

		foreach ($query->rows as $row) {
			if (in_array($row['category_id'], $excluded_categories)) {
				return true;
			}
		}

		return false;
	}
}
