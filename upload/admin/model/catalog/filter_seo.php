<?php
/**
 * Admin data layer for the Filter SEO screen (Catalog > Filter SEO) - a thin
 * wrapper around FilterSeoUrl (system/library/filterseourl.php), which owns
 * all the actual slug-generation/config logic shared with the CLI backfill
 * and on-the-fly generation. This model only adds what's admin-specific:
 * store/language enumeration for the "regenerate across every storefront"
 * actions, and current-slug lookups for the value-list preview column.
 */
class ModelCatalogFilterSeo extends Model {
	private $filter_repo;

	private function getFilterRepo() {
		if (!$this->filter_repo) {
			$this->filter_repo = new FilterSeoUrl($this->registry);
		}

		return $this->filter_repo;
	}

	public function getFacets($language_id) {
		return $this->getFilterRepo()->listFacets($language_id);
	}

	public function getFacetInfo($type, $type_id, $language_id) {
		return $this->getFilterRepo()->getFacetInfo($type, $type_id, $language_id);
	}

	public function getFacetConfig($type, $type_id) {
		return $this->getFilterRepo()->getFacetConfig($type, $type_id);
	}

	public function saveFacetConfig($type, $type_id, $prefix_override, $omit_facet_name, $strip_parenthetical, $strip_brackets) {
		$this->getFilterRepo()->saveFacetConfig($type, $type_id, $prefix_override, $omit_facet_name, $strip_parenthetical, $strip_brackets);
	}

	/**
	 * Values currently in use for a facet (at the given language), each
	 * annotated with its saved override (if any) and its current live slug
	 * (if one's been generated yet) for the admin preview column.
	 */
	public function getFacetValues($type, $type_id, $language_id) {
		$repo = $this->getFilterRepo();
		$overrides = $repo->getValueOverrides($type, $type_id);

		// The admin config object has no ambient "current store" the way a
		// storefront request does - preview against the default/main store
		// (0) explicitly, at whatever language this screen is showing.
		$this->config->set('config_store_id', 0);
		$this->config->set('config_language_id', (int)$language_id);

		$values = array();

		foreach ($repo->getUsedValues($type, $type_id, $language_id) as $value) {
			$override_key = $type === 'fa' ? $value['value_text'] : $value['value_id'];

			$values[] = array(
				'value_id'   => $value['value_id'],
				'value_text' => $value['value_text'],
				'label'      => $value['label'],
				'override'   => $overrides[$override_key] ?? '',
				'slug'       => $repo->getSlug($type, $type_id, $value['value_id'], $value['value_text']),
			);
		}

		return $values;
	}

	public function saveValueOverride($type, $type_id, $value_id, $value_text, $override) {
		$this->getFilterRepo()->saveValueOverride($type, $type_id, $value_id, $value_text, $override);
	}

	public function regenerateFacet($type, $type_id) {
		return $this->getFilterRepo()->regenerateFacet($type, $type_id, $this->getStoreIds(), $this->getLanguageIds());
	}

	public function regenerateValue($type, $type_id, $value_id, $value_text) {
		return $this->getFilterRepo()->regenerateValue($type, $type_id, $value_id, $value_text, $this->getStoreIds(), $this->getLanguageIds());
	}

	private function getStoreIds() {
		$store_ids = array(0);

		$this->load->model('setting/store');
		foreach ($this->model_setting_store->getStores() as $store) {
			$store_ids[] = (int)$store['store_id'];
		}

		return $store_ids;
	}

	private function getLanguageIds() {
		$language_ids = array();

		$this->load->model('localisation/language');
		foreach ($this->model_localisation_language->getLanguages() as $language) {
			$language_ids[] = (int)$language['language_id'];
		}

		return $language_ids;
	}
}
