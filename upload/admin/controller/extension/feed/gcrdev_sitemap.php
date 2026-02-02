<?php
class ControllerExtensionFeedGcrdevSitemap extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/feed/gcrdev_sitemap');
		$this->load->model('extension/feed/gcrdev_sitemap');
		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

			if (isset($_POST['save_settings'])) {
				$count = count($_POST['indlim']);
				for ($i = 0; $i < $count; $i++) {
					$id = $this->db->escape($_POST['id'][$i]);
					$change = $this->db->escape($_POST['prod_change_freq'][$i]);
					$priority = $this->db->escape($_POST['prod_priority'][$i]);
					$indlim = $this->db->escape($_POST['indlim'][$i]);
					$style = (isset($_POST['prod_style'][$i])) ? 1 : 0;
					$this->model_extension_feed_gcrdev_sitemap->updateSettings($id, $change, $priority, $style, $indlim);
				}
				$disprod = (isset($_POST['disprod'])) ? 1 : 0;
				$disinfo = (isset($_POST['disinfo'])) ? 1 : 0;
				$discat = (isset($_POST['discat'])) ? 1 : 0;
				$this->model_extension_feed_gcrdev_sitemap->updateIncStatus($disprod, $disinfo, $discat);
				$this->session->data['success'] = $this->language->get('text_settingsSaved');
			} elseif (isset($_POST['generate_products'])) {
				$this->model_extension_feed_gcrdev_sitemap->generateProducts();
				$this->session->data['success'] = $this->language->get('text_prodGenTrue');
			} elseif (isset($_POST['generate_categories'])) {
				$this->model_extension_feed_gcrdev_sitemap->generateCategories();
				$this->session->data['success'] = $this->language->get('text_catGenTrue');
			} elseif (isset($_POST['generate_brands'])) {
				$this->model_extension_feed_gcrdev_sitemap->generateBrands();
				$this->session->data['success'] = $this->language->get('text_brandGenTrue');
			} elseif (isset($_POST['generate_information'])) {
				$this->model_extension_feed_gcrdev_sitemap->generateInformation();
				$this->session->data['success'] = $this->language->get('text_infoGenTrue');
			} elseif (isset($_POST['generate_pages'])) {
				$this->model_extension_feed_gcrdev_sitemap->generatePages();
				$this->session->data['success'] = $this->language->get('text_pageGenTrue');
			} elseif (isset($_POST['generate_robots'])) {
				$post = $_POST['robots'];
				$this->model_extension_feed_gcrdev_sitemap->generateRobots($post);
				$this->session->data['success'] = $this->language->get('text_robGenTrue');
			} elseif (isset($_POST['restore_products'])) {
				$this->model_extension_feed_gcrdev_sitemap->restore('products');
				$this->session->data['success'] = $this->language->get('text_resProdTrue');
			} elseif (isset($_POST['restore_categories'])) {
				$this->model_extension_feed_gcrdev_sitemap->restore('categories');
				$this->session->data['success'] = $this->language->get('text_resCatTrue');
			} elseif (isset($_POST['restore_brands'])) {
				$this->model_extension_feed_gcrdev_sitemap->restore('brands');
				$this->session->data['success'] = $this->language->get('text_resBrandTrue');
			} elseif (isset($_POST['restore_information'])) {
				$this->model_extension_feed_gcrdev_sitemap->restore('information');
				$this->session->data['success'] = $this->language->get('text_resInfoTrue');
			} elseif (isset($_POST['restore_pages'])) {
				$resp = $this->language->get('text_resPageTrue');
				$this->model_extension_feed_gcrdev_sitemap->restore('pages', $resp);
				$this->session->data['success'] = $this->language->get('text_resPageTrue');
			}

			$this->response->redirect($this->url->link('extension/feed/gcrdev_sitemap', 'user_token=' . $this->session->data['user_token'], true));
			exit();
		}

		// Success message
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		// Warning message (restore failed)
		if (isset($this->session->data['warning'])) {
			$data['warning'] = $this->session->data['warning'];
			unset($this->session->data['warning']);
		} else {
			$data['warning'] = '';
		}

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_sitemap_header'] = $this->language->get('text_sitemap_header');
		$data['text_settings_header'] = $this->language->get('text_settings_header');
		$data['text_robots_header'] = $this->language->get('text_robots_header');
		$data['text_help_header'] = $this->language->get('text_help_header');
		$data['text_sitemap'] = $this->language->get('text_sitemap');
		$data['text_generate_sitemap'] = $this->language->get('text_generate_sitemap');
		$data['text_view_sitemap'] = $this->language->get('text_view_sitemap');
		$data['text_robots'] = $this->language->get('text_robots');
		$data['text_group'] = $this->language->get('text_group');
		$data['text_change'] = $this->language->get('text_change');
		$data['text_priority'] = $this->language->get('text_priority');
		$data['text_limit'] = $this->language->get('text_limit');
		$data['text_style'] = $this->language->get('text_style');
		$data['text_robGenTrue'] = $this->language->get('text_robGenTrue');
		$data['text_generate'] = $this->language->get('text_generate');
		$data['text_sitemap_gen'] = $this->language->get('text_sitemap_gen');
		$data['text_resFalse'] = $this->language->get('text_resFalse');
		$data['text_resProdTrue'] = $this->language->get('text_resProdTrue');
		$data['text_resCatTrue'] = $this->language->get('text_resCatTrue');
		$data['text_resBrandTrue'] = $this->language->get('text_resBrandTrue');
		$data['text_resInfoTrue'] = $this->language->get('text_resInfoTrue');
		$data['text_resPageTrue'] = $this->language->get('text_resPageTrue');
		$data['text_sitemap_help'] = $this->language->get('text_sitemap_help');
		$data['text_robots_help'] = $this->language->get('text_robots_help');
		$data['text_settings_help'] = $this->language->get('text_settings_help');

		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_data_feed'] = $this->language->get('entry_data_feed');

		$data['button_generate_robots_title'] = $this->language->get('button_generate_robots_title');
		$data['button_generate_robots'] = $this->language->get('button_generate_robots');
		$data['button_generate_sitemap_title'] = $this->language->get('button_generate_sitemap_title');
		$data['button_generate_sitemap'] = $this->language->get('button_generate_sitemap');
		$data['button_restore_title'] = $this->language->get('button_restore_title');
		$data['button_restore'] = $this->language->get('button_restore');
		$data['button_save_settings'] = $this->language->get('button_save_settings');
		$data['button_save_settings_title'] = $this->language->get('button_save_settings_title');
		$data['button_view_sitemap'] = $this->language->get('button_view_sitemap');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['tab_general'] = $this->language->get('tab_general');

		$data['select_changefreq'] = $this->language->get('select_changefreq');
		$data['select_priority'] = $this->language->get('select_priority');

		$data['error_warning'] = (isset($this->error['warning'])) ? $this->error['warning'] : '';

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/feed/gcrdev_sitemap', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/feed/gcrdev_sitemap', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=feed', true);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$data['prodCheck'] = $this->model_extension_feed_gcrdev_sitemap->getProdSet();
		$data['catCheck'] = $this->model_extension_feed_gcrdev_sitemap->getCatSet();
		$data['infoCheck'] = $this->model_extension_feed_gcrdev_sitemap->getInfoSet();
		$data['sitemaps'] = array();

		$results = $this->model_extension_feed_gcrdev_sitemap->getData();
		foreach ($results as $result) {
			$data['sitemaps'][] = array(
				'id'               => $result['id'],
				'groups'           => $result['groups'],
				'change'           => $result['changefreq'],
				'priority'         => $result['prio'],
				'indlim'           => $result['indlim'],
				'style'            => $result['prodstyle'],
				'status'           => $result['status'],
				'last_update'      => date('d M Y', strtotime($result['last_update'])),
				'last_update_time' => date('H:i:s', strtotime($result['last_update']))
			);
		}

		// Store URLs for sitemap submission
		$data['store_url'] = '<tr>
