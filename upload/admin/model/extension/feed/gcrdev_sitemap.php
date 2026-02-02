<?php
class ModelExtensionFeedGcrdevSitemap extends Model {

	private function createIndexStyle($title, $store_id, $group) {
		$sitemap_index_file = '../sitemap/' . $store_id . '/' . $group . '/xsl/sitemap-index.xsl';
		$sitemap_index_handle = fopen($sitemap_index_file, 'w') or die('Cannot open file: ' . $sitemap_index_file);

		$decompress = file_get_contents('../sitemap/xsl/sitemap-index/1.xsl');
		$decompress .= '<title>' . $title . '</title>';
		$decompress .= file_get_contents('../sitemap/xsl/sitemap-index/2.xsl');
		$decompress .= '<h1>' . $title . '</h1>';
		$decompress .= file_get_contents('../sitemap/xsl/sitemap-index/3.xsl');
		$decompress .= '<a href="/sitemap/' . $store_id . '/sitemap-index.xml">sitemap index</a></p>';
		$decompress .= file_get_contents('../sitemap/xsl/sitemap-index/4.xsl');
		fwrite($sitemap_index_handle, $decompress);
	}

	private function createStyle($title, $store_id, $group) {
		$sitemap_index_file = '../sitemap/' . $store_id . '/' . $group . '/xsl/sitemap.xsl';
		$sitemap_index_handle = fopen($sitemap_index_file, 'w') or die('Cannot open file: ' . $sitemap_index_file);

		$decompress = file_get_contents('../sitemap/xsl/map-style/1.xsl');
		$decompress .= '<title>' . $title . '</title>';
		$decompress .= file_get_contents('../sitemap/xsl/map-style/2.xsl');
		$decompress .= '<h1>' . $title . '</h1>';
		$decompress .= file_get_contents('../sitemap/xsl/map-style/3.xsl');
		$decompress .= '<a href="/sitemap/' . $store_id . '/sitemap-index.xml">sitemap index</a></p>
<a href="/sitemap/' . $store_id . '/' . $group . '/sitemap-index.xml">' . $group . ' index</a>';
		$decompress .= file_get_contents('../sitemap/xsl/map-style/4.xsl');
		fwrite($sitemap_index_handle, $decompress);
	}

