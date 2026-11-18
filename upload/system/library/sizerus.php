<?php
/**
 * Sizerus — единая российская размерная шкала обуви (RUS) для Uniqsport.
 *
 * Принцип: каждый размер любого бренда переводится в длину стопы (мм),
 * а длина стопы — в RUS по одной шкале (шаг 5 мм = одна ступень RUS).
 * Шкала 210–315 мм совпадает с RUS Спортмастера; ниже 210 мм и для baby
 * используется та же формула, что и у них: RUS = 1,5 × см + 1,5
 * (значения .25/.75 округляются до целого); выше 315 мм — +0,5 на 5 мм.
 *
 * Хранение в OpenCart не меняется: опции 22/23/26/29 со значениями
 * вида "41 us(8)" / "130mm us(7C)". RUS вычисляется из этих значений.
 *
 * Использование:
 *   $this->load->library('sizerus');           // в контроллерах/моделях OC
 *   $this->sizerus->resolve(23, '41 us(8)', 'victor');
 *   new Sizerus($db);                          // в CLI с объектом \DB
 */
class Sizerus {

	/** Опции размеров обуви: option_id => сетка и привязка US → мм */
	public static $options = array(
		22 => array('grid' => 'lining_w',  'mm0' => 205, 'us0' => 4.0),  // Размер женской обуви (US)
		23 => array('grid' => 'lining_mu', 'mm0' => 215, 'us0' => 4.0),  // Размер обуви унисекс (US)
		26 => array('grid' => 'lining_k',  'mm0' => 185, 'us0' => 0.5),  // Размер детской обуви (US)
		29 => array('grid' => 'baby',      'mm0' => null, 'us0' => null), // Размер обуви baby: "130mm us(7C)"
	);

	/** Название группы фильтров OpenCart, которую заполняет syncFilter*() */
	const FILTER_GROUP_NAME = 'Размер (RUS)';

	/** Опорная шкала мм → RUS (210–315 сверено со Спортмастером) */
	public static $rusByMm = array(
		180 => '28.5', 185 => '29',   190 => '30',   195 => '31',   200 => '31.5', 205 => '32',
		210 => '33',   215 => '34',   220 => '34.5', 225 => '35',   230 => '36',   235 => '37',
		240 => '37.5', 245 => '38',   250 => '39',   255 => '40',   260 => '40.5', 265 => '41',
		270 => '42',   275 => '43',   280 => '43.5', 285 => '44',   290 => '44.5', 295 => '45',
		300 => '45.5', 305 => '46',   310 => '46.5', 315 => '47',   320 => '47.5', 325 => '48',
		330 => '48.5', 335 => '49',   340 => '49.5', 345 => '50',   350 => '50.5', 355 => '51',
	);

	/** Victor (с коробки): мм => [EUR, UK, US муж., US жен.] */
	public static $victor = array(
		180 => array('30', '1', '1.5', '3'),       190 => array('31', '1.5', '2', '3.5'),
		200 => array('32', '2', '2.5', '4'),       205 => array('33', '2.5', '3', '4.5'),
		210 => array('34', '3', '3.5', '5'),       220 => array('35', '3.5', '4', '5.5'),
		225 => array('36', '4', '4.5', '6'),       230 => array('37', '4.5', '5', '6.5'),
		235 => array('37.5', '5', '5.5', '7'),     240 => array('38', '5.5', '6', '7.5'),
		245 => array('39', '6', '6.5', '8'),       250 => array('39.5', '6.5', '7', '8.5'),
		255 => array('40', '7', '7.5', '9'),       260 => array('40.5', '7.5', '8', '9.5'),
		265 => array('41', '8', '8.5', '10'),      270 => array('42', '8.5', '9', '10.5'),
		275 => array('43', '9', '9.5', '11'),      280 => array('44', '9.5', '10', '11.5'),
		285 => array('44.5', '10', '10.5', '12'),  290 => array('45', '10.5', '11', '12.5'),
		295 => array('45.5', '11', '11.5', '13'),  300 => array('46', '11.5', '12', '13.5'),
		305 => array('47', '12', '12.5', '14'),    310 => array('47.5', '12.5', '13', '14.5'),
		315 => array('48', '13', '13.5', '15'),
	);

