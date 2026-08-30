#!/usr/bin/env php
<?php
/**
 * Backfills ocus_filter_seo_url with pretty-URL slugs for every
 * attribute/option/filter value actually in use by products, so
 * catalog/controller/startup/seo_url.php can turn Journal3 filter query
 * params (fa70=..., fo12=..., ff3=...) into path segments and back.
 *
 * Idempotent - safe to re-run as new products/attributes are added; existing
 * slugs are never renamed or reissued. New values also get a slug lazily,
 * the first time they're linked to on the live site (see
 * FilterSeoUrl::ensureSlugForFilterValue()) - this script exists to
 * front-load that work in bulk (e.g. after a catalog import) rather than
 * relying only on organic traffic to trigger it one value at a time.
 *
 * Usage:
 *   php generate_filter_seo_urls.php --dry-run                  # preview, rolls back
 *   php generate_filter_seo_urls.php                             # run for real, all stores/languages
 *   php generate_filter_seo_urls.php --store-id=0 --language-id=1
 */

$admin_dir = dirname(__FILE__) . '/../admin/';
if (file_exists($admin_dir . 'config.php')) {
	require_once($admin_dir . 'config.php');
} else {
	die("ERROR: Cannot access admin/config.php\n");
}

require_once(DIR_SYSTEM . 'startup.php');

$registry = new Registry();

$loader = new Loader($registry);
$registry->set('load', $loader);

$config = new Config();
$registry->set('config', $config);

$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$registry->set('db', $db);

$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '0'");
foreach ($query->rows as $setting) {
	if (!$setting['serialized']) {
		$config->set($setting['key'], $setting['value']);
	} else {
		$config->set($setting['key'], json_decode($setting['value'], true));
	}
}

$options = getopt('', array('dry-run', 'store-id:', 'language-id:', 'strip-parenthetical:', 'strip-brackets:', 'prefix-override:', 'help'));

if (isset($options['help'])) {
	echo "Usage:\n";
	echo "  php generate_filter_seo_urls.php --dry-run                     # preview, rolls back\n";
	echo "  php generate_filter_seo_urls.php                                # run for real, all stores/languages\n";
	echo "  php generate_filter_seo_urls.php --store-id=0 --language-id=1   # restrict to one store/language\n";
	echo "  php generate_filter_seo_urls.php --strip-parenthetical=fo30,fa70\n";
	echo "      Drop a trailing '(...)' from the slug (not from the stored/matched value) for the\n";
	echo "      given type+type_id facets only - e.g. manufacturer color codes like 'Синий (C066)'.\n";
	echo "      Never apply this blindly to every facet: for facets like shoe sizes the parenthetical\n";
	echo "      is meaningful data ('38 1/3 us(6)'), not a code, and stripping it would be lossy.\n";
	echo "  php generate_filter_seo_urls.php --strip-brackets=fo11\n";
	echo "      Same idea but for a trailing '[...]', e.g. clothing size 'Euro XS [Asia (S)]' -> 'Euro XS'.\n";
	echo "  php generate_filter_seo_urls.php --prefix-override=fa19:raketki\n";
	echo "      Always combine this word with fa19's own name as the qualifier, whether or not it\n";
	echo "      collides with anything - 'spetsifikatsiya-raketok-tsvet-belyy' becomes 'raketki-tsvet-belyy'.\n";
	echo "  Note: existing rows are never regenerated (idempotent) - to apply a new strip/prefix rule\n";
	echo "  to facets that already have rows, delete those rows first, then re-run.\n";
	echo "  All of the above are read by default from ocus_filter_seo_facet_config /\n";
	echo "  ocus_filter_seo_value_config (also used by on-the-fly generation) - edit them via\n";
	echo "  Admin > Catalog > Filter SEO, or directly in the DB. CLI flags merge on top for one-off runs.\n";
	exit(0);
}

$dry_run = isset($options['dry-run']);

