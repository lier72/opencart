<?php
/**
 * Пересборка фильтра «Размер (RUS)» по всем товарам с размерными опциями обуви.
 *
 * Usage:
 *   php cli/sync_size_filter.php           # пересобрать фильтр (только размеры в наличии)
 *   php cli/sync_size_filter.php --check   # ничего не менять: показать значения опций,
 *                                          # которые не удалось перевести в RUS, и шкалу
 *
 * Запускать после первичной установки, после массового импорта остатков
 * и по расписанию (например, каждые 15 минут), если остатки меняются в обход
 * OpenCart (импорт, синхронизация с учётной системой).
 */

define('DIR_OPENCART', realpath(__DIR__ . '/..') . '/');
require_once DIR_OPENCART . 'config.php';
require_once DIR_SYSTEM . 'library/db.php';
require_once DIR_SYSTEM . 'library/db/' . DB_DRIVER . '.php';
require_once DIR_SYSTEM . 'library/sizerus.php';

$check = in_array('--check', $argv ?? array());

$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$sizerus = new Sizerus($db);

if ($check) {
	$options = implode(',', array_keys(Sizerus::$options));
	$lang = $db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE code LIKE 'ru%' LIMIT 1");
	$lang_id = $lang->num_rows ? (int)$lang->row['language_id'] : 1;

	$q = $db->query("SELECT ov.option_id, ovd.name, COUNT(DISTINCT pov.product_id) AS products
		FROM `" . DB_PREFIX . "option_value` ov
		JOIN `" . DB_PREFIX . "option_value_description` ovd ON ovd.option_value_id = ov.option_value_id AND ovd.language_id = '" . $lang_id . "'
		LEFT JOIN `" . DB_PREFIX . "product_option_value` pov ON pov.option_value_id = ov.option_value_id
		WHERE ov.option_id IN (" . $options . ")
		GROUP BY ov.option_value_id
		ORDER BY ov.option_id, ov.sort_order");

	$bad = 0;

	foreach ($q->rows as $row) {
		$mm = Sizerus::parseMm($row['option_id'], $row['name']);
		$rus = $mm ? Sizerus::rus($mm) : '—';

		if (!$mm) {
			$bad++;
		}

		printf("%s  opt %-3d  %-22s  %4s мм  RUS %-5s  товаров: %d\n",
			$mm ? 'OK ' : '!! ', $row['option_id'], $row['name'], $mm ?: '—', $rus, $row['products']);
	}

	echo "\nНе распознано: {$bad}\n";
	exit($bad ? 1 : 0);
}

$stats = $sizerus->syncFilterAll();

echo "Товаров обработано: {$stats['products']}\n";
echo "Связей товар → размер: {$stats['links']}\n";

if ($stats['unparsed']) {
	echo "Не распознаны значения (в фильтр не попали):\n  " . implode("\n  ", $stats['unparsed']) . "\n";
}