	/** Диапазоны сеток Li-Ning (мм) с коробки */
	public static $liningRange = array(
		'lining_mu' => array(215, 355),
		'lining_w'  => array(205, 305),
		'lining_k'  => array(185, 220),
	);

	private $db;
	private $languages = null;

	public function __construct($registry_or_db) {
		if (is_object($registry_or_db) && method_exists($registry_or_db, 'get') && !method_exists($registry_or_db, 'query')) {
			$this->db = $registry_or_db->get('db');
		} else {
			$this->db = $registry_or_db;
		}
	}

	// ─────────────────────────────────────────────────────────────────────
	// Шкала и сетки
	// ─────────────────────────────────────────────────────────────────────

	/** Длина стопы (мм) → RUS, строкой: "40", "40.5" */
	public static function rus($mm) {
		$mm = (int)$mm;

		if (isset(self::$rusByMm[$mm])) {
			return self::$rusByMm[$mm];
		}

		if ($mm > 355) {
			$v = 51 + ($mm - 355) / 10;
		} else {
			// формула Спортмастера: 1,5 × см + 1,5; .25/.75 → до целого
			$v = 1.5 * $mm / 10 + 1.5;
			$frac = $v - floor($v);

			if (abs($frac - 0.25) < 0.01 || abs($frac - 0.75) < 0.01) {
				$v = round($v);
			} else {
				$v = round($v * 2) / 2;
			}
		}

		return self::num($v);
	}

	/** Li-Ning EUR по мм для сетки (трети: 41, 41 1/3, 41 2/3) */
	public static function liningEur($grid, $mm) {
		// унисекс: 215 мм = 35 2/3 (107/3); жен./дет.: 205 мм = 33 2/3 (101/3); +2/3 на каждые 5 мм
		$thirds = ($grid === 'lining_mu') ? 107 + 2 * ($mm - 215) / 5 : 101 + 2 * ($mm - 205) / 5;
		$thirds = (int)round($thirds);
		$int = intdiv($thirds, 3);
		$rest = $thirds % 3;

		return $int . ($rest ? ' ' . $rest . '/3' : '');
	}

	/** Li-Ning US по мм для сетки */
	public static function liningUs($grid, $mm) {
		$cfg = self::gridOption($grid);

		return self::num($cfg['us0'] + ($mm - $cfg['mm0']) / 10);
	}

	/** Li-Ning UK (как в существующих таблицах модуля): унисекс US−1, жен. US−2,5; детской нет */
	public static function liningUk($grid, $mm) {
		$cfg = self::gridOption($grid);
		$us = $cfg['us0'] + ($mm - $cfg['mm0']) / 10;

		if ($grid === 'lining_mu') {
			return self::num($us - 1);
		}

		if ($grid === 'lining_w') {
			return self::num($us - 2.5);
		}

		return null;
	}

	/**
	 * Подписи размера во всех системах.
	 *
	 * @param int    $option_id  опция OpenCart (22/23/26/29)
	 * @param string $value_name значение опции, например "41 us(8)"
	 * @param string $brand      'victor' | 'lining' | ''
	 * @return array|null ['mm'=>255,'RUS'=>'40','EU'=>'41','US'=>'8','UK'=>'7','cm'=>'25,5'] или null
	 */
	public function resolve($option_id, $value_name, $brand = '') {
		$mm = self::parseMm($option_id, $value_name);

		if (!$mm) {
			return null;
		}

		$labels = array(
			'RUS' => self::rus($mm),
			'EU'  => null,
			'US'  => null,
			'UK'  => null,
			'mm'  => (string)$mm,
			'cm'  => str_replace('.', ',', self::num($mm / 10)),
		);

		$grid = self::$options[$option_id]['grid'];

		if ($brand === 'victor' && isset(self::$victor[$mm])) {
			$row = self::$victor[$mm];
			$labels['EU'] = $row[0];
			$labels['UK'] = $row[1];
			$labels['US'] = ((int)$option_id === 22) ? $row[3] : $row[2];
		} elseif ($grid === 'baby') {
			if (preg_match('/us\s*\(\s*([0-9.,]+\s*C?)\s*\)/i', $value_name, $m)) {
				$labels['US'] = str_replace(',', '.', trim($m[1]));
			}
		} else {
			$labels['EU'] = self::liningEur($grid, $mm);
			$labels['US'] = self::liningUs($grid, $mm);
			$labels['UK'] = self::liningUk($grid, $mm);
		}

		$labels['mm_int'] = $mm;

		return $labels;
	}

