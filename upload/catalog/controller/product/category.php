<?php
class ControllerProductCategory extends Controller {
	/**
	 * Active fm/fa/fo/ff facets as [['name' => 'Цвет', 'value' => 'Белый'], ...],
	 * in the same order (manufacturer first, then fa < fo < ff by type_id
	 * ascending) that catalog/controller/startup/seo_url.php uses for path
	 * segments, so meta text and canonical URLs agree. Facets with no
	 * resolvable name/value (stale ids) are silently skipped. This is also
	 * what the canonical/noindex depth check counts - a manufacturer filter
	 * now counts toward that depth too, not just fa/fo/ff.
	 */
	private function buildActiveFacets($filter_data) {
		$this->load->model('journal3/filter');

		$active_facets = array();

		if (!empty($filter_data['manufacturers'])) {
			$manufacturer_names = array();

			foreach ($filter_data['manufacturers'] as $manufacturer_id) {
				$query = $this->db->query("SELECT `name` FROM " . DB_PREFIX . "manufacturer WHERE manufacturer_id = '" . (int)$manufacturer_id . "'");

				if ($query->num_rows) {
					$manufacturer_names[] = $query->row['name'];
				}
			}

			if ($manufacturer_names) {
				$active_facets[] = array('name' => $this->language->get('text_filter_manufacturer'), 'value' => implode(', ', $manufacturer_names));
			}
		}

		foreach (array('attributes' => 'fa', 'options' => 'fo', 'filters' => 'ff') as $filter_data_key => $type) {
			if (empty($filter_data[$filter_data_key])) {
				continue;
			}

			$facets = $filter_data[$filter_data_key];
			ksort($facets);

			foreach ($facets as $type_id => $values) {
				$facet_name = $this->model_journal3_filter->getFilterLabelName($type, $type_id);

				if ($facet_name === null) {
					continue;
				}

				$value_labels = array();

				foreach ($values as $value) {
					$label = $this->model_journal3_filter->getFilterValueLabel($type, $type_id, $value);

					if ($label !== null && $label !== '') {
						$value_labels[] = $label;
					}
				}

				if ($value_labels) {
					$active_facets[] = array('name' => $facet_name, 'value' => implode(', ', $value_labels));
				}
			}
		}

		return $active_facets;
	}

	public function index() {
		$this->load->language('product/category');

		$this->load->model('catalog/category');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		if (isset($this->request->get['filter'])) {
			$filter = $this->request->get['filter'];
		} else {
			$filter = '';
		}

		// Note: Sort persistence and personalized default are now handled by
		// the adaptive_filter event system (beforeProductListing)
		// The event runs before this controller and modifies $this->request->get['sort']

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];

