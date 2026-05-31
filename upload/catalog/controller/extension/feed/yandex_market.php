<?php
/**
 * Класс YML экспорта
 * YML (Yandex Market Language) - стандарт, разработанный "Яндексом"
 * для принятия и публикации информации в базе данных Яндекс.Маркет
 * YML основан на стандарте XML (Extensible Markup Language)
 * описание формата YML http://partner.market.yandex.ru/legal/tt/
 */
class ControllerExtensionFeedYandexMarket extends Controller {
	private $shop = array();
	private $currencies = array();
	private $categories = array();
	private $offers = array();
	private $from_charset = 'utf-8';
	private $eol = "\n";
	private $cache_file = '';
	private $cache_hash_file = '';

	// Name parsing dictionaries
	private $vendors = array('Yonex', 'Li-NING', 'RSL', 'Chao Pai', 'Uniqsport');
	private $genders = array('мужские', 'женские', 'унисекс', 'детские', 'мужская', 'женская');
	private $sports = array('бадминтон', 'теннис', 'сквош', 'футбол', 'баскетбол', 'волейбол');
	private $colors = array(
		'белый' => 'белый', 'белая' => 'белый', 'белые' => 'белый', 'белы' => 'белый', 'бел' => 'белый', 'бело' => 'белый',
		'черный' => 'черный', 'черная' => 'черный', 'черные' => 'черный', 'черн' => 'черный', 'черно' => 'черный',
		'красный' => 'красный', 'красная' => 'красный', 'красные' => 'красный', 'красн' => 'красный', 'красно' => 'красный',
		'синий' => 'синий', 'синяя' => 'синий', 'синие' => 'синий', 'син' => 'синий', 'сине' => 'синий',
		'зеленый' => 'зеленый', 'зеленая' => 'зеленый', 'зеленые' => 'зеленый', 'зелен' => 'зеленый', 'зелено' => 'зеленый',
		'желтый' => 'желтый', 'желтая' => 'желтый', 'желтые' => 'желтый', 'желт' => 'желтый', 'желто' => 'желтый',
		'оранжевый' => 'оранжевый', 'оранжевая' => 'оранжевый', 'оранжевые' => 'оранжевый', 'оранж' => 'оранжевый', 'оранжево' => 'оранжевый',
		'розовый' => 'розовый', 'розовая' => 'розовый', 'розовые' => 'розовый', 'розов' => 'розовый', 'розово' => 'розовый',
		'фиолетовый' => 'фиолетовый', 'фиолетовая' => 'фиолетовый', 'фиолетовые' => 'фиолетовый', 'фиолетово' => 'фиолетовый',
		'серый' => 'серый', 'серая' => 'серый', 'серые' => 'серый', 'сер' => 'серый', 'серо' => 'серый',
		'коричневый' => 'коричневый', 'коричневая' => 'коричневый', 'коричневые' => 'коричневый', 'коричнево' => 'коричневый',
		'голубой' => 'голубой', 'голубая' => 'голубой', 'голубые' => 'голубой', 'голуб' => 'голубой',
		'бежевый' => 'бежевый', 'бежевая' => 'бежевый', 'бежевые' => 'бежевый', 'беж' => 'бежевый',
		'золотой' => 'золотой', 'золотая' => 'золотой', 'золотые' => 'золотой', 'золото' => 'золотой',
		'серебряный' => 'серебряный', 'серебряная' => 'серебряный', 'серебряные' => 'серебряный', 'серебро' => 'серебряный',
		'салатовый' => 'салатовый', 'салатовая' => 'салатовый', 'салатовые' => 'салатовый', 'салат' => 'салатовый', 'салатово' => 'салатовый',
		// Compound colors
		// 'бело-голубые' => 'бело-голубой', 'бело-голубая' => 'бело-голубой', 'бело-голубой' => 'бело-голубой', 'бел/голуб' => 'бело-голубой',
		// 'бело-синие' => 'бело-синий', 'бело-синяя' => 'бело-синий', 'бело-синий' => 'бело-синий', 'бел/син' => 'бело-синий',
		// 'бело-красные' => 'бело-красный', 'бело-красная' => 'бело-красный', 'бело-красный' => 'бело-красный', 'бел/красн' => 'бело-красный',
		// 'черно-белые' => 'черно-белый', 'черно-белая' => 'черно-белый', 'черно-белый' => 'черно-белый', 'черн/бел' => 'черно-белый',
		// 'красно-черные' => 'красно-черный', 'красно-черная' => 'красно-черный', 'красно-черный' => 'красно-черный', 'красн/черн' => 'красно-черный',
		// 'сине-белые' => 'сине-белый', 'сине-белая' => 'сине-белый', 'сине-белый' => 'сине-белый', 'син/бел' => 'сине-белый',
		// 'сине-красные' => 'сине-красный', 'сине-красная' => 'сине-красный', 'сине-красный' => 'сине-красный', 'син/красн' => 'сине-красный',
		// 'салатово-черные' => 'салатово-черный', 'салатово-черная' => 'салатово-черный', 'салат/черн' => 'салатово-черный',
		// English colors for model names
		'red' => 'красный', 'blue' => 'синий', 'green' => 'зеленый',
		'yellow' => 'желтый', 'orange' => 'оранжевый', 'purple' => 'фиолетовый',
		'pink' => 'розовый', 'black' => 'черный', 'white' => 'белый',
		'grey' => 'серый', 'gray' => 'серый', 'silver' => 'серебряный', 'gold' => 'золотой'
	);
	// Categories to exclude from color detection
	private $no_color_categories = array('Волан', 'Воланы', 'Ракетка', 'Ракетки', 'Сетка', 'Струны', 'Струна');
	private $type_prefixes = array(
		'ракетка' => 'Ракетка',
		'ракетки' => 'Ракетка',
		'воланы' => 'Волан',
		'волан' => 'Волан',
		'сетка' => 'Сетка',
		'стойки' => 'Стойки',
		'обувь' => 'Кроссовки',
		'кроссовки' => 'Кроссовки',
		'пуховик' => 'Пуховик',
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
		'гольфы' => 'Носки',
		'корт' => 'Оборудование',
		'станок' => 'Оборудование',
		'костюм' => 'Спортивный костюм',
		'спортивный костюм' => 'Спортивный костюм',
		'костюм спортивный' => 'Спортивный костюм',
		'напульсник' => 'Напульсник',	 
		'повязка' => 'Повязка', 
		'штаны' => 'Штаны', 
		'подарочный Сертификат' => 'Подарочный Сертификат',
		'тапочки' => 'Тапочки', 
		'прибор' => 'Оборудование', 
		'бейсболка' => 'Бейсболка', 
		'кеды' => 'Кроссовки', 
		'трафарет' => 'Оборудование', 
		'перетяжка ракетки' => 'Услуги', 
		'экспресс перетяжка' => 'Услуги', 
		'индивидуальный подбор' => 'Услуги',
		'смягчающая намотка' => 'Обмотка',
		'майка' => 'Футболка', 
		'спортивные штаны' => 'Брюки'
	);

