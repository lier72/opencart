<?php
class ControllerCatalogFilterSeo extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('catalog/filter_seo');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/filter_seo');

		$this->getList();
	}

	public function form() {
		$this->load->language('catalog/filter_seo');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('catalog/filter_seo');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$type = $this->request->get['type'];
			$type_id = (int)$this->request->get['type_id'];

			$this->model_catalog_filter_seo->saveFacetConfig(
				$type,
				$type_id,
				trim((string)$this->request->post['prefix_override']),
				!empty($this->request->post['omit_facet_name']),
				!empty($this->request->post['strip_parenthetical']),
				!empty($this->request->post['strip_brackets'])
			);

			foreach ((array)$this->request->post['value_override'] as $override_key => $override) {
				$value = $this->decodeOverrideKey($type, $override_key);
				$this->model_catalog_filter_seo->saveValueOverride($type, $type_id, $value['value_id'], $value['value_text'], trim((string)$override));
			}

			$this->model_catalog_filter_seo->regenerateFacet($type, $type_id);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('catalog/filter_seo/form', 'user_token=' . $this->session->data['user_token'] . '&type=' . $type . '&type_id=' . $type_id, true));
		}

		$this->getForm();
	}

	public function regenerate() {
		$this->load->language('catalog/filter_seo');

		$this->load->model('catalog/filter_seo');

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$this->session->data['error'] = $this->language->get('error_permission');
		} elseif (!$this->user->hasPermission('modify', 'catalog/filter_seo')) {
			$this->session->data['error'] = $this->language->get('error_permission');
		} else {
			$type = $this->request->get['type'];
			$type_id = (int)$this->request->get['type_id'];

			$created = $this->model_catalog_filter_seo->regenerateFacet($type, $type_id);

			$this->session->data['success'] = sprintf($this->language->get('text_regenerated'), $created);
		}

		$this->response->redirect($this->url->link('catalog/filter_seo/form', 'user_token=' . $this->session->data['user_token'] . '&type=' . $this->request->get['type'] . '&type_id=' . (int)$this->request->get['type_id'], true));
	}

	/**
	 * Value override POST fields are named value_override[{key}] where {key}
	 * is an admin-safe encoding of the real override key (computeSlugValue()'s
	 * literal fa text, or the fo/ff numeric value_id) - literal Cyrillic/
	 * punctuation text isn't safe as an HTML name attribute, so the form
	 * uses base64 and this reverses it.
	 */
	private function decodeOverrideKey($type, $encoded_key) {
		$base64 = strtr($encoded_key, '-_', '+/');
		$base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
		$decoded = base64_decode($base64);

		if ($type === 'fa') {
			return array('value_id' => null, 'value_text' => $decoded);
		}

		return array('value_id' => (int)$decoded, 'value_text' => null);
	}

	protected function getList() {
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/filter_seo', 'user_token=' . $this->session->data['user_token'], true)
		);

		$language_id = (int)$this->config->get('config_language_id') ?: 1;

		$data['facets'] = array();

		foreach ($this->model_catalog_filter_seo->getFacets($language_id) as $facet) {
			$data['facets'][] = array_merge($facet, array(
				'type_label' => $this->language->get('text_type_' . $facet['type']),
				'edit'       => $this->url->link('catalog/filter_seo/form', 'user_token=' . $this->session->data['user_token'] . '&type=' . $facet['type'] . '&type_id=' . $facet['type_id'], true),
			));
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/filter_seo_list', $data));
	}

	protected function getForm() {
		$type = $this->request->get['type'];
		$type_id = (int)$this->request->get['type_id'];
		$language_id = (int)$this->config->get('config_language_id') ?: 1;

		$facet = $this->model_catalog_filter_seo->getFacetInfo($type, $type_id, $language_id);

		if ($facet === null) {
			$this->session->data['error'] = $this->language->get('error_facet_not_found');
			$this->response->redirect($this->url->link('catalog/filter_seo', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$data['facet'] = array_merge($facet, array('type_label' => $this->language->get('text_type_' . $type)));

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('catalog/filter_seo', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('catalog/filter_seo/form', 'user_token=' . $this->session->data['user_token'] . '&type=' . $type . '&type_id=' . $type_id, true);
		$data['regenerate'] = $this->url->link('catalog/filter_seo/regenerate', 'user_token=' . $this->session->data['user_token'] . '&type=' . $type . '&type_id=' . $type_id, true);
		$data['cancel'] = $this->url->link('catalog/filter_seo', 'user_token=' . $this->session->data['user_token'], true);

		if (isset($this->request->post['prefix_override'])) {
			$config = array(
				'prefix_override'     => $this->request->post['prefix_override'],
				'omit_facet_name'     => !empty($this->request->post['omit_facet_name']),
				'strip_parenthetical' => !empty($this->request->post['strip_parenthetical']),
				'strip_brackets'      => !empty($this->request->post['strip_brackets']),
			);
		} else {
			$config = $this->model_catalog_filter_seo->getFacetConfig($type, $type_id);
		}

		$data['config'] = $config;

		$data['values'] = array();

		foreach ($this->model_catalog_filter_seo->getFacetValues($type, $type_id, $language_id) as $value) {
			$override_key = $type === 'fa' ? $value['value_text'] : $value['value_id'];

			$data['values'][] = array_merge($value, array(
				'field_key' => rtrim(strtr(base64_encode((string)$override_key), '+/', '-_'), '='),
			));
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('catalog/filter_seo_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'catalog/filter_seo')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