	/**
	 * Таблица размеров для всплывающего окна: системы → столбцы.
	 * Для Victor — сетка Victor, иначе — сетка Li-Ning опции.
	 */
	public function guideTable($option_id, $brand = '') {
		$option_id = (int)$option_id;

		if (!isset(self::$options[$option_id]) || self::$options[$option_id]['grid'] === 'baby') {
			return null;
		}

		$grid = self::$options[$option_id]['grid'];

		if ($brand === 'victor') {
			$range = ($grid === 'lining_k') ? array(180, 220) : array(205, 315);
			$mms = array_values(array_filter(array_keys(self::$victor), function ($mm) use ($range) {
				return $mm >= $range[0] && $mm <= $range[1];
			}));
		} else {
			list($from, $to) = self::$liningRange[$grid];
			$mms = range($from, $to, 5);
		}

		$table = array('RUS' => array(), 'EU' => array(), 'US' => array(), 'UK' => array(), 'mm' => array());

		foreach ($mms as $mm) {
			if ($brand === 'victor') {
				$row = self::$victor[$mm];
				$eu = $row[0];
				$uk = $row[1];
				$us = ($option_id === 22) ? $row[3] : $row[2];
			} else {
				$eu = self::liningEur($grid, $mm);
				$us = self::liningUs($grid, $mm);
				$uk = self::liningUk($grid, $mm);
			}

			$table['RUS'][] = self::rus($mm);
			$table['EU'][]  = $eu;
			$table['US'][]  = $us;
			$table['UK'][]  = $uk === null ? '' : $uk;
			$table['mm'][]  = (string)$mm;
		}

		if (!array_filter($table['UK'])) {
			unset($table['UK']);
		}

		return $table;
	}

	/** Длина стопы (мм) из значения опции или null */
	public static function parseMm($option_id, $value_name) {
		$option_id = (int)$option_id;

		if (!isset(self::$options[$option_id])) {
			return null;
		}

		$cfg = self::$options[$option_id];

		if ($cfg['grid'] === 'baby') {
			if (preg_match('/(\d{2,3})\s*mm/i', $value_name, $m)) {
				return (int)$m[1];
			}

			return null;
		}

		if (!preg_match('/us\s*\(\s*([0-9]+(?:[.,][0-9]+)?)\s*\)/i', $value_name, $m)) {
			return null;
		}

		$us = (float)str_replace(',', '.', $m[1]);
		$mm = (int)round($cfg['mm0'] + ($us - $cfg['us0']) * 10);

		// защита от опечаток: только шаг 5 мм и внутри сетки
		list($from, $to) = self::$liningRange[$cfg['grid']];

		if ($mm % 5 !== 0 || $mm < $from || $mm > $to) {
			return null;
		}

		return $mm;
	}

