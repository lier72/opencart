<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 08/12/24
 * Time: 15:07
 * File: controller/extension/module/odoo_product_sync.php
 */

class ControllerExtensionModuleOdooProductSync extends Controller {
    private $error = array();
    const VERSION = '2.0.0';

    public function __construct($registry) {
        parent::__construct($registry);
        $this->load->model('extension/module/odoo_product_mapping');
        $this->load->model('extension/module/odoo_connector');
    }

    public function index() {
        // Main controller entry point - will implement UI later
        $this->checkInstall();
    }

    public function install() {
        $this->load->model('extension/module/odoo_product_mapping');

        // Create category mapping table
        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "odoo_category_map (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `odoo_category_id` int(11) NOT NULL,
            `opencart_category_id` int(11) NOT NULL,
            `created_by` varchar(128) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_sync` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `category_pair` (`odoo_category_id`, `opencart_category_id`)
        ) DEFAULT CHARSET=utf8");

        // Create sync log table
        $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "odoo_product_sync_log (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `product_id` int(11) NOT NULL,
            `sync_direction` enum('to_odoo','from_odoo') NOT NULL,
            `status` enum('synced','error','not_synced','pending') NOT NULL,
            `message` text NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) DEFAULT CHARSET=utf8");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "odoo_category_map");
        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "odoo_product_sync_log");
    }

    protected function checkInstall() {
        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "odoo_category_map'");
        if ($query->num_rows == 0) {
            $this->install();
        }
    }

    public function syncProductToOdoo() {
        if (empty($this->request->get['product_id'])) {
            $this->response->setOutput(json_encode(['error' => 'No product ID specified']));
            return;
        }

        $product_id = (int)$this->request->get['product_id'];

        try {
            $result = $this->model_extension_module_odoo_product_mapping->syncProductToOdoo($product_id);
            $this->response->setOutput(json_encode($result));
        } catch (Exception $e) {
            $this->response->setOutput(json_encode(['error' => $e->getMessage()]));
        }
    }
}