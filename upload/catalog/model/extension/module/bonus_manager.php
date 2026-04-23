<?php
class ModelExtensionModuleBonusManager extends Model {

	/**
	 * Award bonuses for a completed order
	 *
	 * Called when order status changes to "Complete". This method implements a two-phase
	 * bonus award system:
	 *
	 * Phase 1: Create customer_bonus_items entries with status='pending' for each product
	 * - Stores: product_id, product_quantity, bonus_rate, bonus_points, date_expires
	 * - Product details (name, model, price) can be retrieved from order_product table via order_product_id
	 *
	 * Phase 2: Create customer_reward entry and activate all pending bonus_items
	 * - Updates all pending bonus_items to status='active'
	 * - Sends email notification
	 * - Processes any pending deductions (race condition handling)
	 *
	 * Scope: Called from admin/catalog event handlers when order status changes to Complete
	 *
	 * @param int $order_id Order ID
	 * @return bool True if bonuses were awarded, false otherwise
	 */
	public function awardBonusesForOrder($order_id) {
		// Check if module is enabled
		if (!$this->config->get('module_bonus_manager_status')) {
			return false;
		}

		// Check if bonuses already awarded for this order
		if ($this->isBonusAwarded($order_id)) {
			$this->log->write('BONUS: Bonuses already awarded for order #' . $order_id);
			return false;
		}

		// Load order information
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info || !$order_info['customer_id']) {
			$this->log->write('BONUS: Invalid order or guest order #' . $order_id);
			return false;
		}

		// Get customer's group ID from order table (not customer table)
		// This ensures we use the group they had when placing the order, not their current group
		// (customer group might change due to loyalty level upgrades after order placement)
		$order_query = $this->db->query("SELECT customer_group_id FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$order_id . "'");
		$customer_group_id = $order_query->num_rows ? (int)$order_query->row['customer_group_id'] : (int)$this->config->get('config_customer_group_id');

		// Check if customer used bonuses for this order - if yes, don't award new bonuses
		if ($this->wasRewardUsedInOrder($order_id)) {
			$this->log->write('BONUS: Customer used bonuses for order #' . $order_id . '. No new bonuses awarded.');
			return false;
		}

		// Calculate order-wide discount percentage 
		// This will stop adding bonuses for heavily discounted orders or coupons and vouchers
		// to prevent abuse of the bonus system
		$order_discount_percent = $this->calculateOrderDiscountPercent($order_id);
		$discount_threshold = (float)$this->config->get('module_bonus_manager_discount_threshold');

		if ($discount_threshold <= 0) {
			$discount_threshold = 15.0; // Default threshold
		}

		// If order has >15% discount, no bonuses
		if ($order_discount_percent > $discount_threshold) {
			$this->log->write('BONUS: Order #' . $order_id . ' has ' . round($order_discount_percent, 2) . '% discount (threshold: ' . $discount_threshold . '%). No bonuses awarded.');
			return false;
		}

		// Calculate expiration date (needed for both bonus_items and customer_reward)
		$expiration_days = (int)$this->config->get('module_bonus_manager_expiration_days');
		if ($expiration_days <= 0) {
			$expiration_days = 365; // Default 1 year
		}
		$expiration_date_sql = "DATE_ADD(NOW(), INTERVAL " . (int)$expiration_days . " DAY)";

		// Calculate bonuses for each product and store product-level tracking
		$total_bonus = 0;
		$products = $this->model_checkout_order->getOrderProducts($order_id);

		foreach ($products as $product) {
			// Calculate bonus using order's base_price when available
			// If base_price is 0 or not set, use the order price as base (no discount)
			// This prevents comparing old order prices with current catalog prices
			$base_price_from_order = isset($product['base_price']) ? (float)$product['base_price'] : 0;
			if ($base_price_from_order <= 0) {
				// No base_price stored, treat order price as base price (no discount)
				$base_price_from_order = (float)$product['price'];
			}

			$bonus_per_unit = $this->getProductBonusFromOrder($product['product_id'], $customer_group_id, $product['price'], $base_price_from_order);
			$bonus_rate = number_format((float)$this->getBonusPercent($customer_group_id, $product['product_id']),2,'.','');
			if ($bonus_per_unit <= 0) {
				continue;
			}

			// Calculate bonus for this product line (bonus per unit × quantity)
			$product_bonus = $bonus_per_unit * (int)$product['quantity'];
			$product_bonus_rounded = round($product_bonus);

			// Store product-level bonus tracking for return handling with pending status
			if ($product_bonus_rounded > 0) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "customer_bonus_items
					SET order_id = '" . (int)$order_id . "',
					product_id = '" . (int)$product['product_id'] . "',
					product_quantity = '" . (int)$product['quantity'] . "',
					order_product_id = '" . (int)$product['order_product_id'] . "',
					bonus_rate = '" . $bonus_rate . "',
					bonus_points = '" . (int)$product_bonus_rounded . "',
					status = 'pending',
					date_added = NOW(),
					date_expires = " . $expiration_date_sql);
			}

			$total_bonus += $product_bonus_rounded;
		}

