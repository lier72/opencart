<?php
/**
 * Account Adaptive Preferences Controller
 * Allows users to view and manage their personalized preferences
 */
class ControllerAccountAdaptivePreferences extends Controller {
	public function index() {
		// Check if user is logged in
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/adaptive_preferences', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/adaptive_preferences');
		$this->load->language('extension/module/adaptive_filter');
		$this->load->model('extension/module/adaptive_filter');

		$this->document->setTitle($this->language->get('heading_title'));

		// Breadcrumbs
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
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/adaptive_preferences', '', true)
		);

		// Success/error messages
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error'])) {
			$data['error'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error'] = '';
		}

		// Get user preferences
		$data['user_preferences'] = $this->model_extension_module_adaptive_filter->getPreferences();

		// Get smart sorting enabled status
		$data['smart_sorting_enabled'] = $this->model_extension_module_adaptive_filter->isSmartSortingEnabled();

		// Gender translations
		$data['gender_labels'] = array(
			'Men' => $this->language->get('gender_men'),
			'Women' => $this->language->get('gender_women'),
			'Children' => $this->language->get('gender_children')
		);

		// Add configured icons
		$data['shoe_size_icon'] = $this->config->get('module_adaptive_filter_shoe_size_icon') ?? '👟';
		$data['apparel_size_icon'] = $this->config->get('module_adaptive_filter_apparel_size_icon') ?? '👕';
		$data['color_icon'] = $this->config->get('module_adaptive_filter_color_icon') ?? '🎨';
		$data['default_sport_icon'] = $this->config->get('module_adaptive_filter_sport_icon') ?? '🎾';

		// Build sport icon map from language file
		$data['sport_icons'] = array();
		if (!empty($data['user_preferences']['sports'])) {
			foreach ($data['user_preferences']['sports'] as $sport => $count) {
				$sport_key = 'sport_icon_' . $sport;
				$sport_icon = $this->language->get($sport_key);

				if ($sport_icon === $sport_key) {
					$sport_icon = $this->language->get('sport_icon_default');
					if ($sport_icon === 'sport_icon_default') {
						$sport_icon = $data['default_sport_icon'];
					}
				}

				$data['sport_icons'][$sport] = $sport_icon;
			}
		}

		// Check if personalized sorting is enabled
		$data['personalized_enabled'] = $this->config->get('module_adaptive_filter_status');

		// Language strings
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_preferences_info'] = $this->language->get('text_preferences_info');
		$data['text_no_preferences'] = $this->language->get('text_no_preferences');
		$data['text_add_preference'] = $this->language->get('text_add_preference');
		$data['text_your_preferences'] = $this->language->get('text_your_preferences');
		$data['text_size'] = $this->language->get('text_size');
		$data['text_color'] = $this->language->get('text_color');
		$data['text_gender'] = $this->language->get('text_gender');
		$data['text_sport'] = $this->language->get('text_sport');
		$data['button_back'] = $this->language->get('button_back');
		$data['button_clear_all'] = $this->language->get('button_clear_all');
		$data['text_smart_sorting'] = $this->language->get('text_smart_sorting');
		$data['text_smart_sorting_info'] = $this->language->get('text_smart_sorting_info');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');

		// Load gender labels from language file
		$data['gender_labels']['Men'] = $this->language->get('gender_men');
		$data['gender_labels']['Women'] = $this->language->get('gender_women');
		$data['gender_labels']['Children'] = $this->language->get('gender_children');

		// URLs
		$data['action_remove'] = $this->url->link('account/adaptive_preferences/remove', '', true);
		$data['action_clear_all'] = $this->url->link('account/adaptive_preferences/clearAll', '', true);
		$data['back'] = $this->url->link('account/account', '', true);

		// Render template
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/adaptive_preferences', $data));
	}

	/**
	 * Clear all preferences
	 */
	public function clearAll() {
		if (!$this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/adaptive_preferences');
		$this->load->model('extension/module/adaptive_filter');

		// Clear all preferences directly in database
		$user_id = $this->customer->getId();
		$this->db->query("
			UPDATE " . DB_PREFIX . "user_preferences
			SET sizes = '{}',
				colors = '{}',
				genders = '{}',
				sports = '{}',
				last_updated = NOW()
			WHERE user_id = '" . (int)$user_id . "'
		");

		$this->session->data['success'] = $this->language->get('text_preferences_cleared');

		$this->response->redirect($this->url->link('account/adaptive_preferences', '', true));
	}
}
