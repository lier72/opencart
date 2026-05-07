<?php
class ModelExtensionModuleReviewRequest extends Model {
	public function isEnabled() {
		return (bool)$this->config->get('module_review_request_status');
	}

	public function canShowOnOrderPage() {
		return $this->isEnabled() && (bool)$this->config->get('module_review_request_show_on_order_page');
	}

	public function isExcludedCustomerGroup($customer_group_id) {
		return in_array((int)$customer_group_id, $this->getExcludedCustomerGroupIds(), true);
	}

	public function getChannels() {
		$channels = array();

		foreach ($this->getChannelConfigMap() as $code => $config) {
			if (!$this->config->get($config['status'])) {
				continue;
			}

			$url = $this->getDirectChannelUrl($code);
			$widget_code = html_entity_decode((string)$this->config->get($config['widget']), ENT_QUOTES, 'UTF-8');

			if (!$url && !$widget_code) {
				continue;
			}

			$channels[] = array(
				'code' => $code,
				'url' => $url,
				'widget_code' => $widget_code
			);
		}

		return $channels;
	}

	public function canAskOrganizationReview($email) {
		$email = $this->normalizeEmail($email);

		if (!$email) {
			return false;
		}

		if ($this->getOrganizationCooldownDays() <= 0) {
			return true;
		}

		$state = $this->getCustomerState($email);

		if (!$state || !$state['org_review_suppressed_until']) {
			return true;
		}

		return strtotime($state['org_review_suppressed_until']) <= time();
	}