	public function index() {
		if ($this->config->get('feed_yandex_market_status')) {

			if (!($allowed_categories = $this->config->get('feed_yandex_market_categories'))) exit();

			// Initialize cache file paths
			$this->cache_file = DIR_CACHE . 'yandex_market_feed.xml';
			$this->cache_hash_file = DIR_CACHE . 'yandex_market_feed.hash';

			// Check if cached feed is valid
			if ($this->isCacheValid()) {
				$this->response->addHeader('Content-Type: application/xml');
				$this->response->setOutput(file_get_contents($this->cache_file));
				return;
			}

			$this->load->model('extension/feed/yandex_market');
			$this->load->model('localisation/currency');
			$this->load->model('tool/image');

			// Магазин
			$this->setShop('name', $this->config->get('feed_yandex_market_shopname'));
			$this->setShop('company', $this->config->get('feed_yandex_market_company'));
			$this->setShop('url', HTTPS_SERVER . 'index.php?route=extension/feed/yandex_market');
			$this->setShop('platform', 'ocStore');
			$this->setShop('version', VERSION);

			// Валюты
			// TODO: Добавить возможность настраивать проценты в админке.
			$offers_currency = $this->config->get('feed_yandex_market_currency');
			if (!$this->currency->has($offers_currency)) exit();

			$decimal_place = $this->currency->getDecimalPlace($offers_currency);

			$shop_currency = $this->config->get('config_currency');

			$this->setCurrency($offers_currency, 1);

			$currencies = $this->model_localisation_currency->getCurrencies();

			$supported_currencies = array('RUR', 'RUB', 'USD', 'BYR', 'KZT', 'EUR', 'UAH');

			$currencies = array_intersect_key($currencies, array_flip($supported_currencies));

			foreach ($currencies as $currency) {
				if ($currency['code'] != $offers_currency && $currency['status'] == 1) {
					$this->setCurrency($currency['code'], number_format(1/$this->currency->convert($currency['value'], $offers_currency, $shop_currency), 4, '.', ''));
				}
			}

			// Категории
			$categories = $this->model_extension_feed_yandex_market->getCategory();

			foreach ($categories as $category) {
				$this->setCategory($category['name'], $category['category_id'], $category['parent_id']);
			}

			// Товарные предложения
			$in_stock_id = $this->config->get('feed_yandex_market_in_stock'); // id статуса товара "В наличии"
			$out_of_stock_id = $this->config->get('feed_yandex_market_out_of_stock'); // id статуса товара "Нет на складе"
			$vendor_required = false; // true - только товары у которых задан производитель, необходимо для 'vendor.model'
			$products = $this->model_extension_feed_yandex_market->getProduct($allowed_categories, $out_of_stock_id, $vendor_required);

			foreach ($products as $product) {
				$data = array();

				// Get category name for context
				$category_name = isset($this->categories[$product['category_id']]) ? $this->categories[$product['category_id']]['name'] : '';

				// Get full image path
				$image_path = '';
				if (!empty($product['image'])) {
					$image_path = DIR_IMAGE . $product['image'];
				}

				// Parse product name intelligently with color extraction
				$parsed = $this->parseProductName($product['name'], $product['manufacturer'], $category_name, $image_path);

				// Атрибуты товарного предложения
				$data['id'] = $product['product_id'];
				$data['type'] = 'vendor.model';
				$data['available'] = ($product['quantity'] > 0 || $product['stock_status_id'] == $in_stock_id);
//				$data['bid'] = 10;
//				$data['cbid'] = 15;

				// Параметры товарного предложения
				$data['url'] = $this->url->link('product/product', 'path=' . $this->getPath($product['category_id']) . '&product_id=' . $product['product_id']);
				$data['price'] = number_format($this->currency->convert($this->tax->calculate($product['price'], $product['tax_class_id']), $shop_currency, $offers_currency), $decimal_place, '.', '');
				$data['currencyId'] = $offers_currency;
				$data['categoryId'] = $product['category_id'];
				$data['delivery'] = 'true';
//				$data['local_delivery_cost'] = 100;
				$data['name'] = $product['name'];

				// Use parsed vendor and model
				$data['vendor'] = $parsed['vendor'];
				$data['vendorCode'] = $product['model'];
				$data['model'] = $parsed['model'];

				// Add typePrefix if detected
				if (!empty($parsed['typePrefix'])) {
					$data['typePrefix'] = $parsed['typePrefix'];
				}

				// Load product attributes (color, material, etc.)
				$attrs = $this->model_extension_feed_yandex_market->getProductAttributes($product['product_id']);

				// Color: prefer Цвет attribute (strip hex), fall back to name-parsed color
				$color = '';
				if (!empty($attrs['Цвет'])) {
					$color = $this->stripColorHex($attrs['Цвет']);
				} elseif (!empty($parsed['color'])) {
					$color = $parsed['color'];
				}
				if (!empty($color)) {
					$data['param'][] = array('name' => 'Цвет', 'value' => $color);
				}

				// Detect shoes vs apparel for material and size params
				$product_type = $this->detectProductType($product['name'], $attrs);

				if ($product_type === 'shoes') {
					$mat_upper = !empty($attrs['Материал кроссовок']) ? $attrs['Материал кроссовок'] : 'SYNTHETIC LEATHER+TEXTILE';
					$mat_sole  = !empty($attrs['Материал подошвы'])  ? $attrs['Материал подошвы']  : 'RUBBER';
					$data['param'][] = array('name' => 'Материал верха',    'value' => $mat_upper);
					$data['param'][] = array('name' => 'Материал подошвы',  'value' => $mat_sole);
				} elseif ($product_type === 'apparel') {
					$mat = !empty($attrs['Материал']) ? $attrs['Материал'] : 'SHELL:POLYESTER100%';
					$data['param'][] = array('name' => 'Материал', 'value' => $mat);
				}

				// getProductSizeVariants self-selects by option name — no product_type gate needed.
				$size_variants = $this->model_extension_feed_yandex_market->getProductSizeVariants($product['product_id']);

				$data['description'] = $product['description'];
//				$data['manufacturer_warranty'] = 'true';
//				$data['barcode'] = $product['sku'];
				if ($product['image']) {
					$picture = $this->model_tool_image->resize($product['image'], 100, 100);
					if ($picture) {
						$data['picture'] = $picture;
					}
				}

				// For shoes/apparel with size options: one offer per size variant.
				// offer id = "{product_id}:{option_value_id}"; group_id = product_id groups all variants.
				// For all other products: single offer with id = product_id.
				if (!empty($size_variants)) {
					foreach ($size_variants as $variant) {
						$offer              = $data;
						$offer['id']        = $product['product_id'] . ':' . $variant['option_value_id'];
						$offer['group_id']  = $product['product_id'];
						$offer['param'][]   = array('name' => 'Размер', 'value' => $variant['size_display']);
						$this->setOffer($offer);
					}
				} else {
					$this->setOffer($data);
				}
			}

			$this->categories = array_filter($this->categories, array($this, "filterCategory"));

			// Generate YML
			$yml_content = $this->getYml();

			// Save to cache
			$this->saveToCache($yml_content);

			$this->response->addHeader('Content-Type: application/xml');
			$this->response->setOutput($yml_content);
		}
	}

