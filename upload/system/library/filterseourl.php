<?php
/**
 * Maps Journal3 filter values (fa{attribute_id}, fo{option_id}, ff{filter_id})
 * to pretty URL slugs and back, backed by ocus_filter_seo_url.
 *
 * Usable from a Controller (catalog/controller/startup/seo_url.php), a
 * catalog/model/*, or a standalone CLI bootstrap - anywhere with a Registry
 * exposing 'db' and 'config'.
 *
 * Slug generation happens two ways, sharing the exact same rules (config
 * overrides, collision handling): in bulk via cli/generate_filter_seo_urls.php,
 * and lazily, the first time a not-yet-mapped value is linked to - see
 * ensureSlugForFilterValue().
 */
class FilterSeoUrl {
	private $registry;
	private $db;
	private $config;

	private static $translit_map = array(
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
		'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
		'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
		'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
		'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
		'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
		'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
		'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
	);

	private static $config_cache;
	private static $collisions_cache = array();

	public function __construct($registry) {
		$this->registry = $registry;
		$this->db = $registry->get('db');
		$this->config = $registry->get('config');
	}

	public static function translit($text) {
		return strtr((string)$text, self::$translit_map);
	}

	public static function slugify($text) {
		$text = self::translit($text);
		$text = mb_strtolower($text, 'UTF-8');
		$text = preg_replace('/[^a-z0-9]+/u', '-', $text);
		return trim($text, '-');
	}

