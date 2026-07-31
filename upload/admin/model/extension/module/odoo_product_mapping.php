<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 08/12/24
 * Time: 15:10
 * File: model/extension/module/odoo_product_mapping.php
 */

class ModelExtensionModuleOdooProductMapping extends Model {
    protected $debug = False;
    // Sync status constants
    const SYNC_STATUS = [
        0 => 'not_synced',
        1 => 'synced',
        2 => 'pending',
        3 => 'error'
    ];

    // Constructor to initialize the debug variable
    public function __construct($registry) {
        parent::__construct($registry);

        $this->load->model('extension/module/odoo_connector');
        $config = $this->model_extension_module_odoo_connector->getConfig();
        $this->debug = $config['debug'];
    }

    // Helper method to convert numeric status to text
    private function getTextStatus($numericStatus) {
        $res = self::SYNC_STATUS[$numericStatus];
        return isset($res) ? $res : 'unknown';
    }

    // Helper method to convert text status to numeric
    private function getNumericStatus($textStatus) {
        // var_dump($textStatus);
        $flip = array_flip(self::SYNC_STATUS);
        return isset($flip[$textStatus]) ? $flip[$textStatus] : 0;
    }

    public function syncProductToOdoo($opencart_product_id) {
        $direction = 'to_odoo';
        try {
            // First check if OpenCart product exists
            $product = $this->getOpenCartProduct($opencart_product_id);
            if (!$product) {
                $this->logSync($opencart_product_id, 'OpenCart product not found', 'not_synced', $direction);
                throw new Exception('OpenCart product not found');
            }

            if ($this->debug) $this->log->write("Info odoo_product_mapping syncProductToOdoo: Starting sync for " . $opencart_product_id);

            // Check if product is already mapped
            $mapping = $this->getProductMapping(null, $opencart_product_id);

            // Transform product data to Odoo format
            $odoo_product_data = $this->transformOpencartToOdoo($product);

            // Initialize Odoo connection
            $odoo_connect = $this->model_extension_module_odoo_connector->getConnection();

            if (!$odoo_connect || !isset($odoo_connect['status']) || !$odoo_connect['status']) {
                $this->logSync($opencart_product_id, 'Failed to connect to Odoo', 'error', $direction);
                throw new Exception('Failed to connect to Odoo');
            }

            try {
                // Set status to pending before starting sync operation
                if ($mapping) {
                    $this->logSync($opencart_product_id, 'Starting update in Odoo', 'pending', $direction);

                    // Update existing Odoo product
                    $result = $this->updateOdooProduct($mapping['odoo_product_tmpl_id'], $odoo_product_data);
                    if ($result) {
                        $this->logSync($opencart_product_id, 'Product updated in Odoo', 'synced', $direction);
                    }
                } else {
                    $this->logSync($opencart_product_id, 'Starting creation in Odoo', 'pending', $direction);

                    // Create new Odoo product
                    list($odoo_product_id, $odoo_template_id) = $this->createOdooProduct($odoo_product_data);

                    // Create mapping with both IDs - this will set initial pending state
                    $this->createProductMapping($odoo_product_id, $opencart_product_id, $odoo_template_id);

                    // If we got here without exceptions, mark as synced
                    $this->logSync($opencart_product_id, 'Product created in Odoo', 'synced', $direction);
                }

                return ['success' => true];

            } catch (Exception $e) {
                // Log specific error and set error state
                $this->logSync($opencart_product_id, 'Sync failed: ' . $e->getMessage(), 'error', $direction);
                throw $e;
            }

        } catch (Exception $e) {
            if ($this->debug) $this->log->write("Error odoo_product_mapping syncProductToOdoo: " . $e->getMessage());

            // If no state has been set yet, set to error
            $current_mapping = $this->getProductMapping(null, $opencart_product_id);
            if (!$current_mapping || $current_mapping['is_synch'] == 2) { // if no mapping or still pending
                $this->logSync($opencart_product_id, $e->getMessage(), 'error', $direction);
            }
            throw $e;
        }
    }

