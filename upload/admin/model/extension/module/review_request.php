<?php
class ModelExtensionModuleReviewRequest extends Model {
	private $schema_checked = false;

	public function install() {
		$this->ensureSchema();

		$this->load->model('setting/event');
		$this->load->model('setting/setting');

		$this->model_setting_event->deleteEventByCode('review_request_queue');
		$this->model_setting_event->addEvent('review_request_queue', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/module/review_request/queueOrder');

		$current_settings = $this->model_setting_setting->getSetting('module_review_request');

		if (!is_array($current_settings)) {
			$current_settings = array();
		}

		$order_statuses = (array)$this->config->get('config_complete_status');

		if (!$order_statuses) {
			$order_statuses = array(5);
		}

		$defaults = array(
			'module_review_request_status' => 0,
			'module_review_request_email_status' => 1,
			'module_review_request_show_on_order_page' => 1,
			'module_review_request_delay_days' => 7,
			'module_review_request_order_status_ids' => $order_statuses,
			'module_review_request_excluded_customer_group_ids' => array(),
			'module_review_request_include_product_reviews' => 1,
			'module_review_request_org_review_cooldown_days' => 180,
			'module_review_request_org_review_suppressed_mode' => 'product_only',
			'module_review_request_track_review_clicks' => 1,
			'module_review_request_review_bonus_points' => 0,
			'module_review_request_email_subject' => '',
			'module_review_request_email_body' => '',
			'module_review_request_google_status' => 0,
			'module_review_request_google_reference' => '',
			'module_review_request_google_review_url' => '',
			'module_review_request_google_widget_code' => '',
			'module_review_request_yandex_status' => 0,
			'module_review_request_yandex_reference' => '',
			'module_review_request_yandex_review_url' => '',
			'module_review_request_yandex_widget_code' => '',
			'module_review_request_schema_version' => $this->getSchemaVersion()
		);

		$settings = array_merge($defaults, $current_settings);
		$settings['module_review_request_schema_version'] = $this->getSchemaVersion();

		$this->model_setting_setting->editSetting('module_review_request', $settings);
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->load->model('setting/setting');

		$this->model_setting_event->deleteEventByCode('review_request_queue');
		$this->model_setting_setting->deleteSetting('module_review_request');
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "review_request_customer`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "review_request_queue`");
	}

	private function ensureSchema() {
		if ($this->schema_checked) {
			return;
		}

		$queue_table = DB_PREFIX . "review_request_queue";

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "review_request_queue` (
			`review_request_id` int(11) NOT NULL AUTO_INCREMENT,
			`order_id` int(11) NOT NULL,
			`customer_id` int(11) NOT NULL DEFAULT 0,
			`store_id` int(11) NOT NULL DEFAULT 0,
			`language_code` varchar(32) NOT NULL DEFAULT '',
			`email` varchar(96) NOT NULL DEFAULT '',
			`order_status_id` int(11) NOT NULL DEFAULT 0,
			`status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
			`send_attempts` int(11) NOT NULL DEFAULT 0,
			`last_error` text,
			`date_send_after` datetime NOT NULL,
			`date_sent` datetime DEFAULT NULL,
			`date_replied` datetime DEFAULT NULL,
			`reply_channel` varchar(32) NOT NULL DEFAULT '',
			`review_bonus_awarded_at` datetime DEFAULT NULL,
			`review_bonus_award_points` int(11) NOT NULL DEFAULT 0,
			`review_bonus_awarded_by` int(11) NOT NULL DEFAULT 0,
			`date_added` datetime NOT NULL,
			`date_modified` datetime NOT NULL,
			PRIMARY KEY (`review_request_id`),
			UNIQUE KEY `order_id` (`order_id`),
			KEY `status_date_send_after` (`status`,`date_send_after`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "review_request_customer` (
			`review_request_customer_id` int(11) NOT NULL AUTO_INCREMENT,
			`email` varchar(96) NOT NULL,
			`customer_id` int(11) NOT NULL DEFAULT 0,
			`last_order_id` int(11) NOT NULL DEFAULT 0,
			`last_org_request_sent_at` datetime DEFAULT NULL,
			`last_org_click_at` datetime DEFAULT NULL,
			`last_org_click_channel` varchar(32) NOT NULL DEFAULT '',
			`org_review_suppressed_until` datetime DEFAULT NULL,
			`date_added` datetime NOT NULL,
			`date_modified` datetime NOT NULL,
			PRIMARY KEY (`review_request_customer_id`),
			UNIQUE KEY `email` (`email`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

		if (!$this->db->query("SHOW COLUMNS FROM `" . $queue_table . "` LIKE 'date_replied'")->num_rows) {
			$this->db->query("ALTER TABLE `" . $queue_table . "` ADD `date_replied` datetime DEFAULT NULL AFTER `date_sent`");
		}

		if (!$this->db->query("SHOW COLUMNS FROM `" . $queue_table . "` LIKE 'reply_channel'")->num_rows) {
			$this->db->query("ALTER TABLE `" . $queue_table . "` ADD `reply_channel` varchar(32) NOT NULL DEFAULT '' AFTER `date_replied`");
		}

		if (!$this->db->query("SHOW COLUMNS FROM `" . $queue_table . "` LIKE 'review_bonus_awarded_at'")->num_rows) {
			$this->db->query("ALTER TABLE `" . $queue_table . "` ADD `review_bonus_awarded_at` datetime DEFAULT NULL AFTER `reply_channel`");
		}

		if (!$this->db->query("SHOW COLUMNS FROM `" . $queue_table . "` LIKE 'review_bonus_award_points'")->num_rows) {
			$this->db->query("ALTER TABLE `" . $queue_table . "` ADD `review_bonus_award_points` int(11) NOT NULL DEFAULT 0 AFTER `review_bonus_awarded_at`");
		}

		if (!$this->db->query("SHOW COLUMNS FROM `" . $queue_table . "` LIKE 'review_bonus_awarded_by'")->num_rows) {
			$this->db->query("ALTER TABLE `" . $queue_table . "` ADD `review_bonus_awarded_by` int(11) NOT NULL DEFAULT 0 AFTER `review_bonus_award_points`");
		}

		$this->schema_checked = true;
	}

	public function getDueRequests($limit = 50) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "review_request_queue`
			WHERE `status` = 'pending' AND `date_send_after` <= NOW()
			ORDER BY `review_request_id` ASC
			LIMIT " . (int)$limit);

		return $query->rows;
	}

	public function getStatisticsReport() {
		$report = array();
		$periods = array(
			'day' => 'DATE_SUB(NOW(), INTERVAL 1 DAY)',
			'week' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
			'month' => 'DATE_SUB(NOW(), INTERVAL 1 MONTH)',
			'all' => ''
		);

		foreach ($periods as $key => $since_sql) {
			$report[$key] = $this->getStatisticsTotals($since_sql);
		}

		return $report;
	}

	public function getReplyChannelStatisticsReport() {
		$report = array(
			'channels' => $this->getTrackedReplyChannels(),
			'periods' => array()
		);
		$periods = array(
			'day' => 'DATE_SUB(NOW(), INTERVAL 1 DAY)',
			'week' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
			'month' => 'DATE_SUB(NOW(), INTERVAL 1 MONTH)',
			'all' => ''
		);
		$query = $this->db->query("SELECT DISTINCT `reply_channel`
			FROM `" . DB_PREFIX . "review_request_queue`
			WHERE `reply_channel` <> ''
			ORDER BY `reply_channel` ASC");

		foreach ($query->rows as $row) {
			if (!in_array($row['reply_channel'], $report['channels'], true)) {
				$report['channels'][] = $row['reply_channel'];
			}
		}

		foreach ($periods as $key => $since_sql) {
			$report['periods'][$key] = $this->getReplyChannelTotals($since_sql, $report['channels']);
		}

		return $report;
	}

	public function getRecentReviewReplies($limit = 100) {
		$query = $this->db->query("SELECT
			rr.`review_request_id`,
			rr.`order_id`,
			rr.`customer_id`,
			rr.`email`,
			rr.`reply_channel`,
			rr.`date_sent`,
			rr.`date_replied`,
			rr.`review_bonus_awarded_at`,
			rr.`review_bonus_award_points`,
			o.`firstname`,
			o.`lastname`
			FROM `" . DB_PREFIX . "review_request_queue` rr
			LEFT JOIN `" . DB_PREFIX . "order` o ON (o.`order_id` = rr.`order_id`)
			WHERE rr.`date_replied` IS NOT NULL
			ORDER BY rr.`date_replied` DESC
			LIMIT " . (int)$limit);

		return $query->rows;
	}

	public function awardReviewBonus($review_request_id, $user_id = 0) {
		$review_bonus_points = $this->getReviewBonusPoints();

		if ($review_bonus_points <= 0) {
			return array(
				'success' => false,
				'error_code' => 'disabled'
			);
		}

		$review_request_info = $this->getReviewRequestForBonus($review_request_id);

		if (!$review_request_info) {
			return array(
				'success' => false,
				'error_code' => 'not_found'
			);
		}

		if (!$review_request_info['date_replied']) {
			return array(
				'success' => false,
				'error_code' => 'not_replied'
			);
		}

		if ($review_request_info['review_bonus_awarded_at']) {
			return array(
				'success' => false,
				'error_code' => 'already_awarded',
				'awarded_at' => $review_request_info['review_bonus_awarded_at'],
				'points' => (int)$review_request_info['review_bonus_award_points']
			);
		}

		if ((int)$review_request_info['customer_id'] <= 0) {
			return array(
				'success' => false,
				'error_code' => 'guest'
			);
		}

		$this->load->model('customer/customer');

		$description = 'Review bonus for order #' . (int)$review_request_info['order_id'];

		if ($review_request_info['reply_channel']) {
			$description .= ' via ' . $review_request_info['reply_channel'];
		}

		$this->model_customer_customer->addReward((int)$review_request_info['customer_id'], $description, $review_bonus_points, 0);

		$this->db->query("UPDATE `" . DB_PREFIX . "review_request_queue`
			SET `review_bonus_awarded_at` = NOW(),
				`review_bonus_award_points` = '" . (int)$review_bonus_points . "',
				`review_bonus_awarded_by` = '" . (int)$user_id . "',
				`date_modified` = NOW()
			WHERE `review_request_id` = '" . (int)$review_request_id . "'");

		return array(
			'success' => true,
			'points' => $review_bonus_points,
			'customer_id' => (int)$review_request_info['customer_id']
		);
	}

	public function markSent($review_request_id) {
		$this->db->query("UPDATE `" . DB_PREFIX . "review_request_queue`
			SET `status` = 'sent',
				`send_attempts` = `send_attempts` + 1,
				`last_error` = '',
				`date_sent` = NOW(),
				`date_modified` = NOW()
			WHERE `review_request_id` = '" . (int)$review_request_id . "'");
	}

	public function markSkipped($review_request_id, $reason) {
		$this->db->query("UPDATE `" . DB_PREFIX . "review_request_queue`
			SET `status` = 'sent',
				`last_error` = '" . $this->db->escape('Skipped: ' . $reason) . "',
				`date_sent` = NOW(),
				`date_modified` = NOW()
			WHERE `review_request_id` = '" . (int)$review_request_id . "'");
	}

	public function markRetry($review_request_id, $error) {
		$query = $this->db->query("SELECT `send_attempts` FROM `" . DB_PREFIX . "review_request_queue` WHERE `review_request_id` = '" . (int)$review_request_id . "'");

		if (!$query->num_rows) {
			return;
		}

		$send_attempts = (int)$query->row['send_attempts'] + 1;

		if ($send_attempts >= 3) {
			$this->db->query("UPDATE `" . DB_PREFIX . "review_request_queue`
				SET `status` = 'failed',
					`send_attempts` = '" . $send_attempts . "',
					`last_error` = '" . $this->db->escape($error) . "',
					`date_modified` = NOW()
				WHERE `review_request_id` = '" . (int)$review_request_id . "'");
		} else {
			$this->db->query("UPDATE `" . DB_PREFIX . "review_request_queue`
				SET `status` = 'pending',
					`send_attempts` = '" . $send_attempts . "',
					`last_error` = '" . $this->db->escape($error) . "',
					`date_send_after` = DATE_ADD(NOW(), INTERVAL 1 DAY),
					`date_modified` = NOW()
				WHERE `review_request_id` = '" . (int)$review_request_id . "'");
		}
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

	public function isExcludedCustomerGroup($customer_group_id) {
		return in_array((int)$customer_group_id, $this->getExcludedCustomerGroupIds(), true);
	}

	public function markOrganizationReviewSent($email, $customer_id, $order_id) {
		$email = $this->normalizeEmail($email);

		if (!$email) {
			return;
		}

		$state = $this->getCustomerState($email);
		$suppressed_until_sql = $this->getSuppressedUntilSql();

		if ($state) {
			$this->db->query("UPDATE `" . DB_PREFIX . "review_request_customer`
				SET `customer_id` = '" . (int)$customer_id . "',
					`last_order_id` = '" . (int)$order_id . "',
					`last_org_request_sent_at` = NOW(),
					`org_review_suppressed_until` = " . $suppressed_until_sql . ",
					`date_modified` = NOW()
				WHERE `email` = '" . $this->db->escape($email) . "'");
		} else {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "review_request_customer`
				SET `email` = '" . $this->db->escape($email) . "',
					`customer_id` = '" . (int)$customer_id . "',
					`last_order_id` = '" . (int)$order_id . "',
					`last_org_request_sent_at` = NOW(),
					`last_org_click_at` = NULL,
					`last_org_click_channel` = '',
					`org_review_suppressed_until` = " . $suppressed_until_sql . ",
					`date_added` = NOW(),
					`date_modified` = NOW()");
		}
	}

	private function getCustomerState($email) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "review_request_customer`
			WHERE `email` = '" . $this->db->escape($email) . "'
			LIMIT 1");

		return $query->num_rows ? $query->row : array();
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

	private function getStatisticsTotals($since_sql = '') {
		$date_sent_filter = $since_sql ? " AND `date_sent` >= " . $since_sql : '';
		$date_replied_filter = $since_sql ? " AND `date_replied` >= " . $since_sql : '';

		$query = $this->db->query("SELECT
			SUM(CASE
				WHEN `status` = 'sent'
					AND `date_sent` IS NOT NULL
					AND COALESCE(`last_error`, '') NOT LIKE 'Skipped:%'" . $date_sent_filter . "
				THEN 1 ELSE 0
			END) AS `sent`,
			SUM(CASE
				WHEN `date_replied` IS NOT NULL" . $date_replied_filter . "
				THEN 1 ELSE 0
			END) AS `replied`,
			SUM(CASE
				WHEN `status` = 'sent'
					AND `date_sent` IS NOT NULL
					AND COALESCE(`last_error`, '') LIKE 'Skipped:%'" . $date_sent_filter . "
				THEN 1 ELSE 0
			END) AS `skipped`
			FROM `" . DB_PREFIX . "review_request_queue`");

		return array(
			'sent' => (int)$query->row['sent'],
			'replied' => (int)$query->row['replied'],
			'skipped' => (int)$query->row['skipped']
		);
	}

	private function getReplyChannelTotals($since_sql = '', $channels = array()) {
		$totals = array();
		$date_replied_filter = $since_sql ? " AND `date_replied` >= " . $since_sql : '';

		foreach ($channels as $channel) {
			$totals[$channel] = 0;
		}

		$query = $this->db->query("SELECT `reply_channel`, COUNT(*) AS `total`
			FROM `" . DB_PREFIX . "review_request_queue`
			WHERE `date_replied` IS NOT NULL
				AND `reply_channel` <> ''" . $date_replied_filter . "
			GROUP BY `reply_channel`");

		foreach ($query->rows as $row) {
			$totals[$row['reply_channel']] = (int)$row['total'];
		}

		return $totals;
	}

	private function getTrackedReplyChannels() {
		return array('google', 'yandex');
	}

	private function getReviewRequestForBonus($review_request_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "review_request_queue`
			WHERE `review_request_id` = '" . (int)$review_request_id . "'
			LIMIT 1");

		return $query->num_rows ? $query->row : array();
	}

	private function getReviewBonusPoints() {
		$points = $this->config->get('module_review_request_review_bonus_points');

		if ($points === null || $points === '') {
			$points = 0;
		}

		$points = (int)$points;

		if ($points < 0) {
			$points = 0;
		}

		return $points;
	}

	public function needsUpgrade() {
		return (int)$this->config->get('module_review_request_schema_version') < $this->getSchemaVersion();
	}

	private function normalizeEmail($email) {
		return strtolower(trim((string)$email));
	}

	private function getExcludedCustomerGroupIds() {
		$customer_group_ids = array_map('intval', (array)$this->config->get('module_review_request_excluded_customer_group_ids'));

		return array_values(array_filter($customer_group_ids));
	}

	private function getSchemaVersion() {
		return 2;
	}
}
