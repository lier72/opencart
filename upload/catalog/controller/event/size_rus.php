<?php
/**
 * RUS-размеры обуви: события витрины.
 * Регистрация — install/size_rus_install.sql.
 */
class ControllerEventSizeRus extends Controller {

	/**
	 * catalog/model/checkout/order/addOrderHistory/before (sort_order -10, раньше письма о заказе):
	 * дописывает " · RUS 40" к размеру в заказе — видно в письме, в ЛК и в админке.
	 */
	public function orderLabel(&$route, &$args) {
		if (empty($args[0])) {
			return;
		}

		$this->load->library('sizerus');
		$this->sizerus->labelOrder((int)$args[0]);
	}

	/**
	 * catalog/model/checkout/order/addOrderHistory/after:
	 * остатки могли измениться — пересобираем фильтр «Размер (RUS)» для товаров заказа.
	 */
	public function orderFilter(&$route, &$args, &$output) {
		if (empty($args[0])) {
			return;
		}

		$this->load->library('sizerus');
		$this->sizerus->syncFilter($this->sizerus->orderProductIds((int)$args[0]));
	}
}