	private function removeFiles($removeFiles) {
		$files = glob($removeFiles);
		foreach ($files as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
	}

	private function removeDir($dir) {
		if (is_dir($dir)) {
			rmdir($dir);
		}
	}

	private function recurse_copy($src, $dst) {
		$dir = opendir($src);
		@mkdir($dst);
		while (false !== ($file = readdir($dir))) {
			if (($file != '.') && ($file != '..') && ($file != 'backup')) {
				if (is_dir($src . '/' . $file)) {
					$this->recurse_copy($src . '/' . $file, $dst . '/' . $file);
				} else {
					copy($src . '/' . $file, $dst . '/' . $file);
				}
			}
		}
		closedir($dir);
	}

	private function make_dir($src) {
		if (!file_exists($src)) {
			mkdir($src, 0777, true);
		}
	}

	private function delete_dir($path) {
		if (is_dir($path) === true) {
			$files = array_diff(scandir($path), array('.', '..'));
			foreach ($files as $file) {
				$this->delete_dir(realpath($path) . '/' . $file);
			}
			return rmdir($path);
		} elseif (is_file($path) === true) {
			return unlink($path);
		}
	}

	private function getStoreId() {
		$query = $this->db->query("SELECT DISTINCT store_id FROM " . DB_PREFIX . "setting");
		return $query->rows;
	}

	private function generateIndex() {
		$sitemapIndex = '../sitemap/sitemap-index.xml';
		$sitemapIndexHandle = fopen($sitemapIndex, 'w') or die('Cannot open file: ' . $sitemapIndex);
		$storedata = '<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="/sitemap/xsl/stores/sitemap-index.xsl"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

		$produpdate = '';
		$query = $this->db->query("SELECT last_update FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='products'");
		$config = $query->rows;
		foreach ($config as $cfg) {
			$produpdate .= date('Y-m-d', strtotime($cfg['last_update']));
		}

		$catupdate = '';
		$query = $this->db->query("SELECT last_update FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='categories'");
		$config = $query->rows;
		foreach ($config as $cfg) {
			$catupdate .= date('Y-m-d', strtotime($cfg['last_update']));
		}

		$brandupdate = '';
		$query = $this->db->query("SELECT last_update FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='brands'");
		$config = $query->rows;
		foreach ($config as $cfg) {
			$brandupdate .= date('Y-m-d', strtotime($cfg['last_update']));
		}

		$infoupdate = '';
		$query = $this->db->query("SELECT last_update FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='information'");
		$config = $query->rows;
		foreach ($config as $cfg) {
			$infoupdate .= date('Y-m-d', strtotime($cfg['last_update']));
		}

		$pageupdate = '';
		$query = $this->db->query("SELECT last_update FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='pages'");
		$config = $query->rows;
		foreach ($config as $cfg) {
			$pageupdate .= date('Y-m-d', strtotime($cfg['last_update']));
		}

		$stores = $this->getStoreId();
		foreach ($stores as $store) {
			$store_id = $store['store_id'];
			$query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE `store_id`='" . (int)$store_id . "' AND `key`='config_url'");
			$config_url = $query->rows;
			$store_url = '';
			foreach ($config_url as $url) {
				$store_url .= $url['value'];
			}

			$storeurl = ($store_url == '') ? HTTP_CATALOG : $store_url;
			$file = '../sitemap/' . $store_id . '/sitemap-index.xml';
			$handle = fopen($file, 'w') or die('Cannot open file: ' . $file);
			$storedata .= '<sitemap><loc>' . $storeurl . 'sitemap/' . $store_id . '/sitemap-index.xml</loc><lastmod>' . date('Y-m-d') . '</lastmod></sitemap>';

			$data = '<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="' . $storeurl . 'sitemap/xsl/sitemap-index.xsl"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
			if ($produpdate != '1970-01-01') {
				$data .= '<sitemap><loc>' . $storeurl . 'sitemap/' . $store_id . '/products/sitemap-index.xml</loc><lastmod>' . $produpdate . '</lastmod></sitemap>';
			}
			if ($catupdate != '1970-01-01') {
				$data .= '<sitemap><loc>' . $storeurl . 'sitemap/' . $store_id . '/categories/sitemap-index.xml</loc><lastmod>' . $catupdate . '</lastmod></sitemap>';
			}
			if ($brandupdate != '1970-01-01') {
				$data .= '<sitemap><loc>' . $storeurl . 'sitemap/' . $store_id . '/brands/sitemap-index.xml</loc><lastmod>' . $brandupdate . '</lastmod></sitemap>';
			}
			if ($infoupdate != '1970-01-01') {
				$data .= '<sitemap><loc>' . $storeurl . 'sitemap/' . $store_id . '/information/sitemap-index.xml</loc><lastmod>' . $infoupdate . '</lastmod></sitemap>';
			}
			if ($pageupdate != '1970-01-01') {
				$data .= '<sitemap><loc>' . $storeurl . 'sitemap/' . $store_id . '/pages/sitemap.xml</loc><lastmod>' . $pageupdate . '</lastmod></sitemap>';
			}
			$data .= '</sitemapindex>';
			fwrite($handle, $data);
		}
		$storedata .= '</sitemapindex>';
		fwrite($sitemapIndexHandle, $storedata);
	}

	public function install() {
		@$this->db->query("
CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "gcrdev_sitemap`(
`id` INT(11) NOT NULL AUTO_INCREMENT,
`groups` varchar(32) NOT NULL,
`changefreq` varchar(64) NOT NULL,
`prio` DECIMAL(2,1) NOT NULL,
`indlim` INT(15) NOT NULL,
`prodstyle` tinyint(1) NOT NULL,
`lastid` INT(126) NOT NULL,
`status` tinyint(1) NOT NULL,
`last_update` datetime NOT NULL,
PRIMARY KEY (`id`)
)ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
");
		// Check if data already exists
		$query = $this->db->query("SELECT COUNT(*) as cnt FROM " . DB_PREFIX . "gcrdev_sitemap");
		if ($query->row['cnt'] == 0) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "gcrdev_sitemap SET `groups`='products',`changefreq`='never',`prio`='1.0',`indlim`='1000',`prodstyle`='1',`status`='1'");
			$this->db->query("INSERT INTO " . DB_PREFIX . "gcrdev_sitemap SET `groups`='categories',`changefreq`='never',`prio`='0.9',`indlim`='1000',`prodstyle`='1',`status`='1'");
			$this->db->query("INSERT INTO " . DB_PREFIX . "gcrdev_sitemap SET `groups`='brands',`changefreq`='never',`prio`='0.8',`indlim`='1000',`prodstyle`='1',`status`='1'");
			$this->db->query("INSERT INTO " . DB_PREFIX . "gcrdev_sitemap SET `groups`='information',`changefreq`='never',`prio`='0.7',`indlim`='1000',`prodstyle`='1'");
			$this->db->query("INSERT INTO " . DB_PREFIX . "gcrdev_sitemap SET `groups`='pages',`changefreq`='never',`prio`='0.6',`indlim`='1000',`prodstyle`='1',`status`='1'");
		}
		$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET code='feed_gcrdev_sitemap',`key`='feed_gcrdev_sitemap_status',`value`='1' ON DUPLICATE KEY UPDATE `value`='1'");