    private function getOpenCartProduct($product_id, $language_id = null) {
        // Get language_id from parameter or fall back to config
        if ($language_id === null) {
            $language_id = (int)$this->config->get('config_language_id');
        } else {
            $language_id = (int)$language_id;
        }

        $product_query = $this->db->query("SELECT p.product_id, p.price, p.model, p.ean,
            pd.name, pd.description, pd.meta_title, pd.meta_description,
            pd.meta_keyword, pd.tag,
            su.keyword as seo_keyword,
            GROUP_CONCAT(ptc.category_id) as category_ids
        FROM " . DB_PREFIX . "product p
        LEFT JOIN " . DB_PREFIX . "product_description pd
            ON (p.product_id = pd.product_id)
        LEFT JOIN " . DB_PREFIX . "seo_url su
            ON (su.query = CONCAT('product_id=', p.product_id)
                AND su.store_id = '0'
                AND su.language_id = '" . $language_id . "')
        LEFT JOIN " . DB_PREFIX . "product_to_category ptc
            ON (p.product_id = ptc.product_id)
        WHERE p.product_id = '" . (int)$product_id . "'
            AND pd.language_id = '" . $language_id . "'");

        if ($product_query->num_rows) {
            return array(
                'product_id'       => $product_query->row['product_id'],
                'name'             => $product_query->row['name'],
                'description'      => $product_query->row['description'],
                'meta_title'       => $product_query->row['meta_title'],
                'meta_description' => $product_query->row['meta_description'],
                'meta_keyword'     => $product_query->row['meta_keyword'],
                'tag'              => $product_query->row['tag'],
                'model'            => $product_query->row['model'],
                'ean'              => $product_query->row['ean'],
                'price'            => $product_query->row['price'],
                'keyword'          => $product_query->row['seo_keyword'],  // SEO keyword from seo_url table
                'category_ids'     => $product_query->row['category_ids'] ? $product_query->row['category_ids'] : ''

            );
        }

        return false;
    }
    private function cleanHtml($html) {
        if (empty($html)) {
            return '';
        }

        // First decode any existing HTML entities to prevent double-encoding
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove any null bytes or invalid UTF-8 sequences
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');

        // Optional: Remove any empty paragraphs that might have been created
        $html = preg_replace('/<p[^>]*>[\s|&nbsp;]*<\/p>/', '', $html);

        return $html;
    }

    private function transformOpencartToOdoo($product) {
        // Add oc_product_id, oc_sync_date, oc_sync_state to existing transformation
        $mapping = $this->getProductMapping(null, $product['product_id']);
        $sync_state = $mapping ? self::SYNC_STATUS[$mapping['is_synch']] : 'not_synced';
//        $tags = explode(',', $product['tag']);

        return array(
            // Existing fields...
            'name' => $product['name'],
            'default_code' => $product['model'],
//            'barcode' => $product['ean'],
            'price' => $product['price'],
            'type' => 'product',
            'oc_description' => $this->cleanHtml($product['description']),
//            'oc_tag' => array(6,0, $tags), /the tima has not come to make it m2m field
            'oc_tag' => $product['tag'],
            // oc_website_meta_title / oc_website_meta_description are generated and owned by
            // Odoo's SEO module (opencart_product_seo) - never push OpenCart's copy back in,
            // or it overwrites Odoo's generated value and locks it to 'manual' origin.
            // 'oc_website_meta_title' => $product['meta_title'],
            // 'oc_website_meta_description' => $product['meta_description'],
            'oc_website_meta_keyword' => $product['meta_keyword'],

            // New fields
            'oc_product_id' => (int)$product['product_id'],
            'oc_sync_date' => date('Y-m-d H:i:s', time()),
            'oc_sync_state' => $sync_state,

            // Additional fields
            'oc_seo_url' => $product['keyword'], // Use the keyword from getOpenCartProduct
            'oc_category_ids' => $product['category_ids']
        );
    }

    protected function createOdooProduct($data) {
        $odoo_connect = $this->model_extension_module_odoo_connector->getConnection();
        $models = $odoo_connect['client'];
        $db_name = $odoo_connect['db'];
        $uid = $odoo_connect['userId'];
        $password = $odoo_connect['pwd'];

        // Get target language
        $lang_code = $this->getOdooLanguage($models, $db_name, $uid, $password);

        if ($this->debug) $this->log->write("Info odoo_product_mapping createOdooProduct: Creating new product\nProduct data: " .
            serialize($data) . "\nTarget language: " . $lang_code);

        // Extract barcode from data to handle separately
        $barcode = null;
        if (isset($data['barcode'])) {
            $barcode = $data['barcode'];
            unset($data['barcode']); // Remove barcode from template data
        }

        $context = array('lang' => $lang_code);
        // Create product template with context
        $template_id = $models->execute_kw($db_name, $uid, $password,
            'product.template', 'create',
            array($data),
            array('context' => $context)
        );

        if (isset($template_id['faultCode'])) {
            $this->log->write("Error odoo_product_mapping createOdooProduct: Odoo fault - Code: " . $template_id['faultCode'] . ", Message: " . $template_id['faultString']);
            throw new Exception($template_id['faultString'] ?: 'Failed to create product template in Odoo');
        }

        // Get the created variant first
        $variant_ids = $models->execute_kw($db_name, $uid, $password,
            'product.product', 'search',
            array(array(array('product_tmpl_id', '=', $template_id))),
            array('limit' => 1)
        );

        if (!$variant_ids || isset($variant_ids['faultCode'])) {
            $error_msg = isset($variant_ids['faultString']) ? $variant_ids['faultString'] : 'Failed to get product variant from Odoo';
            $this->log->write("Error odoo_product_mapping createOdooProduct: " . $error_msg);
            throw new Exception($error_msg);
        }

//        // Create barcode if provided
//        if ($barcode) {
//            try {
//                $barcode_data = array(
//                    'name' => $barcode,
//                    'product_tmpl_id' => (int)$template_id,
//                    'product_id' => (int)$variant_ids[0],
//                    'sequence' => 0  // Primary barcode
//                );
//
//                $barcode_result = $models->execute_kw($db_name, $uid, $password,
//                    'product.barcode', 'create',
//                    array($barcode_data)
//                );
//
////                $this->log->write("Info odoo_product_mapping createOdooProduct: Barcode creation result: " .
////                    serialize($barcode_result));
//
//                if (isset($barcode_result['faultCode'])) {
//                    $this->log->write("Warning odoo_product_mapping createOdooProduct: Failed to create barcode but continuing: " .
//                        $barcode_result['faultString']);
//                }
//            } catch (Exception $e) {
//                $this->log->write("Warning odoo_product_mapping createOdooProduct: Barcode creation failed but continuing: " .
//                    $e->getMessage());
//            }
//        }

//        $this->log->write("Info odoo_product_mapping createOdooProduct: Successfully created product\n" .
//            "Template ID: " . $template_id . "\n" .
//            "Variant ID: " . $variant_ids[0]);
//
//        $this->log->write("Info odoo_product_mapping createOdooProduct: Creating new product with sync data\n" .
//            "OpenCart ID: " . $data['oc_product_id'] . "\n" .
//            "Sync State: " . $data['oc_sync_state']);

        return array($variant_ids[0], $template_id);
    }

    private function updateOdooProduct($template_id, $product_data) {
        $odoo_connect = $this->model_extension_module_odoo_connector->getConnection();
        $models = $odoo_connect['client'];
        $db_name = $odoo_connect['db'];
        $uid = $odoo_connect['userId'];
        $password = $odoo_connect['pwd'];

        // Get target language
        $lang_code = $this->getOdooLanguage($models, $db_name, $uid, $password);

        if ($this->debug) $this->log->write("Info odoo_product_mapping updateOdooProduct: Updating template " . $template_id .
            "\nProduct data: " . serialize($product_data) . "\nTarget language: " . $lang_code);

        try {
            // First read the existing template data
            $existing_data = $models->execute_kw($db_name, $uid, $password,
                'product.template', 'read',
                array(array((int)$template_id)),
                array('fields' => array(
                    'name', 'default_code', 'price', 'type',
                    'oc_description', 'oc_tag', 'oc_product_id',
                    'oc_sync_date', 'oc_sync_state',
                    'oc_website_meta_title', 'oc_website_meta_description',
                    'oc_website_meta_keyword',
                    'oc_seo_url',
                    'oc_category_ids'
                ))
            );

            if (empty($existing_data)) {
                throw new Exception("Template ID " . $template_id . " not found in Odoo");
            }

            // Prepare update data - only include fields that have changed
            $update_data = array();
            foreach ($product_data as $key => $value) {
                // Skip null values
                if (is_null($value)) {
                    continue;
                }

                // Special handling for numeric fields
                if (in_array($key, array('price'))) {
                    if (!isset($existing_data[0][$key]) || abs($existing_data[0][$key] - $value) > 0.01) {
                        $update_data[$key] = $value;
                    }
                }
                // Normal comparison for other fields
                else if (!isset($existing_data[0][$key]) || $existing_data[0][$key] !== $value) {
                    $update_data[$key] = $value;
                }
            }

            // Only perform update if there are changes
            if (!empty($update_data)) {
                if ($this->debug) $this->log->write("Info odoo_product_mapping updateOdooProduct: Updating fields: " .
                    serialize($update_data));

                $result = $models->execute_kw($db_name, $uid, $password,
                    'product.template', 'write',
                    array(
                        array((int)$template_id),
                        $update_data
                    ),
                    array('context' => array('lang' => $lang_code))
                );

                if (isset($result['faultCode'])) {
                    $this->log->write("Error odoo_product_mapping updateOdooProduct: Odoo fault - Code: " . $result['faultCode'] . ", Message: " . $result['faultString']);
                    throw new Exception($result['faultString'] ?:
                        'Failed to update product in Odoo: ' . serialize($update_data));
                }

                // Verify the update
                $updated_data = $models->execute_kw($db_name, $uid, $password,
                    'product.template', 'read',
                    array(array((int)$template_id)),
                    array('fields' => array_keys($update_data))
                );

                if (isset($updated_data['faultCode'])) {
                    throw new Exception("Failed to verify update: " . $updated_data['faultString']);
                }

                if ($this->debug) $this->log->write("Info odoo_product_mapping updateOdooProduct: Update successful. Verification data: " .
                    serialize($updated_data));

                return true;
            } else {
                if ($this->debug)  $this->log->write("Info odoo_product_mapping updateOdooProduct: No changes needed for template " .
                    $template_id);
                return true;
            }

        } catch (Exception $e) {
            $this->log->write("Error odoo_product_mapping updateOdooProduct: " . $e->getMessage() .
                "\nTemplate ID: " . $template_id .
                "\nProduct Data: " . serialize($product_data));
            throw $e;
        }
    }

    private function getProductMapping($odoo_product_id = null, $opencart_product_id = null, $language_id = null) {
        // Get language_id from parameter or fall back to config
        if ($language_id === null) {
            $language_id = (int)$this->config->get('config_language_id');
        } else {
            $language_id = (int)$language_id;
        }

        $sql = "SELECT opvm.*, p.model as opencart_model, pd.name as opencart_name, p.weight as weight, p.length as length, p.height as height, p.width as width
            FROM " . DB_PREFIX . "odoo_product_variant_map opvm
            LEFT JOIN " . DB_PREFIX . "product p ON p.product_id = opvm.opencart_product_id
            LEFT JOIN " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id
            WHERE pd.language_id = '" . $language_id . "'";

        if ($odoo_product_id) {
            $sql .= " AND opvm.odoo_product_id = '" . (int)$odoo_product_id . "'";
        }

        if ($opencart_product_id) {
            $sql .= " AND opvm.opencart_product_id = '" . (int)$opencart_product_id . "'";
        }

        $query = $this->db->query($sql);
        return $query->num_rows ? $query->row : false;
    }

    function createProductMapping($odoo_product_id, $opencart_product_id, $odoo_template_id, $language_id = null) {
        // Get language_id from parameter or fall back to config
        if ($language_id === null) {
            $language_id = (int)$this->config->get('config_language_id');
        } else {
            $language_id = (int)$language_id;
        }

        // Get OpenCart product data including options if any
        $product_query = $this->db->query("SELECT p.model, pd.name, pov.product_option_value_id,
        CONCAT(od.name, ': ', ovd.name) as option_description
        FROM " . DB_PREFIX . "product p
        LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $language_id . "')
        LEFT JOIN " . DB_PREFIX . "product_option_value pov ON (p.product_id = pov.product_id)
        LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id AND ovd.language_id = '" . $language_id . "')
        LEFT JOIN " . DB_PREFIX . "option_description od ON (ovd.option_id = od.option_id AND od.language_id = '" . $language_id . "')
        WHERE p.product_id = '" . (int)$opencart_product_id . "'");

        if ($product_query->num_rows) {
            $product_data = $product_query->row;
            $option_id = isset($product_data['product_option_value_id']) ?
                $product_data['product_option_value_id'] : -1;
            $option_description = !empty($product_data['option_description']) ?
                $product_data['option_description'] : $product_data['name'];

            if ($this->debug) $this->log->write("Info odoo_product_mapping createProductMapping: Creating mapping for OpenCart product " .
                $opencart_product_id . " with Odoo product " . $odoo_product_id . " and template " . $odoo_template_id);

            // Start with pending status (2)
            $this->db->query("INSERT INTO " . DB_PREFIX . "odoo_product_variant_map SET 
                odoo_product_id = '" . (int)$odoo_product_id . "',
                opencart_product_id = '" . (int)$opencart_product_id . "',
                opencart_product_option_id = '" . (int)$option_id . "',
                opencart_product_name = '" . $this->db->escape($product_data['model']) . "',
                opencart_product_option_description = '" . $this->db->escape($option_description) . "',
                odoo_product_tmpl_id = '" . (int)$odoo_template_id . "',
                created_by = 'sync_system',
                created_on = NOW(),
                is_synch = 2 
                ON DUPLICATE KEY UPDATE 
                opencart_product_option_id = '" . (int)$option_id . "',
                opencart_product_name = '" . $this->db->escape($product_data['model']) . "',
                opencart_product_option_description = '" . $this->db->escape($option_description) . "',
                odoo_product_tmpl_id = '" . (int)$odoo_template_id . "',
                created_by = 'sync_system',
                is_synch = 2");

            // Log the initial mapping with pending status
            $this->logSync($opencart_product_id, "Initial product mapping created", "pending", "to_odoo");

            if ($this->debug) $this->log->write("Info odoo_product_mapping createProductMapping: Mapping created successfully");
        } else {
            $this->log->write("Error odoo_product_mapping createProductMapping: OpenCart product " . $opencart_product_id . " not found");
            throw new Exception('OpenCart product not found');
        }
    }


    public function getTotalMappedProducts($data = array()) {
        // Get language_id from data array or fall back to config
        $language_id = isset($data['language_id']) ? (int)$data['language_id'] : (int)$this->config->get('config_language_id');

        $sql = "SELECT COUNT(DISTINCT opm.id) AS total FROM " . DB_PREFIX . "odoo_product_variant_map opm";
        $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (opm.opencart_product_id = p.product_id)";
        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $language_id . "')";
        $sql .= " LEFT JOIN " . DB_PREFIX . "product_option_value pov ON (opm.opencart_product_option_id = pov.product_option_value_id)";
        $sql .= " LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id AND ovd.language_id = '" . $language_id . "')";
        $sql .= " LEFT JOIN " . DB_PREFIX . "option_description od ON (ovd.option_id = od.option_id AND od.language_id = '" . $language_id . "')";

        $sql .= " WHERE 1=1";


        if (!empty($data['filter_product_id'])) {
            $sql .= " AND p.product_id = '" . $this->db->escape($data['filter_product_id']) . "'";
        }
        if (!empty($data['filter_product'])) {
            $sql .= " AND pd.name LIKE '" . $this->db->escape($data['filter_product']) . "%'";
        }

        if (!empty($data['filter_model'])) {
            $sql .= " AND p.model LIKE '" . $this->db->escape($data['filter_model']) . "%'";
        }

        if (!empty($data['filter_odoo_ref'])) {
            $sql .= " AND opm.odoo_product_id = '" . (int)$data['filter_odoo_ref'] . "'";
        }

        if (isset($data['filter_sync_status']) && $data['filter_sync_status'] !== '') {
            $sql .= " AND opm.is_synch = '" . (int)$data['filter_sync_status'] . "'";
        }

        $query = $this->db->query($sql);

        return $query->row['total'];
    }

    // TODO lets ponder about syncronization status storage Now we have it in two tables
    // odoo_product_variant_map and in odoo_product_sync_log. We take the last+product status in
    // very complcated SQL requestm but it might be better to store the status as a correspondent status number in
    // odoo_product_variant, so we can keep the code lighter and easier to read?
    public function getMappedProducts($data = array()) {
        // Get language_id from data array or fall back to config
        $language_id = isset($data['language_id']) ? (int)$data['language_id'] : (int)$this->config->get('config_language_id');

        $sql = "SELECT opm.*, p.product_id, p.model, p.price, pd.name AS product_name,";
        $sql .= " od.name AS option_name, ovd.name AS option_value_name";
        $sql .= " FROM " . DB_PREFIX . "odoo_product_variant_map opm";
        $sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (opm.opencart_product_id = p.product_id)";
        $sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $language_id . "')";
        $sql .= " LEFT JOIN " . DB_PREFIX . "product_option_value pov ON (opm.opencart_product_option_id = pov.product_option_value_id)";
        $sql .= " LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id AND ovd.language_id = '" . $language_id . "')";
        $sql .= " LEFT JOIN " . DB_PREFIX . "option_description od ON (ovd.option_id = od.option_id AND od.language_id = '" . $language_id . "')";

        $sql .= " WHERE 1=1";

//        if ($this->debug) $this->log->write("Info odoo_product_mapping getMappedProducts: " . serialize($data));

        if (!empty($data['filter_product_id'])) {
            $sql .= " AND p.product_id LIKE '" . $this->db->escape($data['filter_product_id']) . "%'";
        }

        if (!empty($data['filter_product'])) {
            $sql .= " AND pd.name LIKE '" . $this->db->escape($data['filter_product']) . "%'";
        }

        if (!empty($data['filter_model'])) {
            $sql .= " AND p.model LIKE '" . $this->db->escape($data['filter_model']) . "%'";
        }

        if (!empty($data['filter_odoo_ref'])) {
            $sql .= " AND opm.odoo_product_id = '" . (int)$data['filter_odoo_ref'] . "'";
        }

        if (isset($data['filter_sync_status']) && $data['filter_sync_status'] !== '') {
            $sql .= " AND opm.is_synch = '" . (int)$data['filter_sync_status'] . "'";
        }

        $sort_data = array(
            'p.product_id',
            'pd.name',
            'p.model',
            'p.price',
            'opm.odoo_product_id',
            'opm.is_synch'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY pd.name";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        // Convert numeric status to text status for display
        $results = array();
        foreach ($query->rows as $row) {
            $row['sync_status_text'] = $this->getTextStatus($row['is_synch']);
            $results[] = $row;
        }

        return $results;
    }

    public function logSync($product_id, $message, $status, $direction) {
        // Store text status in log
        $this->db->query("INSERT INTO " . DB_PREFIX . "odoo_product_sync_log SET 
            product_id = '" . (int)$product_id . "',
            sync_direction = '" . $this->db->escape($direction) . "',
            status = '" . $this->db->escape($status) . "',
            message = '" . $this->db->escape($message) . "',
            created_on = NOW()");

        // Update numeric status in map table
        $numericStatus = $this->getNumericStatus($status);
        $this->db->query("UPDATE " . DB_PREFIX . "odoo_product_variant_map SET 
            is_synch = '" . (int)$numericStatus . "'
            WHERE opencart_product_id = '" . (int)$product_id . "'");

        if ($direction == 'to_odoo') {
            // Update Odoo status if we have mapping
            $mapping = $this->getProductMapping(null, $product_id);
            if ($mapping && $mapping['odoo_product_tmpl_id']) {
                try {
                    // Get Odoo connection
                    $odoo_connect = $this->model_extension_module_odoo_connector->getConnection();

                    // Check if connection was successful
                    if (!$odoo_connect || !isset($odoo_connect['status']) || !$odoo_connect['status']) {
                        throw new Exception('Failed to establish Odoo connection');
                    }

                    $models = $odoo_connect['client'];
                    $db_name = $odoo_connect['db'];
                    $uid = $odoo_connect['userId'];
                    $password = $odoo_connect['pwd'];

                    $update_data = array(
                        'oc_sync_state' => $status,
                        'oc_sync_date' => date('Y-m-d H:i:s', time())
                    );

                    $result = $models->execute_kw($db_name, $uid, $password,
                        'product.template', 'write',
                        array(
                            array((int)$mapping['odoo_product_tmpl_id']),
                            $update_data
                        )
                    );

                    if (isset($result['faultCode'])) {
                        throw new Exception($result['faultString']);
                    }

                } catch (Exception $e) {
                    $this->log->write("Error odoo_product_mapping logSync: Failed to update Odoo sync status: " . $e->getMessage());
                }
            }
        }
        if ($this->debug) $this->log->write("Info odoo_product_mapping logSync: Product ID " . $product_id .
            " status updated to " . $status . " (" . $numericStatus . ")");
    }

    private function getOdooLanguage($models, $db_name, $uid, $password) {
        try {
            // Search for Russian language
            $lang_ids = $models->execute_kw($db_name, $uid, $password,
                'res.lang', 'search',
                array(array(
                    array('code', '=', 'ru_RU'),
                    array('active', '=', true)
                )),
                array('limit' => 1)
            );

            if (!empty($lang_ids)) {
                if ($this->debug) $this->log->write("Info odoo_product_mapping getOdooLanguage: Found Russian language");
                return 'ru_RU';
            }

            // If Russian not found or not active, fall back to English
            if ($this->debug) $this->log->write("Info odoo_product_mapping getOdooLanguage: Russian language not found, falling back to English");
            return 'en_US';
        } catch (Exception $e) {
            $this->log->write("Warning odoo_product_mapping getOdooLanguage: " . $e->getMessage() . " - falling back to English");
            return 'en_US';
        }
    }
    public function massSyncProductsToOdoo($product_ids, $batch_size = 50) {
        $total = count($product_ids);
        $processed = 0;
        $success = 0;
        $failed = 0;
        $status = array();
        $this->load->language('extension/module/odoo_product_mapping.php');

        if ($this->debug) $this->log->write('Model odoo_product_mapping massSyncProductsToOdoo batch_size: '. serialize($batch_size));
        // Process in batches
        foreach (array_chunk($product_ids, $batch_size) as $batch) {
            foreach ($batch as $product_id) {

                try {
                    $result = $this->syncProductToOdoo($product_id);
                    if (isset($result['success'])) {
                        $success++;
                        $status[] = sprintf($this->language->get('text_product_synced'), $product_id);
                    } else {
                        $failed++;
                        $status[] = sprintf($this->language->get('text_product_failed'), $product_id);
                    }
                } catch (Exception $e) {
                    $failed++;
                    $status[] = sprintf($this->language->get('text_product_error'), $product_id, $e->getMessage());
                    $this->log->write("Error massSyncProductsToOdoo: Product ID " . $product_id . ": " . $e->getMessage());
                }
                $processed++;
            }
        }

        return array(
            'processed' => $processed,
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'complete' => ($processed >= $total),
            'status' => implode('<br/>', array_slice($status, -5)) // Show last 5 status messages
        );
    }

    // Individual product syncing history information
    //
    public function getProductSyncHistory($product_id, $data = array()) {
        // Get language_id from data array or fall back to config
        $language_id = isset($data['language_id']) ? (int)$data['language_id'] : (int)$this->config->get('config_language_id');

        $sql = "SELECT psl.*, pd.name as product_name, p.model
                FROM " . DB_PREFIX . "odoo_product_sync_log psl
                LEFT JOIN " . DB_PREFIX . "product p ON (psl.product_id = p.product_id)
                LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
                WHERE psl.product_id = '" . (int)$product_id . "'
                AND pd.language_id = '" . $language_id . "'";

        $sort_data = array(
            'psl.created_on',
            'psl.sync_direction',
            'psl.status'
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY psl.created_on";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getTotalSyncHistoryEntries($product_id) {
        $query = $this->db->query("SELECT COUNT(*) AS total 
            FROM " . DB_PREFIX . "odoo_product_sync_log 
            WHERE product_id = '" . (int)$product_id . "'");

        return $query->row['total'];
    }

    public function getProductInfo($product_id, $language_id = null) {
        // Get language_id from parameter or fall back to config
        if ($language_id === null) {
            $language_id = (int)$this->config->get('config_language_id');
        } else {
            $language_id = (int)$language_id;
        }

        $query = $this->db->query("SELECT p.*, pd.name,
            opm.odoo_product_id, opm.odoo_product_tmpl_id, opm.is_synch
            FROM " . DB_PREFIX . "product p
            LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
            LEFT JOIN " . DB_PREFIX . "odoo_product_variant_map opm ON (p.product_id = opm.opencart_product_id)
            WHERE p.product_id = '" . (int)$product_id . "'
            AND pd.language_id = '" . $language_id . "'");

        if ($query->num_rows) {
            $product_data = $query->row;
            $product_data['sync_status_text'] = $this->getTextStatus($product_data['is_synch']);
            return $product_data;
        }

        return false;
    }

    public function updateSyncStatus(){
        $products_map = $this->db->query("SELECT `opencart_product_id`, `is_synch` FROM ".DB_PREFIX ."odoo_product_variant_map");
        foreach ($products_map->rows as $product){
            $status_log = $this->db->query("SELECT `status` FROM ".DB_PREFIX ."odoo_product_sync_log WHERE product_id = ".(int)$product['opencart_product_id'] ." ORDER BY id DESC LIMIT 1");
            if ($status_log->num_rows && $status_log->row['status'] != $this->getTextStatus($product['is_synch'])){
                $is_sync = $this->getNumericStatus($status_log->row['status']);
                $quiery= $this->db->query("UPDATE ".DB_PREFIX ."odoo_product_variant_map SET `is_synch` = ".(int)$is_sync.
                    " WHERE `opencart_product_id` = ".(int)$product['opencart_product_id'] );
            }
        }
    }

}