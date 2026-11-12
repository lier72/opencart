<?php
/**
 * Catalog (storefront) controller for the Racket Finder.
 *
 * The finder is a self-contained JS widget. It scores rackets in the browser
 * from a STATIC character-data file shipped with the module
 * (catalog/view/javascript/racket_finder/finder-data.json: measurements,
 * technologies, scores, classes, map positions — everything that does not
 * change between catalogue updates). Price, stock, image and product URL are
 * read LIVE from the store by data() below and merged into the widget by
 * product id + weight, so the page never shows a stale price.
 *
 * Methods:
 *  - page():   full storefront page (header/footer) that hosts the widget.
 *              Route: extension/module/racket_finder.page  ->  /podbor-raketki
 *  - data():   JSON of live price/stock/image/href for every racket product,
 *              cached on disk for the configured number of hours.
 *              Route: extension/module/racket_finder.data (fetched by the widget)
 *  - index($setting): small render used when the module is placed in a Journal3
 *              layout position (e.g. a button over the rackets category filter).
 *              Renders a button that opens the finder page.
 */
class ControllerExtensionModuleRacketFinder extends Controller {

	/** Absolute path of the shipped static character-data file. */
	private function dataFile() {
		return DIR_APPLICATION . '../catalog/view/javascript/racket_finder/finder-data.json';
	}

	/**
	 * Read settings from the first enabled racket_finder module instance.
	 * Returns array with category_id and cache_hours (defaults applied).
	 * Used by page()/data() so both share one configuration.
	 */
	private function settings() {
		$out = array('category_id' => 0, 'cache_hours' => 1);

		// The catalog setting/module model only fetches by id, so read the
		// module table directly and take the first enabled racket_finder row.
		$q = $this->db->query("SELECT `setting` FROM `" . DB_PREFIX . "module` WHERE `code` = 'racket_finder'");
		foreach ($q->rows as $row) {
			$s = json_decode($row['setting'], true);
			if (!empty($s['status'])) {
				$out['category_id'] = isset($s['category_id']) ? (int)$s['category_id'] : 0;
				$out['cache_hours'] = isset($s['cache_hours']) ? (int)$s['cache_hours'] : 1;
				break;
			}
		}

		return $out;
	}

	/**
	 * Full storefront page that hosts the finder widget.
	 * Loads the stylesheet and widget script, points the widget at the static
	 * data file and the live-data endpoint, and passes the storefront language
	 * (ru/en) plus the cart/product URLs the widget needs for links and
	 * "add to cart".
	 */
	public function page() {
		$this->load->language('extension/module/racket_finder');

		$this->document->setTitle($this->language->get('page_title'));
		$this->document->setDescription($this->language->get('page_meta_description'));

		$this->document->addStyle('catalog/view/javascript/racket_finder/widget.css');

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/racket_finder.page')
		);

		$data['heading_title'] = $this->language->get('heading_title');

		// Storefront language: 'en' for the English store, otherwise 'ru'.
		$lang_code = $this->config->get('config_language');
		$data['finder_lang'] = (strpos($lang_code, 'en') === 0) ? 'en' : 'ru';

		// URLs the widget uses at runtime.
		$data['data_url']    = $this->url->link('extension/module/racket_finder.data', '', true);
		$data['cart_url']    = $this->url->link('checkout/cart/add', '', true);
		$data['version']     = $this->dataVersion();
		$data['store_base']  = HTTP_SERVER;

		// Inline the live price/stock map so the widget renders without an extra
		// request. The data() endpoint stays for popup/category placements.
		$data['finder_live'] = json_encode($this->liveData(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

		$data['column_left']    = $this->load->controller('common/column_left');
		$data['column_right']   = $this->load->controller('common/column_right');
		$data['content_top']    = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer']         = $this->load->controller('common/footer');
		$data['header']         = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/racket_finder', $data));
	}

	/**
	 * Live price/stock/image/href for every racket, as JSON keyed by product id.
	 * Shape: { "<product_id>": {p, special, q, href, thumb, name} , ... }
	 *   p       - price the customer pays now (special if active), integer, store currency
	 *   special - 1 when a special price is active, else 0
	 *   q       - 1 in stock, 0 out of stock
	 *   href    - product page URL
	 *   thumb   - resized image URL
	 * The id list comes from the shipped static file (the exact finder set), so
	 * live data and scores always line up. Result cached on disk for the
	 * configured hours; the admin controller deletes the cache file on save,
	 * and any product save should too (see install note / OCMOD).
	 */
	public function data() {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');

		$conf = $this->settings();
		$cache_file = DIR_CACHE . 'racket_finder.json';
		$ttl = max(0, $conf['cache_hours']) * 3600;

		if ($ttl > 0 && is_file($cache_file) && (filemtime($cache_file) + $ttl) > time()) {
			$this->response->setOutput(file_get_contents($cache_file));
			return;
		}

		$json = json_encode($this->liveData(), JSON_UNESCAPED_UNICODE);

		if ($ttl > 0) {
			@file_put_contents($cache_file, $json);
		}

		$this->response->setOutput($json);
	}

	/**
	 * Build the live price/stock/image/href map for every racket product.
	 * Shared by data() (AJAX/cache) and page() (inlined). Returns an array
	 * keyed by product id as described on data(). Not cached here — callers
	 * decide on caching.
	 */
	private function liveData() {
		$ids = $this->racketIds();

		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$out = array();
		foreach ($ids as $product_id) {
			$info = $this->model_catalog_product->getProduct($product_id);
			if (!$info) {
				continue; // disabled or deleted: the widget marks it "not in store"
			}

			// Price the customer pays (special overrides price). Integer, store currency.
			$base = (float)$info['price'];
			$special = ((float)$info['special']) ? (float)$info['special'] : 0;
			$pay = $special ? $special : $base;

			$in_stock = (!$info['subtract'] || (int)$info['quantity'] > 0) ? 1 : 0;

			$thumb = $info['image']
				? $this->model_tool_image->resize($info['image'], 240, 240)
				: '';

			$out[(string)$product_id] = array(
				'p'       => (int)round($pay),
				'special' => $special ? 1 : 0,
				'q'       => $in_stock,
				'href'    => $this->url->link('product/product', 'product_id=' . $product_id),
				'thumb'   => $thumb,
				'name'    => $info['name']
			);
		}

		return $out;
	}

	/**
	 * Module render for a Journal3/layout position: a button that opens the
	 * finder page. Used to place "Find a racket" over the rackets category
	 * filter. $setting is the layout module setting (unused for now).
	 */
	public function index($setting) {
		$this->load->language('extension/module/racket_finder');

		$data['heading_title'] = $this->language->get('heading_title');
		$data['button_text']   = $this->language->get('button_open');
		$data['page_href']     = $this->url->link('extension/module/racket_finder.page');

		return $this->load->view('extension/module/racket_finder_button', $data);
	}

	/**
	 * Collect the racket product ids from the shipped static file (items whose
	 * "site" flag is set). Falls back to an empty list if the file is missing.
	 */
	private function racketIds() {
		$file = $this->dataFile();
		if (!is_file($file)) {
			return array();
		}
		$json = json_decode(file_get_contents($file), true);
		$ids = array();
		if (!empty($json['items'])) {
			foreach ($json['items'] as $it) {
				if (!empty($it['site']) && !empty($it['id'])) {
					$ids[(int)$it['id']] = true;
				}
			}
		}
		return array_keys($ids);
	}

	/** Cache-busting token for the static data file (its mtime). */
	private function dataVersion() {
		$file = $this->dataFile();
		return is_file($file) ? (string)filemtime($file) : '0';
	}
}
