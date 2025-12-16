#!/usr/bin/env php
<?php
// Check if the current PHP version is 8.2 or higher

use Opencart\Catalog\Controller\Journal3\Product;

if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    // Suppress deprecation warnings only for this version and above
	echo 'Inside the IF Current PHP version: ' . PHP_VERSION . "\n";
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// Version
define('VERSION', '3.0.3.6');
/**
 * CLI tool to add model_name attribute to products
 * Usage:
 *   php add_model_name_attribute.php --product_id=123       # Process single product
 *   php add_model_name_attribute.php --all                  # Process all products in configured categories
 *   php add_model_name_attribute.php --dry-run              # Test without saving
 */

// Load OpenCart configuration
$admin_dir = dirname(__FILE__) . '/../admin/';
if (file_exists($admin_dir . 'config.php')) {
	require_once($admin_dir . 'config.php');
} else {
	die("ERROR: Cannot access admin/config.php\n");
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);

// Config
$config = new Config();
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$registry->set('db', $db);

// Settings - load configuration from database
$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0'");
foreach ($query->rows as $setting) {
	if (!$setting['serialized']) {
		$config->set($setting['key'], $setting['value']);
	} else {
		$config->set($setting['key'], json_decode($setting['value'], true));
	}
}

// Parse command line arguments
$options = getopt('', array('product_id:', 'all', 'dry-run', 'help'));

if (isset($options['help'])) {
	echo "Usage:\n";
	echo "  php add_model_name_attribute.php --product_id=123       # Process single product\n";
	echo "  php add_model_name_attribute.php --all                  # Process all products in Yandex Market categories\n";
	echo "  php add_model_name_attribute.php --dry-run              # Test without saving\n";
	exit(0);
}

$dry_run = isset($options['dry-run']);

/**
 * Model Name Parser Class
 * Uses the same logic as yandex_market feed
 */
class ModelNameParser {
	private $vendors = array('Yonex', 'Li-NING', 'RSL', 'ChaoPai', 'Uniqsport');
	private $genders = array('мужские', 'женские', 'унисекс', 'детские', 'мужская', 'женская');
	private $sports = array('бадминтон', 'теннис', 'сквош', 'футбол', 'баскетбол', 'волейбол');
	private $type_prefixes = array(
		'ракетка' => 'Ракетка',
		'ракетки' => 'Ракетка',
		'воланы' => 'Волан',
		'волан' => 'Волан',
		'сетка' => 'Сетка',
		'стойки' => 'Стойки',
		'обувь' => 'Кроссовки',
		'кроссовки' => 'Кроссовки',
		'футболка' => 'Футболка',
		'шорты' => 'Шорты',
		'юбка' => 'Юбка',
		'платье' => 'Платье',
		'сумка' => 'Сумка',
		'рюкзак' => 'Рюкзак',
		'чехол' => 'Сумка',
		'грип' => 'Обмотка',
		'обмотка' => 'Обмотка',
		'намотка' => 'Обмотка',
		'струны' => 'Струны',
		'струна' => 'Струна',
		'мяч' => 'Мяч',
		'мячи' => 'Мяч',
		'поло' => 'Поло',
		'комплект' => 'Комплект',
		'комплекты' => 'Комплект',
		'куртка' => 'Куртка',
		'толстовка' => 'Толстовка',
		'ветровка' => 'Ветровка',
		'брюки' => 'Брюки',
		'носки' => 'Носки',
		'бюстгалтер' => 'Топ',
		'топ' => 'Топ',
		'бра' => 'Топ',

	);

	/**
	 * Parse product name to extract model name
	 * This is adapted from the yandex_market feed parseProductName method
	 */
	public function parseModelName($name, $manufacturer = '', $product_model = '') {
		$name_lower = mb_strtolower($name, 'UTF-8');
		$words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);

		$model = '';

		// Look for vendor in the name
		$vendor_found = false;
		$vendor_pos = false;
		$vendor = '';

		foreach ($this->vendors as $v) {
			if (mb_stripos($name_lower, mb_strtolower($v, 'UTF-8')) !== false) {
				$vendor = $v;
				$vendor_pos = mb_stripos($name_lower, mb_strtolower($v, 'UTF-8'));
				$vendor_found = true;
				break;
			}
		}

		// If no vendor found in name but manufacturer is set, use it
		if (!$vendor_found && !empty($manufacturer)) {
			$vendor = $manufacturer;
			$vendor_found = true;
		}