	public static function stripTrailingParenthetical($text) {
		return trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $text));
	}

	public static function stripTrailingBrackets($text) {
		return trim(preg_replace('/\s*\[[^\]]*\]\s*$/u', '', $text));
	}

	/**
	 * Forward lookup: given a parsed fa/fo/ff value, return its slug or null if unmapped.
	 */
	public function getSlug($type, $type_id, $value_id, $value_text) {
		$value_hash = $type === 'fa' ? md5(trim((string)$value_text)) : '';

		$query = $this->db->query("SELECT `slug` FROM `" . DB_PREFIX . "filter_seo_url`
			WHERE `store_id` = '" . (int)$this->config->get('config_store_id') . "'
			AND `language_id` = '" . (int)$this->config->get('config_language_id') . "'
			AND `type` = '" . $this->db->escape($type) . "'
			AND `type_id` = '" . (int)$type_id . "'
			AND `value_id` = '" . (int)$value_id . "'
			AND `value_hash` = '" . $this->db->escape($value_hash) . "'");

		return $query->num_rows ? $query->row['slug'] : null;
	}

	/**
	 * Reverse lookup: given a URL path segment, return the fa/fo/ff value it represents, or null.
	 */
	public function resolveSlug($slug, $store_id, $language_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "filter_seo_url`
			WHERE `store_id` = '" . (int)$store_id . "'
			AND `language_id` = '" . (int)$language_id . "'
			AND `slug` = '" . $this->db->escape($slug) . "'");

		if (!$query->num_rows) {
			return null;
		}

		return array(
			'type'       => $query->row['type'],
			'type_id'    => (int)$query->row['type_id'],
			'value_id'   => (int)$query->row['value_id'],
			'value_text' => $query->row['value_text'],
		);
	}

	/**
	 * The one entry point that knows how to go from a raw fa/fo/ff value to a
	 * slug, creating a row on the fly if none exists yet - used both by the
	 * CLI backfill (which still batches its own name lookups for efficiency
	 * across thousands of rows, then calls buildPrefix()/computeSlugValue()
	 * directly) and by catalog/controller/startup/seo_url.php's rewrite(),
	 * where it means a brand-new attribute/option value gets a pretty URL on
	 * the very first page view that links to it, not after the next cron
	 * backfill run. Returns null only if the facet itself (attribute_id /
	 * option_id / filter_group_id) can't be found at all.
	 */
	public function ensureSlugForFilterValue($type, $type_id, $value_id, $value_text) {
		$existing = $this->getSlug($type, $type_id, $value_id, $value_text);
		if ($existing !== null) {
			return $existing;
		}

		// Unlike the CLI backfill (which only ever iterates values already
		// known to be in use by a real product), this method can be reached
		// with arbitrary attacker/bot-supplied query strings, or values
		// mangled by an unrelated bug elsewhere (e.g. core's
		// common/language.php builds its "same page in another language"
		// link via urldecode(http_build_query(...)), which un-escapes a
		// literal "#" back into the args string - parse_url() then reads
		// everything after it as a URL fragment and silently truncates the
		// query, so "Синий (#0000FF)" arrives here as "Синий ("). Only ever
		// create a row for a value that's genuinely in use right now, or
		// every stray/malformed request would permanently pollute the table.
		if (!$this->isValueInUse($type, $type_id, $value_id, $value_text)) {
			return null;
		}

		$language_id = (int)$this->config->get('config_language_id');

		$naming = $this->getFacetNaming($type, $type_id, $language_id);
		if ($naming === null) {
			return null;
		}
		list($facet_name, $group_name) = $naming;

		switch ($type) {
			case 'fa':
				$label = (string)$value_text;
				break;

			case 'fo':
				$label = $this->getOptionValueName((int)$value_id, $language_id);
				if ($label === null) {
					return null;
				}
				break;

			case 'ff':
				$label = $this->getFilterName((int)$value_id, $language_id);
				if ($label === null) {
					return null;
				}
				break;

			default:
				return null;
		}

		$collisions = $this->detectNameCollisionsCached($language_id);
		$prefix = $this->buildPrefix($type, $type_id, $facet_name, $group_name, $collisions[$type] ?? array());
		$slug_value = $this->computeSlugValue($type, $type_id, $value_id, $value_text, $label);

		$result = $this->ensureSlug($type, $type_id, $value_id, $value_text, $prefix, $slug_value);

		return $result['slug'];
	}

	/**
	 * Facet-level (not value-level) name lookup - [facet_name, group_name] or
	 * null if the facet doesn't exist. Shared by ensureSlugForFilterValue()
	 * and getPrefixForFacet(), so the fa/fo/ff naming rules (including the
	 * synthetic "option-{id}" qualifier for options, which have no real
	 * group concept) live in exactly one place.
	 */
	private function getFacetNaming($type, $type_id, $language_id) {
		switch ($type) {
			case 'fa':
				return $this->getAttributeInfo((int)$type_id, $language_id);

			case 'fo':
				$facet_name = $this->getOptionName((int)$type_id, $language_id);
				return $facet_name === null ? null : array($facet_name, 'option-' . $type_id);

			case 'ff':
				$facet_name = $this->getFilterGroupName((int)$type_id, $language_id);
				// type_id here already IS the filter group, so there's
				// nothing meaningful left to qualify with on collision.
				return $facet_name === null ? null : array($facet_name, null);

			default:
				return null;
		}
	}

	/**
	 * The prefix a facet's slugs currently use (or would use, if none exist
	 * yet), independent of any specific value - needed to compose/decompose
	 * a multi-value URL segment like "razmer-odezhdy-euro-xxs+euro-xs+euro-s",
	 * where the prefix appears once instead of once per value. Returns null
	 * if the facet doesn't exist.
	 */
	public function getPrefixForFacet($type, $type_id, $language_id) {
		$naming = $this->getFacetNaming($type, $type_id, $language_id);
		if ($naming === null) {
			return null;
		}

		list($facet_name, $group_name) = $naming;
		$collisions = $this->detectNameCollisionsCached($language_id);

		return $this->buildPrefix($type, $type_id, $facet_name, $group_name, $collisions[$type] ?? array());
	}

	/**
	 * The manufacturer filter (Journal3's "fm") is unlike fa/fo/ff: it isn't
	 * parameterized by a type_id (there's only one manufacturer facet, not
	 * one per attribute/option/filter-group), so it doesn't go through
	 * ocus_filter_seo_url at all. Instead it reuses each manufacturer's own
	 * core SEO keyword (oc_seo_url, query='manufacturer_id=X') - this store
	 * already curates those ("yonex", "li-ning", ...), and generating a
	 * separate one would always collide with (and get shadowed by) the
	 * manufacturer's own dedicated page keyword anyway, since both would
	 * slugify the identical brand name.
	 */
	public function getManufacturerKeyword($manufacturer_id, $store_id, $language_id) {
		$query = $this->db->query("SELECT `keyword` FROM `" . DB_PREFIX . "seo_url`
			WHERE `query` = 'manufacturer_id=" . (int)$manufacturer_id . "'
			AND `store_id` = '" . (int)$store_id . "' AND `language_id` = '" . (int)$language_id . "'");

		return $query->num_rows ? $query->row['keyword'] : null;
	}

	/**
	 * Reverse of getManufacturerKeyword() - only matches a keyword whose
	 * query is specifically "manufacturer_id=...", so this can safely be
	 * tried as a fallback after a filter-slug lookup misses without risking
	 * a false match against some other keyword type.
	 */
	public function resolveManufacturerKeyword($keyword, $store_id, $language_id) {
		$query = $this->db->query("SELECT `query` FROM `" . DB_PREFIX . "seo_url`
			WHERE `keyword` = '" . $this->db->escape($keyword) . "' AND `query` LIKE 'manufacturer_id=%'
			AND `store_id` = '" . (int)$store_id . "' AND `language_id` = '" . (int)$language_id . "'");

		if (!$query->num_rows) {
			return null;
		}

		return (int)explode('=', $query->row['query'])[1];
	}

	/**
	 * The facet-name-to-prefix rule, shared by the CLI backfill and
	 * ensureSlugForFilterValue(). $collisions_for_type is the 'fa'/'fo'/'ff'
	 * sub-array from detectNameCollisions() (slug => [type_id, ...]).
	 */
	public function buildPrefix($type, $type_id, $facet_name, $group_name, $collisions_for_type) {
		$config = $this->loadConfig();
		$key = $type . $type_id;

		// Explicit opt-in escape hatch: the facet's own name is dropped
		// entirely, so "raketki-tsvet-belyy" becomes "raketki-belyy" (or,
		// with prefix_override also empty, just "belyy" - no prefix at all).
		// Off by default because dropping the name is what usually makes a
		// slug ambiguous (see the non-omit branch below) - this only applies
		// when an admin has explicitly decided that's fine for this facet.
		if (!empty($config['omit_facet_name'][$key])) {
			$override = $config['prefix_override'][$key] ?? '';
			return $override !== '' ? self::slugify($override) : '';
		}

		$base_slug = self::slugify($facet_name);

		// The override always stands in for the qualifier word (e.g.
		// "raketki" instead of the auto-derived "spetsifikatsiya-raketok"),
		// combined with the facet's own name - it's never a full replacement
		// of the facet name itself, or "raketki-tsvet-belyy" would silently
		// lose the "tsvet" (colour) part and become ambiguous.
		if (isset($config['prefix_override'][$key]) && $config['prefix_override'][$key] !== '') {
			return self::slugify($config['prefix_override'][$key]) . '-' . $base_slug;
		}

		$colliding = isset($collisions_for_type[$base_slug]) && count($collisions_for_type[$base_slug]) > 1;

		if ($colliding && $group_name) {
			return self::slugify($group_name) . '-' . $base_slug;
		}

		return $base_slug;
	}

	/**
	 * The value-label-to-slug-text rule, shared the same way. $value_text is
	 * only used as the 'fa' override lookup key (exact literal match text);
	 * $label is what actually gets slugified once overrides/stripping apply.
	 */
	public function computeSlugValue($type, $type_id, $value_id, $value_text, $label) {
		$config = $this->loadConfig();
		$key = $type . $type_id;

		$override_key = $type === 'fa' ? trim((string)$value_text) : (int)$value_id;

		if (isset($config['value_override'][$key]) && array_key_exists($override_key, $config['value_override'][$key])) {
			return $config['value_override'][$key][$override_key];
		}

		$text = $label;

		if (isset($config['strip_brackets'][$key])) {
			$text = self::stripTrailingBrackets($text);
		}

		if (isset($config['strip_parenthetical'][$key])) {
			$text = self::stripTrailingParenthetical($text);
		}

		return $text;
	}

	/**
	 * Loads facet/value overrides from ocus_filter_seo_facet_config and
	 * ocus_filter_seo_value_config once per request - admin-editable via
	 * admin/controller/catalog/filter_seo.php. Normalizes into the same
	 * shape buildPrefix()/computeSlugValue() have always expected, so
	 * moving the source of truth from a config file to the database didn't
	 * need to touch either of those methods.
	 */
	public function loadConfig() {
		if (self::$config_cache !== null) {
			return self::$config_cache;
		}

		$config = array(
			'strip_parenthetical' => array(),
			'strip_brackets'      => array(),
			'prefix_override'     => array(),
			'omit_facet_name'     => array(),
			'value_override'      => array(),
		);

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "filter_seo_facet_config`");
		foreach ($query->rows as $row) {
			$key = $row['type'] . $row['type_id'];

			if ($row['strip_parenthetical']) {
				$config['strip_parenthetical'][$key] = true;
			}

			if ($row['strip_brackets']) {
				$config['strip_brackets'][$key] = true;
			}

			if ($row['omit_facet_name']) {
				$config['omit_facet_name'][$key] = true;
			}

			if ($row['prefix_override'] !== '') {
				$config['prefix_override'][$key] = $row['prefix_override'];
			}
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "filter_seo_value_config`");
		foreach ($query->rows as $row) {
			$key = $row['type'] . $row['type_id'];
			$override_key = $row['type'] === 'fa' ? $row['value_text'] : (int)$row['value_id'];
			$config['value_override'][$key][$override_key] = $row['override'];
		}

		return self::$config_cache = $config;
	}

	/**
	 * Drops the request-lifetime config cache so a just-saved admin change
	 * is picked up by the very next loadConfig() call (e.g. before
	 * regenerating a facet's slugs in the same request).
	 */
	public function clearConfigCache() {
		self::$config_cache = null;
	}

	/**
	 * Merges CLI-flag overrides (comma/colon-separated strings from getopt())
	 * on top of the DB-backed config, for cli/generate_filter_seo_urls.php's
	 * one-off-run flags. Call before anything else reads loadConfig().
	 */
	public function mergeConfigOverrides($strip_parenthetical_flag, $strip_brackets_flag, $prefix_override_flag) {
		$config = $this->loadConfig();

		foreach (array_filter(explode(',', (string)$strip_parenthetical_flag)) as $token) {
			$config['strip_parenthetical'][trim($token)] = true;
		}

		foreach (array_filter(explode(',', (string)$strip_brackets_flag)) as $token) {
			$config['strip_brackets'][trim($token)] = true;
		}

		foreach (array_filter(explode(',', (string)$prefix_override_flag)) as $token) {
			list($key, $value) = array_pad(explode(':', trim($token), 2), 2, null);
			if ($key !== null && $value !== null && $value !== '') {
				$config['prefix_override'][$key] = $value;
			}
		}

		self::$config_cache = $config;
	}

	/**
	 * Current facet-level config row for the admin edit form, or sane
	 * defaults if nothing's been saved for it yet.
	 */
	public function getFacetConfig($type, $type_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "filter_seo_facet_config`
			WHERE `type` = '" . $this->db->escape($type) . "' AND `type_id` = '" . (int)$type_id . "'");

		if ($query->num_rows) {
			return array(
				'prefix_override'     => $query->row['prefix_override'],
				'omit_facet_name'     => (bool)$query->row['omit_facet_name'],
				'strip_parenthetical' => (bool)$query->row['strip_parenthetical'],
				'strip_brackets'      => (bool)$query->row['strip_brackets'],
			);
		}

		return array('prefix_override' => '', 'omit_facet_name' => false, 'strip_parenthetical' => false, 'strip_brackets' => false);
	}

	public function saveFacetConfig($type, $type_id, $prefix_override, $omit_facet_name, $strip_parenthetical, $strip_brackets) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "filter_seo_facet_config`
			WHERE `type` = '" . $this->db->escape($type) . "' AND `type_id` = '" . (int)$type_id . "'");

		if ($prefix_override !== '' || $omit_facet_name || $strip_parenthetical || $strip_brackets) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_seo_facet_config`
				SET `type` = '" . $this->db->escape($type) . "', `type_id` = '" . (int)$type_id . "',
				`prefix_override` = '" . $this->db->escape($prefix_override) . "',
				`omit_facet_name` = '" . ($omit_facet_name ? 1 : 0) . "',
				`strip_parenthetical` = '" . ($strip_parenthetical ? 1 : 0) . "',
				`strip_brackets` = '" . ($strip_brackets ? 1 : 0) . "',
				`date_modified` = NOW()");
		}

		$this->clearConfigCache();
	}

	/**
	 * All saved value-level overrides for a facet, for the admin value list.
	 * Returns [override_key => override_text], override_key matching
	 * computeSlugValue()'s convention (literal text for 'fa', int id otherwise).
	 */
	public function getValueOverrides($type, $type_id) {
		$config = $this->loadConfig();
		return $config['value_override'][$type . $type_id] ?? array();
	}

	public function saveValueOverride($type, $type_id, $value_id, $value_text, $override) {
		$value_id_col = $type === 'fa' ? 0 : (int)$value_id;
		$value_text_col = $type === 'fa' ? trim((string)$value_text) : '';
		$value_hash_col = $type === 'fa' ? md5($value_text_col) : '';

		$this->db->query("DELETE FROM `" . DB_PREFIX . "filter_seo_value_config`
			WHERE `type` = '" . $this->db->escape($type) . "' AND `type_id` = '" . (int)$type_id . "'
			AND `value_id` = '" . $value_id_col . "' AND `value_hash` = '" . $this->db->escape($value_hash_col) . "'");

		if ($override !== '') {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_seo_value_config`
				SET `type` = '" . $this->db->escape($type) . "', `type_id` = '" . (int)$type_id . "',
				`value_id` = '" . $value_id_col . "', `value_text` = '" . $this->db->escape($value_text_col) . "',
				`value_hash` = '" . $this->db->escape($value_hash_col) . "', `override` = '" . $this->db->escape($override) . "',
				`date_modified` = NOW()");
		}

		$this->clearConfigCache();
	}

	/**
	 * Every value currently in use for a facet, at a given language - the
	 * same per-type "what's actually on a product right now" queries the
	 * CLI backfill uses, exposed here for the admin value browser and for
	 * regenerateFacet()/regenerateValue(). Returns
	 * [['value_id' => int|null, 'value_text' => string|null, 'label' => string], ...].
	 */
	public function getUsedValues($type, $type_id, $language_id) {
		$language_id = (int)$language_id;
		$values = array();

		switch ($type) {
			case 'fa':
				$attribute_table = $this->config->get('filterAttributeValuesSeparator') ? 'journal3_product_attribute' : 'product_attribute';
				$query = $this->db->query("SELECT DISTINCT TRIM(`text`) AS value_text FROM `" . DB_PREFIX . $attribute_table . "`
					WHERE attribute_id = '" . (int)$type_id . "' AND language_id = '" . $language_id . "' AND TRIM(`text`) != ''");
				foreach ($query->rows as $row) {
					$values[] = array('value_id' => null, 'value_text' => $row['value_text'], 'label' => $row['value_text']);
				}
				break;

			case 'fo':
				$query = $this->db->query("SELECT DISTINCT option_value_id FROM `" . DB_PREFIX . "product_option_value` WHERE option_id = '" . (int)$type_id . "'");
				foreach ($query->rows as $row) {
					$label = $this->getOptionValueName((int)$row['option_value_id'], $language_id);
					if ($label !== null) {
						$values[] = array('value_id' => (int)$row['option_value_id'], 'value_text' => null, 'label' => $label);
					}
				}
				break;

			case 'ff':
				$query = $this->db->query("SELECT DISTINCT pf.filter_id FROM `" . DB_PREFIX . "product_filter` pf
					LEFT JOIN `" . DB_PREFIX . "filter` f ON (f.filter_id = pf.filter_id)
					WHERE f.filter_group_id = '" . (int)$type_id . "'");
				foreach ($query->rows as $row) {
					$label = $this->getFilterName((int)$row['filter_id'], $language_id);
					if ($label !== null) {
						$values[] = array('value_id' => (int)$row['filter_id'], 'value_text' => null, 'label' => $label);
					}
				}
				break;
		}

		return $values;
	}

	/**
	 * Deletes and immediately regenerates every slug for a facet, across
	 * every store/language combination - used after an admin changes a
	 * facet's prefix/strip rules, so the new rule is reflected right away
	 * instead of only affecting values that happen to get lazily
	 * regenerated on their next visit. Returns the number of rows recreated.
	 */
	public function regenerateFacet($type, $type_id, $store_ids, $language_ids) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "filter_seo_url`
			WHERE `type` = '" . $this->db->escape($type) . "' AND `type_id` = '" . (int)$type_id . "'");

		$created = 0;
		$orig_store_id = $this->config->get('config_store_id');
		$orig_language_id = $this->config->get('config_language_id');

		foreach ($store_ids as $store_id) {
			foreach ($language_ids as $language_id) {
				$this->config->set('config_store_id', $store_id);
				$this->config->set('config_language_id', $language_id);

				foreach ($this->getUsedValues($type, $type_id, $language_id) as $value) {
					if ($this->ensureSlugForFilterValue($type, $type_id, $value['value_id'], $value['value_text']) !== null) {
						$created++;
					}
				}
			}
		}

		$this->config->set('config_store_id', $orig_store_id);
		$this->config->set('config_language_id', $orig_language_id);

		return $created;
	}

	/**
	 * Same as regenerateFacet() but scoped to one value - used after an
	 * admin changes a single value's override, so a facet-wide regeneration
	 * (and its associated URL churn for every other value) isn't needed for
	 * a one-value edit.
	 */
	public function regenerateValue($type, $type_id, $value_id, $value_text, $store_ids, $language_ids) {
		$value_id_col = $type === 'fa' ? 0 : (int)$value_id;
		$value_hash_col = $type === 'fa' ? md5(trim((string)$value_text)) : '';

		$this->db->query("DELETE FROM `" . DB_PREFIX . "filter_seo_url`
			WHERE `type` = '" . $this->db->escape($type) . "' AND `type_id` = '" . (int)$type_id . "'
			AND `value_id` = '" . $value_id_col . "' AND `value_hash` = '" . $this->db->escape($value_hash_col) . "'");

		$created = 0;
		$orig_store_id = $this->config->get('config_store_id');
		$orig_language_id = $this->config->get('config_language_id');

		foreach ($store_ids as $store_id) {
			foreach ($language_ids as $language_id) {
				$this->config->set('config_store_id', $store_id);
				$this->config->set('config_language_id', $language_id);

				if ($this->ensureSlugForFilterValue($type, $type_id, $value_id, $value_text) !== null) {
					$created++;
				}
			}
		}

		$this->config->set('config_store_id', $orig_store_id);
		$this->config->set('config_language_id', $orig_language_id);

		return $created;
	}

	/**
	 * All attribute/option/filter-group facets for the admin list screen,
	 * with their name/group, collision status, and whether a facet_config
	 * override already exists - one row per (type, type_id).
	 */
	public function listFacets($language_id) {
		$language_id = (int)$language_id;
		$collisions = $this->detectNameCollisions($language_id);
		$configured = array();

		$query = $this->db->query("SELECT `type`, `type_id` FROM `" . DB_PREFIX . "filter_seo_facet_config`");
		foreach ($query->rows as $row) {
			$configured[$row['type'] . $row['type_id']] = true;
		}

		$facets = array();

		$query = $this->db->query("SELECT ad.attribute_id AS type_id, ad.name, agd.name AS group_name
			FROM `" . DB_PREFIX . "attribute_description` ad
			LEFT JOIN `" . DB_PREFIX . "attribute` a ON (a.attribute_id = ad.attribute_id)
			LEFT JOIN `" . DB_PREFIX . "attribute_group_description` agd ON (agd.attribute_group_id = a.attribute_group_id AND agd.language_id = ad.language_id)
			WHERE ad.language_id = '" . $language_id . "' ORDER BY ad.name");
		foreach ($query->rows as $row) {
			$facets[] = $this->buildFacetListRow('fa', $row, $collisions['fa'], $configured);
		}

		$query = $this->db->query("SELECT option_id AS type_id, `name` FROM `" . DB_PREFIX . "option_description` WHERE language_id = '" . $language_id . "' ORDER BY `name`");
		foreach ($query->rows as $row) {
			$facets[] = $this->buildFacetListRow('fo', $row, $collisions['fo'], $configured);
		}

		$query = $this->db->query("SELECT filter_group_id AS type_id, `name` FROM `" . DB_PREFIX . "filter_group_description` WHERE language_id = '" . $language_id . "' ORDER BY `name`");
		foreach ($query->rows as $row) {
			$facets[] = $this->buildFacetListRow('ff', $row, $collisions['ff'], $configured);
		}

		return $facets;
	}

	private function buildFacetListRow($type, $row, $collisions_for_type, $configured) {
		$type_id = (int)$row['type_id'];
		$base_slug = self::slugify($row['name']);

		return array(
			'type'       => $type,
			'type_id'    => $type_id,
			'name'       => $row['name'],
			'group_name' => $row['group_name'] ?? null,
			'colliding'  => isset($collisions_for_type[$base_slug]) && count($collisions_for_type[$base_slug]) > 1,
			'configured' => isset($configured[$type . $type_id]),
		);
	}

	/**
	 * Single-facet lookup for the admin edit page header - name, group
	 * (fa only), and collision status. Returns null if the facet doesn't
	 * exist (bad/stale type_id).
	 */
	public function getFacetInfo($type, $type_id, $language_id) {
		$language_id = (int)$language_id;
		$type_id = (int)$type_id;

		switch ($type) {
			case 'fa':
				$info = $this->getAttributeInfo($type_id, $language_id);
				if ($info === null) {
					return null;
				}
				list($name, $group_name) = $info;
				break;

			case 'fo':
				$name = $this->getOptionName($type_id, $language_id);
				$group_name = null;
				if ($name === null) {
					return null;
				}
				break;

			case 'ff':
				$name = $this->getFilterGroupName($type_id, $language_id);
				$group_name = null;
				if ($name === null) {
					return null;
				}
				break;

			default:
				return null;
		}

		$collisions = $this->detectNameCollisions($language_id);
		$base_slug = self::slugify($name);
		$collisions_for_type = $collisions[$type];

		return array(
			'type'       => $type,
			'type_id'    => $type_id,
			'name'       => $name,
			'group_name' => $group_name,
			'colliding'  => isset($collisions_for_type[$base_slug]) && count($collisions_for_type[$base_slug]) > 1,
			'colliding_with' => isset($collisions_for_type[$base_slug]) ? array_diff($collisions_for_type[$base_slug], array($type_id)) : array(),
		);
	}

	private function getAttributeInfo($attribute_id, $language_id) {
		$query = $this->db->query("SELECT ad.`name`, agd.`name` AS group_name
			FROM `" . DB_PREFIX . "attribute_description` ad
			LEFT JOIN `" . DB_PREFIX . "attribute` a ON (a.attribute_id = ad.attribute_id)
			LEFT JOIN `" . DB_PREFIX . "attribute_group_description` agd ON (agd.attribute_group_id = a.attribute_group_id AND agd.language_id = ad.language_id)
			WHERE ad.attribute_id = '" . $attribute_id . "' AND ad.language_id = '" . $language_id . "'");

		return $query->num_rows ? array($query->row['name'], $query->row['group_name']) : null;
	}

	private function getOptionName($option_id, $language_id) {
		$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "option_description` WHERE option_id = '" . $option_id . "' AND language_id = '" . $language_id . "'");
		return $query->num_rows ? $query->row['name'] : null;
	}

	private function getOptionValueName($option_value_id, $language_id) {
		$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "option_value_description` WHERE option_value_id = '" . $option_value_id . "' AND language_id = '" . $language_id . "'");
		return $query->num_rows ? $query->row['name'] : null;
	}

	private function getFilterGroupName($filter_group_id, $language_id) {
		$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "filter_group_description` WHERE filter_group_id = '" . $filter_group_id . "' AND language_id = '" . $language_id . "'");
		return $query->num_rows ? $query->row['name'] : null;
	}

	private function getFilterName($filter_id, $language_id) {
		$query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "filter_description` WHERE filter_id = '" . $filter_id . "' AND language_id = '" . $language_id . "'");
		return $query->num_rows ? $query->row['name'] : null;
	}

	/**
	 * Guards lazy generation: only ever create a row for a value some
	 * product actually has right now, mirroring the CLI backfill's own
	 * "only iterate values actually in use" scope. See the comment in
	 * ensureSlugForFilterValue() for why this matters.
	 */
	private function isValueInUse($type, $type_id, $value_id, $value_text) {
		switch ($type) {
			case 'fa':
				$attribute_table = $this->config->get('filterAttributeValuesSeparator') ? 'journal3_product_attribute' : 'product_attribute';
				$query = $this->db->query("SELECT 1 FROM `" . DB_PREFIX . $attribute_table . "`
					WHERE attribute_id = '" . (int)$type_id . "' AND TRIM(`text`) = '" . $this->db->escape(trim((string)$value_text)) . "' LIMIT 1");
				return (bool)$query->num_rows;

			case 'fo':
				$query = $this->db->query("SELECT 1 FROM `" . DB_PREFIX . "product_option_value`
					WHERE option_id = '" . (int)$type_id . "' AND option_value_id = '" . (int)$value_id . "' LIMIT 1");
				return (bool)$query->num_rows;

			case 'ff':
				$query = $this->db->query("SELECT 1 FROM `" . DB_PREFIX . "product_filter` pf
					LEFT JOIN `" . DB_PREFIX . "filter` f ON (f.filter_id = pf.filter_id)
					WHERE pf.filter_id = '" . (int)$value_id . "' AND f.filter_group_id = '" . (int)$type_id . "' LIMIT 1");
				return (bool)$query->num_rows;

			default:
				return false;
		}
	}

	/**
	 * Idempotent create-or-fetch. $value_text is the human-readable value
	 * stored and matched at resolve time (attribute text for fa, unused for
	 * fo/ff - those match by numeric value_id instead); $prefix is the
	 * already-qualified facet-name prefix (collision handling is the
	 * caller's responsibility, see detectNameCollisions()/buildPrefix()).
	 *
	 * $slug_value, if given, is what actually gets slugified instead of
	 * $value_text - e.g. to drop a manufacturer color code like "(C066)"
	 * from the URL without touching the literal text that fa filtering
	 * matches against via TRIM(product_attribute.text) =. Defaults to
	 * $value_text. Never strip $value_text itself for 'fa' - only what's
	 * passed here.
	 *
	 * Returns ['slug' => string, 'needs_review' => bool, 'created' => bool].
	 */
	public function ensureSlug($type, $type_id, $value_id, $value_text, $prefix, $slug_value = null) {
		$existing = $this->getSlug($type, $type_id, $value_id, $value_text);
		if ($existing !== null) {
			return array('slug' => $existing, 'needs_review' => false, 'created' => false);
		}

		if ($slug_value === null) {
			$slug_value = $value_text;
		}

		$store_id = (int)$this->config->get('config_store_id');
		$language_id = (int)$this->config->get('config_language_id');

		$base_slug = trim($prefix . '-' . self::slugify($slug_value), '-');

		$slug = $base_slug;
		$needs_review = false;
		$suffix = 1;
		while ($this->slugTaken($slug, $store_id, $language_id)) {
			$suffix++;
			$slug = $base_slug . '-' . $suffix;
			$needs_review = true;
		}

		$value_id_col = $type === 'fa' ? 0 : (int)$value_id;
		$value_text_col = $type === 'fa' ? trim((string)$value_text) : '';
		$value_hash_col = $type === 'fa' ? md5($value_text_col) : '';

		try {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "filter_seo_url`
				SET `store_id` = '" . $store_id . "',
				`language_id` = '" . $language_id . "',
				`type` = '" . $this->db->escape($type) . "',
				`type_id` = '" . (int)$type_id . "',
				`value_id` = '" . $value_id_col . "',
				`value_text` = '" . $this->db->escape($value_text_col) . "',
				`value_hash` = '" . $this->db->escape($value_hash_col) . "',
				`slug` = '" . $this->db->escape($slug) . "',
				`needs_review` = '" . ($needs_review ? 1 : 0) . "',
				`date_added` = NOW(),
				`date_modified` = NOW()");
		} catch (\Exception $e) {
			// A concurrent request for the exact same value can race us here
			// (both saw "no existing row", both tried to insert) - the
			// reverse_lookup unique key rejects the loser with a duplicate-key
			// error (1062). That's not a real failure: the winner's row is
			// what we wanted anyway, so fetch and return it instead of
			// bubbling up a 500. Any other DB error still propagates.
			if (strpos($e->getMessage(), 'Error No: 1062') !== false) {
				$winner = $this->getSlug($type, $type_id, $value_id, $value_text);
				if ($winner !== null) {
					return array('slug' => $winner, 'needs_review' => false, 'created' => false);
				}
			}

			throw $e;
		}

		return array('slug' => $slug, 'needs_review' => $needs_review, 'created' => true);
	}

	private function slugTaken($slug, $store_id, $language_id) {
		$query = $this->db->query("SELECT `filter_seo_url_id` FROM `" . DB_PREFIX . "filter_seo_url`
			WHERE `store_id` = '" . (int)$store_id . "' AND `language_id` = '" . (int)$language_id . "'
			AND `slug` = '" . $this->db->escape($slug) . "'");
		if ($query->num_rows) {
			return true;
		}

		// Filter slugs and category/product keywords share the same URL
		// segment namespace at resolve time - must not collide either.
		$query = $this->db->query("SELECT `seo_url_id` FROM `" . DB_PREFIX . "seo_url`
			WHERE `store_id` = '" . (int)$store_id . "' AND `language_id` = '" . (int)$language_id . "'
			AND `keyword` = '" . $this->db->escape($slug) . "'");

		return (bool)$query->num_rows;
	}

	/**
	 * Groups attribute/option/filter-group description names by their
	 * slugified form, for any given language, and returns the ones shared by
	 * more than one type_id - a deterministic, content-only pre-pass so
	 * collision resolution never depends on backfill iteration order.
	 *
	 * Returns ['fa' => [slug => [type_id, ...]], 'fo' => [...], 'ff' => [...]]
	 * with only colliding groups (count > 1) included.
	 */
	public function detectNameCollisions($language_id) {
		$language_id = (int)$language_id;

		$query = $this->db->query("SELECT `attribute_id` AS `id`, `name` FROM `" . DB_PREFIX . "attribute_description` WHERE `language_id` = '" . $language_id . "'");
		$fa = $this->groupCollisions($query->rows);

		$query = $this->db->query("SELECT `option_id` AS `id`, `name` FROM `" . DB_PREFIX . "option_description` WHERE `language_id` = '" . $language_id . "'");
		$fo = $this->groupCollisions($query->rows);

		$query = $this->db->query("SELECT `filter_group_id` AS `id`, `name` FROM `" . DB_PREFIX . "filter_group_description` WHERE `language_id` = '" . $language_id . "'");
		$ff = $this->groupCollisions($query->rows);

		return array('fa' => $fa, 'fo' => $fo, 'ff' => $ff);
	}

	/**
	 * Same as detectNameCollisions() but memoized per language for the life
	 * of the request - ensureSlugForFilterValue() may run this several times
	 * in one request (e.g. a multi-facet URL with more than one new value)
	 * and the underlying tables are small, but there's no reason to re-scan
	 * them repeatedly for the same language.
	 */
	public function detectNameCollisionsCached($language_id) {
		$language_id = (int)$language_id;

		if (!isset(self::$collisions_cache[$language_id])) {
			self::$collisions_cache[$language_id] = $this->detectNameCollisions($language_id);
		}

		return self::$collisions_cache[$language_id];
	}

	private function groupCollisions($rows) {
		$by_slug = array();
		foreach ($rows as $row) {
			$by_slug[self::slugify($row['name'])][] = (int)$row['id'];
		}

		$collisions = array();
		foreach ($by_slug as $slug => $ids) {
			if (count($ids) > 1) {
				$collisions[$slug] = $ids;
			}
		}

		return $collisions;
	}
}
