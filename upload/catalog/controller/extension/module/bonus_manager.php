<?php
class ControllerExtensionModuleBonusManager extends Controller {

	/**
	 * Event handler: Award bonuses when order status changes to "Complete"
	 * This is triggered by the event system
	 */
	public function awardBonusesOnOrderComplete(&$route, &$args, &$output) {
		// Check if module is enabled
		if (!$this->config->get('module_bonus_manager_status')) {
			return;
		}

		// Get order_id and new order_status_id from args
		$order_id = isset($args[0]) ? (int)$args[0] : 0;
		$order_status_id = isset($args[1]) ? (int)$args[1] : 0;

		if (!$order_id || !$order_status_id) {
			return;
		}

		// Check if this is the configured accrual status
		$accrual_status_id = (int)$this->config->get('module_bonus_manager_accrual_status_id');

		if ($accrual_status_id <= 0) {
			$accrual_status_id = 5; // Default to "Complete" status
		}

		// If order status matches accrual status, award bonuses
		if ($order_status_id == $accrual_status_id) {
			$this->load->model('extension/module/bonus_manager');
			$this->model_extension_module_bonus_manager->awardBonusesForOrder($order_id);
		}
	}
}