	/**
	 * Check if cached feed is still valid
	 *
	 * @return bool
	 */
	private function isCacheValid() {
		if (!file_exists($this->cache_file) || !file_exists($this->cache_hash_file)) {
			return false;
		}

		// Get current hash of products
		$current_hash = $this->getProductsHash();
		$cached_hash = file_get_contents($this->cache_hash_file);

		return $current_hash === $cached_hash;
	}

	/**
	 * Calculate hash of all products that will be in feed
	 * This hash changes when product names, prices, or other significant parameters change
	 *
	 * @return string
	 */
	private function getProductsHash() {
		$allowed_categories = $this->config->get('feed_yandex_market_categories');
		if (!$allowed_categories) return '';

		$this->load->model('extension/feed/yandex_market');

		$in_stock_id = $this->config->get('feed_yandex_market_in_stock');
		$out_of_stock_id = $this->config->get('feed_yandex_market_out_of_stock');

		$products = $this->model_extension_feed_yandex_market->getProduct($allowed_categories, $out_of_stock_id, false);

		// Create hash from product data that affects the feed
		$hash_data = array();
		foreach ($products as $product) {
			$hash_data[] = $product['product_id'] . '|' .
			               $product['name'] . '|' .
			               $product['price'] . '|' .
			               $product['manufacturer'] . '|' .
			               $product['model'] . '|' .
			               $product['quantity'] . '|' .
			               $product['image'] . '|' .
			               $product['date_modified'];
		}

		return md5(serialize($hash_data));
	}