	public function queueOrder($order_info, $order_status_id) {
		if (!$this->hasEligibleStatus($order_status_id)) {
			return false;
		}

		if ($this->isExcludedCustomerGroup(isset($order_info['customer_group_id']) ? $order_info['customer_group_id'] : 0)) {
			return false;
		}

		$email = $this->normalizeEmail($order_info['email']);

		if (!$email || $this->hasQueuedOrder($order_info['order_id'])) {
			return false;
		}

		if (!$this->hasEmailTargets($order_info['customer_id'])) {
			return false;
		}

		$delay_days = (int)$this->config->get('module_review_request_delay_days');

		if ($delay_days < 0) {
			$delay_days = 0;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "review_request_queue`
			SET `order_id` = '" . (int)$order_info['order_id'] . "',
				`customer_id` = '" . (int)$order_info['customer_id'] . "',
				`store_id` = '" . (int)$order_info['store_id'] . "',
				`language_code` = '" . $this->db->escape($order_info['language_code']) . "',
				`email` = '" . $this->db->escape($email) . "',
				`order_status_id` = '" . (int)$order_status_id . "',
				`status` = 'pending',
				`send_attempts` = 0,
				`date_send_after` = DATE_ADD(NOW(), INTERVAL " . $delay_days . " DAY),
				`date_added` = NOW(),
				`date_modified` = NOW()");

		return true;
	}

	public function getTrackedOrganizationUrl($review_request_id, $channel) {
		$request_info = $this->getQueueRequest($review_request_id);

		if (!$request_info) {
			return '';
		}

		return $this->getDirectChannelUrl($channel);
	}

	public function trackOrganizationReviewClick($review_request_id, $channel) {
		$request_info = $this->getQueueRequest($review_request_id);

		if (!$request_info) {
			return false;
		}

		$target_url = $this->getDirectChannelUrl($channel);

		if (!$target_url) {
			return false;
		}

		$email = $this->normalizeEmail($request_info['email']);

		if (!$email) {
			return false;
		}

		$state = $this->getCustomerState($email);
		$suppressed_until_sql = $this->getSuppressedUntilSql();

		$this->db->query("UPDATE `" . DB_PREFIX . "review_request_queue`
			SET `date_replied` = IFNULL(`date_replied`, NOW()),
				`reply_channel` = IF(`reply_channel` = '', '" . $this->db->escape($channel) . "', `reply_channel`),
				`date_modified` = NOW()
			WHERE `review_request_id` = '" . (int)$review_request_id . "'");

		if ($state) {
			$this->db->query("UPDATE `" . DB_PREFIX . "review_request_customer`
				SET `customer_id` = '" . (int)$request_info['customer_id'] . "',
					`last_order_id` = '" . (int)$request_info['order_id'] . "',
					`last_org_click_at` = NOW(),
					`last_org_click_channel` = '" . $this->db->escape($channel) . "',
					`org_review_suppressed_until` = " . $suppressed_until_sql . ",
					`date_modified` = NOW()
				WHERE `email` = '" . $this->db->escape($email) . "'");
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "review_request_customer`
				SET `email` = '" . $this->db->escape($email) . "',
					`customer_id` = '" . (int)$request_info['customer_id'] . "',
					`last_order_id` = '" . (int)$request_info['order_id'] . "',
					`last_org_request_sent_at` = NULL,
					`last_org_click_at` = NOW(),
					`last_org_click_channel` = '" . $this->db->escape($channel) . "',
					`org_review_suppressed_until` = " . $suppressed_until_sql . ",
					`date_added` = NOW(),
					`date_modified` = NOW()");
		}

		return true;
	}

	private function hasQueuedOrder($order_id) {
		$query = $this->db->query("SELECT `review_request_id` FROM `" . DB_PREFIX . "review_request_queue` WHERE `order_id` = '" . (int)$order_id . "'");

		return (bool)$query->num_rows;
	}

	private function hasEligibleStatus($order_status_id) {
		$order_statuses = (array)$this->config->get('module_review_request_order_status_ids');

		if (!$order_statuses) {
			$order_statuses = (array)$this->config->get('config_complete_status');
		}

		$order_statuses = array_map('intval', $order_statuses);

		return in_array((int)$order_status_id, $order_statuses);
	}

	private function hasEmailTargets($customer_id) {
		foreach (array_keys($this->getChannelConfigMap()) as $code) {
			if ($this->getDirectChannelUrl($code)) {
				return true;
			}
		}

		if ($this->config->get('module_review_request_include_product_reviews') && $this->config->get('config_review_status')) {
			if ($customer_id || $this->config->get('config_review_guest')) {
				return true;
			}
		}

		return false;
	}

	private function getQueueRequest($review_request_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "review_request_queue`
			WHERE `review_request_id` = '" . (int)$review_request_id . "'
			LIMIT 1");

		return $query->num_rows ? $query->row : array();
	}

	private function getCustomerState($email) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "review_request_customer`
			WHERE `email` = '" . $this->db->escape($email) . "'
			LIMIT 1");

		return $query->num_rows ? $query->row : array();
	}

	private function getChannelConfigMap() {
		return array(
			'google' => array(
				'status' => 'module_review_request_google_status',
				'reference' => 'module_review_request_google_reference',
				'url' => 'module_review_request_google_review_url',
				'widget' => 'module_review_request_google_widget_code'
			),
			'yandex' => array(
				'status' => 'module_review_request_yandex_status',
				'reference' => 'module_review_request_yandex_reference',
				'url' => 'module_review_request_yandex_review_url',
				'widget' => 'module_review_request_yandex_widget_code'
			)
		);
	}

	private function getDirectChannelUrl($code) {
		$config_map = $this->getChannelConfigMap();

		if (!isset($config_map[$code]) || !$this->config->get($config_map[$code]['status'])) {
			return '';
		}

		$reference = trim(html_entity_decode((string)$this->config->get($config_map[$code]['reference']), ENT_QUOTES, 'UTF-8'));
		$url = trim(html_entity_decode((string)$this->config->get($config_map[$code]['url']), ENT_QUOTES, 'UTF-8'));

		if (!$url) {
			$url = $this->normalizeReviewUrl($code, $reference);
		}

		return $url;
	}

	private function normalizeReviewUrl($code, $value) {
		$value = trim($value);

		if (!$value) {
			return '';
		}

		if (filter_var($value, FILTER_VALIDATE_URL)) {
			return $value;
		}

		if ($code == 'yandex' && ctype_digit($value)) {
			return 'https://yandex.ru/maps/org/' . $value . '/reviews/';
		}

		return '';
	}

	private function getOrganizationCooldownDays() {
		$cooldown_days = $this->config->get('module_review_request_org_review_cooldown_days');

		if ($cooldown_days === null || $cooldown_days === '') {
			$cooldown_days = 180;
		}

		$cooldown_days = (int)$cooldown_days;

		if ($cooldown_days < 0) {
			$cooldown_days = 0;
		}

		return $cooldown_days;
	}

	private function getSuppressedUntilSql() {
		$cooldown_days = $this->getOrganizationCooldownDays();

		if ($cooldown_days <= 0) {
			return 'NULL';
		}

		return "DATE_ADD(NOW(), INTERVAL " . $cooldown_days . " DAY)";
	}

	private function normalizeEmail($email) {
		return strtolower(trim((string)$email));
	}

	private function getExcludedCustomerGroupIds() {
		$customer_group_ids = array_map('intval', (array)$this->config->get('module_review_request_excluded_customer_group_ids'));

		return array_values(array_filter($customer_group_ids));
	}
}