			// Handle "Enable Smart Sorting" action
			if ($sort === 'enable-personalized') {
				// Load the adaptive filter model
				$this->load->model('extension/module/adaptive_filter');

				// Enable smart sorting for this user
				$this->model_extension_module_adaptive_filter->enableSmartSorting();

				// Redirect to personalized sort
				$redirect_url = str_replace('sort=enable-personalized', 'sort=personalized', $this->request->server['REQUEST_URI']);
				$this->response->redirect($redirect_url);
			}
		} else {
			$sort = 'p.sort_order';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = $sort == 'personalized' ? 'DESC' : 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($this->request->get['limit'])) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = $this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
		}

		$adaptive_filter_debug_mode = (bool)($this->config->get('module_adaptive_filter_debug_mode') ?? false);

		if ($sort === 'personalized' && $this->config->get('module_adaptive_filter_status')) {
			$this->load->model('extension/module/adaptive_filter');

			if (!$this->model_extension_module_adaptive_filter->isSmartSortingEnabled()) {
				$sort = 'p.sort_order';
				$order = 'ASC';
			}
		}

		$data['breadcrumbs'] = array();
		$data['disabled_product_notice'] = '';

		if (isset($this->session->data['disabled_product_redirect_notice'])) {
			$data['disabled_product_notice'] = $this->language->get('text_disabled_product_notice');

			unset($this->session->data['disabled_product_redirect_notice']);
		}

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		if (isset($this->request->get['path'])) {
			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$path = '';

			$parts = explode('_', (string)$this->request->get['path']);

			$category_id = (int)array_pop($parts);

			foreach ($parts as $path_id) {
				if (!$path) {
					$path = (int)$path_id;
				} else {
					$path .= '_' . (int)$path_id;
				}

				$category_info = $this->model_catalog_category->getCategory($path_id);

				if ($category_info) {
					$data['breadcrumbs'][] = array(
						'text' => $category_info['name'],
						'href' => $this->url->link('product/category', 'path=' . $path . $url)
					);
				}
			}
		} else {
			$category_id = 0;
		}

		$category_info = $this->model_catalog_category->getCategory($category_id);

		if ($category_info) {
			$this->document->setTitle($category_info['meta_title']);
			$this->document->setDescription($category_info['meta_description']);
			$this->document->setKeywords($category_info['meta_keyword']);

			$this->load->model('journal3/filter');
			$active_filter_data = $this->model_journal3_filter->getFilterData();
			$active_facets = $this->buildActiveFacets($active_filter_data);

			if ($active_facets) {
				$active_facets_text = array();

				foreach ($active_facets as $facet) {
					$active_facets_text[] = $facet['name'] . ': ' . $facet['value'];
				}

				$this->document->setTitle($category_info['name'] . ' — ' . implode(', ', $active_facets_text));
				$this->document->setDescription(trim($category_info['meta_description'] . ' ' . sprintf($this->language->get('text_filter_meta_suffix'), implode(', ', $active_facets_text))));
			}

			$data['heading_title'] = $category_info['name'];

			$data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));

			// Set the last category breadcrumb
			$data['breadcrumbs'][] = array(
				'text' => $category_info['name'],
				'href' => $this->url->link('product/category', 'path=' . $this->request->get['path'])
			);

			if ($category_info['image']) {
				$data['thumb'] = $this->model_tool_image->resize($category_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_height'));
			} else {
				$data['thumb'] = '';
			}

			$data['description'] = html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8');
			$data['compare'] = $this->url->link('product/compare');

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['categories'] = array();

			$results = $this->model_catalog_category->getCategories($category_id);

			foreach ($results as $result) {
				$filter_data = array(
					'filter_category_id'  => $result['category_id'],
					'filter_sub_category' => true
				);

				$data['categories'][] = array(
					'name' => $result['name'] . ($this->config->get('config_product_count') ? ' (' . $this->model_catalog_product->getTotalProducts($filter_data) . ')' : ''),
					'href' => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '_' . $result['category_id'] . $url)
				);
			}

			$data['products'] = array();

			$filter_data = array(
				'filter_category_id' => $category_id,
				'filter_filter'      => $filter,
				'sort'               => $sort,
				'order'              => $order,
				'start'              => ($page - 1) * $limit,
				'limit'              => $limit
			);

			// Use personalized sorting if requested
			if ($sort == 'personalized' && $this->config->get('module_adaptive_filter_status')) {
				$this->load->model('extension/module/adaptive_filter');
				$results = $this->model_extension_module_adaptive_filter->getPersonalizedProducts($filter_data, $limit, ($page - 1) * $limit);
				$product_total = $this->model_extension_module_adaptive_filter->getPersonalizedProductsTotal();
			} else {
				$results = $this->model_catalog_product->getProducts($filter_data);
				$product_total = $this->model_catalog_product->getTotalProducts($filter_data);
			}

			foreach ($results as $result) {
				if ($result['image']) {
					$image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$price = false;
				}

				if ((float)$result['special']) {
					$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$special = false;
				}

				// Max Surdu modifications - add retail_price formatting
				if (isset($result['retail_price']) && (float)$result['retail_price'] && ($this->customer->isLogged() || !$this->config->get('config_customer_price'))) {
					$retail_price = $this->currency->format($this->tax->calculate($result['retail_price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
				} else {
					$retail_price = false;
				}
				// End of Max Surdu modifications

				if ($this->config->get('config_tax')) {
					$tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
				} else {
					$tax = false;
				}

				if ($this->config->get('config_review_status')) {
					$rating = (int)$result['rating'];
				} else {
					$rating = false;
				}

					$data['products'][] = array(
						'product_id'  => $result['product_id'],
						'thumb'       => $image,
						'name'        => $result['name'],
						'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
						'price'       => $price,
						'special'     => $special,
						'retail_price' => $retail_price,
						'tax'         => $tax,
						'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
						'rating'      => $result['rating'],
						'adaptive_filter_debug_mode' => $adaptive_filter_debug_mode,
						'personalization_score' => $adaptive_filter_debug_mode && $sort == 'personalized' ? (float)($result['personalization_score'] ?? 0) : null,
						'href'        => $this->url->link('product/product', 'path=' . $this->request->get['path'] . '&product_id=' . $result['product_id'] . $url)
					);
				}

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['sorts'] = array();

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_default'),
				'value' => 'p.sort_order-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.sort_order&order=ASC' . $url)
			);

			// Add Smart Sorting option if adaptive filter is enabled
			if ($this->config->get('module_adaptive_filter_status')) {
				$this->load->language('extension/module/adaptive_filter');
				$this->load->model('extension/module/adaptive_filter');

				$is_enabled = $this->model_extension_module_adaptive_filter->isSmartSortingEnabled();

				if ($is_enabled) {
					// Smart Sorting is enabled - show regular option
					$data['sorts'][] = array(
						'text'  => $this->language->get('text_sort_personalized'),
						'value' => 'personalized-DESC',
						'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=personalized&order=DESC' . $url)
					);
				} else {
					// Smart Sorting is disabled - show "Enable Smart Sorting" (handled by JavaScript)
					$data['sorts'][] = array(
						'text'  => $this->language->get('text_sort_enable_personalized'),
						'value' => 'enable-personalized-DESC',
						'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=enable-personalized&order=DESC' . $url)
					);
				}
			}

			// Add newest products sorting
			$data['sorts'][] = array(
				'text'  => $this->language->get('text_newest_first'),
				'value' => 'p.date_added-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.date_added&order=DESC' . $url)
			);

			// Add bestseller sorting (by number of sales)
			$data['sorts'][] = array(
				'text'  => $this->language->get('text_bessteller_first'),
				'value' => 'sales-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=sales&order=DESC' . $url)
			);

			// Add in-stock first sorting
			$data['sorts'][] = array(
				'text'  => $this->language->get('text_instock_first'),
				'value' => 'p.quantity-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.quantity&order=DESC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_asc'),
				'value' => 'pd.name-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=pd.name&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_name_desc'),
				'value' => 'pd.name-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=pd.name&order=DESC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_asc'),
				'value' => 'p.price-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.price&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_price_desc'),
				'value' => 'p.price-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.price&order=DESC' . $url)
			);

			if ($this->config->get('config_review_status')) {
				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_desc'),
					'value' => 'rating-DESC',
					'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=rating&order=DESC' . $url)
				);

				$data['sorts'][] = array(
					'text'  => $this->language->get('text_rating_asc'),
					'value' => 'rating-ASC',
					'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=rating&order=ASC' . $url)
				);
			}

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_asc'),
				'value' => 'p.model-ASC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.model&order=ASC' . $url)
			);

			$data['sorts'][] = array(
				'text'  => $this->language->get('text_model_desc'),
				'value' => 'p.model-DESC',
				'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . '&sort=p.model&order=DESC' . $url)
			);

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			$data['limits'] = array();

			$limits = array_unique(array($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit'), 25, 50, 75, 100));

			sort($limits);

			foreach($limits as $value) {
				$data['limits'][] = array(
					'text'  => $value,
					'value' => $value,
					'href'  => $this->url->link('product/category', 'path=' . $this->request->get['path'] . $url . '&limit=' . $value)
				);
			}

			$url = '';

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$pagination = new Pagination();
			$pagination->total = $product_total;
			$pagination->page = $page;
			$pagination->limit = $limit;
			$pagination->url = $this->url->link('product/category', 'path=' . $this->request->get['path'] . $url . '&page={page}');

			$data['pagination'] = $pagination->render();

			$data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

			// http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html
			//
			// Filter-aware canonical/indexing policy: a single active facet
			// (e.g. just a colour) is a real, worthwhile "landing page" and
			// gets its own canonical + stays indexable; two or more active
			// facets (or any combination beyond the configured depth)
			// canonicalize back to the bare category and get noindex,follow
			// to avoid indexing a combinatorial explosion of thin/duplicate
			// filtered pages, while still passing link equity through.
			$filter_depth = count($active_facets);
			$filter_seo_indexable_depth = (int)($this->config->get('filterSeoIndexableDepth') ?? 1);

			if ($filter_depth > 0 && $filter_depth <= $filter_seo_indexable_depth) {
				$this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . '&' . $this->model_journal3_filter->getFilterParams($active_filter_data)), 'canonical');
			} elseif ($page == 1) {
			    $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id']), 'canonical');
			} else {
				$this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . '&page='. $page), 'canonical');
			}

			if ($filter_depth > $filter_seo_indexable_depth) {
				$this->journal3_document->addMeta('robots', 'noindex, follow');
			}
			
			if ($page > 1) {
			    $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . (($page - 2) ? '&page='. ($page - 1) : '')), 'prev');
			}

			if ($limit && ceil($product_total / $limit) > $page) {
			    $this->document->addLink($this->url->link('product/category', 'path=' . $category_info['category_id'] . '&page='. ($page + 1)), 'next');
			}

			$data['sort'] = $sort;
			$data['order'] = $order;
			$data['limit'] = $limit;

			$data['continue'] = $this->url->link('common/home');

			// Render adaptive filter widgets
			if ($this->config->get('module_adaptive_filter_status')) {
				$data['adaptive_filter_preferences'] = $this->load->controller('extension/module/adaptive_filter/renderPreferencesWidget');
				$data['adaptive_filter_assets'] = $this->load->controller('extension/module/adaptive_filter/renderAssets');
			} else {
				$data['adaptive_filter_preferences'] = '';
				$data['adaptive_filter_assets'] = '';
			}

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('product/category', $data));
		} else {
			$url = '';

			if (isset($this->request->get['path'])) {
				$url .= '&path=' . $this->request->get['path'];
			}

			if (isset($this->request->get['filter'])) {
				$url .= '&filter=' . $this->request->get['filter'];
			}

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			if (isset($this->request->get['limit'])) {
				$url .= '&limit=' . $this->request->get['limit'];
			}

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_error'),
				'href' => $this->url->link('product/category', $url)
			);

			$this->document->setTitle($this->language->get('text_error'));

			$data['continue'] = $this->url->link('common/home');

			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}
}
