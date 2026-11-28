<?php
/**
 * Admin controller for the AI Chatbot module.
 *
 * Scope: lives in Extensions > Modules, exactly like Racket Finder. Stores a
 * small settings record (status, chat service URL, shared secret, rate
 * limit, welcome message) through model/setting/module. Holds no racket
 * data and no LLM API key — the LLM key lives only in the Node service's
 * own environment, never here.
 *
 * Methods:
 *  - index():   render and save the settings form.
 *  - validate(): permission check used by index() before a save.
 */
class ControllerExtensionModuleAiChatbot extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/ai_chatbot');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/module');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('ai_chatbot', $this->request->post);
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$data['error_warning']        = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['error_name']           = isset($this->error['name']) ? $this->error['name'] : '';
		$data['error_chat_service_url'] = isset($this->error['chat_service_url']) ? $this->error['chat_service_url'] : '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/ai_chatbot', 'user_token=' . $this->session->data['user_token'], true)
			);

			$data['action'] = $this->url->link('extension/module/ai_chatbot', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/ai_chatbot', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);

			$data['action'] = $this->url->link('extension/module/ai_chatbot', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		// Pre-fill: POST (failed validation) > saved value > default.
		$fields = array(
			'name'                  => $this->language->get('heading_title'),
			'chat_service_url'      => '',
			'shared_secret'         => '',
			'rate_limit_per_minute' => 10,
			'welcome_message'       => '',
			'status'                => 0,
			'debug'                 => 0,
		);

		foreach ($fields as $field => $default) {
			if (isset($this->request->post[$field])) {
				$data[$field] = $this->request->post[$field];
			} elseif (isset($module_info[$field])) {
				$data[$field] = $module_info[$field];
			} else {
				$data[$field] = $default;
			}
		}

		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ai_chatbot', $data));
	}

	/**
	 * Permission + input guard for saving. shared_secret and chat_service_url
	 * are optional at save time (the module can be created disabled first,
	 * configured once the Node service is deployed) but chat() itself
	 * refuses to call out unless both are set — see the catalog controller.
	 */
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/ai_chatbot')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (isset($this->request->post['name']) && (strlen($this->request->post['name']) < 3 || strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		if (!empty($this->request->post['chat_service_url']) && !filter_var($this->request->post['chat_service_url'], FILTER_VALIDATE_URL)) {
			$this->error['chat_service_url'] = $this->language->get('error_chat_service_url');
		}

		return !$this->error;
	}
}