		$stores = $this->getStoreId();
		foreach ($stores as $store) {
			$store_id = $store['store_id'];
			if (!file_exists('../sitemap/' . $store_id . '/brands/xsl')) {
				mkdir('../sitemap/' . $store_id . '/brands/xsl', 0777, true);
			}
			if (!file_exists('../sitemap/' . $store_id . '/categories/xsl')) {
				mkdir('../sitemap/' . $store_id . '/categories/xsl', 0777, true);
			}
			if (!file_exists('../sitemap/' . $store_id . '/information/xsl')) {
				mkdir('../sitemap/' . $store_id . '/information/xsl', 0777, true);
			}
			if (!file_exists('../sitemap/' . $store_id . '/pages/xsl')) {
				mkdir('../sitemap/' . $store_id . '/pages/xsl', 0777, true);
			}
			if (!file_exists('../sitemap/' . $store_id . '/products/xsl')) {
				mkdir('../sitemap/' . $store_id . '/products/xsl', 0777, true);
			}
		}
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "gcrdev_sitemap`");
	}

	public function getData() {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "gcrdev_sitemap");
		return $query->rows;
	}

	public function getProdSet() {
		$query = $this->db->query("SELECT status FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='products'");
		$row = $query->rows;
		$check = '';
		foreach ($row as $r) {
			$check .= ($r['status'] == 1) ? ' checked' : '';
		}
		return $check;
	}

	public function getCatSet() {
		$query = $this->db->query("SELECT status FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='categories'");
		$row = $query->rows;
		$check = '';
		foreach ($row as $r) {
			$check .= ($r['status'] == 1) ? ' checked' : '';
		}
		return $check;
	}

	public function getInfoSet() {
		$query = $this->db->query("SELECT status FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='information'");
		$row = $query->rows;
		$check = '';
		foreach ($row as $r) {
			$check .= ($r['status'] == 1) ? ' checked' : '';
		}
		return $check;
	}

	public function restore($group, $resp = '') {
		$stores = $this->getStoreId();
		foreach ($stores as $store) {
			$store_id = $store['store_id'];
			$src = '../sitemap/' . $store_id . '/' . $group . '/';
			$dst = '../sitemap/' . $store_id . '/' . $group . '/restore/';
			if (file_exists('../sitemap/' . $store_id . '/' . $group . '/backup/')) {
				$dir = opendir($src);
				@mkdir($dst);
				while (false !== ($file = readdir($dir))) {
					if (($file != '.') && ($file != '..') && ($file != 'restore') && ($file != 'backup')) {
						if (is_dir($src . '/' . $file)) {
							$this->recurse_copy($src . '/' . $file, $dst . '/' . $file);
						} else {
							copy($src . '/' . $file, $dst . '/' . $file);
						}
					}
				}
				closedir($dir);
				$this->removeFiles($src . '*.xml');
				$this->removeFiles($src . 'xsl/*');
				$this->recurse_copy($src . 'backup', $src);
				$this->removeFiles($src . 'backup/*');
				$this->removeFiles($src . 'backup/xsl/*');
				$this->recurse_copy($src . 'restore', $src . 'backup');
				$this->removeFiles($src . 'restore/*');
				$this->removeFiles($src . 'restore/xsl/*');
				if (is_dir($dst . 'xsl/')) {
					rmdir($dst . 'xsl/');
				}
				if (is_dir($dst)) {
					rmdir($dst);
				}
			} else {
				$this->session->data['warning'] = $this->language->get('text_resFalse');
				return;
			}
		}
	}

	public function updateSettings($id, $change, $priority, $style, $indlim) {
		$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `changefreq`='" . $this->db->escape($change) . "', `prio`='" . $this->db->escape($priority) . "', `prodstyle`='" . (int)$style . "', `indlim`='" . (int)$indlim . "' WHERE `id`='" . (int)$id . "'");
	}

	public function updateIncStatus($disprod, $disinfo, $discat) {
		$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `status`='" . (int)$disprod . "' WHERE `groups`='products'");
		$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `status`='" . (int)$disinfo . "' WHERE `groups`='information'");
		$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `status`='" . (int)$discat . "' WHERE `groups`='categories'");
	}

	public function generateRobots($post) {
		$file = '../robots.txt';
		$robots_handle = fopen($file, 'w') or die('Cannot open file: ' . $file);
		fwrite($robots_handle, str_replace('\\r\\n', "\n", $post));
		fclose($robots_handle);
	}

	// Products
	public function generateProducts() {
		$stores = $this->getStoreId();
		foreach ($stores as $store) {
			$store_id = $store['store_id'];
			$query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE `store_id`='" . (int)$store_id . "' AND `key`='config_url'");
			$config_url = $query->rows;
			$store_url = '';
			foreach ($config_url as $url) {
				$store_url .= $url['value'];
			}
			$storeurl = ($store_url == '') ? HTTP_CATALOG : $store_url;
			$src = '../sitemap/' . $store_id . '/products/';
			$dst = '../sitemap/' . $store_id . '/products/backup/';
			$xsl = '../sitemap/' . $store_id . '/products/xsl/';
			$this->make_dir($src);
			$this->make_dir($dst);
			$this->removeFiles($dst . 'xsl/*');
			$this->removeFiles($dst . '*');
			$this->recurse_copy($src, $dst);
			$this->make_dir($xsl);

			$this->removeDir($dst . 'backup/xsl/');
			$this->removeDir($dst . 'backup/');

			$this->removeFiles($src . '*');

			$priority = '';
			$statusd = '';
			$prodstyle = '';
			$changefreq = '';
			$indlim = '';
			$query = $this->db->query("SELECT prio,indlim,changefreq,prodstyle,status FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='products'");
			$row = $query->rows;
			foreach ($row as $r) {
				$priority .= $r['prio'];
				$indlim .= $r['indlim'];
				$statusd .= $r['status'];
				$prodstyle .= $r['prodstyle'];
				$changefreq .= $r['changefreq'];
			}
			$sitemap_index_file = $src . 'sitemap-index.xml';
			$sitemap_index_handle = fopen($sitemap_index_file, 'w') or die('Cannot open file: ' . $sitemap_index_file);
			$sitemap_index = '<?xml version="1.0" encoding="UTF-8"?>';
			if ($prodstyle == '1') {
				$sitemap_index .= '<?xml-stylesheet type="text/xsl" href="' . $storeurl . 'sitemap/' . $store_id . '/products/xsl/sitemap-index.xsl"?>';
			}
			$sitemap_index .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

			$status = ($statusd == 0) ? ' AND status=1' : '';

			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_store WHERE store_id='" . (int)$store_id . "'");
			$productCount = $query->num_rows;
			$prodpages = ceil($productCount / $indlim);
			for ($i = 1; $i <= $prodpages; $i++) {
				$lastid = '';
				$query = $this->db->query("SELECT lastid FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='products'");
				$last_id = $query->rows;
				foreach ($last_id as $lid) {
					$lastid .= $lid['lastid'];
				}
				$status = ($statusd == 0) ? ' status=1 AND ' : '';
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_store WHERE product_id > '" . (int)$lastid . "' AND store_id='" . (int)$store_id . "' LIMIT " . (int)$indlim);
				$prodrow = $query->rows;
				$file = '../sitemap/' . $store_id . '/products/sitemap_' . $i . '.xml';
				$handle = fopen($file, 'w') or die('Cannot open file: ' . $file);
				$data = '<?xml version="1.0" encoding="UTF-8"?>';
				if ($prodstyle == '1') {
					$data .= '<?xml-stylesheet type="text/xsl" href="' . $storeurl . 'sitemap/' . $store_id . '/products/xsl/sitemap.xsl"?>';
				}
				$data .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

				foreach ($prodrow as $prod) {
					$product_id = $prod['product_id'];
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE " . $status . " product_id ='" . (int)$product_id . "'");
					$row = $query->rows;
					foreach ($row as $r) {
						$product_id = $r['product_id'];
						$producturl = 'product_id=' . $product_id;
						$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query='" . $this->db->escape($producturl) . "'");
						$url_alias = $query->rows;
						foreach ($url_alias as $alias) {
							$data .= '<url><loc>' . $storeurl . '' . str_replace("&", "", $alias['keyword']) . '</loc>';

							if ($r['image'] != '') {
								$data .= '<image:image><image:loc>' . $storeurl . '/image/' . $r['image'] . '</image:loc>';
								$query = $this->db->query("SELECT meta_title FROM " . DB_PREFIX . "product_description WHERE product_id='" . (int)$product_id . "'");
								$product_title = $query->rows;
								foreach ($product_title as $title) {
									$data .= '<image:title>' . htmlspecialchars($title['meta_title'], ENT_XML1) . '</image:title>';
								}
								$data .= '</image:image>';
							}

							$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id='" . (int)$product_id . "'");
							$product_image = $query->rows;
							foreach ($product_image as $image) {
								$data .= '<image:image><image:loc>' . $storeurl . 'image/' . $image['image'] . '</image:loc>';
								$query = $this->db->query("SELECT meta_title FROM " . DB_PREFIX . "product_description WHERE product_id='" . (int)$product_id . "'");
								$product_title = $query->rows;
								foreach ($product_title as $title) {
									$data .= '<image:title>' . htmlspecialchars($title['meta_title'], ENT_XML1) . '</image:title>';
								}
								$data .= '</image:image>';
							}
							$data .= '<changefreq>' . $changefreq . '</changefreq><lastmod>' . date('Y-m-d', strtotime($r['date_modified'])) . '</lastmod><priority>' . $priority . '</priority></url>';

							$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `lastid`='" . (int)$product_id . "' WHERE `groups`='products'");
						}
					}
				}
				$data .= '</urlset>';
				fwrite($handle, $data);
				fclose($handle);

				$sitemap_index .= '<sitemap><loc>' . $storeurl . 'sitemap/' . $store_id . '/products/sitemap_' . $i . '.xml</loc><lastmod>' . date('Y-m-d') . '</lastmod></sitemap>';
			}
			$sitemap_index .= '</sitemapindex>';
			fwrite($sitemap_index_handle, $sitemap_index);
			fclose($sitemap_index_handle);

			$this->createIndexStyle('products index', $store_id, 'products');
			$this->createStyle('products sitemap', $store_id, 'products');

			$last_update = date('Y-m-d H:i:s');

			$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `lastid`='0', last_update='" . $this->db->escape($last_update) . "' WHERE `groups`='products'");
		}
		$this->generateIndex();
	}

	public function generateCategories() {
		$stores = $this->getStoreId();
		foreach ($stores as $store) {
			$store_id = $store['store_id'];
			$query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE `store_id`='" . (int)$store_id . "' AND `key`='config_url'");
			$config_url = $query->rows;
			$store_url = '';
			foreach ($config_url as $url) {
				$store_url .= $url['value'];
			}
			$storeurl = ($store_url == '') ? HTTP_CATALOG : $store_url;
			$src = '../sitemap/' . $store_id . '/categories/';
			$dst = '../sitemap/' . $store_id . '/categories/backup/';
			$xsl = '../sitemap/' . $store_id . '/categories/xsl/';
			$this->make_dir($src);
			$this->make_dir($dst);
			$this->removeFiles($dst . 'xsl/*');
			$this->removeFiles($dst . '*');
			$this->recurse_copy($src, $dst);
			$this->make_dir($xsl);

			if (is_dir($dst . 'backup/xsl/')) {
				rmdir($dst . 'backup/xsl/');
			}
			if (is_dir($dst . 'backup/')) {
				rmdir($dst . 'backup/');
			}
			$this->removeFiles($src . '*');

			$indlim = '';
			$priority = '';
			$changefreq = '';
			$statusd = '';
			$prodstyle = '';
			$query = $this->db->query("SELECT indlim,prio,changefreq,prodstyle,status FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='categories'");
			$row = $query->rows;
			foreach ($row as $r) {
				$indlim .= $r['indlim'];
				$priority .= $r['prio'];
				$statusd .= $r['status'];
				$prodstyle .= $r['prodstyle'];
				$changefreq .= $r['changefreq'];
			}
			$sitemap_index_file = $src . 'sitemap-index.xml';
			$sitemap_index_handle = fopen($sitemap_index_file, 'w') or die('Cannot open file: ' . $sitemap_index_file);
			$sitemap_index = '<?xml version="1.0" encoding="UTF-8"?>';
			if ($prodstyle == '1') {
				$sitemap_index .= '<?xml-stylesheet type="text/xsl" href="' . $storeurl . 'sitemap/' . $store_id . '/categories/xsl/sitemap-index.xsl"?>';
			}
			$sitemap_index .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

			$status = ($statusd == 0) ? 'WHERE status=1' : '';

			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_to_store WHERE store_id='" . (int)$store_id . "'");
			$productCount = $query->num_rows;
			$pages = ceil($productCount / $indlim);
			for ($i = 1; $i <= $pages; $i++) {
				$lastid = '';
				$query = $this->db->query("SELECT lastid FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='categories'");
				$last_id = $query->rows;
				foreach ($last_id as $lid) {
					$lastid .= $lid['lastid'];
				}
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_to_store WHERE category_id > '" . (int)$lastid . "' AND store_id='" . (int)$store_id . "' LIMIT " . (int)$indlim);
				$catrow = $query->rows;
				$file = '../sitemap/' . $store_id . '/categories/sitemap_' . $i . '.xml';
				$handle = fopen($file, 'w') or die('Cannot open file: ' . $file);
				$data = '<?xml version="1.0" encoding="UTF-8"?>';
				if ($prodstyle == '1') {
					$data .= '<?xml-stylesheet type="text/xsl" href="' . $storeurl . 'sitemap/' . $store_id . '/categories/xsl/sitemap.xsl"?>';
				}
				$data .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

				$status = ($statusd == 0) ? ' status=1 AND ' : '';
				foreach ($catrow as $cat) {
					$category_id = $cat['category_id'];
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category WHERE " . $status . " category_id ='" . (int)$category_id . "'");
					$row = $query->rows;
					foreach ($row as $r) {
						$category_id = $r['category_id'];
						$caturl = 'category_id=' . $category_id;
						$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query='" . $this->db->escape($caturl) . "'");
						$url_alias = $query->rows;

						foreach ($url_alias as $alias) {
							$data .= '<url><loc>' . $storeurl . '' . $alias['keyword'] . '</loc>';
						}

						if ($r['image'] != '') {
							$data .= '<image:image><image:loc>' . $storeurl . 'image/' . $r['image'] . '</image:loc>';
							$query = $this->db->query("SELECT meta_title FROM " . DB_PREFIX . "category_description WHERE category_id='" . (int)$category_id . "'");
							$product_title = $query->rows;
							foreach ($product_title as $title) {
								$data .= '<image:title>' . htmlspecialchars($title['meta_title'], ENT_XML1) . '</image:title>';
							}
							$data .= '</image:image>';
						}
						$data .= '<changefreq>' . $changefreq . '</changefreq><lastmod>' . date('Y-m-d', strtotime($r['date_modified'])) . '</lastmod><priority>' . $priority . '</priority></url>';

						$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `lastid`='" . (int)$category_id . "' WHERE `groups`='categories'");
					}
				}
				$data .= '</urlset>';
				fwrite($handle, $data);
				fclose($handle);

				$sitemap_index .= '<sitemap><loc>' . $storeurl . 'sitemap/' . $store_id . '/categories/sitemap_' . $i . '.xml</loc><lastmod>' . date('Y-m-d') . '</lastmod></sitemap>';
			}
			$sitemap_index .= '</sitemapindex>';
			fwrite($sitemap_index_handle, $sitemap_index);
			fclose($sitemap_index_handle);

			$this->createIndexStyle('categories index', $store_id, 'categories');
			$this->createStyle('categories sitemap', $store_id, 'categories');

			$last_update = date('Y-m-d H:i:s');
			$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `lastid`='0', last_update='" . $this->db->escape($last_update) . "' WHERE `groups`='categories'");
		}
		$this->generateIndex();
	}

	public function generateBrands() {
		$stores = $this->getStoreId();
		foreach ($stores as $store) {
			$store_id = $store['store_id'];
			$query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE `store_id`='" . (int)$store_id . "' AND `key`='config_url'");
			$config_url = $query->rows;
			$store_url = '';
			foreach ($config_url as $url) {
				$store_url .= $url['value'];
			}
			$storeurl = ($store_url == '') ? HTTP_CATALOG : $store_url;
			$src = '../sitemap/' . $store_id . '/brands/';
			$dst = '../sitemap/' . $store_id . '/brands/backup/';
			$xsl = '../sitemap/' . $store_id . '/brands/xsl/';
			$this->make_dir($src);
			$this->make_dir($dst);
			$this->removeFiles('../sitemap/' . $store_id . '/brands/backup/xsl/*');
			$this->removeFiles('../sitemap/' . $store_id . '/brands/backup/*');
			$this->recurse_copy($src, $dst);
			$this->make_dir($xsl);

			$this->removeFiles('../sitemap/' . $store_id . '/brands/*');

			$indlim = '';
			$prodstyle = '';
			$query = $this->db->query("SELECT indlim,prodstyle FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='brands'");
			$row = $query->rows;
			foreach ($row as $r) {
				$indlim .= $r['indlim'];
				$prodstyle .= $r['prodstyle'];
			}

			$sitemap_index_file = '../sitemap/' . $store_id . '/brands/sitemap-index.xml';
			$sitemap_index_handle = fopen($sitemap_index_file, 'w') or die('Cannot open file: ' . $sitemap_index_file);
			$sitemap_index = '<?xml version="1.0" encoding="UTF-8"?>';

			if ($prodstyle == 1) {
				$sitemap_index .= '<?xml-stylesheet type="text/xsl" href="' . $storeurl . 'sitemap/' . $store_id . '/brands/xsl/sitemap-index.xsl"?>';
			}
			$sitemap_index .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer_to_store WHERE store_id='" . (int)$store_id . "'");

			$productCount = $query->num_rows;
			$pages = ceil($productCount / $indlim);
			for ($i = 1; $i <= $pages; $i++) {
				$priority = '';
				$lastid = '';
				$changefreq = '';
				$query = $this->db->query("SELECT prio,lastid,changefreq FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='brands'");
				$last_id = $query->rows;
				foreach ($last_id as $lid) {
					$priority .= $lid['prio'];
					$lastid .= $lid['lastid'];
					$changefreq .= $lid['changefreq'];
				}
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer_to_store WHERE manufacturer_id > '" . (int)$lastid . "' AND store_id='" . (int)$store_id . "' LIMIT " . (int)$indlim);
				$brandrow = $query->rows;

				$file = '../sitemap/' . $store_id . '/brands/sitemap_' . $i . '.xml';
				$handle = fopen($file, 'w') or die('Cannot open file: ' . $file);
				$data = '<?xml version="1.0" encoding="UTF-8"?>';

				if ($prodstyle == 1) {
					$data .= '<?xml-stylesheet type="text/xsl" href="' . $storeurl . 'sitemap/' . $store_id . '/brands/xsl/sitemap.xsl"?>';
				}
				$data .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

				foreach ($brandrow as $brand) {
					$manufacturer_id = $brand['manufacturer_id'];
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer WHERE manufacturer_id ='" . (int)$manufacturer_id . "'");
					$row = $query->rows;
					foreach ($row as $r) {
						$manufacturer_id = $r['manufacturer_id'];
						$brandurl = 'manufacturer_id=' . $manufacturer_id;
						$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query='" . $this->db->escape($brandurl) . "'");
						$url_alias = $query->rows;

						foreach ($url_alias as $alias) {
							$data .= '<url><loc>' . $storeurl . '' . $alias['keyword'] . '</loc>';
						}

						if ($r['image'] != '') {
							$data .= '<image:image><image:loc>' . $storeurl . 'image/' . $r['image'] . '</image:loc><image:title>' . htmlspecialchars($r['name'], ENT_XML1) . '</image:title></image:image>';
						}
						$data .= '<changefreq>' . $changefreq . '</changefreq><priority>' . $priority . '</priority></url>';

						$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `lastid`='" . (int)$manufacturer_id . "' WHERE `groups`='brands'");
					}
				}
				$data .= '</urlset>';
				fwrite($handle, $data);
				fclose($handle);

				$sitemap_index .= '<sitemap><loc>' . $storeurl . 'sitemap/' . $store_id . '/brands/sitemap_' . $i . '.xml</loc><lastmod>' . date('Y-m-d') . '</lastmod></sitemap>';
			}
			$sitemap_index .= '</sitemapindex>';
			fwrite($sitemap_index_handle, $sitemap_index);
			fclose($sitemap_index_handle);

			$this->createIndexStyle('brands index', $store_id, 'brands');
			$this->createStyle('brands sitemap', $store_id, 'brands');

			$last_update = date('Y-m-d H:i:s');
			$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `lastid`='0', last_update='" . $this->db->escape($last_update) . "' WHERE `groups`='brands'");
		}
		$this->generateIndex();
	}

	public function generateInformation() {
		$stores = $this->getStoreId();
		foreach ($stores as $store) {
			$store_id = $store['store_id'];
			$query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE `store_id`='" . (int)$store_id . "' AND `key`='config_url'");
			$config_url = $query->rows;
			$store_url = '';
			foreach ($config_url as $url) {
				$store_url .= $url['value'];
			}
			$storeurl = ($store_url == '') ? HTTP_CATALOG : $store_url;
			$src = '../sitemap/' . $store_id . '/information/';
			$dst = '../sitemap/' . $store_id . '/information/backup/';
			$xsl = '../sitemap/' . $store_id . '/information/xsl/';
			$this->make_dir($src);
			$this->make_dir($dst);
			$this->removeFiles('../sitemap/' . $store_id . '/information/backup/xsl/*');
			$this->removeFiles('../sitemap/' . $store_id . '/information/backup/*');
			$this->recurse_copy($src, $dst);
			$this->make_dir($xsl);

			$this->removeFiles('../sitemap/' . $store_id . '/information/*');

			$priority = '';
			$indlim = '';
			$prodstyle = '';
			$statusd = '';
			$changefreq = '';
			$query = $this->db->query("SELECT indlim,status,prio,changefreq,prodstyle FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='information'");
			$row = $query->rows;
			foreach ($row as $r) {
				$priority .= $r['prio'];
				$indlim .= $r['indlim'];
				$statusd .= $r['status'];
				$changefreq .= $r['changefreq'];
				$prodstyle .= $r['prodstyle'];
			}
			$sitemap_index_file = '../sitemap/' . $store_id . '/information/sitemap-index.xml';
			$sitemap_index_handle = fopen($sitemap_index_file, 'w') or die('Cannot open file: ' . $sitemap_index_file);
			$sitemap_index = '<?xml version="1.0" encoding="UTF-8"?>';
			if ($prodstyle == 1) {
				$sitemap_index .= '<?xml-stylesheet type="text/xsl" href="' . $storeurl . 'sitemap/' . $store_id . '/information/xsl/sitemap-index.xsl"?>';
			}
			$sitemap_index .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
			$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_to_store WHERE store_id='" . (int)$store_id . "'");
			$productCount = $query->num_rows;
			$pages = ceil($productCount / $indlim);
			for ($i = 1; $i <= $pages; $i++) {
				$lastid = '';
				$query = $this->db->query("SELECT lastid FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='information'");
				$last_id = $query->rows;
				foreach ($last_id as $lid) {
					$lastid .= $lid['lastid'];
				}
				$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_to_store WHERE information_id > '" . (int)$lastid . "' AND store_id='" . (int)$store_id . "' ORDER BY information_id ASC LIMIT " . (int)$indlim);
				$inforow = $query->rows;
				$file = '../sitemap/' . $store_id . '/information/sitemap_' . $i . '.xml';
				$handle = fopen($file, 'w') or die('Cannot open file: ' . $file);
				$data = '<?xml version="1.0" encoding="UTF-8"?>';
				if ($prodstyle == 1) {
					$data .= '<?xml-stylesheet type="text/xsl" href="' . $storeurl . 'sitemap/' . $store_id . '/information/xsl/sitemap.xsl"?>';
				}
				$data .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
				$status = ($statusd == 0) ? ' status=1 AND ' : '';
				foreach ($inforow as $info) {
					$information_id = $info['information_id'];
					$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information WHERE " . $status . " information_id ='" . (int)$information_id . "'");
					$statusrow = $query->rows;
					foreach ($statusrow as $sr) {
						$information_id = $sr['information_id'];
						$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "information_description WHERE information_id ='" . (int)$information_id . "'");
						$row = $query->rows;
						foreach ($row as $r) {
							$information_id = $r['information_id'];
							$infourl = 'information_id=' . $information_id;
							$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "seo_url WHERE query='" . $this->db->escape($infourl) . "'");
							$url_alias = $query->rows;

							foreach ($url_alias as $alias) {
								$data .= '<url><loc>' . $storeurl . $alias['keyword'] . '</loc>';
							}
							$data .= '<changefreq>' . $changefreq . '</changefreq><priority>' . $priority . '</priority></url>';

							$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `lastid`='" . (int)$information_id . "' WHERE `groups`='information'");
						}
					}
				}
				$data .= '</urlset>';
				fwrite($handle, $data);
				fclose($handle);

				$sitemap_index .= '<sitemap><loc>' . $storeurl . 'sitemap/' . $store_id . '/information/sitemap_' . $i . '.xml</loc><lastmod>' . date('Y-m-d') . '</lastmod></sitemap>';
			}
			$sitemap_index .= '</sitemapindex>';
			fwrite($sitemap_index_handle, $sitemap_index);
			fclose($sitemap_index_handle);

			$this->createIndexStyle('information index', $store_id, 'information');
			$this->createStyle('information sitemap', $store_id, 'information');

			$last_update = date('Y-m-d H:i:s');
			$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET `lastid`='0', last_update='" . $this->db->escape($last_update) . "' WHERE `groups`='information'");
		}
		$this->generateIndex();
	}

	public function generatePages() {
		$stores = $this->getStoreId();
		foreach ($stores as $store) {
			$store_id = $store['store_id'];
			$query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE `store_id`='" . (int)$store_id . "' AND `key`='config_url'");
			$config_url = $query->rows;
			$store_url = '';
			foreach ($config_url as $url) {
				$store_url .= $url['value'];
			}
			$storeurl = ($store_url == '') ? HTTP_CATALOG : $store_url;
			$src = '../sitemap/' . $store_id . '/pages/';
			$dst = '../sitemap/' . $store_id . '/pages/backup/';
			$xsl = '../sitemap/' . $store_id . '/pages/xsl/';
			$this->make_dir($src);
			$this->make_dir($dst);
			$this->removeFiles('../sitemap/' . $store_id . '/pages/backup/xsl/*');
			$this->removeFiles('../sitemap/' . $store_id . '/pages/backup/*');
			$this->recurse_copy($src, $dst);
			$this->make_dir($xsl);
			$this->removeFiles('../sitemap/' . $store_id . '/pages/*');

			$priority = '';
			$changefreq = '';
			$prodstyle = '';
			$query = $this->db->query("SELECT prio,changefreq,prodstyle FROM " . DB_PREFIX . "gcrdev_sitemap WHERE `groups`='pages'");
			$last_id = $query->rows;
			foreach ($last_id as $lid) {
				$priority .= $lid['prio'];
				$changefreq .= $lid['changefreq'];
				$prodstyle .= $lid['prodstyle'];
			}
			$file = '../sitemap/' . $store_id . '/pages/sitemap.xml';
			$handle = fopen($file, 'w') or die('Cannot open file: ' . $file);
			$data = '<?xml version="1.0" encoding="UTF-8"?>';

			if ($prodstyle == 1) {
				$data .= '<?xml-stylesheet type="text/xsl" href="' . $storeurl . 'sitemap/' . $store_id . '/pages/xsl/sitemap.xsl"?>';
			}
			$data .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<url><loc>' . rtrim($storeurl, '/') . '</loc>
<changefreq>' . $changefreq . '</changefreq>
<priority>' . $priority . '</priority></url>
<url><loc>' . $storeurl . 'index.php?route=product/manufacturer</loc>
<changefreq>' . $changefreq . '</changefreq>
<priority>' . $priority . '</priority></url>
<url><loc>' . $storeurl . 'index.php?route=account/voucher</loc>
<changefreq>' . $changefreq . '</changefreq>
<priority>' . $priority . '</priority></url>
<url><loc>' . $storeurl . 'index.php?route=affiliate/login</loc>
<changefreq>' . $changefreq . '</changefreq>
<priority>' . $priority . '</priority></url>
<url><loc>' . $storeurl . 'index.php?route=product/special</loc>
<changefreq>' . $changefreq . '</changefreq>
<priority>' . $priority . '</priority></url>
<url><loc>' . $storeurl . 'index.php?route=account/return/add</loc>
<changefreq>' . $changefreq . '</changefreq>
<priority>' . $priority . '</priority></url>
</urlset>';

			fwrite($handle, $data);
			fclose($handle);

			$this->createIndexStyle('pages index', $store_id, 'pages');
			$this->createStyle('pages sitemap', $store_id, 'pages');
		}

		$last_update = date('Y-m-d H:i:s');
		$this->db->query("UPDATE " . DB_PREFIX . "gcrdev_sitemap SET last_update='" . $this->db->escape($last_update) . "' WHERE `groups`='pages'");
		$this->generateIndex();
	}
}
