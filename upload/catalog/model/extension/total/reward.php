<?php
class ModelExtensionTotalReward extends Model {
	public function getTotal($total) {
		if (isset($this->session->data['reward'])) {
			$this->load->language('extension/total/reward', 'reward');

			$points = $this->customer->getRewardPoints();

			if ($this->session->data['reward'] <= $points) {
				$discount_total = 0;

				// Calculate cart subtotal to determine maximum discount (1 point = 1 RUB)
				$cart_subtotal = 0;
				foreach ($this->cart->getProducts() as $product) {
					$cart_subtotal += $product['total'];
				}

				// Get maximum usage percentage from configuration (default 30%)
				$max_usage_percent = (float)$this->config->get('module_bonus_manager_max_usage_percent') ?: 30;

				// Calculate maximum allowed bonus usage based on percentage
				$max_allowed_bonus = ($cart_subtotal * $max_usage_percent) / 100;

				// Limit points usage to the minimum of: requested points, max allowed bonus, or cart subtotal
				$points_to_use = min($this->session->data['reward'], $max_allowed_bonus, $cart_subtotal);

				// Distribute discount proportionally across all products
				foreach ($this->cart->getProducts() as $product) {
					// Calculate proportional discount for this product
					$discount = $product['total'] * ($points_to_use / $cart_subtotal);

					// Apply tax adjustments
					if ($discount > 0 && $product['tax_class_id']) {
						$tax_rates = $this->tax->getRates($product['total'] - ($product['total'] - $discount), $product['tax_class_id']);

						foreach ($tax_rates as $tax_rate) {
							if ($tax_rate['type'] == 'P') {
								$total['taxes'][$tax_rate['tax_rate_id']] -= $tax_rate['amount'];
							}
						}
					}

					$discount_total += $discount;
				}

				$total['totals'][] = array(
					'code'       => 'reward',
					'title'      => sprintf($this->language->get('reward')->get('text_reward'), $this->session->data['reward']),
					'value'      => -$discount_total,
					'sort_order' => $this->config->get('total_reward_sort_order')
				);

				$total['total'] -= $discount_total;
			}
		}
	}

	public function confirm($order_info, $order_total) {
		$this->load->language('extension/total/reward');

		$points = 0;

		$start = strpos($order_total['title'], '(') + 1;
		$end = strrpos($order_total['title'], ')');

		if ($start && $end) {
			$points = substr($order_total['title'], $start, $end - $start);
		}

		$this->load->model('account/customer');

		// Deduct points from customer's account using FIFO allocation
		if ($this->model_account_customer->getRewardTotal($order_info['customer_id']) >= $points) {
			// Create SPEND entry in customer_reward
			// For SPEND entries: remaining = NULL, reward_kind = 'spend'
			$this->db->query("INSERT INTO " . DB_PREFIX . "customer_reward
			SET customer_id = '" . (int)$order_info['customer_id'] . "',
			order_id = '" . (int)$order_info['order_id'] . "',
			description = '" . $this->db->escape(sprintf($this->language->get('text_order_id'), (int)$order_info['order_id'])) . "',
			points = '" . (float)-$points . "',
			remaining = NULL,
			bonus_type = 'order_spend',
			reward_kind = 'spend',
			bonus_metadata = '" . $this->db->escape(json_encode(array(
				'order_id' => (int)$order_info['order_id']
			))) . "',
			date_added = NOW()");

			$spend_reward_id = $this->db->getLastId();

			// Allocate spending using FIFO (First In First Out by expiry date)
			// Find all AWARD rows with remaining > 0, ordered by date_expires ASC
			$query = $this->db->query("
				SELECT customer_reward_id, remaining, date_expires
				FROM " . DB_PREFIX . "customer_reward
				WHERE customer_id = '" . (int)$order_info['customer_id'] . "'
				AND reward_kind = 'award'
				AND remaining > 0
				AND (date_expires IS NULL OR date_expires > NOW())
				ORDER BY
					CASE WHEN date_expires IS NULL THEN 1 ELSE 0 END,
					date_expires ASC
			");

			$points_to_allocate = (int)$points;

			foreach ($query->rows as $award) {
				if ($points_to_allocate <= 0) {
					break;
				}

				$award_reward_id = (int)$award['customer_reward_id'];
				$available = (int)$award['remaining'];

				// Allocate as much as possible from this award
				$allocated = min($points_to_allocate, $available);

				// Decrement remaining in the award entry
				$this->db->query("
					UPDATE " . DB_PREFIX . "customer_reward
					SET remaining = remaining - " . (int)$allocated . "
					WHERE customer_reward_id = '" . (int)$award_reward_id . "'
				");

				// Create allocation record linking spend to award
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "customer_reward_allocation
					SET spend_reward_id = '" . (int)$spend_reward_id . "',
					award_reward_id = '" . (int)$award_reward_id . "',
					points = '" . (int)$allocated . "'
				");

				$points_to_allocate -= $allocated;
			}

			// Send email notification about spent bonuses
			$this->load->controller('mail/bonus/spent', array($order_info, $points));
		} else {
			return $this->config->get('config_fraud_status_id');
		}
	}

	/**
	 * Reverse bonus spending when order is cancelled/unconfirmed
	 * This method restores allocated bonuses back to awards and removes allocation records
	 *
	 * @param int $order_id Order ID to unconfirm
	 */
	public function unconfirm($order_id) {
		// Find the spend entry for this order
		$query = $this->db->query("
			SELECT customer_reward_id, customer_id, points
			FROM " . DB_PREFIX . "customer_reward
			WHERE order_id = '" . (int)$order_id . "'
			AND reward_kind = 'spend'
			AND points < 0
		");

		if ($query->num_rows) {
			$spend_reward_id = (int)$query->row['customer_reward_id'];

			// Find all allocations for this spend
			$allocations_query = $this->db->query("
				SELECT award_reward_id, points
				FROM " . DB_PREFIX . "customer_reward_allocation
				WHERE spend_reward_id = '" . (int)$spend_reward_id . "'
			");

			// Restore remaining points to each award
			foreach ($allocations_query->rows as $allocation) {
				$this->db->query("
					UPDATE " . DB_PREFIX . "customer_reward
					SET remaining = remaining + " . (int)$allocation['points'] . "
					WHERE customer_reward_id = '" . (int)$allocation['award_reward_id'] . "'
				");
			}

			// Delete allocation records
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "customer_reward_allocation
				WHERE spend_reward_id = '" . (int)$spend_reward_id . "'
			");
		}

		// Delete the spend entry
		$this->db->query("DELETE FROM " . DB_PREFIX . "customer_reward WHERE order_id = '" . (int)$order_id . "' AND points < 0");
	}
}
