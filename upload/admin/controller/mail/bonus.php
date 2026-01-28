<?php
/**
 * Admin Mail Controller for Bonus Manager
 * Handles email notifications for bonus operations from admin context
 */
class ControllerMailBonus extends Controller {

	/**
	 * Send email notification when bonuses are deducted (return)
	 * Called when bonuses are deducted due to product return
	 *
	 * @param array $args Arguments: [0] = customer_info, [1] = order_id, [2] = return_id, [3] = points_deducted
	 * @return void
	 */
	public function deducted($args = array()) {
		// Check if email notifications are enabled
		if (!$this->config->get('module_bonus_manager_email_deducted_status')) {
			return;
		}

		// Extract arguments
		if (!isset($args[0]) || !isset($args[1]) || !isset($args[2]) || !isset($args[3])) {
			$this->log->write('BONUS MAIL: Missing arguments for deducted notification');
			return;
		}

		$customer_info = $args[0];
		$order_id = $args[1];
		$return_id = $args[2];
		$points_deducted = $args[3];

		// Don't send if no points were deducted
		if ($points_deducted <= 0) {
			return;
		}

		if (!$customer_info || !$customer_info['email']) {
			return;
		}

		// Get current bonus balance (excluding expired)
		$this->load->model('customer/customer');
		$current_balance = $this->model_customer_customer->getRewardTotal($customer_info['customer_id']);

		// Get store info
		$store_name = $this->config->get('config_name');
		$store_url = HTTP_CATALOG;

		// Prepare data
		$data = array(
			'customer_firstname' => $customer_info['firstname'],
			'customer_lastname' => $customer_info['lastname'],
			'order_id' => $order_id,
			'return_id' => $return_id,
			'points_deducted' => number_format($points_deducted, 0, '.', ' '),
			'current_balance' => number_format($current_balance, 0, '.', ' '),
			'store_name' => $store_name,
			'store_url' => $store_url,
			'date_deducted' => date('d.m.Y H:i'),
			'account_url' => $store_url . 'index.php?route=account/account'
		);

		// Get templates
		$subject_template = $this->config->get('module_bonus_manager_email_deducted_subject');
		if (!$subject_template) {
			$subject_template = 'Бонусные баллы списаны - {store_name}';
		}

		$body_template = $this->config->get('module_bonus_manager_email_deducted_body');
		if (!$body_template) {
			// Get default template from bonus_manager controller (single source of truth)
			$body_template = $this->load->controller('extension/module/bonus_manager/getDefaultDeductedTemplate');
		}

		$subject = $this->replacePlaceholders($subject_template, $data);
		$body = $this->replacePlaceholders($body_template, $data);

		// Get from email
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

			$this->log->write('BONUS: Deduction notification sent to ' . $customer_info['email'] . ' for return #' . $return_id);
		} catch (Exception $e) {
			$this->log->write('BONUS: Deduction notification failed: ' . $e->getMessage());
		}
	}

	/**
	 * Send expiration warning email to customer
	 * Called by cron job when bonuses are about to expire
	 *
	 * This method handles email notifications for customers whose bonuses are expiring soon.
	 * It supports both simple placeholder syntax ({var}) and Twig syntax ({{ var }}, {% if %}).
	 *
	 * Scope: Called from admin/controller/extension/module/bonus_manager::cron() method
	 * via $this->load->controller('mail/bonus/expiring', $customer_data, $days)
	 *
	 * @param array $args Array containing:
	 *                     [0] = customer_data - Customer and bonus information from getExpiringBonuses():
	 *                           - customer_info: array with customer_id, firstname, lastname, email
	 *                           - expiring_points: int total points expiring
	 *                           - days_left: int days until expiration
	 *                           - expiration_date: string formatted date
	 *                           - reward_ids: array of reward IDs being warned about
	 *                     [1] = days - Warning period in days (e.g., 90, 30, 7)
	 * @return bool True if email sent successfully
	 */
	public function expiring($args) {
		// Extract arguments (OpenCart loader passes data as array)
		$customer_data = isset($args[0]) ? $args[0] : array();
		$days = isset($args[1]) ? (int)$args[1] : 0;
		// Check if email notifications are enabled
		if (!$this->config->get('module_bonus_manager_email_expiring_status')) {
			return false;
		}

		// Validate required data
		if (!isset($customer_data['customer_info']) || !$customer_data['customer_info']['email']) {
			$this->log->write('BONUS MAIL: Missing customer info for expiring notification');
			return false;
		}

		if ($customer_data['expiring_points'] <= 0) {
			return false;
		}

		$customer_info = $customer_data['customer_info'];

		// Get current bonus balance (excluding expired)
		$this->load->model('customer/customer');
		$current_balance = $this->model_customer_customer->getRewardTotal($customer_info['customer_id']);

		// Get store info
		$store_name = $this->config->get('config_name') ?: 'UniqSport';
		$store_url = defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER;

		// Prepare data for template
		$data = array(
			'customer_firstname' => $customer_info['firstname'],
			'customer_lastname' => $customer_info['lastname'],
			'expiring_points' => number_format($customer_data['expiring_points'], 0, '.', ' '),
			'days_left' => $customer_data['days_left'],
			'expiration_date' => $customer_data['expiration_date'],
			'current_balance' => number_format($current_balance, 0, '.', ' '),
			'store_name' => $store_name,
			'store_url' => $store_url,
			'account_url' => $store_url . 'index.php?route=account/account'
		);

		// Get templates from configuration
		$subject_template = $this->config->get('module_bonus_manager_email_expiring_subject');
		if (!$subject_template) {
			$subject_template = 'Ваши бонусы скоро сгорят!';
		}

		$body_template = $this->config->get('module_bonus_manager_email_expiring_body');
		if (!$body_template) {
			// Get default template from bonus_manager controller (single source of truth)
			$body_template = $this->load->controller('extension/module/bonus_manager/getDefaultExpiringTemplate');
		}

		// Decode HTML entities in templates (they may be stored encoded in database)
		$subject_template = html_entity_decode($subject_template, ENT_QUOTES, 'UTF-8');
		$body_template = html_entity_decode($body_template, ENT_QUOTES, 'UTF-8');

		// Replace placeholders in subject
		$subject = $this->replacePlaceholders($subject_template, $data);

		// Render body with Twig support
		$body = $this->renderTwigTemplate($body_template, $data);

		// Get from email
		$from = $this->config->get('config_email');

		// Send email
		try {
			$mail = new Mail($this->config->get('config_mail_engine') ?: 'mail');
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

			$this->log->write('Sent expiring warning to ' . $customer_info['email'] .
				' (' . $customer_data['expiring_points'] . ' points, ' . $customer_data['days_left'] . ' days)');

			return true;

		} catch (Exception $e) {
			$this->log->write('Failed to send warning to ' . $customer_info['email'] . ': ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Send birthday bonus email to customer
	 * Called by cron job when birthday bonus is awarded
	 *
	 * This method handles email notifications for customers receiving birthday bonuses.
	 * Uses the same placeholder and Twig rendering system as other bonus emails.
	 *
	 * Scope: Called from admin/controller/extension/module/bonus_manager::processBirthdayBonuses()
	 *
	 * @param array $args Array containing:
	 *                     [0] = customer_info - Customer data array with customer_id, firstname, lastname, email
	 *                     [1] = bonus_amount - Birthday bonus amount awarded
	 * @return bool True if email sent successfully
	 */
	public function birthday($args) {
		// Extract arguments
		$customer_info = isset($args[0]) ? $args[0] : array();
		$bonus_amount = isset($args[1]) ? (int)$args[1] : 0;

		// Check if email notifications are enabled
		if (!$this->config->get('module_bonus_manager_email_birthday_status')) {
			return false;
		}

		// Validate required data
		if (!isset($customer_info['email']) || !$customer_info['email']) {
			$this->log->write('BONUS MAIL: Missing customer email for birthday notification');
			return false;
		}

		if ($bonus_amount <= 0) {
			return false;
		}

		// Get current bonus balance
		$this->load->model('customer/customer');
		$current_balance = $this->model_customer_customer->getRewardTotal($customer_info['customer_id']);

		// Calculate expiration date
		$expiration_days = (int)$this->config->get('module_bonus_manager_expiration_days');
		if ($expiration_days <= 0) {
			$expiration_days = 365;
		}
		$expiration_date = date('d.m.Y', strtotime('+' . $expiration_days . ' days'));

		// Get store info
		$store_name = $this->config->get('config_name') ?: 'UniqSport';
		$store_url = defined('HTTP_CATALOG') ? HTTP_CATALOG : HTTP_SERVER;

		// Prepare data for template
		$data = array(
			'customer_firstname' => $customer_info['firstname'],
			'customer_lastname' => $customer_info['lastname'],
			'birthday_bonus' => number_format($bonus_amount, 0, '.', ' '),
			'current_balance' => number_format($current_balance, 0, '.', ' '),
			'expiration_date' => $expiration_date,
			'store_name' => $store_name,
			'store_url' => $store_url,
			'account_url' => $store_url . 'index.php?route=account/account'
		);

		// Get templates from configuration
		$subject_template = $this->config->get('module_bonus_manager_email_birthday_subject');
		if (!$subject_template) {
			$subject_template = 'С Днём рождения, {customer_firstname}! Вам подарок от {store_name}';
		}

		$body_template = $this->config->get('module_bonus_manager_email_birthday_body');
		if (!$body_template) {
			// Get default template from bonus_manager controller (single source of truth)
			$body_template = $this->load->controller('extension/module/bonus_manager/getDefaultBirthdayTemplate');
		}

		// Decode HTML entities in templates
		$subject_template = html_entity_decode($subject_template, ENT_QUOTES, 'UTF-8');
		$body_template = html_entity_decode($body_template, ENT_QUOTES, 'UTF-8');

		// Replace placeholders
		$subject = $this->replacePlaceholders($subject_template, $data);
		$body = $this->renderTwigTemplate($body_template, $data);

		// Get from email
		$from = $this->config->get('config_email');

		// Send email
		try {
			$mail = new Mail($this->config->get('config_mail_engine') ?: 'mail');
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

			$this->log->write('BONUS: Birthday notification sent to ' . $customer_info['email'] .
				' (bonus: ' . $bonus_amount . ' points)');

			return true;

		} catch (Exception $e) {
			$this->log->write('BONUS: Birthday notification failed: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Replace placeholders in template
	 *
	 * Replaces {placeholder} syntax with actual values from data array.
	 *
	 * @param string $template Template string with placeholders
	 * @param array $data Data for replacement
	 * @return string Processed template
	 */
	private function replacePlaceholders($template, $data) {
		foreach ($data as $key => $value) {
			$template = str_replace('{' . $key . '}', $value, $template);
		}
		return $template;
	}

	/**
	 * Escape data for Twig template rendering
	 *
	 * Escapes special Twig characters that could break parsing,
	 * particularly the pipe character which is the Twig filter operator.
	 *
	 * @param array $data Template variables
	 * @return array Escaped data safe for Twig rendering
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
	 *
	 * Supports both simple placeholders ({var}) and Twig syntax ({{ var }}, {% if %}).
	 * Twig is rendered first, then simple placeholders are replaced.
	 *
	 * @param string $template Template string
	 * @param array $data Template variables
	 * @return string Rendered template
	 */
	private function renderTwigTemplate($template, $data) {
		// If template contains Twig syntax, render with Twig FIRST (before placeholder replacement)
		if (strpos($template, '{%') !== false || strpos($template, '{{') !== false) {
			try {
				if (class_exists('\Twig\Loader\ArrayLoader')) {
					// Create a temporary Twig environment
					$loader = new \Twig\Loader\ArrayLoader([
						'template' => $template
					]);

					$twig = new \Twig\Environment($loader, [
						'autoescape' => false // Allow HTML in templates
					]);

					// Escape special Twig characters in string data
					$twig_data = $this->escapeTwigData($data);

					// Render Twig with escaped data
					$template = $twig->render('template', $twig_data);
				}
			} catch (Exception $e) {
				// If Twig rendering fails, log and continue with simple placeholders
				$this->log->write('BONUS: Twig rendering failed: ' . $e->getMessage());
			}
		}

		// Then replace simple placeholders
		return $this->replacePlaceholders($template, $data);
	}
}
