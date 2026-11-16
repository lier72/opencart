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
 *              Route: extension/module/racket_finder/page  (OpenCart 3 separates
 *              the controller method with a slash, not a dot — a dot is stripped
 *              by system/engine/action.php and the route 404s)
 *  - data():   JSON of live price/stock/image/href for every racket product,
 *              cached on disk for the configured number of hours.
 *              Route: extension/module/racket_finder/data (fetched by the widget)
 *  - index($setting): small render used when the module is placed in a Journal3
 *              layout position (e.g. a button over the rackets category filter).
 *              Renders a button that opens the finder page.
 */
class ControllerExtensionModuleRacketFinder extends Controller {

	/** Guard so the fatal-error shutdown handler is registered only once. */
	private static $shutdownRegistered = false;

	/** Thumbnail size the widget cards use. */
	const THUMB_W = 240;
	const THUMB_H = 240;

	/** Skip generating a thumbnail when the source image is larger than this
	 *  many pixels: loading a huge source into GD is what exhausts the PHP
	 *  memory limit (system/library/image.php). Such products keep the original. */
	const MAX_SOURCE_PX = 12000000;

	/** Most thumbnails to generate in a single request; the rest fall back to the
	 *  original image and are generated on later requests. */
	const GEN_BUDGET = 25;

	/** Set by thumb() when a thumbnail was deferred to a later request, so the
	 *  live-data cache is not frozen with fallback (original-size) image URLs. */
	private $thumbsPending = false;

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
		$out = array('category_id' => 0, 'cache_hours' => 1, 'debug' => 0);