	/**
	 * Save generated feed to cache
	 *
	 * @param string $content
	 */
	private function saveToCache($content) {
		file_put_contents($this->cache_file, $content);
		file_put_contents($this->cache_hash_file, $this->getProductsHash());
	}

	/**
	 * Parse product name according to naming convention:
	 * <typePrefix> <gender> <sport> <colour> <vendor> <model>
	 *
	 * @param string $name Product name
	 * @param string $manufacturer Manufacturer from database
	 * @param string $category_name Category name for context
	 * @param string $image_path Product image path for color detection
	 * @return array Parsed components
	 */
	private function parseProductName($name, $manufacturer, $category_name = '', $image_path = '') {
		$result = array(
			'typePrefix' => '',
			'vendor' => 'Uniqsport',
			'model' => '',
			'color' => '',
			'sport' => ''
		);

		$name_lower = mb_strtolower($name, 'UTF-8');
		$words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);

		// 1. Check for typePrefix at the beginning
		foreach ($this->type_prefixes as $prefix_key => $prefix_value) {
			if (mb_strpos($name_lower, $prefix_key) === 0) {
				$result['typePrefix'] = $prefix_value;
				break;
			}
		}

		// 2. Detect sport in the name
		foreach ($this->sports as $sport) {
			if (mb_stripos($name, $sport) !== false && mb_stripos($name, "футболк")== false) {
				$result['sport'] = $sport;
				break;
			}
		}

		// 3. Detect color in the name (only for applicable categories)
		// Check BEFORE adding sport to typePrefix
		$color_detection_allowed = true;
		if (!empty($result['typePrefix']) && in_array($result['typePrefix'], $this->no_color_categories)) {
			$color_detection_allowed = false;
		}
		if (!empty($category_name) && in_array($category_name, $this->no_color_categories)) {
			$color_detection_allowed = false;
		}

		// 4. Add sport to typePrefix if both exist
		// Do this AFTER color detection check
		if (!empty($result['typePrefix']) && !empty($result['sport'])) {
			$result['typePrefix'] = $result['typePrefix'] . ' для ' . $result['sport'] . 'а';
		}

		if ($color_detection_allowed) {
			// Look for color in product name
			foreach ($words as $word) {
				// Remove parentheses and other punctuation from word, keep - and /
				$word_clean = preg_replace('/[^\p{L}\p{N}\-\/]/u', '', $word);
				$word_lower = mb_strtolower($word_clean, 'UTF-8');

				// First, try exact match (handles simple colors and fully-defined compounds)
				if (isset($this->colors[$word_lower])) {
					$result['color'] = $this->colors[$word_lower];
					break;
				}

				// If not found, check if it's a compound color
				// Match pattern: color-color, color/color (e.g., "бело-голубые", "бел/син")
				if (preg_match('/^([а-яё]+)[\-\/]([а-яё]+)$/u', $word_lower, $matches)) {
					$first_color_part = $matches[1];
					// Check if first part is a known color or color abbreviation
					if (isset($this->colors[$first_color_part])) {
						$result['color'] = $this->colors[$first_color_part];
						break;
					}
				}
			}

			// If no color found in name and image is provided, extract from image
			if (empty($result['color']) && !empty($image_path) && file_exists($image_path)) {
				$result['color'] = $this->extractColorFromImage($image_path);
			}
		}

		// 5. Look for vendor in the name
		$vendor_found = false;
		$vendor_pos = false;
		foreach ($this->vendors as $vendor) {
			if (mb_stripos($name, $vendor) !== false) {
				$result['vendor'] = $vendor;
				$vendor_pos = mb_stripos($name, $vendor);
				$vendor_found = true;
				break;
			}
		}

		// If no vendor found in name but manufacturer is set, use it
		if (!$vendor_found && !empty($manufacturer)) {
			$result['vendor'] = $manufacturer;
			$vendor_found = true;
		}

		// 6. Extract model name
		// Model is typically BEFORE the vendor name (e.g., "Falcon 4.0 Li-NING")
		// or after vendor if no match found before
		if ($vendor_found && $result['vendor'] !== 'Uniqsport') {
			// First, try to extract model BEFORE vendor position
			if ($vendor_pos !== false) {
				$before_vendor = trim(mb_substr($name, 0, $vendor_pos));

				// Extract capitalized model name before vendor (like Falcon, Astrox, AXForce)
				if (preg_match_all('/\b([A-Z][A-Za-z0-9]+(?:\s+[0-9]+(?:\.[0-9]+)?)?(?:\s+[A-Z][A-Za-z0-9-]*)*)\b/u', $before_vendor, $matches)) {
					// Get all capitalized sequences
					$potential_models = array();
					foreach ($matches[1] as $match) {
						// Skip type prefixes, genders, sports, colors
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
						$result['model'] = end($potential_models);
					}
				}
			}

			// If no model found before vendor, try after vendor (original logic)
			if (empty($result['model']) && $vendor_pos !== false) {
				$after_vendor = trim(mb_substr($name, $vendor_pos + mb_strlen($result['vendor'])));

				// Extract capitalized model name (like Astrox, Falcon, AXForce)
				if (preg_match('/([A-Z][A-Za-z0-9-]+(?:\s+[A-Z0-9][A-Za-z0-9-]*)*)/u', $after_vendor, $matches)) {
					$result['model'] = trim($matches[1]);
				} elseif (!empty($after_vendor)) {
					// If no capitalized pattern found, use what's after vendor
					$result['model'] = preg_replace('/\s+/u', ' ', $after_vendor);
				}
			}
		}

