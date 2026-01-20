<?php
class ModelAccountReward extends Model {
	/**
	 * Get customer reward transactions with full details
	 *
	 * Retrieves reward point transactions for the logged-in customer including:
	 * - Basic transaction info (points, description, date_added)
	 * - bonus_type: Type of transaction (order_complete, return_deduction, registration, etc.)
	 * - bonus_metadata: JSON data with additional details like order_id, product_id, bonus_pct, etc.
	 * - date_expires: Expiration date for earned points (NULL if no expiration)
	 *
	 * Scope: Called from catalog/controller/account/reward.php to display detailed transaction history
	 *
	 * @param array $data Pagination and sorting parameters
	 * @return array Array of reward transactions with all fields
	 */
	public function getRewards($data = array()) {
		$sql = "SELECT * FROM `" . DB_PREFIX . "customer_reward` WHERE customer_id = '" . (int)$this->customer->getId() . "'";

		$sort_data = array(
			'points',
			'description',
			'date_added',
			'bonus_type',
			'date_expires'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY date_added";
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

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalRewards() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "customer_reward` WHERE customer_id = '" . (int)$this->customer->getId() . "'");

		return $query->row['total'];
	}

	public function getTotalPoints() {
//		$query = $this->db->query("SELECT SUM(points) AS total FROM `" . DB_PREFIX . "customer_reward` WHERE customer_id = '" . (int)$this->customer->getId() . "' GROUP BY customer_id");
		// Exclude expired bonuses from total calculation
		$query = $this->db->query("SELECT SUM(remaining) AS total FROM " . DB_PREFIX . "customer_reward
			WHERE customer_id = '" . (int) $this->customer->getId() . "'
			AND reward_kind = 'award'
			AND remaining > 0
			AND (date_expires IS NULL OR date_expires > NOW())");
		if ($query->num_rows) {
			return $query->row['total'];
		} else {
			return 0;
		}
	}
}