	/** 'victor' | 'lining' | '' по производителю товара */
	public function brandOf($product_id) {
		$q = $this->db->query("SELECT m.name FROM `" . DB_PREFIX . "product` p
			LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON m.manufacturer_id = p.manufacturer_id
			WHERE p.product_id = '" . (int)$product_id . "'");

		$name = $q->num_rows ? mb_strtolower((string)$q->row['name']) : '';

		if (strpos($name, 'victor') !== false) {
			return 'victor';
		}

		if (strpos($name, 'li-ning') !== false || strpos($name, 'lining') !== false || strpos($name, 'li ning') !== false) {
			return 'lining';
		}

		return '';
	}

	// ─────────────────────────────────────────────────────────────────────
	// Фильтр «Размер (RUS)»
	// ─────────────────────────────────────────────────────────────────────

	/** Пересобрать фильтр для одного товара */
	public function syncFilterProduct($product_id) {
		$this->syncFilter(array((int)$product_id));
	}

	/** Пересобрать фильтр для всех товаров с размерными опциями. Возвращает статистику. */
	public function syncFilterAll() {
		$ids = array();
		$q = $this->db->query("SELECT DISTINCT product_id FROM `" . DB_PREFIX . "product_option`
			WHERE option_id IN (" . implode(',', array_keys(self::$options)) . ")");

		foreach ($q->rows as $row) {
			$ids[] = (int)$row['product_id'];
		}

		return $this->syncFilter($ids, true);
	}

	/**
	 * @param int[] $product_ids
	 * @param bool  $link_categories привязать фильтры к категориям товаров (для Journal «filtersCategoryCheck»)
	 */
	public function syncFilter(array $product_ids, $link_categories = false) {
		$stats = array('products' => 0, 'links' => 0, 'unparsed' => array());

		$product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));

		if (!$product_ids) {
			return $stats;
		}

		$group_id = $this->filterGroupId();
		$filter_ids = $this->filterIdsByLabel($group_id);
		$lang_id = $this->defaultLanguageId();

		foreach (array_chunk($product_ids, 200) as $chunk) {
			$in = implode(',', $chunk);

			$q = $this->db->query("SELECT po.product_id, po.option_id, ovd.name, pov.quantity, pov.subtract, p.quantity AS product_quantity
				FROM `" . DB_PREFIX . "product_option` po
				JOIN `" . DB_PREFIX . "product` p ON p.product_id = po.product_id
				JOIN `" . DB_PREFIX . "product_option_value` pov ON pov.product_option_id = po.product_option_id
				JOIN `" . DB_PREFIX . "option_value_description` ovd ON ovd.option_value_id = pov.option_value_id AND ovd.language_id = '" . (int)$lang_id . "'
				WHERE po.product_id IN (" . $in . ")
				  AND po.option_id IN (" . implode(',', array_keys(self::$options)) . ")");

			$wanted = array();

			foreach ($q->rows as $row) {
				$in_stock = $row['subtract'] ? ((int)$row['quantity'] > 0) : ((int)$row['product_quantity'] > 0);

				if (!$in_stock) {
					continue;
				}

				$mm = self::parseMm($row['option_id'], $row['name']);

				if (!$mm) {
					$stats['unparsed'][$row['option_id'] . ': ' . $row['name']] = true;
					continue;
				}

				$label = self::rus($mm);

				if (!isset($filter_ids[$label])) {
					$filter_ids[$label] = $this->addFilter($group_id, $label, $mm);
				}

				$wanted[(int)$row['product_id']][$filter_ids[$label]] = true;
			}

			$group_filter_ids = implode(',', array_map('intval', $filter_ids)) ?: '0';

			$this->db->query("DELETE FROM `" . DB_PREFIX . "product_filter`
				WHERE product_id IN (" . $in . ") AND filter_id IN (" . $group_filter_ids . ")");

			$values = array();

			foreach ($wanted as $pid => $fids) {
				foreach (array_keys($fids) as $fid) {
					$values[] = "('" . (int)$pid . "','" . (int)$fid . "')";
				}
			}

			if ($values) {
				$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "product_filter` (product_id, filter_id) VALUES " . implode(',', $values));

				if ($link_categories) {
					$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "category_filter` (category_id, filter_id)
						SELECT DISTINCT p2c.category_id, pf.filter_id
						FROM `" . DB_PREFIX . "product_filter` pf
						JOIN `" . DB_PREFIX . "product_to_category` p2c ON p2c.product_id = pf.product_id
						WHERE pf.product_id IN (" . $in . ") AND pf.filter_id IN (" . implode(',', array_map('intval', $filter_ids)) . ")");
				}
			}

			$stats['products'] += count($chunk);
			$stats['links'] += count($values);
		}

		$stats['unparsed'] = array_keys($stats['unparsed']);

		return $stats;
	}

	private function filterGroupId() {
		$q = $this->db->query("SELECT filter_group_id FROM `" . DB_PREFIX . "filter_group_description`
			WHERE name = '" . $this->db->escape(self::FILTER_GROUP_NAME) . "' LIMIT 1");

		if ($q->num_rows) {
			return (int)$q->row['filter_group_id'];
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_group` SET sort_order = '0'");
		$group_id = (int)$this->db->getLastId();

		foreach ($this->languageIds() as $lang_id) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_group_description` SET filter_group_id = '" . $group_id . "',
				language_id = '" . (int)$lang_id . "', name = '" . $this->db->escape(self::FILTER_GROUP_NAME) . "'");
		}

		return $group_id;
	}

	/** RUS-подпись → filter_id в группе */
	private function filterIdsByLabel($group_id) {
		$ids = array();
		$q = $this->db->query("SELECT f.filter_id, fd.name FROM `" . DB_PREFIX . "filter` f
			JOIN `" . DB_PREFIX . "filter_description` fd ON fd.filter_id = f.filter_id AND fd.language_id = '" . (int)$this->defaultLanguageId() . "'
			WHERE f.filter_group_id = '" . (int)$group_id . "'");

		foreach ($q->rows as $row) {
			$ids[$row['name']] = (int)$row['filter_id'];
		}

		return $ids;
	}

	private function addFilter($group_id, $label, $mm) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "filter` SET filter_group_id = '" . (int)$group_id . "', sort_order = '" . (int)$mm . "'");
		$filter_id = (int)$this->db->getLastId();

		foreach ($this->languageIds() as $lang_id) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_description` SET filter_id = '" . $filter_id . "',
				language_id = '" . (int)$lang_id . "', filter_group_id = '" . (int)$group_id . "', name = '" . $this->db->escape($label) . "'");
		}

		return $filter_id;
	}

	private function languageIds() {
		if ($this->languages === null) {
			$this->languages = array();
			$q = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` ORDER BY sort_order, language_id");

			foreach ($q->rows as $row) {
				$this->languages[] = (int)$row['language_id'];
			}
		}

		return $this->languages;
	}

