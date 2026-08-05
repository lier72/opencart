<?php
/**
 * Created by PhpStorm.
 *
 * API Extension to provide additional functionality for Odoo - Opencart connector
 * Adds edit() and create() endpoints
 * uses some helper functions such as gets Manufacturer from the product name etc.
 *
 * User: max
 * Date: 19/12/24
 * Time: 16:28
 */

class ControllerApiProduct extends Controller {
    private $error = array();
    protected $debug = False;

    private $default_language_id;
    private $adminProduct;


    const SYNC_STATUS = [
        0 => 'not_synced',
        1 => 'synced',
        2 => 'pending',
        3 => 'error'
    ];

    function __construct($registry)
    {
        parent::__construct($registry);
        $this->debug = $this->getDebug();
    }

    /**
     * Edit a product via the API.
     *
     * This method allows updating a product's details in the catalog. It accepts JSON input containing the
     * product details to be updated. The method performs validation, updates the product, and logs the operation.
     *
     * **Request Parameters**:
     * - `product_id` (GET, optional): The ID of the product to edit. If not provided, the product ID is assumed to be `0`.
     * - `data` (JSON, required): The JSON input containing product details to update.
     *      - `default_code` (string, optional): Updates the product's model.
     *      - `barcode` (string, optional): Updates the product's EAN (barcode).
     *      - `name` (string, optional): Updates the product's name in the configured language.
     *      - `oc_description` (string, optional): Updates the OpenCart product description.
     *      - `website_meta_title` (string, optional): Updates the website meta title.
     *      - `website_meta_description` (string, optional): Updates the website meta description.
     *      - `oc_tag` (string, optional): Updates the product tags.
     *
     * **Output**:
     * - On success: Returns a JSON object with a `success` message.
     * - On failure: Returns a JSON object with an `error` message.
     *
     * **Example Request**:
     * ```json
     * {
     *     "data": {
     *         "default_code": "PRD123",
     *         "barcode": "1234567890123",
     *         "name": "Updated Product Name",
     *         "oc_description": "Updated product description.",
     *         "website_meta_title": "Updated Meta Title",
     *         "website_meta_description": "Updated Meta Description",
     *         "oc_tag": "tag1, tag2"
     *     }
     * }
     * ```
     *
     * **Example Response**:
     * - Success:
     *   ```json
     *   {
     *       "success": "The product has been updated successfully."
     *   }
     *   ```
     * - Error:
     *   ```json
     *   {
     *       "error": "Product not found."
     *   }
     *   ```
     *
     * @throws \Exception If an error occurs during the update process.
     * @return void Outputs a JSON response directly to the client.
     */
    public function edit() {
        $this->load->language('api/product');
        $this->load->model('catalog/product');

        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            // Get JSON input
            $input_json = json_decode(file_get_contents('php://input'), true);
            $this->log->write("API Product Edit - Received data: " . print_r($input_json, true));

            if ($this->validateEdit($input_json)) {
                $product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
                $this->log->write("API Product Edit - Product Id: " . serialize($product_id));
                // this has to be admin function as catalog function as catalog verdion is limited to wrok only with enabled
                // products
                $product_info = $this->getProduct($product_id);
                $this->log->write("API Product Edit - Product Info: " . serialize($product_info));

                if ($product_info) {
                    try {
                        // Keep the API field names intact. editProduct() applies
                        // language-specific values only to config_language_id.
                        $update_data = $input_json['data'];

                        if (array_key_exists('oc_category_ids', $update_data)) {
                            $update_data['oc_category_ids'] = $this->normalizeCategoryIds(
                                $update_data['oc_category_ids']
                            );
                        }

                        // Update product using rewritten here model
                        $edit_result = $this->editProduct($product_id, $update_data);

                        $json['success'] = $this->language->get('text_success');
                        $json['language_id'] = $edit_result['language_id'];

                    } catch (Exception $e) {
                        $this->log->write("API Product Edit - Error: " . $e->getMessage());
                        $json['error'] = $e->getMessage();

                        // Log sync error using correct method name
                        $this->logSync($product_id,
                            'Error updating from Odoo: ' . $e->getMessage(),
                            'error',
                            'from_odoo'
                        );
                    }
                } else {
                    $json['error'] = $this->language->get('error_not_found');
                }
            } else {
                $json['error'] = $this->error;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Return the complete category catalogue for the configured store/language.
     *
     * Odoo uses this read-only endpoint to mirror OpenCart category IDs, names,
     * paths, status, and parent relations.
     */
    public function categories() {
        $this->load->language('api/product');
        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $store_id = (int)$this->config->get('config_store_id');
            $language_id = (int)$this->config->get('config_language_id');

            $language_query = $this->db->query(
                "SELECT language_id, code FROM " . DB_PREFIX . "language " .
                "WHERE language_id = '" . $language_id . "' LIMIT 1"
            );
            $language_code = $language_query->num_rows
                ? $language_query->row['code']
                : '';

            $sql = "SELECT c.category_id, c.parent_id, cd.name, " .
                "c.status, c.sort_order, " .
                "(SELECT GROUP_CONCAT(path_cd.name ORDER BY cp.level " .
                "SEPARATOR ' > ') " .
                "FROM " . DB_PREFIX . "category_path cp " .
                "INNER JOIN " . DB_PREFIX . "category_description path_cd " .
                "ON (path_cd.category_id = cp.path_id " .
                "AND path_cd.language_id = '" . $language_id . "') " .
                "WHERE cp.category_id = c.category_id) AS path " .
                "FROM " . DB_PREFIX . "category c " .
                "INNER JOIN " . DB_PREFIX . "category_description cd " .
                "ON (cd.category_id = c.category_id " .
                "AND cd.language_id = '" . $language_id . "') " .
                "INNER JOIN " . DB_PREFIX . "category_to_store c2s " .
                "ON (c2s.category_id = c.category_id " .
                "AND c2s.store_id = '" . $store_id . "') " .
                "ORDER BY c.parent_id, c.sort_order, LCASE(cd.name), " .
                "c.category_id";

            $query = $this->db->query($sql);
            $categories = array();

            foreach ($query->rows as $row) {
                $categories[] = array(
                    'category_id' => (int)$row['category_id'],
                    'parent_id' => (int)$row['parent_id'],
                    'name' => $row['name'],
                    'path' => $row['path'] ? $row['path'] : $row['name'],
                    'status' => (bool)$row['status'],
                    'sort_order' => (int)$row['sort_order']
                );
            }

            $json = array(
                'success' => true,
                'store_id' => $store_id,
                'language' => array(
                    'language_id' => $language_id,
                    'code' => $language_code
                ),
                'categories' => $categories
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Validate json data for edit() method of the class
     *
     * @param $input_json
     * @return bool
     */
    protected function validateEdit($input_json) {
        $this->log->write("API Product Edit - validateEdit input_json: " . serialize($input_json));


        if (!isset($input_json['data'])) {
            $this->error['warning'] = $this->language->get('error_data');
            return false;
        }

        $data = $input_json['data'];

        // Validate product ID
        if (!isset($this->request->get['product_id']) || !is_numeric($this->request->get['product_id'])) {
            $this->error['warning'] = $this->language->get('error_product');
            return false;
        }

        // Validate fields if present
        if (isset($data['name']) && ((utf8_strlen($data['name']) < 3) || (utf8_strlen($data['name']) > 255))) {
            $this->error['name'] = $this->language->get('error_name');
        }

        if (isset($data['default_code']) && ((utf8_strlen($data['default_code']) < 1) || (utf8_strlen($data['default_code']) > 64))) {
            $this->error['model'] = $this->language->get('error_model');
        }

        if (!empty($data['barcode']) && !preg_match('/^[0-9]{8,13}$/', $data['barcode'])) {
            $this->error['barcode'] = $this->language->get('error_barcode');
        }

        if (isset($data['price']) == 0) {
            $this->error['price'] = $this->language->get('error_zero_price');
        }

        // Validate SEO URL if present
        if (isset($data['oc_seo_url'])) {
            if (utf8_strlen($data['oc_seo_url']) > 768) {
                $this->error['oc_seo_url'] = $this->language->get('error_seo_url_length');
            }

            // Check if SEO URL is unique across all stores and languages
            $query = $this->db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "seo_url
            WHERE keyword = '" . $this->db->escape($data['oc_seo_url']) . "'
            AND query != 'product_id=" . (int)$this->request->get['product_id'] . "'");

            if ($query->row['total'] > 0) {
                $this->error['oc_seo_url'] = $this->language->get('error_seo_url_exists');
            }
        }

        // Add category validation
        if (array_key_exists('oc_category_ids', $data)) {
            $category_ids = $this->normalizeCategoryIds(
                $data['oc_category_ids']
            );
            if ($category_ids === null) {
                $this->error['category'] = $this->language->get('error_category_format');
            } else {
                foreach ($category_ids as $category_id) {
                    if (!$this->validateCategory($category_id)) {
                        $this->error['category'] = sprintf(
                            $this->language->get('error_category_not_found'),
                            $category_id
                        );
                        break;
                    }
                }
            }
        }

        return !$this->error;
    }

    /**
     * Create a new product via the API.
     *
     * This method allows creating a new product in the catalog with full support for product variants/options.
     * It accepts JSON input containing the product details and automatically creates:
     * - The base product
     * - Product options (e.g., Size, Color) if provided
     * - Product option values (e.g., Small, Medium, Large)
     * - Odoo variant mappings for synchronization
     *
     * **Request Parameters**:
     * - `data` (JSON, required): The JSON input containing product details.
     *      - `odoo_product_id` (int, optional): Odoo product variant ID for mapping
     *      - `odoo_product_tmpl_id` (int, optional): Odoo product template ID for mapping
     *      - `name` (string, required): Product name
     *      - `default_code` (string, required): Product model/SKU
     *      - `price` (float, optional): Product price
     *      - `barcode` (string, optional): Product EAN/barcode
     *      - `oc_description` (string, optional): HTML product description
     *      - `oc_tag` (string, optional): Product tags (comma-separated)
     *      - `oc_website_meta_title` (string, optional): SEO meta title
     *      - `oc_website_meta_description` (string, optional): SEO meta description
     *      - `oc_website_meta_keyword` (string, optional): SEO keywords
     *      - `oc_seo_url` (string, optional): SEO-friendly URL slug
     *      - `oc_category_ids` (array|string, optional): Category IDs. Arrays
     *        are canonical; comma-separated values remain supported.
     *      - `product_length` (float, optional): Product length in cm
     *      - `product_width` (float, optional): Product width in cm
     *      - `product_height` (float, optional): Product height in cm
     *      - `weight` (float, optional): Product weight in kg
     *      - `options` (object, optional): Product options/variants structure
     *          - `odoo_attribute_id` (int, required): Odoo attribute ID (from product.attribute table)
     *              This ID is mapped to OpenCart option_id via odoo_config table
     *              Config entry format: key="odoo_attribute_{id}", value="{opencart_option_id}"
     *          - `option_name` (string, optional): Attribute name for logging (e.g., "Size")
     *          - `option_values` (array): Array of option values
     *              - `odoo_variant_id` (int): Odoo product variant ID
     *              - `default_code` (string): Variant SKU/model
     *              - `option_value_name` (string): Value display name (e.g., "Small")
     *              - `option_value_code` (string, required): Value code used for matching OpenCart option values
     *                  Matched using LIKE '%{code}%' against OpenCart option value names
     *
     * **Output**:
     * - On success: Returns a JSON object with `success` message and `product_id`
     * - On failure: Returns a JSON object with an `error` message
     *
     * **Example Request**:
     * ```json
     * {
     *     "data": {
     *         "odoo_product_id": 456,
     *         "odoo_product_tmpl_id": 123,
     *         "name": "T-Shirt ABC",
     *         "price": 29.99,
     *         "default_code": "ABC",
     *         "oc_description": "<p>Cotton t-shirt</p>",
     *         "oc_tag": "casual, apparel",
     *         "oc_website_meta_title": "ABC T-Shirt",
     *         "oc_website_meta_description": "Comfortable cotton t-shirt",
     *         "oc_website_meta_keyword": "t-shirt, cotton, casual",
     *         "oc_seo_url": "abc-tshirt",
     *         "oc_category_ids": [12, 34],
     *         "product_length": 0.0,
     *         "product_width": 0.0,
     *         "product_height": 0.0,
     *         "weight": 0.2,
     *         "barcode": "1234567890123",
     *         "options": {
     *             "odoo_attribute_id": 5,
     *             "option_name": "Size",
     *             "option_values": [
     *                 {
     *                     "odoo_variant_id": 789,
     *                     "default_code": "ABC_S",
     *                     "option_value_name": "Small",
     *                     "option_value_code": "S"
     *                 },
     *                 {
     *                     "odoo_variant_id": 790,
     *                     "default_code": "ABC_M",
     *                     "option_value_name": "Medium",
     *                     "option_value_code": "M"
     *                 },
     *                 {
     *                     "odoo_variant_id": 791,
     *                     "default_code": "ABC_L",
     *                     "option_value_name": "Large",
     *                     "option_value_code": "L"
     *                 }
     *             ]
     *         }
     *     }
     * }
     * ```
     *
     * **Example Response**:
     * - Success:
     *   ```json
     *   {
     *       "success": "The product has been created successfully.",
     *       "product_id": 123
     *   }
     *   ```
     * - Error:
     *   ```json
     *   {
     *       "error": "Product with model ABC already exists.",
     *       "existing_product_id": 456
     *   }
     *   ```
     *
     * **Notes**:
     * - Products are created with status=0 (disabled) by default
     * - **IMPORTANT**: Before using product options, you must configure attribute mappings in odoo_config table
     *   Example: INSERT INTO ocus_odoo_config (`key`, `value`) VALUES ('odoo_attribute_5', '13');
     *   This maps Odoo attribute_id 5 to OpenCart option_id 13
     * - Option values are matched using LIKE '%{option_value_code}%' against existing OpenCart option values
     * - No new options or option values are created automatically - they must exist in OpenCart
     * - Variant mappings are stored in odoo_product_variant_map table
     * - The method creates mappings between Odoo variant IDs and OpenCart product_option_value_ids
     *
     * @throws \Exception If an error occurs during the creation process.
     * @return void Outputs a JSON response directly to the client.
     */
    public function create() {
        $this->load->language('api/product');
        // Add at the beginning of the create() method:
        $this->log->write("API Product Create - Starting product creation process");

        // Load admin models with different class names
        require_once(DIR_SYSTEM . '../admin/model/catalog/product.php');
        require_once(DIR_SYSTEM . '../admin/model/catalog/manufacturer.php');

        // Create class aliases to avoid conflicts
        if (!class_exists('ModelCatalogProductAdmin')) {
            class_alias('ModelCatalogProduct', 'ModelCatalogProductAdmin');
        }
        if (!class_exists('ModelCatalogManufacturerAdmin')) {
            class_alias('ModelCatalogManufacturer', 'ModelCatalogManufacturerAdmin');
        }

        // Instantiate admin models
        $this->adminProduct = new ModelCatalogProductAdmin($this->registry);
        $this->adminManufacturer = new ModelCatalogManufacturerAdmin($this->registry);

        $json = array();

        if (!isset($this->session->data['api_id'])) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            // Get JSON input
            $input_json = json_decode(file_get_contents('php://input'), true);
            // Add after JSON decode:
            if ($input_json === null) {
                $this->log->write("API Product Create - Invalid JSON received: " . file_get_contents('php://input'));
                $json['error'] = 'Invalid JSON data received';
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
                return;
            }
            $this->log->write("API Product Create - Received data: " . print_r($input_json, true));

            if ($this->validateCreate($input_json)) {
                try {
                    $this->default_language_id = $this->getConfiguredLanguageId();

                    // Check existing product by model/default_code
                    $existing_product = $this->checkExistingProduct($input_json['data']['default_code']);

                    if ($existing_product) {
                        $json['error'] = sprintf($this->language->get('error_product_exists'),
                            $input_json['data']['default_code']);
                        $json['existing_product_id'] = $existing_product['product_id'];
                    } else {
                        // Prepare update data
                        $product_data = $this->prepareProductData($input_json['data']);
                        $this->log->write("API Product Create - Prepared data: " . print_r($product_data, true));
                        // Create product using admin model
                        ob_start();
                        $product_id = $this->adminProduct->addProduct($product_data);
                        $output = ob_get_clean();
                        $json['message'] = $output;
                        if ($product_id) {
                            $this->log->write("API Product Create - Successfully created product ID: " . $product_id);
                            $json['success'] = $this->language->get('text_success');
                            $json['product_id'] = $product_id;
                            $json['language_id'] = $this->default_language_id;

                            // Create variant mappings if we have Odoo product information
                            if (isset($input_json['data']['odoo_product_id'])) {
                                $odoo_product_id = $input_json['data']['odoo_product_id'];
                                $odoo_template_id = isset($input_json['data']['odoo_product_tmpl_id']) ?
                                    $input_json['data']['odoo_product_tmpl_id'] : $odoo_product_id;

                                // If product has options/variants, we need to map each variant
                                if (isset($input_json['data']['options']) && !empty($input_json['data']['options']['option_values'])) {
                                    $this->createVariantMappings($product_id, $input_json['data'], $odoo_template_id);
                                } else {
                                    // For products without variants, create a simple mapping
                                    $this->createSimpleProductMapping(
                                        $odoo_product_id,
                                        $product_id,
                                        $odoo_template_id
                                    );
                                    $this->log->write("API Product Create - Created product mapping for product ID: " . $product_id);
                                }
                            }

                            // 4. Update sync status in mapping table
                            $this->logSync(
                                $product_id,
                                'Product created from Odoo API',
                                'synced',
                                'from_odoo'
                            );
                        } else {
                            throw new Exception($this->language->get('error_create_failed'));
                        }
                    }
                } catch (Exception $e) {
                    $this->log->write("API Product Create - Error: " . $e->getMessage());
                    $json['error'] = $e->getMessage();
                }
            } else {
                $json['error'] = $this->error;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    protected function getManufacturerFromName($product_name) {
        try {
            $manufacturer_name = null;
            $product_name_lower = strtolower($product_name);

            // Check for known manufacturers in the name
            if (strpos($product_name_lower, 'lining') !== false ||
                strpos($product_name_lower, 'li ning') !== false ||
                strpos($product_name_lower, 'li-ning') !== false) {
                $manufacturer_name = 'Li-Ning';
            } elseif (strpos($product_name_lower, 'yonex') !== false) {
                $manufacturer_name = 'Yonex';
            }

            if ($manufacturer_name) {
                // Try to find existing manufacturer
                $query = $this->db->query("SELECT manufacturer_id FROM " . DB_PREFIX .
                    "manufacturer WHERE name = '" . $this->db->escape($manufacturer_name) . "'");

                if ($query->num_rows) {
                    return $query->row['manufacturer_id'];
                } else {
                    // Create new manufacturer
                    $manufacturer_data = array(
                        'name' => $manufacturer_name,
                        'sort_order' => 0,
                        'manufacturer_store' => array(0) // Default store
                    );

                    return $this->adminManufacturer->addManufacturer($manufacturer_data);
                }
            }

            return 0; // Return 0 if no manufacturer found/created
        } catch (Exception $e) {
            $this->log->write("API Product Create - Error in manufacturer processing: " . $e->getMessage());
            throw new Exception('Failed to process manufacturer: ' . $e->getMessage());
        }
    }

    /**
     * @param $input_json
     * @return bool
     */
    protected function validateCreate($input_json) {
        if (!isset($input_json['data'])) {
            $this->error['data'] = 'No product data provided';
            return false;
        }
        $this->log->write('API Product Create ValidateCreate $input_json: ' . serialize($input_json), true);
        $data = $input_json['data'];

        // Required fields validation
        $required_fields = array('name', 'default_code');
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->error[$field] = sprintf($this->language->get('error_required'), $field);
            }
        }

        // Validate default_code/model format
        if (isset($data['default_code'])) {
            if ((utf8_strlen($data['default_code']) < 1) || (utf8_strlen($data['default_code']) > 64)) {
                $this->error['default_code'] = $this->language->get('error_model');
            }
        }

        if (array_key_exists('oc_category_ids', $data)) {
            $category_ids = $this->normalizeCategoryIds(
                $data['oc_category_ids']
            );
            if ($category_ids === null) {
                $this->error['category'] = $this->language->get('error_category_format');
            } else {
                foreach ($category_ids as $category_id) {
                    if (!$this->validateCategory($category_id)) {
                        $this->error['category'] = sprintf(
                            $this->language->get('error_category_not_found'),
                            $category_id
                        );
                        break;
                    }
                }
            }
        }
        $this->log->write('API Product Create ValidateCreate $input_json: ' . serialize($this->error), true);

        return !$this->error;
    }

    /**
     * @param $data
     * @return array
     * @throws Exception
     */
    protected function prepareProductData($data) {
        $product_data = array();

        // Basic product data
        $product_data['model'] = $data['default_code'];
        $product_data['ean'] = isset($data['barcode']) ? $data['barcode'] : '';
        $product_data['status'] = 0; // Initially disabled

        // Price handling
        $product_data['price'] = isset($data['price']) ? (float)$data['price'] : 0.0;

        // Dimensions and weight
        $product_data['weight'] = isset($data['weight']) ? (float)$data['weight'] : 0.0;
        $product_data['length'] = isset($data['product_length']) ? (float)$data['product_length'] : 0.0;
        $product_data['width'] = isset($data['product_width']) ? (float)$data['product_width'] : 0.0;
        $product_data['height'] = isset($data['product_height']) ? (float)$data['product_height'] : 0.0;

        // Weight class is KG
        $product_data['weight_class_id'] = 1; // Ensure this ID corresponds to kilograms
        // Length class is CM
        $product_data['length_class_id'] = 1; // Ensure this ID corresponds to centimeters

        // Handle manufacturer
        $manufacturer_id = $this->getManufacturerFromName($data['name']);
        if ($manufacturer_id) {
            $product_data['manufacturer_id'] = $manufacturer_id;
        }

        // Handle SEO URL - OC3 uses product_seo_url array with [store_id][language_id] structure
        if (isset($data['oc_seo_url']) && !empty($data['oc_seo_url'])) {
            // Create SEO URL for default store (0) and default language
            $product_data['product_seo_url'][0][$this->default_language_id] = $data['oc_seo_url'];
        } else if (isset($data['default_code'])) {
            // If no SEO URL provided, use default_code as fallback
            $product_data['product_seo_url'][0][$this->default_language_id] = strtolower($data['default_code']);
        }

        // Handle categories if provided
        if (array_key_exists('oc_category_ids', $data)) {
            $categories = $this->normalizeCategoryIds(
                $data['oc_category_ids']
            );
            if ($categories === null) {
                throw new Exception(
                    $this->language->get('error_category_format')
                );
            }
            $product_data['product_category'] = array();
            foreach ($categories as $category_id) {
                if (!$this->validateCategory($category_id)) {
                    throw new Exception(sprintf(
                        $this->language->get('error_category_not_found'),
                        $category_id
                    ));
                }
                $product_data['product_category'][] = $category_id;
            }
        }

        // Product description for the configured storefront language
        $product_data['product_description'] = array();
        $product_data['product_description'][$this->default_language_id] = array(
            'name' => $data['name'],
            'description' => isset($data['oc_description']) ? $data['oc_description'] : '',
            'tag' => isset($data['oc_tag']) ? $data['oc_tag'] : '',
            'meta_title' => isset($data['oc_website_meta_title']) ? $data['oc_website_meta_title'] : $data['name'],
            'meta_description' => isset($data['oc_website_meta_description']) ? $data['oc_website_meta_description'] : '',
            'meta_keyword' => isset($data['oc_website_meta_keyword']) ? $data['oc_website_meta_keyword'] : '',
        );

        // Store assignment
        $product_data['product_store'] = array(0);

        // Default required fields
        $product_data['quantity'] = 0;
        $product_data['minimum'] = 1;
        $product_data['subtract'] = 1;
        $product_data['stock_status_id'] = 5; // Out of stock
        $product_data['shipping'] = 1;
        $product_data['date_available'] = date('Y-m-d');
        $product_data['length_class_id'] = 1; // Default length class
        $product_data['weight_class_id'] = 1; // Default weight class
        $product_data['tax_class_id'] = 0;    // No tax class
        $product_data['sort_order'] = 0;

        // Handle product options if provided
        if (isset($data['options']) && !empty($data['options'])) {
            $product_data['product_option'] = $this->prepareProductOptions($data['options']);
            $this->log->write("API Product Create - Prepared product options: " . print_r($product_data['product_option'], true));
        }

        return $product_data;
    }

    /** Model realted methods that I do not know if i need to palce separately or keep them inside controller
     *
     */

    /**
     * Return the active language configured for the current storefront.
     *
     * Product descriptions, category names, and SEO URLs must all use this
     * language until the API gains an explicit multilingual payload.
     *
     * @return int
     * @throws Exception
     */
    protected function getConfiguredLanguageId() {
        $language_id = (int)$this->config->get('config_language_id');

        if ($language_id <= 0) {
            throw new Exception('OpenCart default language is not configured');
        }

        $query = $this->db->query(
            "SELECT language_id FROM " . DB_PREFIX . "language " .
            "WHERE language_id = '" . $language_id . "' " .
            "AND status = '1' LIMIT 1"
        );

        if (!$query->num_rows) {
            throw new Exception('OpenCart default language is not active');
        }

        return (int)$query->row['language_id'];
    }

    protected function checkExistingProduct($model) {
        $query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE model = '" .
            $this->db->escape($model) . "'");
        return $query->row;
    }

    /**
     * @param $product_id
     * @param $data
     * @return array
     * @throws Exception
     */
    public function editProduct($product_id, $data) {
        $this->load->model('catalog/product');

        try {
            // Start transaction
            $this->db->query('START TRANSACTION');
            $this->log->write("API Product Edit - Opencart Product ". $product_id . " received data: " . serialize($data));
            // 1. Get existing product data
            $existing_product = $this->getProduct($product_id);
            if (!$existing_product) {
                $this->log->write("API Product Edit - Product not found: " . $product_id);
                throw new Exception('Product not found');
            }

            $language_id = $this->getConfiguredLanguageId();

            // 2. Update main product table for sync fields
            $main_update_fields = array();

            if (isset($data['default_code'])) {
                $main_update_fields[] = "model = '" . $this->db->escape($data['default_code']) . "'";
            }

            if (isset($data['barcode'])) {
                $main_update_fields[] = "ean = '" . $this->db->escape($data['barcode']) . "'";
            }


            if (isset($data['price'])) {
                $main_update_fields[] = "price = '" . (float)$this->db->escape($data['price']) . "'";
            }

            if (!empty($main_update_fields)) {
                $sql = "UPDATE " . DB_PREFIX . "product SET " .
                    implode(', ', $main_update_fields) .
                    ", date_modified = NOW() 
                   WHERE product_id = '" . (int)$product_id . "'";

                $this->db->query($sql);
                $this->log->write("API Product Edit - Updated main product fields: " . implode(', ', $main_update_fields));
            }

            // Replace only the configured language's SEO URL. Other language
            // and store URLs must remain untouched.
            if (array_key_exists('oc_seo_url', $data)) {
                $this->db->query("DELETE FROM " . DB_PREFIX . "seo_url
                WHERE query = 'product_id=" . (int)$product_id . "'
                AND store_id = '0'
                AND language_id = '" . (int)$language_id . "'");

                if (trim((string)$data['oc_seo_url']) !== '') {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "seo_url
                    SET store_id = '0',
                        language_id = '" . (int)$language_id . "',
                        query = 'product_id=" . (int)$product_id . "',
                        keyword = '" . $this->db->escape($data['oc_seo_url']) . "'");
                }

                $this->log->write(
                    "API Product Edit - Updated language " . $language_id .
                    " SEO URL to: " . $data['oc_seo_url']
                );
            }

            // Handle category updates if provided
            if (array_key_exists('oc_category_ids', $data)) {
                $categories = $this->normalizeCategoryIds(
                    $data['oc_category_ids']
                );
                if ($categories === null) {
                    throw new Exception(
                        $this->language->get('error_category_format')
                    );
                }
                foreach ($categories as $category_id) {
                    if (!$this->validateCategory($category_id)) {
                        throw new Exception(sprintf(
                            $this->language->get('error_category_not_found'),
                            $category_id
                        ));
                    }
                }

                // Delete existing category assignments
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category 
                WHERE product_id = '" . (int)$product_id . "'");

                // Insert new category assignments
                if (!empty($categories)) {
                    foreach ($categories as $category_id) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category 
                        SET product_id = '" . (int)$product_id . "', 
                            category_id = '" . (int)$category_id . "'");
                    }
                    $this->log->write(
                        "API Product Edit - Updated categories to: " .
                        implode(',', $categories)
                    );
                }
            }

            // 3. Update the product description for config_language_id only.
            $description_update_fields = array();

            if (isset($data['name'])) {
                $description_update_fields[] = "name = '" . $this->db->escape($data['name']) . "'";
            }

            if (isset($data['oc_description'])) {
                $description_update_fields[] = "description = '" . $this->db->escape($data['oc_description']) . "'";
            }

            if (isset($data['oc_website_meta_description'])) {
                $description_update_fields[] = "meta_description = '" .
                    $this->db->escape($data['oc_website_meta_description']) . "'";
            }

            if (isset($data['oc_website_meta_title'])) {
                $description_update_fields[] = "meta_title = '" .
                    $this->db->escape($data['oc_website_meta_title']) . "'";
            }

            if (isset($data['oc_website_meta_keyword'])) {
                $description_update_fields[] = "meta_keyword = '" .
                    $this->db->escape($data['oc_website_meta_keyword']) . "'";
            }


            if (isset($data['oc_tag'])) {
                $description_update_fields[] = "tag = '" . $this->db->escape($data['oc_tag']) . "'";
            }

            if (!empty($description_update_fields)) {
                // Preserve every other language and update only the configured
                // language row.
                $check_sql = "SELECT COUNT(*) as total FROM " . DB_PREFIX .
                    "product_description WHERE product_id = '" . (int)$product_id .
                    "' AND language_id = '" . (int)$language_id . "'";
                $exists = $this->db->query($check_sql)->row['total'];

                if ($exists) {
                    // Update existing description
                    $sql = "UPDATE " . DB_PREFIX . "product_description SET " .
                        implode(', ', $description_update_fields) .
                        " WHERE product_id = '" . (int)$product_id .
                        "' AND language_id = '" . (int)$language_id . "'";
                } else {
                    // OpenCart requires a name for a valid description row.
                    if (!isset($data['name']) || trim((string)$data['name']) === '') {
                        throw new Exception(
                            'Product name is required when creating the default language description'
                        );
                    }

                    $name = $data['name'];
                    $description = isset($data['oc_description'])
                        ? $data['oc_description'] : '';
                    $tag = isset($data['oc_tag']) ? $data['oc_tag'] : '';
                    $meta_title = isset($data['oc_website_meta_title'])
                        ? $data['oc_website_meta_title'] : $name;
                    $meta_description = isset($data['oc_website_meta_description'])
                        ? $data['oc_website_meta_description'] : '';
                    $meta_keyword = isset($data['oc_website_meta_keyword'])
                        ? $data['oc_website_meta_keyword'] : '';

                    $sql = "INSERT INTO " . DB_PREFIX . "product_description SET " .
                        "product_id = '" . (int)$product_id . "', " .
                        "language_id = '" . (int)$language_id . "', " .
                        "name = '" . $this->db->escape($name) . "', " .
                        "description = '" . $this->db->escape($description) . "', " .
                        "tag = '" . $this->db->escape($tag) . "', " .
                        "meta_title = '" . $this->db->escape($meta_title) . "', " .
                        "meta_description = '" . $this->db->escape($meta_description) . "', " .
                        "meta_keyword = '" . $this->db->escape($meta_keyword) . "'";
                }

                $this->db->query($sql);
                $this->log->write(
                    "API Product Edit - Updated language " . $language_id .
                    " product description: {" .
                    implode('}, ', $description_update_fields) . "};");
            }

            // 4. Update sync status in mapping table
            $this->logSync(
                $product_id,
                'Product updated from Odoo API',
                'synced',
                'from_odoo'
            );

            // Commit transaction
            $this->db->query('COMMIT');

            return array(
                'success' => true,
                'message' => 'Product updated successfully',
                'product_id' => $product_id,
                'language_id' => $language_id
            );

        } catch (Exception $e) {
            // Rollback transaction on error
            $this->db->query('ROLLBACK');

            // Log error
            $this->log->write("API Product Edit - Error: " . $e->getMessage());

            // Log sync error
            $this->logSync(
                $product_id,
                'Error updating from Odoo: ' . $e->getMessage(),
                'error',
                'from_odoo'
            );

            throw $e;
        }
    }

    // Copied this one from admin product Class in order to save time

    /**
     * returns Product Description from product_description table
     *
     * @param $product_id
     * @return array
     * product_id
     * language_id
     * name
     * description
     * tag
     * meta_title
     * meta_description
     * meta_keyword
     */
    public function getProductDescriptions($product_id) {
        $product_description_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_description_data[$result['language_id']] = array(
                'name'             => $result['name'],
                'description'      => $result['description'],
                'meta_title'       => $result['meta_title'],
                'meta_description' => $result['meta_description'],
                'meta_keyword'     => $result['meta_keyword'],
                'tag'              => $result['tag']
            );
        }

        return $product_description_data;
    }

    /**
     * Writes a sync results to odoo_product_sync table
     *
     * @param $product_id
     * @param $message
     * @param $status
     * @param $direction
     */
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
                    $odoo_connect = $this->model_module_odoo_connector->connection;
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
                    $this->log->write("Error odoo_product_sync logSync: Failed to update Odoo sync status: " . $e->getMessage());
                }
            }
        }
        if ($this->debug) $this->log->write("Info odoo_product_sync logSync: Product ID " . $product_id .
            " status updated to " . $status . " (" . $numericStatus . ")");
    }

    /**
     * Returns debug configuration parameter
     *
     * @return bool
     */
    private function getDebug(){
        $result = $this->db->query("SELECT `value` FROM " . DB_PREFIX . "odoo_config  WHERE `key` = 'debug'");
        if ($result->row) return $result->row['value'];
        return False;
    }

    // Helper method to convert numeric status to text
    private function getTextStatus($numericStatus) {
        $res = self::SYNC_STATUS[$numericStatus];
        return isset($res) ? $res : 'unknown';
    }

    // Helper method to convert text status to numeric
    private function getNumericStatus($textStatus) {
        $flip = array_flip(self::SYNC_STATUS);
        return isset($flip[$textStatus]) ? $flip[$textStatus] : 0;
    }

    /**
     * Gets a product inforamtion as required in admin space
     * This is actually a copy of admin space getProduct function put here
     * for convenience
     *
     * @param $product_id
     * @return mixed
     */
    public function getProduct($product_id) {
        $query = $this->db->query("SELECT DISTINCT *, (SELECT keyword FROM " . DB_PREFIX . "seo_url WHERE query = 'product_id=" . (int)$product_id . "' AND store_id = '0' AND language_id = '" . (int)$this->config->get('config_language_id') . "' LIMIT 1) AS keyword FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') WHERE p.product_id = '" . (int)$product_id . "'");
        return $query->row;
    }

    private function validateCategory($category_id) {
        $query = $this->db->query("SELECT category_id FROM " . DB_PREFIX .
            "category WHERE category_id = '" . (int)$category_id . "'");
        return $query->num_rows > 0;
    }

    /**
     * Normalize both the legacy CSV format and the canonical JSON array.
     *
     * @param mixed $value
     * @return array|null Null means invalid input.
     */
    private function normalizeCategoryIds($value) {
        if ($value === null || $value === '') {
            return array();
        }

        if (is_string($value)) {
            $values = explode(',', $value);
        } elseif (is_array($value)) {
            $values = $value;
        } else {
            return null;
        }

        $category_ids = array();
        foreach ($values as $value) {
            if (is_int($value)) {
                $category_id = $value;
            } elseif (is_string($value) && ctype_digit(trim($value))) {
                $category_id = (int)trim($value);
            } else {
                return null;
            }

            if ($category_id <= 0) {
                return null;
            }
            $category_ids[$category_id] = $category_id;
        }

        return array_values($category_ids);
    }


    public function processPriceEntry($product_id, $price_data) {
        $result = array(
            'success' => false,
            'message' => '',
            'error' => '',
            'customer_group' => array(
                'id' => 0,
                'name' => '',
                'pricelist_name' => isset($price_data['name']) ? $price_data['name'] : '',
                'old_price' => 0,
                'new_price' => isset($price_data['price']) ? $price_data['price'] : 0
            )
        );

        if ($this->debug) $this->log->write("Processing price entry for product ID: " . $product_id);
        if ($this->debug) $this->log->write("Price data: " . print_r($price_data, true));

        try {
            if (!isset($price_data['pricelist_id'])) {
                throw new Exception('Pricelist ID not provided');
            }

            // Get customer group mapping
            $customer_group_id = $this->model_extension_module_odoo_price_sync->getOpenCartGroupId($price_data['pricelist_id']);
            if ($this->debug) $this->log->write("Found customer group ID: " . $customer_group_id);
            if (!$customer_group_id) {
                throw new Exception(sprintf('No mapping found for pricelist ID: %s (%s)',
                    $price_data['pricelist_id'],
                    $price_data['name']
                ));
            }

            // Get customer group name
            $customer_group_query = $this->db->query("SELECT name FROM " . DB_PREFIX . "customer_group_description 
            WHERE customer_group_id = '" . (int)$customer_group_id . "' 
            AND language_id = '" . (int)$this->config->get('config_language_id') . "'");

            $result['customer_group']['id'] = $customer_group_id;
            $result['customer_group']['name'] = $customer_group_query->row['name'];

            // Get current price
            $current_price = $this->model_extension_module_odoo_price_sync->getActualProductPrice($product_id, $customer_group_id);
            $result['customer_group']['old_price'] = $current_price['price'];

            if ($this->debug) $this->log->write("Current price info: " . print_r($current_price, true));

            // Enhanced price comparison logging
            if ($this->debug) $this->log->write(sprintf(
                "Price comparison:\nNew price: %f\nCurrent price: %f\nEqual: %s",
                $price_data['price'],
                $current_price['price'],
                ($current_price['price'] == $price_data['price'] ? 'Yes' : 'No')
            ));


            // Skip update if price hasn't changed
            if ($current_price['price'] == $price_data['price']) {
                $result['success'] = true;
                $result['message'] = sprintf(
                    'Price unchanged for customer group %s (%s) - %s RUB',
                    $result['customer_group']['name'],
                    $price_data['name'],
                    $price_data['price']
                );
                return $result;
            }

            $update_result = $this->model_extension_module_odoo_price_sync->updateProductPrices(
                $product_id,
                $customer_group_id,
                $price_data['price']
            );

            if ($update_result) {
                $result['success'] = true;
                $result['message'] = sprintf(
                    'Updated price for customer group %s (%s) from %s to %s RUB',
                    $result['customer_group']['name'],
                    $price_data['name'],
                    $current_price['price'],
                    $price_data['price']
                );
            } else {
                throw new Exception(sprintf(
                    'Failed to update price for customer group %s (%s)',
                    $result['customer_group']['name'],
                    $price_data['name']
                ));
            }

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();

            // Log individual price sync error
            $this->model_extension_module_odoo_price_sync->logPriceSync([
                'product_id' => $product_id,
                'customer_group_id' => $result['customer_group']['id'],
                'old_price' => $result['customer_group']['old_price'],
                'new_price' => $result['customer_group']['new_price'],
                'sync_direction' => 'from_odoo',
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }

        return $result;
    }

    public function syncPrice() {
        $this->load->language('api/product');
        $json = array();

        try {
            if (!isset($this->session->data['api_id'])) {
                throw new Exception($this->language->get('error_permission'));
            }

            // Get JSON input
            $input = file_get_contents('php://input');
            if (empty($input)) {
                throw new Exception('No input data received');
            }

            $input_json = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON input: ' . json_last_error_msg());
            }

            if ($this->debug) $this->log->write("API Product Price Sync - Received data: " . print_r($input_json, true));

            if (!$this->validatePriceSyncInput($input_json)) {
                throw new Exception(json_encode($this->error));
            }

            $product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
            if (!$product_id) {
                throw new Exception('Product ID not provided');
            }

            $this->load->model('extension/module/odoo_price_sync');
            $this->load->model('catalog/product');

            // Verify product exists
            $product_info = $this->getProduct($product_id);
            if (!$product_info) {
                throw new Exception($this->language->get('error_product_not_found'));
            }

            // Initialize results tracking
            $total_prices = count($input_json['prices']);
            $results = array(
                'updated' => array(),
                'skipped' => array(),
                'failed' => array()
            );

            // Start transaction
            $this->db->query("START TRANSACTION");

            try {
                foreach ($input_json['prices'] as $price_data) {
                    $entry_result = $this->processPriceEntry($product_id, $price_data);
                    if ($this->debug) $this->log->write('Price entry result: ' . print_r($entry_result, true));

                    if ($entry_result['success']) {
                        if (strpos($entry_result['message'], 'unchanged') !== false) {
                            $results['skipped'][] = array(
                                'pricelist_id' => $price_data['pricelist_id'],
                                'name' => $price_data['name'],
                                'price' => $price_data['price'],
                                'reason' => 'Price unchanged'
                            );
                        } else {
                            $results['updated'][] = array(
                                'pricelist_id' => $price_data['pricelist_id'],
                                'name' => $price_data['name'],
                                'old_price' => $entry_result['customer_group']['old_price'],
                                'new_price' => $price_data['price'],
                                'customer_group' => $entry_result['customer_group']['name']
                            );
                        }
                    } else {
                        $results['failed'][] = array(
                            'pricelist_id' => $price_data['pricelist_id'],
                            'name' => $price_data['name'],
                            'error' => $entry_result['error']
                        );
                    }
                }

                // Optional: carry the current Odoo-generated meta title/description
                // along with the price sync, so they stay in lockstep without a
                // separate full product sync. Simple single UPDATE, only when
                // provided - no extra requests, no per-price-entry overhead.
                if (isset($input_json['meta_title']) || isset($input_json['meta_description'])) {
                    $meta_update_fields = array();

                    if (isset($input_json['meta_title'])) {
                        $meta_update_fields[] = "meta_title = '" .
                            $this->db->escape($input_json['meta_title']) . "'";
                    }

                    if (isset($input_json['meta_description'])) {
                        $meta_update_fields[] = "meta_description = '" .
                            $this->db->escape($input_json['meta_description']) . "'";
                    }

                    if (!empty($meta_update_fields)) {
                        $language_id = $this->getConfiguredLanguageId();
                        $this->db->query(
                            "UPDATE " . DB_PREFIX . "product_description SET " .
                            implode(', ', $meta_update_fields) .
                            " WHERE product_id = '" . (int)$product_id .
                            "' AND language_id = '" . (int)$language_id . "'"
                        );
                        if ($this->debug) $this->log->write(
                            "API Product Price Sync - Updated meta fields for language " .
                            $language_id . ": {" . implode('}, ', $meta_update_fields) . "};"
                        );
                    }
                }

                $updates_count = count($results['updated']);
                $skipped_count = count($results['skipped']);
                $failed_count = count($results['failed']);

                $summary = sprintf(
                    "Product ID %d: %d/%d prices processed - %d updated, %d skipped, %d failed",
                    $product_id,
                    ($updates_count + $skipped_count),
                    $total_prices,
                    $updates_count,
                    $skipped_count,
                    $failed_count
                );

                if (empty($results['failed'])) {
                    $this->db->query("COMMIT");
                    $json = array(
                        'success' => true,
                        'message' => $summary,
                        'product_id' => $product_id,
                        'status' => 'completed',
                        'statistics' => array(
                            'total' => $total_prices,
                            'processed' => $updates_count + $skipped_count,
                            'updated' => $updates_count,
                            'skipped' => $skipped_count,
                            'failed' => 0
                        ),
                        'details' => array(
                            'updated' => array_map(function($item) {
                                return sprintf(
                                    "Pricelist '%s': %s RUB → %s RUB (Group: %s)",
                                    $item['name'],
                                    $item['old_price'],
                                    $item['new_price'],
                                    $item['customer_group']
                                );
                            }, $results['updated']),
                            'skipped' => array_map(function($item) {
                                return sprintf(
                                    "Pricelist '%s': %s RUB (%s)",
                                    $item['name'],
                                    $item['price'],
                                    $item['reason']
                                );
                            }, $results['skipped'])
                        )
                    );
                } else {
                    // Build detailed error message
                    $error_details = array_map(function($failure) {
                        return sprintf(
                            "Pricelist '%s' (ID: %s): %s",
                            $failure['name'],
                            $failure['pricelist_id'],
                            $failure['error']
                        );
                    }, $results['failed']);

                    throw new Exception(
                        $summary . "<br/>Errors:<br/>" . implode("<br/>", $error_details)
                    );
                }

            } catch (Exception $e) {
                $this->db->query("ROLLBACK");
                throw $e;
            }

        } catch (Exception $e) {
            $json = array(
                'success' => false,
                'message' => $e->getMessage(),
                'product_id' => isset($product_id) ? $product_id : 0,
                'status' => 'failed',
                'statistics' => isset($results) ? array(
                    'total' => $total_prices,
                    'processed' => count($results['updated']) + count($results['skipped']),
                    'updated' => count($results['updated']),
                    'skipped' => count($results['skipped']),
                    'failed' => count($results['failed'])
                ) : array(),
                'details' => isset($results) ? array(
                    'errors' => array_map(function($failure) {
                        return sprintf(
                            "Pricelist '%s': %s",
                            $failure['name'],
                            $failure['error']
                        );
                    }, $results['failed']),
                    'processed' => array_merge(
                        array_map(function($item) {
                            return sprintf(
                                "Updated: Pricelist '%s': %s RUB → %s RUB",
                                $item['name'],
                                $item['old_price'],
                                $item['new_price']
                            );
                        }, $results['updated']),
                        array_map(function($item) {
                            return sprintf(
                                "Skipped: Pricelist '%s': %s RUB",
                                $item['name'],
                                $item['price']
                            );
                        }, $results['skipped'])
                    )
                ) : array()
            );
            $this->log->write("API Product Price Sync - Error: " . $e->getMessage());
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    protected function validatePriceSyncInput($input_json) {
        if (!isset($input_json['prices']) || !is_array($input_json['prices'])) {
            $this->error['prices'] = $this->language->get('error_prices_required');
            return false;
        }

        foreach ($input_json['prices'] as $idx => $price_data) {
            if (!isset($price_data['price']) || !is_numeric($price_data['price'])) {
                $this->error['price_' . $idx] = $this->language->get('error_price');
            }

            if (!isset($price_data['pricelist_id']) || !is_numeric($price_data['pricelist_id'])) {
                $this->error['pricelist_' . $idx] = $this->language->get('error_pricelist');
            }

            if (!isset($price_data['currency']) || $price_data['currency'] !== 'RUB') {
                $this->error['currency_' . $idx] = $this->language->get('error_currency');
            }
        }

        return empty($this->error);
    }

    /**
     * Create simple product mapping (for products without variants)
     *
     * @param int $odoo_product_id Odoo product ID
     * @param int $opencart_product_id OpenCart product ID
     * @param int $odoo_template_id Odoo product template ID
     * @throws Exception
     */
    protected function createSimpleProductMapping($odoo_product_id, $opencart_product_id, $odoo_template_id) {
        // Get OpenCart product data
        $product_query = $this->db->query("SELECT p.model, pd.name
            FROM " . DB_PREFIX . "product p
            LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
            WHERE p.product_id = '" . (int)$opencart_product_id . "'
            AND pd.language_id = '" . (int)$this->default_language_id . "'
            LIMIT 1");

        if (!$product_query->num_rows) {
            throw new Exception('OpenCart product ' . $opencart_product_id . ' not found');
        }

        $product_data = $product_query->row;

        // Create mapping entry with no option (product_option_value_id = -1 for simple products)
        $this->db->query("INSERT INTO " . DB_PREFIX . "odoo_product_variant_map SET
            odoo_product_id = '" . (int)$odoo_product_id . "',
            opencart_product_id = '" . (int)$opencart_product_id . "',
            opencart_product_option_id = -1,
            opencart_product_name = '" . $this->db->escape($product_data['model']) . "',
            opencart_product_option_description = '" . $this->db->escape($product_data['name']) . "',
            odoo_product_tmpl_id = '" . (int)$odoo_template_id . "',
            created_by = 'api_product_create',
            created_on = NOW(),
            is_synch = 1
            ON DUPLICATE KEY UPDATE
            opencart_product_option_id = -1,
            opencart_product_name = '" . $this->db->escape($product_data['model']) . "',
            opencart_product_option_description = '" . $this->db->escape($product_data['name']) . "',
            odoo_product_tmpl_id = '" . (int)$odoo_template_id . "',
            is_synch = 1");

        $this->log->write(sprintf(
            "API Product Create - Created simple product mapping: Odoo ID %d -> OC product_id %d",
            $odoo_product_id,
            $opencart_product_id
        ));
    }

    /**
     * Create variant mappings for products with options
     *
     * @param int $product_id OpenCart product ID
     * @param array $data Product data from Odoo including options
     * @param int $odoo_template_id Odoo product template ID
     * @throws Exception
     */
    protected function createVariantMappings($product_id, $data, $odoo_template_id) {
        $this->log->write("API Product Create - Creating variant mappings for product ID: " . $product_id);

        if (!isset($data['options']['option_values']) || empty($data['options']['option_values'])) {
            throw new Exception('No option values found for variant mapping');
        }

        // Get the created product options from database
        $option_query = $this->db->query("SELECT po.product_option_id, po.option_id
            FROM " . DB_PREFIX . "product_option po
            WHERE po.product_id = '" . (int)$product_id . "'
            LIMIT 1");

        if (!$option_query->num_rows) {
            throw new Exception('Product options not found after product creation');
        }

        $product_option_id = $option_query->row['product_option_id'];

        // Map each Odoo variant to OpenCart product option value
        foreach ($data['options']['option_values'] as $variant_data) {
            if (!isset($variant_data['odoo_variant_id'])) {
                $this->log->write("API Product Create - Skipping variant without odoo_variant_id: " . print_r($variant_data, true));
                continue;
            }

            // Use option_value_code for LIKE matching (same as prepareProductOptions)
            if (!isset($variant_data['option_value_code'])) {
                $this->log->write("API Product Create - Skipping variant without option_value_code: " . print_r($variant_data, true));
                continue;
            }

            $variant_code = $variant_data['option_value_code'];

            // Resolve option_value_id via explicit mapping table first, then LIKE fallback
            $odoo_attr_id = isset($data['options']['odoo_attribute_id']) ? (int)$data['options']['odoo_attribute_id'] : 0;
            $resolved     = $this->resolveOptionValue($odoo_attr_id, $variant_code, $option_query->row['option_id']);

            if ($resolved !== null) {
                // Exact lookup by resolved option_value_id — no LIKE needed
                $pov_query = $this->db->query("SELECT pov.product_option_value_id, ovd.name
                    FROM " . DB_PREFIX . "product_option_value pov
                    LEFT JOIN " . DB_PREFIX . "option_value_description ovd
                        ON (pov.option_value_id = ovd.option_value_id
                        AND ovd.language_id = '" . (int)$this->default_language_id . "')
                    WHERE pov.product_id = '" . (int)$product_id . "'
                    AND pov.product_option_id = '" . (int)$product_option_id . "'
                    AND pov.option_value_id = '" . (int)$resolved['option_value_id'] . "'
                    LIMIT 1");
            } else {
                // LIKE fallback: parentheses/bracket pattern prevents partial matches (e.g. "9" vs "9,5")
                $pov_query = $this->db->query("SELECT pov.product_option_value_id, ovd.name
                    FROM " . DB_PREFIX . "product_option_value pov
                    LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id)
                    WHERE pov.product_id = '" . (int)$product_id . "'
                    AND pov.product_option_id = '" . (int)$product_option_id . "'
                    AND (ovd.name LIKE '%(" . $this->db->escape($variant_code) . ")%' OR ovd.name LIKE '%[" . $this->db->escape($variant_code) . "]%')
                    AND ovd.language_id = '" . (int)$this->default_language_id . "'
                    LIMIT 1");
            }

            if ($pov_query && $pov_query->num_rows) {
                $product_option_value_id = $pov_query->row['product_option_value_id'];
                $matched_option_name = $pov_query->row['name'];

                // Create mapping entry for this variant
                $this->db->query("INSERT INTO " . DB_PREFIX . "odoo_product_variant_map SET
                    odoo_product_id = '" . (int)$variant_data['odoo_variant_id'] . "',
                    opencart_product_id = '" . (int)$product_id . "',
                    opencart_product_option_id = '" . (int)$product_option_value_id . "',
                    opencart_product_name = '" . $this->db->escape($data['default_code']) . "',
                    opencart_product_option_description = '" . $this->db->escape($matched_option_name) . "',
                    odoo_product_tmpl_id = '" . (int)$odoo_template_id . "',
                    created_by = 'api_product_create',
                    created_on = NOW(),
                    is_synch = 1
                    ON DUPLICATE KEY UPDATE
                    opencart_product_option_id = '" . (int)$product_option_value_id . "',
                    opencart_product_name = '" . $this->db->escape($data['default_code']) . "',
                    opencart_product_option_description = '" . $this->db->escape($matched_option_name) . "',
                    odoo_product_tmpl_id = '" . (int)$odoo_template_id . "',
                    is_synch = 1");

                $this->log->write(sprintf(
                    "API Product Create - Mapped Odoo variant %d (code: '%s') to OC product_option_value_id %d ('%s')",
                    $variant_data['odoo_variant_id'],
                    $variant_code,
                    $product_option_value_id,
                    $matched_option_name
                ));
            } else {
                $this->log->write(sprintf(
                    "API Product Create - Warning: Could not find product_option_value for variant code '%s'",
                    $variant_code
                ));
            }
        }

        $this->log->write("API Product Create - Completed variant mappings for product ID: " . $product_id);
    }

    /**
     * Prepare product options from Odoo data
     *
     * Maps Odoo attributes to OpenCart options using configuration stored in odoo_config table.
     * Config format: key = "odoo_attribute_{odoo_attribute_id}", value = "{opencart_option_id}"
     * Example: odoo_attribute_5 = 13 (maps Odoo attribute_id 5 to OpenCart option_id 13)
     *
     * @param array $options_data Options data from Odoo
     * @return array Formatted product options for OpenCart
     * @throws Exception
     */
    protected function prepareProductOptions($options_data) {
        $product_options = array();

        // Get Odoo attribute ID
        if (!isset($options_data['odoo_attribute_id'])) {
            throw new Exception('odoo_attribute_id is required in options data');
        }

        $odoo_attribute_id = (int)$options_data['odoo_attribute_id'];
        $odoo_attribute_name = isset($options_data['option_name']) ? $options_data['option_name'] : 'Unknown';

        // Look up the mapping in odoo_config table
        $config_key = 'odoo_attribute_' . $odoo_attribute_id;
        $mapping_query = $this->db->query("SELECT value FROM " . DB_PREFIX . "odoo_config
            WHERE `key` = '" . $this->db->escape($config_key) . "'
            LIMIT 1");

        if (!$mapping_query->num_rows) {
            throw new Exception(sprintf(
                'No OpenCart option mapping found for Odoo attribute_id %d ("%s"). Please configure in odoo_config table: key="%s", value="<opencart_option_id>"',
                $odoo_attribute_id,
                $odoo_attribute_name,
                $config_key
            ));
        }

        $option_id = (int)$mapping_query->row['value'];

        // Verify the option exists in OpenCart
        $option_check = $this->db->query("SELECT o.option_id, od.name
            FROM " . DB_PREFIX . "option o
            LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id)
            WHERE o.option_id = '" . (int)$option_id . "'
            AND od.language_id = '" . (int)$this->default_language_id . "'
            LIMIT 1");

        if (!$option_check->num_rows) {
            throw new Exception(sprintf(
                'Mapped OpenCart option_id %d not found (from Odoo attribute_id %d "%s")',
                $option_id,
                $odoo_attribute_id,
                $odoo_attribute_name
            ));
        }

        $this->log->write(sprintf(
            "API Product Options - Mapped Odoo attribute_id %d (\"%s\") -> OpenCart option '%s' (ID: %d)",
            $odoo_attribute_id,
            $odoo_attribute_name,
            $option_check->row['name'],
            $option_id
        ));

        // Prepare option values using same matching approach as update_product_quantity.php
        // Match by searching option value description with LIKE '%{code}%'
        $option_values = array();
        if (isset($options_data['option_values']) && is_array($options_data['option_values'])) {
            foreach ($options_data['option_values'] as $value_data) {
                if (!isset($value_data['option_value_code'])) {
                    $this->log->write(sprintf(
                        "API Product Options - WARNING: option_value_code not provided for variant: %s",
                        isset($value_data['option_value_name']) ? $value_data['option_value_name'] : 'N/A'
                    ));
                    continue;
                }

                $variant_code = $value_data['option_value_code'];

                // Resolve via explicit value mapping table first, then LIKE fallback
                $resolved = $this->resolveOptionValue($odoo_attribute_id, $variant_code, $option_id);

                if ($resolved !== null) {
                    $option_values[] = array(
                        'option_value_id' => $resolved['option_value_id'],
                        'quantity'        => 0,
                        'subtract'        => 1,
                        'price'           => 0.00,
                        'price_prefix'    => '+',
                        'points'          => 0,
                        'points_prefix'   => '+',
                        'weight'          => 0.00000000,
                        'weight_prefix'   => '+'
                    );

                    $this->log->write(sprintf(
                        "API Product Options - Matched option value: Odoo '%s' -> OC '%s' (option_value_id: %d)",
                        $variant_code,
                        $resolved['name'],
                        $resolved['option_value_id']
                    ));
                } else {
                    $this->log->write(sprintf(
                        "API Product Options - ERROR: No matching option value found for code '%s' in option %s (option_id: %d)",
                        $variant_code,
                        $option_check->row['name'],
                        $option_id
                    ));
                }
            }
        }

        // Return the product option structure
        if (!empty($option_values)) {
            $product_options[] = array(
                'option_id' => $option_id,
                'type' => 'select',  // Default to select type
                'required' => 1,
                'product_option_value' => $option_values
            );
        }

        return $product_options;
    }

    /**
     * Resolve an Odoo attribute value code to an OpenCart option_value_id.
     *
     * Checks ocus_odoo_attribute_value_mapping first (explicit vendor overrides), then
     * falls back to LIKE matching against option_value_description.name.
     * Returns ['option_value_id' => int, 'name' => string] or null when not found.
     *
     * Call this instead of a bare LIKE query whenever resolving option values during
     * product creation (prepareProductOptions) or variant mapping (createVariantMappings).
     *
     * @param int    $odoo_attribute_id
     * @param string $variant_code       e.g. "260" or "9,5"
     * @param int    $option_id          OpenCart option_id (used for LIKE fallback scope)
     * @return array|null
     */
    private function resolveOptionValue($odoo_attribute_id, $variant_code, $option_id) {
        // Explicit mapping table takes priority
        $mapping_query = $this->db->query("
            SELECT opencart_option_value_id
            FROM " . DB_PREFIX . "odoo_attribute_value_mapping
            WHERE odoo_attribute_id = '" . (int)$odoo_attribute_id . "'
            AND odoo_value_code     = '" . $this->db->escape($variant_code) . "'
            LIMIT 1
        ");

        if ($mapping_query->num_rows) {
            $option_value_id = (int)$mapping_query->row['opencart_option_value_id'];
            $name_query = $this->db->query("
                SELECT name FROM " . DB_PREFIX . "option_value_description
                WHERE option_value_id = '" . $option_value_id . "'
                AND language_id = '" . (int)$this->default_language_id . "'
                LIMIT 1
            ");
            $name = $name_query->num_rows ? $name_query->row['name'] : 'ID:' . $option_value_id;
            $this->log->write(sprintf(
                "API Product Options - Value mapping hit: '%s' -> option_value_id %d ('%s')",
                $variant_code, $option_value_id, $name
            ));
            return array('option_value_id' => $option_value_id, 'name' => $name);
        }

        // LIKE fallback — matches patterns like "(9,5)" or "[9.5]" in option value names
        $query = $this->db->query("
            SELECT ov.option_value_id, ovd.name
            FROM " . DB_PREFIX . "option_value ov
            LEFT JOIN " . DB_PREFIX . "option_value_description ovd
                ON (ov.option_value_id = ovd.option_value_id)
            WHERE ov.option_id = '" . (int)$option_id . "'
            AND (ovd.name LIKE '%(" . $this->db->escape($variant_code) . ")%'
              OR ovd.name LIKE '%[" . $this->db->escape($variant_code) . "]%')
            AND ovd.language_id = '" . (int)$this->default_language_id . "'
            LIMIT 1
        ");

        if ($query->num_rows) {
            return array(
                'option_value_id' => (int)$query->row['option_value_id'],
                'name'            => $query->row['name'],
            );
        }

        return null;
    }

    private function getProductMapping($odoo_product_id = null, $opencart_product_id = null) {
        $sql = "SELECT opvm.*, p.model as opencart_model, pd.name as opencart_name, p.weight as weight, p.length as length, p.height as height, p.width as width
            FROM " . DB_PREFIX . "odoo_product_variant_map opvm
            LEFT JOIN " . DB_PREFIX . "product p ON p.product_id = opvm.opencart_product_id
            LEFT JOIN " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id
            WHERE 1=1";

        if ($odoo_product_id) {
            $sql .= " AND opvm.odoo_product_id = '" . (int)$odoo_product_id . "'";
        }

        if ($opencart_product_id) {
            $sql .= " AND opvm.opencart_product_id = '" . (int)$opencart_product_id . "'";
        }

        $query = $this->db->query($sql);
        return $query->num_rows ? $query->row : false;
    }

}
