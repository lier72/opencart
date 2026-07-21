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
	 * Returns product attributes as a name→value map for one product.
	 * Kept for backward compatibility; use getAttributesMap() in feed generation.
	 */
	public function getProductAttributes($product_id) {
		$lang  = (int)$this->config->get('config_language_id');
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
	 * Returns size variants for one product.
	 * Kept for backward compatibility; use getSizeVariantsMap() in feed generation.
	 */
	public function getProductSizeVariants($product_id) {
		$lang  = (int)$this->config->get('config_language_id');
		$names = $this->sizeOptionNames();
		$in    = implode(',', array_map(function($n) { return "'" . $this->db->escape($n) . "'"; }, $names));

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
			ORDER BY ov.sort_order
		");

		$variants = array();
		foreach ($query->rows as $row) {
			$variants[] = array(
				'option_value_id' => (int)$row['option_value_id'],
				'size_display'    => $this->formatSize($row['size_value']),
				'quantity'        => (int)$row['quantity'],
				'subtract'        => (bool)$row['subtract'],
			);
		}
		return $variants;
	}

	// -------------------------------------------------------------------------
	// Bulk methods — used by feed controllers to load all data in one pass.
	// Each replaces N per-product queries with a single IN(...) query.
	// -------------------------------------------------------------------------

	/**
	 * Returns all products in the given feed categories in one query.
	 * Replaces the per-category getProducts() loop. Includes google_base_category_id
	 * (lowest mapped value when a product belongs to multiple feed categories) and
	 * the active special price.
	 * Use in the Google Base and Local Inventory feed controllers.
	 */
	public function getAllFeedProducts(array $category_ids) {
		if (empty($category_ids)) return array();

		$lang     = (int)$this->config->get('config_language_id');
		$store_id = (int)$this->config->get('config_store_id');
		$in       = implode(',', array_map('intval', $category_ids));

		$query = $this->db->query("
			SELECT p.product_id, pd.name, pd.description,
			       COALESCE(m.name, '') AS manufacturer,
			       p.model, p.image, p.upc, p.ean, p.price,
			       p.tax_class_id, p.quantity, p.weight, p.weight_class_id,
			       p.stock_status_id, p.date_available,
			       feed.google_base_category_id,
			       (SELECT ps.price
			        FROM `" . DB_PREFIX . "product_special` ps
			        WHERE ps.product_id = p.product_id
			          AND ps.customer_group_id = '1'
			          AND (ps.date_start = '0000-00-00' OR ps.date_start <= NOW())
			          AND (ps.date_end   = '0000-00-00' OR ps.date_end   >= NOW())
			        ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special
			FROM `" . DB_PREFIX . "product` p
			JOIN `" . DB_PREFIX . "product_description` pd
				ON pd.product_id = p.product_id AND pd.language_id = '" . $lang . "'
			LEFT JOIN `" . DB_PREFIX . "manufacturer` m
				ON m.manufacturer_id = p.manufacturer_id
			JOIN `" . DB_PREFIX . "product_to_store` p2s
				ON p2s.product_id = p.product_id AND p2s.store_id = '" . $store_id . "'
			JOIN (
				SELECT p2c.product_id, MIN(gbc2c.google_base_category_id) AS google_base_category_id
				FROM `" . DB_PREFIX . "product_to_category` p2c
				JOIN `" . DB_PREFIX . "google_base_category_to_category` gbc2c
					ON gbc2c.category_id = p2c.category_id
				WHERE gbc2c.category_id IN (" . $in . ")
				GROUP BY p2c.product_id
			) feed ON feed.product_id = p.product_id
			WHERE p.status = '1'
			  AND (p.quantity > 0 OR p.stock_status_id IN (6, 8))
			  AND (p.date_available <= NOW() OR p.stock_status_id IN (6, 8))
			  AND pd.description != ''
			ORDER BY p.product_id ASC
		");

		return $query->rows;
	}

	/**
	 * Returns all attributes for the given products in one query.
	 * Returns [product_id => [attribute_name => value]].
	 * Use instead of calling getProductAttributes() per product.
	 */
	public function getAttributesMap(array $product_ids) {
		if (empty($product_ids)) return array();

		$lang = (int)$this->config->get('config_language_id');
		$in   = implode(',', array_map('intval', $product_ids));

		$query = $this->db->query("
			SELECT pav.product_id, ad.name, pav.text
			FROM `" . DB_PREFIX . "product_attribute` pav
			JOIN `" . DB_PREFIX . "attribute_description` ad
				ON ad.attribute_id = pav.attribute_id AND ad.language_id = '" . $lang . "'
			WHERE pav.product_id IN (" . $in . ")
			  AND pav.language_id = '" . $lang . "'
		");

		$map = array();
		foreach ($query->rows as $row) {
			$map[(int)$row['product_id']][$row['name']] = $row['text'];
		}
		return $map;
	}

	/**
	 * Returns all size variants for the given products in one query.
	 * Returns [product_id => [[option_value_id, size_display, quantity, subtract], ...]].
	 * Use instead of calling getProductSizeVariants() per product.
	 */
	public function getSizeVariantsMap(array $product_ids) {
		if (empty($product_ids)) return array();

		$lang  = (int)$this->config->get('config_language_id');
		$in    = implode(',', array_map('intval', $product_ids));
		$names = $this->sizeOptionNames();
		$nin   = implode(',', array_map(function($n) { return "'" . $this->db->escape($n) . "'"; }, $names));

		$query = $this->db->query("
			SELECT po.product_id, ov.option_value_id, ovd.name AS size_value,
			       pov.quantity, pov.subtract
			FROM `" . DB_PREFIX . "option` o
			JOIN `" . DB_PREFIX . "option_description` od
				ON od.option_id = o.option_id AND od.language_id = '" . $lang . "'
			JOIN `" . DB_PREFIX . "product_option` po
				ON po.option_id = o.option_id AND po.product_id IN (" . $in . ")
			JOIN `" . DB_PREFIX . "product_option_value` pov
				ON pov.product_option_id = po.product_option_id
			JOIN `" . DB_PREFIX . "option_value` ov
				ON ov.option_value_id = pov.option_value_id
			JOIN `" . DB_PREFIX . "option_value_description` ovd
				ON ovd.option_value_id = ov.option_value_id AND ovd.language_id = '" . $lang . "'
			WHERE od.name IN (" . $nin . ")
			ORDER BY po.product_id ASC, ov.sort_order ASC
		");

		$map = array();
		foreach ($query->rows as $row) {
			$map[(int)$row['product_id']][] = array(
				'option_value_id' => (int)$row['option_value_id'],
				'size_display'    => $this->formatSize($row['size_value']),
				'quantity'        => (int)$row['quantity'],
				'subtract'        => (bool)$row['subtract'],
			);
		}
		return $map;
	}

	/**
	 * Returns additional (non-main) product images for the given products in one query.
	 * Returns [product_id => [image_path, ...]].
	 * The main product image is already on the product row from getAllFeedProducts().
	 * Use instead of calling getProductImages() per product.
	 */
	public function getImagesMap(array $product_ids) {
		if (empty($product_ids)) return array();

		$in    = implode(',', array_map('intval', $product_ids));
		$query = $this->db->query("
			SELECT product_id, image
			FROM `" . DB_PREFIX . "product_image`
			WHERE product_id IN (" . $in . ")
			ORDER BY product_id ASC, sort_order ASC
		");

		$map = array();
		foreach ($query->rows as $row) {
			$map[(int)$row['product_id']][] = $row['image'];
		}
		return $map;
	}

	/**
	 * Returns category breadcrumb strings for the given products in one query,
	 * using the pre-computed oc_category_path table (eliminates recursive getCategory() calls).
	 * Returns [product_id => ["Root > Parent > Leaf", ...]].
	 * Use instead of getPath() + getCategory() loops in the feed controller.
	 */
	public function getCategoryBreadcrumbsMap(array $product_ids) {
		if (empty($product_ids)) return array();

		$lang  = (int)$this->config->get('config_language_id');
		$in    = implode(',', array_map('intval', $product_ids));

		$query = $this->db->query("
			SELECT ptc.product_id,
			       GROUP_CONCAT(cd.name ORDER BY cp.level ASC SEPARATOR ' > ') AS breadcrumb
			FROM `" . DB_PREFIX . "product_to_category` ptc
			JOIN `" . DB_PREFIX . "category_path` cp ON cp.category_id = ptc.category_id
			JOIN `" . DB_PREFIX . "category_description` cd
				ON cd.category_id = cp.path_id AND cd.language_id = '" . $lang . "'
			WHERE ptc.product_id IN (" . $in . ")
			GROUP BY ptc.product_id, ptc.category_id
		");

		$map = array();
		foreach ($query->rows as $row) {
			$map[(int)$row['product_id']][] = $row['breadcrumb'];
		}
		return $map;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function sizeOptionNames() {
		return array(
			'Размер детской обуви (US)',
			'Размер женской обуви (US)',
			'Размер обуви baby',
			'Размер обуви унисекс (US)',
			'Размер детской одежды',
			'Размер одежды',
		);
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
