<?php

/**
 * Journal3 Size Converter Model
 * Handles size conversion between different sizing systems
 *
 * Conversion systems:
 * - Shoes: EU, US, UK, mm (millimeters)
 * - Apparel: Asian, EU, US (with offset logic)
 *
 * Gender categories:
 * - women: Women's sizing
 * - universal: Men's/Unisex sizing (same table for both)
 */
class ModelJournal3SizeConverter extends Model {

	/**
	 * Women's Shoe Sizes
	 * Each index represents the same size across all systems
	 */
	private $women_shoes = array(
		'EU' => array('33 2/3', '34 1/3', '35', '35 2/3', '36 1/3', '37', '37 2/3', '38 1/3', '39', '39 2/3', '40 1/3', '41', '41 2/3', '42 1/3', '43', '43 2/3', '44 1/3'),
		'US' => array('4', '4.5', '5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11', '11.5', '12'),
		'UK' => array('1.5', '2', '2.5', '3', '3.5', '4', '4.5', '5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5'),
		'mm' => array('205', '210', '215', '220', '225', '230', '235', '240', '245', '250', '255', '260', '265', '270', '275', '280', '285')
	);

	/**
	 * Universal Shoe Sizes (Men/Unisex)
	 * Each index represents the same size across all systems
	 */
	private $universal_shoes = array(
		'EU' => array('35 2/3', '36 1/3', '37', '37 2/3', '38 1/3', '39', '39 2/3', '40 1/3', '41', '41 2/3', '42 1/3', '43', '43 2/3', '44 1/3', '45', '45 2/3', '46 1/3', '47', '47 2/3', '48 1/3', '49'),
		'US' => array('4', '4.5', '5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11', '11.5', '12', '12.5', '13', '13.5', '14'),
		'UK' => array('3', '3.5', '4', '4.5', '5', '5.5', '6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11', '11.5', '12', '12.5', '13'),
		'mm' => array('215', '220', '225', '230', '235', '240', '245', '250', '255', '260', '265', '270', '275', '280', '285', '290', '295', '300', '305', '310', '315')
	);

