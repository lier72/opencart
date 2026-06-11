<?php
class ControllerExtensionFeedGoogleLocalInventory extends Controller {
	public function index() {
		if (!$this->config->get('feed_google_local_inventory_status')) {
			return;
		}

		$store_code = (string)$this->config->get('feed_google_local_inventory_store_code');

		if ($store_code === '') {
			return;
		}

		$this->load->model('extension/feed/google_base');
		$this->load->model('catalog/product');

		$output  = '<?xml version="1.0" encoding="UTF-8" ?>';
		$output .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
		$output .= '<channel>';
		$output .= '<title>' . $this->config->get('config_name') . '</title>';
		$output .= '<link>' . $this->config->get('config_url') . '</link>';
		$output .= '<description>Local inventory data</description>';

		$currencies = array('USD', 'EUR', 'GBP', 'RUB');
		if (in_array($this->session->data['currency'], $currencies)) {
			$currency_code  = $this->session->data['currency'];
			$currency_value = $this->currency->getValue($this->session->data['currency']);
		} else {
			$currency_code  = 'RUB';
			$currency_value = $this->currency->getValue('RUB');
		}

		$product_data = array();

		$google_base_categories = $this->model_extension_feed_google_base->getCategories();

		foreach ($google_base_categories as $google_base_category) {
			$filter_data = array(
				'filter_category_id' => $google_base_category['category_id'],
				'filter_filter'      => false
			);

			$products = $this->model_catalog_product->getProducts($filter_data);

			foreach ($products as $product) {
				if (in_array($product['product_id'], $product_data) || !$product['description']) {
					continue;
				}

				$product_data[] = $product['product_id'];

				$attrs = $this->model_extension_feed_google_base->getProductAttributes($product['product_id']);
				$type  = $this->detectProductType($product['name'], $attrs);

				$size_variants = array();
				if ($type === 'shoes' || $type === 'apparel') {
					$size_variants = $this->model_extension_feed_google_base->getProductSizeVariants($product['product_id']);
				}

				if ((float)$product['special']) {
					$price = $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id']), $currency_code, $currency_value, false);
				} else {
					$price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id']), $currency_code, $currency_value, false);
				}

				if (!empty($size_variants)) {
					foreach ($size_variants as $variant) {
						$qty = ($variant['subtract']) ? $variant['quantity'] : $product['quantity'];
						$availability = $qty > 0 ? 'in_stock' : 'out_of_stock';

						$output .= '<item>';
						$output .= '<g:store_code>' . htmlspecialchars($store_code, ENT_XML1) . '</g:store_code>';
						$output .= '<g:id>' . $product['product_id'] . ':' . $variant['option_value_id'] . '</g:id>';
						$output .= '<g:availability>' . $availability . '</g:availability>';
						$output .= '<g:quantity>' . $qty . '</g:quantity>';
						$output .= '<g:price>' . $price . '</g:price>';
						$output .= '</item>';
					}
				} else {
					$qty = (int)$product['quantity'];
					$availability = $qty > 0 ? 'in_stock' : 'out_of_stock';

					$output .= '<item>';
					$output .= '<g:store_code>' . htmlspecialchars($store_code, ENT_XML1) . '</g:store_code>';
					$output .= '<g:id>' . $product['product_id'] . '</g:id>';
					$output .= '<g:availability>' . $availability . '</g:availability>';
					$output .= '<g:quantity>' . $qty . '</g:quantity>';
					$output .= '<g:price>' . $price . '</g:price>';
					$output .= '</item>';
				}
			}
		}

		$output .= '</channel>';
		$output .= '</rss>';

		$this->response->addHeader('Content-Type: application/rss+xml');
		$this->response->setOutput($output);
	}

	/**
	 * Detects whether a product is footwear or apparel based on attributes then name keywords.
	 * Returns 'shoes', 'apparel', or null.
	 * Mirrors the same method in catalog/controller/extension/feed/google_base.php.
	 */
	private function detectProductType($product_name, $attributes) {
		if (isset($attributes['Материал кроссовок']) || isset($attributes['Материал подошвы']) || isset($attributes['Серия кроссовок'])) {
			return 'shoes';
		}
		$lower = mb_strtolower($product_name, 'UTF-8');
		foreach (array('кроссовк', 'тапочк', 'кеды', 'обувь') as $kw) {
			if (mb_strpos($lower, $kw) !== false) return 'shoes';
		}
		foreach (array('футболк', 'шорты', 'куртк', 'ветровк', 'брюки', 'носк', 'костюм', 'толстовк', 'поло', 'платье', 'юбк', 'майк', 'пуховик') as $kw) {
			if (mb_strpos($lower, $kw) !== false) return 'apparel';
		}
		return null;
	}
}