		// If model is still empty, try to find capitalized words in the name
		if (empty($result['model'])) {
			// Look for capitalized sequences that could be model names
			if (preg_match_all('/\b([A-Z][A-Za-z0-9]+(?:-[A-Za-z0-9]+)?)\b/u', $name, $matches)) {
				$potential_models = array();
				foreach ($matches[1] as $match) {
					// Skip vendor names and common words
					if (!in_array($match, $this->vendors) && strlen($match) > 2) {
						$potential_models[] = $match;
					}
				}
				if (!empty($potential_models)) {
					$result['model'] = implode(' ', $potential_models);
				}
			}
		}

		// Fallback: if still no model, use last significant words from name
		if (empty($result['model'])) {
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
				$result['model'] = implode(' ', array_slice($significant_words, -3));
			}
		}

		// If still no model, use the whole name as model
		if (empty($result['model'])) {
			$result['model'] = $name;
		}

		return $result;
	}

	/**
	 * Strips hex color code from attribute values like "Черный (#000000)" → "Черный".
	 */
	private function stripColorHex($color) {
		return trim(preg_replace('/\s*\(#[0-9A-Fa-f]+\)/u', '', $color));
	}

	/**
	 * Detects whether a product is footwear or apparel based on attributes then name keywords.
	 * Returns 'shoes', 'apparel', or null.
	 * Use before building material/size params to pick correct fallbacks and param names.
	 */
	private function detectProductType($product_name, $attributes) {
		if (isset($attributes['Материал кроссовок']) || isset($attributes['Материал подошвы']) || isset($attributes['Серия кроссовок'])) {
			return 'shoes';
		}
		$lower = mb_strtolower($product_name, 'UTF-8');
		foreach (array('кроссовк', 'тапочк', 'кеды', 'обувь') as $kw) {
			if (mb_strpos($lower, $kw) !== false) return 'shoes';
		}
		foreach (array('футболк', 'шорты', 'куртк', 'ветровк', 'брюки', 'носк', 'костюм', 'толстовк', 'поло', 'платье', 'юбк', 'майк', 'пуховик') as $kw) {
			if (mb_strpos($lower, $kw) !== false) return 'apparel';
		}
		return null;
	}

	/**
	 * Extract dominant color from product image
	 * Excludes white-ish background colors
	 *
	 * @param string $image_path Full path to product image
	 * @return string Color name in Russian
	 */
	private function extractColorFromImage($image_path) {
		// Check if GD library is available
		if (!extension_loaded('gd')) {
			return '';
		}

		// Get image info
		$image_info = @getimagesize($image_path);
		if (!$image_info) {
			return '';
		}

		// Create image resource based on type
		$image = false;
		switch ($image_info[2]) {
			case IMAGETYPE_JPEG:
				$image = @imagecreatefromjpeg($image_path);
				break;
			case IMAGETYPE_PNG:
				$image = @imagecreatefrompng($image_path);
				break;
			case IMAGETYPE_GIF:
				$image = @imagecreatefromgif($image_path);
				break;
		}

		if (!$image) {
			return '';
		}

		$width = imagesx($image);
		$height = imagesy($image);

		// Sample colors from the image (skip edges to avoid background)
		$colors = array();
		$sample_size = 20; // Sample every 20 pixels
		$edge_margin = (int)($width * 0.15); // Skip 15% from edges

		for ($x = $edge_margin; $x < $width - $edge_margin; $x += $sample_size) {
			for ($y = $edge_margin; $y < $height - $edge_margin; $y += $sample_size) {
				$rgb = imagecolorat($image, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;

				// Skip white-ish colors (background)
				if ($r > 220 && $g > 220 && $b > 220) {
					continue;
				}

				// Skip very dark colors (shadows, borders)
				if ($r < 30 && $g < 30 && $b < 30) {
					continue;
				}

				// Convert RGB to HSL to get dominant hue
				$hsl = $this->rgbToHsl($r, $g, $b);
				$hue = $hsl[0];
				$saturation = $hsl[1];
				$lightness = $hsl[2];

				// Only consider colors with sufficient saturation (not gray)
				if ($saturation > 0.15) {
					$color_name = $this->hueToColorName($hue, $saturation, $lightness);
					if (!isset($colors[$color_name])) {
						$colors[$color_name] = 0;
					}
					$colors[$color_name]++;
				}
			}
		}

		imagedestroy($image);

		// Return the most dominant color
		if (!empty($colors)) {
			arsort($colors);
			return array_key_first($colors);
		}

		return '';
	}

	/**
	 * Convert RGB to HSL
	 *
	 * @param int $r Red (0-255)
	 * @param int $g Green (0-255)
	 * @param int $b Blue (0-255)
	 * @return array [h, s, l] where h is 0-360, s and l are 0-1
	 */
	private function rgbToHsl($r, $g, $b) {
		$r /= 255;
		$g /= 255;
		$b /= 255;

		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$l = ($max + $min) / 2;

		if ($max == $min) {
			$h = $s = 0; // achromatic
		} else {
			$d = $max - $min;
			$s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

			switch ($max) {
				case $r:
					$h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
					break;
				case $g:
					$h = (($b - $r) / $d + 2) / 6;
					break;
				case $b:
					$h = (($r - $g) / $d + 4) / 6;
					break;
			}
		}

		return array($h * 360, $s, $l);
	}

	/**
	 * Convert hue to color name in Russian
	 *
	 * @param float $hue Hue value (0-360)
	 * @param float $saturation Saturation (0-1)
	 * @param float $lightness Lightness (0-1)
	 * @return string Color name in Russian
	 */
	private function hueToColorName($hue, $saturation, $lightness) {
		// Very low saturation = gray
		if ($saturation < 0.2) {
			if ($lightness > 0.7) return 'серый';
			if ($lightness < 0.3) return 'черный';
			return 'серый';
		}

		// Determine color based on hue
		if ($hue >= 0 && $hue < 15) return 'красный';
		if ($hue >= 15 && $hue < 45) return 'оранжевый';
		if ($hue >= 45 && $hue < 75) return 'желтый';
		if ($hue >= 75 && $hue < 155) return 'зеленый';
		if ($hue >= 155 && $hue < 200) return 'голубой';
		if ($hue >= 200 && $hue < 260) return 'синий';
		if ($hue >= 260 && $hue < 300) return 'фиолетовый';
		if ($hue >= 300 && $hue < 330) return 'розовый';
		if ($hue >= 330 && $hue <= 360) return 'красный';

		return 'серый';
	}

	/**
	 * Методы формирования YML
	 */

	/**
	 * Формирование массива для элемента shop описывающего магазин
	 *
	 * @param string $name - Название элемента
	 * @param string $value - Значение элемента
	 */
	private function setShop($name, $value) {
		$allowed = array('name', 'company', 'url', 'phone', 'platform', 'version', 'agency', 'email');
		if (in_array($name, $allowed)) {
			$this->shop[$name] = $this->prepareField($value);
		}
	}

	/**
	 * Валюты
	 *
	 * @param string $id - код валюты (RUR, RUB, USD, BYR, KZT, EUR, UAH)
	 * @param float|string $rate - курс этой валюты к валюте, взятой за единицу.
	 *	Параметр rate может иметь так же следующие значения:
	 *		CBRF - курс по Центральному банку РФ.
	 *		NBU - курс по Национальному банку Украины.
	 *		NBK - курс по Национальному банку Казахстана.
	 *		СВ - курс по банку той страны, к которой относится интернет-магазин
	 * 		по Своему региону, указанному в Партнерском интерфейсе Яндекс.Маркета.
	 * @param float $plus - используется только в случае rate = CBRF, NBU, NBK или СВ
	 *		и означает на сколько увеличить курс в процентах от курса выбранного банка
	 * @return bool
	 */
	private function setCurrency($id, $rate = 'CBRF', $plus = 0) {
		$allow_id = array('RUR', 'RUB', 'USD', 'BYR', 'KZT', 'EUR', 'UAH');
		if (!in_array($id, $allow_id)) {
			return false;
		}
		$allow_rate = array('CBRF', 'NBU', 'NBK', 'CB');
		if (in_array($rate, $allow_rate)) {
			$plus = str_replace(',', '.', $plus);
			if (is_numeric($plus) && $plus > 0) {
				$this->currencies[] = array(
					'id'=>$this->prepareField(strtoupper($id)),
					'rate'=>$rate,
					'plus'=>(float)$plus
				);
			} else {
				$this->currencies[] = array(
					'id'=>$this->prepareField(strtoupper($id)),
					'rate'=>$rate
				);
			}
		} else {
			$rate = str_replace(',', '.', $rate);
			if (!(is_numeric($rate) && $rate > 0)) {
				return false;
			}
			$this->currencies[] = array(
				'id'=>$this->prepareField(strtoupper($id)),
				'rate'=>(float)$rate
			);
		}

		return true;
	}

	/**
	 * Категории товаров
	 *
	 * @param string $name - название рубрики
	 * @param int $id - id рубрики
	 * @param int $parent_id - id родительской рубрики
	 * @return bool
	 */
	private function setCategory($name, $id, $parent_id = 0) {
		$id = (int)$id;
		if ($id < 1 || trim($name) == '') {
			return false;
		}
		if ((int)$parent_id > 0) {
			$this->categories[$id] = array(
				'id'=>$id,
				'parentId'=>(int)$parent_id,
				'name'=>$this->prepareField($name)
			);
		} else {
			$this->categories[$id] = array(
				'id'=>$id,
				'name'=>$this->prepareField($name)
			);
		}

		return true;
	}

	/**
	 * Товарные предложения
	 *
	 * @param array $data - массив параметров товарного предложения
	 */
	private function setOffer($data) {
		$offer = array();

		$attributes = array('id', 'type', 'available', 'bid', 'cbid', 'group_id', 'param');
		$attributes = array_intersect_key($data, array_flip($attributes));

		foreach ($attributes as $key => $value) {
			switch ($key)
			{
				case 'id':
					// Preserve string IDs like "42:15" for YCP variant offers
					if (preg_match('/^\d+/', (string)$value)) {
						$offer[$key] = $value;
					}
					break;

				case 'group_id':
					$value = (int)$value;
					if ($value > 0) {
						$offer[$key] = $value;
					}
					break;

				case 'bid':
				case 'cbid':
					$value = (int)$value;
					if ($value > 0) {
						$offer[$key] = $value;
					}
					break;

				case 'type':
					if (in_array($value, array('vendor.model', 'book', 'audiobook', 'artist.title', 'tour', 'ticket', 'event-ticket'))) {
						$offer['type'] = $value;
					}
					break;

				case 'available':
					$offer['available'] = ($value ? 'true' : 'false');
					break;

				case 'param':
					if (is_array($value)) {
						$offer['param'] = $value;
					}
					break;

				default:
					break;
			}
		}

		$type = isset($offer['type']) ? $offer['type'] : '';

		$allowed_tags = array('url'=>0, 'buyurl'=>0, 'price'=>1, 'wprice'=>0, 'currencyId'=>1, 'xCategory'=>0, 'categoryId'=>1, 'picture'=>0, 'store'=>0, 'pickup'=>0, 'delivery'=>0, 'deliveryIncluded'=>0, 'local_delivery_cost'=>0, 'orderingTime'=>0);

		switch ($type) {
			case 'vendor.model':
				$allowed_tags = array_merge($allowed_tags, array('typePrefix'=>1, 'vendor'=>1, 'vendorCode'=>0, 'model'=>1, 'name'=>0, 'provider'=>0, 'tarifplan'=>0));
				break;

			case 'book':
				$allowed_tags = array_merge($allowed_tags, array('author'=>0, 'name'=>1, 'publisher'=>0, 'series'=>0, 'year'=>0, 'ISBN'=>0, 'volume'=>0, 'part'=>0, 'language'=>0, 'binding'=>0, 'page_extent'=>0, 'table_of_contents'=>0));
				break;

			case 'audiobook':
				$allowed_tags = array_merge($allowed_tags, array('author'=>0, 'name'=>1, 'publisher'=>0, 'series'=>0, 'year'=>0, 'ISBN'=>0, 'volume'=>0, 'part'=>0, 'language'=>0, 'table_of_contents'=>0, 'performed_by'=>0, 'performance_type'=>0, 'storage'=>0, 'format'=>0, 'recording_length'=>0));
				break;

			case 'artist.title':
				$allowed_tags = array_merge($allowed_tags, array('artist'=>0, 'title'=>1, 'year'=>0, 'media'=>0, 'starring'=>0, 'director'=>0, 'originalName'=>0, 'country'=>0));
				break;

			case 'tour':
				$allowed_tags = array_merge($allowed_tags, array('worldRegion'=>0, 'country'=>0, 'region'=>0, 'days'=>1, 'dataTour'=>0, 'name'=>1, 'hotel_stars'=>0, 'room'=>0, 'meal'=>0, 'included'=>1, 'transport'=>1, 'price_min'=>0, 'price_max'=>0, 'options'=>0));
				break;

			case 'event-ticket':
				$allowed_tags = array_merge($allowed_tags, array('name'=>1, 'place'=>1, 'hall'=>0, 'hall_part'=>0, 'date'=>1, 'is_premiere'=>0, 'is_kids'=>0));
				break;

			default:
				$allowed_tags = array_merge($allowed_tags, array('name'=>1, 'vendor'=>0, 'vendorCode'=>0));
				break;
		}

		$allowed_tags = array_merge($allowed_tags, array('aliases'=>0, 'additional'=>0, 'description'=>0, 'sales_notes'=>0, 'promo'=>0, 'manufacturer_warranty'=>0, 'country_of_origin'=>0, 'downloadable'=>0, 'adult'=>0, 'barcode'=>0));

		$required_tags = array_filter($allowed_tags);

		if (sizeof(array_intersect_key($data, $required_tags)) != sizeof($required_tags)) {
			return;
		}

		$data = array_intersect_key($data, $allowed_tags);
//		if (isset($data['tarifplan']) && !isset($data['provider'])) {
//			unset($data['tarifplan']);
//		}

		$allowed_tags = array_intersect_key($allowed_tags, $data);

		// Стандарт XML учитывает порядок следования элементов,
		// поэтому важно соблюдать его в соответствии с порядком описанным в DTD
		$offer['data'] = array();
		foreach ($allowed_tags as $key => $value) {
			$offer['data'][$key] = $this->prepareField($data[$key]);
		}

		$this->offers[] = $offer;
	}

	/**
	 * Формирование YML файла
	 *
	 * @return string
	 */
	private function getYml() {
		$yml  = '<?xml version="1.0" encoding="windows-1251"?>' . $this->eol;
		$yml .= '<!DOCTYPE yml_catalog SYSTEM "shops.dtd">' . $this->eol;
		$yml .= '<yml_catalog date="' . date('Y-m-d H:i') . '">' . $this->eol;
		$yml .= '<shop>' . $this->eol;

		// информация о магазине
		$yml .= $this->array2Tag($this->shop);

		// валюты
		$yml .= '<currencies>' . $this->eol;
		foreach ($this->currencies as $currency) {
			$yml .= $this->getElement($currency, 'currency');
		}
		$yml .= '</currencies>' . $this->eol;

		// категории
		$yml .= '<categories>' . $this->eol;
		foreach ($this->categories as $category) {
			$category_name = $category['name'];
			unset($category['name'], $category['export']);
			$yml .= $this->getElement($category, 'category', $category_name);
		}
		$yml .= '</categories>' . $this->eol;

		// товарные предложения
		$yml .= '<offers>' . $this->eol;
		foreach ($this->offers as $offer) {
			$tags = $this->array2Tag($offer['data']);
			unset($offer['data']);
			if (isset($offer['param'])) {
				$tags .= $this->array2Param($offer['param']);
				unset($offer['param']);
			}
			$yml .= $this->getElement($offer, 'offer', $tags);
		}
		$yml .= '</offers>' . $this->eol;

		$yml .= '</shop>';
		$yml .= '</yml_catalog>';

		return $yml;
	}

	/**
	 * Фрмирование элемента
	 *
	 * @param array $attributes
	 * @param string $element_name
	 * @param string $element_value
	 * @return string
	 */
	private function getElement($attributes, $element_name, $element_value = '') {
		$retval = '<' . $element_name . ' ';
		foreach ($attributes as $key => $value) {
			$retval .= $key . '="' . $value . '" ';
		}
		$retval .= $element_value ? '>' . $this->eol . $element_value . '</' . $element_name . '>' : '/>';
		$retval .= $this->eol;

		return $retval;
	}

	/**
	 * Преобразование массива в теги
	 *
	 * @param array $tags
	 * @return string
	 */
	private function array2Tag($tags) {
		$retval = '';
		foreach ($tags as $key => $value) {
			if ($value !== '' && $value !== null) {
				$retval .= '<' . $key . '>' . $value . '</' . $key . '>' . $this->eol;
			}
		}

		return $retval;
	}

	/**
	 * Преобразование массива в теги параметров
	 *
	 * @param array $params
	 * @return string
	 */
	private function array2Param($params) {
		$retval = '';
		foreach ($params as $param) {
			$retval .= '<param name="' . $this->prepareField($param['name']);
			if (isset($param['unit'])) {
				$retval .= '" unit="' . $this->prepareField($param['unit']);
			}
			$retval .= '">' . $this->prepareField($param['value']) . '</param>' . $this->eol;
		}

		return $retval;
	}

	/**
	 * Подготовка текстового поля в соответствии с требованиями Яндекса
	 * Запрещаем любые html-тэги, стандарт XML не допускает использования в текстовых данных
	 * непечатаемых символов с ASCII-кодами в диапазоне значений от 0 до 31 (за исключением
	 * символов с кодами 9, 10, 13 - табуляция, перевод строки, возврат каретки). Также этот
	 * стандарт требует обязательной замены некоторых символов на их символьные примитивы.
	 * @param string $text
	 * @return string
	 */
	private function prepareField($field) {
		$field = htmlspecialchars_decode($field);

		// Remove entire table blocks with content
		$field = preg_replace('/<table[^>]*>.*?<\/table>/is', ' ', $field);

		// Remove style blocks
		$field = preg_replace('/<style[^>]*>.*?<\/style>/is', ' ', $field);

		// Remove script blocks
		$field = preg_replace('/<script[^>]*>.*?<\/script>/is', ' ', $field);

		// Add spacing around block-level tags before stripping
		$block_tags = array('p', 'div', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'tr', 'td', 'th', 'dt', 'dd', 'blockquote');
		foreach ($block_tags as $tag) {
			$field = preg_replace('/<' . $tag . '[^>]*>/i', ' ', $field);
			$field = preg_replace('/<\/' . $tag . '>/i', ' ', $field);
		}

		// Strip remaining tags
		$field = strip_tags($field);

		// Clean up multiple spaces/newlines
		$field = preg_replace('/\s+/', ' ', $field);

		$from = array('"', '&', '>', '<', '\'');
		$to = array('&quot;', '&amp;', '&gt;', '&lt;', '&apos;');
		$field = str_replace($from, $to, $field);
		if ($this->from_charset != 'windows-1251') {
			$field = iconv($this->from_charset, 'windows-1251//TRANSLIT//IGNORE', $field);
		}
		$field = preg_replace('#[\x00-\x08\x0B-\x0C\x0E-\x1F]+#is', ' ', $field);

		return trim($field);
	}

	protected function getPath($category_id, $current_path = '') {
		if (isset($this->categories[$category_id])) {
			$this->categories[$category_id]['export'] = 1;

			if (!$current_path) {
				$new_path = $this->categories[$category_id]['id'];
			} else {
				$new_path = $this->categories[$category_id]['id'] . '_' . $current_path;
			}

			if (isset($this->categories[$category_id]['parentId'])) {
				return $this->getPath($this->categories[$category_id]['parentId'], $new_path);
			} else {
				return $new_path;
			}

		}
	}

	function filterCategory($category) {
		return isset($category['export']);
	}
}
