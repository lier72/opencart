<?php
/**
 * Product Family Model
 * Handles fetching related products based on matching model attribute
 */
class ModelExtensionModuleProductFamily extends Model {

	/**
	 * Get product family members (products with same model attribute value)
	 *
	 * @param int $product_id Current product ID
	 * @return array Array of related products
	 */
	public function getProductFamily($product_id) {
		$product_id = (int)$product_id;

		// Get module configuration
		$model_attribute_id = (int)$this->config->get('module_product_family_model_attribute_id');
		$variant_attribute_ids = $this->config->get('module_product_family_variant_attributes');
		$strict_mode = $this->config->get('module_product_family_strict_mode');
		$strict_categories = $this->config->get('module_product_family_strict_categories');

		// Check if current product is in a strict mode category
		$use_strict_mode = false;
		if ($strict_mode && !empty($strict_categories) && is_array($strict_categories)) {
			$category_check_query = $this->db->query("
				SELECT category_id
				FROM " . DB_PREFIX . "product_to_category
				WHERE product_id = '" . $product_id . "'
			");

			foreach ($category_check_query->rows as $row) {
				if (in_array($row['category_id'], $strict_categories)) {
					$use_strict_mode = true;
					break;
				}
			}
		}

		// Use strict mode (model field) or attribute mode
		if ($use_strict_mode) {
			return $this->getProductFamilyByModelField($product_id, $variant_attribute_ids);
		}

		if (!$model_attribute_id) {
			return array();
		}

		// Get the model attribute value for current product
		$model_value_query = $this->db->query("
			SELECT text
			FROM " . DB_PREFIX . "product_attribute
			WHERE product_id = '" . $product_id . "'
			AND attribute_id = '" . $model_attribute_id . "'
			AND language_id = '" . (int)$this->config->get('config_language_id') . "'
			LIMIT 1
		");

		if (!$model_value_query->num_rows) {
			return array();
		}

		$model_value = $model_value_query->row['text'];

		// Find all products with the same model attribute value
		$family_query = $this->db->query("
			SELECT DISTINCT pa.product_id
			FROM " . DB_PREFIX . "product_attribute pa
			LEFT JOIN " . DB_PREFIX . "product p ON (pa.product_id = p.product_id)
			WHERE pa.attribute_id = '" . $model_attribute_id . "'
			AND pa.text = '" . $this->db->escape($model_value) . "'
			AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "'
			AND pa.product_id != '" . $product_id . "'
			AND p.status = '1'
			ORDER BY pa.product_id ASC
		");

		if (!$family_query->num_rows) {
			return array();
		}

		// Collect all product IDs in family (including current product)
		$all_product_ids = array($product_id);
		foreach ($family_query->rows as $row) {
			$all_product_ids[] = $row['product_id'];
		}

		// Determine which attributes actually vary across the family
		$varying_attribute_ids = $this->getVaryingAttributes($all_product_ids, $variant_attribute_ids);

		$products = array();
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		foreach ($family_query->rows as $row) {
			$product_info = $this->model_catalog_product->getProduct($row['product_id']);

			if ($product_info) {
				// Get variant attributes for this product (only varying ones)
				$variants = array();

				if (!empty($varying_attribute_ids) && is_array($varying_attribute_ids)) {
					foreach ($varying_attribute_ids as $variant_attr_id) {
						$variant_query = $this->db->query("
							SELECT pa.text, ad.name
							FROM " . DB_PREFIX . "product_attribute pa
							LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (pa.attribute_id = ad.attribute_id AND ad.language_id = pa.language_id)
							WHERE pa.product_id = '" . (int)$row['product_id'] . "'
							AND pa.attribute_id = '" . (int)$variant_attr_id . "'
							AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "'
							LIMIT 1
						");

						if ($variant_query->num_rows) {
							$variants[] = $this->buildVariantData(
								$variant_attr_id,
								$variant_query->row['name'],
								$variant_query->row['text']
							);
						}
					}
				}

				// Prepare product data
				$image_width = (int)$this->config->get('module_product_family_image_width') ?: 60;
				$image_height = (int)$this->config->get('module_product_family_image_height') ?: 60;

				if ($product_info['image']) {
					$image = $this->model_tool_image->resize($product_info['image'], $image_width, $image_height);
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $image_width, $image_height);
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = false;
				}

				if ((float)$product_info['special']) {
					$special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$special = false;
				}

				$products[] = array(
					'product_id'  => $product_info['product_id'],
					'name'        => $product_info['name'],
					'model'       => $product_info['model'],
					'image'       => $image,
					'price'       => $price,
					'special'     => $special,
					'quantity'    => $product_info['quantity'],
					'href'        => $this->url->link('product/product', 'product_id=' . $product_info['product_id']),
					'variants'    => $variants,
					'in_stock'    => $product_info['quantity'] > 0
				);
			}
		}

		return $products;
	}

	/**
	 * Get all product IDs in the family (including current product)
	 *
	 * @param int $product_id Current product ID
	 * @return array All product IDs in family
	 */
	private function getFamilyProductIds($product_id) {
		$product_id = (int)$product_id;
		$model_attribute_id = (int)$this->config->get('module_product_family_model_attribute_id');
		$strict_mode = $this->config->get('module_product_family_strict_mode');
		$strict_categories = $this->config->get('module_product_family_strict_categories');

		$all_product_ids = array($product_id);

		// Check if current product is in a strict mode category
		$use_strict_mode = false;
		if ($strict_mode && !empty($strict_categories) && is_array($strict_categories)) {
			$category_check_query = $this->db->query("
				SELECT category_id
				FROM " . DB_PREFIX . "product_to_category
				WHERE product_id = '" . $product_id . "'
			");

			foreach ($category_check_query->rows as $row) {
				if (in_array($row['category_id'], $strict_categories)) {
					$use_strict_mode = true;
					break;
				}
			}
		}

		// Use strict mode (model field) or attribute mode
		if ($use_strict_mode) {
			// Get current product's model field
			$current_product_query = $this->db->query("
				SELECT model
				FROM " . DB_PREFIX . "product
				WHERE product_id = '" . $product_id . "'
				LIMIT 1
			");

			if ($current_product_query->num_rows) {
				$current_model = $current_product_query->row['model'];
				$model_base = preg_replace('/-\d{1,2}$/', '', $current_model);

				if (!empty($model_base)) {
					$family_query = $this->db->query("
						SELECT DISTINCT p.product_id
						FROM " . DB_PREFIX . "product p
						WHERE p.model LIKE '" . $this->db->escape($model_base) . "%'
						AND p.status = '1'
						AND (
							p.model = '" . $this->db->escape($model_base) . "'
							OR p.model REGEXP '^" . $this->db->escape($model_base) . "-[0-9]{1,2}$'
						)
					");

					foreach ($family_query->rows as $row) {
						if (!in_array($row['product_id'], $all_product_ids)) {
							$all_product_ids[] = $row['product_id'];
						}
					}
				}
			}
		} else {
			// Attribute-based grouping
			if ($model_attribute_id) {
				$model_value_query = $this->db->query("
					SELECT text
					FROM " . DB_PREFIX . "product_attribute
					WHERE product_id = '" . $product_id . "'
					AND attribute_id = '" . $model_attribute_id . "'
					AND language_id = '" . (int)$this->config->get('config_language_id') . "'
					LIMIT 1
				");

				if ($model_value_query->num_rows) {
					$model_value = $model_value_query->row['text'];

					$family_query = $this->db->query("
						SELECT DISTINCT pa.product_id
						FROM " . DB_PREFIX . "product_attribute pa
						LEFT JOIN " . DB_PREFIX . "product p ON (pa.product_id = p.product_id)
						WHERE pa.attribute_id = '" . $model_attribute_id . "'
						AND pa.text = '" . $this->db->escape($model_value) . "'
						AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "'
						AND p.status = '1'
					");

					foreach ($family_query->rows as $row) {
						if (!in_array($row['product_id'], $all_product_ids)) {
							$all_product_ids[] = $row['product_id'];
						}
					}
				}
			}
		}

		return $all_product_ids;
	}

	/**
	 * Get only attributes that vary across family members
	 * Filters out attributes where all products have the same value
	 * Respects "always_show" flag in attribute configuration
	 *
	 * @param array $product_ids All product IDs in the family
	 * @param array $variant_attribute_ids Configured variant attribute IDs
	 * @return array Attribute IDs that actually vary or are marked as always_show
	 */
	private function getVaryingAttributes($product_ids, $variant_attribute_ids) {
		if (empty($variant_attribute_ids) || !is_array($variant_attribute_ids)) {
			return array();
		}

		$varying_attributes = array();

		foreach ($variant_attribute_ids as $attr_id) {
			// Check if this attribute is configured to always show
			$config = $this->getAttributeConfigById($attr_id);

			if (!empty($config['always_show'])) {
				// Always show this attribute regardless of whether values vary
				$varying_attributes[] = $attr_id;
				continue;
			}

			// Otherwise, check if values actually vary
			$values = array();

			// Collect all values for this attribute across all products
			foreach ($product_ids as $pid) {
				$attr_query = $this->db->query("
					SELECT text
					FROM " . DB_PREFIX . "product_attribute
					WHERE product_id = '" . (int)$pid . "'
					AND attribute_id = '" . (int)$attr_id . "'
					AND language_id = '" . (int)$this->config->get('config_language_id') . "'
					LIMIT 1
				");

				if ($attr_query->num_rows) {
					// Apply regex if configured to normalize comparison
					$processed = $this->applyAttributeConfig(
						$attr_id,
						'',
						$attr_query->row['text']
					);
					$values[] = $processed['value'];
				}
			}

			// Only include this attribute if values differ
			$unique_values = array_unique($values);
			if (count($unique_values) > 1) {
				$varying_attributes[] = $attr_id;
			}
		}

		return $varying_attributes;
	}

	/**
	 * Get configuration for a specific attribute ID
	 *
	 * @param int $attribute_id
	 * @return array|null Attribute configuration or null if not found
	 */
	private function getAttributeConfigById($attribute_id) {
		$attribute_config = $this->config->get('module_product_family_attribute_config');

		if (!empty($attribute_config) && is_array($attribute_config)) {
			foreach ($attribute_config as $config) {
				if (isset($config['attribute_id']) && $config['attribute_id'] == $attribute_id) {
					return $config;
				}
			}
		}

		return null;
	}

	/**
	 * Apply attribute configuration (custom label and regex pattern)
	 *
	 * @param int $attribute_id
	 * @param string $name Original attribute name
	 * @param string $value Original attribute value
	 * @return array Modified name and value
	 */
	private function applyAttributeConfig($attribute_id, $name, $value) {
		$config = $this->getAttributeConfigById($attribute_id);

		if ($config) {
			// Apply custom label if set
			if (!empty($config['label'])) {
				$name = $config['label'];
			}

			// Apply regex pattern if set
			if (!empty($config['regex']) && !empty($value)) {
				if (preg_match('/' . str_replace('/', '\/', $config['regex']) . '/u', $value, $matches)) {
					// Use first capture group if exists, otherwise use full match
					$value = isset($matches[1]) ? $matches[1] : $matches[0];
				}
			}
		}

		return array(
			'name' => $name,
			'value' => $value
		);
	}

	/**
	 * Build frontend-ready variant data.
	 *
	 * @param int $attribute_id
	 * @param string $name
	 * @param string $value
	 * @return array
	 */
	private function buildVariantData($attribute_id, $name, $value) {
		$processed = $this->applyAttributeConfig($attribute_id, $name, $value);
		$presentation = $this->extractColorPresentation($processed['value']);

		return array(
			'name' => $processed['name'],
			'value' => $presentation['value'],
			'raw_value' => $presentation['raw_value'],
			'color_hex' => $presentation['color_hex'],
			'color_is_light' => $presentation['color_is_light']
		);
	}

	/**
	 * Extract a HEX color from a variant value and clean the display label.
	 *
	 * @param string $value
	 * @return array
	 */
	private function extractColorPresentation($value) {
		$result = array(
			'value' => $value,
			'raw_value' => $value,
			'color_hex' => '',
			'color_is_light' => false
		);

		if (!is_string($value) || $value === '') {
			return $result;
		}

		if (!preg_match('/#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})\b/', $value, $matches)) {
			return $result;
		}

		$hex = strtoupper($matches[0]);
		$display = preg_replace('/\s*\(\s*' . preg_quote($hex, '/') . '\s*\)\s*/i', ' ', $value);
		$display = preg_replace('/\s*' . preg_quote($hex, '/') . '\s*/i', ' ', $display);
		$display = trim(preg_replace('/\s{2,}/', ' ', $display));

		$result['value'] = $display;
		$result['color_hex'] = $hex;
		$result['color_is_light'] = $this->isLightHexColor($hex);

		return $result;
	}

	/**
	 * Determine whether a HEX color is light enough to need a darker outline.
	 *
	 * @param string $hex
	 * @return bool
	 */
	private function isLightHexColor($hex) {
		$hex = ltrim($hex, '#');

		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if (strlen($hex) !== 6) {
			return false;
		}

		$red = hexdec(substr($hex, 0, 2));
		$green = hexdec(substr($hex, 2, 2));
		$blue = hexdec(substr($hex, 4, 2));
		$brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

		return $brightness > 200;
	}

	/**
	 * Check if images should be shown for current product's categories
	 *
	 * @param int $product_id
	 * @return bool
	 */
	public function shouldShowImages($product_id) {
		$show_image_categories = $this->config->get('module_product_family_show_image_categories');

		// If no specific categories selected, use global setting
		if (empty($show_image_categories) || !is_array($show_image_categories)) {
			return (bool)$this->config->get('module_product_family_show_image');
		}

		// Check if product is in any of the show_image categories
		$category_query = $this->db->query("
			SELECT category_id
			FROM " . DB_PREFIX . "product_to_category
			WHERE product_id = '" . (int)$product_id . "'
		");

		foreach ($category_query->rows as $row) {
			if (in_array($row['category_id'], $show_image_categories)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get current product's attributes (only those that vary in the family)
	 *
	 * @param int $product_id
	 * @return array
	 */
	public function getCurrentProductVariants($product_id) {
		$product_id = (int)$product_id;
		$variant_attribute_ids = $this->config->get('module_product_family_variant_attributes');

		// Get all product IDs in the family to determine varying attributes
		$all_product_ids = $this->getFamilyProductIds($product_id);

		// Determine which attributes actually vary across the family
		$varying_attribute_ids = $this->getVaryingAttributes($all_product_ids, $variant_attribute_ids);

		$variants = array();

		if (!empty($varying_attribute_ids) && is_array($varying_attribute_ids)) {
			foreach ($varying_attribute_ids as $variant_attr_id) {
				$variant_query = $this->db->query("
					SELECT pa.text, ad.name
					FROM " . DB_PREFIX . "product_attribute pa
					LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (pa.attribute_id = ad.attribute_id AND ad.language_id = pa.language_id)
					WHERE pa.product_id = '" . $product_id . "'
					AND pa.attribute_id = '" . (int)$variant_attr_id . "'
					AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "'
					LIMIT 1
				");

				if ($variant_query->num_rows) {
					$variants[] = $this->buildVariantData(
						$variant_attr_id,
						$variant_query->row['name'],
						$variant_query->row['text']
					);
				}
			}
		}

		return $variants;
	}

	/**
	 * Get product family by model field (strict mode)
	 * Groups products based on the product.model field with variant suffix removed
	 *
	 * @param int $product_id Current product ID
	 * @param array $variant_attribute_ids Variant attribute IDs
	 * @return array Array of related products
	 */
	private function getProductFamilyByModelField($product_id, $variant_attribute_ids) {
		$product_id = (int)$product_id;

		// Get current product's model field
		$current_product_query = $this->db->query("
			SELECT model
			FROM " . DB_PREFIX . "product
			WHERE product_id = '" . $product_id . "'
			LIMIT 1
		");

		if (!$current_product_query->num_rows) {
			return array();
		}

		$current_model = $current_product_query->row['model'];

		// Extract model base by removing variant suffix (-1, -2, -12, etc.)
		$model_base = preg_replace('/-\d{1,2}$/', '', $current_model);

		if (empty($model_base)) {
			return array();
		}

		// Find all products with model starting with model_base
		// This will match: AWSU076-1, AWSU076-2, AWSU076-12, etc. for base AWSU076
		$family_query = $this->db->query("
			SELECT DISTINCT p.product_id
			FROM " . DB_PREFIX . "product p
			WHERE p.model LIKE '" . $this->db->escape($model_base) . "%'
			AND p.product_id != '" . $product_id . "'
			AND p.status = '1'
			AND (
				p.model = '" . $this->db->escape($model_base) . "'
				OR p.model REGEXP '^" . $this->db->escape($model_base) . "-[0-9]{1,2}$'
			)
			ORDER BY p.model ASC
		");

		if (!$family_query->num_rows) {
			return array();
		}

		// Collect all product IDs in family (including current product)
		$all_product_ids = array($product_id);
		foreach ($family_query->rows as $row) {
			$all_product_ids[] = $row['product_id'];
		}

		// Determine which attributes actually vary across the family
		$varying_attribute_ids = $this->getVaryingAttributes($all_product_ids, $variant_attribute_ids);

		$products = array();
		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		foreach ($family_query->rows as $row) {
			$product_info = $this->model_catalog_product->getProduct($row['product_id']);

			if ($product_info) {
				// Get variant attributes for this product (only varying ones)
				$variants = array();

				if (!empty($varying_attribute_ids) && is_array($varying_attribute_ids)) {
					foreach ($varying_attribute_ids as $variant_attr_id) {
						$variant_query = $this->db->query("
							SELECT pa.text, ad.name
							FROM " . DB_PREFIX . "product_attribute pa
							LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (pa.attribute_id = ad.attribute_id AND ad.language_id = pa.language_id)
							WHERE pa.product_id = '" . (int)$row['product_id'] . "'
							AND pa.attribute_id = '" . (int)$variant_attr_id . "'
							AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "'
							LIMIT 1
						");

						if ($variant_query->num_rows) {
							$variants[] = $this->buildVariantData(
								$variant_attr_id,
								$variant_query->row['name'],
								$variant_query->row['text']
							);
						}
					}
				}

				// Prepare product data
				$image_width = (int)$this->config->get('module_product_family_image_width') ?: 60;
				$image_height = (int)$this->config->get('module_product_family_image_height') ?: 60;

				if ($product_info['image']) {
					$image = $this->model_tool_image->resize($product_info['image'], $image_width, $image_height);
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $image_width, $image_height);
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = false;
				}

				if ((float)$product_info['special']) {
					$special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$special = false;
				}

				$products[] = array(
					'product_id'  => $product_info['product_id'],
					'name'        => $product_info['name'],
					'model'       => $product_info['model'],
					'image'       => $image,
					'price'       => $price,
					'special'     => $special,
					'quantity'    => $product_info['quantity'],
					'href'        => $this->url->link('product/product', 'product_id=' . $product_info['product_id']),
					'variants'    => $variants,
					'in_stock'    => $product_info['quantity'] > 0
				);
			}
		}

		return $products;
	}
}