<td class="text-left">' . $_SERVER['SERVER_NAME'] . '</td>
<td class="text-left"><a href="http://www.bing.com/webmaster/ping.aspx?siteMap=http://' . $_SERVER['SERVER_NAME'] . '/sitemap/0/sitemap-index.xml" target="_blank"><i class="fa fa-windows"></i> bing</a></td>
<td class="text-left"><a href="http://www.google.com/webmasters/sitemaps/ping?sitemap=http://' . $_SERVER['SERVER_NAME'] . '/sitemap/0/sitemap-index.xml" target="_blank"><i class="fa fa-google"></i> google</a></td>
</tr>';

		$query = $this->db->query("SELECT DISTINCT store_id FROM " . DB_PREFIX . "setting");
		$stores = $query->rows;
		foreach ($stores as $store) {
			$store_id = $store['store_id'];
			$query = $this->db->query("SELECT value, store_id FROM " . DB_PREFIX . "setting WHERE `store_id`='" . (int)$store_id . "' AND `key`='config_url'");
			$config_url = $query->rows;
			foreach ($config_url as $url) {
				$store_id = $url['store_id'];
				$data['store_url'] .= '<tr>
<td class="text-left">' . preg_replace('#^https?://#', '', rtrim($url['value'], '/')) . '</td>
<td class="text-left"><a href="http://www.bing.com/webmaster/ping.aspx?siteMap=' . $url['value'] . 'sitemap/' . $store_id . '/sitemap-index.xml" target="_blank"><i class="fa fa-windows"></i> bing</a></td>
<td class="text-left"><a href="http://www.google.com/webmasters/sitemaps/ping?sitemap=' . $url['value'] . 'sitemap/' . $store_id . '/sitemap-index.xml" target="_blank"><i class="fa fa-google"></i> google</a></td>
</tr>';
			}
		}

		// Read robots.txt content
		$robots_file = DIR_CATALOG . '../robots.txt';
		if (file_exists($robots_file)) {
			$data['robots_content'] = file_get_contents($robots_file);
		} else {
			$data['robots_content'] = '';
		}

		$this->response->setOutput($this->load->view('extension/feed/gcrdev_sitemap', $data));
	}

	public function install() {
		$this->load->model('extension/feed/gcrdev_sitemap');
		$this->model_extension_feed_gcrdev_sitemap->install();
	}

	public function uninstall() {
		$this->load->model('extension/feed/gcrdev_sitemap');
		$this->model_extension_feed_gcrdev_sitemap->uninstall();
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/feed/gcrdev_sitemap')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}
}
