<?php // ==========================================  seo_url.php v.140618 opencart-russia.ru ===============================
class ControllerStartupSeoUrl extends Controller {
	private $filter_repo;

	private function getFilterRepo() {
		if (!$this->filter_repo) {
			$this->filter_repo = new FilterSeoUrl($this->registry);
		}

		return $this->filter_repo;
	}

	public function index() {
		// Add rewrite to url class
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);
		}

		// Decode URL
		if (isset($this->request->get['_route_'])) {
			// Snapshot which fa/fo/ff/fm keys genuinely arrived as a raw
			// "?fa70=..." query string, before the parts loop below injects
			// the exact same key shape from resolved path segments - the
			// legacy-redirect check further down must only ever act on the
			// former, or a pretty URL like /cat/colour-belyi would see its
			// own injected "fa70" and 301-redirect to itself.
			$legacy_query_filter_keys = array();

			foreach ($this->request->get as $key => $value) {
				if (preg_match('/^(fa|fo|ff)\d+$/', (string)$key) || $key === 'fm') {
					$legacy_query_filter_keys[$key] = true;
				}
			}

			$parts = explode('/', $this->request->get['_route_']);

			// remove any empty arrays from trailing
			if (utf8_strlen(end($parts)) == 0) {
				array_pop($parts);
			}

			foreach ($parts as $part) {
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($part) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

				if ($query->num_rows) {
					$url = explode('=', $query->row['query']);

					if ($url[0] == 'product_id') {
						$this->request->get['product_id'] = $url[1];
					}

					if ($url[0] == 'category_id') {
						if (!isset($this->request->get['path'])) {
							$this->request->get['path'] = $url[1];
						} else {
							$this->request->get['path'] .= '_' . $url[1];
						}
					}

					if ($url[0] == 'manufacturer_id') {
						if (isset($this->request->get['path'])) {
							// A category has already resolved earlier in this
							// path - this keyword means "filter by this
							// manufacturer within the listing", not "go to
							// this manufacturer's own dedicated page".
							$this->request->get['fm'] = isset($this->request->get['fm']) ? $this->request->get['fm'] . ',' . $url[1] : $url[1];
						} else {
							$this->request->get['manufacturer_id'] = $url[1];
						}
					}

					if ($url[0] == 'information_id') {
						$this->request->get['information_id'] = $url[1];
					}

					if ($query->row['query'] && $url[0] != 'information_id' && $url[0] != 'manufacturer_id' && $url[0] != 'category_id' && $url[0] != 'product_id') {
						$this->request->get['route'] = $query->row['query'];
					}
				} elseif (isset($this->request->get['path'])) {
					// Not a category/product/manufacturer/information keyword, but a
					// category has already resolved earlier in this path - try it as a
					// Journal3 filter slug before giving up. A single value is a plain
					// full slug ("colour-belyi"); multiple values of the same facet
					// share one prefix instead of repeating it ("razmer-odezhdy-euro-xxs
					// ,euro-xs,euro-s") - see rewriteFilterSegments()'s encode side. Comma,
					// not "+": a literal "+" in the path becomes a space by the time it
					// gets here, because .htaccess passes _route_ through the query string
					// (index.php?_route_=$1) and PHP's automatic $_GET decoding treats "+"
					// as application/x-www-form-urlencoded space - a comma has no such
					// special meaning and survives untouched. Each continuation token is
					// tried bare first (in case it happens to already be a full standalone
					// slug), then with the first value's resolved prefix prepended. A
					// multi-manufacturer segment ("yonex,li-ning") never matches the
					// oc_seo_url lookup above as one whole part (no keyword has a comma
					// in it), so it always lands here - each sub-token falls back to a
					// manufacturer-keyword lookup once resolveSlug() misses.
					$filter_match = true;
					$prefix = null;
					$store_id = (int)$this->config->get('config_store_id');
					$language_id = (int)$this->config->get('config_language_id');

					foreach (explode(',', $part) as $index => $sub) {
						$sub = mb_strtolower($sub, 'UTF-8');
						$match = $this->getFilterRepo()->resolveSlug($sub, $store_id, $language_id);

						if ($match === null && $index > 0 && $prefix !== null && $prefix !== '') {
							$match = $this->getFilterRepo()->resolveSlug($prefix . '-' . $sub, $store_id, $language_id);
						}

						if ($match === null) {
							$manufacturer_id = $this->getFilterRepo()->resolveManufacturerKeyword($sub, $store_id, $language_id);

							if ($manufacturer_id !== null) {
								$match = array('type' => 'fm', 'type_id' => null, 'value_id' => $manufacturer_id, 'value_text' => null);
							}
						}

						if ($match === null) {
							$filter_match = false;

							break;
						}

						if ($index === 0 && $match['type'] !== 'fm') {
							$prefix = $this->getFilterRepo()->getPrefixForFacet($match['type'], $match['type_id'], $language_id);
						}

						$filter_key = $match['type'] === 'fm' ? 'fm' : $match['type'] . $match['type_id'];
						$filter_value = $match['type'] === 'fa' ? $match['value_text'] : $match['value_id'];

						$this->request->get[$filter_key] = isset($this->request->get[$filter_key]) ? $this->request->get[$filter_key] . ',' . $filter_value : $filter_value;
					}

					if (!$filter_match) {
						$this->request->get['route'] = 'error/not_found';

						break;
					}
				} else {
					$this->request->get['route'] = 'error/not_found';

					break;
				}
			}

			if (!isset($this->request->get['route'])) {
				if (isset($this->request->get['product_id'])) {
					$this->request->get['route'] = 'product/product';
				} elseif (isset($this->request->get['path'])) {
					$this->request->get['route'] = 'product/category';
				} elseif (isset($this->request->get['manufacturer_id'])) {
					$this->request->get['route'] = 'product/manufacturer/info';
				} elseif (isset($this->request->get['information_id'])) {
					$this->request->get['route'] = 'information/information';
				}
			}

			// Legacy filter query-string consolidation: an old bookmarked/indexed
			// /category-slug?fa70=...&fo12=... URL redirects to the fully pretty
			// /category-slug/colour-belyi/razmer-42 form once one exists for every
			// active fa/fo/ff param. Never fires for the AJAX filter panel itself
			// (Filter-Module header) - that must keep using the plain query-string
			// form for its actual fetch, only the address bar gets prettified
			// client-side (see filter.js).
			if ($this->request->get['route'] === 'product/category'
				&& $this->config->get('config_seo_url')
				&& ($this->config->get('filterSeoRedirectLegacyUrls') ?? true)
				&& empty($this->request->server['HTTP_FILTER_MODULE'])
				&& empty($this->request->post)
				&& !isset($this->request->get['token'])
			) {
				if ($legacy_query_filter_keys) {
					$remaining = $this->request->get;

					unset($remaining['route'], $remaining['path'], $remaining['_route_']);

					$segment = $this->rewriteFilterSegments($remaining);

					if ($segment) {
						$target = $this->url->link('product/category', 'path=' . $this->request->get['path'], false) . $segment;

						if ($remaining) {
							$target .= (strpos($target, '?') === false ? '?' : '&') . http_build_query($remaining);
						}

						$this->response->redirect($target, 301);
					}
				}
			}
		// Redirect 301
		} elseif (isset($this->request->get['route']) && empty($this->request->post) && !isset($this->request->get['token']) && $this->config->get('config_seo_url')) {
			$arg = '';
			$cat_path = false;
			$route = $this->request->get['route'];

			if ($this->request->get['route'] == 'product/product' && isset($this->request->get['product_id'])) {
				$route = 'product_id=' . (int)$this->request->get['product_id'];
			} elseif ($this->request->get['route'] == 'product/category' && isset($this->request->get['path'])) {
				$categorys_id = explode('_', $this->request->get['path']);
				$cat_path = '';
				foreach ($categorys_id as $category_id) {
					$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'category_id=" . (int)$category_id . "' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "' AND `language_id` = '" . (int)$this->config->get('config_language_id') . "'");   
					if ($query->num_rows && $query->row['keyword'] /**/ ) {
						$cat_path .= '/' . $query->row['keyword'];
					} else {
						$cat_path = false;
						break;
					}
				}
				$arg = trim($cat_path, '/');
				if (isset($this->request->get['page'])) $arg = $arg . '?page=' . (int)$this->request->get['page'];
			} elseif ($this->request->get['route'] == 'product/manufacturer/info' && isset($this->request->get['manufacturer_id'])) {
				$route = 'manufacturer_id=' . (int)$this->request->get['manufacturer_id'];
				if (isset($this->request->get['page'])) $arg = $arg . '?page=' . (int)$this->request->get['page'];
			} elseif ($this->request->get['route'] == 'information/information' && isset($this->request->get['information_id'])) {
				$route = 'information_id=' . (int)$this->request->get['information_id'];
			} elseif (sizeof($this->request->get) > 1) {
				$args = '?' . str_replace("route=" . $this->request->get['route'].'&amp;', "", $this->request->server['QUERY_STRING']);
				$arg = str_replace('&amp;', '&', $args);
			}

			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE `query` = '" . $this->db->escape($route) . "' AND `store_id` = '" . (int)$this->config->get('config_store_id') . "' AND `language_id` = '" . (int)$this->config->get('config_language_id') . "'");

			if (!empty($query->num_rows) && !empty($query->row['keyword']) && $route) {
				$this->response->redirect($query->row['keyword'] . $arg, 301);
			} elseif ($cat_path) {
				$this->response->redirect($arg, 301);
			} elseif ($this->request->get['route'] == 'common/home') {
				$this->response->redirect(HTTP_SERVER . $arg, 301);
			}
		}
	}

	/**
	 * Given the parsed query args for a product/category link, resolves any
	 * fa{attribute_id}/fo{option_id}/ff{filter_id} keys to slugs and returns
	 * the "/segment/segment" string to append to the path, in a fixed
	 * deterministic order (fa < fo < ff, then numeric id ascending) so the
	 * same active filter set always produces the same URL. All-or-nothing:
	 * if any present filter key has no slug yet, none are touched and they
	 * fall through to the caller's normal leftover-query-string handling.
	 * Consumed keys are unset from $data.
	 *
	 * A value with no slug yet gets one created on the spot (see
	 * FilterSeoUrl::ensureSlugForFilterValue()) rather than only falling
	 * back to the ugly query-string form - so a brand-new attribute/option
	 * value gets a pretty URL starting with the very first link built to it,
	 * not after the next cli/generate_filter_seo_urls.php run.
	 */
	private function rewriteFilterSegments(&$data) {
		$type_rank = array('fa' => 0, 'fo' => 1, 'ff' => 2);
		$filter_keys = array();

		foreach ($data as $key => $value) {
			if (preg_match('/^(fa|fo|ff)(\d+)$/', (string)$key, $matches)) {
				$filter_keys[] = array('key' => $key, 'type' => $matches[1], 'type_id' => (int)$matches[2], 'value' => $value);
			}
		}

		if (!$filter_keys && !isset($data['fm'])) {
			return '';
		}

		usort($filter_keys, function ($a, $b) use ($type_rank) {
			if ($type_rank[$a['type']] !== $type_rank[$b['type']]) {
				return $type_rank[$a['type']] - $type_rank[$b['type']];
			}

			return $a['type_id'] - $b['type_id'];
		});

		$segments = array();

		// Manufacturer filter comes first in the URL and is handled
		// separately from fa/fo/ff: it isn't parameterized by a type_id (see
		// FilterSeoUrl::getManufacturerKeyword()'s docblock), so it reuses
		// each manufacturer's own core SEO keyword rather than going through
		// ocus_filter_seo_url at all.
		if (isset($data['fm'])) {
			$store_id = (int)$this->config->get('config_store_id');
			$language_id = (int)$this->config->get('config_language_id');
			$keywords = array();
			$all_mapped = true;

			foreach (explode(',', (string)$data['fm']) as $manufacturer_id) {
				$keyword = $this->getFilterRepo()->getManufacturerKeyword((int)trim($manufacturer_id), $store_id, $language_id);

				if ($keyword === null) {
					// All-or-nothing, same rule as fa/fo/ff: leave fm alone,
					// it falls through to the query string.
					$all_mapped = false;

					break;
				}

				$keywords[] = $keyword;
			}

			if ($all_mapped && $keywords) {
				$segments[] = implode(',', $keywords);

				unset($data['fm']);
			}
		}

		foreach ($filter_keys as $filter_key) {
			$full_slugs = array();

			foreach (explode(',', (string)$filter_key['value']) as $sub_value) {
				$sub_value = trim($sub_value);
				$value_id = $filter_key['type'] === 'fa' ? null : (int)$sub_value;
				$value_text = $filter_key['type'] === 'fa' ? $sub_value : null;

				$slug = $this->getFilterRepo()->ensureSlugForFilterValue($filter_key['type'], $filter_key['type_id'], $value_id, $value_text);

				if ($slug === null) {
					// The facet itself doesn't exist (stale/bad id) - leave
					// all of them in $data, they fall through to the query
					// string exactly as an unmapped value did before.
					return '';
				}

				$full_slugs[] = $slug;
			}

			// A single value keeps its full stored slug as-is (also usable
			// standalone, e.g. /category/razmer-odezhdy-euro-xxs). Multiple
			// values of the SAME facet share one prefix instead of repeating
			// it per value - razmer-odezhdy-euro-xxs,euro-xs,euro-s instead
			// of razmer-odezhdy-euro-xxs,razmer-odezhdy-euro-xs,... See
			// index()'s counterpart, which reconstructs the stripped prefix
			// on the way back in. Comma, not "+" - see the comment in
			// index() for why "+" gets silently turned into a space before
			// this code ever sees it.
			if (count($full_slugs) === 1) {
				$segments[] = $full_slugs[0];
			} else {
				$prefix = $this->getFilterRepo()->getPrefixForFacet($filter_key['type'], $filter_key['type_id'], (int)$this->config->get('config_language_id'));
				$parts = array();

				foreach ($full_slugs as $i => $full_slug) {
					if ($i === 0 || $prefix === null || $prefix === '' || strpos($full_slug, $prefix . '-') !== 0) {
						$parts[] = $full_slug;
					} else {
						$parts[] = substr($full_slug, strlen($prefix) + 1);
					}
				}

				$segments[] = implode(',', $parts);
			}
		}

		foreach ($filter_keys as $filter_key) {
			unset($data[$filter_key['key']]);
		}

		// $segments can still be empty here even though isset($data['fm'])
		// was true at the top - e.g. fm failed to map and there were no
		// fa/fo/ff keys at all - guard against returning a bare "/".
		return $segments ? '/' . implode('/', $segments) : '';
	}

	public function rewrite($link) {
		$url_info = parse_url(str_replace('&amp;', '&', $link));

		$url = '';

		$data = array();

		parse_str($url_info['query'], $data);

		foreach ($data as $key => $value) {
			if (isset($data['route'])) {
				if (($data['route'] == 'product/product' && $key == 'product_id') || (($data['route'] == 'product/manufacturer/info' || $data['route'] == 'product/product') && $key == 'manufacturer_id') || ($data['route'] == 'information/information' && $key == 'information_id')) {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($key . '=' . (int)$value) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

					if ($query->num_rows && $query->row['keyword']) {
						$url .= '/' . $query->row['keyword'];

						unset($data[$key]);
					}
				} elseif ($key == 'path') {
					$categories = explode('_', $value);

					foreach ($categories as $category) {
						$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = 'category_id=" . (int)$category . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

						if ($query->num_rows && $query->row['keyword']) {
							$url .= '/' . $query->row['keyword'];
						} else {
							$url = '';

							break;
						}
					}

					unset($data[$key]);
				} elseif ($key == 'route') {
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE `query` = '" . $this->db->escape($data['route']) . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
					if ($query->num_rows) /**/ {
						$url .= '/' . $query->row['keyword'];
					}
				}
			}
		}

		if ($url) {
			$is_category_route = isset($data['route']) && $data['route'] == 'product/category';

			unset($data['route']);

			if ($is_category_route) {
				$url .= $this->rewriteFilterSegments($data);
			}

			$query = '';

			if ($data) {
				foreach ($data as $key => $value) {
					$query .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((is_array($value) ? http_build_query($value) : (string)$value));
				}

				if ($query) {
					$query = '?' . str_replace('&', '&amp;', trim($query, '&'));
				}
			}

			return $url_info['scheme'] . '://' . $url_info['host'] . (isset($url_info['port']) ? ':' . $url_info['port'] : '') . str_replace('/index.php', '', $url_info['path']) . $url . $query;
		} else {
			return $link;
		}
	}
}
