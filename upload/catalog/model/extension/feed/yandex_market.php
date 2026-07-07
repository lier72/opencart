<?php
class ModelExtensionFeedYandexMarket extends Model {
	public function getCategory() {
		$query = $this->db->query("SELECT cd.name, c.category_id, c.parent_id FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'  AND c.status = '1' AND c.sort_order <> '-1'");

		return $query->rows;
	}

	public function getProduct($allowed_categories, $out_of_stock_id, $vendor_required = true) {
		$query = $this->db->query("SELECT p.*, pd.name, pd.description, m.name AS manufacturer, MIN(p2c.category_id) AS category_id, IFNULL(ps.price, p.price) AS price FROM " . DB_PREFIX . "product p JOIN " . DB_PREFIX . "product_to_category AS p2c ON (p.product_id = p2c.product_id) " . ($vendor_required ? '' : 'LEFT ') . "JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) LEFT JOIN " . DB_PREFIX . "product_special ps ON (p.product_id = ps.product_id) AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ps.date_start < NOW() AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()) WHERE p2c.category_id IN (" . $this->db->escape($allowed_categories) . ") AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.date_available <= NOW() AND p.status = '1' AND (p.quantity > '0' OR p.stock_status_id != '" . (int)$out_of_stock_id . "') GROUP BY p.product_id");

		return $query->rows;
	}