	/** Язык, по которому читаются названия значений опций (русский, если есть) */
	private function defaultLanguageId() {
		$q = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE code LIKE 'ru%' LIMIT 1");

		if ($q->num_rows) {
			return (int)$q->row['language_id'];
		}

		$ids = $this->languageIds();

		return $ids ? $ids[0] : 1;
	}

	// ─────────────────────────────────────────────────────────────────────
	// Заказ: дописать RUS к размеру
	// ─────────────────────────────────────────────────────────────────────

	/** Дописывает " · RUS 40" к размерным опциям заказа (один раз). */
	public function labelOrder($order_id) {
		$q = $this->db->query("SELECT oo.order_option_id, oo.value, po.option_id
			FROM `" . DB_PREFIX . "order_option` oo
			JOIN `" . DB_PREFIX . "product_option` po ON po.product_option_id = oo.product_option_id
			WHERE oo.order_id = '" . (int)$order_id . "'
			  AND po.option_id IN (" . implode(',', array_keys(self::$options)) . ")");

		foreach ($q->rows as $row) {
			if (stripos($row['value'], 'RUS') !== false) {
				continue;
			}

			$mm = self::parseMm($row['option_id'], $row['value']);

			if (!$mm) {
				continue;
			}

			$value = $row['value'] . ' · RUS ' . self::rus($mm);

			$this->db->query("UPDATE `" . DB_PREFIX . "order_option` SET `value` = '" . $this->db->escape($value) . "'
				WHERE order_option_id = '" . (int)$row['order_option_id'] . "'");
		}
	}

	/** product_id товаров заказа */
	public function orderProductIds($order_id) {
		$ids = array();
		$q = $this->db->query("SELECT DISTINCT product_id FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . (int)$order_id . "'");

		foreach ($q->rows as $row) {
			$ids[] = (int)$row['product_id'];
		}

		return $ids;
	}

	// ─────────────────────────────────────────────────────────────────────

	private static function gridOption($grid) {
		foreach (self::$options as $cfg) {
			if ($cfg['grid'] === $grid) {
				return $cfg;
			}
		}

		return array('mm0' => 0, 'us0' => 0);
	}

	/** 40.0 → "40", 40.5 → "40.5" */
	private static function num($v) {
		$v = round((float)$v, 2);

		return (floor($v) == $v) ? (string)(int)$v : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
	}
}
