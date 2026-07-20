<?php
class ControllerExtensionFeedGoogleBase extends Controller {
	private $cache_file      = '';
	private $cache_hash_file = '';

	public function index() {
		if (!$this->config->get('feed_google_base_status')) {
			return;
		}

		$this->cache_file      = DIR_CACHE . 'google_base_feed.xml';
		$this->cache_hash_file = DIR_CACHE . 'google_base_feed.hash';

		$this->load->model('extension/feed/google_base');

		if ($this->isCacheValid()) {
			$this->response->addHeader('Content-Type: application/atom+xml');
			$this->response->setOutput(file_get_contents($this->cache_file));
			return;
		}

		$this->load->model('tool/image');

		$google_base_categories = $this->model_extension_feed_google_base->getCategories();
		$category_ids = array_column($google_base_categories, 'category_id');

		if (empty($category_ids)) {
			return;
		}

		// ── 5 bulk queries replace ~88 000 per-product queries ────────────────
		$all_products   = $this->model_extension_feed_google_base->getAllFeedProducts($category_ids);
		$product_ids    = array_column($all_products, 'product_id');

		if (empty($product_ids)) {
			return;
		}

		$attrs_map      = $this->model_extension_feed_google_base->getAttributesMap($product_ids);
		$variants_map   = $this->model_extension_feed_google_base->getSizeVariantsMap($product_ids);
		$images_map     = $this->model_extension_feed_google_base->getImagesMap($product_ids);
		$breadcrumb_map = $this->model_extension_feed_google_base->getCategoryBreadcrumbsMap($product_ids);
		// ─────────────────────────────────────────────────────────────────────

		$currencies = array('USD', 'EUR', 'GBP', 'RUB');
		if (in_array($this->session->data['currency'], $currencies)) {
			$currency_code  = $this->session->data['currency'];
			$currency_value = $this->currency->getValue($this->session->data['currency']);
		} else {
			$currency_code  = 'RUB';
			$currency_value = $this->currency->getValue('RUB');
		}

		$output  = '<?xml version="1.0" encoding="UTF-8"?>';
		$output .= '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">';
		$output .= '<title>' . htmlspecialchars($this->config->get('config_name'), ENT_XML1, 'UTF-8') . '</title>';
		$output .= '<link rel="self" type="application/atom+xml" href="' . $this->config->get('config_url') . 'index.php?route=extension/feed/google_base"/>';

		foreach ($all_products as $product) {
			$pid   = (int)$product['product_id'];
			$attrs = isset($attrs_map[$pid]) ? $attrs_map[$pid] : array();
			$type  = $this->detectProductType($product['name'], $attrs);

			$size_variants = array();
			if ($type === 'shoes' || $type === 'apparel') {
				$size_variants = isset($variants_map[$pid]) ? $variants_map[$pid] : array();
			}

			$variant_list = !empty($size_variants) ? $size_variants : array(null);

			// Image tags — built once, shared across all variants of this product
			$image_tags = '';
			if ($product['image']) {
				$image_tags .= '<g:image_link>' . $this->model_tool_image->resize($product['image'], 500, 500) . '</g:image_link>';
			} else {
				$image_tags .= '<g:image_link></g:image_link>';
			}
			$extra_images = isset($images_map[$pid]) ? $images_map[$pid] : array();
			$img_count = 0;
			foreach ($extra_images as $img) {
				if ($img_count >= 10) break;
				$image_tags .= '<g:additional_image_link>' . $this->model_tool_image->resize($img, 500, 500) . '</g:additional_image_link>';
				$img_count++;
			}

			// Price tag — built once. Format: "15990.00 RUB" as required by Google.
			$raw_price = (float)$product['special'] ? $product['special'] : $product['price'];
			$taxed     = $this->tax->calculate($raw_price, $product['tax_class_id']);
			$converted = round($taxed * $currency_value, 2);
			$price_tag = '<g:price>' . number_format($converted, 2, '.', '') . ' ' . $currency_code . '</g:price>';

			// Category tags — built once
			$category_tags = '<g:google_product_category>' . (int)$product['google_base_category_id'] . '</g:google_product_category>';
			$crumbs = isset($breadcrumb_map[$pid]) ? $breadcrumb_map[$pid] : array();
			if (!empty($crumbs)) {
				// Google uses only the first product_type; pick the most specific (longest path).
				usort($crumbs, function($a, $b) { return strlen($b) - strlen($a); });
				$category_tags .= '<g:product_type><![CDATA[' . $crumbs[0] . ']]></g:product_type>';
			}

			// Color and material tags — built once
			$attr_tags = '';
			$color = '';
			if (!empty($attrs['Цвет'])) {
				$color = $this->stripColorHex($attrs['Цвет']);
			}
			if ($color) {
				$attr_tags .= '<g:color><![CDATA[' . $color . ']]></g:color>';
			}
			if ($type === 'shoes') {
				$mat_upper = !empty($attrs['Материал кроссовок']) ? $attrs['Материал кроссовок'] : 'SYNTHETIC LEATHER+TEXTILE';
				$mat_sole  = !empty($attrs['Материал подошвы'])   ? $attrs['Материал подошвы']   : 'RUBBER';
				$attr_tags .= '<g:material><![CDATA[' . $mat_upper . ' / ' . $mat_sole . ']]></g:material>';
			} elseif ($type === 'apparel') {
				$mat = !empty($attrs['Материал']) ? $attrs['Материал'] : 'SHELL:POLYESTER100%';
				$attr_tags .= '<g:material><![CDATA[' . $mat . ']]></g:material>';
			}

			foreach ($variant_list as $variant) {
				// Resolve quantity before writing the entry so we can skip out-of-stock.
				if ($variant !== null && $variant['subtract']) {
					$qty = $variant['quantity'];
				} else {
					$qty = (int)$product['quantity'];
				}
				if ($qty <= 0) continue;

				$output .= '<entry>';
				$output .= '<title><![CDATA[' . $product['name'] . ']]></title>';
				$output .= '<link rel="alternate" type="text/html" href="' . $this->url->link('product/product', 'product_id=' . $pid) . '"/>';
				$desc = strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8'));
				$desc = mb_substr($desc, 0, 1000, 'UTF-8');
				$output .= '<summary><![CDATA[' . $desc . ']]></summary>';
				$output .= '<g:brand><![CDATA[' . html_entity_decode($product['manufacturer'], ENT_QUOTES, 'UTF-8') . ']]></g:brand>';
				$output .= '<g:condition>new</g:condition>';

				if ($variant !== null) {
					$output .= '<g:id>' . $pid . ':' . $variant['option_value_id'] . '</g:id>';
					$output .= '<g:item_group_id>' . $pid . '</g:item_group_id>';
				} else {
					$output .= '<g:id>' . $pid . '</g:id>';
				}

				$output .= $image_tags;
				$output .= '<g:model_number>' . $product['model'] . '</g:model_number>';

				if ($product['model']) {
					$output .= '<g:mpn><![CDATA[' . $product['model'] . ']]></g:mpn>';
				} else {
					$output .= '<g:identifier_exists>false</g:identifier_exists>';
				}

				if ($product['upc']) {
					$output .= '<g:upc>' . $product['upc'] . '</g:upc>';
				}
				if ($product['ean']) {
					$output .= '<g:ean>' . $product['ean'] . '</g:ean>';
				}

				$output .= $price_tag;
				$output .= $category_tags;

				$output .= '<g:quantity>' . $qty . '</g:quantity>';
				$output .= '<g:weight>' . $this->formatWeight($product['weight'], $product['weight_class_id']) . '</g:weight>';
				$output .= '<g:availability>' . ($qty > 0 ? 'in_stock' : 'out_of_stock') . '</g:availability>';

				$output .= $attr_tags;

				if ($variant !== null) {
					$output .= '<g:size_system>EU</g:size_system>';
					$output .= '<g:size><![CDATA[' . $variant['size_display'] . ']]></g:size>';
				}

				$output .= '</entry>';
			}
		}

		$output .= '</feed>';

		$this->saveToCache($output);

		$this->response->addHeader('Content-Type: application/atom+xml');
		$this->response->setOutput($output);
	}

