<?php
/**
 * Product Family Controller
 * Displays product variants on the product page
 */
class ControllerExtensionModuleProductFamily extends Controller {

	public function index($args) {
		// Check if module is enabled
		if (!$this->config->get('module_product_family_status')) {
			return '';
		}

		// Get product_id from arguments or request
		$product_id = 0;

		if (isset($args['product_id'])) {
			$product_id = (int)$args['product_id'];
		} elseif (isset($this->request->get['product_id'])) {
			$product_id = (int)$this->request->get['product_id'];
		}

		if (!$product_id) {
			return '';
		}

		$this->load->language('extension/module/product_family');
		$this->load->model('extension/module/product_family');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_select'] = $this->language->get('text_select');
		$data['text_out_of_stock'] = $this->language->get('text_out_of_stock');

		// Get product family
		$data['products'] = $this->model_extension_module_product_family->getProductFamily($product_id);

		// Get current product variants for display
		$data['current_variants'] = $this->model_extension_module_product_family->getCurrentProductVariants($product_id);

		// Module settings - check per-category image display
		$data['show_image'] = $this->model_extension_module_product_family->shouldShowImages($product_id);
		$data['show_price'] = $this->config->get('module_product_family_show_price');

		// Only render if there are family members
		if (empty($data['products'])) {
			return '';
		}

		return $this->load->view('extension/module/product_family', $data);
	}
}
