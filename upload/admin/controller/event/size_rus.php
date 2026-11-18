<?php
/**
 * RUS-размеры обуви: события админки.
 * После сохранения товара пересобирает его значения в фильтре «Размер (RUS)».
 * Регистрация — install/size_rus_install.sql.
 */
class ControllerEventSizeRus extends Controller {

	/** admin/model/catalog/product/editProduct/after */
	public function productEdit(&$route, &$args, &$output) {
		if (!empty($args[0])) {
			$this->load->library('sizerus');
			$this->sizerus->syncFilter(array((int)$args[0]), true);
		}
	}

	/** admin/model/catalog/product/addProduct/after */
	public function productAdd(&$route, &$args, &$output) {
		if (!empty($output)) {
			$this->load->library('sizerus');
			$this->sizerus->syncFilter(array((int)$output), true);
		}
	}
}
