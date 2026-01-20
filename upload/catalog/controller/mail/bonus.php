<?php
class ControllerMailBonus extends Controller {
	/**
	 * Event handler for bonus awarded notification
	 * Triggered when bonuses are awarded to customer
	 */
	public function awarded($args) {
		// Debug logging
		$this->log->write('BONUS MAIL: awarded() method called');

		// Check if email notifications are enabled
		if (!$this->config->get('module_bonus_manager_email_awarded_status')) {
			$this->log->write('BONUS MAIL: Email notifications disabled');
			return;
		}

		// Extract arguments from event trigger
		if (isset($args[0])) {
			$order_info = $args[0];
		} else {
			$this->log->write('BONUS MAIL: No order_info in args');
			return;
		}

		if (isset($args[1])) {
			$bonus_amount = $args[1];
		} else {
			$this->log->write('BONUS MAIL: No bonus_amount in args');
			return;
		}

		$this->log->write('BONUS MAIL: Processing order #' . $order_info['order_id'] . ', bonus amount: ' . $bonus_amount);

		// Don't send email if no bonus was awarded
		if ($bonus_amount <= 0) {
			return;
		}

		// Check if order has customer email (required for sending)
		if (!$order_info['email']) {
			$this->log->write('BONUS MAIL: No email in order_info');
			return;
		}

		// Get current bonus balance (excluding expired)
		$this->load->model('account/customer');
		$current_balance = $this->model_account_customer->getRewardTotal($order_info['customer_id']);
//		Refactored to use model method
//		$query = $this->db->query("SELECT SUM(points) as total FROM " . DB_PREFIX . "customer_reward
//			WHERE customer_id = '" . (int)$order_info['customer_id'] . "'
//			AND (date_expires IS NULL OR date_expires > NOW())");
//		$current_balance = (int)$query->row['total'];

		// Load language for email
		$language = new Language($order_info['language_code']);
		$language->load($order_info['language_code']);

		// Get max usage percentage
		$max_usage_percent = (float)$this->config->get('module_bonus_manager_max_usage_percent') ?: 30;

		// Prepare data for template
		$data = array(
			'customer_firstname' => $order_info['firstname'],
			'customer_lastname' => $order_info['lastname'],
			'order_id' => $order_info['order_id'],
			'bonus_amount' => number_format($bonus_amount, 0, '.', ' '),
			'current_balance' => number_format($current_balance, 0, '.', ' '),
			'max_usage_percent' => $max_usage_percent,
			'store_name' => $order_info['store_name'],
			'store_url' => $order_info['store_url'],
			'date_awarded' => date('d.m.Y H:i'),
			'account_url' => $order_info['store_url'] . 'index.php?route=account/account',
			'order_url' => $order_info['store_url'] . 'index.php?route=account/order/info&order_id=' . $order_info['order_id']
		);

		// Get subject and body templates from configuration (set via admin)
		$subject_template = $this->config->get('module_bonus_manager_email_awarded_subject');
		if (!$subject_template) {
			$subject_template = 'Вам начислены бонусы за заказ #{order_id}';
		}

		$body_template = $this->config->get('module_bonus_manager_email_awarded_body');
		if (!$body_template) {
			$this->log->write('BONUS MAIL ERROR: Email awarded body template not found in database. Please configure in Admin > Extensions > Modules > Bonus Manager > Notifications tab.');
			return;
		}

		// Replace placeholders in subject and body
		$subject = $this->replacePlaceholders($subject_template, $data);
		$body = $this->replacePlaceholders($body_template, $data);

		// Load settings
		$this->load->model('setting/setting');

		$from = $this->model_setting_setting->getSettingValue('config_email', $order_info['store_id']);

		if (!$from) {
			$from = $this->config->get('config_email');
		}

		// Send email using OpenCart's Mail class
		try {
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($order_info['email']);
			$mail->setFrom($from);
			$mail->setSender(html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
			$mail->setHtml(html_entity_decode($body, ENT_QUOTES, 'UTF-8'));
			$mail->send();

			$this->log->write('BONUS: Email notification sent to ' . $order_info['email'] . ' for order #' . $order_info['order_id']);
		} catch (Exception $e) {
			$this->log->write('BONUS: Email notification failed: ' . $e->getMessage());
		}
	}

	/**
	 * Send email notification when bonuses are spent
	 * Called directly when customer uses bonuses
	 */
	public function spent($args = array()) {
		// Check if email notifications are enabled
		if (!$this->config->get('module_bonus_manager_email_spent_status')) {
			return;
		}

		// Extract arguments
		if (isset($args[0])) {
			$order_info = $args[0];
		} else {
			return;
		}

		if (isset($args[1])) {
			$points_spent = $args[1];
		} else {
			return;
		}

		// Don't send if no points were spent
		if ($points_spent <= 0) {
			return;
		}

		// Check if order has customer email (required for sending)
		if (!$order_info['email']) {
			return;
		}

		// Get current bonus balance (excluding expired)
		$this->load->model('account/customer');
		$current_balance = $this->model_account_customer->getRewardTotal($order_info['customer_id']);
//		Refactored to use model method
//		$query = $this->db->query("SELECT SUM(points) as total FROM " . DB_PREFIX . "customer_reward
//			WHERE customer_id = '" . (int)$order_info['customer_id'] . "'
//			AND (date_expires IS NULL OR date_expires > NOW())");
//		$current_balance = (int)$query->row['total'];

		// Load language
		$language = new Language($order_info['language_code']);
		$language->load($order_info['language_code']);

		// Prepare data
		$data = array(
			'customer_firstname' => $order_info['firstname'],
			'customer_lastname' => $order_info['lastname'],
			'order_id' => $order_info['order_id'],
			'points_spent' => number_format(abs($points_spent), 0, '.', ' '),
			'current_balance' => number_format($current_balance, 0, '.', ' '),
			'store_name' => $order_info['store_name'],
			'store_url' => $order_info['store_url'],
			'date_spent' => date('d.m.Y H:i'),
			'account_url' => $order_info['store_url'] . 'index.php?route=account/account',
			'order_url' => $order_info['store_url'] . 'index.php?route=account/order/info&order_id=' . $order_info['order_id']
		);

		// Get templates from configuration (set via admin)
		$subject_template = $this->config->get('module_bonus_manager_email_spent_subject');
		if (!$subject_template) {
			$subject_template = 'Списаны бонусы за заказ #{order_id}';
		}

		$body_template = $this->config->get('module_bonus_manager_email_spent_body');
		if (!$body_template) {
			$this->log->write('BONUS MAIL ERROR: Email spent body template not found in database. Please configure in Admin > Extensions > Modules > Bonus Manager > Notifications tab.');
			return;
		}

		$subject = $this->replacePlaceholders($subject_template, $data);
		$body = $this->replacePlaceholders($body_template, $data);

		// Load settings
		$this->load->model('setting/setting');
		$from = $this->model_setting_setting->getSettingValue('config_email', $order_info['store_id']);
		if (!$from) {
			$from = $this->config->get('config_email');
		}

		// Send email
		try {
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($order_info['email']);
			$mail->setFrom($from);
			$mail->setSender(html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
			$mail->setHtml(html_entity_decode($body, ENT_QUOTES, 'UTF-8'));
			$mail->send();

			$this->log->write('BONUS: Spent notification sent to ' . $order_info['email'] . ' for order #' . $order_info['order_id']);
		} catch (Exception $e) {
			$this->log->write('BONUS: Spent notification failed: ' . $e->getMessage());
		}
	}

	/**
	 * Replace placeholders in template string
	 */
	private function replacePlaceholders($template, $data) {
		foreach ($data as $key => $value) {
			$template = str_replace('{' . $key . '}', $value, $template);
		}
		return $template;
	}

	/**
	 * Send expiration warning email to customer
	 * Called by cron job when bonuses are about to expire
	 *
	 * @param array $customer_info Customer data
	 * @param int $expiring_points Amount of points expiring
	 * @param int $days_left Days until expiration
	 * @param string $expiration_date Date when points expire
	 */
	public function expiring($customer_info, $expiring_points, $days_left, $expiration_date) {
		// Check if email notifications are enabled
		if (!$this->config->get('module_bonus_manager_email_expiring_status')) {
			return;
		}

		// Validate required data
		if (!$customer_info || !$customer_info['email'] || $expiring_points <= 0) {
			return;
		}

		// Get current bonus balance (excluding expired)
		$this->load->model('account/customer');
		$current_balance = $this->model_account_customer->getRewardTotal($customer_info['customer_id']);
//		Refactored to use model method
		// $query = $this->db->query("SELECT SUM(points) as total FROM " . DB_PREFIX . "customer_reward
		// 	WHERE customer_id = '" . (int)$customer_info['customer_id'] . "'
		// 	AND (date_expires IS NULL OR date_expires > NOW())");
		// $current_balance = (int)$query->row['total'];

		// Get store info 
		// There is no getStore method, only getStores
		//$this->load->model('setting/store');
		//$store_info = $this->model_setting_store->getStore(0); // Default store

		$store_name = $this->config->get('config_name');
		$store_url = HTTP_SERVER;

		// Prepare data for template
		$data = array(
			'customer_firstname' => $customer_info['firstname'],
			'customer_lastname' => $customer_info['lastname'],
			'expiring_points' => number_format($expiring_points, 0, '.', ' '),
			'days_left' => $days_left,
			'expiration_date' => $expiration_date,
			'current_balance' => number_format($current_balance, 0, '.', ' '),
			'store_name' => $store_name,
			'store_url' => $store_url,
			'account_url' => $store_url . 'index.php?route=account/account'
		);

		// Get templates from configuration (set via admin)
		$subject_template = $this->config->get('module_bonus_manager_email_expiring_subject');
		if (!$subject_template) {
			$subject_template = 'Ваши бонусы скоро сгорят!';
		}

		$body_template = $this->config->get('module_bonus_manager_email_expiring_body');
		if (!$body_template) {
			$this->log->write('BONUS MAIL ERROR: Email expiring body template not found in database. Please configure in Admin > Extensions > Modules > Bonus Manager > Notifications tab.');
			return;
		}

		// Decode HTML entities in templates (they may be stored encoded in database)
		$subject_template = html_entity_decode($subject_template, ENT_QUOTES, 'UTF-8');
		$body_template = html_entity_decode($body_template, ENT_QUOTES, 'UTF-8');

		// Replace simple placeholders in subject
		$subject = $this->replacePlaceholders($subject_template, $data);

		// Render body with Twig support
		$body = $this->renderTwigTemplate($body_template, $data);

		// Get from email
		$this->load->model('setting/setting');
		$from = $this->config->get('config_email');

		// Send email
		try {
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($customer_info['email']);
			$mail->setFrom($from);
			$mail->setSender(html_entity_decode($store_name, ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
			$mail->setHtml(html_entity_decode($body, ENT_QUOTES, 'UTF-8'));
			$mail->send();

			$this->log->write('BONUS: Expiring notification sent to ' . $customer_info['email'] . ' (' . $expiring_points . ' points, ' . $days_left . ' days)');
		} catch (Exception $e) {
			$this->log->write('BONUS: Expiring notification failed: ' . $e->getMessage());
		}
	}

	/**
	 * Escape data for Twig template rendering
	 * Escapes special Twig characters that could break parsing
	 */
	private function escapeTwigData($data) {
		$escaped = array();
		foreach ($data as $key => $value) {
			if (is_string($value)) {
				// First decode any HTML entities that might be in the data
				$value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
				// Then escape pipe character (Twig filter operator) as HTML entity
				$value = str_replace('|', '&#124;', $value);
			}
			$escaped[$key] = $value;
		}
		return $escaped;
	}

	/**
	 * Render template with Twig support
	 * Supports both simple placeholders and Twig syntax
	 */
	private function renderTwigTemplate($template, $data) {
		// If template contains Twig syntax, render with Twig FIRST (before placeholder replacement)
		if (strpos($template, '{%') !== false || strpos($template, '{{') !== false) {
			try {
				// Create a temporary Twig environment
				$loader = new \Twig\Loader\ArrayLoader([
					'template' => $template
				]);

				$twig = new \Twig\Environment($loader, [
					'autoescape' => false // Allow HTML in templates
				]);

				// Escape special Twig characters in string data
				$twig_data = $this->escapeTwigData($data);

				// Render Twig with escaped data (keep days_left as integer for logic)
				$template = $twig->render('template', $twig_data);

				$this->log->write('BONUS: Twig rendering successful');
			} catch (Exception $e) {
				// If Twig rendering fails, log and return simple placeholder version
				$this->log->write('BONUS: Twig rendering failed: ' . $e->getMessage());
				// Continue with template as-is, will replace placeholders below
			}
		}

		// Then replace simple placeholders (for formatted values)
		$rendered = $this->replacePlaceholders($template, $data);

		return $rendered;
	}

	/**
	 * Send loyalty level upgrade email to customer
	 * Called when customer's loyalty level is upgraded based on total spent
	 *
	 * This method handles email notifications when a customer's loyalty level changes.
	 * It should be called from the bonus_manager model after a customer is upgraded/downgraded
	 * via the checkAndUpgradeCustomer() method.
	 *
	 * Scope: This method should be invoked after a successful loyalty level change in the
	 * catalog/model/extension/module/bonus_manager.php::checkAndUpgradeCustomer() method
	 * to notify the customer about their new loyalty status and benefits.
	 *
	 * @param array $args Arguments array containing:
	 *                    [0] = customer_info (array with customer_id, firstname, lastname, email, language_code)
	 *                    [1] = old_group_name (string) - previous loyalty level name
	 *                    [2] = new_group_name (string) - new loyalty level name
	 *                    [3] = new_bonus_percent (float) - new bonus percentage for this level
	 *                    [4] = total_spent (float) - customer's total spent that qualified them for upgrade
	 * @return void
	 */
	public function loyaltyUpgrade($args = array()) {
		// Check if email notifications are enabled for loyalty upgrades
		if (!$this->config->get('module_bonus_manager_email_loyalty_upgrade_status')) {
			$this->log->write('BONUS MAIL: Loyalty upgrade email notifications disabled');
			return;
		}

		// Validate required arguments
		if (!isset($args[0]) || !isset($args[1]) || !isset($args[2]) || !isset($args[3]) || !isset($args[4])) {
			$this->log->write('BONUS MAIL: Missing arguments for loyalty upgrade notification');
			return;
		}

		$customer_info = $args[0];
		$old_group_name = $args[1];
		$new_group_name = $args[2];
		$new_bonus_percent = (float)$args[3];
		$total_spent = (float)$args[4];

		// Validate customer info
		if (!$customer_info || !isset($customer_info['email']) || !$customer_info['email']) {
			$this->log->write('BONUS MAIL: No email in customer_info for loyalty upgrade');
			return;
		}

		// Get current bonus balance (excluding expired)
		$this->load->model('account/customer');
		$current_balance = $this->model_account_customer->getRewardTotal($customer_info['customer_id']);

		$store_name = $this->config->get('config_name');
		$store_url = HTTP_SERVER;

		// Prepare data for template
		$data = array(
			'customer_firstname' => $customer_info['firstname'],
			'customer_lastname' => $customer_info['lastname'],
			'old_group_name' => $old_group_name,
			'new_group_name' => $new_group_name,
			'new_bonus_percent' => number_format($new_bonus_percent, 1, '.', ''),
			'total_spent' => number_format($total_spent, 0, '.', ' '),
			'current_balance' => number_format($current_balance, 0, '.', ' '),
			'store_name' => $store_name,
			'store_url' => $store_url,
			'account_url' => $store_url . 'index.php?route=account/account',
			'date_upgraded' => date('d.m.Y H:i')
		);

		// Get templates from configuration (set via admin)
		$subject_template = $this->config->get('module_bonus_manager_email_loyalty_upgrade_subject');
		if (!$subject_template) {
			$subject_template = 'Поздравляем! Ваш уровень лояльности повышен до {new_group_name}';
		}

		$body_template = $this->config->get('module_bonus_manager_email_loyalty_upgrade_body');
		if (!$body_template) {
			$this->log->write('BONUS MAIL ERROR: Email loyalty upgrade body template not found in database. Please configure in Admin > Extensions > Modules > Bonus Manager > Notifications tab.');
			return;
		}

		// Decode HTML entities in templates (they may be stored encoded in database)
		$subject_template = html_entity_decode($subject_template, ENT_QUOTES, 'UTF-8');
		$body_template = html_entity_decode($body_template, ENT_QUOTES, 'UTF-8');

		// Replace placeholders in subject
		$subject = $this->replacePlaceholders($subject_template, $data);

		// Render body with Twig support
		$body = $this->renderTwigTemplate($body_template, $data);

		// Get from email
		$this->load->model('setting/setting');
		$from = $this->config->get('config_email');

		// Send email
		try {
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($customer_info['email']);
			$mail->setFrom($from);
			$mail->setSender(html_entity_decode($store_name, ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
			$mail->setHtml(html_entity_decode($body, ENT_QUOTES, 'UTF-8'));
			$mail->send();

			$this->log->write('BONUS: Loyalty upgrade notification sent to ' . $customer_info['email'] . ' (from "' . $old_group_name . '" to "' . $new_group_name . '")');
		} catch (Exception $e) {
			$this->log->write('BONUS: Loyalty upgrade notification failed: ' . $e->getMessage());
		}
	}
}