		// Extract model name
		if ($vendor_found && $vendor !== 'Uniqsport') {
			// First, try to extract model BEFORE vendor position
			if ($vendor_pos !== false) {
				$before_vendor = trim(mb_substr($name, 0, $vendor_pos));

				// Extract capitalized model name before vendor (like Falcon, Astrox, AXForce)
				if (preg_match_all('/\b([A-Z][A-Za-z0-9]+(?:\s+[0-9]+(?:\.[0-9]+)?)?(?:\s+[A-Z][A-Za-z0-9-]*)*)\b/u', $before_vendor, $matches)) {
					// Get all capitalized sequences
					$potential_models = array();
					foreach ($matches[1] as $match) {
						// Skip type prefixes, genders, sports
						$match_lower = mb_strtolower($match, 'UTF-8');
						$is_descriptor = false;

						// Check if it's a type prefix
						foreach (array_keys($this->type_prefixes) as $prefix) {
							if (mb_stripos($match_lower, $prefix) !== false) {
								$is_descriptor = true;
								break;
							}
						}

						// Check if it's a gender or sport
						if (!$is_descriptor && (in_array($match_lower, $this->genders) || in_array($match_lower, $this->sports))) {
							$is_descriptor = true;
						}

						// If it's a valid model name, add it
						if (!$is_descriptor && strlen($match) > 2) {
							$potential_models[] = $match;
						}
					}

					// Use the last capitalized word(s) before vendor as model
					if (!empty($potential_models)) {
						$model = end($potential_models);
					}
				}
			}

			// If no model found before vendor, try after vendor
			if (empty($model) && $vendor_pos !== false) {
				$after_vendor = trim(mb_substr($name, $vendor_pos + mb_strlen($vendor)));

				// Extract capitalized model name (like Astrox, Falcon, AXForce)
				if (preg_match('/([A-Z][A-Za-z0-9-]+(?:\s+[A-Z0-9][A-Za-z0-9-]*)*)/u', $after_vendor, $matches)) {
					$model = trim($matches[1]);
				} elseif (!empty($after_vendor)) {
					// If no capitalized pattern found, use what's after vendor
					$model = preg_replace('/\s+/u', ' ', $after_vendor);
				}
				if (preg_match('/\s+\b[A-Z]{2,}\d+(?:-\d+)?\b$/u', $model)) {
 				   $model = preg_replace('/\s+\b[A-Z]{2,}\d+(?:-\d+)?\b$/u', '', $model);
				}
			}
		}

		// If model is still empty, try to find capitalized words in the name
		if (empty($model)) {
			if (preg_match_all('/\b([A-Z][A-Za-z0-9]+(?:-[A-Za-z0-9]+)?)\b/u', $name, $matches)) {
				$potential_models = array();
				foreach ($matches[1] as $match) {
					if (strlen($match) <= 2) {
						continue;
					}
					// Skip vendor name
					if (in_array($match, $this->vendors)) {
						continue;
					}
					$potential_models[] = $match;
				}

				// If something meaningful remains — use it
				if (!empty($potential_models)) {
					$model = implode(' ', $potential_models);
				} else {
					// Otherwise fall back to vendor code
					$model = $product_model;
				}
			}
		}

		// Fallback: if still no model, use last significant words from name
		if (empty($model)) {
			$significant_words = array();
			foreach ($words as $word) {
				$word_lower = mb_strtolower($word, 'UTF-8');
				// Skip common descriptive words
				if (!in_array($word_lower, array_merge($this->genders, $this->sports, array_keys($this->type_prefixes)))) {
					$significant_words[] = $word;
				}
			}
			if (!empty($significant_words)) {
				// Take last 1-3 words as model
				$model = implode(' ', array_slice($significant_words, -3));
			}
		}

		// If still no model, use the whole name as model
		if (empty($model)) {
			$model = $product_model;
		}
		// If model fell back to vendor code, strip color / variant suffix (-1, -12)
		if (!empty($model) && $model === $product_model) {
 		   $model = preg_replace('/-\d{1,2}$/', '', $model);
		}

		return $model;
	}
}

/**
 * Ensure model_name attribute exists
 */
