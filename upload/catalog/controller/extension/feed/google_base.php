<?php
class ControllerExtensionFeedGoogleBase extends Controller {
	public function index() {
		if ($this->config->get('feed_google_base_status')) {
			$output  = '<?xml version="1.0" encoding="UTF-8" ?>';
			$output .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
			$output .= '  <channel>';
			$output .= '  <title>' . $this->config->get('config_name') . '</title>';
			$output .= '  <description>' . $this->config->get('config_meta_description') . '</description>';
			$output .= '  <link>' . $this->config->get('config_url') . '</link>';

			$this->load->model('extension/feed/google_base');
			$this->load->model('catalog/category');
			$this->load->model('catalog/product');

			$this->load->model('tool/image');

			$product_data = array();

			$google_base_categories = $this->model_extension_feed_google_base->getCategories();

			foreach ($google_base_categories as $google_base_category) {
				$filter_data = array(
					'filter_category_id' => $google_base_category['category_id'],
					'filter_filter'      => false
				);

				$products = $this->model_catalog_product->getProducts($filter_data);

				foreach ($products as $product) {
					if (!in_array($product['product_id'], $product_data) && $product['description']) {
						
						$product_data[] = $product['product_id'];

						// Resolve currency
						$currencies = array('USD', 'EUR', 'GBP', 'RUB');
						if (in_array($this->session->data['currency'], $currencies)) {
							$currency_code  = $this->session->data['currency'];
							$currency_value = $this->currency->getValue($this->session->data['currency']);
						} else {
							$currency_code  = 'USD';
							$currency_value = $this->currency->getValue('USD');
						}

						// Detect product type and load size variants before building XML
						$attrs = $this->model_extension_feed_google_base->getProductAttributes($product['product_id']);
						$type  = $this->detectProductType($product['name'], $attrs);

						$size_variants = array();
						if ($type === 'shoes' || $type === 'apparel') {
							$size_variants = $this->model_extension_feed_google_base->getProductSizeVariants($product['product_id']);
						}

						// null entry = no size variant → emit one plain item
						$variant_list = !empty($size_variants) ? $size_variants : array(null);

						// Build image tags once (shared across all variants)
						$image_tags = '';
						if ($product['image']) {
							$image_tags .= '  <g:image_link>' . $this->model_tool_image->resize($product['image'], 500, 500) . '</g:image_link>';
						} else {
							$image_tags .= '  <g:image_link></g:image_link>';
						}
						$product_images = $this->model_catalog_product->getProductImages($product['product_id']);
						$image_count = 0;
						foreach ($product_images as $product_image) {
							if ($image_count >= 10) break;
							$image_tags .= '  <g:additional_image_link>' . $this->model_tool_image->resize($product_image['image'], 500, 500) . '</g:additional_image_link>';
							$image_count++;
						}

						// Build price tag once
						if ((float)$product['special']) {
							$price_tag = '  <g:price>' . $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id']), $currency_code, $currency_value, false) . '</g:price>';
						} else {
							$price_tag = '  <g:price>' . $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id']), $currency_code, $currency_value, false) . '</g:price>';
						}

						// Build category breadcrumb tags once
						$category_tags = '  <g:google_product_category>' . $google_base_category['google_base_category_id'] . '</g:google_product_category>';
						$categories = $this->model_catalog_product->getCategories($product['product_id']);
						foreach ($categories as $category) {
							$path = $this->getPath($category['category_id']);
							if ($path) {
								$string = '';
								foreach (explode('_', $path) as $path_id) {
									$category_info = $this->model_catalog_category->getCategory($path_id);
									if ($category_info) {
										$string = $string ? $string . ' &gt; ' . $category_info['name'] : $category_info['name'];
									}
								}
								$category_tags .= '<g:product_type><![CDATA[' . $string . ']]></g:product_type>';
							}
						}

						// Build color and material tags once
						$attr_tags = '';
						$color = '';
						if (!empty($attrs['Цвет'])) {
							$color = $this->stripColorHex($attrs['Цвет']);
						}
						if ($color) {
							$attr_tags .= '  <g:color><![CDATA[' . $color . ']]></g:color>';
						}
						if ($type === 'shoes') {
							$mat_upper = !empty($attrs['Материал кроссовок']) ? $attrs['Материал кроссовок'] : 'SYNTHETIC LEATHER+TEXTILE';
							$mat_sole  = !empty($attrs['Материал подошвы'])  ? $attrs['Материал подошвы']  : 'RUBBER';
							$attr_tags .= '  <g:material><![CDATA[' . $mat_upper . ' / ' . $mat_sole . ']]></g:material>';
						} elseif ($type === 'apparel') {
							$mat = !empty($attrs['Материал']) ? $attrs['Материал'] : 'SHELL:POLYESTER100%';
							$attr_tags .= '  <g:material><![CDATA[' . $mat . ']]></g:material>';
						}

						foreach ($variant_list as $variant) {
							$output .= '<item>';
							$output .= '<title><![CDATA[' . $product['name'] . ']]></title>';
							$output .= '<link>' . $this->url->link('product/product', 'product_id=' . $product['product_id']) . '</link>';
							$output .= '<description><![CDATA[' . strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8')) . ']]></description>';
							$output .= '<g:brand><![CDATA[' . html_entity_decode($product['manufacturer'], ENT_QUOTES, 'UTF-8') . ']]></g:brand>';
							$output .= '<g:condition>new</g:condition>';

							if ($variant !== null) {
								$output .= '<g:id>' . $product['product_id'] . ':' . $variant['option_value_id'] . '</g:id>';
								$output .= '<g:item_group_id>' . $product['product_id'] . '</g:item_group_id>';
							} else {
								$output .= '<g:id>' . $product['product_id'] . '</g:id>';
							}

							$output .= $image_tags;
							$output .= '  <g:model_number>' . $product['model'] . '</g:model_number>';

							if ($product['model']) {
								$output .= '  <g:mpn><![CDATA[' . $product['model'] . ']]></g:mpn>';
							} else {
								$output .= '  <g:identifier_exists>false</g:identifier_exists>';
							}

							if ($product['upc']) {
								$output .= '  <g:upc>' . $product['upc'] . '</g:upc>';
							}
							if ($product['ean']) {
								$output .= '  <g:ean>' . $product['ean'] . '</g:ean>';
							}

							$output .= $price_tag;
							$output .= $category_tags;

							// Per-variant quantity and availability
							if ($variant !== null && $variant['subtract']) {
								$qty = $variant['quantity'];
							} else {
								$qty = $product['quantity'];
							}
							$output .= '  <g:quantity>' . $qty . '</g:quantity>';
							$output .= '  <g:weight>' . $this->weight->format($product['weight'], $product['weight_class_id']) . '</g:weight>';
							$output .= '  <g:availability><![CDATA[' . ($qty ? 'in stock' : 'out of stock') . ']]></g:availability>';

							$output .= $attr_tags;

							if ($variant !== null) {
								$output .= '  <g:size_system>EU</g:size_system>';
								$output .= '  <g:size><![CDATA[' . $variant['size_display'] . ']]></g:size>';
							}

							$output .= '</item>';
						}
					}
				}
			}

			$output .= '  </channel>';
			$output .= '</rss>';

			$this->response->addHeader('Content-Type: application/rss+xml');
			$this->response->setOutput($output);
		}
	}

	/**
	 * Strips hex color code from attribute values like "Черный (#000000)" → "Черный".
	 */
	private function stripColorHex($color) {
		return trim(preg_replace('/\s*\(#[0-9A-Fa-f]+\)/u', '', $color));
	}

	/**
	 * Detects whether a product is footwear or apparel based on attributes then name keywords.
	 * Returns 'shoes', 'apparel', or null.
	 * Use before outputting g:material / g:size to pick correct fallbacks and field names.
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

	protected function getPath($parent_id, $current_path = '') {
		$category_info = $this->model_catalog_category->getCategory($parent_id);

		if ($category_info) {
			if (!$current_path) {
				$new_path = $category_info['category_id'];
			} else {
				$new_path = $category_info['category_id'] . '_' . $current_path;
			}

			$path = $this->getPath($category_info['parent_id'], $new_path);

			if ($path) {
				return $path;
			} else {
				return $new_path;
			}
		}
	}
}
