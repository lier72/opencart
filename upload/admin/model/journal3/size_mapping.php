<?php

/**
 * Journal3 Size Mapping Admin Model
 * Extends the catalog model for admin use
 */
class ModelJournal3SizeMapping extends Model {

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
}

class_alias('ModelJournal3SizeMapping', '\Opencart\Admin\Model\Journal3\SizeMapping');
