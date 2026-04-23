<?php
class ControllerExtensionModuleBonusManager extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/bonus_manager');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/module/bonus_manager');

		// Handle form submission
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			// IMPORTANT: Database uses utf8mb3 which cannot store 4-byte UTF-8 (emojis)
			// We must keep HTML entities like &#127881; in their entity form
			// Request class converts < to &lt;, > to &gt;, & to &amp;
			// This double-encodes &#127881; to &amp;#127881;
			// We need to decode HTML tags but preserve numeric HTML entities
			$post_data = $this->request->post;
			$html_fields = array(
				'module_bonus_manager_email_awarded_body',
				'module_bonus_manager_email_spent_body',
				'module_bonus_manager_email_deducted_body',
				'module_bonus_manager_email_expiring_body',
				'module_bonus_manager_email_loyalty_upgrade_body',
				'module_bonus_manager_email_birthday_body'
			);

			foreach ($html_fields as $field) {
				if (isset($post_data[$field])) {
					// Decode HTML tags but keep numeric entities as entities
					// Full decode would convert &#127881; to 🎉 which breaks utf8mb3
					$decoded = html_entity_decode($post_data[$field], ENT_QUOTES, 'UTF-8');
					// Re-encode any Unicode emojis back to HTML entities
					$post_data[$field] = mb_encode_numericentity($decoded, [0x10000, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8');
				}
			}

			$this->model_setting_setting->editSetting('module_bonus_manager', $post_data);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		// Load language variables
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_discount_threshold'] = $this->language->get('entry_discount_threshold');
		$data['entry_max_usage_percent'] = $this->language->get('entry_max_usage_percent');
		$data['entry_excluded_categories'] = $this->language->get('entry_excluded_categories');
		$data['entry_accrual_status'] = $this->language->get('entry_accrual_status');
		$data['entry_return_deduction_status'] = $this->language->get('entry_return_deduction_status');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['tab_general'] = $this->language->get('tab_general');
		$data['tab_bonus_settings'] = $this->language->get('tab_bonus_settings');
		$data['tab_loyalty_levels'] = 'Loyalty Levels'; // Add to language file
		$data['tab_notifications'] = $this->language->get('tab_notifications');
		$data['entry_notification_email'] = $this->language->get('entry_notification_email');
		$data['entry_email_subject'] = $this->language->get('entry_email_subject');
		$data['entry_email_body'] = $this->language->get('entry_email_body');
		$data['help_notification_email'] = $this->language->get('help_notification_email');
		$data['help_email_subject'] = $this->language->get('help_email_subject');
		$data['help_email_body'] = $this->language->get('help_email_body');

		// Error handling
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		// Success message
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		// Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/bonus_manager', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/bonus_manager', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		// Module status
		if (isset($this->request->post['module_bonus_manager_status'])) {
			$data['module_bonus_manager_status'] = $this->request->post['module_bonus_manager_status'];
		} else {
			$data['module_bonus_manager_status'] = $this->config->get('module_bonus_manager_status');
		}

		// Discount threshold
		if (isset($this->request->post['module_bonus_manager_discount_threshold'])) {
			$data['module_bonus_manager_discount_threshold'] = $this->request->post['module_bonus_manager_discount_threshold'];
		} else {
			$data['module_bonus_manager_discount_threshold'] = $this->config->get('module_bonus_manager_discount_threshold') ?: 15;
		}

		// Max bonus usage percentage
		if (isset($this->request->post['module_bonus_manager_max_usage_percent'])) {
			$data['module_bonus_manager_max_usage_percent'] = $this->request->post['module_bonus_manager_max_usage_percent'];
		} else {
			$data['module_bonus_manager_max_usage_percent'] = $this->config->get('module_bonus_manager_max_usage_percent') ?: 30;
		}

		// Bonus expiration days
		if (isset($this->request->post['module_bonus_manager_expiration_days'])) {
			$data['module_bonus_manager_expiration_days'] = $this->request->post['module_bonus_manager_expiration_days'];
		} else {
			$data['module_bonus_manager_expiration_days'] = $this->config->get('module_bonus_manager_expiration_days') ?: 365;
		}

		// Accrual order status
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['module_bonus_manager_accrual_status_id'])) {
			$data['module_bonus_manager_accrual_status_id'] = $this->request->post['module_bonus_manager_accrual_status_id'];
		} else {
			$data['module_bonus_manager_accrual_status_id'] = $this->config->get('module_bonus_manager_accrual_status_id') ?: 5;
		}

		// Return deduction status
		$this->load->model('localisation/return_status');
		$data['return_statuses'] = $this->model_localisation_return_status->getReturnStatuses();

		if (isset($this->request->post['module_bonus_manager_return_deduction_status_id'])) {
			$data['module_bonus_manager_return_deduction_status_id'] = $this->request->post['module_bonus_manager_return_deduction_status_id'];
		} else {
			$data['module_bonus_manager_return_deduction_status_id'] = $this->config->get('module_bonus_manager_return_deduction_status_id') ?: 3;
		}

		// Excluded categories
		$this->load->model('catalog/category');

		if (isset($this->request->post['module_bonus_manager_excluded_categories'])) {
			$categories = $this->request->post['module_bonus_manager_excluded_categories'];
		} else {
			$categories = $this->config->get('module_bonus_manager_excluded_categories');
		}

		$data['excluded_categories'] = array();

		if (!empty($categories)) {
			foreach ($categories as $category_id) {
				$category_info = $this->model_catalog_category->getCategory($category_id);

				if ($category_info) {
					$data['excluded_categories'][] = array(
						'category_id' => $category_info['category_id'],
						'name'        => $category_info['name']
					);
				}
			}
		}

		// Notification settings - Bonus Awarded Event
		if (isset($this->request->post['module_bonus_manager_email_awarded_status'])) {
			$data['module_bonus_manager_email_awarded_status'] = $this->request->post['module_bonus_manager_email_awarded_status'];
		} else {
			$data['module_bonus_manager_email_awarded_status'] = $this->config->get('module_bonus_manager_email_awarded_status');
		}

		if (isset($this->request->post['module_bonus_manager_email_awarded_subject'])) {
			$data['module_bonus_manager_email_awarded_subject'] = $this->request->post['module_bonus_manager_email_awarded_subject'];
		} else {
			$data['module_bonus_manager_email_awarded_subject'] = $this->config->get('module_bonus_manager_email_awarded_subject') ?: 'Вам начислены бонусы за заказ #{order_id}';
		}

		if (isset($this->request->post['module_bonus_manager_email_awarded_body'])) {
			$data['module_bonus_manager_email_awarded_body'] = $this->request->post['module_bonus_manager_email_awarded_body'];
		} else {
			// Load directly from DB - no decoding needed
			// HTML entities like &#127881; should stay as entities
			$data['module_bonus_manager_email_awarded_body'] = $this->config->get('module_bonus_manager_email_awarded_body') ?: $this->getDefaultAwardedTemplate();
		}

		// Notification settings - Bonus Spent Event
		if (isset($this->request->post['module_bonus_manager_email_spent_status'])) {
			$data['module_bonus_manager_email_spent_status'] = $this->request->post['module_bonus_manager_email_spent_status'];
		} else {
			$data['module_bonus_manager_email_spent_status'] = $this->config->get('module_bonus_manager_email_spent_status');
		}

		if (isset($this->request->post['module_bonus_manager_email_spent_subject'])) {
			$data['module_bonus_manager_email_spent_subject'] = $this->request->post['module_bonus_manager_email_spent_subject'];
		} else {
			$data['module_bonus_manager_email_spent_subject'] = $this->config->get('module_bonus_manager_email_spent_subject') ?: 'Списаны бонусы за заказ #{order_id}';
		}

		if (isset($this->request->post['module_bonus_manager_email_spent_body'])) {
			$data['module_bonus_manager_email_spent_body'] = $this->request->post['module_bonus_manager_email_spent_body'];
		} else {
			$data['module_bonus_manager_email_spent_body'] = $this->config->get('module_bonus_manager_email_spent_body') ?: $this->getDefaultSpentTemplate();
		}

		// Notification settings - Bonus Deducted Event
		if (isset($this->request->post['module_bonus_manager_email_deducted_status'])) {
			$data['module_bonus_manager_email_deducted_status'] = $this->request->post['module_bonus_manager_email_deducted_status'];
		} else {
			$data['module_bonus_manager_email_deducted_status'] = $this->config->get('module_bonus_manager_email_deducted_status');
		}

		if (isset($this->request->post['module_bonus_manager_email_deducted_subject'])) {
			$data['module_bonus_manager_email_deducted_subject'] = $this->request->post['module_bonus_manager_email_deducted_subject'];
		} else {
			$data['module_bonus_manager_email_deducted_subject'] = $this->config->get('module_bonus_manager_email_deducted_subject') ?: 'Бонусные баллы списаны - {store_name}';
		}

		if (isset($this->request->post['module_bonus_manager_email_deducted_body'])) {
			$data['module_bonus_manager_email_deducted_body'] = $this->request->post['module_bonus_manager_email_deducted_body'];
		} else {
			$data['module_bonus_manager_email_deducted_body'] = $this->config->get('module_bonus_manager_email_deducted_body') ?: $this->getDefaultDeductedTemplate();
		}

		// Notification settings - Bonus Expiring Warning
		if (isset($this->request->post['module_bonus_manager_expiration_warning_days'])) {
			$data['module_bonus_manager_expiration_warning_days'] = $this->request->post['module_bonus_manager_expiration_warning_days'];
		} else {
			$data['module_bonus_manager_expiration_warning_days'] = $this->config->get('module_bonus_manager_expiration_warning_days') ?: '90,30,7';
		}

		if (isset($this->request->post['module_bonus_manager_email_expiring_status'])) {
			$data['module_bonus_manager_email_expiring_status'] = $this->request->post['module_bonus_manager_email_expiring_status'];
		} else {
			$data['module_bonus_manager_email_expiring_status'] = $this->config->get('module_bonus_manager_email_expiring_status');
		}

		if (isset($this->request->post['module_bonus_manager_email_expiring_subject'])) {
			$data['module_bonus_manager_email_expiring_subject'] = $this->request->post['module_bonus_manager_email_expiring_subject'];
		} else {
			$data['module_bonus_manager_email_expiring_subject'] = $this->config->get('module_bonus_manager_email_expiring_subject') ?: 'Ваши бонусы скоро сгорят!';
		}

		if (isset($this->request->post['module_bonus_manager_email_expiring_body'])) {
			$data['module_bonus_manager_email_expiring_body'] = $this->request->post['module_bonus_manager_email_expiring_body'];
		} else {
			$data['module_bonus_manager_email_expiring_body'] = $this->config->get('module_bonus_manager_email_expiring_body') ?: $this->getDefaultExpiringTemplate();
		}

		// Notification settings - Loyalty Level Upgrade
		if (isset($this->request->post['module_bonus_manager_email_loyalty_upgrade_status'])) {
			$data['module_bonus_manager_email_loyalty_upgrade_status'] = $this->request->post['module_bonus_manager_email_loyalty_upgrade_status'];
		} else {
			$data['module_bonus_manager_email_loyalty_upgrade_status'] = $this->config->get('module_bonus_manager_email_loyalty_upgrade_status');
		}

		if (isset($this->request->post['module_bonus_manager_email_loyalty_upgrade_subject'])) {
			$data['module_bonus_manager_email_loyalty_upgrade_subject'] = $this->request->post['module_bonus_manager_email_loyalty_upgrade_subject'];
		} else {
			$data['module_bonus_manager_email_loyalty_upgrade_subject'] = $this->config->get('module_bonus_manager_email_loyalty_upgrade_subject') ?: 'Поздравляем! Ваш уровень лояльности повышен до {new_group_name}';
		}

		if (isset($this->request->post['module_bonus_manager_email_loyalty_upgrade_body'])) {
			$data['module_bonus_manager_email_loyalty_upgrade_body'] = $this->request->post['module_bonus_manager_email_loyalty_upgrade_body'];
		} else {
			$data['module_bonus_manager_email_loyalty_upgrade_body'] = $this->config->get('module_bonus_manager_email_loyalty_upgrade_body') ?: $this->getDefaultLoyaltyUpgradeTemplate();
		}

		// Birthday Bonus Settings
		if (isset($this->request->post['module_bonus_manager_birthday_bonus_amount'])) {
			$data['module_bonus_manager_birthday_bonus_amount'] = $this->request->post['module_bonus_manager_birthday_bonus_amount'];
		} else {
			$data['module_bonus_manager_birthday_bonus_amount'] = $this->config->get('module_bonus_manager_birthday_bonus_amount') ?: 500;
		}

		if (isset($this->request->post['module_bonus_manager_email_birthday_status'])) {
			$data['module_bonus_manager_email_birthday_status'] = $this->request->post['module_bonus_manager_email_birthday_status'];
		} else {
			$data['module_bonus_manager_email_birthday_status'] = $this->config->get('module_bonus_manager_email_birthday_status');
		}

		if (isset($this->request->post['module_bonus_manager_email_birthday_subject'])) {
			$data['module_bonus_manager_email_birthday_subject'] = $this->request->post['module_bonus_manager_email_birthday_subject'];
		} else {
			$data['module_bonus_manager_email_birthday_subject'] = $this->config->get('module_bonus_manager_email_birthday_subject') ?: 'С Днём рождения, {customer_firstname}! Вам подарок от {store_name}';
		}

		if (isset($this->request->post['module_bonus_manager_email_birthday_body'])) {
			$data['module_bonus_manager_email_birthday_body'] = $this->request->post['module_bonus_manager_email_birthday_body'];
		} else {
			$data['module_bonus_manager_email_birthday_body'] = $this->config->get('module_bonus_manager_email_birthday_body') ?: $this->getDefaultBirthdayTemplate();
		}

		// Registration Widget Settings
		if (isset($this->request->post['module_bonus_manager_register_widget_heading'])) {
			$data['module_bonus_manager_register_widget_heading'] = $this->request->post['module_bonus_manager_register_widget_heading'];
		} else {
			$data['module_bonus_manager_register_widget_heading'] = $this->config->get('module_bonus_manager_register_widget_heading');
		}

		if (isset($this->request->post['module_bonus_manager_register_widget_description'])) {
			$data['module_bonus_manager_register_widget_description'] = $this->request->post['module_bonus_manager_register_widget_description'];
		} else {
			$data['module_bonus_manager_register_widget_description'] = $this->config->get('module_bonus_manager_register_widget_description');
		}

		if (isset($this->request->post['module_bonus_manager_register_widget_button_text'])) {
			$data['module_bonus_manager_register_widget_button_text'] = $this->request->post['module_bonus_manager_register_widget_button_text'];
		} else {
			$data['module_bonus_manager_register_widget_button_text'] = $this->config->get('module_bonus_manager_register_widget_button_text');
		}

		if (isset($this->request->post['module_bonus_manager_register_widget_icon'])) {
			$data['module_bonus_manager_register_widget_icon'] = $this->request->post['module_bonus_manager_register_widget_icon'];
		} else {
			$data['module_bonus_manager_register_widget_icon'] = $this->config->get('module_bonus_manager_register_widget_icon') ?: 'fa fa-gift';
		}

		if (isset($this->request->post['module_bonus_manager_register_widget_show_details'])) {
			$data['module_bonus_manager_register_widget_show_details'] = $this->request->post['module_bonus_manager_register_widget_show_details'];
		} else {
			$data['module_bonus_manager_register_widget_show_details'] = $this->config->get('module_bonus_manager_register_widget_show_details');
		}

		// Get customer groups with bonus settings
		$data['customer_groups'] = $this->model_extension_module_bonus_manager->getCustomerGroups();

		// Get all bonus settings
		$data['bonus_settings'] = $this->model_extension_module_bonus_manager->getAllBonusSettings();

		// Loyalty Levels Settings
		if (isset($this->request->post['module_bonus_manager_loyalty_status'])) {
			$data['module_bonus_manager_loyalty_status'] = $this->request->post['module_bonus_manager_loyalty_status'];
		} else {
			$data['module_bonus_manager_loyalty_status'] = $this->config->get('module_bonus_manager_loyalty_status');
		}

		if (isset($this->request->post['module_bonus_manager_loyalty_info_id'])) {
			$data['module_bonus_manager_loyalty_info_id'] = $this->request->post['module_bonus_manager_loyalty_info_id'];
		} else {
			$data['module_bonus_manager_loyalty_info_id'] = $this->config->get('module_bonus_manager_loyalty_info_id');
		}

		if (isset($this->request->post['module_bonus_manager_loyalty_period_start'])) {
			$data['module_bonus_manager_loyalty_period_start'] = $this->request->post['module_bonus_manager_loyalty_period_start'];
		} else {
			$data['module_bonus_manager_loyalty_period_start'] = $this->config->get('module_bonus_manager_loyalty_period_start') ?: '01-01';
		}

		// Get loyalty levels as array for display
		$loyalty_levels_data = isset($this->request->post['module_bonus_manager_loyalty_levels'])
			? $this->request->post['module_bonus_manager_loyalty_levels']
			: $this->config->get('module_bonus_manager_loyalty_levels');

		$data['loyalty_levels'] = array();
		if ($loyalty_levels_data) {
			// If it's already an array (from config with serialized=1 or from POST), use it
			if (is_array($loyalty_levels_data)) {
				$levels = $loyalty_levels_data;
			} else {
				// If it's a JSON string (old format with serialized=0), decode it
				$levels = json_decode($loyalty_levels_data, true);
			}

			if (is_array($levels)) {
				// Sort by min_total_spent for display
				usort($levels, function($a, $b) {
					return $a['min_total_spent'] - $b['min_total_spent'];
				});
				$data['loyalty_levels'] = $levels;
			}
		}

		// Get information pages for loyalty info page selection
		$this->load->model('catalog/information');
		$data['information_pages'] = $this->model_catalog_information->getInformations();

		// Help texts
		$data['help_status'] = $this->language->get('help_status');
		$data['help_discount_threshold'] = $this->language->get('help_discount_threshold');
		$data['help_max_usage_percent'] = $this->language->get('help_max_usage_percent');
		$data['help_excluded_categories'] = $this->language->get('help_excluded_categories');
		$data['help_accrual_status'] = $this->language->get('help_accrual_status');
		$data['help_return_deduction_status'] = $this->language->get('help_return_deduction_status');
		$data['help_register_widget'] = $this->language->get('help_register_widget');
		$data['help_register_widget_icon'] = $this->language->get('help_register_widget_icon');
		$data['help_register_widget_show_details'] = $this->language->get('help_register_widget_show_details');

		// Entry labels for registration widget
		$data['entry_register_widget_heading'] = $this->language->get('entry_register_widget_heading');
		$data['entry_register_widget_title'] = $this->language->get('entry_register_widget_title');
		$data['entry_register_widget_description'] = $this->language->get('entry_register_widget_description');
		$data['entry_register_widget_button_text'] = $this->language->get('entry_register_widget_button_text');
		$data['entry_register_widget_icon'] = $this->language->get('entry_register_widget_icon');
		$data['entry_register_widget_show_details'] = $this->language->get('entry_register_widget_show_details');

		// Entry labels for loyalty levels
		$data['entry_loyalty_status'] = 'Enable Automatic Loyalty Upgrades';
		$data['entry_loyalty_info_page'] = 'Loyalty Program Information Page';
		$data['entry_loyalty_period_start'] = 'Program Period Start Date';
		$data['entry_loyalty_levels'] = 'Loyalty Level Thresholds';
		$data['entry_customer_group'] = 'Customer Group';
		$data['entry_min_total_spent'] = 'Minimum Total Spent (₽)';
		$data['text_add_level'] = 'Add Level';
		$data['help_loyalty_status'] = 'Automatically upgrade customers to better pricing groups when they reach spending thresholds';
		$data['help_loyalty_period_start'] = 'Format: MM-DD (e.g., 01-01 for January 1st). Program period runs for 1 year from this date.';
		$data['help_loyalty_levels'] = 'Define spending thresholds for each customer group. Customers will be automatically upgraded when they reach the threshold within the current program period.';

		// Default text for registration widget
		$data['text_register_widget_heading_default'] = $this->language->get('text_register_widget_heading_default');
		$data['text_register_widget_description_default'] = $this->language->get('text_register_widget_description_default');
		$data['text_register_button_default'] = $this->language->get('text_register_button_default');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');

		// User token for AJAX requests
		$data['user_token'] = $this->session->data['user_token'];

		// Load header, footer
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/bonus_manager', $data));
	}

	public function dashboard() {
		$this->load->language('extension/module/bonus_manager');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/bonus_manager');
		$this->model_extension_module_bonus_manager->syncPendingLoyaltyDowngrades();

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_dashboard'] = $this->language->get('text_dashboard');
		$data['text_recent_transactions'] = $this->language->get('text_recent_transactions');
		$data['text_operations'] = $this->language->get('text_operations');
		$data['text_operations_help'] = $this->language->get('text_operations_help');
		$data['text_view_all_operations'] = $this->language->get('text_view_all_operations');
		$data['text_total_issued'] = $this->language->get('text_total_issued');
		$data['text_total_redeemed'] = $this->language->get('text_total_redeemed');
		$data['text_active_bonuses'] = $this->language->get('text_active_bonuses');
		$data['text_customers_count'] = $this->language->get('text_customers_count');
		$data['text_orders_with_bonuses'] = $this->language->get('text_orders_with_bonuses');
		$data['text_settings'] = $this->language->get('text_settings');
		$data['text_awarded_clients'] = $this->language->get('text_awarded_clients');
		$data['text_view_all_awarded_clients'] = $this->language->get('text_view_all_awarded_clients');
		$data['text_pending_loyalty_downgrades'] = $this->language->get('text_pending_loyalty_downgrades');
		$data['text_pending_loyalty_downgrades_help'] = $this->language->get('text_pending_loyalty_downgrades_help');
		$data['text_view_all_loyalty_reviews'] = $this->language->get('text_view_all_loyalty_reviews');
		$data['text_no_pending_loyalty_downgrades'] = $this->language->get('text_no_pending_loyalty_downgrades');
		$data['text_spent_bonuses'] = $this->language->get('text_spent_bonuses');
		$data['text_active_bonus_awards'] = $this->language->get('text_active_bonus_awards');
		$data['column_order_id'] = $this->language->get('column_order_id');
		$data['column_customer'] = $this->language->get('column_customer');
		$data['column_points'] = $this->language->get('column_points');
		$data['column_remaining'] = $this->language->get('column_remaining');
		$data['column_reward_kind'] = $this->language->get('column_reward_kind');
		$data['column_bonus_type'] = $this->language->get('column_bonus_type');
		$data['column_date'] = $this->language->get('column_date');
		$data['column_date_expires'] = $this->language->get('column_date_expires');
		$data['column_total_awarded'] = $this->language->get('column_total_awarded');
		$data['column_total_remaining'] = $this->language->get('column_total_remaining');
		$data['column_last_award_date'] = $this->language->get('column_last_award_date');
		$data['column_current_loyalty_level'] = $this->language->get('column_current_loyalty_level');
		$data['column_recommended_loyalty_level'] = $this->language->get('column_recommended_loyalty_level');
		$data['column_total_spent'] = $this->language->get('column_total_spent');
		$data['column_required_total_spent'] = $this->language->get('column_required_total_spent');
		$data['column_period'] = $this->language->get('column_period');

		$data['statistics'] = $this->model_extension_module_bonus_manager->getBonusStatistics();
		$data['recent_transactions'] = $this->model_extension_module_bonus_manager->getRecentBonusTransactions(10);
		$data['spent_transactions'] = $this->model_extension_module_bonus_manager->getBonusTransactions(array(
			'start' => 0,
			'limit' => 10,
			'filter_points_sign' => 'negative'
		));
		$data['active_awards'] = $this->model_extension_module_bonus_manager->getActiveBonusAwards(10);
		$data['awarded_clients'] = $this->model_extension_module_bonus_manager->getAwardedClients(array(
			'start' => 0,
			'limit' => 10
		));
		$data['pending_loyalty_downgrades'] = $this->model_extension_module_bonus_manager->getPendingLoyaltyDowngrades(array(
			'start' => 0,
			'limit' => 10
		));
		$data['todays_birthdays'] = $this->model_extension_module_bonus_manager->getTodaysBirthdays();

		$data['settings_link'] = $this->url->link('extension/module/bonus_manager', 'user_token=' . $this->session->data['user_token'], true);
		$data['operations_link'] = $this->url->link('extension/module/bonus_manager/operations', 'user_token=' . $this->session->data['user_token'], true);
		$data['awarded_clients_link'] = $this->url->link('extension/module/bonus_manager/awardedClients', 'user_token=' . $this->session->data['user_token'], true);
		$data['loyalty_reviews_link'] = $this->url->link('extension/module/bonus_manager/loyaltyReviews', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_dashboard'),
			'href' => $this->url->link('extension/module/bonus_manager/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/bonus_manager_dashboard', $data));
	}

	public function loyaltyReviews() {
		$this->load->language('extension/module/bonus_manager');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/bonus_manager');
		$this->model_extension_module_bonus_manager->syncPendingLoyaltyDowngrades();

		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;
		$filter_customer = isset($this->request->get['filter_customer']) ? trim($this->request->get['filter_customer']) : '';

		if ($page < 1) {
			$page = 1;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$filter_data = array(
			'start' => ($page - 1) * $limit,
			'limit' => $limit,
			'filter_customer' => $filter_customer
		);

		$total = $this->model_extension_module_bonus_manager->getPendingLoyaltyDowngradesTotal($filter_data);
		$data['reviews'] = $this->model_extension_module_bonus_manager->getPendingLoyaltyDowngrades($filter_data);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_dashboard'] = $this->language->get('text_dashboard');
		$data['text_pending_loyalty_downgrades'] = $this->language->get('text_pending_loyalty_downgrades');
		$data['text_pending_loyalty_downgrades_help'] = $this->language->get('text_pending_loyalty_downgrades_help');
		$data['text_filter'] = $this->language->get('text_filter');
		$data['button_filter'] = $this->language->get('button_filter');
		$data['button_apply_downgrade'] = $this->language->get('button_apply_downgrade');
		$data['button_dismiss_downgrade'] = $this->language->get('button_dismiss_downgrade');
		$data['entry_customer'] = $this->language->get('entry_customer');
		$data['column_customer'] = $this->language->get('column_customer');
		$data['column_current_loyalty_level'] = $this->language->get('column_current_loyalty_level');
		$data['column_recommended_loyalty_level'] = $this->language->get('column_recommended_loyalty_level');
		$data['column_total_spent'] = $this->language->get('column_total_spent');
		$data['column_required_total_spent'] = $this->language->get('column_required_total_spent');
		$data['column_period'] = $this->language->get('column_period');
		$data['column_action'] = $this->language->get('column_action');
		$data['text_no_pending_loyalty_downgrades'] = $this->language->get('text_no_pending_loyalty_downgrades');
		$data['text_confirm_apply_downgrade'] = $this->language->get('text_confirm_apply_downgrade');
		$data['text_confirm_dismiss_downgrade'] = $this->language->get('text_confirm_dismiss_downgrade');

		$data['filter_customer'] = $filter_customer;
		$data['dashboard_link'] = $this->url->link('extension/module/bonus_manager/dashboard', 'user_token=' . $this->session->data['user_token'], true);
		$data['apply_downgrade_action'] = $this->url->link('extension/module/bonus_manager/applyLoyaltyDowngrade', 'user_token=' . $this->session->data['user_token'], true);
		$data['dismiss_downgrade_action'] = $this->url->link('extension/module/bonus_manager/dismissLoyaltyDowngrade', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_dashboard'),
			'href' => $this->url->link('extension/module/bonus_manager/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_pending_loyalty_downgrades'),
			'href' => $this->url->link('extension/module/bonus_manager/loyaltyReviews', 'user_token=' . $this->session->data['user_token'], true)
		);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;

		$url = '';
		if ($filter_customer !== '') {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($filter_customer, ENT_QUOTES, 'UTF-8'));
		}

		$pagination->url = $this->url->link('extension/module/bonus_manager/loyaltyReviews', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}&limit=' . $limit, true);
		$data['pagination'] = $pagination->render();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/bonus_manager_loyalty_reviews', $data));
	}

	public function applyLoyaltyDowngrade() {
		$this->load->language('extension/module/bonus_manager');
		$this->load->model('extension/module/bonus_manager');

		if (!$this->user->hasPermission('modify', 'extension/module/bonus_manager')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
		} else {
			$review_id = isset($this->request->post['loyalty_review_id']) ? (int)$this->request->post['loyalty_review_id'] : 0;

			if ($review_id > 0 && $this->model_extension_module_bonus_manager->applyPendingLoyaltyDowngrade($review_id, (int)$this->user->getId())) {
				$this->session->data['success'] = $this->language->get('text_loyalty_downgrade_applied');
			} else {
				$this->session->data['error_warning'] = $this->language->get('error_loyalty_review_not_found');
			}
		}

		$this->response->redirect($this->url->link('extension/module/bonus_manager/loyaltyReviews', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function dismissLoyaltyDowngrade() {
		$this->load->language('extension/module/bonus_manager');
		$this->load->model('extension/module/bonus_manager');

		if (!$this->user->hasPermission('modify', 'extension/module/bonus_manager')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
		} else {
			$review_id = isset($this->request->post['loyalty_review_id']) ? (int)$this->request->post['loyalty_review_id'] : 0;

			if ($review_id > 0 && $this->model_extension_module_bonus_manager->dismissPendingLoyaltyDowngrade($review_id, (int)$this->user->getId())) {
				$this->session->data['success'] = $this->language->get('text_loyalty_downgrade_dismissed');
			} else {
				$this->session->data['error_warning'] = $this->language->get('error_loyalty_review_not_found');
			}
		}

		$this->response->redirect($this->url->link('extension/module/bonus_manager/loyaltyReviews', 'user_token=' . $this->session->data['user_token'], true));
	}

		public function operations() {
		$this->load->language('extension/module/bonus_manager');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/bonus_manager');

		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;
		$filter_order_id = isset($this->request->get['filter_order_id']) ? (int)$this->request->get['filter_order_id'] : 0;
		$filter_customer = isset($this->request->get['filter_customer']) ? trim($this->request->get['filter_customer']) : '';
		$filter_reward_kind = isset($this->request->get['filter_reward_kind']) ? trim($this->request->get['filter_reward_kind']) : '';
		$filter_bonus_type = isset($this->request->get['filter_bonus_type']) ? trim($this->request->get['filter_bonus_type']) : '';
		$filter_points_sign = isset($this->request->get['filter_points_sign']) ? trim($this->request->get['filter_points_sign']) : '';
		$filter_date_from = isset($this->request->get['filter_date_from']) ? trim($this->request->get['filter_date_from']) : '';
		$filter_date_to = isset($this->request->get['filter_date_to']) ? trim($this->request->get['filter_date_to']) : '';

		if ($page < 1) {
			$page = 1;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$filter_data = array(
			'start' => ($page - 1) * $limit,
			'limit' => $limit,
			'filter_order_id' => $filter_order_id,
			'filter_customer' => $filter_customer,
			'filter_reward_kind' => $filter_reward_kind,
			'filter_bonus_type' => $filter_bonus_type,
			'filter_points_sign' => $filter_points_sign,
			'filter_date_from' => $filter_date_from,
			'filter_date_to' => $filter_date_to
		);

		$total = $this->model_extension_module_bonus_manager->getBonusTransactionsTotal($filter_data);
		$data['transactions'] = $this->model_extension_module_bonus_manager->getBonusTransactions($filter_data);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_dashboard'] = $this->language->get('text_dashboard');
		$data['text_operations'] = $this->language->get('text_operations');
		$data['text_operations_help'] = $this->language->get('text_operations_help');
		$data['column_order_id'] = $this->language->get('column_order_id');
		$data['column_customer'] = $this->language->get('column_customer');
		$data['column_points'] = $this->language->get('column_points');
		$data['column_remaining'] = $this->language->get('column_remaining');
		$data['column_reward_kind'] = $this->language->get('column_reward_kind');
		$data['column_bonus_type'] = $this->language->get('column_bonus_type');
		$data['column_date'] = $this->language->get('column_date');
		$data['entry_order_id'] = $this->language->get('entry_order_id');
		$data['entry_customer'] = $this->language->get('entry_customer');
		$data['entry_reward_kind'] = $this->language->get('entry_reward_kind');
		$data['entry_bonus_type'] = $this->language->get('entry_bonus_type');
		$data['entry_points_sign'] = $this->language->get('entry_points_sign');
		$data['entry_date_from'] = $this->language->get('entry_date_from');
		$data['entry_date_to'] = $this->language->get('entry_date_to');
		$data['text_filter'] = $this->language->get('text_filter');
		$data['button_filter'] = $this->language->get('button_filter');
		$data['datepicker'] = $this->language->get('datepicker');

		$data['filter_order_id'] = $filter_order_id;
		$data['filter_customer'] = $filter_customer;
		$data['filter_reward_kind'] = $filter_reward_kind;
		$data['filter_bonus_type'] = $filter_bonus_type;
		$data['filter_points_sign'] = $filter_points_sign;
		$data['filter_date_from'] = $filter_date_from;
		$data['filter_date_to'] = $filter_date_to;

		$data['reward_kinds'] = array('award', 'spend', 'deduction', 'adjust', 'expire');

		$data['dashboard_link'] = $this->url->link('extension/module/bonus_manager/dashboard', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_operations'),
			'href' => $this->url->link('extension/module/bonus_manager/operations', 'user_token=' . $this->session->data['user_token'], true)
		);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$url = '';
		if ($filter_order_id) {
			$url .= '&filter_order_id=' . $filter_order_id;
		}
		if ($filter_customer !== '') {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($filter_customer, ENT_QUOTES, 'UTF-8'));
		}
		if ($filter_reward_kind !== '') {
			$url .= '&filter_reward_kind=' . $filter_reward_kind;
		}
		if ($filter_bonus_type !== '') {
			$url .= '&filter_bonus_type=' . $filter_bonus_type;
		}
		if ($filter_points_sign !== '') {
			$url .= '&filter_points_sign=' . $filter_points_sign;
		}
		if ($filter_date_from !== '') {
			$url .= '&filter_date_from=' . $filter_date_from;
		}
		if ($filter_date_to !== '') {
			$url .= '&filter_date_to=' . $filter_date_to;
		}

		$pagination->url = $this->url->link('extension/module/bonus_manager/operations', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}&limit=' . $limit, true);
		$data['pagination'] = $pagination->render();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/bonus_manager_operations', $data));
	}

	public function awardedClients() {
		$this->load->language('extension/module/bonus_manager');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/bonus_manager');

		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 20;
		$filter_customer = isset($this->request->get['filter_customer']) ? trim($this->request->get['filter_customer']) : '';
		$filter_date_from = isset($this->request->get['filter_date_from']) ? trim($this->request->get['filter_date_from']) : '';
		$filter_date_to = isset($this->request->get['filter_date_to']) ? trim($this->request->get['filter_date_to']) : '';
		$filter_min_remaining = isset($this->request->get['filter_min_remaining']) ? (int)$this->request->get['filter_min_remaining'] : 0;

		if ($page < 1) {
			$page = 1;
		}

		if ($limit < 1) {
			$limit = 20;
		}

		$filter_data = array(
			'start' => ($page - 1) * $limit,
			'limit' => $limit,
			'filter_customer' => $filter_customer,
			'filter_date_from' => $filter_date_from,
			'filter_date_to' => $filter_date_to,
			'filter_min_remaining' => $filter_min_remaining
		);

		$total = $this->model_extension_module_bonus_manager->getAwardedClientsTotal($filter_data);
		$data['clients'] = $this->model_extension_module_bonus_manager->getAwardedClients($filter_data);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_dashboard'] = $this->language->get('text_dashboard');
		$data['text_awarded_clients'] = $this->language->get('text_awarded_clients');
		$data['text_awarded_clients_help'] = $this->language->get('text_awarded_clients_help');
		$data['column_customer'] = $this->language->get('column_customer');
		$data['column_loyalty_level'] = $this->language->get('column_loyalty_level');
		$data['column_total_awarded'] = $this->language->get('column_total_awarded');
		$data['column_total_remaining'] = $this->language->get('column_total_remaining');
		$data['column_last_award_date'] = $this->language->get('column_last_award_date');
		$data['entry_customer'] = $this->language->get('entry_customer');
		$data['entry_date_from'] = $this->language->get('entry_date_from');
		$data['entry_date_to'] = $this->language->get('entry_date_to');
		$data['entry_min_remaining'] = $this->language->get('entry_min_remaining');
		$data['text_filter'] = $this->language->get('text_filter');
		$data['button_filter'] = $this->language->get('button_filter');
		$data['datepicker'] = $this->language->get('datepicker');

		$data['filter_customer'] = $filter_customer;
		$data['filter_date_from'] = $filter_date_from;
		$data['filter_date_to'] = $filter_date_to;
		$data['filter_min_remaining'] = $filter_min_remaining;

		$data['dashboard_link'] = $this->url->link('extension/module/bonus_manager/dashboard', 'user_token=' . $this->session->data['user_token'], true);
		$data['user_token'] = $this->session->data['user_token'];

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_awarded_clients'),
			'href' => $this->url->link('extension/module/bonus_manager/awardedClients', 'user_token=' . $this->session->data['user_token'], true)
		);

		$pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page = $page;
		$pagination->limit = $limit;

		$url = '';
		if ($filter_customer !== '') {
			$url .= '&filter_customer=' . urlencode(html_entity_decode($filter_customer, ENT_QUOTES, 'UTF-8'));
		}
		if ($filter_date_from !== '') {
			$url .= '&filter_date_from=' . $filter_date_from;
		}
		if ($filter_date_to !== '') {
			$url .= '&filter_date_to=' . $filter_date_to;
		}
		if ($filter_min_remaining) {
			$url .= '&filter_min_remaining=' . $filter_min_remaining;
		}

		$pagination->url = $this->url->link('extension/module/bonus_manager/awardedClients', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}&limit=' . $limit, true);
		$data['pagination'] = $pagination->render();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/bonus_manager_awarded_clients', $data));
	}

	/**
	 * AJAX: Add or update bonus setting
	 */
	public function addBonusSetting() {
		$this->load->language('extension/module/bonus_manager');
		$this->load->model('extension/module/bonus_manager');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/bonus_manager')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$customer_group_id = isset($this->request->post['customer_group_id']) ? (int)$this->request->post['customer_group_id'] : 0;
			$category_id = isset($this->request->post['category_id']) ? (int)$this->request->post['category_id'] : 0;
			$bonus_percent = isset($this->request->post['bonus_percent']) ? (float)$this->request->post['bonus_percent'] : 0;

			if ($customer_group_id > 0) {
				$data = array(
					'customer_group_id' => $customer_group_id,
					'category_id' => $category_id,
					'bonus_percent' => $bonus_percent
				);

				$this->model_extension_module_bonus_manager->addBonusSetting($data);

				$json['success'] = $this->language->get('text_bonus_setting_added');
			} else {
				$json['error'] = $this->language->get('error_customer_group');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: Delete bonus setting
	 */
	public function deleteBonusSetting() {
		$this->load->language('extension/module/bonus_manager');
		$this->load->model('extension/module/bonus_manager');

		$json = array();

		if (!$this->user->hasPermission('modify', 'extension/module/bonus_manager')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$bonus_setting_id = isset($this->request->post['bonus_setting_id']) ? (int)$this->request->post['bonus_setting_id'] : 0;

			if ($bonus_setting_id > 0) {
				$this->model_extension_module_bonus_manager->deleteBonusSetting($bonus_setting_id);
				$json['success'] = $this->language->get('text_bonus_setting_deleted');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Install module
	 */
	public function install() {
		$this->load->model('extension/module/bonus_manager');
		$this->model_extension_module_bonus_manager->install();
	}

	/**
	 * Uninstall module
	 */
	public function uninstall() {
		$this->load->model('extension/module/bonus_manager');
		$this->model_extension_module_bonus_manager->uninstall();
	}

	/**
	 * Event handler: Deduct bonuses when return is completed
	 * Triggered by event: admin/model/sale/return/addReturnHistory/after
	 */
	public function deductBonusesOnReturnComplete($route, $args, $output) {
		// $args[0] = return_id
		// $args[1] = return_status_id
		$return_id = isset($args[0]) ? (int)$args[0] : 0;
		$return_status_id = isset($args[1]) ? (int)$args[1] : 0;

		// Get configured return deduction status
		$configured_status = (int)$this->config->get('module_bonus_manager_return_deduction_status_id');
		if (!$configured_status) {
			$configured_status = 4; // Default to "Complete" status
		}

		// Only process if status matches configured deduction status
		if ($return_status_id !== $configured_status || $return_id <= 0) {
			return;
		}

		// Call admin model method directly (no cross-context call needed)
		$this->load->model('extension/module/bonus_manager');
		$result = $this->model_extension_module_bonus_manager->returnProductBonuses($return_id);

		// Log the result for debugging
		if ($result) {
			$this->log->write('Bonus Manager: Successfully deducted bonuses for return #' . $return_id);
		} else {
			$this->log->write('Bonus Manager: Failed to deduct bonuses for return #' . $return_id);
		}
	}

	/**
	 * Cron job entry point for bonus expiration processing
	 *
	 * This method is called via CLI cron: php admin/bonus_expiration_cron.php
	 * It handles:
	 * 1. Sending expiration warning emails to customers
	 * 2. Marking expired bonuses (setting remaining=0)
	 *
	 * Uses the model layer for all database operations instead of direct SQL,
	 * ensuring consistency with the rest of the application.
	 *
	 * Scope: Called from CLI via start('bonuscron') framework bootstrap
	 *
	 * @return void
	 */
	public function cron() {
		$this->log->write('=== Bonus Cron Job Started ===');

		try {
			$this->load->model('extension/module/bonus_manager');

			// Step 1: Send expiration warning emails
			if ($this->config->get('module_bonus_manager_email_expiring_status')) {
				$this->processExpirationWarnings();
			}

			// Step 2: Process expired bonuses
			$this->processExpiredBonuses();

			// Step 3: Process birthday bonuses
			$birthday_bonus_amount = (int)$this->config->get('module_bonus_manager_birthday_bonus_amount');
			if ($birthday_bonus_amount > 0) {
				$this->processBirthdayBonuses($birthday_bonus_amount);
			}

			$this->log->write('=== Bonus Cron Job Completed Successfully ===');

		} catch (Exception $e) {
			$this->log->write('CRON ERROR: ' . $e->getMessage());
			$this->log->write('=== Bonus Cron Job Failed ===');
		}

		// For CLI output
		echo "Bonus cron completed. Check logs for details.\n";
	}

	/**
	 * Process expiration warning emails
	 *
	 * Sends warning emails to customers whose bonuses are about to expire.
	 * Uses configured warning periods (e.g., 90, 30, 7 days before expiration).
	 * Delegates email sending to admin/controller/mail/bonus::expiring()
	 *
	 * @return void
	 */
	private function processExpirationWarnings() {
		$warning_days_config = $this->config->get('module_bonus_manager_expiration_warning_days');
		if (!$warning_days_config) {
			return;
		}

		// Parse comma-separated warning periods (e.g., "90,30,7")
		$warning_periods = array_map('trim', explode(',', $warning_days_config));
		$warning_periods = array_filter(array_map('intval', $warning_periods));

		$this->log->write('Checking expiration warnings for periods: ' . implode(', ', $warning_periods) . ' days');

		foreach ($warning_periods as $days) {
			if ($days <= 0) continue;

			// Use model method to get expiring bonuses
			$customers_data = $this->model_extension_module_bonus_manager->getExpiringBonuses($days);

			if (empty($customers_data)) {
				continue;
			}

			$this->log->write('Found ' . count($customers_data) . ' customers with bonuses expiring in ~' . $days . ' days');

			$emails_sent = 0;
			foreach ($customers_data as $customer_data) {
				// Delegate email sending to mail controller
				// Note: OpenCart's loader wraps the second param in array, so we pass both args as array elements
				$success = $this->load->controller('mail/bonus/expiring', array($customer_data, $days));

				if ($success) {
					// Mark these bonuses as warned using model method
					$this->model_extension_module_bonus_manager->markBonusesAsWarned(
						$customer_data['reward_ids'],
						$days
					);
					$emails_sent++;
				}
			}

			$this->log->write('Sent ' . $emails_sent . ' expiration warning emails for ' . $days . '-day period');
		}
	}

	/**
	 * Process expired bonuses
	 *
	 * Marks all bonuses past their expiration date as expired.
	 *
	 * @return void
	 */
	private function processExpiredBonuses() {
		$info = $this->model_extension_module_bonus_manager->getExpiredBonusesInfo();

		if ($info['count'] <= 0) {
			$this->log->write('No expired bonuses found');
			return;
		}

		$this->log->write('Found ' . $info['count'] . ' expired bonus records (' . $info['total_points'] . ' total points)');

		$expired_count = $this->model_extension_module_bonus_manager->expireExpiredBonuses();

		$this->log->write('Expired ' . $expired_count . ' bonus records (set remaining to 0)');
	}

	/**
	 * Process birthday bonuses
	 *
	 * Awards birthday bonuses to customers who have a birthday today.
	 * Only awards once per year per customer.
	 * Sends birthday email notification if enabled.
	 *
	 * Scope: Called from cron() method daily
	 *
	 * @param int $bonus_amount Amount of birthday bonus to award
	 * @return void
	 */
	private function processBirthdayBonuses($bonus_amount) {
		$this->log->write('Processing birthday bonuses (amount: ' . $bonus_amount . ')');

		// Get customers with birthday today who haven't received bonus this year
		$customers = $this->model_extension_module_bonus_manager->getCustomersWithBirthdayToday();

		if (empty($customers)) {
			$this->log->write('No customers with birthday today found');
			return;
		}

		$this->log->write('Found ' . count($customers) . ' customers with birthday today');

		$bonuses_awarded = 0;
		$emails_sent = 0;

		foreach ($customers as $customer) {
			// Award birthday bonus
			$result = $this->model_extension_module_bonus_manager->awardBirthdayBonus(
				$customer['customer_id'],
				$bonus_amount
			);

			if ($result) {
				$bonuses_awarded++;

				// Send birthday email if enabled
				if ($this->config->get('module_bonus_manager_email_birthday_status')) {
					$success = $this->load->controller('mail/bonus/birthday', array($customer, $bonus_amount));
					if ($success) {
						$emails_sent++;
					}
				}
			}
		}

		$this->log->write('Awarded ' . $bonuses_awarded . ' birthday bonuses, sent ' . $emails_sent . ' emails');
	}

	/**
	 * Validate form data
	 */
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/bonus_manager')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	/**
	 * Get default email template for bonus awarded
	 */
	private function getDefaultAwardedTemplate() {
		return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
	<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
		<h1 style="color: white; margin: 0; font-size: 28px;">&#127873; Бонусы начислены!</h1>
	</div>

	<div style="background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px;">
		<p style="font-size: 16px; color: #374151;">Здравствуйте, <strong>{customer_firstname}</strong>!</p>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Мы рады сообщить, что вам начислены бонусы за заказ <strong>#{order_id}</strong>.
		</p>

		<div style="background: white; border-left: 4px solid #10b981; padding: 20px; margin: 20px 0; border-radius: 4px;">
			<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Начислено бонусов:</p>
			<p style="margin: 0; font-size: 32px; color: #10b981; font-weight: bold;">+{bonus_amount} &#8381;</p>
		</div>

		<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 15px; margin: 20px 0;">
			<p style="margin: 0; color: #1e40af; font-size: 14px;">
				<strong>Детали:</strong><br>
				&#128176; Текущий баланс: <strong>{current_balance} &#8381;</strong><br>
				&#128197; Дата начисления: {date_awarded}
			</p>
		</div>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Вы можете использовать бонусы для оплаты следующих заказов. Максимальная сумма оплаты бонусами — <strong>{max_usage_percent}%</strong> от суммы заказа.
		</p>

		<div style="text-align: center; margin: 30px 0;">
			<a href="{account_url}" style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
				Перейти в личный кабинет
			</a>
		</div>

		<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<p style="color: #6b7280; font-size: 14px; margin: 0;">
				С уважением,<br>
				<strong>{store_name}</strong>
			</p>
		</div>
	</div>
</div>';
	}

	/**
	 * Get default email template for bonus spent
	 */
	private function getDefaultSpentTemplate() {
		return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
	<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
		<h1 style="color: white; margin: 0; font-size: 28px;">&#128179; Бонусы использованы</h1>
	</div>

	<div style="background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px;">
		<p style="font-size: 16px; color: #374151;">Здравствуйте, <strong>{customer_firstname}</strong>!</p>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Благодарим за использование бонусов при оплате заказа <strong>#{order_id}</strong>.
		</p>

		<div style="background: white; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 4px;">
			<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Списано бонусов:</p>
			<p style="margin: 0; font-size: 32px; color: #667eea; font-weight: bold;">-{points_spent} &#8381;</p>
		</div>

		<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 15px; margin: 20px 0;">
			<p style="margin: 0; color: #1e40af; font-size: 14px;">
				<strong>Детали:</strong><br>
				&#128176; Остаток на балансе: <strong>{current_balance} &#8381;</strong><br>
				&#128197; Дата списания: {date_spent}
			</p>
		</div>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Продолжайте делать покупки и копите бонусы для следующих заказов!
		</p>

		<div style="text-align: center; margin: 30px 0;">
			<a href="{order_url}" style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
				Просмотреть заказ
			</a>
		</div>

		<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<p style="color: #6b7280; font-size: 14px; margin: 0;">
				С уважением,<br>
				<strong>{store_name}</strong>
			</p>
		</div>
	</div>
</div>';
	}

	/**
	 * Get default email template for bonus deduction (return)
	 *
	 * This method is public so it can be called from admin/controller/mail/bonus.php
	 * to maintain a single source of truth for the default template.
	 *
	 * Scope: Called from bonus_manager form and mail/bonus/deducted()
	 *
	 * @return string Default HTML template
	 */
	public function getDefaultDeductedTemplate() {
		return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
	<div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
		<h1 style="color: white; margin: 0; font-size: 28px;">&#128230; Возврат обработан</h1>
	</div>

	<div style="background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px;">
		<p style="font-size: 16px; color: #374151;">Здравствуйте, <strong>{customer_firstname}</strong>!</p>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Информируем вас о списании бонусных баллов в связи с возвратом товара.
		</p>

		<div style="background: white; border-left: 4px solid #f59e0b; padding: 20px; margin: 20px 0; border-radius: 4px;">
			<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Списано бонусов:</p>
			<p style="margin: 0; font-size: 32px; color: #f59e0b; font-weight: bold;">-{points_deducted} &#8381;</p>
		</div>

		<div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 6px; padding: 15px; margin: 20px 0;">
			<p style="margin: 0; color: #92400e; font-size: 14px;">
				<strong>Информация о возврате:</strong><br>
				&#128230; Заказ: <strong>#{order_id}</strong><br>
				&#128196; Возврат: <strong>#{return_id}</strong><br>
				&#128176; Остаток на балансе: <strong>{current_balance} &#8381;</strong><br>
				&#128197; Дата: {date_deducted}
			</p>
		</div>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Если у вас есть вопросы по возврату, пожалуйста, свяжитесь с нами.
		</p>

		<div style="text-align: center; margin: 30px 0;">
			<a href="{account_url}" style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
				Просмотреть баланс
			</a>
		</div>

		<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<p style="color: #6b7280; font-size: 14px; margin: 0;">
				С уважением,<br>
				<strong>{store_name}</strong>
			</p>
		</div>
	</div>
</div>';
	}

	/**
	 * Get default email template for bonus expiring warning (with Twig support)
	 *
	 * This method is public so it can be called from admin/controller/mail/bonus.php
	 * to maintain a single source of truth for the default template.
	 *
	 * Scope: Called from bonus_manager form (line ~260) and mail/bonus/expiring()
	 *
	 * @return string Default HTML template
	 */
	public function getDefaultExpiringTemplate() {
		return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
	<div style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
		<h1 style="color: white; margin: 0; font-size: 28px;">&#9200; Бонусы скоро сгорят!</h1>
	</div>

	<div style="background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px;">
		<p style="font-size: 16px; color: #374151;">Здравствуйте, <strong>{customer_firstname}</strong>!</p>

		<div style="background: white; border-left: 4px solid #dc2626; padding: 20px; margin: 20px 0; border-radius: 4px;">
			<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Сгорит бонусов:</p>
			<p style="margin: 0; font-size: 32px; color: #dc2626; font-weight: bold;">{expiring_points} &#8381;</p>
		</div>

		<div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 15px; margin: 20px 0;">
			<p style="margin: 0; color: #991b1b; font-size: 14px;">
				<strong>&#9888; Внимание!</strong><br>
				&#128197; Дата сгорания: <strong>{expiration_date}</strong><br>
				&#128176; Текущий баланс: <strong>{current_balance} &#8381;</strong>
			</p>
		</div>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Не теряйте свои бонусы! Используйте их для оплаты следующего заказа.
		</p>

		<div style="text-align: center; margin: 30px 0;">
			<a href="{store_url}" style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
				Перейти в магазин
			</a>
		</div>

		<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<p style="color: #6b7280; font-size: 14px; margin: 0;">
				С уважением,<br>
				<strong>{store_name}</strong>
			</p>
		</div>
	</div>
</div>';
	}

	/**
	 * Get default template for birthday bonus email
	 *
	 * This method is public so it can be called from admin/controller/mail/bonus.php
	 * to maintain a single source of truth for the default template.
	 *
	 * Scope: Called from bonus_manager form and mail/bonus/birthday()
	 *
	 * @return string Default HTML template
	 */
	public function getDefaultBirthdayTemplate() {
		return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
	<div style="background: linear-gradient(135deg, #ec4899 0%, #f472b6 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
		<h1 style="color: white; margin: 0; font-size: 28px;">&#127874; С Днём рождения!</h1>
	</div>

	<div style="background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px;">
		<p style="font-size: 16px; color: #374151;">Дорогой(ая) <strong>{customer_firstname}</strong>!</p>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			От всей души поздравляем вас с Днём рождения! &#127881;
		</p>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			В честь этого замечательного дня мы дарим вам подарочные бонусы!
		</p>

		<div style="background: white; border-left: 4px solid #ec4899; padding: 20px; margin: 20px 0; border-radius: 4px;">
			<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Ваш подарок:</p>
			<p style="margin: 0; font-size: 32px; color: #ec4899; font-weight: bold;">+{birthday_bonus} &#8381;</p>
		</div>

		<div style="background: #fdf2f8; border: 1px solid #fbcfe8; border-radius: 6px; padding: 15px; margin: 20px 0;">
			<p style="margin: 0; color: #9d174d; font-size: 14px;">
				<strong>&#127873; Подарок уже на вашем счету!</strong><br>
				&#128176; Текущий баланс: <strong>{current_balance} &#8381;</strong><br>
				&#128197; Бонусы действительны до: {expiration_date}
			</p>
		</div>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Желаем вам здоровья, счастья и исполнения всех желаний! Пусть этот день будет наполнен радостью и приятными сюрпризами.
		</p>

		<div style="text-align: center; margin: 30px 0;">
			<a href="{store_url}" style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #ec4899 0%, #f472b6 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
				Порадовать себя подарком
			</a>
		</div>

		<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<p style="color: #6b7280; font-size: 14px; margin: 0;">
				С наилучшими пожеланиями,<br>
				<strong>{store_name}</strong>
			</p>
		</div>
	</div>
</div>';
	}

	/**
	 * Get default template for loyalty level upgrade email
	 *
	 * @return string Default HTML template with Twig support
	 */
	private function getDefaultLoyaltyUpgradeTemplate() {
		return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
	<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
		<h1 style="color: white; margin: 0; font-size: 28px;">&#127881; Поздравляем!</h1>
	</div>

	<div style="background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px;">
		<p style="font-size: 16px; color: #374151;">Здравствуйте, <strong>{customer_firstname}</strong>!</p>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Мы рады сообщить, что ваш уровень лояльности был повышен!
		</p>

		<div style="background: white; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 4px;">
			<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Ваш предыдущий уровень:</p>
			<p style="margin: 0 0 15px 0; font-size: 18px; color: #9ca3af;"><s>{old_group_name}</s></p>

			<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Ваш новый уровень:</p>
			<p style="margin: 0; font-size: 24px; color: #667eea; font-weight: bold;">{new_group_name}</p>
		</div>

		<h3 style="color: #374151; margin-top: 30px;">Ваши новые преимущества:</h3>
		<ul style="color: #374151; line-height: 1.8; font-size: 15px;">
			<li><strong>Повышенный процент бонусов:</strong> {new_bonus_percent}% от суммы покупки</li>
			<li><strong>Приоритетная поддержка</strong></li>
			<li><strong>Эксклюзивные предложения</strong> для вашего уровня</li>
		</ul>

		<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 15px; margin: 20px 0;">
			<p style="margin: 0; color: #1e40af; font-size: 14px;">
				<strong>Ваша статистика:</strong><br>
				&#128176; Сумма покупок: <strong>{total_spent} &#8381;</strong><br>
				&#127873; Текущий баланс бонусов: <strong>{current_balance} &#8381;</strong><br>
				&#128197; Дата повышения: {date_upgraded}
			</p>
		</div>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Спасибо за то, что выбираете нас! Продолжайте делать покупки и получайте еще больше бонусов.
		</p>

		<div style="text-align: center; margin: 30px 0;">
			<a href="{account_url}" style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
				Перейти в личный кабинет
			</a>
		</div>

		<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<p style="color: #6b7280; font-size: 14px; margin: 0;">
				С уважением,<br>
				<strong>{store_name}</strong>
			</p>
		</div>
	</div>
</div>';
	}
}
