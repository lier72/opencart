<?php
class ModelExtensionModuleColorDetector extends Model {

	public function install() {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "color_mapping` (
				`color_id` int(11) NOT NULL AUTO_INCREMENT,
				`keyword` varchar(255) NOT NULL,
				`color_name_ru` varchar(100) NOT NULL,
				`color_name_en` varchar(100) NOT NULL,
				`hex_code` varchar(7) NOT NULL,
				`sort_order` int(3) NOT NULL DEFAULT '0',
				PRIMARY KEY (`color_id`),
				KEY `keyword` (`keyword`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
		");

		// Insert default color mappings
		$default_colors = array(
			array('keyword' => 'черн', 'color_name_ru' => 'Черный', 'color_name_en' => 'Black', 'hex_code' => '#000000', 'sort_order' => 1),
			array('keyword' => 'бел', 'color_name_ru' => 'Белый', 'color_name_en' => 'White', 'hex_code' => '#FFFFFF', 'sort_order' => 2),
			array('keyword' => 'красн', 'color_name_ru' => 'Красный', 'color_name_en' => 'Red', 'hex_code' => '#FF0000', 'sort_order' => 3),
			array('keyword' => 'син', 'color_name_ru' => 'Синий', 'color_name_en' => 'Blue', 'hex_code' => '#0000FF', 'sort_order' => 4),
			array('keyword' => 'зелен', 'color_name_ru' => 'Зеленый', 'color_name_en' => 'Green', 'hex_code' => '#00FF00', 'sort_order' => 5),
			array('keyword' => 'желт', 'color_name_ru' => 'Желтый', 'color_name_en' => 'Yellow', 'hex_code' => '#FFFF00', 'sort_order' => 6),
			array('keyword' => 'оранж', 'color_name_ru' => 'Оранжевый', 'color_name_en' => 'Orange', 'hex_code' => '#FFA500', 'sort_order' => 7),
			array('keyword' => 'роз', 'color_name_ru' => 'Розовый', 'color_name_en' => 'Pink', 'hex_code' => '#FFC0CB', 'sort_order' => 8),
			array('keyword' => 'фиолет', 'color_name_ru' => 'Фиолетовый', 'color_name_en' => 'Purple', 'hex_code' => '#800080', 'sort_order' => 9),
			array('keyword' => 'коричнев', 'color_name_ru' => 'Коричневый', 'color_name_en' => 'Brown', 'hex_code' => '#A52A2A', 'sort_order' => 10),
			array('keyword' => 'сер', 'color_name_ru' => 'Серый', 'color_name_en' => 'Gray', 'hex_code' => '#808080', 'sort_order' => 11),
			array('keyword' => 'бежев', 'color_name_ru' => 'Бежевый', 'color_name_en' => 'Beige', 'hex_code' => '#F5F5DC', 'sort_order' => 12),
			array('keyword' => 'бордов', 'color_name_ru' => 'Бордовый', 'color_name_en' => 'Burgundy', 'hex_code' => '#800020', 'sort_order' => 13),
			array('keyword' => 'голуб', 'color_name_ru' => 'Голубой', 'color_name_en' => 'Light Blue', 'hex_code' => '#87CEEB', 'sort_order' => 14),
			array('keyword' => 'салатов', 'color_name_ru' => 'Салатовый', 'color_name_en' => 'Lime', 'hex_code' => '#00FF00', 'sort_order' => 15),
			array('keyword' => 'хаки', 'color_name_ru' => 'Хаки', 'color_name_en' => 'Khaki', 'hex_code' => '#F0E68C', 'sort_order' => 16),
			array('keyword' => 'navy', 'color_name_ru' => 'Темно-синий', 'color_name_en' => 'Navy', 'hex_code' => '#000080', 'sort_order' => 17),
			array('keyword' => 'maroon', 'color_name_ru' => 'Темно-красный', 'color_name_en' => 'Maroon', 'hex_code' => '#800000', 'sort_order' => 18),
		);

		foreach ($default_colors as $color) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "color_mapping SET
				keyword = '" . $this->db->escape($color['keyword']) . "',
				color_name_ru = '" . $this->db->escape($color['color_name_ru']) . "',
				color_name_en = '" . $this->db->escape($color['color_name_en']) . "',
				hex_code = '" . $this->db->escape($color['hex_code']) . "',
				sort_order = '" . (int)$color['sort_order'] . "'
			");
		}
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "color_mapping`");
	}

	public function addColor($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "color_mapping SET
			keyword = '" . $this->db->escape($data['keyword']) . "',
			color_name_ru = '" . $this->db->escape($data['color_name_ru']) . "',
			color_name_en = '" . $this->db->escape($data['color_name_en']) . "',
			hex_code = '" . $this->db->escape(strtoupper($data['hex_code'])) . "',
			sort_order = '" . (int)$data['sort_order'] . "'
		");

		return $this->db->getLastId();
	}

	public function editColor($color_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "color_mapping SET
			keyword = '" . $this->db->escape($data['keyword']) . "',
			color_name_ru = '" . $this->db->escape($data['color_name_ru']) . "',
			color_name_en = '" . $this->db->escape($data['color_name_en']) . "',
			hex_code = '" . $this->db->escape(strtoupper($data['hex_code'])) . "',
			sort_order = '" . (int)$data['sort_order'] . "'
			WHERE color_id = '" . (int)$color_id . "'
		");
	}

	public function deleteColor($color_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "color_mapping WHERE color_id = '" . (int)$color_id . "'");
	}

	public function getColor($color_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "color_mapping WHERE color_id = '" . (int)$color_id . "'");

		return $query->row;
	}

	public function getColors($data = array()) {
		$sql = "SELECT * FROM " . DB_PREFIX . "color_mapping";

		$sql .= " ORDER BY sort_order ASC, color_name_ru ASC";

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function detectColor($product) {
		$colors = $this->getColors();
		$product_name = mb_strtolower($product['name'], 'UTF-8');

		// Find all color matches with their positions in the product name
		$matches = array();

		foreach ($colors as $color) {
			$keyword = mb_strtolower($color['keyword'], 'UTF-8');
			$position = mb_strpos($product_name, $keyword);

			if ($position !== false) {
				// If found "цветн" (multicolor), mark for image detection
				if ($keyword === 'цветн') {
					// Use image detection for multicolor products
					break;
				}

				$matches[] = array(
					'position' => $position,
					'length' => mb_strlen($keyword, 'UTF-8'),
					'color_name_ru' => $color['color_name_ru'],
					'color_name_en' => $color['color_name_en'],
					'hex_code' => $color['hex_code']
				);
			}
		}

		// Sort matches by position (earliest first), then by length (longest first)
		if (!empty($matches)) {
			usort($matches, function($a, $b) {
				if ($a['position'] !== $b['position']) {
					return $a['position'] - $b['position'];
				}
				return $b['length'] - $a['length'];
			});

			// Return the first color found in the product name
			return array(
				'color_name_ru' => $matches[0]['color_name_ru'],
				'color_name_en' => $matches[0]['color_name_en'],
				'hex_code' => $matches[0]['hex_code']
			);
		}

		// Try to detect from image if no color in name or if product is marked as "multicolor"
		if (!empty($product['image'])) {
			$image_path = DIR_IMAGE . $product['image'];

			if (file_exists($image_path)) {
				$detected_hex = $this->getDominantColorFromImage($image_path);

				if ($detected_hex) {
					$closest_color = $this->findClosestColor($detected_hex, $colors);

					if ($closest_color) {
						return array(
							'color_name_ru' => $closest_color['color_name_ru'],
							'color_name_en' => $closest_color['color_name_en'],
							'hex_code' => $closest_color['hex_code']
						);
					}
				}
			}
		}

		return false;
	}

	private function getDominantColorFromImage($image_path) {
		// Check if GD is available
		if (!extension_loaded('gd')) {
			return false;
		}

		// Get image info
		$image_info = getimagesize($image_path);

		if (!$image_info) {
			return false;
		}

		// Create image resource based on type
		switch ($image_info[2]) {
			case IMAGETYPE_JPEG:
				$image = imagecreatefromjpeg($image_path);
				break;
			case IMAGETYPE_PNG:
				$image = imagecreatefrompng($image_path);
				break;
			case IMAGETYPE_GIF:
				$image = imagecreatefromgif($image_path);
				break;
			default:
				return false;
		}

		if (!$image) {
			return false;
		}

		// Resize image to speed up processing
		$width = imagesx($image);
		$height = imagesy($image);
		$new_width = 50;
		$new_height = floor($height * ($new_width / $width));

		$thumb = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($thumb, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

		// Count colors
		$colors = array();

		for ($x = 0; $x < $new_width; $x++) {
			for ($y = 0; $y < $new_height; $y++) {
				$rgb = imagecolorat($thumb, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;

				// Skip white and very light colors
				if ($r > 240 && $g > 240 && $b > 240) {
					continue;
				}

				// Skip black and very dark colors
				if ($r < 15 && $g < 15 && $b < 15) {
					continue;
				}

				// Round to nearest 32 to group similar colors
				$r = round($r / 32) * 32;
				$g = round($g / 32) * 32;
				$b = round($b / 32) * 32;

				$hex = sprintf("#%02X%02X%02X", $r, $g, $b);

				if (!isset($colors[$hex])) {
					$colors[$hex] = 0;
				}
				$colors[$hex]++;
			}
		}

		imagedestroy($thumb);
		imagedestroy($image);

		if (empty($colors)) {
			return false;
		}

		// Get most common color
		arsort($colors);
		$dominant_color = key($colors);

		return $dominant_color;
	}

	private function findClosestColor($target_hex, $colors) {
		$target_rgb = $this->hexToRgb($target_hex);

		if (!$target_rgb) {
			return false;
		}

		$closest_color = null;
		$min_distance = PHP_INT_MAX;

		foreach ($colors as $color) {
			$color_rgb = $this->hexToRgb($color['hex_code']);

			if (!$color_rgb) {
				continue;
			}

			// Calculate color distance using Euclidean distance in RGB space
			$distance = sqrt(
				pow($target_rgb['r'] - $color_rgb['r'], 2) +
				pow($target_rgb['g'] - $color_rgb['g'], 2) +
				pow($target_rgb['b'] - $color_rgb['b'], 2)
			);

			if ($distance < $min_distance) {
				$min_distance = $distance;
				$closest_color = $color;
			}
		}

		return $closest_color;
	}

	private function hexToRgb($hex) {
		$hex = str_replace('#', '', $hex);

		if (strlen($hex) != 6) {
			return false;
		}

		return array(
			'r' => hexdec(substr($hex, 0, 2)),
			'g' => hexdec(substr($hex, 2, 2)),
			'b' => hexdec(substr($hex, 4, 2))
		);
	}

	public function setProductColor($product_id, $attribute_id, $color_data) {
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		// Delete existing color attribute
		$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute
			WHERE product_id = '" . (int)$product_id . "'
			AND attribute_id = '" . (int)$attribute_id . "'
		");

		// Add color attribute for each language
		foreach ($languages as $language) {
			$color_name = ($language['code'] == 'ru-ru' || $language['code'] == 'ru')
				? $color_data['color_name_ru']
				: $color_data['color_name_en'];

			$text = $color_name . ' (' . $color_data['hex_code'] . ')';

			$this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET
				product_id = '" . (int)$product_id . "',
				attribute_id = '" . (int)$attribute_id . "',
				language_id = '" . (int)$language['language_id'] . "',
				text = '" . $this->db->escape($text) . "'
			");
		}
	}
}
