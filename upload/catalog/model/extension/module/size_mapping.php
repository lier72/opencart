<?php

/**
 * Size Mapping Model
 * Handles database operations for size option mappings
 */
class ModelExtensionModuleSizeMapping extends Model {

	/**
	 * Get mapping for a specific option ID
	 *
	 * @param int $option_id OpenCart option ID
	 * @return array|false Mapping data or false if not found
	 */
	public function getMapping($option_id) {
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "j3_size_option_mapping`
			WHERE `option_id` = '" . (int)$option_id . "'
			AND `enabled` = 1
			LIMIT 1
		");

		if ($query->num_rows) {
			return $query->row;
		}

		return false;
	}

	/**
	 * Get all mappings for a product's options
	 *
	 * @param int $product_id Product ID
	 * @return array Array of mappings indexed by option_id
	 */
	public function getProductMappings($product_id) {
		$query = $this->db->query("
			SELECT m.*
			FROM `" . DB_PREFIX . "j3_size_option_mapping` m
			INNER JOIN `" . DB_PREFIX . "product_option` po ON m.option_id = po.option_id
			WHERE po.product_id = '" . (int)$product_id . "'
			AND m.enabled = 1
		");

		$mappings = array();

		foreach ($query->rows as $row) {
			$mappings[$row['option_id']] = $row;
		}

		return $mappings;
	}

	/**
	 * Get all enabled mappings
	 *
	 * @return array All mappings
	 */
	public function getAllMappings() {
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "j3_size_option_mapping`
			WHERE `enabled` = 1
			ORDER BY `option_id` ASC
		");

		return $query->rows;
	}

	/**
	 * Check if a product has any size-mapped options
	 *
	 * @param int $product_id Product ID
	 * @return bool True if product has mapped size options
	 */
	public function productHasSizeOptions($product_id) {
		$query = $this->db->query("
			SELECT COUNT(*) as total
			FROM `" . DB_PREFIX . "j3_size_option_mapping` m
			INNER JOIN `" . DB_PREFIX . "product_option` po ON m.option_id = po.option_id
			WHERE po.product_id = '" . (int)$product_id . "'
			AND m.enabled = 1
		");

		return $query->row['total'] > 0;
	}

	/**
	 * Get grouped mappings by gender for a product
	 * Useful for determining which gender tabs to show
	 *
	 * @param int $product_id Product ID
	 * @return array Array of genders available (e.g., ['women', 'universal'])
	 */
	public function getProductGenders($product_id) {
		$query = $this->db->query("
			SELECT DISTINCT m.gender
			FROM `" . DB_PREFIX . "j3_size_option_mapping` m
			INNER JOIN `" . DB_PREFIX . "product_option` po ON m.option_id = po.option_id
			WHERE po.product_id = '" . (int)$product_id . "'
			AND m.enabled = 1
			ORDER BY FIELD(m.gender, 'universal', 'women', 'unisex')
		");

		$genders = array();

		foreach ($query->rows as $row) {
			$genders[] = $row['gender'];
		}

		return $genders;
	}

	/**
	 * Save or update a mapping
	 *
	 * @param array $data Mapping data
	 * @return int Mapping ID
	 */
	/* public function saveMapping($data) {
		$option_id = (int)$data['option_id'];

		// Check if mapping exists
		$existing = $this->getMapping($option_id);

		if ($existing) {
			// Update existing
			$this->db->query("
				UPDATE `" . DB_PREFIX . "j3_size_option_mapping`
				SET
					`gender` = '" . $this->db->escape($data['gender']) . "',
					`size_type` = '" . $this->db->escape($data['size_type']) . "',
					`source_system` = '" . $this->db->escape($data['source_system']) . "',
					`enabled` = '" . (int)$data['enabled'] . "'
				WHERE `option_id` = '" . $option_id . "'
			");

			return $existing['mapping_id'];
		} else {
			// Insert new
			$this->db->query("
				INSERT INTO `" . DB_PREFIX . "j3_size_option_mapping`
				SET
					`option_id` = '" . $option_id . "',
					`gender` = '" . $this->db->escape($data['gender']) . "',
					`size_type` = '" . $this->db->escape($data['size_type']) . "',
					`source_system` = '" . $this->db->escape($data['source_system']) . "',
					`enabled` = '" . (int)$data['enabled'] . "'
			");

			return $this->db->getLastId();
		}
	}
 */
	/**
	 * Delete a mapping
	 *
	 * @param int $option_id Option ID
	 * @return void
	 */
	public function deleteMapping($option_id) {
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "j3_size_option_mapping`
			WHERE `option_id` = '" . (int)$option_id . "'
		");
	}

	/**
	 * Get size guide for a category or global
	 *
	 * @param int|null $category_id Category ID or null for global
	 * @param string $gender Gender category
	 * @param string $size_type Size type
	 * @return array|false Guide data or false
	 */
	/* public function getSizeGuide($category_id, $gender, $size_type) {
		// Try category-specific first
		if ($category_id) {
			$query = $this->db->query("
				SELECT *
				FROM `" . DB_PREFIX . "j3_size_guide`
				WHERE `category_id` = '" . (int)$category_id . "'
				AND `gender` = '" . $this->db->escape($gender) . "'
				AND `size_type` = '" . $this->db->escape($size_type) . "'
				AND `enabled` = 1
				LIMIT 1
			");

			if ($query->num_rows) {
				return $query->row;
			}
		}

		// Fallback to global guide
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "j3_size_guide`
			WHERE `category_id` IS NULL
			AND `gender` = '" . $this->db->escape($gender) . "'
			AND `size_type` = '" . $this->db->escape($size_type) . "'
			AND `enabled` = 1
			LIMIT 1
		");

		if ($query->num_rows) {
			return $query->row;
		}

		return false;
	} */

	/**
	 * Get module setting
	 *
	 * @param string $key Setting key
	 * @param mixed $default Default value
	 * @return mixed Setting value
	 */
	public function getSetting($key, $default = null) {
		$query = $this->db->query("
			SELECT `setting_value`
			FROM `" . DB_PREFIX . "j3_size_selector_settings`
			WHERE `setting_key` = '" . $this->db->escape($key) . "'
			LIMIT 1
		");

		if ($query->num_rows) {
			return $query->row['setting_value'];
		}

		return $default;
	}

	/**
	 * Save module setting
	 *
	 * @param string $key Setting key
	 * @param mixed $value Setting value
	 * @return void
	 */
	public function saveSetting($key, $value) {
		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "j3_size_selector_settings`
			(`setting_key`, `setting_value`)
			VALUES ('" . $this->db->escape($key) . "', '" . $this->db->escape($value) . "')
			ON DUPLICATE KEY UPDATE
			`setting_value` = '" . $this->db->escape($value) . "'
		");
	}

	/**
	 * Get all module settings
	 *
	 * @return array Associative array of settings
	 */
	public function getAllSettings() {
		$query = $this->db->query("
			SELECT `setting_key`, `setting_value`
			FROM `" . DB_PREFIX . "j3_size_selector_settings`
		");

		$settings = array();

		foreach ($query->rows as $row) {
			$settings[$row['setting_key']] = $row['setting_value'];
		}

		return $settings;
	}

	// ============================================
	// Category-based Gender Detection (v2)
	// ============================================

	/**
	 * Check if v2 tables exist
	 *
	 * @return bool
	 */
	public function isV2Installed() {
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "j3_size_category_mapping'");
		return $query->num_rows > 0;
	}

	/**
	 * Get effective gender for a product based on its categories
	 *
	 * @param int $product_id Product ID
	 * @return array Gender info with gender_type and age_group
	 */
	public function getProductGenderFromCategory($product_id) {
		// Check if v2 tables are installed
		if (!$this->isV2Installed()) {
			return array(
				'gender_type' => 'unisex',
				'age_group' => 'adult'
			);
		}

		// Get product's categories
		$query = $this->db->query("
			SELECT pc.category_id
			FROM `" . DB_PREFIX . "product_to_category` pc
			WHERE pc.product_id = '" . (int)$product_id . "'
		");

		if (!$query->num_rows) {
			return array(
				'gender_type' => 'unisex',
				'age_group' => 'adult'
			);
		}

		$genders = array();
		$age_group = 'adult';

		foreach ($query->rows as $row) {
			$category_gender = $this->getEffectiveGenderForCategory($row['category_id']);

			if ($category_gender) {
				$genders[] = $category_gender['gender_type'];

				// Kids/baby age group takes priority
				if ($category_gender['age_group'] == 'kids' || $category_gender['age_group'] == 'baby') {
					$age_group = $category_gender['age_group'];
				}
			}
		}

		// Remove duplicates
		$genders = array_unique($genders);

		// Determine final gender
		$final_gender = 'unisex';

		if (count($genders) == 1) {
			$final_gender = $genders[0];
		} elseif (count($genders) > 1) {
			// If product has multiple genders (e.g., men and women), it's unisex
			// But if it's kids, keep it as kids
			if (in_array('kids', $genders)) {
				$final_gender = 'kids';
			} else {
				$final_gender = 'unisex';
			}
		}

		return array(
			'gender_type' => $final_gender,
			'age_group' => $age_group
		);
	}

	/**
	 * Get effective gender for a category (with parent inheritance)
	 *
	 * @param int $category_id Category ID
	 * @return array|false Category gender info or false
	 */
	public function getEffectiveGenderForCategory($category_id) {
		// First check if this category has a direct mapping
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "j3_size_category_mapping`
			WHERE `category_id` = '" . (int)$category_id . "'
			LIMIT 1
		");

		if ($query->num_rows) {
			return $query->row;
		}

		// Get parent categories and check for inheritance
		$query = $this->db->query("
			SELECT cp.path_id, cm.*
			FROM `" . DB_PREFIX . "category_path` cp
			LEFT JOIN `" . DB_PREFIX . "j3_size_category_mapping` cm ON cp.path_id = cm.category_id
			WHERE cp.category_id = '" . (int)$category_id . "'
			AND cm.mapping_id IS NOT NULL
			AND cm.inherit_children = 1
			ORDER BY cp.level DESC
			LIMIT 1
		");

		if ($query->num_rows) {
			return $query->row;
		}

		return false;
	}

	/**
	 * Get conversion table from database
	 *
	 * @param string $gender_type Gender type (men, women, kids, etc.)
	 * @param string $size_type Size type (shoes, apparel)
	 * @return array|false Table data or false
	 */
	public function getConversionTable($gender_type, $size_type) {
		if (!$this->isV2Installed()) {
			return false;
		}

		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "j3_size_conversion_table`
			WHERE `gender_type` = '" . $this->db->escape($gender_type) . "'
			AND `size_type` = '" . $this->db->escape($size_type) . "'
			AND `enabled` = 1
			LIMIT 1
		");

		if ($query->num_rows) {
			$row = $query->row;
			$row['table_data'] = json_decode($row['table_data'], true);
			return $row;
		}

		// Fallback to unisex if no specific table found
		if ($gender_type != 'unisex') {
			$query = $this->db->query("
				SELECT *
				FROM `" . DB_PREFIX . "j3_size_conversion_table`
				WHERE `gender_type` = 'unisex'
				AND `size_type` = '" . $this->db->escape($size_type) . "'
				AND `enabled` = 1
				LIMIT 1
			");

			if ($query->num_rows) {
				$row = $query->row;
				$row['table_data'] = json_decode($row['table_data'], true);
				return $row;
			}
		}

		return false;
	}

	/**
	 * Get product genders with category-based detection
	 *
	 * @param int $product_id Product ID
	 * @return array Array of gender info
	 */
	public function getProductGendersV2($product_id) {
		// Get gender from category
		$category_gender = $this->getProductGenderFromCategory($product_id);

		// Get gender from option mappings
		$option_genders = $this->getProductGenders($product_id);

		// If we have category-based gender, use it as primary
		if ($this->isV2Installed() && $category_gender['gender_type'] != 'unisex') {
			// Override option genders with category gender
			return array($category_gender['gender_type']);
		}

		// Otherwise use option-based genders
		return $option_genders;
	}
}

class_alias('ModelExtensionModuleSizeMapping', '\Opencart\Catalog\Model\Extension\Module\SizeMapping');