	// ── Cache helpers ─────────────────────────────────────────────────────────

	private function isCacheValid() {
		if (!file_exists($this->cache_file) || !file_exists($this->cache_hash_file)) {
			return false;
		}
		return $this->getFeedHash() === file_get_contents($this->cache_hash_file);
	}

	/**
	 * Lightweight hash of all feed-relevant product fields.
	 * Uses SUM(CRC32()) per row — no GROUP_CONCAT length limit, scales to any catalog size.
	 */
	private function getFeedHash() {
		$products_hash = $this->db->query("
			SELECT SUM(CRC32(CONCAT(
				p.product_id, '-', p.price, '-', p.quantity, '-', UNIX_TIMESTAMP(p.date_modified)
			))) AS h
			FROM `" . DB_PREFIX . "product` p
			JOIN `" . DB_PREFIX . "product_to_category` p2c ON p2c.product_id = p.product_id
			JOIN `" . DB_PREFIX . "google_base_category_to_category` gbc
				ON gbc.category_id = p2c.category_id
			WHERE p.status = '1'
		");

		$variants_hash = $this->db->query("
			SELECT SUM(CRC32(CONCAT(
				po.product_id, '-', pov.option_value_id, '-', pov.quantity
			))) AS h
			FROM `" . DB_PREFIX . "product_option_value` pov
			JOIN `" . DB_PREFIX . "product_option` po
				ON po.product_option_id = pov.product_option_id
		");

		$h1 = $products_hash->row['h'] ?? '0';
		$h2 = $variants_hash->row['h'] ?? '0';

		return md5($h1 . $h2);
	}

	private function saveToCache($content) {
		file_put_contents($this->cache_file, $content);
		file_put_contents($this->cache_hash_file, $this->getFeedHash());
	}

	// ── Product-type detection and color stripping ────────────────────────────

	/**
	 * Formats weight as "VALUE UNIT" with English unit abbreviation for Google.
	 * OpenCart's weight->format() returns Cyrillic units (e.g. "0.10кг"); this normalises them.
	 */
	private function formatWeight($value, $weight_class_id) {
		$str = $this->weight->format($value, $weight_class_id);
		$str = preg_replace('/([0-9])\s*кг/u',      '$1 kg', $str);
		$str = preg_replace('/([0-9])\s*г(?!г)/u',  '$1 g',  $str);
		$str = preg_replace('/([0-9])\s*фунт.*/u',  '$1 lb', $str);
		$str = preg_replace('/([0-9])\s*унц.*/u',   '$1 oz', $str);
		// If already English unit but missing space
		$str = preg_replace('/([0-9])(k?g|lb|oz)\b/', '$1 $2', $str);
		return $str;
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
}