function ensureAttributeExists($db, $config) {
	// Check if attribute exists
	$query = $db->query("
		SELECT a.attribute_id
		FROM " . DB_PREFIX . "attribute a
		LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id)
		WHERE ad.name = 'Модель'
		AND ad.language_id = '1'
		LIMIT 1
	");

	if ($query->num_rows > 0) {
		echo "Attribute 'Модель' already exists (ID: " . $query->row['attribute_id'] . ")\n";
		return (int)$query->row['attribute_id'];
	}

	// Get or create attribute group
	$group_query = $db->query("
		SELECT ag.attribute_group_id
		FROM " . DB_PREFIX . "attribute_group ag
		LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (ag.attribute_group_id = agd.attribute_group_id)
		WHERE agd.name = 'Общий'
		AND agd.language_id = '1'
		LIMIT 1
	");

	if ($group_query->num_rows > 0) {
		$attribute_group_id = (int)$group_query->row['attribute_group_id'];
	} else {
		// Create attribute group
		$db->query("INSERT INTO " . DB_PREFIX . "attribute_group SET sort_order = 1");
		$attribute_group_id = $db->getLastId();

		// Add descriptions for both languages
		$db->query("INSERT INTO " . DB_PREFIX . "attribute_group_description SET attribute_group_id = '" . (int)$attribute_group_id . "', language_id = '1', name = 'Общий'");
		$db->query("INSERT INTO " . DB_PREFIX . "attribute_group_description SET attribute_group_id = '" . (int)$attribute_group_id . "', language_id = '2', name = 'General'");

		echo "Created attribute group 'Общий' (ID: $attribute_group_id)\n";
	}

	// Create attribute
	$db->query("INSERT INTO " . DB_PREFIX . "attribute SET attribute_group_id = '" . (int)$attribute_group_id . "', sort_order = 1");
	$attribute_id = $db->getLastId();

	// Add descriptions for both languages (1=English, 2=Russian based on typical OC setup)
	$db->query("INSERT INTO " . DB_PREFIX . "attribute_description SET attribute_id = '" . (int)$attribute_id . "', language_id = '1', name = 'Модель'");
	$db->query("INSERT INTO " . DB_PREFIX . "attribute_description SET attribute_id = '" . (int)$attribute_id . "', language_id = '2', name = 'Model'");

	echo "Created attribute 'Модель' (ID: $attribute_id)\n";

	return $attribute_id;
}

/**
 * Process a single product
 */
function processProduct($db, $product_id, $attribute_id, $parser, $dry_run = false) {
	// Get product details
	$product_query = $db->query("
		SELECT p.product_id, pd.name, p.model, m.name as manufacturer
		FROM " . DB_PREFIX . "product p
		LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
		LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)
		WHERE p.product_id = '" . (int)$product_id . "'
		AND pd.language_id = '1'
		LIMIT 1
	");

	if ($product_query->num_rows == 0) {
		echo "Product ID $product_id not found\n";
		return false;
	}

	$product = $product_query->row;

	// Parse model name
	$model_name = $parser->parseModelName($product['name'], $product['manufacturer'], $product['model']);

	echo "Product ID: {$product['product_id']}\n";
	echo "  Name: {$product['name']}\n";
	echo "  Manufacturer: {$product['manufacturer']}\n";
	echo "  Parsed Model: $model_name\n";

	if (!$dry_run) {
		// Delete existing model_name attribute for this product
		$db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$attribute_id . "'");

		// Add model_name attribute for both languages
		// Language 1 (English)
		$db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET
			product_id = '" . (int)$product_id . "',
			attribute_id = '" . (int)$attribute_id . "',
			language_id = '1',
			text = '" . $db->escape($model_name) . "'
		");

		// Language 2 (Russian)
		$db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET
			product_id = '" . (int)$product_id . "',
			attribute_id = '" . (int)$attribute_id . "',
			language_id = '2',
			text = '" . $db->escape($model_name) . "'
		");

		echo "  ✓ Attribute saved\n\n";
	} else {
		echo "  (DRY RUN - not saved)\n\n";
	}

	return true;
}

/**
 * Process all products in configured categories
 */
function processAllProducts($db, $config, $attribute_id, $parser, $dry_run = false) {
	// Get configured categories from Yandex Market settings
	$allowed_categories = $config->get('feed_yandex_market_categories');

	if (empty($allowed_categories)) {
		echo "No categories configured in Yandex Market feed settings.\n";
		echo "Please configure categories in Admin > Extensions > Feeds > Yandex Market\n";
		return;
	}

	echo "Processing products in categories: $allowed_categories\n\n";

	// Get products from allowed categories
	$products_query = $db->query("
		SELECT DISTINCT p.product_id, pd.name, p.model, m.name as manufacturer
		FROM " . DB_PREFIX . "product p
		LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
		LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)
		LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)
		WHERE pd.language_id = '1'
		AND p2c.category_id IN (" . $allowed_categories . ")
		AND p.status = '1'
		ORDER BY p.product_id ASC
	");

	echo "Found {$products_query->num_rows} products to process\n\n";

	$processed = 0;
	foreach ($products_query->rows as $product) {
		processProduct($db, $product['product_id'], $attribute_id, $parser, $dry_run);
		$processed++;
	}

	echo "\nProcessed $processed products\n";
}

// Main execution
echo "=== Model Name Attribute Tool ===\n\n";

// Ensure attribute exists
$attribute_id = ensureAttributeExists($db, $config);
echo "\n";

// Create parser
$parser = new ModelNameParser();

// Process based on arguments
if (isset($options['product_id'])) {
	$product_id = (int)$options['product_id'];
	echo "Processing single product ID: $product_id\n\n";
	processProduct($db, $product_id, $attribute_id, $parser, $dry_run);
} elseif (isset($options['all'])) {
	echo "Processing all products in configured categories\n\n";
	processAllProducts($db, $config, $attribute_id, $parser, $dry_run);
} else {
	echo "Error: Please specify --product_id=123 or --all\n";
	echo "Use --help for usage information\n";
	exit(1);
}

echo "\nDone!\n";
