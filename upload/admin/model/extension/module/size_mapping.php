<?php

/**
 * Size Mapping Admin Model
 * Extends the catalog model for admin use
 */
class ModelExtensionModuleSizeMapping extends Model {

	/**
	 * Get mapping for a specific option ID
	 */
	public function getMapping($option_id) {
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "j3_size_option_mapping`
			WHERE `option_id` = '" . (int)$option_id . "'
			LIMIT 1
		");

		if ($query->num_rows) {
			return $query->row;
		}

		return false;
	}

	/**
	 * Get all mappings
	 */
	public function getAllMappings() {
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "j3_size_option_mapping`
			ORDER BY `option_id` ASC
		");

		return $query->rows;
	}

	/**
	 * Save or update a mapping
	 */
	public function saveMapping($data) {
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

	/**
	 * Delete a mapping
	 */
	public function deleteMapping($option_id) {
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "j3_size_option_mapping`
			WHERE `option_id` = '" . (int)$option_id . "'
		");
	}

	/**
	 * Check if tables exist
	 */
	public function isInstalled() {
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "j3_size_option_mapping'");
		return $query->num_rows > 0;
	}

	// ============================================
	// Category Gender Mapping Methods
	// ============================================

	/**
	 * Get all category mappings
	 */
	public function getCategoryMappings() {
		$query = $this->db->query("
			SELECT cm.*, cd.name as category_name
			FROM `" . DB_PREFIX . "j3_size_category_mapping` cm
			LEFT JOIN `" . DB_PREFIX . "category_description` cd
				ON cm.category_id = cd.category_id
				AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			ORDER BY cd.name ASC
		");
		return $query->rows;
	}

	/**
	 * Get category mapping by ID
	 */
	public function getCategoryMapping($category_id) {
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "j3_size_category_mapping`
			WHERE `category_id` = '" . (int)$category_id . "'
			LIMIT 1
		");
		return $query->num_rows ? $query->row : false;
	}

	/**
	 * Get effective gender for a category (with parent inheritance)
	 */
	public function getEffectiveGenderForCategory($category_id) {
		// First check if this category has a direct mapping
		$direct = $this->getCategoryMapping($category_id);
		if ($direct) {
			return $direct;
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

		return $query->num_rows ? $query->row : false;
	}

	/**
	 * Save category mapping
	 */
	public function saveCategoryMapping($data) {
		$category_id = (int)$data['category_id'];

		$this->db->query("
			INSERT INTO `" . DB_PREFIX . "j3_size_category_mapping`
			SET
				`category_id` = '" . $category_id . "',
				`gender_type` = '" . $this->db->escape($data['gender_type']) . "',
				`age_group` = '" . $this->db->escape($data['age_group']) . "',
				`inherit_children` = '" . (int)$data['inherit_children'] . "'
			ON DUPLICATE KEY UPDATE
				`gender_type` = '" . $this->db->escape($data['gender_type']) . "',
				`age_group` = '" . $this->db->escape($data['age_group']) . "',
				`inherit_children` = '" . (int)$data['inherit_children'] . "'
		");

		return $category_id;
	}

	/**
	 * Delete category mapping
	 */
	public function deleteCategoryMapping($category_id) {
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "j3_size_category_mapping`
			WHERE `category_id` = '" . (int)$category_id . "'
		");
	}

	/**
	 * Check if category belongs to multiple genders (unisex detection)
	 */
	public function isUnisexCategory($category_id) {
		// Get all parent mappings
		$query = $this->db->query("
			SELECT DISTINCT cm.gender_type
			FROM `" . DB_PREFIX . "category_path` cp
			INNER JOIN `" . DB_PREFIX . "j3_size_category_mapping` cm ON cp.path_id = cm.category_id
			WHERE cp.category_id = '" . (int)$category_id . "'
			AND cm.inherit_children = 1
		");

		$genders = array();
		foreach ($query->rows as $row) {
			$genders[] = $row['gender_type'];
		}

		// If category has both men and women parents, it's unisex
		if (in_array('men', $genders) && in_array('women', $genders)) {
			return true;
		}

		return false;
	}

	// ============================================
	// Conversion Table Methods
	// ============================================

	/**
	 * Get all conversion tables
	 */
	public function getConversionTables() {
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "j3_size_conversion_table`
			ORDER BY `gender_type`, `size_type`
		");
		return $query->rows;
	}

	/**
	 * Get conversion table by ID
	 */
	public function getConversionTable($table_id) {
		$query = $this->db->query("
			SELECT *
			FROM `" . DB_PREFIX . "j3_size_conversion_table`
			WHERE `table_id` = '" . (int)$table_id . "'
			LIMIT 1
		");

		if ($query->num_rows) {
			$row = $query->row;
			$row['table_data'] = json_decode($row['table_data'], true);
			return $row;
		}

		return false;
	}

	/**
	 * Get conversion table by gender and type
	 */
	public function getConversionTableByGenderType($gender_type, $size_type) {
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

		return false;
	}

	/**
	 * Save conversion table
	 */
	public function saveConversionTable($data) {
	// Normalize input: remove HTML entities if present
	if (!is_array($data['table_data'])) {
		$data['table_data'] = json_decode(htmlspecialchars_decode($data['table_data']), true);
	}
	$table_data = json_encode($data['table_data'], JSON_UNESCAPED_UNICODE);

		if (isset($data['table_id']) && $data['table_id']) {
			// Update existing
			$this->db->query("
				UPDATE `" . DB_PREFIX . "j3_size_conversion_table`
				SET
					`table_name` = '" . $this->db->escape($data['table_name']) . "',
					`table_data` = '" . $this->db->escape($table_data) . "',
					`enabled` = '" . (int)$data['enabled'] . "'
				WHERE `table_id` = '" . (int)$data['table_id'] . "'
			");
			return $data['table_id'];
		} else {
			// Insert new
			$this->db->query("
				INSERT INTO `" . DB_PREFIX . "j3_size_conversion_table`
				SET
					`gender_type` = '" . $this->db->escape($data['gender_type']) . "',
					`size_type` = '" . $this->db->escape($data['size_type']) . "',
					`table_name` = '" . $this->db->escape($data['table_name']) . "',
					`table_data` = '" . $this->db->escape($table_data) . "',
					`enabled` = '" . (int)$data['enabled'] . "'
			");
			return $this->db->getLastId();
		}
	}

	/**
	 * Delete conversion table
	 */
	public function deleteConversionTable($table_id) {
		$this->db->query("
			DELETE FROM `" . DB_PREFIX . "j3_size_conversion_table`
			WHERE `table_id` = '" . (int)$table_id . "'
		");
	}

	/**
	 * Get categories tree for dropdown
	 */
	public function getCategoriesTree($parent_id = 0, $level = 0) {
		$query = $this->db->query("
			SELECT c.category_id, cd.name, cm.gender_type, cm.age_group, cm.inherit_children
			FROM `" . DB_PREFIX . "category` c
			LEFT JOIN `" . DB_PREFIX . "category_description` cd
				ON c.category_id = cd.category_id
				AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			LEFT JOIN `" . DB_PREFIX . "j3_size_category_mapping` cm ON c.category_id = cm.category_id
			WHERE c.parent_id = '" . (int)$parent_id . "'
			ORDER BY cd.name ASC
		");

		$categories = array();

		foreach ($query->rows as $row) {
			$categories[] = array(
				'category_id' => $row['category_id'],
				'name' => str_repeat('— ', $level) . $row['name'],
				'level' => $level,
				'gender_type' => $row['gender_type'],
				'age_group' => $row['age_group'],
				'inherit_children' => $row['inherit_children']
			);

			// Recursively get children
			$children = $this->getCategoriesTree($row['category_id'], $level + 1);
			$categories = array_merge($categories, $children);
		}

		return $categories;
	}

	/**
	 * Check if v2 tables exist
	 */
	public function isV2Installed() {
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "j3_size_category_mapping'");
		return $query->num_rows > 0;
	}
}

class_alias('ModelExtensionModuleSizeMapping', '\Opencart\Admin\Model\Extension\Module\SizeMapping');
