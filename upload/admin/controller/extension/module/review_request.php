<?php
class ControllerExtensionModuleReviewRequest extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/review_request');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/review_request');
		$this->load->model('setting/setting');
		$this->load->model('localisation/order_status');
		$this->load->model('customer/customer_group');
		$this->upgradeModuleIfNeeded();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$post_data = $this->request->post;

			foreach (array(
				'module_review_request_email_body',
				'module_review_request_google_reference',
				'module_review_request_google_review_url',
				'module_review_request_google_widget_code',
				'module_review_request_yandex_reference',
				'module_review_request_yandex_review_url',
				'module_review_request_yandex_widget_code'
			) as $field) {
				if (isset($post_data[$field])) {
					$decoded = html_entity_decode($post_data[$field], ENT_QUOTES, 'UTF-8');
					$post_data[$field] = mb_encode_numericentity($decoded, [0x10000, 0x10FFFF, 0, 0xFFFFFF], 'UTF-8');
				}
			}

			$post_data['module_review_request_excluded_customer_group_ids'] = isset($post_data['module_review_request_excluded_customer_group_ids']) ? array_map('intval', (array)$post_data['module_review_request_excluded_customer_group_ids']) : array();

			$this->model_setting_setting->editSetting('module_review_request', $post_data);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_delay_days'] = isset($this->error['delay_days']) ? $this->error['delay_days'] : '';
		$data['error_org_cooldown_days'] = isset($this->error['org_cooldown_days']) ? $this->error['org_cooldown_days'] : '';
		$data['error_review_bonus_points'] = isset($this->error['review_bonus_points']) ? $this->error['review_bonus_points'] : '';

		if (!$data['error_warning'] && !empty($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->session->data['error_warning'];
			unset($this->session->data['error_warning']);
		}

		$data['success'] = !empty($this->session->data['success']) ? $this->session->data['success'] : '';

		if (!empty($this->session->data['success'])) {
			unset($this->session->data['success']);
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
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/review_request', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/review_request', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$data['module_review_request_status'] = $this->getSettingValue('module_review_request_status', 0);
		$data['module_review_request_email_status'] = $this->getSettingValue('module_review_request_email_status', 1);
		$data['module_review_request_show_on_order_page'] = $this->getSettingValue('module_review_request_show_on_order_page', 1);
		$data['module_review_request_delay_days'] = $this->getSettingValue('module_review_request_delay_days', 7);
		$data['module_review_request_excluded_customer_group_ids'] = array_map('intval', (array)$this->getSettingValue('module_review_request_excluded_customer_group_ids', array()));
		$data['module_review_request_include_product_reviews'] = $this->getSettingValue('module_review_request_include_product_reviews', 1);
		$data['module_review_request_org_review_cooldown_days'] = $this->getSettingValue('module_review_request_org_review_cooldown_days', 180);
		$data['module_review_request_org_review_suppressed_mode'] = $this->getSettingValue('module_review_request_org_review_suppressed_mode', 'product_only');
		$data['module_review_request_track_review_clicks'] = $this->getSettingValue('module_review_request_track_review_clicks', 1);
		$data['module_review_request_review_bonus_points'] = $this->getSettingValue('module_review_request_review_bonus_points', 0);
		$data['module_review_request_order_status_ids'] = (array)$this->getSettingValue('module_review_request_order_status_ids', (array)$this->config->get('config_complete_status'));
		$data['module_review_request_email_subject'] = $this->getSettingValue('module_review_request_email_subject', $this->getDefaultEmailSubject($this->config->get('config_admin_language')));
		$data['module_review_request_email_body'] = $this->getSettingValue('module_review_request_email_body', $this->getDefaultEmailBody($this->config->get('config_admin_language')));

		$data['module_review_request_google_status'] = $this->getSettingValue('module_review_request_google_status', 0);
		$data['module_review_request_google_reference'] = $this->getSettingValue('module_review_request_google_reference', '');
		$data['module_review_request_google_review_url'] = $this->getSettingValue('module_review_request_google_review_url', '');
		$data['module_review_request_google_widget_code'] = $this->getSettingValue('module_review_request_google_widget_code', '');

		$data['module_review_request_yandex_status'] = $this->getSettingValue('module_review_request_yandex_status', 0);
		$data['module_review_request_yandex_reference'] = $this->getSettingValue('module_review_request_yandex_reference', '');
		$data['module_review_request_yandex_review_url'] = $this->getSettingValue('module_review_request_yandex_review_url', '');
		$data['module_review_request_yandex_widget_code'] = $this->getSettingValue('module_review_request_yandex_widget_code', '');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
		$data['cron_command'] = 'php admin/review_request_cron.php';

		$statistics_report = $this->model_extension_module_review_request->getStatisticsReport();
		$reply_channel_report = $this->model_extension_module_review_request->getReplyChannelStatisticsReport();
		$recent_review_replies = $this->model_extension_module_review_request->getRecentReviewReplies();
		$review_bonus_points = (int)$data['module_review_request_review_bonus_points'];

		$data['statistics_reports'] = array(
			array(
				'label' => $this->language->get('text_last_day'),
				'sent' => $statistics_report['day']['sent'],
				'replied' => $statistics_report['day']['replied'],
				'skipped' => $statistics_report['day']['skipped']
			),
			array(
				'label' => $this->language->get('text_last_week'),
				'sent' => $statistics_report['week']['sent'],
				'replied' => $statistics_report['week']['replied'],
				'skipped' => $statistics_report['week']['skipped']
			),
			array(
				'label' => $this->language->get('text_last_month'),
				'sent' => $statistics_report['month']['sent'],
				'replied' => $statistics_report['month']['replied'],
				'skipped' => $statistics_report['month']['skipped']
			),
			array(
				'label' => $this->language->get('text_all_time'),
				'sent' => $statistics_report['all']['sent'],
				'replied' => $statistics_report['all']['replied'],
				'skipped' => $statistics_report['all']['skipped']
			)
		);

		$data['statistics_channel_headers'] = array();

		foreach ($reply_channel_report['channels'] as $channel_code) {
			$data['statistics_channel_headers'][] = array(
				'code' => $channel_code,
				'label' => $this->getReplyChannelLabel($channel_code)
			);
		}

		$data['statistics_channel_reports'] = array(
			array(
				'label' => $this->language->get('text_last_day'),
				'counts' => $reply_channel_report['periods']['day']
			),
			array(
				'label' => $this->language->get('text_last_week'),
				'counts' => $reply_channel_report['periods']['week']
			),
			array(
				'label' => $this->language->get('text_last_month'),
				'counts' => $reply_channel_report['periods']['month']
			),
			array(
				'label' => $this->language->get('text_all_time'),
				'counts' => $reply_channel_report['periods']['all']
			)
		);

		$data['recent_review_replies'] = array();

		foreach ($recent_review_replies as $reply_info) {
			$reviewer = trim(html_entity_decode($reply_info['firstname'], ENT_QUOTES, 'UTF-8') . ' ' . html_entity_decode($reply_info['lastname'], ENT_QUOTES, 'UTF-8'));
			$customer_link = '';
			$award_bonus_link = '';
			$bonus_awarded = !empty($reply_info['review_bonus_awarded_at']);
			$can_award_bonus = false;
			$bonus_status = '';

			if ((int)$reply_info['customer_id'] > 0) {
				$customer_link = $this->url->link('customer/customer/edit', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . (int)$reply_info['customer_id'], true);
			}

			if (!$reviewer) {
				$reviewer = $reply_info['email'];
			}

			if ($bonus_awarded) {
				$bonus_status = sprintf($this->language->get('text_bonus_awarded'), (int)$reply_info['review_bonus_award_points'], date('d.m.Y H:i', strtotime($reply_info['review_bonus_awarded_at'])));
			} elseif ((int)$reply_info['customer_id'] <= 0) {
				$bonus_status = $this->language->get('text_bonus_guest');
			} elseif ($review_bonus_points <= 0) {
				$bonus_status = $this->language->get('text_bonus_disabled');
			} else {
				$can_award_bonus = true;
				$award_bonus_link = $this->url->link('extension/module/review_request/awardReviewBonus', 'user_token=' . $this->session->data['user_token'] . '&review_request_id=' . (int)$reply_info['review_request_id'], true) . '#tab-statistics';
			}

			$data['recent_review_replies'][] = array(
				'review_request_id' => (int)$reply_info['review_request_id'],
				'order_id' => (int)$reply_info['order_id'],
				'order_link' => $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . (int)$reply_info['order_id'], true),
				'customer_id' => (int)$reply_info['customer_id'],
				'customer_link' => $customer_link,
				'reviewer' => $reviewer,
				'email' => $reply_info['email'],
				'channel' => $this->getReplyChannelLabel($reply_info['reply_channel']),
				'date_sent' => $reply_info['date_sent'] ? date('d.m.Y H:i', strtotime($reply_info['date_sent'])) : '',
				'date_replied' => date('d.m.Y H:i', strtotime($reply_info['date_replied'])),
				'can_award_bonus' => $can_award_bonus,
				'award_bonus_link' => $award_bonus_link,
				'bonus_status' => $bonus_status
			);
		}

		$language_keys = array(
			'heading_title',
			'text_edit',
			'text_settings',
			'text_statistics',
			'text_enabled',
			'text_disabled',
			'text_general',
			'text_google',
			'text_yandex',
			'text_email',
			'text_storefront',
			'text_cron',
			'text_extension',
			'text_last_day',
			'text_last_week',
			'text_last_month',
			'text_all_time',
			'text_sent',
			'text_replied',
			'text_skipped',
			'text_reply_channels',
			'text_review_follow_up',
			'text_no_review_replies',
			'text_sent_label',
			'text_bonus_awarded',
			'text_bonus_disabled',
			'text_bonus_guest',
			'text_period',
			'entry_status',
			'entry_email_status',
			'entry_show_on_order_page',
			'entry_delay_days',
			'entry_order_statuses',
			'entry_excluded_customer_groups',
			'entry_include_product_reviews',
			'entry_org_review_cooldown_days',
			'entry_org_review_suppressed_mode',
			'entry_track_review_clicks',
			'entry_review_bonus_points',
			'entry_email_subject',
			'entry_email_body',
			'entry_google_status',
			'entry_google_reference',
			'entry_google_review_url',
			'entry_google_widget_code',
			'entry_yandex_status',
			'entry_yandex_reference',
			'entry_yandex_review_url',
			'entry_yandex_widget_code',
			'column_clicked',
			'column_reviewer',
			'column_channel',
			'column_order',
			'column_actions',
			'help_delay_days',
			'help_order_statuses',
			'help_excluded_customer_groups',
			'help_org_review_cooldown_days',
			'help_org_review_suppressed_mode',
			'help_track_review_clicks',
			'help_review_bonus_points',
			'help_email_subject',
			'help_email_body',
			'help_email_placeholders',
			'help_google_reference',
			'help_google_review_url',
			'help_yandex_reference',
			'help_yandex_review_url',
			'help_widget_code',
			'help_layout',
			'help_cron',
			'help_statistics',
			'help_statistics_tracking_disabled',
			'help_review_follow_up',
			'text_product_only',
			'text_skip_email',
			'button_order',
			'button_customer',
			'button_award_bonus',
			'button_reward_points',
			'button_save',
			'button_cancel'
		);

		foreach ($language_keys as $language_key) {
			$data[$language_key] = $this->language->get($language_key);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/review_request', $data));
	}

	public function install() {
		$this->load->model('extension/module/review_request');

		$this->model_extension_module_review_request->install();
	}

	public function uninstall() {
		$this->load->model('extension/module/review_request');

		$this->model_extension_module_review_request->uninstall();
	}

	public function awardReviewBonus() {
		$this->load->language('extension/module/review_request');
		$this->load->model('extension/module/review_request');

		if (!$this->user->hasPermission('modify', 'extension/module/review_request')) {
			$this->session->data['error_warning'] = $this->language->get('error_permission');
			$this->response->redirect($this->url->link('extension/module/review_request', 'user_token=' . $this->session->data['user_token'], true) . '#tab-statistics');
			return;
		}

		$this->upgradeModuleIfNeeded();

		$review_request_id = isset($this->request->get['review_request_id']) ? (int)$this->request->get['review_request_id'] : 0;
		$result = $this->model_extension_module_review_request->awardReviewBonus($review_request_id, (int)$this->user->getId());

		if (!empty($result['success'])) {
			$this->session->data['success'] = sprintf($this->language->get('text_review_bonus_success'), (int)$result['points']);
		} else {
			$error_key = 'error_review_bonus_' . (!empty($result['error_code']) ? $result['error_code'] : 'failed');
			$this->session->data['error_warning'] = $this->language->get($error_key);
		}

		$this->response->redirect($this->url->link('extension/module/review_request', 'user_token=' . $this->session->data['user_token'], true) . '#tab-statistics');
	}

	public function cron() {
		$this->log->write('Review Request: cron started');

		if (!$this->config->get('module_review_request_status') || !$this->config->get('module_review_request_email_status')) {
			$this->log->write('Review Request: cron skipped because module or email sending is disabled');
			echo "Review request cron skipped.\n";

			return;
		}

		$this->load->model('extension/module/review_request');
		$this->upgradeModuleIfNeeded();

		$requests = $this->model_extension_module_review_request->getDueRequests();
		$sent = 0;
		$failed = 0;
		$skipped = 0;

		foreach ($requests as $request_info) {
			try {
				$result = $this->sendReviewRequest($request_info);

				if ($result['status'] == 'skipped') {
					$this->model_extension_module_review_request->markSkipped($request_info['review_request_id'], $result['reason']);
					$skipped++;
					continue;
				}

				$this->model_extension_module_review_request->markSent($request_info['review_request_id']);

				if (!empty($result['organization_requested'])) {
					$this->model_extension_module_review_request->markOrganizationReviewSent($result['email'], $result['customer_id'], $result['order_id']);
				}

				$sent++;
			} catch (Exception $e) {
				$this->model_extension_module_review_request->markRetry($request_info['review_request_id'], $e->getMessage());
				$this->log->write('Review Request: send failed for order #' . (int)$request_info['order_id'] . ' - ' . $e->getMessage());
				$failed++;
			}
		}

		$this->log->write('Review Request: cron finished. Sent=' . $sent . ', Skipped=' . $skipped . ', Failed=' . $failed);

		echo 'Review request cron finished. Sent: ' . $sent . ', Skipped: ' . $skipped . ', Failed: ' . $failed . "\n";
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/review_request')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$delay_days = isset($this->request->post['module_review_request_delay_days']) ? $this->request->post['module_review_request_delay_days'] : 0;
		$org_cooldown_days = isset($this->request->post['module_review_request_org_review_cooldown_days']) ? $this->request->post['module_review_request_org_review_cooldown_days'] : 0;
		$review_bonus_points = isset($this->request->post['module_review_request_review_bonus_points']) ? $this->request->post['module_review_request_review_bonus_points'] : 0;

		if (!is_numeric($delay_days) || (int)$delay_days < 0) {
			$this->error['delay_days'] = $this->language->get('error_delay_days');
		}

		if (!is_numeric($org_cooldown_days) || (int)$org_cooldown_days < 0) {
			$this->error['org_cooldown_days'] = $this->language->get('error_org_cooldown_days');
		}

		if (!is_numeric($review_bonus_points) || (int)$review_bonus_points < 0) {
			$this->error['review_bonus_points'] = $this->language->get('error_review_bonus_points');
		}

		return !$this->error;
	}

	private function getSettingValue($key, $default = '') {
		if (isset($this->request->post[$key])) {
			return $this->request->post[$key];
		}

		$value = $this->config->get($key);

		if ($value === null || $value === '') {
			return $default;
		}

		return $value;
	}

	private function getReplyChannelLabel($channel_code) {
		if ($channel_code == 'google') {
			return $this->language->get('text_google');
		}

		if ($channel_code == 'yandex') {
			return $this->language->get('text_yandex');
		}

		return ucwords(str_replace(array('-', '_'), ' ', $channel_code));
	}

	private function sendReviewRequest($request_info) {
		$this->load->model('extension/module/review_request');
		$this->load->model('sale/order');
		$this->load->model('setting/setting');

		$order_info = $this->model_sale_order->getOrder($request_info['order_id']);

		if (!$order_info) {
			throw new Exception('Order not found');
		}

		if ($this->model_extension_module_review_request->isExcludedCustomerGroup(isset($order_info['customer_group_id']) ? $order_info['customer_group_id'] : 0)) {
			return array(
				'status' => 'skipped',
				'reason' => 'customer group is excluded from review requests'
			);
		}

		if (!$order_info['email']) {
			throw new Exception('Order has no customer email');
		}

		$language_code = $order_info['language_code'] ? $order_info['language_code'] : $this->config->get('config_language');
		$language = new Language($language_code);
		$language->load($language_code);
		$language->load('mail/review_request');

		$email = utf8_strtolower(trim($order_info['email']));
		$available_channels = $this->getOrganizationChannels($language);
		$organization_allowed = $available_channels && $this->model_extension_module_review_request->canAskOrganizationReview($email);
		$channels = $organization_allowed ? $this->getOrganizationChannels($language, (int)$request_info['review_request_id'], $order_info['store_url']) : array();
		$product_review_links = $this->getProductReviewLinks($order_info);
		$organization_suppressed = !$organization_allowed && !empty($available_channels);

		if ($organization_suppressed && (($this->config->get('module_review_request_org_review_suppressed_mode') ?: 'product_only') == 'skip_email')) {
			$product_review_links = array();
		}

		if (!$channels && !$product_review_links) {
			return array(
				'status' => 'skipped',
				'reason' => $organization_suppressed ? 'organization review is in cooldown and no product reviews remain' : 'no review targets configured'
			);
		}

		$store_name = html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8');
		$order_date = date('d.m.Y', strtotime($order_info['date_added']));

		$data['heading'] = sprintf($language->get('text_heading'), $store_name);
		$data['intro'] = $language->get('text_intro');
		$data['order_hint'] = sprintf($language->get('text_order_hint'), $order_info['order_id'], $order_date);
		$data['customer_firstname'] = html_entity_decode($order_info['firstname'], ENT_QUOTES, 'UTF-8');
		$data['customer_lastname'] = html_entity_decode($order_info['lastname'], ENT_QUOTES, 'UTF-8');
		$data['customer_name'] = trim($data['customer_firstname'] . ' ' . $data['customer_lastname']);
		$data['store_name'] = $store_name;
		$data['order_id'] = (int)$order_info['order_id'];
		$data['order_date'] = $order_date;
		$data['email_intro'] = $this->buildEmailIntro($language_code, $store_name, $order_info['order_id'], $order_date, !empty($channels));
		$data['organization_review_section'] = $this->renderOrganizationReviewSection($channels, $language_code, $order_info['order_id'], $store_name, $order_info['customer_id'] ? $this->getConfiguredReviewBonusPoints() : 0);
		$data['order_link'] = '';
		$data['google_review_url'] = '';
		$data['yandex_review_url'] = '';
		$data['google_button'] = '';
		$data['yandex_button'] = '';
		$data['review_buttons'] = '';
		$data['product_reviews_section'] = '';
		$data['order_button'] = '';

		foreach ($channels as $channel) {
			if ($channel['code'] == 'google') {
				$data['google_review_url'] = $channel['url'];
				$data['google_button'] = $this->renderEmailButton($channel['label'], $channel['url'], $channel['button_style']);
			}

			if ($channel['code'] == 'yandex') {
				$data['yandex_review_url'] = $channel['url'];
				$data['yandex_button'] = $this->renderEmailButton($channel['label'], $channel['url'], $channel['button_style']);
			}
		}

		$data['review_buttons'] = $this->renderReviewButtons($channels);

		if ($order_info['customer_id']) {
			$data['order_link'] = rtrim($order_info['store_url'], '/') . '/index.php?route=account/order/info&order_id=' . (int)$order_info['order_id'];
			$data['order_button'] = $this->renderEmailButton($language->get('text_view_order'), $data['order_link'], 'linear-gradient(135deg, #334155 0%, #0f172a 100%)');
		}

		$data['product_reviews_section'] = $this->renderProductReviewSection($product_review_links, $language->get('text_product_reviews'));

		$from = $this->model_setting_setting->getSettingValue('config_email', $order_info['store_id']);

		if (!$from) {
			$from = $this->config->get('config_email');
		}

		$subject_template = $this->config->get('module_review_request_email_subject');
		if (!$subject_template) {
			$subject_template = $this->getDefaultEmailSubject($language_code);
		}

		$body_template = $this->config->get('module_review_request_email_body');
		if (!$body_template) {
			$body_template = $this->getDefaultEmailBody($language_code);
		}

		$subject_template = html_entity_decode($subject_template, ENT_QUOTES, 'UTF-8');
		$body_template = html_entity_decode($body_template, ENT_QUOTES, 'UTF-8');

		$subject = $this->replacePlaceholders($subject_template, $data);
		$body = $this->replacePlaceholders($body_template, $data);

		$mail = new Mail($this->config->get('config_mail_engine'));
		$mail->parameter = $this->config->get('config_mail_parameter');
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

		$mail->setTo($order_info['email']);
		$mail->setFrom($from);
		$mail->setSender($store_name);
		$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
		$mail->setHtml(html_entity_decode($body, ENT_QUOTES, 'UTF-8'));
		$mail->send();

		return array(
			'status' => 'sent',
			'organization_requested' => !empty($channels),
			'email' => $email,
			'customer_id' => (int)$order_info['customer_id'],
			'order_id' => (int)$order_info['order_id']
		);
	}

	private function getOrganizationChannels($language, $review_request_id = 0, $store_url = '') {
		$channels = array();

		$config_map = array(
			'google' => array(
				'status' => 'module_review_request_google_status',
				'reference' => 'module_review_request_google_reference',
				'url' => 'module_review_request_google_review_url',
				'label' => $language->get('text_google')
			),
			'yandex' => array(
				'status' => 'module_review_request_yandex_status',
				'reference' => 'module_review_request_yandex_reference',
				'url' => 'module_review_request_yandex_review_url',
				'label' => $language->get('text_yandex')
			)
		);

		foreach ($config_map as $code => $config) {
			if (!$this->config->get($config['status'])) {
				continue;
			}

			$reference = trim(html_entity_decode((string)$this->config->get($config['reference']), ENT_QUOTES, 'UTF-8'));
			$url = trim(html_entity_decode((string)$this->config->get($config['url']), ENT_QUOTES, 'UTF-8'));

			if (!$url) {
				$url = $this->normalizeReviewUrl($code, $reference);
			}

			if (!$url) {
				continue;
			}

			if ($review_request_id && $store_url && $this->isReviewClickTrackingEnabled()) {
				$url = $this->buildTrackedReviewUrl($store_url, $review_request_id, $code);
			}

			$channels[] = array(
				'code' => $code,
				'label' => $config['label'],
				'url' => $url,
				'button_style' => $this->getReviewChannelButtonStyle($code)
			);
		}

		return $channels;
	}

	private function getProductReviewLinks($order_info) {
		if (!$this->config->get('module_review_request_include_product_reviews') || !$this->config->get('config_review_status')) {
			return array();
		}

		if (!$order_info['customer_id'] && !$this->config->get('config_review_guest')) {
			return array();
		}

		$this->load->model('sale/order');

		$order_products = $this->model_sale_order->getOrderProducts($order_info['order_id']);
		$review_links = array();
		$seen = array();

		foreach ($order_products as $order_product) {
			$product_id = (int)$order_product['product_id'];

			if (isset($seen[$product_id])) {
				continue;
			}

			$seen[$product_id] = true;
			$review_links[] = array(
				'name' => html_entity_decode($order_product['name'], ENT_QUOTES, 'UTF-8'),
				'url' => rtrim($order_info['store_url'], '/') . '/index.php?route=product/product&product_id=' . $product_id . '#tab-review'
			);
		}

		return $review_links;
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

	private function buildTrackedReviewUrl($store_url, $review_request_id, $channel) {
		return rtrim($store_url, '/') . '/index.php?route=extension/module/review_request/redirect&review_request_id=' . (int)$review_request_id . '&channel=' . rawurlencode($channel);
	}

	private function isReviewClickTrackingEnabled() {
		$value = $this->config->get('module_review_request_track_review_clicks');

		if ($value === null || $value === '') {
			return true;
		}

		return (bool)$value;
	}

	private function replacePlaceholders($template, $data) {
		foreach ($data as $key => $value) {
			if (!is_array($value) && !is_object($value)) {
				$template = str_replace('{' . $key . '}', $value, $template);
			}
		}

		return $template;
	}

	private function renderReviewButtons($channels) {
		$html = '';

		foreach ($channels as $channel) {
			$html .= $this->renderEmailButton($channel['label'], $channel['url'], $channel['button_style']);
		}

		return $html;
	}

	private function buildEmailIntro($language_code, $store_name, $order_id, $order_date, $has_organization_reviews) {
		$store_name = htmlspecialchars($store_name, ENT_QUOTES, 'UTF-8');

		if ($this->isRussianLanguage($language_code)) {
			if ($has_organization_reviews) {
				return '<p style="font-size: 16px; color: #374151; line-height: 1.6;">Спасибо за заказ в <strong>' . $store_name . '</strong>. Заказ <strong>#' . (int)$order_id . '</strong> от <strong>' . htmlspecialchars($order_date, ENT_QUOTES, 'UTF-8') . '</strong> завершен, и нам будет очень полезен ваш короткий отзыв.</p>';
			}

			return '<p style="font-size: 16px; color: #374151; line-height: 1.6;">Спасибо за заказ в <strong>' . $store_name . '</strong>. Заказ <strong>#' . (int)$order_id . '</strong> от <strong>' . htmlspecialchars($order_date, ENT_QUOTES, 'UTF-8') . '</strong> завершен. Если у вас есть пара минут, будем рады отзывам о товарах из этого заказа.</p>';
		}

		if ($has_organization_reviews) {
			return '<p style="font-size: 16px; color: #374151; line-height: 1.6;">Thank you for ordering from <strong>' . $store_name . '</strong>. Order <strong>#' . (int)$order_id . '</strong> from <strong>' . htmlspecialchars($order_date, ENT_QUOTES, 'UTF-8') . '</strong> is complete, and a short review would really help us.</p>';
		}

		return '<p style="font-size: 16px; color: #374151; line-height: 1.6;">Thank you for ordering from <strong>' . $store_name . '</strong>. Order <strong>#' . (int)$order_id . '</strong> from <strong>' . htmlspecialchars($order_date, ENT_QUOTES, 'UTF-8') . '</strong> is complete. If you have a moment, we would love to hear what you think about the products from this order.</p>';
	}

	private function renderOrganizationReviewSection($channels, $language_code, $order_id, $store_name, $review_bonus_points = 0) {
		if (!$channels) {
			return '';
		}

		$store_name = htmlspecialchars($store_name, ENT_QUOTES, 'UTF-8');
		$order_id = (int)$order_id;

		if ($this->isRussianLanguage($language_code)) {
			$html = '<div style="background: white; border-left: 4px solid #14b8a6; padding: 20px; margin: 20px 0; border-radius: 4px;">';
			$html .= '<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Что нам важно сейчас:</p>';
			$html .= '<p style="margin: 0; font-size: 22px; color: #0f766e; font-weight: bold;">Отзыв об организации в Google или Яндекс</p>';
			$html .= '</div>';
			$html .= '<div style="background: #ecfeff; border: 1px solid #a5f3fc; border-radius: 6px; padding: 15px; margin: 20px 0;">';
			$html .= '<p style="margin: 0; color: #155e75; font-size: 14px;"><strong>Подсказка:</strong><br>&#128172; Можно написать всего пару предложений о сервисе, доставке или качестве заказа<br>&#128197; Заказ: <strong>#' . $order_id . '</strong><br>&#127970; Магазин: <strong>' . $store_name . '</strong></p>';
			$html .= '</div>';
			$html .= $this->renderReviewBonusNotice($language_code, $review_bonus_points);
			$html .= '<p style="font-size: 16px; color: #374151; line-height: 1.6;">Выберите удобную площадку:</p>';
			$html .= '<div style="text-align: center; margin: 30px 0;">' . $this->renderReviewButtons($channels) . '</div>';

			return $html;
		}

		$html = '<div style="background: white; border-left: 4px solid #14b8a6; padding: 20px; margin: 20px 0; border-radius: 4px;">';
		$html .= '<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Best next step:</p>';
		$html .= '<p style="margin: 0; font-size: 22px; color: #0f766e; font-weight: bold;">Leave a store review on Google or Yandex</p>';
		$html .= '</div>';
		$html .= '<div style="background: #ecfeff; border: 1px solid #a5f3fc; border-radius: 6px; padding: 15px; margin: 20px 0;">';
		$html .= '<p style="margin: 0; color: #155e75; font-size: 14px;"><strong>Tip:</strong><br>&#128172; A few words about service, delivery, or the order experience are enough<br>&#128197; Order: <strong>#' . $order_id . '</strong><br>&#127970; Store: <strong>' . $store_name . '</strong></p>';
		$html .= '</div>';
		$html .= $this->renderReviewBonusNotice($language_code, $review_bonus_points);
		$html .= '<p style="font-size: 16px; color: #374151; line-height: 1.6;">Choose whichever platform is more convenient:</p>';
		$html .= '<div style="text-align: center; margin: 30px 0;">' . $this->renderReviewButtons($channels) . '</div>';

		return $html;
	}

	private function renderReviewBonusNotice($language_code, $review_bonus_points) {
		$review_bonus_points = (int)$review_bonus_points;

		if ($review_bonus_points <= 0) {
			return '';
		}

		if ($this->isRussianLanguage($language_code)) {
			return '<div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 15px; margin: 20px 0;">'
				. '<p style="margin: 0; color: #166534; font-size: 14px;"><strong>Бонус за отзыв:</strong><br>После проверки опубликованного отзыва начислим <strong>' . $review_bonus_points . ' бонусных баллов</strong> на ваш аккаунт.</p>'
				. '</div>';
		}

		return '<div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 15px; margin: 20px 0;">'
			. '<p style="margin: 0; color: #166534; font-size: 14px;"><strong>Review bonus:</strong><br>After we verify the published review, we will add <strong>' . $review_bonus_points . ' bonus points</strong> to your customer account.</p>'
			. '</div>';
	}

	private function getReviewChannelButtonStyle($code) {
		if ($code == 'google') {
			return array(
				'background' => '#ffffff',
				'text_color' => '#202124',
				'border' => '1px solid #dadce0',
				'box_shadow' => '0 1px 2px rgba(60, 64, 67, 0.18)',
				'icon_html' => $this->getReviewChannelIconHtml($code),
				'padding' => '14px 24px',
				'border_radius' => '8px'
			);
		}

		if ($code == 'yandex') {
			return array(
				'background' => 'linear-gradient(135deg, #fc3f1d 0%, #e02d0c 100%)',
				'text_color' => '#ffffff',
				'border' => '1px solid #d82a0c',
				'box_shadow' => '0 4px 10px rgba(252, 63, 29, 0.18)',
				'icon_html' => $this->getReviewChannelIconHtml($code),
				'padding' => '14px 24px',
				'border_radius' => '8px'
			);
		}

		return array();
	}

	private function getReviewChannelIconHtml($code) {
		if ($code == 'google') {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 18 18" aria-hidden="true"><path fill="#4285F4" d="M17.64 9.2045c0-.638-.0573-1.2518-.1636-1.8409H9v3.4818h4.8436c-.2086 1.125-.8427 2.0782-1.7963 2.7164v2.2582h2.9081c1.7018-1.5664 2.6846-3.8741 2.6846-6.6155z"/><path fill="#34A853" d="M9 18c2.43 0 4.4673-.8064 5.9564-2.1791l-2.9081-2.2582c-.8064.54-1.8368.8591-3.0482.8591-2.3441 0-4.3282-1.5845-5.0364-3.7109H.9573v2.3327C2.4382 15.9836 5.4818 18 9 18z"/><path fill="#FBBC05" d="M3.9636 10.7109C3.7832 10.1709 3.6818 9.5945 3.6818 9s.1014-1.1709.2818-1.7109V4.9573H.9573C.3477 6.1732 0 7.5491 0 9s.3477 2.8268.9573 4.0427l3.0063-2.3318z"/><path fill="#EA4335" d="M9 3.5782c1.3214 0 2.5077.4541 3.4405 1.345l2.5813-2.5814C13.4632.8918 11.43 0 9 0 5.4818 0 2.4382 2.0164.9573 4.9573l3.0063 2.3318C4.6718 5.1627 6.6559 3.5782 9 3.5782z"/></svg>';
		}

		if ($code == 'yandex') {
			return '<span style="display:inline-block; width:20px; height:20px; border-radius:5px; background:#ffffff; color:#fc3f1d; font-family:Arial, sans-serif; font-size:14px; font-weight:700; line-height:20px; text-align:center;">Я</span>';
		}

		return '';
	}

	private function renderEmailButton($label, $url, $style) {
		if (!$url) {
			return '';
		}

		$button_style = array(
			'background' => is_array($style) && !empty($style['background']) ? $style['background'] : (string)$style,
			'text_color' => is_array($style) && !empty($style['text_color']) ? $style['text_color'] : '#ffffff',
			'border' => is_array($style) && !empty($style['border']) ? $style['border'] : 'none',
			'box_shadow' => is_array($style) && !empty($style['box_shadow']) ? $style['box_shadow'] : 'none',
			'icon_html' => is_array($style) && !empty($style['icon_html']) ? $style['icon_html'] : '',
			'padding' => is_array($style) && !empty($style['padding']) ? $style['padding'] : '14px 28px',
			'border_radius' => is_array($style) && !empty($style['border_radius']) ? $style['border_radius'] : '6px'
		);

		$html = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; margin:0 12px 12px 0; padding:' . htmlspecialchars($button_style['padding'], ENT_QUOTES, 'UTF-8') . '; background:' . htmlspecialchars($button_style['background'], ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($button_style['text_color'], ENT_QUOTES, 'UTF-8') . '; text-decoration:none; border-radius:' . htmlspecialchars($button_style['border_radius'], ENT_QUOTES, 'UTF-8') . '; border:' . htmlspecialchars($button_style['border'], ENT_QUOTES, 'UTF-8') . '; box-shadow:' . htmlspecialchars($button_style['box_shadow'], ENT_QUOTES, 'UTF-8') . '; font-weight:bold; font-size:16px; font-family:Arial, sans-serif; white-space:nowrap;">';

		if ($button_style['icon_html']) {
			$html .= '<span style="display:inline-block; vertical-align:middle; line-height:0; margin-right:10px;">' . $button_style['icon_html'] . '</span>';
		}

		$html .= '<span style="display:inline-block; vertical-align:middle;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
		$html .= '</a>';

		return $html;
	}

	private function renderProductReviewSection($product_review_links, $heading) {
		if (!$product_review_links) {
			return '';
		}

		$html = '<div style="background:white; border-left:4px solid #14b8a6; padding:20px; margin:20px 0; border-radius:4px;">';
		$html .= '<p style="margin:0 0 12px; color:#6b7280; font-size:14px;">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</p>';
		$html .= '<ul style="margin:0; padding-left:18px;">';

		foreach ($product_review_links as $product_review_link) {
			$html .= '<li style="margin-bottom:10px; color:#374151;"><a href="' . htmlspecialchars($product_review_link['url'], ENT_QUOTES, 'UTF-8') . '" style="color:#0f766e; text-decoration:none; font-weight:bold;">' . htmlspecialchars($product_review_link['name'], ENT_QUOTES, 'UTF-8') . '</a></li>';
		}

		$html .= '</ul></div>';

		return $html;
	}

	private function getDefaultEmailSubject($language_code = '') {
		if ($this->isRussianLanguage($language_code)) {
			return '{store_name} - поделитесь впечатлением о заказе #{order_id}';
		}

		return '{store_name} - tell us about order #{order_id}';
	}

	private function getDefaultEmailBody($language_code = '') {
		if ($this->isRussianLanguage($language_code)) {
			return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
	<div style="background: linear-gradient(135deg, #14b8a6 0%, #0f766e 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
		<h1 style="color: white; margin: 0; font-size: 28px;">&#11088; Поделитесь впечатлением</h1>
	</div>

	<div style="background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px;">
		<p style="font-size: 16px; color: #374151;">Здравствуйте, <strong>{customer_firstname}</strong>!</p>
		{email_intro}
		{organization_review_section}

		{product_reviews_section}

		<div style="text-align: center; margin: 30px 0;">
			{order_button}
		</div>

		<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<p style="color: #6b7280; font-size: 14px; margin: 0;">
				Спасибо за ваше время и доверие.<br>
				<strong>{store_name}</strong>
			</p>
		</div>
	</div>
</div>';
		}

		return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
	<div style="background: linear-gradient(135deg, #14b8a6 0%, #0f766e 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
		<h1 style="color: white; margin: 0; font-size: 28px;">&#11088; Share your experience</h1>
	</div>

	<div style="background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px;">
		<p style="font-size: 16px; color: #374151;">Hello, <strong>{customer_firstname}</strong>!</p>
		{email_intro}
		{organization_review_section}

		{product_reviews_section}

		<div style="text-align: center; margin: 30px 0;">
			{order_button}
		</div>

		<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<p style="color: #6b7280; font-size: 14px; margin: 0;">
				Thank you for your time and trust.<br>
				<strong>{store_name}</strong>
			</p>
		</div>
	</div>
</div>';
	}

	private function isRussianLanguage($language_code) {
		return strpos((string)$language_code, 'ru') === 0 || (string)$language_code == 'russian';
	}

	private function getConfiguredReviewBonusPoints() {
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

	private function upgradeModuleIfNeeded() {
		if ($this->model_extension_module_review_request->needsUpgrade()) {
			$this->model_extension_module_review_request->install();
		}
	}
}
