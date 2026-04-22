<?php
class ModelExtensionFeedGoogleBase extends Model {
    public function getCategories() {
		$query = $this->db->query("SELECT google_base_category_id, (SELECT name FROM `" . DB_PREFIX . "google_base_category` gbc WHERE gbc.google_base_category_id = gbc2c.google_base_category_id) AS google_base_category, category_id, (SELECT name FROM `" . DB_PREFIX . "category_description` cd WHERE cd.category_id = gbc2c.category_id AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS category FROM `" . DB_PREFIX . "google_base_category_to_category` gbc2c ORDER BY google_base_category ASC");

		return $query->rows;
    }

	public function getTotalCategories() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "google_base_category_to_category`");

		return $query->row['total'];
    }

	/**
	 * Returns product attributes as a name→value map for the store's default language.
	 * Use this to read Цвет, Материал кроссовок, Материал подошвы, Материал, etc.
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
	 * Returns all size option values available for a product (shoes and apparel).
	 * Each value is pre-formatted: EU label stripped from "Euro XS [Asia (S)]" → "XS",
	 * EU numeric stripped from "34 1/3 us(4,5)" → "34 1/3".
	 * Call for any product that may have size options before outputting g:size tags.
	 */
	public function getProductSizes($product_id) {
		$lang    = (int)$this->config->get('config_language_id');
		$names   = array(
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

	private function formatSize($raw) {
		// "Euro XS [Asia (S)]" → "XS"
		if (preg_match('/^Euro\s+(\S+)/u', $raw, $m)) {
			return $m[1];
		}
		// "34 1/3 us(4,5)" → "34 1/3"
		if (preg_match('/^(.+?)\s+us\(/ui', $raw, $m)) {
			return trim($m[1]);
		}
		return $raw;
	}
}
