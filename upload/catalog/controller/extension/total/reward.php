<?php
class ControllerExtensionTotalReward extends Controller {
	public function index() {
		// If bonus manager is active, don't show the default reward UI
		// Our integrated bonus display widget handles this now
		if ($this->config->get('module_bonus_manager_status')) {
			return '';
		}

		$points = $this->customer->getRewardPoints();

		$points_total = 0;

		foreach ($this->cart->getProducts() as $product) {
			if ($product['points']) {
				$points_total += $product['points'];
			}
		}

		// Modified: Allow using points even if products don't have "points price"
		// Use points as discount/cashback instead of alternative payment
		if ($points && $this->config->get('total_reward_status')) {
			$this->load->language('extension/total/reward');

			// If no points_total (no products with points price), allow using points as discount
			if (!$points_total) {
				// Get cart subtotal to calculate maximum usable points
				$this->load->model('extension/total/sub_total');

				$subtotal = 0;
				foreach ($this->cart->getProducts() as $product) {
					$subtotal += $product['total'];
				}

				// Get maximum usage percentage from configuration (default 30%)
				$max_usage_percent = (float)$this->config->get('module_bonus_manager_max_usage_percent') ?: 30;

				// Calculate maximum allowed bonus usage based on percentage
				$max_allowed_bonus = ($subtotal * $max_usage_percent) / 100;

				// Limit to: customer's points, max allowed bonus, or cart subtotal
				$points_total = min($points, $max_allowed_bonus, $subtotal);
			}

			$data['heading_title'] = sprintf($this->language->get('heading_title'), $points);

			$data['entry_reward'] = sprintf($this->language->get('entry_reward'), $points_total);

			if (isset($this->session->data['reward'])) {
				$data['reward'] = $this->session->data['reward'];
			} else {
				$data['reward'] = '';
			}

			return $this->load->view('extension/total/reward', $data);
		}
	}

	public function reward() {
		$this->load->language('extension/total/reward');

		$json = array();

		// Use centralized validation from bonus_manager model if module is enabled
		if ($this->config->get('module_bonus_manager_status')) {
			$this->load->model('extension/module/bonus_manager');
			$validation = $this->model_extension_module_bonus_manager->validateReward(
				isset($this->request->post['reward']) ? $this->request->post['reward'] : null
			);
		} else {
			// Fallback validation when bonus_manager is disabled
			$reward = isset($this->request->post['reward']) ? (int)$this->request->post['reward'] : 0;
			$points = $this->customer->getRewardPoints();

			if ($reward < 0) {
				$validation = array('valid' => false, 'error' => $this->language->get('error_reward'));
			} elseif ($reward > $points) {
				$validation = array('valid' => false, 'error' => sprintf($this->language->get('error_points'), $reward));
			} else {
				$validation = array('valid' => true, 'amount' => $reward);
			}
		}

		if (!$validation['valid']) {
			$json['error'] = $validation['error'];
		} else {
			// Valid - update session
			if ($validation['amount'] > 0) {
				$this->session->data['reward'] = $validation['amount'];
			} else {
				// Amount is 0 - clear reward
				unset($this->session->data['reward']);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			if (isset($this->request->post['redirect'])) {
				$json['redirect'] = $this->url->link($this->request->post['redirect']);
			} else {
				$json['redirect'] = $this->url->link('checkout/cart');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