		// Award bonuses if any
		if ($total_bonus > 0) {
			// Get bonus percentage for metadata
			$bonus_percent = $this->getBonusPercent($customer_group_id, $products[0]['product_id']);

			// Award bonuses with expiration date, bonus_type and remaining field
			// For AWARD entries: remaining = points (tracks how much is still available to spend)
			$this->db->query("INSERT INTO " . DB_PREFIX . "customer_reward
				SET customer_id = '" . (int)$order_info['customer_id'] . "',
				order_id = '" . (int)$order_id . "',
				description = '" . $this->db->escape(sprintf('Бонусы за заказ #%s', $order_id)) . "',
				points = '" . (int)$total_bonus . "',
				remaining = '" . (int)$total_bonus . "',
				bonus_type = 'order_complete',
				reward_kind = 'award',
				bonus_metadata = '" . $this->db->escape(json_encode(array(
					'order_id' => (int)$order_id,
					'bonus_pct' => (float)$bonus_percent
				))) . "',
				date_added = NOW(),
				date_expires = " . $expiration_date_sql);

			// Update all pending bonus_items for this order to active status
			$this->db->query("UPDATE " . DB_PREFIX . "customer_bonus_items
				SET status = 'active'
				WHERE order_id = '" . (int)$order_id . "'
				AND status = 'pending'");

			$this->log->write('BONUS: Awarded ' . $total_bonus . ' bonuses for order #' . $order_id . ' (expires in ' . $expiration_days . ' days)');

			// Send email notification
			$this->load->controller('mail/bonus/awarded', array($order_info, $total_bonus));

			// Check and upgrade customer loyalty level after order completion
			$this->checkAndUpgradeCustomer($order_info['customer_id']);

			// Process any pending deductions for this order (race condition handling)
			$this->processPendingDeductions($order_id);

			return true;
		}

		$this->log->write('BONUS: No bonuses calculated for order #' . $order_id);
		return false;
	}

	/**
	 * Check if bonus already awarded for this order
	 */
	private function isBonusAwarded($order_id) {
		$query = $this->db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "customer_reward
			WHERE order_id = '" . (int)$order_id . "' AND points > 0");

		return $query->row['total'] > 0;
	}

	/**
	 * Process pending deductions for an order
	 *
	 * Called after bonuses are awarded to handle race condition where returns
	 * were approved before order completion. This method implements the negative
	 * bonus_items tracking system for returns.
	 *
	 * For each pending deduction:
	 * 1. Retrieves the original active bonus_items entry (with all product data)
	 * 2. Creates a negative customer_reward entry (deduction)
	 * 3. Creates a NEW negative bonus_items entry (with negative bonus_points)
	 *    - Mirrors all data from original: product_id, product_quantity, bonus_rate
	 *    - Sets bonus_points to negative value
	 *    - Sets status='active' and return_id
	 *    - Uses same date_expires as original
	 * 4. Marks the original bonus_items entry as status='deducted'
	 * 5. Deletes the pending_deduction marker entry
	 *
	 * This approach maintains a complete audit trail showing both the original
	 * bonus award (+points) and the return deduction (-points) as separate entries.
	 *
	 * Scope: Called immediately after bonuses are awarded in awardBonusesForOrder()
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	private function processPendingDeductions($order_id) {
		// Find all bonus items for this order marked as pending_deduction
		$query = $this->db->query("
			SELECT bi.*, op.quantity
			FROM " . DB_PREFIX . "customer_bonus_items bi
			INNER JOIN " . DB_PREFIX . "order_product op ON op.order_product_id = bi.order_product_id
			WHERE bi.order_id = '" . (int)$order_id . "'
			AND bi.status = 'pending_deduction'
		");

		if (!$query->num_rows) {
			return; // No pending deductions
		}

		$this->log->write('BONUS: Found ' . $query->num_rows . ' pending deductions for order #' . $order_id);

		foreach ($query->rows as $pending) {
			$order_product_id = (int)$pending['order_product_id'];
			$product_id = (int)$pending['product_id'];
			$return_id = (int)$pending['return_id'];
			$pending_return_qty = isset($pending['return_quantity']) ? (int)$pending['return_quantity'] : 0;
			$original_quantity = isset($pending['quantity']) ? (int)$pending['quantity'] : 0;

			// Get the original active bonus_items entry with all product data
			$awarded_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_bonus_items
				WHERE order_product_id = '" . (int)$order_product_id . "'
				AND status = 'active'");

			if (!$awarded_query->num_rows) {
				$this->log->write('BONUS: No active bonus found for pending deduction order_product_id #' . $order_product_id);
				continue;
			}

			$awarded_item = $awarded_query->row;
			$awarded_bonus_points = (int)$awarded_item['bonus_points'];

			if ($awarded_bonus_points <= 0) {
				$this->log->write('BONUS: No bonus points to deduct for order_product_id #' . $order_product_id);
				continue;
			}

			// Calculate deduction: if pending marked a return_quantity, calculate proportional deduction,
			// otherwise treat as full (award) deduction.
			if ($pending_return_qty > 0 && $original_quantity > 0) {
				$deduction = round(($pending_return_qty / $original_quantity) * $awarded_bonus_points);
			} else {
				$deduction = $awarded_bonus_points;
			}

			// Get customer and order info for email
			$return_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "return WHERE return_id = '" . (int)$return_id . "'");
			if (!$return_query->num_rows) {
				continue;
			}
			$return_info = $return_query->row;
			$customer_id = (int)$return_info['customer_id'];

			// Deduct from remaining balance using cascade logic (same as admin returnProductBonuses):
			// 1. Try to deduct from the original order's award first
			// 2. If not enough, set to 0 and deduct remainder from most recent awards with remaining > 0

			$points_to_deduct = $deduction;

			// Step 1: Find and deduct from the original order's award (the one just created)
			$award_query = $this->db->query("
				SELECT customer_reward_id, remaining
				FROM " . DB_PREFIX . "customer_reward
				WHERE customer_id = '" . (int)$customer_id . "'
				AND order_id = '" . (int)$order_id . "'
				AND reward_kind = 'award'
				AND bonus_type = 'order_complete'
				AND remaining > 0
				ORDER BY customer_reward_id DESC
				LIMIT 1
			");

			if ($award_query->num_rows) {
				$award_id = (int)$award_query->row['customer_reward_id'];
				$current_remaining = (int)$award_query->row['remaining'];

				$deduct_from_this = min($points_to_deduct, $current_remaining);
				$new_remaining = $current_remaining - $deduct_from_this;

				$this->db->query("
					UPDATE " . DB_PREFIX . "customer_reward
					SET remaining = '" . (int)$new_remaining . "'
					WHERE customer_reward_id = '" . (int)$award_id . "'
				");

				$this->log->write('BONUS PENDING: Deducted ' . $deduct_from_this . ' from original order award #' . $award_id . ' (remaining: ' . $current_remaining . ' -> ' . $new_remaining . ')');

				$points_to_deduct -= $deduct_from_this;
			}

			// Step 2: If still have points to deduct, deduct from most recent awards with remaining > 0
			if ($points_to_deduct > 0) {
				$this->log->write('BONUS PENDING: Still need to deduct ' . $points_to_deduct . ' points. Deducting from most recent awards...');

				$remaining_awards_query = $this->db->query("
					SELECT customer_reward_id, remaining
					FROM " . DB_PREFIX . "customer_reward
					WHERE customer_id = '" . (int)$customer_id . "'
					AND reward_kind = 'award'
					AND remaining > 0
					AND (date_expires IS NULL OR date_expires > NOW())
					ORDER BY customer_reward_id DESC
				");

				foreach ($remaining_awards_query->rows as $award) {
					if ($points_to_deduct <= 0) {
						break;
					}

					$award_id = (int)$award['customer_reward_id'];

			// Insert negative customer_reward record to record the deduction for this pending return
			$this->load->language('extension/module/bonus_manager');
			$description = sprintf($this->language->get('text_return_deduction'), $return_id);

			$this->db->query("INSERT INTO " . DB_PREFIX . "customer_reward
				SET customer_id = '" . (int)$customer_id . "',
				order_id = '" . (int)$order_id . "',
				description = '" . $this->db->escape($description) . "',
				points = '" . (int)(-$deduction) . "',
				remaining = 0,
				bonus_type = 'return_deduction',
				reward_kind = 'deduction',
				bonus_metadata = '" . $this->db->escape(json_encode(array('return_id' => (int)$return_id, 'order_product_id' => (int)$order_product_id, 'product_id' => (int)$product_id))) . "',
				date_added = NOW(),
				date_expires = NULL");

			// Update the original awarded item's bonus_points to reflect remaining per-item points
					$deduct_from_this = min($points_to_deduct, $current_remaining);
					$new_remaining = $current_remaining - $deduct_from_this;

					$this->db->query("
						UPDATE " . DB_PREFIX . "customer_reward
						SET remaining = '" . (int)$new_remaining . "'
						WHERE customer_reward_id = '" . (int)$award_id . "'
					");

					$this->log->write('BONUS PENDING: Deducted ' . $deduct_from_this . ' from award #' . $award_id . ' (remaining: ' . $current_remaining . ' -> ' . $new_remaining . ')');

					$points_to_deduct -= $deduct_from_this;
				}

				if ($points_to_deduct > 0) {
					$this->log->write('BONUS PENDING: WARNING - Could not deduct all points. ' . $points_to_deduct . ' points remain undeducted.');
				}
			}

			// Update the original awarded item's bonus_points to reflect remaining per-item points
			$awarded_id = (int)$awarded_item['bonus_item_id'];
			$new_awarded_points = max(0, (int)$awarded_bonus_points - (int)$deduction);

			if ($new_awarded_points > 0) {
				$this->db->query("UPDATE " . DB_PREFIX . "customer_bonus_items
					SET bonus_points = '" . (int)$new_awarded_points . "'
					WHERE bonus_item_id = '" . (int)$awarded_id . "'");
			} else {
				$this->db->query("UPDATE " . DB_PREFIX . "customer_bonus_items
					SET bonus_points = 0,
					status = 'deducted',
					return_id = '" . (int)$return_id . "'
					WHERE bonus_item_id = '" . (int)$awarded_id . "'");
			}

			// Update awarded item to accumulate returned quantity (store return info on same record)
			if ($pending_return_qty > 0) {
				$this->db->query("UPDATE " . DB_PREFIX . "customer_bonus_items
					SET return_quantity = COALESCE(return_quantity,0) + '" . (int)$pending_return_qty . "',
					return_id = '" . (int)$return_id . "'
					WHERE bonus_item_id = '" . (int)$awarded_id . "'");
			}

			// Delete the pending deduction marker
			// $this->db->query("DELETE FROM " . DB_PREFIX . "customer_bonus_items
			// 	WHERE order_product_id = '" . (int)$order_product_id . "'
			// 	AND status = 'pending_deduction'");

			$this->log->write('BONUS: Processed pending deduction for return #' . $return_id . ', deducted ' . $deduction . ' points');

			// Note: Email notification is not sent here because it was already sent when the return was processed
			// or can be sent from admin context if needed
		}
	}

	/**
	 * Check if customer used reward points (bonuses) for this order
	 * If bonuses were spent, we don't award new bonuses
	 */
	private function wasRewardUsedInOrder($order_id) {
		$query = $this->db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "order_total
			WHERE order_id = '" . (int)$order_id . "'
			AND code = 'reward'
			AND value < 0");

		return $query->row['total'] > 0;
	}

	/**
	 * Calculate order-wide discount percentage
	 */
	private function calculateOrderDiscountPercent($order_id) {
		$query = $this->db->query("SELECT code, value FROM " . DB_PREFIX . "order_total
			WHERE order_id = '" . (int)$order_id . "'
			ORDER BY sort_order ASC");

		$subtotal = 0;
		$total_discounts = 0;

		foreach ($query->rows as $row) {
			if ($row['code'] == 'sub_total') {
				$subtotal = (float)$row['value'];
			} elseif (in_array($row['code'], array('coupon', 'voucher', 'reward'))) {
				// These are negative values in DB
				$total_discounts += abs((float)$row['value']);
			}
		}

		if ($subtotal <= 0) {
			return 0;
		}

		return ($total_discounts / $subtotal) * 100;
	}

	/**
	 * Get bonus percentage for a product based on customer group and category
	 */
	public function getBonusPercent($customer_group_id, $product_id) {
		// Get product's categories
		$product_categories = $this->getProductCategories($product_id);

		// Try to find specific category bonus settings
		foreach ($product_categories as $category_id) {
			$query = $this->db->query("SELECT bonus_percent FROM " . DB_PREFIX . "bonus_settings
				WHERE customer_group_id = '" . (int)$customer_group_id . "'
				AND category_id = '" . (int)$category_id . "'");

			if ($query->num_rows) {
				return (float)$query->row['bonus_percent'];
			}
		}

		// Fall back to default for customer group (category_id = 0)
		$query = $this->db->query("SELECT bonus_percent FROM " . DB_PREFIX . "bonus_settings
			WHERE customer_group_id = '" . (int)$customer_group_id . "'
			AND category_id = 0");

		if ($query->num_rows) {
			return (float)$query->row['bonus_percent'];
		}

		// If customer group is not configured in bonus_settings at all, return 0 (no bonuses)
		// This ensures only explicitly configured customer groups receive bonuses
		return 0;
	}

	/**
	 * Get product categories
	 */
	private function getProductCategories($product_id) {
		$query = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "product_to_category
			WHERE product_id = '" . (int)$product_id . "'");

		$categories = array();
		foreach ($query->rows as $row) {
			$categories[] = $row['category_id'];
		}

		return $categories;
	}

	/**
	 * Check if product is in excluded category
	 */
	private function isProductInExcludedCategory($product_id, $excluded_categories) {
		if (empty($excluded_categories)) {
			return false;
		}

		$product_categories = $this->getProductCategories($product_id);

		foreach ($product_categories as $category_id) {
			if (in_array($category_id, $excluded_categories)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get excluded categories from settings
	 */
	private function getExcludedCategories() {
		$excluded = $this->config->get('module_bonus_manager_excluded_categories');

		if (is_array($excluded)) {
			return $excluded;
		}

		return array();
	}

	/**
	 * Calculate expected bonus for a product (for frontend display)
	 */
	public function getProductBonus($product_id, $customer_group_id, $price) {
		// Check if module is enabled
		if (!$this->config->get('module_bonus_manager_status')) {
			return 0;
		}

		// Check if product is in excluded category
		$excluded_categories = $this->getExcludedCategories();
		if ($this->isProductInExcludedCategory($product_id, $excluded_categories)) {
			return 0;
		}

		// Get product base price
		$this->load->model('catalog/product');
		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) {
			return 0;
		}

		$base_price = (float)$product_info['price'];
		$final_price = (float)$price;

		// Check discount threshold
		$discount_threshold = (float)$this->config->get('module_bonus_manager_discount_threshold');
		if ($discount_threshold <= 0) {
			$discount_threshold = 15.0;
		}

		$product_discount_percent = 0;
		if ($base_price > 0) {
			$product_discount_percent = (($base_price - $final_price) / $base_price) * 100;
		}

		// If product has >15% discount, no bonus
		if ($product_discount_percent > $discount_threshold) {
			return 0;
		}

		// Get bonus percentage
		$bonus_percent = $this->getBonusPercent($customer_group_id, $product_id);

		if ($bonus_percent <= 0) {
			return 0;
		}

		return round($final_price * $bonus_percent / 100, 2);
	}

	/**
	 * Calculate expected bonus for a product using order's base price
	 *
	 * This method is used when processing completed orders. Unlike getProductBonus() which
	 * fetches the current catalog price, this method uses the base_price stored in the order
	 * to calculate discount percentage. This ensures accurate bonus calculation even if
	 * catalog prices change after the order was placed.
	 *
	 * Scope: Called from awardBonusesForOrder() when order status changes to Complete
	 *
	 * @param int $product_id Product ID
	 * @param int $customer_group_id Customer group ID
	 * @param float $price Final price paid (from order_product.price)
	 * @param float $base_price Original price before discount (from order_product.base_price)
	 * @return float Bonus amount per unit
	 */
	public function getProductBonusFromOrder($product_id, $customer_group_id, $price, $base_price) {
		// Check if module is enabled
		if (!$this->config->get('module_bonus_manager_status')) {
			return 0;
		}

		// Check if product is in excluded category
		$excluded_categories = $this->getExcludedCategories();
		if ($this->isProductInExcludedCategory($product_id, $excluded_categories)) {
			return 0;
		}

		$base_price = (float)$base_price;
		$final_price = (float)$price;

		// Check discount threshold using order's base_price
		$discount_threshold = (float)$this->config->get('module_bonus_manager_discount_threshold');
		if ($discount_threshold <= 0) {
			$discount_threshold = 15.0;
		}

		$product_discount_percent = 0;
		if ($base_price > 0) {
			$product_discount_percent = (($base_price - $final_price) / $base_price) * 100;
		}

		// If product has >threshold% discount, no bonus
		if ($product_discount_percent > $discount_threshold) {
			return 0;
		}

		// Get bonus percentage for this customer group and product
		$bonus_percent = $this->getBonusPercent($customer_group_id, $product_id);

		if ($bonus_percent <= 0) {
			return 0;
		}

		return round($final_price * $bonus_percent / 100, 2);
	}

	/**
	 * Get bonus settings for a customer group
	 */
	public function getBonusSettings($customer_group_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bonus_settings
			WHERE customer_group_id = '" . (int)$customer_group_id . "'
			ORDER BY category_id ASC");

		return $query->rows;
	}

	/**
	 * Check if product has heavy discount (>threshold)
	 */
	public function hasHeavyDiscount($product_id, $price) {
		$this->load->model('catalog/product');
		$product_info = $this->model_catalog_product->getProduct($product_id);

		if (!$product_info) {
			return false;
		}

		$base_price = (float)$product_info['price'];
		$final_price = (float)$price;

		$discount_threshold = (float)$this->config->get('module_bonus_manager_discount_threshold');
		if ($discount_threshold <= 0) {
			$discount_threshold = 15.0;
		}

		$product_discount_percent = 0;
		if ($base_price > 0) {
			$product_discount_percent = (($base_price - $final_price) / $base_price) * 100;
		}

		return $product_discount_percent > $discount_threshold;
	}

	/**
	 * Calculate maximum allowed reward points for current cart
	 * Centralized method to avoid duplicate logic
	 *
	 * @return array ['points' => customer points, 'points_total' => max allowed, 'subtotal' => cart subtotal]
	 */
	public function getMaxAllowedReward() {
		$this->load->model('account/customer');

		$points = $this->customer->getRewardPoints();
		$points_total = 0;
		$subtotal = 0;

		// Check if any products have point prices
		foreach ($this->cart->getProducts() as $product) {
			$subtotal += $product['total'];
			if (isset($product['points']) && $product['points']) {
				$points_total += $product['points'];
			}
		}

		// If no products have points price, calculate max allowed based on cart subtotal (bonus system)
		if (!$points_total && $subtotal > 0) {
			// Get maximum usage percentage from configuration (default 30%)
			$max_usage_percent = (float)$this->config->get('module_bonus_manager_max_usage_percent') ?: 30;

			// Calculate maximum allowed bonus usage based on percentage
			$max_allowed_bonus = ($subtotal * $max_usage_percent) / 100;

			// Set points_total to max allowed
			$points_total = min($points, $max_allowed_bonus, $subtotal);
		}

		return array(
			'points' => $points,
			'points_total' => $points_total,
			'subtotal' => $subtotal
		);
	}

	/**
	 * Validate reward amount
	 * Centralized validation to avoid duplicate logic
	 *
	 * @param mixed $reward_amount The reward amount to validate
	 * @return array ['valid' => bool, 'error' => string|null, 'amount' => int]
	 */
	public function validateReward($reward_amount) {
		$this->load->language('extension/total/reward');

		$limits = $this->getMaxAllowedReward();
		$points = $limits['points'];
		$points_total = $limits['points_total'];

		// Empty or whitespace-only value - valid (customer chooses not to use bonuses)
		if (!isset($reward_amount) || trim($reward_amount) === '') {
			return array(
				'valid' => true,
				'error' => null,
				'amount' => 0
			);
		}

		// Convert to integer
		$amount = (int)$reward_amount;

		// Zero amount is valid (clearing rewards)
		if ($amount === 0) {
			return array(
				'valid' => true,
				'error' => null,
				'amount' => 0
			);
		}

		// Check if exceeds customer's available points
		if ($amount > $points) {
			return array(
				'valid' => false,
				'error' => sprintf($this->language->get('error_points'), $amount),
				'amount' => $amount
			);
		}

		// Check if exceeds maximum allowed for this order
		if ($amount > $points_total) {
			return array(
				'valid' => false,
				'error' => sprintf($this->language->get('error_maximum'), $points_total),
				'amount' => $amount
			);
		}

		// Valid amount
		return array(
			'valid' => true,
			'error' => null,
			'amount' => abs($amount)
		);
	}

	// =============================================================================
	// LOYALTY LEVEL MANAGEMENT
	// =============================================================================

	/**
	 * Check customer loyalty level after order completion.
	 *
	 * Upgrades remain automatic. Downgrades are converted into admin review items
	 * so the customer keeps the current level until a manager makes a decision.
	 *
	 * @param int $customer_id Customer ID to check
	 * @return bool True if an automatic upgrade happened or a downgrade review was queued
	 */
	public function checkAndUpgradeCustomer($customer_id) {
		// Check if loyalty auto-upgrade is enabled
		if (!$this->config->get('module_bonus_manager_loyalty_status')) {
			return false;
		}

		// Get loyalty levels
		$levels = $this->getLoyaltyLevels();
		if (empty($levels)) {
			return false;
		}

		// Sort levels by min_total_spent descending to find highest qualified level
		usort($levels, function($a, $b) {
			return $b['min_total_spent'] - $a['min_total_spent'];
		});

		$period = $this->getCurrentLoyaltyPeriod();

		// Get customer's total spent in current program period
		$total_spent = $this->getCustomerTotalSpent($customer_id);

		// Find highest level customer qualifies for
		$target_level = null;
		foreach ($levels as $level) {
			if ($total_spent >= $level['min_total_spent']) {
				$target_level = $level;
				break;
			}
		}

		if ($target_level === null) {
			return false;
		}

		$target_group_id = (int)$target_level['customer_group_id'];

		// Get customer's current group and info
		$query = $this->db->query("SELECT customer_group_id, firstname, lastname, email, language_id FROM " . DB_PREFIX . "customer
			WHERE customer_id = '" . (int)$customer_id . "'");

		if (!$query->num_rows) {
			$this->log->write('LOYALTY UPGRADE: Customer #' . $customer_id . ' not found');
			return false;
		}

		$customer_info = $query->row;
		$current_group_id = (int)$customer_info['customer_group_id'];
		$current_level = $this->getLoyaltyLevel($current_group_id);
		$current_min_total_spent = $current_level ? (float)$current_level['min_total_spent'] : null;
		$target_min_total_spent = (float)$target_level['min_total_spent'];

		// Get language code from language_id for email
		$lang_query = $this->db->query("SELECT code FROM " . DB_PREFIX . "language WHERE language_id = '" . (int)$customer_info['language_id'] . "'");
		$customer_info['language_code'] = $lang_query->num_rows ? $lang_query->row['code'] : 'en-gb';

		$should_upgrade = false;
		$is_downgrade_candidate = false;

		if ($target_group_id !== $current_group_id) {
			if ($current_level) {
				$should_upgrade = $target_min_total_spent > $current_min_total_spent;
				$is_downgrade_candidate = $target_min_total_spent < $current_min_total_spent;
			} else {
				$should_upgrade = true;
			}
		}

		if ($should_upgrade) {
			$this->clearPendingLoyaltyReview($customer_id, $period['start_date']);

			// Get customer group names for email notification
			$old_group_name = $this->getCustomerGroupName($current_group_id);
			$new_group_name = $this->getCustomerGroupName($target_group_id);

			// Get new bonus percentage for the target level
			$new_bonus_percent = $this->getBonusPercent($target_group_id, 0); // Pass 0 for product_id to get default

			// Update customer group
			$this->db->query("UPDATE " . DB_PREFIX . "customer
				SET customer_group_id = '" . (int)$target_group_id . "'
				WHERE customer_id = '" . (int)$customer_id . "'");

			// Log the upgrade
			$this->log->write('LOYALTY UPGRADE: Customer #' . $customer_id . ' upgraded from group #' . $current_group_id . ' to group #' . $target_group_id . ' (spent: ' . $total_spent . ')');

			// Send email notification
			$customer_data = array(
				'customer_id' => (int)$customer_id,
				'firstname' => $customer_info['firstname'],
				'lastname' => $customer_info['lastname'],
				'email' => $customer_info['email'],
				'language_code' => $customer_info['language_code']
			);

			$this->load->controller('mail/bonus/loyaltyUpgrade', array(
				$customer_data,
				$old_group_name,
				$new_group_name,
				$new_bonus_percent,
				$total_spent
			));

			return true;
		}

		// Downgrades are reviewed by admin. Keep the current group and queue a
		// pending record once per loyalty period.
		if ($is_downgrade_candidate) {
			$review_status = $this->queueLoyaltyDowngradeReview(
				$customer_id,
				$current_group_id,
				$target_group_id,
				$total_spent,
				$current_min_total_spent,
				$period
			);

			if ($review_status === 'created') {
				$this->log->write(
					'LOYALTY REVIEW: Customer #' . $customer_id .
					' flagged for manual downgrade from group #' . $current_group_id .
					' to group #' . $target_group_id .
					' (spent: ' . $total_spent .
					', required: ' . $current_min_total_spent .
					', period: ' . $period['start_date'] . ' - ' . $period['end_date'] . ')'
				);
			} elseif ($review_status === 'updated') {
				$this->log->write(
					'LOYALTY REVIEW: Updated pending downgrade for customer #' . $customer_id .
					' (current group #' . $current_group_id .
					', recommended group #' . $target_group_id .
					', spent: ' . $total_spent .
					', required: ' . $current_min_total_spent . ')'
				);
			}

			return in_array($review_status, array('created', 'updated'), true);
		}

		// Customer still qualifies for the same level, or there is no downgrade path
		// to review. Remove any unresolved record for the current period.
		$this->clearPendingLoyaltyReview($customer_id, $period['start_date']);

		return false;
	}

	/**
	 * Ensure the admin review table exists for loyalty downgrade decisions.
	 *
	 * This keeps existing installations working without requiring a reinstall.
	 *
	 * @return void
	 */
	private function ensureLoyaltyReviewTable() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "bonus_loyalty_review` (
			`loyalty_review_id` int(11) NOT NULL AUTO_INCREMENT,
			`customer_id` int(11) NOT NULL,
			`period_start` date NOT NULL,
			`period_end` date NOT NULL,
			`current_group_id` int(11) NOT NULL,
			`recommended_group_id` int(11) NOT NULL,
			`total_spent` decimal(15,4) NOT NULL DEFAULT 0.0000,
			`required_total_spent` decimal(15,4) NOT NULL DEFAULT 0.0000,
			`status` enum('pending','applied','dismissed') NOT NULL DEFAULT 'pending',
			`date_created` datetime NOT NULL,
			`date_modified` datetime NOT NULL,
			`date_resolved` datetime DEFAULT NULL,
			`resolved_by_user_id` int(11) DEFAULT NULL,
			PRIMARY KEY (`loyalty_review_id`),
			UNIQUE KEY `customer_period` (`customer_id`, `period_start`),
			KEY `status` (`status`),
			KEY `recommended_group_id` (`recommended_group_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
	}

	/**
	 * Get the current loyalty period boundaries.
	 *
	 * @return array
	 */
	private function getCurrentLoyaltyPeriod() {
		$period_start_date = $this->config->get('module_bonus_manager_loyalty_period_start');
		if (!$period_start_date) {
			$period_start_date = '01-01';
		}

		$current_year = date('Y');
		$period_start_full = $current_year . '-' . $period_start_date . ' 00:00:00';

		if (strtotime($period_start_full) > time()) {
			$current_year--;
			$period_start_full = $current_year . '-' . $period_start_date . ' 00:00:00';
		}

		$period_end_full = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($period_start_full)));

		return array(
			'start_at' => $period_start_full,
			'end_at' => $period_end_full,
			'start_date' => date('Y-m-d', strtotime($period_start_full)),
			'end_date' => date('Y-m-d', strtotime('-1 day', strtotime($period_end_full)))
		);
	}

	/**
	 * Create or update a pending downgrade review for the current loyalty period.
	 *
	 * Dismissed or already applied reviews stay resolved for the same period.
	 *
	 * @param int   $customer_id
	 * @param int   $current_group_id
	 * @param int   $target_group_id
	 * @param float $total_spent
	 * @param float $required_total_spent
	 * @param array $period
	 *
	 * @return string created|updated|ignored
	 */
	private function queueLoyaltyDowngradeReview($customer_id, $current_group_id, $target_group_id, $total_spent, $required_total_spent, array $period) {
		$this->ensureLoyaltyReviewTable();

		$query = $this->db->query("SELECT loyalty_review_id, status
			FROM `" . DB_PREFIX . "bonus_loyalty_review`
			WHERE customer_id = '" . (int)$customer_id . "'
			AND period_start = '" . $this->db->escape($period['start_date']) . "'
			LIMIT 1");

		if ($query->num_rows) {
			if ($query->row['status'] !== 'pending') {
				return 'ignored';
			}

			$this->db->query("UPDATE `" . DB_PREFIX . "bonus_loyalty_review`
				SET current_group_id = '" . (int)$current_group_id . "',
					recommended_group_id = '" . (int)$target_group_id . "',
					total_spent = '" . (float)$total_spent . "',
					required_total_spent = '" . (float)$required_total_spent . "',
					period_end = '" . $this->db->escape($period['end_date']) . "',
					date_modified = NOW()
				WHERE loyalty_review_id = '" . (int)$query->row['loyalty_review_id'] . "'");

			return 'updated';
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "bonus_loyalty_review`
			SET customer_id = '" . (int)$customer_id . "',
				period_start = '" . $this->db->escape($period['start_date']) . "',
				period_end = '" . $this->db->escape($period['end_date']) . "',
				current_group_id = '" . (int)$current_group_id . "',
				recommended_group_id = '" . (int)$target_group_id . "',
				total_spent = '" . (float)$total_spent . "',
				required_total_spent = '" . (float)$required_total_spent . "',
				status = 'pending',
				date_created = NOW(),
				date_modified = NOW()");

		return 'created';
	}

	/**
	 * Remove any unresolved downgrade review for the active loyalty period.
	 *
	 * @param int    $customer_id
	 * @param string $period_start
	 *
	 * @return void
	 */
	private function clearPendingLoyaltyReview($customer_id, $period_start) {
		$this->ensureLoyaltyReviewTable();

		$this->db->query("DELETE FROM `" . DB_PREFIX . "bonus_loyalty_review`
			WHERE customer_id = '" . (int)$customer_id . "'
			AND period_start = '" . $this->db->escape($period_start) . "'
			AND status = 'pending'");
	}

	/**
	 * Get customer group name by ID
	 * Checks for custom display name in loyalty levels first, then falls back to default group name
	 *
	 * @param int $customer_group_id Customer group ID
	 * @return string Customer group name or custom display name
	 */
	private function getCustomerGroupName($customer_group_id) {
		// First, check if this customer group has a custom display name in loyalty levels
		$levels = $this->getLoyaltyLevels();
		foreach ($levels as $level) {
			if ((int)$level['customer_group_id'] === (int)$customer_group_id) {
				// If display_name is set and not empty, use it
				if (isset($level['display_name']) && !empty(trim($level['display_name']))) {
					return trim($level['display_name']);
				}
				break;
			}
		}

		// Fall back to default customer group name from database
		$query = $this->db->query("SELECT name FROM " . DB_PREFIX . "customer_group_description
			WHERE customer_group_id = '" . (int)$customer_group_id . "'
			AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

		if ($query->num_rows) {
			return $query->row['name'];
		}

		return 'Группа #' . $customer_group_id;
	}

	/**
	 * Get customer's total spent within current loyalty program period
	 * Program period is 1 year starting from configured start date (default Jan 1)
	 *
	 * @param int $customer_id Customer ID
	 * @return float Total amount spent in current program period
	 */
	public function getCustomerTotalSpent($customer_id) {
		// Get accrual status (which order status triggers bonus awards)
		$accrual_status_id = $this->config->get('module_bonus_manager_accrual_status_id');
		if (!$accrual_status_id) {
			$accrual_status_id = 5; // Default: Complete status
		}

		$period = $this->getCurrentLoyaltyPeriod();

		// Sum order totals for this customer in current program period
		$query = $this->db->query("
			SELECT SUM(total) as total_spent
			FROM " . DB_PREFIX . "order
			WHERE customer_id = '" . (int)$customer_id . "'
			AND order_status_id = '" . (int)$accrual_status_id . "'
			AND date_added >= '" . $this->db->escape($period['start_at']) . "'
			AND date_added < '" . $this->db->escape($period['end_at']) . "'
		");

		return $query->row && $query->row['total_spent'] ? (float)$query->row['total_spent'] : 0.00;
	}

	/**
	 * Get product-level bonus breakdown for an order
	 * Used for user account display to show which products earned which bonuses
	 *
	 * @param int $order_id Order ID
	 * @return array Array of products with bonus information
	 */
	public function getOrderBonusItems($order_id) {
		$query = $this->db->query("
			SELECT
				bi.bonus_item_id,
				bi.order_product_id,
				bi.product_id,
				bi.bonus_points,
				bi.status,
				bi.date_added,
				op.name,
				op.model,
				op.quantity,
				op.price,
				op.total
			FROM " . DB_PREFIX . "customer_bonus_items bi
			INNER JOIN " . DB_PREFIX . "order_product op ON op.order_product_id = bi.order_product_id
			WHERE bi.order_id = '" . (int)$order_id . "'
			ORDER BY bi.bonus_item_id ASC
		");

		return $query->rows;
	}

	/**
	 * Get loyalty levels configuration
	 *
	 * @return array Array of loyalty levels with customer_group_id and min_total_spent
	 */
	public function getLoyaltyLevels() {
		$levels = $this->config->get('module_bonus_manager_loyalty_levels');
		if (!$levels) {
			return [];
		}

		// If it's already an array (serialized=1), use it directly
		// If it's a JSON string (serialized=0), decode it
		if (!is_array($levels)) {
			// Decode HTML entities first (in case it was saved with serialized=0)
			$levels_json = html_entity_decode($levels, ENT_QUOTES, 'UTF-8');
			$levels = json_decode($levels_json, true);

			if (!is_array($levels)) {
				return [];
			}
		}

		// Sort by min_total_spent ascending for display
		usort($levels, function($a, $b) {
			return $a['min_total_spent'] - $b['min_total_spent'];
		});

		return $levels;
	}

	/**
	 * Get specific loyalty level configuration for a customer group
	 *
	 * This method retrieves the loyalty level details for a specific customer group ID.
	 * It searches through all configured loyalty levels and returns the matching level
	 * with its configuration including min_total_spent, display_name, and other properties.
	 *
	 * Function: Returns loyalty level configuration for a given customer group
	 * Scope: Should be called when you need to retrieve loyalty level details for a specific
	 *        customer group, such as displaying level information on customer profile pages,
	 *        or checking level requirements in the bonus manager controller or other modules
	 *
	 * @param int $customer_group_id The customer group ID to lookup
	 * @return array|null Loyalty level configuration array with keys: customer_group_id,
	 *                    min_total_spent, display_name (optional). Returns null if not found.
	 */
	public function getLoyaltyLevel($customer_group_id) {
		// Get all loyalty levels
		$levels = $this->getLoyaltyLevels();

		if (empty($levels)) {
			return null;
		}

		// Find the level matching this customer group ID
		foreach ($levels as $level) {
			if (isset($level['customer_group_id']) && (int)$level['customer_group_id'] === (int)$customer_group_id) {
				return $level;
			}
		}

		// No matching level found
		return null;
	}
}
