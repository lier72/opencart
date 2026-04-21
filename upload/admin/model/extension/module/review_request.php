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
			'module_review_request_include_product_reviews' => 1,
			'module_review_request_org_review_cooldown_days' => 180,
			'module_review_request_org_review_suppressed_mode' => 'product_only',
			'module_review_request_track_review_clicks' => 1,
			'module_review_request_email_subject' => '',
			'module_review_request_email_body' => '',
			'module_review_request_google_status' => 0,
			'module_review_request_google_reference' => '',
			'module_review_request_google_review_url' => '',
			'module_review_request_google_widget_code' => '',
			'module_review_request_yandex_status' => 0,
			'module_review_request_yandex_reference' => '',
			'module_review_request_yandex_review_url' => '',
			'module_review_request_yandex_widget_code' => ''
		);

		$this->model_setting_setting->editSetting('module_review_request', array_merge($defaults, $current_settings));
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->load->model('setting/setting');

		$this->model_setting_event->deleteEventByCode('review_request_queue');
		$this->model_setting_setting->deleteSetting('module_review_request');
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "review_request_customer`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "review_request_queue`");
	}

	public function ensureSchema() {
		if ($this->schema_checked) {
			return;
		}

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

		$this->schema_checked = true;
	}

	public function getDueRequests($limit = 50) {
		$this->ensureSchema();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "review_request_queue`
			WHERE `status` = 'pending' AND `date_send_after` <= NOW()
			ORDER BY `review_request_id` ASC
			LIMIT " . (int)$limit);

		return $query->rows;
	}

	public function markSent($review_request_id) {
		$this->ensureSchema();

		$this->db->query("UPDATE `" . DB_PREFIX . "review_request_queue`
			SET `status` = 'sent',
				`send_attempts` = `send_attempts` + 1,
				`last_error` = '',
				`date_sent` = NOW(),
				`date_modified` = NOW()
			WHERE `review_request_id` = '" . (int)$review_request_id . "'");
	}

	public function markSkipped($review_request_id, $reason) {
		$this->ensureSchema();

		$this->db->query("UPDATE `" . DB_PREFIX . "review_request_queue`
			SET `status` = 'sent',
				`last_error` = '" . $this->db->escape('Skipped: ' . $reason) . "',
				`date_sent` = NOW(),
				`date_modified` = NOW()
			WHERE `review_request_id` = '" . (int)$review_request_id . "'");
	}

	public function markRetry($review_request_id, $error) {
		$this->ensureSchema();

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
		$this->ensureSchema();

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

	public function markOrganizationReviewSent($email, $customer_id, $order_id) {
		$this->ensureSchema();

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

	private function normalizeEmail($email) {
		return strtolower(trim((string)$email));
	}
}