$filter_repo = new FilterSeoUrl($registry);
$filter_repo->mergeConfigOverrides(
	$options['strip-parenthetical'] ?? '',
	$options['strip-brackets'] ?? '',
	$options['prefix-override'] ?? ''
);

// Store 0 is the default/main store (implicit, no row in oc_store); any
// additional storefronts are actual rows.
$store_ids = array(0);
$query = $db->query("SELECT store_id FROM " . DB_PREFIX . "store");
foreach ($query->rows as $row) {
	$store_ids[] = (int)$row['store_id'];
}
if (isset($options['store-id'])) {
	$store_ids = array((int)$options['store-id']);
}

$language_ids = array();
$query = $db->query("SELECT language_id FROM " . DB_PREFIX . "language");
foreach ($query->rows as $row) {
	$language_ids[] = (int)$row['language_id'];
}
if (isset($options['language-id'])) {
	$language_ids = array((int)$options['language-id']);
}

$total_created = 0;
$total_existing = 0;
$needs_review = array();

foreach ($store_ids as $store_id) {
	foreach ($language_ids as $language_id) {
		echo "=== store_id={$store_id} language_id={$language_id} ===\n";

		$config->set('config_store_id', $store_id);
		$config->set('config_language_id', $language_id);

		if ($dry_run) {
			$db->query("START TRANSACTION");
		}

		$collisions = $filter_repo->detectNameCollisions($language_id);

		// --- fa: attributes, keyed by (attribute_id, TRIM'd text) actually used on products ---
		$attribute_table = $config->get('filterAttributeValuesSeparator') ? 'journal3_product_attribute' : 'product_attribute';
		$query = $db->query("SELECT DISTINCT pa.attribute_id, TRIM(pa.text) AS text
			FROM " . DB_PREFIX . $attribute_table . " pa
			WHERE pa.language_id = '" . (int)$language_id . "' AND TRIM(pa.text) != ''");

		$attribute_names = array();
		$attribute_group_names = array();

		foreach ($query->rows as $row) {
			$attribute_id = (int)$row['attribute_id'];

			if (!isset($attribute_names[$attribute_id])) {
				$name_query = $db->query("SELECT ad.name, agd.name AS group_name
					FROM " . DB_PREFIX . "attribute_description ad
					LEFT JOIN " . DB_PREFIX . "attribute a ON (a.attribute_id = ad.attribute_id)
					LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (agd.attribute_group_id = a.attribute_group_id AND agd.language_id = ad.language_id)
					WHERE ad.attribute_id = '" . $attribute_id . "' AND ad.language_id = '" . (int)$language_id . "'");

				if (!$name_query->num_rows) {
					continue;
				}

				$attribute_names[$attribute_id] = $name_query->row['name'];
				$attribute_group_names[$attribute_id] = $name_query->row['group_name'];
			}

			$prefix = $filter_repo->buildPrefix('fa', $attribute_id, $attribute_names[$attribute_id], $attribute_group_names[$attribute_id], $collisions['fa']);
			$slug_value = $filter_repo->computeSlugValue('fa', $attribute_id, null, $row['text'], $row['text']);

			$result = $filter_repo->ensureSlug('fa', $attribute_id, null, $row['text'], $prefix, $slug_value);
			reportResult('fa', $attribute_id, $row['text'], $result, $total_created, $total_existing, $needs_review);
		}

		// --- fo: options, keyed by (option_id, option_value_id) actually used on products ---
		$query = $db->query("SELECT DISTINCT pov.option_id, pov.option_value_id
			FROM " . DB_PREFIX . "product_option_value pov");

		$option_names = array();

		foreach ($query->rows as $row) {
			$option_id = (int)$row['option_id'];
			$option_value_id = (int)$row['option_value_id'];

			if (!isset($option_names[$option_id])) {
				$name_query = $db->query("SELECT `name` FROM " . DB_PREFIX . "option_description
					WHERE option_id = '" . $option_id . "' AND language_id = '" . (int)$language_id . "'");
				if (!$name_query->num_rows) {
					continue;
				}
				$option_names[$option_id] = $name_query->row['name'];
			}

			$value_query = $db->query("SELECT `name` FROM " . DB_PREFIX . "option_value_description
				WHERE option_value_id = '" . $option_value_id . "' AND language_id = '" . (int)$language_id . "'");
			if (!$value_query->num_rows) {
				continue;
			}

			// Options have no group concept in core OpenCart - fall back to
			// the option_id itself as the disambiguating qualifier.
			$prefix = $filter_repo->buildPrefix('fo', $option_id, $option_names[$option_id], 'option-' . $option_id, $collisions['fo']);
			$slug_value = $filter_repo->computeSlugValue('fo', $option_id, $option_value_id, null, $value_query->row['name']);

			$result = $filter_repo->ensureSlug('fo', $option_id, $option_value_id, $value_query->row['name'], $prefix, $slug_value);
			reportResult('fo', $option_id, $value_query->row['name'], $result, $total_created, $total_existing, $needs_review);
		}

		// --- ff: stock filters, keyed by (filter_group_id, filter_id) actually used on products ---
		$query = $db->query("SELECT DISTINCT pf.filter_id, f.filter_group_id
			FROM " . DB_PREFIX . "product_filter pf
			LEFT JOIN " . DB_PREFIX . "filter f ON (f.filter_id = pf.filter_id)");

		$filter_group_names = array();

		foreach ($query->rows as $row) {
			$filter_id = (int)$row['filter_id'];
			$filter_group_id = (int)$row['filter_group_id'];

			if (!isset($filter_group_names[$filter_group_id])) {
				$group_query = $db->query("SELECT `name` FROM " . DB_PREFIX . "filter_group_description
					WHERE filter_group_id = '" . $filter_group_id . "' AND language_id = '" . (int)$language_id . "'");
				if (!$group_query->num_rows) {
					continue;
				}
				$filter_group_names[$filter_group_id] = $group_query->row['name'];
			}

			$name_query = $db->query("SELECT `name` FROM " . DB_PREFIX . "filter_description
				WHERE filter_id = '" . $filter_id . "' AND language_id = '" . (int)$language_id . "'");
			if (!$name_query->num_rows) {
				continue;
			}

			// type_id here already IS the filter group, so there's nothing
			// meaningful left to qualify with on collision - group_name=null.
			$prefix = $filter_repo->buildPrefix('ff', $filter_group_id, $filter_group_names[$filter_group_id], null, $collisions['ff']);
			$slug_value = $filter_repo->computeSlugValue('ff', $filter_group_id, $filter_id, null, $name_query->row['name']);

			$result = $filter_repo->ensureSlug('ff', $filter_group_id, $filter_id, $name_query->row['name'], $prefix, $slug_value);
			reportResult('ff', $filter_group_id, $name_query->row['name'], $result, $total_created, $total_existing, $needs_review);
		}

		if ($dry_run) {
			$db->query("ROLLBACK");
		}
	}
}

function reportResult($type, $type_id, $value, $result, &$total_created, &$total_existing, &$needs_review) {
	if ($result['created']) {
		$total_created++;
		echo "  + {$type}{$type_id} '{$value}' -> {$result['slug']}" . ($result['needs_review'] ? " (REVIEW: collided, suffixed)\n" : "\n");
	} else {
		$total_existing++;
	}

	if ($result['needs_review']) {
		$needs_review[] = "{$type}{$type_id} '{$value}' -> {$result['slug']}";
	}
}

echo "\n";
echo $dry_run ? "DRY RUN - no changes were committed.\n" : "Done.\n";
echo "Created: {$total_created}, already existed: {$total_existing}\n";

if ($needs_review) {
	echo "\nREVIEW NEEDED (" . count($needs_review) . " slug(s) had to be numerically suffixed - check for duplicate attribute/option/filter definitions):\n";
	foreach ($needs_review as $line) {
		echo "  - $line\n";
	}
}
