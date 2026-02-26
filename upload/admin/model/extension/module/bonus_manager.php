<?php
class ModelExtensionModuleBonusManager extends Model {

	/**
	 * Install module - creates all required tables and columns
	 *
	 * Creates:
	 * 1. bonus_settings - stores bonus percentage by customer group and category
	 * 2. customer_bonus_items - tracks product-level bonus points for returns handling
	 * 3. Extends customer_reward table with remaining, reward_kind, date_expires columns
	 * 4. Registers event handlers for order completion and returns
	 *
	 * Scope: Called when module is installed via admin Extensions > Modules
	 * Note: Compatible with MySQL 5.6+ (no IF NOT EXISTS for columns/indexes)
	 */
	public function install() {
		// 1. Create bonus_settings table for storing bonus percentages
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "bonus_settings` (
			`bonus_setting_id` int(11) NOT NULL AUTO_INCREMENT,
			`customer_group_id` int(11) NOT NULL,
			`category_id` int(11) NOT NULL DEFAULT 0,
			`bonus_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
			`date_added` datetime NOT NULL,
			`date_modified` datetime NOT NULL,
			PRIMARY KEY (`bonus_setting_id`),
			KEY `customer_group_id` (`customer_group_id`),
			KEY `category_id` (`category_id`),
			UNIQUE KEY `group_category` (`customer_group_id`, `category_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

		// 2. Create customer_bonus_items table for product-level bonus tracking
		// This table links order products to bonus points for accurate return deductions
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "customer_bonus_items` (
			`bonus_item_id` int(11) NOT NULL AUTO_INCREMENT,
			`order_id` int(11) NOT NULL,
			`product_id` int(11) NOT NULL COMMENT 'Product ID - allows flexible return matching',
			`product_name` varchar(255) DEFAULT NULL COMMENT 'Product name at time of order',
			`product_model` varchar(64) DEFAULT NULL COMMENT 'Product model/SKU at time of order',
			`product_quantity` int(11) NOT NULL DEFAULT 1 COMMENT 'Quantity ordered',
			`product_price` decimal(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Unit price',
			`product_total` decimal(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Line total',
			`order_product_id` int(11) NOT NULL COMMENT 'FK to order_product table',
			`bonus_points` int(11) NOT NULL COMMENT 'Bonus points for this line',
			`bonus_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Bonus percentage used',
			`status` enum('active','pending_deduction','deducted','pending','expired','cancelled') NOT NULL DEFAULT 'pending',
			`return_id` int(11) DEFAULT NULL COMMENT 'Return ID if deducted due to return',
			`return_quantity` int(11) DEFAULT NULL COMMENT 'Quantity returned',
			`date_added` datetime NOT NULL,
			`date_expires` datetime DEFAULT NULL COMMENT 'When bonus points expire',
			PRIMARY KEY (`bonus_item_id`),
			UNIQUE KEY `idx_order_product` (`order_product_id`),
			KEY `idx_order` (`order_id`),
			KEY `idx_product` (`product_id`),
			KEY `idx_date_expires` (`date_expires`),
			KEY `idx_status` (`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='Links order products to bonus points for return handling';");

		// 3. Extend customer_reward table with bonus-specific columns
		// These columns add expiration tracking and balance management to OpenCart's core table
		// Using helper methods for MySQL 5.6 compatibility (no IF NOT EXISTS for columns)

		// Add 'remaining' column - tracks unspent bonus balance for each award entry
		$this->addColumnIfNotExists('customer_reward', 'remaining',
			"ADD COLUMN `remaining` int(11) NOT NULL DEFAULT 0 COMMENT 'Unspent balance from this award' AFTER `points`");

		// Add 'reward_kind' column - categorizes entry type (award, adjust, expire, spend)
		$this->addColumnIfNotExists('customer_reward', 'reward_kind',
			"ADD COLUMN `reward_kind` enum('award','adjust','expire','spend','deduction') DEFAULT 'award' COMMENT 'Entry type' AFTER `remaining`");

		// Add 'bonus_type' column - specific bonus operation type
		$this->addColumnIfNotExists('customer_reward', 'bonus_type',
			"ADD COLUMN `bonus_type` varchar(50) DEFAULT 'reward' COMMENT 'order_complete, return_deduction, manual, etc.' AFTER `reward_kind`");

		// Add 'bonus_metadata' column - JSON storage for additional data
		$this->addColumnIfNotExists('customer_reward', 'bonus_metadata',
			"ADD COLUMN `bonus_metadata` text COMMENT 'JSON metadata for bonus info' AFTER `bonus_type`");

		// Add 'date_expires' column - when the bonus points expire
		$this->addColumnIfNotExists('customer_reward', 'date_expires',
			"ADD COLUMN `date_expires` datetime DEFAULT NULL COMMENT 'Expiration date for bonus points' AFTER `date_added`");

		// Add indexes for efficient queries
		$this->addIndexIfNotExists('customer_reward', 'idx_bonus_type', 'ADD INDEX `idx_bonus_type` (`bonus_type`)');
		$this->addIndexIfNotExists('customer_reward', 'idx_reward_kind', 'ADD INDEX `idx_reward_kind` (`reward_kind`)');
		$this->addIndexIfNotExists('customer_reward', 'idx_date_expires', 'ADD INDEX `idx_date_expires` (`date_expires`)');
		$this->addIndexIfNotExists('customer_reward', 'idx_remaining', 'ADD INDEX `idx_remaining` (`remaining`)');

		// Install event handlers
		$this->load->model('setting/event');

		// Remove existing events if any
		$this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = 'bonus_manager_order_complete'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = 'bonus_email_awarded'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = 'bonus_email_spent'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = 'bonus_email_deducted'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = 'bonus_manager_return_complete'");

		// Event: Award bonuses when order reaches complete status
		$this->model_setting_event->addEvent(
			'bonus_manager_order_complete',
			'catalog/model/checkout/order/addOrderHistory/after',
			'extension/module/bonus_manager/awardBonusesOnOrderComplete'
		);

		// Event: Send email when bonuses are awarded (custom event)
		$this->model_setting_event->addEvent(
			'bonus_email_awarded',
			'model/extension/module/bonus_manager/awarded',
			'mail/bonus/awarded'
		);

		// Event: Send email when bonuses are spent (future implementation, custom event)
		$this->model_setting_event->addEvent(
			'bonus_email_spent',
			'model/extension/module/bonus_manager/spent',
			'mail/bonus/spent'
		);

		// Event: Send email when bonuses are deducted (custom event)
		$this->model_setting_event->addEvent(
			'bonus_email_deducted',
			'model/extension/module/bonus_manager/deducted',
			'mail/bonus/deducted'
		);

		// Event: Deduct bonuses when return is completed (status = 4)
		$this->model_setting_event->addEvent(
			'bonus_manager_return_complete',
			'admin/model/sale/return/addReturnHistory/after',
			'extension/module/bonus_manager/deductBonusesOnReturnComplete'
		);

		// Add order_product_id column to return table for accurate bonus deduction
		// This allows identifying the exact order line item when same product appears multiple times
		$this->addColumnIfNotExists('return', 'order_product_id',
			"ADD COLUMN `order_product_id` int(11) DEFAULT NULL COMMENT 'FK to order_product for bonus tracking' AFTER `product_id`");
	}

	/**
	 * Uninstall module
	 */
	public function uninstall() {
		// Remove event handlers
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('bonus_manager_order_complete');
		$this->model_setting_event->deleteEventByCode('bonus_email_awarded');
		$this->model_setting_event->deleteEventByCode('bonus_email_spent');
		$this->model_setting_event->deleteEventByCode('bonus_email_deducted');
		$this->model_setting_event->deleteEventByCode('bonus_manager_return_complete');
	}

	/**
	 * Get all bonus settings
	 */
	public function getAllBonusSettings() {
		$query = $this->db->query("SELECT bs.*, cg.sort_order as group_sort, cgd.name as group_name
			FROM " . DB_PREFIX . "bonus_settings bs
			LEFT JOIN " . DB_PREFIX . "customer_group cg ON bs.customer_group_id = cg.customer_group_id
			LEFT JOIN " . DB_PREFIX . "customer_group_description cgd ON cg.customer_group_id = cgd.customer_group_id
			WHERE cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			ORDER BY cg.sort_order ASC, bs.category_id ASC");

		return $query->rows;
	}

	/**
	 * Get bonus settings for specific customer group
	 */
	public function getBonusSettings($customer_group_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bonus_settings
			WHERE customer_group_id = '" . (int)$customer_group_id . "'
			ORDER BY category_id ASC");

		return $query->rows;
	}

	/**
	 * Get single bonus setting
	 */
	public function getBonusSetting($bonus_setting_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "bonus_settings
			WHERE bonus_setting_id = '" . (int)$bonus_setting_id . "'");

		return $query->row;
	}

	/**
	 * Add bonus setting
	 */
	public function addBonusSetting($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "bonus_settings
			SET customer_group_id = '" . (int)$data['customer_group_id'] . "',
			category_id = '" . (int)$data['category_id'] . "',
			bonus_percent = '" . (float)$data['bonus_percent'] . "',
			date_added = NOW(),
			date_modified = NOW()
			ON DUPLICATE KEY UPDATE
			bonus_percent = '" . (float)$data['bonus_percent'] . "',
			date_modified = NOW()");

		return $this->db->getLastId();
	}

	/**
	 * Update bonus setting
	 */
	public function editBonusSetting($bonus_setting_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "bonus_settings
			SET customer_group_id = '" . (int)$data['customer_group_id'] . "',
			category_id = '" . (int)$data['category_id'] . "',
			bonus_percent = '" . (float)$data['bonus_percent'] . "',
			date_modified = NOW()
			WHERE bonus_setting_id = '" . (int)$bonus_setting_id . "'");
	}

	/**
	 * Delete bonus setting
	 */
	public function deleteBonusSetting($bonus_setting_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "bonus_settings
			WHERE bonus_setting_id = '" . (int)$bonus_setting_id . "'");
	}

	/**
	 * Get bonus statistics
	 */
	public function getBonusStatistics() {
		$stats = array();

		// Total bonuses issued
		$query = $this->db->query("SELECT SUM(points) as total FROM " . DB_PREFIX . "customer_reward
			WHERE points > 0 AND order_id > 0");
		$stats['total_issued'] = $query->row['total'] ? (int)$query->row['total'] : 0;

		// Total bonuses redeemed
		$query = $this->db->query("SELECT SUM(ABS(points)) as total FROM " . DB_PREFIX . "customer_reward
			WHERE points < 0");
		$stats['total_redeemed'] = $query->row['total'] ? (int)$query->row['total'] : 0;

		// Current active bonuses
		$query = $this->db->query("SELECT SUM(remaining) as total FROM " . DB_PREFIX . "customer_reward");
		$stats['active_bonuses'] = $query->row['total'] ? (int)$query->row['total'] : 0;

		// Number of customers with bonuses
		$query = $this->db->query("SELECT COUNT(DISTINCT customer_id) as total FROM " . DB_PREFIX . "customer_reward
			WHERE points > 0");
		$stats['customers_count'] = $query->row['total'] ? (int)$query->row['total'] : 0;

		// Orders with bonuses awarded
		$query = $this->db->query("SELECT COUNT(DISTINCT order_id) as total FROM " . DB_PREFIX . "customer_reward
			WHERE order_id > 0 AND points > 0");
		$stats['orders_with_bonuses'] = $query->row['total'] ? (int)$query->row['total'] : 0;

		return $stats;
	}

	/**
	 * Get recent bonus transactions
	 */
	public function getRecentBonusTransactions($limit = 10) {
		$query = $this->db->query("SELECT cr.*, CONCAT(c.firstname, ' ', c.lastname) as customer_name, c.email
			FROM " . DB_PREFIX . "customer_reward cr
			LEFT JOIN " . DB_PREFIX . "customer c ON cr.customer_id = c.customer_id
			ORDER BY cr.date_added DESC
			LIMIT " . (int)$limit);

		return $query->rows;
	}

	/**
	 * Get bonus transactions with pagination
	 */
	public function getBonusTransactions($data = array()) {
		$start = isset($data['start']) ? (int)$data['start'] : 0;
		$limit = isset($data['limit']) ? (int)$data['limit'] : 20;
		$filters = array();

		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		if (!empty($data['filter_order_id'])) {
			$filters[] = "cr.order_id = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$filters[] = "(CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'
				OR c.email LIKE '%" . $this->db->escape($data['filter_customer']) . "%')";
		}

		if (!empty($data['filter_reward_kind'])) {
			$filters[] = "cr.reward_kind = '" . $this->db->escape($data['filter_reward_kind']) . "'";
		}

		if (!empty($data['filter_bonus_type'])) {
			$filters[] = "cr.bonus_type = '" . $this->db->escape($data['filter_bonus_type']) . "'";
		}

		if (!empty($data['filter_points_sign'])) {
			if ($data['filter_points_sign'] === 'positive') {
				$filters[] = "cr.points > 0";
			} elseif ($data['filter_points_sign'] === 'negative') {
				$filters[] = "cr.points < 0";
			} elseif ($data['filter_points_sign'] === 'zero') {
				$filters[] = "cr.points = 0";
			}
		}

		if (!empty($data['filter_date_from'])) {
			$filters[] = "DATE(cr.date_added) >= DATE('" . $this->db->escape($data['filter_date_from']) . "')";
		}

		if (!empty($data['filter_date_to'])) {
			$filters[] = "DATE(cr.date_added) <= DATE('" . $this->db->escape($data['filter_date_to']) . "')";
		}

		$sql = "SELECT cr.*, CONCAT(c.firstname, ' ', c.lastname) as customer_name, c.email
			FROM " . DB_PREFIX . "customer_reward cr
			LEFT JOIN " . DB_PREFIX . "customer c ON cr.customer_id = c.customer_id";

		if ($filters) {
			$sql .= " WHERE " . implode(" AND ", $filters);
		}

		$sql .= " ORDER BY cr.date_added DESC
			LIMIT " . (int)$start . ", " . (int)$limit;

		$query = $this->db->query($sql);

		return $query->rows;
	}

	/**
	 * Get total bonus transactions
	 */
	public function getBonusTransactionsTotal($data = array()) {
		$filters = array();

		if (!empty($data['filter_order_id'])) {
			$filters[] = "cr.order_id = '" . (int)$data['filter_order_id'] . "'";
		}

		if (!empty($data['filter_customer'])) {
			$filters[] = "(CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'
				OR c.email LIKE '%" . $this->db->escape($data['filter_customer']) . "%')";
		}

		if (!empty($data['filter_reward_kind'])) {
			$filters[] = "cr.reward_kind = '" . $this->db->escape($data['filter_reward_kind']) . "'";
		}

		if (!empty($data['filter_bonus_type'])) {
			$filters[] = "cr.bonus_type = '" . $this->db->escape($data['filter_bonus_type']) . "'";
		}

		if (!empty($data['filter_points_sign'])) {
			if ($data['filter_points_sign'] === 'positive') {
				$filters[] = "cr.points > 0";
			} elseif ($data['filter_points_sign'] === 'negative') {
				$filters[] = "cr.points < 0";
			} elseif ($data['filter_points_sign'] === 'zero') {
				$filters[] = "cr.points = 0";
			}
		}

		if (!empty($data['filter_date_from'])) {
			$filters[] = "DATE(cr.date_added) >= DATE('" . $this->db->escape($data['filter_date_from']) . "')";
		}

		if (!empty($data['filter_date_to'])) {
			$filters[] = "DATE(cr.date_added) <= DATE('" . $this->db->escape($data['filter_date_to']) . "')";
		}

		$sql = "SELECT COUNT(*) as total
			FROM " . DB_PREFIX . "customer_reward cr
			LEFT JOIN " . DB_PREFIX . "customer c ON cr.customer_id = c.customer_id";

		if ($filters) {
			$sql .= " WHERE " . implode(" AND ", $filters);
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Get active bonus awards (remaining > 0)
	 */
	public function getActiveBonusAwards($limit = 10) {
		$query = $this->db->query("SELECT cr.*, CONCAT(c.firstname, ' ', c.lastname) as customer_name, c.email
			FROM " . DB_PREFIX . "customer_reward cr
			LEFT JOIN " . DB_PREFIX . "customer c ON cr.customer_id = c.customer_id
			WHERE cr.reward_kind = 'award'
			AND cr.remaining > 0
			ORDER BY cr.date_added DESC
			LIMIT " . (int)$limit);

		return $query->rows;
	}

	/**
	 * Get awarded clients with pagination
	 */
	public function getAwardedClients($data = array()) {
		$start = isset($data['start']) ? (int)$data['start'] : 0;
		$limit = isset($data['limit']) ? (int)$data['limit'] : 20;
		$filters = array();

		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$filters[] = "cr.reward_kind = 'award'";
		$filters[] = "cr.points > 0";

		if (!empty($data['filter_customer'])) {
			$filters[] = "(CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'
				OR c.email LIKE '%" . $this->db->escape($data['filter_customer']) . "%')";
		}

		if (!empty($data['filter_date_from'])) {
			$filters[] = "DATE(cr.date_added) >= DATE('" . $this->db->escape($data['filter_date_from']) . "')";
		}

		if (!empty($data['filter_date_to'])) {
			$filters[] = "DATE(cr.date_added) <= DATE('" . $this->db->escape($data['filter_date_to']) . "')";
		}

		if (!empty($data['filter_min_remaining'])) {
			$filters[] = "cr.remaining >= '" . (int)$data['filter_min_remaining'] . "'";
		}

		$sql = "SELECT c.customer_id, c.customer_group_id, CONCAT(c.firstname, ' ', c.lastname) as customer_name, c.email,
				SUM(cr.points) as total_awarded,
				SUM(CASE WHEN cr.remaining IS NULL THEN 0 ELSE cr.remaining END) as total_remaining,
				MAX(cr.date_added) as last_award_date
			FROM " . DB_PREFIX . "customer_reward cr
			LEFT JOIN " . DB_PREFIX . "customer c ON cr.customer_id = c.customer_id";

		if ($filters) {
			$sql .= " WHERE " . implode(" AND ", $filters);
		}

		$sql .= " GROUP BY c.customer_id
			ORDER BY total_remaining DESC, last_award_date DESC
			LIMIT " . (int)$start . ", " . (int)$limit;

		$query = $this->db->query($sql);
		$rows = $query->rows;

		foreach ($rows as &$row) {
			$row['loyalty_level'] = $this->getLoyaltyLevelDisplayName($row['customer_group_id']);
		}
		unset($row);

		return $rows;
	}

	/**
	 * Get loyalty level display name for a customer group.
	 * Uses custom display name from loyalty levels config when available.
	 */
	private function getLoyaltyLevelDisplayName($customer_group_id) {
		$levels = $this->config->get('module_bonus_manager_loyalty_levels');
		if ($levels && !is_array($levels)) {
			$decoded = json_decode($levels, true);
			if (is_array($decoded)) {
				$levels = $decoded;
			}
		}

		if (is_array($levels)) {
			foreach ($levels as $level) {
				if ((int)$level['customer_group_id'] === (int)$customer_group_id) {
					if (isset($level['display_name']) && trim($level['display_name']) !== '') {
						return trim($level['display_name']);
					}
					break;
				}
			}
		}

		$query = $this->db->query("SELECT name FROM " . DB_PREFIX . "customer_group_description
			WHERE customer_group_id = '" . (int)$customer_group_id . "'
			AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

		if ($query->num_rows) {
			return $query->row['name'];
		}

		return 'Group #' . (int)$customer_group_id;
	}

	/**
	 * Get total awarded clients
	 */
	public function getAwardedClientsTotal($data = array()) {
		$filters = array();

		$filters[] = "cr.reward_kind = 'award'";
		$filters[] = "cr.points > 0";

		if (!empty($data['filter_customer'])) {
			$filters[] = "(CONCAT(c.firstname, ' ', c.lastname) LIKE '%" . $this->db->escape($data['filter_customer']) . "%'
				OR c.email LIKE '%" . $this->db->escape($data['filter_customer']) . "%')";
		}

		if (!empty($data['filter_date_from'])) {
			$filters[] = "DATE(cr.date_added) >= DATE('" . $this->db->escape($data['filter_date_from']) . "')";
		}

		if (!empty($data['filter_date_to'])) {
			$filters[] = "DATE(cr.date_added) <= DATE('" . $this->db->escape($data['filter_date_to']) . "')";
		}

		if (!empty($data['filter_min_remaining'])) {
			$filters[] = "cr.remaining >= '" . (int)$data['filter_min_remaining'] . "'";
		}

		$sql = "SELECT COUNT(DISTINCT c.customer_id) as total
			FROM " . DB_PREFIX . "customer_reward cr
			LEFT JOIN " . DB_PREFIX . "customer c ON cr.customer_id = c.customer_id";

		if ($filters) {
			$sql .= " WHERE " . implode(" AND ", $filters);
		}

		$query = $this->db->query($sql);

		return (int)$query->row['total'];
	}

	/**
	 * Manually award bonuses for an order (admin action)
	 * This one tries to load the catalog model and should never be called from there
	 */
	
	// public function manuallyAwardBonuses($order_id) {
	// 	$this->load->model('sale/order');
	// 	$order_info = $this->model_sale_order->getOrder($order_id);

	// 	if (!$order_info) {
	// 		return false;
	// 	}

	// 	// Load catalog model
	// 	$catalog_model_path = DIR_CATALOG . 'model/extension/module/bonus_manager.php';

	// 	if (file_exists($catalog_model_path)) {
	// 		require_once($catalog_model_path);

	// 		$registry = new Registry();
	// 		$registry->set('db', $this->db);
	// 		$registry->set('config', $this->config);
	// 		$registry->set('log', $this->log);

	// 		$bonus_model = new ModelExtensionModuleBonusManager($registry);
	// 		return $bonus_model->awardBonusesForOrder($order_id);
	// 	}

	// 	return false;
	// }

	/**
	 * Get customer groups
	 */
	public function getCustomerGroups() {
		$query = $this->db->query("SELECT cg.customer_group_id, cgd.name
			FROM " . DB_PREFIX . "customer_group cg
			LEFT JOIN " . DB_PREFIX . "customer_group_description cgd ON cg.customer_group_id = cgd.customer_group_id
			WHERE cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			ORDER BY cg.sort_order ASC");

		return $query->rows;
	}

	/**
	 * Get categories for selection
	 */
	public function getCategories() {
		$query = $this->db->query("SELECT c.category_id, cd.name, c.parent_id
			FROM " . DB_PREFIX . "category c
			LEFT JOIN " . DB_PREFIX . "category_description cd ON c.category_id = cd.category_id
			WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			ORDER BY c.sort_order ASC, cd.name ASC");

		return $query->rows;
	}

	/**
	 * Get product-level bonus breakdown for an order
	 * Used for admin display to show which products earned which bonuses
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
	 * Deduct bonuses for returned product(s)
	 *
	 * Called when a return is approved in admin. Implements the negative bonus_items
	 * tracking system for returns with support for both full and partial returns.
	 *
	 * Process flow:
	 * 1. Retrieves return details (order_id, product_id, quantity)
	 * 2. Finds the original active bonus_items entry for the product
	 * 3. Calculates proportional deduction: (return_qty / original_qty) * bonus_points
	 * 4. Creates negative customer_reward entry (deduction)
	 * 5. Creates NEW negative bonus_items entry with:
	 *    - Negative bonus_points
	 *    - Return quantity in return_quantity field
	 *    - Same bonus_rate as original
	 *    - status='active' and return_id populated
	 *    - Same date_expires as original
	 * 6. Marks original bonus_items entry as status='deducted'
	 *
	 * Race condition handling:
	 * If bonuses haven't been awarded yet (no active entry found), creates a
	 * pending_deduction marker that will be processed by processPendingDeductions()
	 * when bonuses are awarded.
	 *
	 * Scope: Called from admin return approval workflow (return status change events)
	 *
	 * @param int $return_id Return ID from ocus_return table
	 * @return bool True if deduction successful or pending, false on error
	 */
	public function returnProductBonuses($return_id) {
		// Check if module is enabled
		if (!$this->config->get('module_bonus_manager_status')) {
			return false;
		}

		// Get return details
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "return
			WHERE return_id = '" . (int)$return_id . "'");

		if (!$query->num_rows) {
			$this->log->write('BONUS RETURN: Return #' . $return_id . ' not found');
			return false;
		}

		$return = $query->row;
		$order_id = (int)$return['order_id'];
		$product_id = (int)$return['product_id'];
		$return_quantity = (int)$return['quantity'] > 0 ? (int)$return['quantity'] : 1;
		// Use order_product_id from return if available (for accurate matching when same product ordered multiple times)
		$stored_order_product_id = isset($return['order_product_id']) ? (int)$return['order_product_id'] : 0;

		// Get customer_id - if order_id is specified, use it; otherwise find from product
		$customer_id = 0;
		$order_product_id = 0;
		$original_quantity = 0;

		if ($order_id > 0) {
			// Order specified - find product in this order
			$this->load->model('sale/order');
			$order_info = $this->model_sale_order->getOrder($order_id);

			if (!$order_info || !$order_info['customer_id']) {
				$this->log->write('BONUS RETURN: Invalid order #' . $order_id . ' for return #' . $return_id);
				return false;
			}

			$customer_id = (int)$order_info['customer_id'];

			// Use stored order_product_id if available, otherwise fall back to product_id lookup
			if ($stored_order_product_id > 0) {
				// Exact match using order_product_id from return record
				$query = $this->db->query("SELECT order_product_id, quantity FROM " . DB_PREFIX . "order_product
					WHERE order_id = '" . (int)$order_id . "'
					AND order_product_id = '" . (int)$stored_order_product_id . "'
					LIMIT 1");
			} else {
				// Legacy fallback: find by product_id (may be ambiguous if same product ordered multiple times)
				$query = $this->db->query("SELECT order_product_id, quantity FROM " . DB_PREFIX . "order_product
					WHERE order_id = '" . (int)$order_id . "'
					AND product_id = '" . (int)$product_id . "'
					LIMIT 1");
			}

			if (!$query->num_rows) {
				$this->log->write('BONUS RETURN: Product not found in order #' . $order_id . ' (order_product_id: ' . $stored_order_product_id . ', product_id: ' . $product_id . ')');
				return false;
			}

			$order_product = $query->row;
			$order_product_id = (int)$order_product['order_product_id'];
			$original_quantity = (int)$order_product['quantity'];
		} else {
			// Order not specified - find oldest order with this product that has bonus points
			$query = $this->db->query("
				SELECT op.order_product_id, op.quantity, op.order_id, o.customer_id, bi.bonus_points
				FROM " . DB_PREFIX . "order_product op
				INNER JOIN " . DB_PREFIX . "customer_bonus_items bi ON bi.order_product_id = op.order_product_id
				INNER JOIN " . DB_PREFIX . "order o ON o.order_id = op.order_id
				WHERE op.product_id = '" . (int)$product_id . "'
				AND bi.bonus_points > 0
				AND bi.status = 'active'
				ORDER BY op.order_id ASC
				LIMIT 1
			");

			if (!$query->num_rows) {
				$this->log->write('BONUS RETURN: No orders found with product #' . $product_id . ' that have bonus points');
				return false;
			}

			$order_product = $query->row;
			$order_product_id = (int)$order_product['order_product_id'];
			$original_quantity = (int)$order_product['quantity'];
			$order_id = (int)$order_product['order_id'];
			$customer_id = (int)$order_product['customer_id'];

			$this->log->write('BONUS RETURN: Flexible matching found order #' . $order_id . ' for product #' . $product_id);
		}

		// Get the original active bonus_items entry with all product data
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_bonus_items
			WHERE order_product_id = '" . (int)$order_product_id . "'
			AND status = 'active'");

		if (!$query->num_rows) {
			// No active bonus record exists yet - bonuses not awarded yet
			// Mark this return as pending deduction for when bonuses are awarded
			$this->log->write('BONUS RETURN: No active bonus record found for order_product_id #' . $order_product_id . '. Return #' . $return_id . ' will be processed when bonuses are awarded.');

			// Create a pending deduction marker row (store only order_product_id and return_quantity)
			// This is not actullay running because we have no condition when award has not yet run

			// TODO if it run in future we would need to check for existing pending deduction first 
			// when awarding bonuses to avoid errors for unique constraint!
			$this->db->query("INSERT INTO " . DB_PREFIX . "customer_bonus_items
				SET order_id = '" . (int)$order_id . "',
				order_product_id = '" . (int)$order_product_id . "',
				product_id = '" . (int)$product_id . "',
				return_quantity = '" . (int)$return_quantity . "',
				bonus_rate = 0.00,
				bonus_points = 0,
				status = 'pending_deduction',
				return_id = '" . (int)$return_id . "',
				date_added = NOW()");

			return true; // Return true as we've handled it by marking it pending
		}

		$awarded_item = $query->row;
		$awarded_bonus_points = (int)$awarded_item['bonus_points'];

		if ($awarded_bonus_points <= 0) {
			$this->log->write('BONUS RETURN: No bonus points to deduct for order_product_id #' . $order_product_id);
			return false;
		}

		// Calculate proportional deduction based on return quantity vs original product quantity
		$deduction = round(($return_quantity / $original_quantity) * $awarded_bonus_points);

		if ($deduction <= 0) {
			$this->log->write('BONUS RETURN: Calculated deduction is 0 for return #' . $return_id);
			return false;
		}

		// Deduct from remaining balance using cascade logic:
		// 1. Try to deduct from the original order's award first
		// 2. If not enough, set to 0 and deduct remainder from most recent awards with remaining > 0

		$points_to_deduct = $deduction;

		// Step 1: Find and deduct from the original order's award
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
			// Update remaining in the award entry
			$this->db->query("
				UPDATE " . DB_PREFIX . "customer_reward
				SET remaining = '" . (int)$new_remaining . "'
				WHERE customer_reward_id = '" . (int)$award_id . "'
			");

			$this->log->write('BONUS RETURN: Deducted ' . $deduct_from_this . ' from original order award #' . $award_id . ' (remaining: ' . $current_remaining . ' -> ' . $new_remaining . ')');

			$points_to_deduct -= $deduct_from_this;
		}

		// Step 2: If still have points to deduct, deduct from most recent awards with remaining > 0
		if ($points_to_deduct > 0) {
			$this->log->write('BONUS RETURN: Still need to deduct ' . $points_to_deduct . ' points. Deducting from most recent awards...');

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
				$current_remaining = (int)$award['remaining'];

				$deduct_from_this = min($points_to_deduct, $current_remaining);
				$new_remaining = $current_remaining - $deduct_from_this;

				// Update remaining points	
				$this->db->query("
					UPDATE " . DB_PREFIX . "customer_reward
					SET remaining = '" . (int)$new_remaining . "'
					WHERE customer_reward_id = '" . (int)$award_id . "'
				");

				$this->log->write('BONUS RETURN: Deducted ' . $deduct_from_this . ' from award #' . $award_id . ' (remaining: ' . $current_remaining . ' -> ' . $new_remaining . ')');

				$points_to_deduct -= $deduct_from_this;
			}

			if ($points_to_deduct > 0) {
				$this->log->write('BONUS RETURN: WARNING - Could not deduct all points. ' . $points_to_deduct . ' points remain undeducted.');
			}
		}

		// Insert negative customer_reward record to record the deduction
		// This is the only one point where we actually deduct from customer's total bonuses
		$this->load->language('extension/module/bonus_manager');
		$description = sprintf($this->language->get('text_return_deduction'), $return_id);

		$this->db->query("INSERT INTO " . DB_PREFIX . "customer_reward
			SET customer_id = '" . (int)$customer_id . "',
			order_id = '" . (int)$order_id . "',
			description = '" . $this->db->escape($description) . "',
			points = '" . (int)(-$deduction) . "',
			remaining = 0,
			bonus_type = 'return_deduction',
			reward_kind = 'adjust',
			bonus_metadata = '" . $this->db->escape(json_encode(array('return_id' => (int)$return_id, 'order_product_id' => (int)$order_product_id, 'product_id' => (int)$product_id))) . "',
			date_added = NOW(),
			date_expires = NULL");

		// Update the original awarded bonus_item: subtract deduction from bonus_points
		$awarded_id = (int)$awarded_item['bonus_item_id'];
		$new_awarded_points = max(0, (int)$awarded_bonus_points - (int)$deduction);

		if ($new_awarded_points > 0) {
			// Partial return: update remaining points and increment return_quantity
			$this->db->query("UPDATE " . DB_PREFIX . "customer_bonus_items
				SET bonus_points = '" . (int)$new_awarded_points . "',
				return_quantity = IFNULL(return_quantity,0) + '" . (int)$return_quantity . "'
				WHERE bonus_item_id = '" . (int)$awarded_id . "'");
		} else {
			// Full return: zero out points, mark as deducted, and set return_quantity
			$this->db->query("UPDATE " . DB_PREFIX . "customer_bonus_items
				SET bonus_points = 0,
				status = 'deducted',
				return_id = '" . (int)$return_id . "',
				return_quantity = IFNULL(return_quantity,0) + '" . (int)$return_quantity . "'
				WHERE bonus_item_id = '" . (int)$awarded_id . "'");
		}

		if ($return_quantity >= $original_quantity) {
			$this->log->write('BONUS RETURN: Deducted ' . $deduction . ' bonuses for return #' . $return_id . ' (full return)');
		} else {
			$this->log->write('BONUS RETURN: Deducted ' . $deduction . ' bonuses for return #' . $return_id . ' (partial return: ' . $return_quantity . '/' . $original_quantity . ' units)');
		}

		// Send email notification via admin mail controller
		if ($this->config->get('module_bonus_manager_email_deducted_status')) {
			// Prepare customer info from return data
			$customer_info = array(
				'customer_id' => $customer_id,
				'firstname' => $return['firstname'],
				'lastname' => $return['lastname'],
				'email' => $return['email']
			);

			// Call admin mail controller directly
			$this->load->controller('mail/bonus/deducted', array($customer_info, $order_id, $return_id, $deduction));
		}

		return true;
	}

	// =============================================================================
	// NOTE: Loyalty level logic has been moved to catalog/model/extension/module/bonus_manager.php
	// where it runs during order processing. Admin model only handles UI display needs.
	// =============================================================================

	// =============================================================================
	// CRON OPERATIONS - Expiration and Warning Methods
	// =============================================================================

	/**
	 * Get customer's current bonus balance
	 *
	 * Centralized method to calculate customer's available bonus balance.
	 * Uses the same logic as catalog/model/account/customer.php::getRewardTotal()
	 * to ensure consistency across the application.
	 *
	 * Scope: Called from cron operations and admin displays where customer balance is needed
	 *
	 * @param int $customer_id Customer ID
	 * @return int Available bonus balance
	 */
	public function getCustomerBalance($customer_id) {
		$query = $this->db->query("SELECT COALESCE(SUM(remaining), 0) as total FROM " . DB_PREFIX . "customer_reward
			WHERE customer_id = '" . (int)$customer_id . "'
			AND reward_kind = 'award'
			AND remaining > 0
			AND (date_expires IS NULL OR date_expires > NOW())");

		return (int)$query->row['total'];
	}

	/**
	 * Get bonuses expiring within specified days for warning emails
	 *
	 * Finds all bonus award entries that will expire within the given number of days
	 * and haven't had a warning sent yet for this period. Groups results by customer
	 * to enable sending consolidated emails.
	 *
	 * Scope: Called from cron job to send expiration warning emails
	 *
	 * @param int $days Number of days until expiration (with +/- 1 day tolerance)
	 * @return array Array of customers with their expiring bonus details
	 */
	public function getExpiringBonuses($days) {
		$query = $this->db->query("
			SELECT
				cr.customer_id,
				cr.customer_reward_id,
				cr.remaining,
				cr.date_expires,
				DATEDIFF(cr.date_expires, NOW()) as days_left,
				c.firstname,
				c.lastname,
				c.email
			FROM " . DB_PREFIX . "customer_reward cr
			LEFT JOIN " . DB_PREFIX . "customer c ON cr.customer_id = c.customer_id
			WHERE cr.reward_kind = 'award'
			AND cr.remaining > 0
			AND cr.date_expires IS NOT NULL
			AND DATEDIFF(cr.date_expires, NOW()) BETWEEN " . ((int)$days - 1) . " AND " . ((int)$days + 1) . "
			AND cr.description NOT LIKE '%(Warning " . (int)$days . "d sent)%'
			AND c.email IS NOT NULL
			ORDER BY cr.customer_id, cr.date_expires
		");

		if (!$query->num_rows) {
			return array();
		}

		// Group by customer to send one email per customer
		$customers_data = array();
		foreach ($query->rows as $row) {
			$customer_id = $row['customer_id'];
			if (!isset($customers_data[$customer_id])) {
				$customers_data[$customer_id] = array(
					'customer_info' => array(
						'customer_id' => $customer_id,
						'firstname' => $row['firstname'],
						'lastname' => $row['lastname'],
						'email' => $row['email']
					),
					'expiring_points' => 0,
					'days_left' => (int)$row['days_left'],
					'expiration_date' => date('d.m.Y', strtotime($row['date_expires'])),
					'reward_ids' => array()
				);
			}
			$customers_data[$customer_id]['expiring_points'] += (int)$row['remaining'];
			$customers_data[$customer_id]['reward_ids'][] = $row['customer_reward_id'];
		}

		return $customers_data;
	}

	/**
	 * Mark bonuses as warned for a specific warning period
	 *
	 * Updates the description field of bonus entries to indicate that a warning
	 * email has been sent for this period. Prevents duplicate warnings.
	 *
	 * Scope: Called after successfully sending expiration warning email
	 *
	 * @param array $reward_ids Array of customer_reward_id values to mark
	 * @param int $days The warning period in days
	 * @return void
	 */
	public function markBonusesAsWarned($reward_ids, $days) {
		if (empty($reward_ids)) {
			return;
		}

		foreach ($reward_ids as $reward_id) {
			$this->db->query("UPDATE " . DB_PREFIX . "customer_reward
				SET description = CONCAT(description, ' (Warning " . (int)$days . "d sent)')
				WHERE customer_reward_id = '" . (int)$reward_id . "'");
		}
	}

	/**
	 * Get count of expired bonuses that need to be processed
	 *
	 * Finds all bonus award entries that have passed their expiration date
	 * and still have remaining balance > 0.
	 *
	 * Scope: Called from cron job to check for expired bonuses
	 *
	 * @return array Array with 'count' and 'total_points' of expired bonuses
	 */
	public function getExpiredBonusesInfo() {
		$query = $this->db->query("SELECT COUNT(*) as count, COALESCE(SUM(remaining), 0) as total_points
			FROM " . DB_PREFIX . "customer_reward
			WHERE reward_kind = 'award'
			AND remaining > 0
			AND date_expires IS NOT NULL
			AND date_expires <= NOW()");

		return array(
			'count' => (int)$query->row['count'],
			'total_points' => (int)$query->row['total_points']
		);
	}

	/**
	 * Process expired bonuses - mark them as expired
	 *
	 * Sets remaining to 0 for all expired award entries and updates their
	 * reward_kind to 'expire'. This removes them from available balance
	 * while preserving the audit trail.
	 *
	 * Scope: Called from cron job to expire bonuses past their expiration date
	 *
	 * @return int Number of bonus records that were expired
	 */
	public function expireExpiredBonuses() {
		$info = $this->getExpiredBonusesInfo();

		if ($info['count'] <= 0) {
			return 0;
		}

		$this->db->query("UPDATE " . DB_PREFIX . "customer_reward
			SET remaining = 0,
			description = CONCAT(description, ' (Expired)'),
			reward_kind = 'expire'
			WHERE reward_kind = 'award'
			AND remaining > 0
			AND date_expires IS NOT NULL
			AND date_expires <= NOW()");

		return $info['count'];
	}

	// =============================================================================
	// BIRTHDAY BONUS METHODS
	// =============================================================================

	/**
	 * Get all customers with birthday today (for admin display)
	 *
	 * Returns all customers whose birthday matches today's date, regardless of
	 * whether they've already received a birthday bonus. Used for statistics display.
	 *
	 * Birthday field format in custom_field JSON: {"2":"YYYY-MM-DD", ...}
	 *
	 * Scope: Called from admin statistics tab display
	 *
	 * @return array Array of customer data (customer_id, firstname, lastname, email, received_bonus)
	 */
	public function getTodaysBirthdays() {
		$today_month = date('m');
		$today_day = date('d');
		$current_year = date('Y');

		// Get all customers with birthday custom field
		// Note: JSON may have spaces after colons ("2": "date") or not ("2":"date")
		$query = $this->db->query("
			SELECT c.customer_id, c.firstname, c.lastname, c.email, c.custom_field,
				(SELECT COUNT(*) FROM " . DB_PREFIX . "customer_reward cr
				 WHERE cr.customer_id = c.customer_id
				 AND cr.bonus_type = 'birthday'
				 AND YEAR(cr.date_added) = '" . (int)$current_year . "') as received_bonus
			FROM " . DB_PREFIX . "customer c
			WHERE c.status = 1
			AND c.custom_field IS NOT NULL
			AND c.custom_field != ''
			AND (c.custom_field LIKE '%\"2\":\"%-%-%\"%' OR c.custom_field LIKE '%\"2\": \"%-%-%\"%')
		");

		$customers = array();

		foreach ($query->rows as $row) {
			// Parse custom_field JSON and check birthday date
			$custom_fields = json_decode($row['custom_field'], true);

			if (!is_array($custom_fields) || !isset($custom_fields['2'])) {
				continue;
			}

			$birthday = $custom_fields['2'];

			// Validate date format YYYY-MM-DD and check if month/day matches today
			if (preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $birthday, $matches)) {
				if ($matches[1] == $today_month && $matches[2] == $today_day) {
					$customers[] = array(
						'customer_id' => $row['customer_id'],
						'firstname' => $row['firstname'],
						'lastname' => $row['lastname'],
						'email' => $row['email'],
						'received_bonus' => (int)$row['received_bonus'] > 0
					);
				}
			}
		}

		return $customers;
	}

	/**
	 * Get customers with birthday today who are eligible for birthday bonus
	 *
	 * Finds all registered customers whose birthday (stored in custom_field JSON with key "2")
	 * matches today's date (month and day), are in a customer group enrolled in the bonus program,
	 * and haven't received a birthday bonus in the current calendar year.
	 *
	 * Birthday field format in custom_field JSON: {"2":"YYYY-MM-DD", ...}
	 *
	 * Scope: Called from cron job to award birthday bonuses
	 *
	 * @return array Array of customer data (customer_id, firstname, lastname, email)
	 */
	public function getCustomersWithBirthdayToday() {
		$today_month = date('m');
		$today_day = date('d');
		$current_year = date('Y');

		// Get customer groups that are enrolled in bonus program
		$bonus_groups_query = $this->db->query("
			SELECT DISTINCT customer_group_id FROM " . DB_PREFIX . "bonus_settings
			WHERE bonus_percent > 0
		");

		if (!$bonus_groups_query->num_rows) {
			return array(); // No bonus-eligible groups configured
		}

		$eligible_group_ids = array();
		foreach ($bonus_groups_query->rows as $row) {
			$eligible_group_ids[] = (int)$row['customer_group_id'];
		}

		// Find customers with birthday today who:
		// 1. Are active (status = 1)
		// 2. Have an email address
		// 3. Are in a bonus-eligible customer group
		// 4. Have birthday matching today's month and day (custom_field JSON key "2")
		// 5. Haven't received birthday bonus this year
		// Note: JSON may have spaces after colons ("2": "date") or not ("2":"date")
		$query = $this->db->query("
			SELECT c.customer_id, c.firstname, c.lastname, c.email, c.custom_field
			FROM " . DB_PREFIX . "customer c
			WHERE c.status = 1
			AND c.email != ''
			AND c.customer_group_id IN (" . implode(',', $eligible_group_ids) . ")
			AND c.custom_field IS NOT NULL
			AND c.custom_field != ''
			AND (c.custom_field LIKE '%\"2\":\"%-%-%\"%' OR c.custom_field LIKE '%\"2\": \"%-%-%\"%')
			AND c.customer_id NOT IN (
				SELECT cr.customer_id FROM " . DB_PREFIX . "customer_reward cr
				WHERE cr.bonus_type = 'birthday'
				AND YEAR(cr.date_added) = '" . (int)$current_year . "'
			)
		");

		$customers = array();

		foreach ($query->rows as $row) {
			// Parse custom_field JSON and check birthday date
			$custom_fields = json_decode($row['custom_field'], true);

			if (!is_array($custom_fields) || !isset($custom_fields['2'])) {
				continue;
			}

			$birthday = $custom_fields['2'];

			// Validate date format YYYY-MM-DD and check if month/day matches today
			if (preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $birthday, $matches)) {
				if ($matches[1] == $today_month && $matches[2] == $today_day) {
					$customers[] = array(
						'customer_id' => $row['customer_id'],
						'firstname' => $row['firstname'],
						'lastname' => $row['lastname'],
						'email' => $row['email']
					);
				}
			}
		}

		return $customers;
	}

	/**
	 * Award birthday bonus to a customer
	 *
	 * Creates a bonus award entry for the customer's birthday.
	 * Uses 'birthday' as bonus_type to track and prevent duplicate awards within the same year.
	 * This method includes a double-check to prevent duplicate awards if cron runs multiple times.
	 *
	 * Scope: Called from cron job when processing birthday bonuses
	 *
	 * @param int $customer_id Customer ID
	 * @param int $bonus_amount Amount of bonus points to award
	 * @return bool True if bonus was awarded successfully
	 */
	public function awardBirthdayBonus($customer_id, $bonus_amount) {
		if ($customer_id <= 0 || $bonus_amount <= 0) {
			return false;
		}

		$current_year = date('Y');

		// Double-check: prevent duplicate awards if cron runs multiple times per day
		$check = $this->db->query("
			SELECT customer_reward_id FROM " . DB_PREFIX . "customer_reward
			WHERE customer_id = '" . (int)$customer_id . "'
			AND bonus_type = 'birthday'
			AND YEAR(date_added) = '" . (int)$current_year . "'
		");

		if ($check->num_rows) {
			return false; // Already awarded this year
		}

		// Calculate expiration date (use configured expiration days)
		$expiration_days = (int)$this->config->get('module_bonus_manager_expiration_days');
		if ($expiration_days <= 0) {
			$expiration_days = 365;
		}
		$expiration_date = date('Y-m-d H:i:s', strtotime('+' . $expiration_days . ' days'));

		// Insert birthday bonus
		$description = 'Подарок на День рождения ' . $current_year;

		$this->db->query("INSERT INTO " . DB_PREFIX . "customer_reward SET
			customer_id = '" . (int)$customer_id . "',
			order_id = 0,
			description = '" . $this->db->escape($description) . "',
			points = '" . (int)$bonus_amount . "',
			remaining = '" . (int)$bonus_amount . "',
			reward_kind = 'award',
			bonus_type = 'birthday',
			bonus_metadata = '" . $this->db->escape(json_encode(array('year' => $current_year))) . "',
			date_added = NOW(),
			date_expires = '" . $this->db->escape($expiration_date) . "'
		");

		return $this->db->countAffected() > 0;
	}

	// =============================================================================
	// SCHEMA HELPER METHODS (MySQL 5.6 compatible)
	// =============================================================================

	/**
	 * Add column to table if it doesn't exist (MySQL 5.6 compatible)
	 *
	 * MySQL 5.6 doesn't support IF NOT EXISTS for ADD COLUMN, so we check
	 * INFORMATION_SCHEMA first.
	 *
	 * @param string $table Table name (without prefix)
	 * @param string $column Column name to check
	 * @param string $definition ALTER TABLE ADD COLUMN definition
	 * @return bool True if column was added, false if already exists
	 */
	private function addColumnIfNotExists($table, $column, $definition) {
		$query = $this->db->query("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = '" . DB_PREFIX . $this->db->escape($table) . "'
			AND COLUMN_NAME = '" . $this->db->escape($column) . "'");

		if ($query->row['cnt'] == 0) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . $this->db->escape($table) . "` " . $definition);
			return true;
		}

		return false;
	}

	/**
	 * Add index to table if it doesn't exist (MySQL 5.6 compatible)
	 *
	 * @param string $table Table name (without prefix)
	 * @param string $index Index name to check
	 * @param string $definition ALTER TABLE ADD INDEX definition
	 * @return bool True if index was added, false if already exists
	 */
	private function addIndexIfNotExists($table, $index, $definition) {
		$query = $this->db->query("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.STATISTICS
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = '" . DB_PREFIX . $this->db->escape($table) . "'
			AND INDEX_NAME = '" . $this->db->escape($index) . "'");

		if ($query->row['cnt'] == 0) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . $this->db->escape($table) . "` " . $definition);
			return true;
		}

		return false;
	}
}
