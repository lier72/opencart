<?php
class ControllerAccountReward extends Controller {
	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/reward', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/reward');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_reward'),
			'href' => $this->url->link('account/reward', '', true)
		);

		$this->load->model('account/reward');

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		$data['rewards'] = array();

		$filter_data = array(
			'sort'  => 'date_added',
			'order' => 'DESC',
			'start' => ($page - 1) * 10,
			'limit' => 10
		);

		$reward_total = $this->model_account_reward->getTotalRewards();

		$results = $this->model_account_reward->getRewards($filter_data);

		foreach ($results as $result) {
			// Format bonus type for display
			$bonus_type_display = $this->formatBonusType($result['bonus_type']);

			// Parse and format metadata
			$metadata_display = $this->formatMetadata($result['bonus_metadata'], $result['bonus_type']);

			// Format expiration date
			$date_expires_display = '';
			$expiration_status = '';
			if (!empty($result['date_expires'])) {
				$expires_timestamp = strtotime($result['date_expires']);
				$date_expires_display = date($this->language->get('date_format_short'), $expires_timestamp);

				// Check if expired
				if ($expires_timestamp < time()) {
					$expiration_status = 'expired';
				} else {
					// Calculate days until expiration
					$days_until_expiry = ceil(($expires_timestamp - time()) / 86400);
					if ($days_until_expiry <= 30) {
						$expiration_status = 'expiring_soon';
					} else {
						$expiration_status = 'active';
					}
				}
			}

			// Format points display (positive in green, negative in red)
			$points_class = $result['points'] > 0 ? 'text-success' : ($result['points'] < 0 ? 'text-danger' : '');
			$points_display = $result['points'] > 0 ? '+' . $result['points'] : $result['points'];

			$data['rewards'][] = array(
				'order_id'           => $result['order_id'],
				'points'             => $result['points'],
				'points_display'     => $points_display,
				'points_class'       => $points_class,
				'description'        => $result['description'],
				'date_added'         => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'bonus_type'         => $result['bonus_type'],
				'bonus_type_display' => $bonus_type_display,
				'metadata_display'   => $metadata_display,
				'date_expires'       => $date_expires_display,
				'expiration_status'  => $expiration_status,
				'href'               => $this->url->link('account/order/info', 'order_id=' . $result['order_id'], true)
			);
		}

		$pagination = new Pagination();
		$pagination->total = $reward_total;
		$pagination->page = $page;
		$pagination->limit = 10;
		$pagination->url = $this->url->link('account/reward', 'page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($reward_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($reward_total - 10)) ? $reward_total : ((($page - 1) * 10) + 10), $reward_total, ceil($reward_total / 10));

		$data['total'] = (int)$this->customer->getRewardPoints();

		// Get customer's loyalty level information (only if bonus_manager is enabled)
		if ($this->config->get('module_bonus_manager_status')) {
			$this->load->model('extension/module/bonus_manager');
			$customer_group_id = $this->customer->getGroupId();
			$current_level = $this->model_extension_module_bonus_manager->getLoyaltyLevel($customer_group_id);

			// Pass loyalty level data to view
			$data['loyalty_level'] = $current_level;
			$data['loyalty_level_color'] = $this->getLoyaltyLevelColor($current_level);
		} else {
			$data['loyalty_level'] = null;
			$data['loyalty_level_color'] = 'default';
		}

		$data['continue'] = $this->url->link('account/account', '', true);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/reward', $data));
	}

	/**
	 * Format bonus type for display
	 *
	 * Converts database bonus_type codes into user-friendly display labels.
	 * Supports multiple transaction types from the bonus system.
	 *
	 * Scope: Helper method called from index() to format bonus types for customer view
	 *
	 * @param string $bonus_type The bonus_type from database (order_complete, return_deduction, etc.)
	 * @return string Formatted, user-friendly type label
	 */
	private function formatBonusType($bonus_type) {
		if (empty($bonus_type)) {
			$bonus_type = 'reward'; // Default for old entries
		}

		$type_map = array(
			'order_spend'       => $this->language->get('text_type_order_spend'),
			'order_complete'    => $this->language->get('text_type_order_complete'),
			'return_deduction'  => $this->language->get('text_type_return_deduction'),
			'registration'      => $this->language->get('text_type_registration'),
			'birthday'          => $this->language->get('text_type_birthday'),
			'product_bonus'     => $this->language->get('text_type_product_bonus'),
			'manual_adjustment' => $this->language->get('text_type_manual_adjustment'),
			'reward'            => $this->language->get('text_type_reward')
		);

		return isset($type_map[$bonus_type]) ? $type_map[$bonus_type] : ucfirst(str_replace('_', ' ', $bonus_type));
	}

	/**
	 * Format bonus metadata for display
	 *
	 * Parses JSON bonus_metadata and formats it into user-friendly display text.
	 * Different metadata structures are shown based on bonus_type:
	 * - order_complete: Shows order_id and bonus percentage
	 * - return_deduction: Shows return_id, product_id, and order_product_id
	 *
	 * Scope: Helper method called from index() to format metadata for customer view
	 *
	 * @param string $metadata_json JSON-encoded metadata from database
	 * @param string $bonus_type The type of bonus transaction
	 * @return string Formatted metadata string for display
	 */
	private function formatMetadata($metadata_json, $bonus_type) {
		if (empty($metadata_json)) {
			return '-';
		}

		$metadata = json_decode($metadata_json, true);
		if (!is_array($metadata)) {
			return '-';
		}

		$parts = array();

		// Format based on bonus type
		switch ($bonus_type) {
			case 'order_complete':
				if (isset($metadata['order_id'])) {
					$parts[] = $this->language->get('text_metadata_order') . ' #' . $metadata['order_id'];
				}
				if (isset($metadata['bonus_pct'])) {
					$parts[] = $this->language->get('text_metadata_bonus_percent') . ': ' . number_format($metadata['bonus_pct'], 1) . '%';
				}
				break;

			case 'return_deduction':
				if (isset($metadata['return_id'])) {
					$parts[] = $this->language->get('text_metadata_return') . ' #' . $metadata['return_id'];
				}
				if (isset($metadata['product_id'])) {
					$parts[] = $this->language->get('text_metadata_product') . ' #' . $metadata['product_id'];
				}
				break;

			default:
				// For other types, show all metadata as key: value pairs
				foreach ($metadata as $key => $value) {
					if (is_scalar($value)) {
						$parts[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
					}
				}
				break;
		}

		return !empty($parts) ? implode(', ', $parts) : '-';
	}

	/**
	 * Get loyalty level color badge
	 *
	 * Maps loyalty level display names to Bootstrap badge colors.
	 * Colors are assigned based on common loyalty tier naming patterns:
	 * - Basic/Базовый: secondary (gray)
	 * - Bronze/Бронзовый: warning (orange/brown)
	 * - Silver/Серебряный: default (light gray/silver)
	 * - Gold/Золотой: warning (gold/yellow)
	 * - Platinum/Платиновый: info (blue/platinum)
	 * - Diamond/Алмазный: primary (blue)
	 *
	 * Scope: Helper method called from index() to determine badge color for loyalty level display
	 *
	 * @param array|null $level Loyalty level data with display_name
	 * @return string Bootstrap badge color class (primary, success, info, warning, danger, default)
	 */
	private function getLoyaltyLevelColor($level) {
		if (empty($level) || !isset($level['display_name'])) {
			return 'default';
		}

		$display_name = mb_strtolower($level['display_name'], 'UTF-8');

		// Color mapping based on common loyalty tier names
		$color_map = array(
			// Russian names
			'базов' => 'secondary',      // Базовый - Basic (gray)
			'бронз' => 'warning',        // Бронзовый - Bronze (brown/orange)
			'серебр' => 'silver',        // Серебряный - Silver (silver/light gray)
			'золот' => 'gold',           // Золотой - Gold (gold)
			'платин' => 'platinum',      // Платиновый - Platinum (blue)
			'алмаз' => 'primary',        // Алмазный - Diamond (blue)

			// English names
			'basic' => 'secondary',
			'bronze' => 'warning',
			'silver' => 'silver',
			'gold' => 'gold',
			'platinum' => 'platinum',
			'diamond' => 'primary'
		);

		// Check for matches
		foreach ($color_map as $keyword => $color) {
			if (strpos($display_name, $keyword) !== false) {
				return $color;
			}
		}

		// Default color if no match found
		return 'primary';
	}
}