	/**
	 * Returns product attributes as a name→value map for the store's default language.
	 * Use to read Цвет, Материал кроссовок, Материал подошвы, Материал, Серия кроссовок, etc.
	 */
	public function getProductAttributes($product_id) {
		$lang = (int)$this->config->get('config_language_id');
		$query = $this->db->query("
			SELECT ad.name, pav.text
			FROM `" . DB_PREFIX . "attribute` a
			JOIN `" . DB_PREFIX . "attribute_description` ad
				ON ad.attribute_id = a.attribute_id AND ad.language_id = '" . $lang . "'
			JOIN `" . DB_PREFIX . "product_attribute` pav
				ON pav.attribute_id = a.attribute_id
				AND pav.product_id = '" . (int)$product_id . "'
				AND pav.language_id = '" . $lang . "'
		");
		$attributes = array();
		foreach ($query->rows as $row) {
			$attributes[$row['name']] = $row['text'];
		}
		return $attributes;
	}

	/**
	 * Returns all size option values for a product with their option_value_id and formatted display name.
	 * Used by the YML feed to generate one offer per size variant with id="{product_id}:{option_value_id}",
	 * matching the YCP productId encoding convention used in catalog/controller/api/ycp.php.
	 *
	 * Returns array of ['option_value_id' => int, 'size_display' => string].
	 * Returns empty array if the product has no size options.
	 */
	public function getProductSizeVariants($product_id) {
		$lang  = (int)$this->config->get('config_language_id');
		$names = array(
			'Размер детской обуви (US)',
			'Размер женской обуви (US)',
			'Размер обуви baby',
			'Размер обуви унисекс (US)',
			'Размер детской одежды',
			'Размер одежды',
		);
		$in = implode(',', array_map(function($n) { return "'" . $this->db->escape($n) . "'"; }, $names));

		$query = $this->db->query("
			SELECT ov.option_value_id, ovd.name AS size_value,
			       pov.quantity, pov.subtract
			FROM `" . DB_PREFIX . "option` o
			JOIN `" . DB_PREFIX . "option_description` od
				ON od.option_id = o.option_id AND od.language_id = '" . $lang . "'
			JOIN `" . DB_PREFIX . "product_option` po
				ON po.option_id = o.option_id AND po.product_id = '" . (int)$product_id . "'
			JOIN `" . DB_PREFIX . "product_option_value` pov
				ON pov.product_option_id = po.product_option_id
			JOIN `" . DB_PREFIX . "option_value` ov
				ON ov.option_value_id = pov.option_value_id
			JOIN `" . DB_PREFIX . "option_value_description` ovd
				ON ovd.option_value_id = ov.option_value_id AND ovd.language_id = '" . $lang . "'
			WHERE od.name IN (" . $in . ")
			GROUP BY ov.option_value_id
			ORDER BY ov.sort_order
		");

		$variants = array();
		foreach ($query->rows as $row) {
			$variants[] = array(
				'option_value_id' => (int)$row['option_value_id'],
				'size_display'    => $this->formatSize($row['size_value']),
				'quantity'        => (int)$row['quantity'],
				'subtract'        => (bool)$row['subtract']
			);
		}
		return $variants;
	}

	/**
	 * Returns all available size option values for a product, pre-formatted to EU labels only.
	 * "Euro XS [Asia (S)]" → "XS", "34 1/3 us(4,5)" → "34 1/3".
	 * Call before building param array for shoes/apparel offers.
	 */
	public function getProductSizes($product_id) {
		$lang  = (int)$this->config->get('config_language_id');
		$names = array(
			'Размер детской обуви (US)',
			'Размер женской обуви (US)',
			'Размер обуви baby',
			'Размер обуви унисекс (US)',
			'Размер детской одежды',
			'Размер одежды',
		);
		$in = implode(',', array_map(function($n) { return "'" . $this->db->escape($n) . "'"; }, $names));

		$query = $this->db->query("
			SELECT ovd.name AS size_value
			FROM `" . DB_PREFIX . "option` o
			JOIN `" . DB_PREFIX . "option_description` od
				ON od.option_id = o.option_id AND od.language_id = '" . $lang . "'
			JOIN `" . DB_PREFIX . "product_option` po
				ON po.option_id = o.option_id AND po.product_id = '" . (int)$product_id . "'
			JOIN `" . DB_PREFIX . "product_option_value` pov
				ON pov.product_option_id = po.product_option_id
			JOIN `" . DB_PREFIX . "option_value` ov
				ON ov.option_value_id = pov.option_value_id
			JOIN `" . DB_PREFIX . "option_value_description` ovd
				ON ovd.option_value_id = ov.option_value_id AND ovd.language_id = '" . $lang . "'
			WHERE od.name IN (" . $in . ")
			ORDER BY ov.sort_order
		");

		$sizes = array();
		foreach ($query->rows as $row) {
			$sizes[] = $this->formatSize($row['size_value']);
		}
		return $sizes;
	}

	/**
	 * Strips vendor-specific prefixes/suffixes from raw size labels.
	 * "Euro XS [Asia (S)]" → "XS", "34 1/3 us(4,5)" → "34 1/3".
	 * Used by both the YML feed and the YCP basket check response.
	 */
	public function formatSize($raw) {
		if (preg_match('/^Euro\s+(\S+)/u', $raw, $m)) return $m[1];
		if (preg_match('/^(.+?)\s+us\(/ui', $raw, $m)) return trim($m[1]);
		return $raw;
	}

	/**
	 * Resolves the Yandex size characteristic code from an option group name.
	 *
	 * The code only needs to be consistent across all variants of the same product —
	 * Yandex uses it to group alternatives, not as a strict server-side enum.
	 *
	 * Resolution order:
	 *   1. Exact match against known option names (most precise).
	 *   2. Keyword scan (fallback for option names not in the exact map).
	 *   3. Default: SIZES_CLOTHES.
	 *
	 * Known codes used:
	 *   SIZES_CLOTHES    — adult apparel (Размер одежды)
	 *   SIZE_KID_CLOTHES — children's apparel (Размер детской одежды)
	 *   SIZE_SHOES       — adult footwear (женская, унисекс)
	 *   SIZE_KID_SHOES   — children's footwear (Размер детской обуви)
	 *   SIZE_BABY_SHOES  — baby footwear (Размер обуви baby)
	 *
	 * Used by both the YML feed and the YCP basket check response.
	 */
	public function getSizeCode($option_name) {
		$exact = [
			'Размер одежды'             => 'SIZES_CLOTHES',
			'Размер детской одежды'     => 'SIZE_KID_CLOTHES',
			'Размер женской обуви (US)' => 'SIZE_SHOES',
			'Размер обуви унисекс (US)' => 'SIZE_SHOES',
			'Размер детской обуви (US)' => 'SIZE_KID_SHOES',
			'Размер обуви baby'         => 'SIZE_BABY_SHOES',
		];

		if (isset($exact[$option_name])) {
			return $exact[$option_name];
		}

		$lower = mb_strtolower((string)$option_name, 'UTF-8');

		$keywords = [
			'SIZE_BABY_SHOES' => ['baby', 'беби', 'малыш'],
			'SIZE_KID_SHOES'  => ['детск', 'child', 'kid'],
			'SIZE_SHOES'      => ['обув', 'ботин', 'кроссов', 'shoe', 'boot', 'sneaker'],
			'SIZE_KID_CLOTHES'=> ['детск', 'ребен', 'child', 'kid'],
		];

		foreach ($keywords as $code => $kws) {
			foreach ($kws as $kw) {
				if (mb_strpos($lower, $kw) !== false) {
					return $code;
				}
			}
		}

		return 'SIZES_CLOTHES';
	}
}
