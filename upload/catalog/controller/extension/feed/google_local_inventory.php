<?php
class ControllerExtensionFeedGoogleLocalInventory extends Controller {
	private $cache_file      = '';
	private $cache_hash_file = '';

	public function index() {
		if (!$this->config->get('feed_google_local_inventory_status')) {
			return;
		}

		$store_code = (string)$this->config->get('feed_google_local_inventory_store_code');
		if ($store_code === '') {
			return;
		}

		$this->cache_file      = DIR_CACHE . 'google_local_inventory_feed.xml';
		$this->cache_hash_file = DIR_CACHE . 'google_local_inventory_feed.hash';

		$this->load->model('extension/feed/google_base');

		if ($this->isCacheValid()) {
			$this->response->addHeader('Content-Type: application/atom+xml');
			$this->response->setOutput(file_get_contents($this->cache_file));
			return;
		}

		$google_base_categories = $this->model_extension_feed_google_base->getCategories();
		$category_ids = array_column($google_base_categories, 'category_id');

		if (empty($category_ids)) {
			return;
		}

		// ── 3 bulk queries replace ~15 000 per-product queries ────────────────
		$all_products = $this->model_extension_feed_google_base->getAllFeedProducts($category_ids);
		$product_ids  = array_column($all_products, 'product_id');

		if (empty($product_ids)) {
			return;
		}

		$attrs_map    = $this->model_extension_feed_google_base->getAttributesMap($product_ids);
		$variants_map = $this->model_extension_feed_google_base->getSizeVariantsMap($product_ids);
		// ─────────────────────────────────────────────────────────────────────

		$currencies = array('USD', 'EUR', 'GBP', 'RUB');
		if (in_array($this->session->data['currency'], $currencies)) {
			$currency_code  = $this->session->data['currency'];
			$currency_value = $this->currency->getValue($this->session->data['currency']);
		} else {
			$currency_code  = 'RUB';
			$currency_value = $this->currency->getValue('RUB');
		}

		$store_code_xml = htmlspecialchars($store_code, ENT_XML1);

		$output  = '<?xml version="1.0" encoding="UTF-8"?>';
		$output .= '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">';
		$output .= '<title>' . htmlspecialchars($this->config->get('config_name'), ENT_XML1, 'UTF-8') . ' — Local Inventory</title>';
		$output .= '<link rel="self" type="application/atom+xml" href="' . $this->config->get('config_url') . 'index.php?route=extension/feed/google_local_inventory"/>';

		foreach ($all_products as $product) {
			$pid   = (int)$product['product_id'];
			$attrs = isset($attrs_map[$pid]) ? $attrs_map[$pid] : array();
			$type  = $this->detectProductType($product['name'], $attrs);

			$size_variants = array();
			if ($type === 'shoes' || $type === 'apparel') {
				$size_variants = isset($variants_map[$pid]) ? $variants_map[$pid] : array();
			}

			$raw_price  = (float)$product['special'] ? $product['special'] : $product['price'];
			$taxed      = $this->tax->calculate($raw_price, $product['tax_class_id']);
			$converted  = round($taxed * $currency_value, 2);
			$price      = number_format($converted, 2, '.', '') . ' ' . $currency_code;

			if (!empty($size_variants)) {
				foreach ($size_variants as $variant) {
					$qty          = $variant['subtract'] ? (int)$variant['quantity'] : (int)$product['quantity'];
					$availability = $qty > 0 ? 'in_stock' : 'out_of_stock';

					$output .= '<entry>';
					$output .= '<g:store_code>' . $store_code_xml . '</g:store_code>';
					$output .= '<g:id>' . $pid . ':' . $variant['option_value_id'] . '</g:id>';
					$output .= '<g:availability>' . $availability . '</g:availability>';
					$output .= '<g:quantity>' . $qty . '</g:quantity>';
					$output .= '<g:price>' . $price . '</g:price>';
					$output .= '</entry>';
				}
			} else {
				$qty          = (int)$product['quantity'];
				$availability = $qty > 0 ? 'in_stock' : 'out_of_stock';

				$output .= '<entry>';
				$output .= '<g:store_code>' . $store_code_xml . '</g:store_code>';
				$output .= '<g:id>' . $pid . '</g:id>';
				$output .= '<g:availability>' . $availability . '</g:availability>';
				$output .= '<g:quantity>' . $qty . '</g:quantity>';
				$output .= '<g:price>' . $price . '</g:price>';
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
	 * Lightweight hash covering quantity, price and date_modified for all feed products,
	 * plus per-variant option quantities. Uses SUM(CRC32()) — no GROUP_CONCAT length limit.
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

	// ── Product-type detection ────────────────────────────────────────────────

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