	/**
	 * Men's Apparel Sizes
	 * Asian L = EU M = US S (offset conversion)
	 */
	private $apparel_men = array(
		'Asian' => array('XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL'),
		'EU'    => array('XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'),
		'US'    => array('XXXS', 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL'),
		'measurements' => array(
			'XS'   => array('chest' => '160 / 80', 'waist' => '160 / 68'),
			'S'    => array('chest' => '165 / 84', 'waist' => '165 / 72'),
			'M'    => array('chest' => '170 / 88', 'waist' => '170 / 76'),
			'L'    => array('chest' => '175 / 92', 'waist' => '175 / 80'),
			'XL'   => array('chest' => '180 / 96', 'waist' => '180 / 84'),
			'XXL'  => array('chest' => '185 / 100', 'waist' => '185 / 88'),
			'XXXL' => array('chest' => '190 / 104', 'waist' => '190 / 92'),
			'4XL'  => array('chest' => '195 / 108', 'waist' => '195 / 96')
		)
	);

	/**
	 * Women's Apparel Sizes
	 * Asian L = EU M = US S (offset conversion)
	 */
	private $apparel_women = array(
		'Asian' => array('XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'),
		'EU'    => array('XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL'),
		'US'    => array('XXXS', 'XXS', 'XS', 'S', 'M', 'L', 'XL'),
		'measurements' => array(
			'XS'   => array('chest' => '155 / 80', 'waist' => '155 / 64'),
			'S'    => array('chest' => '160 / 84', 'waist' => '160 / 68'),
			'M'    => array('chest' => '165 / 88', 'waist' => '165 / 72'),
			'L'    => array('chest' => '170 / 92', 'waist' => '170 / 76'),
			'XL'   => array('chest' => '175 / 96', 'waist' => '175 / 80'),
			'XXL'  => array('chest' => '180 / 100', 'waist' => '180 / 84'),
			'XXXL' => array('chest' => '185 / 104', 'waist' => '185 / 88')
		)
	);

	/**
	 * Convert size from one system to another
	 *
	 * @param string $value The size value (e.g., "37", "M", "6.5")
	 * @param string $from_system Source system (EU, US, UK, mm, Asian)
	 * @param string $to_system Target system
	 * @param string $gender women/universal/unisex
	 * @param string $size_type shoes/apparel
	 * @return string|false Converted size or false if not found
	 */
	public function convert($value, $from_system, $to_system, $gender, $size_type) {
		// Normalize value
		$value = trim($value);

		// If converting to same system, return value as-is
		if ($from_system === $to_system) {
			return $value;
		}

		// Get the appropriate conversion table
		$table = $this->getConversionTable($gender, $size_type);

		if (!$table || !isset($table[$from_system]) || !isset($table[$to_system])) {
			return false;
		}

		// Find the index of the value in the source system
		$index = array_search($value, $table[$from_system], true);

		if ($index === false) {
			// Try fuzzy matching for partial matches (e.g., "37" matches "37 2/3")
			$index = $this->fuzzyMatch($value, $table[$from_system]);
		}

		if ($index === false) {
			return false;
		}

		// Return the corresponding value in the target system
		return isset($table[$to_system][$index]) ? $table[$to_system][$index] : false;
	}

	/**
	 * Convert between genders (Women <-> Universal) using mm as intermediary
	 * Only works for shoes
	 *
	 * @param string $value Size value in current system
	 * @param string $current_system Current size system (EU, US, UK, mm)
	 * @param string $from_gender Source gender (women/universal)
	 * @param string $to_gender Target gender (women/universal)
	 * @return string|false Converted size or false if not found
	 */
	public function convertGender($value, $current_system, $from_gender, $to_gender) {
		// Only for shoes
		if ($from_gender === $to_gender) {
			return $value;
		}

		// Step 1: Convert from current system to mm in source gender
		$mm_value = $this->convert($value, $current_system, 'mm', $from_gender, 'shoes');

		if ($mm_value === false) {
			return false;
		}

		// Step 2: Convert from mm to target system in target gender
		$converted = $this->convert($mm_value, 'mm', $current_system, $to_gender, 'shoes');

		return $converted;
	}

	/**
	 * Get all available sizes in a specific system for a gender/type
	 *
	 * @param string $system Size system (EU, US, UK, mm, Asian)
	 * @param string $gender Gender category
	 * @param string $size_type Size type
	 * @return array|false Array of sizes or false
	 */
	public function getAllSizes($system, $gender, $size_type) {
		$table = $this->getConversionTable($gender, $size_type);

		if (!$table || !isset($table[$system])) {
			return false;
		}

		return $table[$system];
	}

	/**
	 * Get measurement guide for apparel
	 *
	 * @param string $gender Gender category
	 * @return array|false Measurements array or false
	 */
	public function getMeasurements($gender) {
		if ($gender === 'universal' || $gender === 'unisex') {
			return $this->apparel_men['measurements'];
		} elseif ($gender === 'women') {
			return $this->apparel_women['measurements'];
		}

		return false;
	}

	/**
	 * Parse size value from option value description
	 * Supports multiple formats from uniqsport.ru:
	 *   - "34 1/3 us(4,5)" -> extract "4,5" (US in parentheses)
	 *   - "Euro XXS [Asia (XS)]" -> extract "XS" (Asian in brackets)
	 *   - "130mm us(7C)" -> extract "7C" (US in parentheses)
	 *   - "EU 37", "Size M" -> extract "37", "M" (standard formats)
	 *
	 * @param string $description Option value description
	 * @param string $source_system Expected source system (US, Asian, EU, UK, mm)
	 * @return string|false Extracted size value or false
	 */
	public function parseSize($description, $source_system = null) {
		$description = trim($description);

		// UNIQSPORT SPECIFIC PATTERNS

		// Pattern US1: "34 1/3 us(4,5)" or "35 2/3 us(4)" -> Extract US value in parentheses
		// This is for shoe options where US size is in parentheses
		if ($source_system === 'US' && preg_match('/us\s*\(([0-9\.,C]+)\)/i', $description, $matches)) {
			return str_replace(',', '.', trim($matches[1])); // Convert comma to period
		}

		// Pattern Asian1: "Euro XXS [Asia (XS)]" -> Extract Asian value in brackets
		// This is for apparel where Asian size is in square brackets
		if ($source_system === 'Asian' && preg_match('/\[Asia\s*\(([A-Z0-9]+)\)\]/i', $description, $matches)) {
			return trim($matches[1]);
		}

		// Pattern mm1: "130mm us(7C)" -> Extract US value when source is US
		if ($source_system === 'US' && preg_match('/mm\s+us\s*\(([0-9\.,C]+)\)/i', $description, $matches)) {
			return str_replace(',', '.', trim($matches[1]));
		}

		// STANDARD PATTERNS (backwards compatibility)

		// Pattern 1: "EU 37", "US 6.5", "UK 4"
		if (preg_match('/^(EU|US|UK|Asian|mm|Size)\s+(.+)$/i', $description, $matches)) {
			return trim($matches[2]);
		}

		// Pattern 2: "37 EU", "6.5 US"
		if (preg_match('/^(.+)\s+(EU|US|UK|mm)$/i', $description, $matches)) {
			return trim($matches[1]);
		}

		// Pattern 3: Try to extract value in parentheses regardless (fallback)
		if (preg_match('/\(([0-9\.,A-Z]+)\)$/i', $description, $matches)) {
			return str_replace(',', '.', trim($matches[1]));
		}

		// Pattern 4: Try to extract value in brackets (fallback)
		if (preg_match('/\[([A-Z0-9]+)\]$/i', $description, $matches)) {
			return trim($matches[1]);
		}

		// Pattern 5: Just the size value (e.g., "37", "M", "6.5")
		// If it looks like a valid size, return it
		if (preg_match('/^[0-9\.\/\s]+$|^[XSML]+$/i', $description)) {
			return $description;
		}

		// Pattern 6: Try to extract any number or size letter combination
		if (preg_match('/([0-9]+[\s\/0-9]*|[XSML]+)/i', $description, $matches)) {
			return trim($matches[1]);
		}

		return false;
	}

	/**
	 * Get the appropriate conversion table based on gender and size type
	 *
	 * @param string $gender Gender category
	 * @param string $size_type Size type
	 * @return array|false Conversion table or false
	 */
	private function getConversionTable($gender, $size_type) {
		if ($size_type === 'shoes') {
			if ($gender === 'women') {
				return $this->women_shoes;
			} elseif ($gender === 'universal' || $gender === 'unisex') {
				return $this->universal_shoes;
			}
		} elseif ($size_type === 'apparel') {
			if ($gender === 'women') {
				return $this->apparel_women;
			} elseif ($gender === 'universal' || $gender === 'unisex') {
				return $this->apparel_men;
			}
		}

		return false;
	}

	/**
	 * Fuzzy matching for size values
	 * Handles cases like "37" matching "37 2/3" or "6.5" matching "6,5"
	 *
	 * @param string $needle Value to find
	 * @param array $haystack Array to search in
	 * @return int|false Index if found, false otherwise
	 */
	private function fuzzyMatch($needle, $haystack) {
		$needle = trim($needle);

		foreach ($haystack as $index => $value) {
			$value = trim($value);

			// Exact match
			if ($value === $needle) {
				return $index;
			}

			// Check if haystack value starts with needle (e.g., "37" matches "37 2/3")
			if (strpos($value, $needle) === 0) {
				// Make sure it's followed by space or end of string
				if (strlen($value) === strlen($needle) || $value[strlen($needle)] === ' ') {
					return $index;
				}
			}

			// Normalize and compare (handle comma vs period)
			$normalized_needle = str_replace(',', '.', $needle);
			$normalized_value = str_replace(',', '.', $value);

			if ($normalized_value === $normalized_needle) {
				return $index;
			}

			// Check if normalized haystack starts with normalized needle
			if (strpos($normalized_value, $normalized_needle) === 0) {
				if (strlen($normalized_value) === strlen($normalized_needle) || $normalized_value[strlen($normalized_needle)] === ' ') {
					return $index;
				}
			}
		}

		return false;
	}

	/**
	 * Get available size systems for a gender/type combination
	 *
	 * @param string $gender Gender category
	 * @param string $size_type Size type
	 * @return array Available systems (e.g., ['EU', 'US', 'UK', 'mm'])
	 */
	public function getAvailableSystems($gender, $size_type) {
		$table = $this->getConversionTable($gender, $size_type);

		if (!$table) {
			return array();
		}

		$systems = array_keys($table);

		// Remove 'measurements' from the list
		$systems = array_filter($systems, function($system) {
			return $system !== 'measurements';
		});

		return array_values($systems);
	}

	/**
	 * Check if a size exists in a specific system
	 *
	 * @param string $value Size value
	 * @param string $system Size system
	 * @param string $gender Gender category
	 * @param string $size_type Size type
	 * @return bool True if size exists
	 */
	public function sizeExists($value, $system, $gender, $size_type) {
		$table = $this->getConversionTable($gender, $size_type);

		if (!$table || !isset($table[$system])) {
			return false;
		}

		$index = array_search($value, $table[$system], true);

		if ($index === false) {
			$index = $this->fuzzyMatch($value, $table[$system]);
		}

		return $index !== false;
	}
}

class_alias('ModelJournal3SizeConverter', '\Opencart\Catalog\Model\Journal3\SizeConverter');