		// The catalog setting/module model only fetches by id, so read the
		// module table directly and take the first enabled racket_finder row.
		$q = $this->db->query("SELECT `setting` FROM `" . DB_PREFIX . "module` WHERE `code` = 'racket_finder'");
		foreach ($q->rows as $row) {
			$s = json_decode($row['setting'], true);
			if (!empty($s['status'])) {
				$out['category_id'] = isset($s['category_id']) ? (int)$s['category_id'] : 0;
				$out['cache_hours'] = isset($s['cache_hours']) ? (int)$s['cache_hours'] : 1;
				$out['debug']       = !empty($s['debug']) ? 1 : 0;
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
		$conf = $this->settings();
		$this->bootLogging($conf['debug']);

		try {

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
			'href' => $this->url->link('extension/module/racket_finder/page')
		);

		$data['heading_title'] = $this->language->get('heading_title');

		// Storefront language: 'en' for the English store, otherwise 'ru'.
		$lang_code = $this->config->get('config_language');
		$data['finder_lang'] = (strpos($lang_code, 'en') === 0) ? 'en' : 'ru';

		// URLs the widget uses at runtime.
		$data['data_url']    = $this->url->link('extension/module/racket_finder/data', '', true);
		$data['cart_url']    = $this->url->link('checkout/cart/add', '', true);
		$data['version']     = $this->dataVersion();
		$data['store_base']  = HTTP_SERVER;

		// Inline the live price/stock map so the widget renders without an extra
		// request. The data() endpoint stays for popup/category placements.
		$data['finder_live'] = json_encode($this->liveDataCached($conf['cache_hours']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

		$data['column_left']    = $this->load->controller('common/column_left');
		$data['column_right']   = $this->load->controller('common/column_right');
		$data['content_top']    = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer']         = $this->load->controller('common/footer');
		$data['header']         = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/racket_finder', $data));

		} catch (\Throwable $e) {
			$this->handleError($e, $conf['debug'], false);
		}
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
		$conf = $this->settings();
		$this->bootLogging($conf['debug']);

		try {

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->setOutput(json_encode($this->liveDataCached($conf['cache_hours']), JSON_UNESCAPED_UNICODE));

		} catch (\Throwable $e) {
			$this->handleError($e, $conf['debug'], true);
		}
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

		// Generating a thumbnail loads the full-size source into GD, so a page
		// that builds ~200 of them at once can exhaust the PHP memory limit (see
		// thumb()). Give the generate path some headroom and a per-request
		// budget; already-cached thumbnails cost nothing.
		@ini_set('memory_limit', '512M');
		$budget = self::GEN_BUDGET;

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

			$thumb = $info['image'] ? $this->thumb($info['image'], $budget) : '';

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
	 * liveData() wrapped in the on-disk cache (DIR_CACHE/racket_finder.json).
	 * Used by both page() and data() so the price/stock/thumbnail map is built at
	 * most once per cache_hours instead of on every page load (the per-load
	 * rebuild, with its thumbnail generation, was what ran out of memory). The
	 * map is cached only when every thumbnail was resolved; if thumb() deferred
	 * any generation ($thumbsPending), the partial map is still returned but not
	 * cached, so the rest are generated on later requests.
	 */
	private function liveDataCached($cache_hours) {
		$cache_file = DIR_CACHE . 'racket_finder.json';
		$ttl = max(0, (int)$cache_hours) * 3600;

		if ($ttl > 0 && is_file($cache_file) && (filemtime($cache_file) + $ttl) > time()) {
			$cached = json_decode(file_get_contents($cache_file), true);
			if (is_array($cached)) {
				return $cached;
			}
		}

		$this->thumbsPending = false;
		$map = $this->liveData();

		if ($ttl > 0 && !$this->thumbsPending) {
			@file_put_contents($cache_file, json_encode($map, JSON_UNESCAPED_UNICODE));
		}

		return $map;
	}

	/**
	 * Resolve the card thumbnail URL for a product image, cheaply.
	 *
	 * When the resized thumbnail already exists in image/cache its URL is built
	 * without touching GD. When it does not, generating it loads the full-size
	 * source into memory (system/library/image.php line 47), which is what
	 * exhausts the PHP memory limit when a page builds many at once — so
	 * generation is guarded: a source larger than MAX_SOURCE_PX is left at
	 * original size, and at most GEN_BUDGET thumbnails are generated per request
	 * ($budget, by reference). Anything not generated falls back to the original
	 * image URL this time and is generated on a later request. Call only from
	 * liveData(), where tool/image is loaded.
	 */
	private function thumb($filename, &$budget) {
		if (!is_file(DIR_IMAGE . $filename)) {
			return '';
		}

		$dot = strrpos($filename, '.');
		if ($dot === false) {
			return $this->imageUrl($filename);
		}

		$ext    = strtolower(substr($filename, $dot + 1));
		$cached = 'cache/' . substr($filename, 0, $dot) . '-' . (int)self::THUMB_W . 'x' . (int)self::THUMB_H . '.' . $ext;

		// Thumbnail already built and not older than the source: cheap URL only.
		if (is_file(DIR_IMAGE . $cached) && filemtime(DIR_IMAGE . $filename) <= filemtime(DIR_IMAGE . $cached)) {
			return $this->model_tool_image->resize($filename, self::THUMB_W, self::THUMB_H);
		}

		$info = @getimagesize(DIR_IMAGE . $filename); // reads the header only, cheap
		$px   = ($info && isset($info[0], $info[1])) ? ($info[0] * $info[1]) : PHP_INT_MAX;

		if ($px <= self::MAX_SOURCE_PX && $budget > 0) {
			$budget--;
			return $this->model_tool_image->resize($filename, self::THUMB_W, self::THUMB_H);
		}

		if ($budget <= 0) {
			$this->thumbsPending = true; // only the per-request budget cap is temporary
		}

		return $this->imageUrl($filename);
	}

	/**
	 * URL of an original (un-resized) image under image/, built the same way as
	 * model/tool/image so links stay consistent. Thumbnail fallback.
	 */
	private function imageUrl($filename) {
		$file = str_replace(' ', '%20', $filename);
		$base = !empty($this->request->server['HTTPS']) ? $this->config->get('config_ssl') : $this->config->get('config_url');

		return $base . 'image/' . $file;
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
		$data['page_href']     = $this->url->link('extension/module/racket_finder/page');

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

	/**
	 * Prepare error capture for page()/data(). Registers (once per request) a
	 * shutdown handler that records PHP fatal errors the try/catch in page()/
	 * data() cannot catch — out-of-memory, exceeded max_execution_time, and
	 * other E_ERROR-class failures — to system/storage/logs/racket_finder.log.
	 * When $debug is on it also turns on display_errors and appends the fatal to
	 * the response, because those fatals never reach handleError(). Call at the
	 * very start of any public entry point, before the work that may fail.
	 */
	private function bootLogging($debug) {
		if ($debug) {
			@ini_set('display_errors', '1');
			error_reporting(E_ALL);
		}

		if (self::$shutdownRegistered) {
			return;
		}
		self::$shutdownRegistered = true;

		register_shutdown_function(function () use ($debug) {
			$e = error_get_last();
			$fatal = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);

			if ($e && in_array($e['type'], $fatal, true)) {
				$line = 'PHP Fatal: ' . $e['message'] . ' in ' . $e['file'] . ' on line ' . $e['line'];
				@file_put_contents(DIR_LOGS . 'racket_finder.log', date('Y-m-d H:i:s') . ' - ' . $line . "\n", FILE_APPEND);

				if ($debug) {
					echo "\n\nRacket Finder fatal error\n\n" . $line . "\n";
				}
			}
		});
	}

	/**
	 * Handle a Throwable caught in page()/data(): always write the class,
	 * message, file:line and stack trace to system/storage/logs/racket_finder.log.
	 * With the module's Debug option OFF the error is then re-thrown, so the
	 * storefront behaves exactly as before (OpenCart renders its error page).
	 * With Debug ON the error text is sent to the browser instead of a blank
	 * 500 — plain text for page(), JSON {"error": ...} for the data endpoint —
	 * so the cause is visible without shell access to the container.
	 * $json selects the response format; pass true only from data().
	 */
	private function handleError($e, $debug, $json) {
		$detail = get_class($e) . ': ' . $e->getMessage()
			. ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString();

		$log = new Log('racket_finder.log');
		$log->write($detail);

		if (empty($debug)) {
			throw $e;
		}

		if ($json) {
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode(array('error' => $detail), JSON_UNESCAPED_UNICODE));
		} else {
			$this->response->addHeader('Content-Type: text/plain; charset=utf-8');
			$this->response->setOutput("Racket Finder error\n\n" . $detail);
		}
	}
}
