<?php
/**
 * Adaptive Product Filtering & Sorting
 *
 * @package    OpenCart 3.x
 * @author     Adaptive Filter Module
 * @version    1.0.0
 * @license    Proprietary
 */

class ControllerExtensionModuleAdaptiveFilter extends Controller {

    private $error = array();

    /**
     * Main settings page
     */
    public function index() {
        $this->load->language('extension/module/adaptive_filter');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('extension/module/adaptive_filter');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            // Convert multi-select arrays to comma-separated strings
            $post_data = $this->request->post;

            if (isset($post_data['module_adaptive_filter_size_option_ids']) && is_array($post_data['module_adaptive_filter_size_option_ids'])) {
                $post_data['module_adaptive_filter_size_option_ids'] = implode(',', $post_data['module_adaptive_filter_size_option_ids']);
            }

            if (isset($post_data['module_adaptive_filter_color_attribute_ids']) && is_array($post_data['module_adaptive_filter_color_attribute_ids'])) {
                $post_data['module_adaptive_filter_color_attribute_ids'] = implode(',', $post_data['module_adaptive_filter_color_attribute_ids']);
            }

            $this->model_setting_setting->editSetting('module_adaptive_filter', $post_data);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');

        $data['entry_status'] = $this->language->get('entry_status');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/adaptive_filter', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/adaptive_filter', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // Settings
        if (isset($this->request->post['module_adaptive_filter_status'])) {
            $data['module_adaptive_filter_status'] = $this->request->post['module_adaptive_filter_status'];
        } else {
            $data['module_adaptive_filter_status'] = $this->config->get('module_adaptive_filter_status');
        }

        // Debug mode setting
        if (isset($this->request->post['module_adaptive_filter_debug_mode'])) {
            $data['module_adaptive_filter_debug_mode'] = $this->request->post['module_adaptive_filter_debug_mode'];
        } else {
            $data['module_adaptive_filter_debug_mode'] = $this->config->get('module_adaptive_filter_debug_mode');
        }

        // Journal3 Filter Mapping - Size option IDs configuration
        if (isset($this->request->post['module_adaptive_filter_size_option_ids'])) {
            $data['module_adaptive_filter_size_option_ids'] = $this->request->post['module_adaptive_filter_size_option_ids'];
        } else {
            $data['module_adaptive_filter_size_option_ids'] = $this->config->get('module_adaptive_filter_size_option_ids') ?? '';
        }

        // Color attribute IDs configuration
        if (isset($this->request->post['module_adaptive_filter_color_attribute_ids'])) {
            $data['module_adaptive_filter_color_attribute_ids'] = $this->request->post['module_adaptive_filter_color_attribute_ids'];
        } else {
            $data['module_adaptive_filter_color_attribute_ids'] = $this->config->get('module_adaptive_filter_color_attribute_ids') ?? '';
        }

        // Icon configuration
        if (isset($this->request->post['module_adaptive_filter_shoe_size_icon'])) {
            $data['module_adaptive_filter_shoe_size_icon'] = $this->request->post['module_adaptive_filter_shoe_size_icon'];
        } else {
            $data['module_adaptive_filter_shoe_size_icon'] = $this->config->get('module_adaptive_filter_shoe_size_icon') ?? '👟';
        }

        if (isset($this->request->post['module_adaptive_filter_apparel_size_icon'])) {
            $data['module_adaptive_filter_apparel_size_icon'] = $this->request->post['module_adaptive_filter_apparel_size_icon'];
        } else {
            $data['module_adaptive_filter_apparel_size_icon'] = $this->config->get('module_adaptive_filter_apparel_size_icon') ?? '👕';
        }

        if (isset($this->request->post['module_adaptive_filter_color_icon'])) {
            $data['module_adaptive_filter_color_icon'] = $this->request->post['module_adaptive_filter_color_icon'];
        } else {
            $data['module_adaptive_filter_color_icon'] = $this->config->get('module_adaptive_filter_color_icon') ?? '🎨';
        }

        if (isset($this->request->post['module_adaptive_filter_sport_icon'])) {
            $data['module_adaptive_filter_sport_icon'] = $this->request->post['module_adaptive_filter_sport_icon'];
        } else {
            $data['module_adaptive_filter_sport_icon'] = $this->config->get('module_adaptive_filter_sport_icon') ?? '🎾';
        }

        // Weight configuration (for signal recording)
        if (isset($this->request->post['module_adaptive_filter_weight_view'])) {
            $data['module_adaptive_filter_weight_view'] = $this->request->post['module_adaptive_filter_weight_view'];
        } else {
            $data['module_adaptive_filter_weight_view'] = $this->config->get('module_adaptive_filter_weight_view') ?? '1';
        }

        if (isset($this->request->post['module_adaptive_filter_weight_cart'])) {
            $data['module_adaptive_filter_weight_cart'] = $this->request->post['module_adaptive_filter_weight_cart'];
        } else {
            $data['module_adaptive_filter_weight_cart'] = $this->config->get('module_adaptive_filter_weight_cart') ?? '5';
        }

        if (isset($this->request->post['module_adaptive_filter_weight_manual'])) {
            $data['module_adaptive_filter_weight_manual'] = $this->request->post['module_adaptive_filter_weight_manual'];
        } else {
            $data['module_adaptive_filter_weight_manual'] = $this->config->get('module_adaptive_filter_weight_manual') ?? '10';
        }

        // Score configuration (for product scoring)
        if (isset($this->request->post['module_adaptive_filter_score_size'])) {
            $data['module_adaptive_filter_score_size'] = $this->request->post['module_adaptive_filter_score_size'];
        } else {
            $data['module_adaptive_filter_score_size'] = $this->config->get('module_adaptive_filter_score_size') ?? '10';
        }

        if (isset($this->request->post['module_adaptive_filter_score_color'])) {
            $data['module_adaptive_filter_score_color'] = $this->request->post['module_adaptive_filter_score_color'];
        } else {
            $data['module_adaptive_filter_score_color'] = $this->config->get('module_adaptive_filter_score_color') ?? '5';
        }

        if (isset($this->request->post['module_adaptive_filter_score_gender'])) {
            $data['module_adaptive_filter_score_gender'] = $this->request->post['module_adaptive_filter_score_gender'];
        } else {
            $data['module_adaptive_filter_score_gender'] = $this->config->get('module_adaptive_filter_score_gender') ?? '2';
        }

        if (isset($this->request->post['module_adaptive_filter_score_sport'])) {
            $data['module_adaptive_filter_score_sport'] = $this->request->post['module_adaptive_filter_score_sport'];
        } else {
            $data['module_adaptive_filter_score_sport'] = $this->config->get('module_adaptive_filter_score_sport') ?? '1';
        }

        // Gender category mapping configuration
        if (isset($this->request->post['module_adaptive_filter_gender_men_categories'])) {
            $data['module_adaptive_filter_gender_men_categories'] = $this->request->post['module_adaptive_filter_gender_men_categories'];
        } else {
            $data['module_adaptive_filter_gender_men_categories'] = $this->config->get('module_adaptive_filter_gender_men_categories') ?? '';
        }

        if (isset($this->request->post['module_adaptive_filter_gender_women_categories'])) {
            $data['module_adaptive_filter_gender_women_categories'] = $this->request->post['module_adaptive_filter_gender_women_categories'];
        } else {
            $data['module_adaptive_filter_gender_women_categories'] = $this->config->get('module_adaptive_filter_gender_women_categories') ?? '';
        }

        if (isset($this->request->post['module_adaptive_filter_gender_children_categories'])) {
            $data['module_adaptive_filter_gender_children_categories'] = $this->request->post['module_adaptive_filter_gender_children_categories'];
        } else {
            $data['module_adaptive_filter_gender_children_categories'] = $this->config->get('module_adaptive_filter_gender_children_categories') ?? '';
        }

        // Load all available options for selection
        $data['available_options'] = $this->getAvailableOptions();

        // Load all available attributes for selection
        $data['available_attributes'] = $this->getAvailableAttributes();

        // Load all available categories for sport mapping
        $data['available_categories'] = $this->getAvailableCategories();

        // Load sport mappings
        $data['sport_mappings'] = $this->model_extension_module_adaptive_filter->getSportMappings();

        // Load sales mix data
        $this->load->model('extension/module/adaptive_filter');
        $data['sales_mix'] = $this->model_extension_module_adaptive_filter->getAllSalesMix();

        // Pass user token for AJAX requests
        $data['user_token'] = $this->session->data['user_token'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/adaptive_filter', $data));
    }

    /**
     * Get all available product options
     */
    private function getAvailableOptions() {
        $query = $this->db->query("
            SELECT DISTINCT od.option_id, od.name
            FROM " . DB_PREFIX . "option_description od
            WHERE od.language_id = '" . (int)$this->config->get('config_language_id') . "'
            ORDER BY od.name
        ");

        return $query->rows;
    }

    /**
     * Get all available product attributes
     */
    private function getAvailableAttributes() {
        $query = $this->db->query("
            SELECT DISTINCT ad.attribute_id, ad.name, agd.name as group_name
            FROM " . DB_PREFIX . "attribute_description ad
            LEFT JOIN " . DB_PREFIX . "attribute a ON ad.attribute_id = a.attribute_id
            LEFT JOIN " . DB_PREFIX . "attribute_group_description agd
                ON a.attribute_group_id = agd.attribute_group_id
                AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "'
            WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "'
            ORDER BY agd.name, ad.name
        ");

        return $query->rows;
    }

    /**
     * Get all available categories
     */
    private function getAvailableCategories() {
        $query = $this->db->query("
            SELECT c.category_id, cd.name, c.parent_id
            FROM " . DB_PREFIX . "category c
            LEFT JOIN " . DB_PREFIX . "category_description cd
                ON c.category_id = cd.category_id
            WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
            ORDER BY cd.name
        ");

        return $query->rows;
    }


    /**
     * Install - Create database tables
     */
    public function install() {
        $this->load->model('extension/module/adaptive_filter');
        $this->model_extension_module_adaptive_filter->install();
    }

    /**
     * Uninstall - Remove database tables
     */
    public function uninstall() {
        $this->load->model('extension/module/adaptive_filter');
        $this->model_extension_module_adaptive_filter->uninstall();
    }

    /**
     * AJAX: Add sport mapping
     */
    public function addSportMapping() {
        $this->load->language('extension/module/adaptive_filter');
        $this->load->model('extension/module/adaptive_filter');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/adaptive_filter')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $category_id = isset($this->request->post['category_id']) ? (int)$this->request->post['category_id'] : 0;
            $sport = isset($this->request->post['sport']) ? trim($this->request->post['sport']) : '';
            $weight = isset($this->request->post['weight']) ? (float)$this->request->post['weight'] : 1.0;

            if (!$category_id || !$sport) {
                $json['error'] = 'Category and sport are required';
            } else {
                // Check if mapping already exists
                $check = $this->db->query("
                    SELECT * FROM " . DB_PREFIX . "sport_mapping
                    WHERE category_id = '" . (int)$category_id . "'
                ");

                if ($check->num_rows) {
                    // Update existing
                    $this->db->query("
                        UPDATE " . DB_PREFIX . "sport_mapping
                        SET sport = '" . $this->db->escape($sport) . "',
                            weight = '" . (float)$weight . "'
                        WHERE category_id = '" . (int)$category_id . "'
                    ");
                } else {
                    // Insert new
                    $this->db->query("
                        INSERT INTO " . DB_PREFIX . "sport_mapping
                        SET category_id = '" . (int)$category_id . "',
                            sport = '" . $this->db->escape($sport) . "',
                            weight = '" . (float)$weight . "'
                    ");
                }

                $json['success'] = 'Sport mapping saved successfully';
                $json['mappings'] = $this->model_extension_module_adaptive_filter->getSportMappings();
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Delete sport mapping
     */
    public function deleteSportMapping() {
        $this->load->language('extension/module/adaptive_filter');
        $this->load->model('extension/module/adaptive_filter');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/adaptive_filter')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $category_id = isset($this->request->post['category_id']) ? (int)$this->request->post['category_id'] : 0;

            if (!$category_id) {
                $json['error'] = 'Category ID is required';
            } else {
                $this->db->query("
                    DELETE FROM " . DB_PREFIX . "sport_mapping
                    WHERE category_id = '" . (int)$category_id . "'
                ");

                $json['success'] = 'Sport mapping deleted successfully';
                $json['mappings'] = $this->model_extension_module_adaptive_filter->getSportMappings();
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Calculate sales mix percentages
     */
    public function calculateSalesMix() {
        $this->load->language('extension/module/adaptive_filter');
        $this->load->model('extension/module/adaptive_filter');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/adaptive_filter')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            $days = isset($this->request->post['days']) ? (int)$this->request->post['days'] : 90;

            $results = $this->model_extension_module_adaptive_filter->calculateSalesMix($days);

            $json['success'] = 'Sales mix calculated successfully';
            $json['results'] = $results;
            $json['sales_mix'] = $this->model_extension_module_adaptive_filter->getAllSalesMix();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Get current sales mix data
     */
    public function getSalesMixData() {
        $this->load->model('extension/module/adaptive_filter');

        $json = array();

        $json['sales_mix'] = $this->model_extension_module_adaptive_filter->getAllSalesMix();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Update manual percentage
     */
    public function updateManualPercentage() {
        $this->load->language('extension/module/adaptive_filter');
        $this->load->model('extension/module/adaptive_filter');

        $json = array();

        try {
            if (!$this->user->hasPermission('modify', 'extension/module/adaptive_filter')) {
                $json['error'] = $this->language->get('error_permission');
            } else {
                $parent_id = isset($this->request->post['parent_category_id']) ? (int)$this->request->post['parent_category_id'] : 0;
                $subcategory_id = isset($this->request->post['subcategory_id']) ? (int)$this->request->post['subcategory_id'] : 0;
                $percentage = isset($this->request->post['percentage']) ? (float)$this->request->post['percentage'] : 0;

                // Log the input
                $this->log->write('updateManualPercentage called with: parent_id=' . $parent_id . ', subcategory_id=' . $subcategory_id . ', percentage=' . $percentage);

                if (!$parent_id || !$subcategory_id) {
                    $json['error'] = 'Missing category IDs (parent: ' . $parent_id . ', subcat: ' . $subcategory_id . ')';
                } elseif ($percentage < 0 || $percentage > 100) {
                    $json['error'] = 'Percentage must be between 0 and 100 (got: ' . $percentage . ')';
                } else {
                    $result = $this->model_extension_module_adaptive_filter->updateManualPercentage($parent_id, $subcategory_id, $percentage);

                    if ($result) {
                        $json['success'] = 'Percentage updated successfully';
                        $json['sales_mix'] = $this->model_extension_module_adaptive_filter->getAllSalesMix();
                    } else {
                        $json['error'] = 'Failed to update percentage in database';
                    }
                }
            }
        } catch (Exception $e) {
            $this->log->write('updateManualPercentage error: ' . $e->getMessage());
            $json['error'] = 'Exception: ' . $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Reset to calculated percentage
     */
    public function resetToCalculated() {
        $this->load->language('extension/module/adaptive_filter');
        $this->load->model('extension/module/adaptive_filter');

        $json = array();

        try {
            if (!$this->user->hasPermission('modify', 'extension/module/adaptive_filter')) {
                $json['error'] = $this->language->get('error_permission');
            } else {
                $parent_id = isset($this->request->post['parent_category_id']) ? (int)$this->request->post['parent_category_id'] : 0;
                $subcategory_id = isset($this->request->post['subcategory_id']) ? (int)$this->request->post['subcategory_id'] : 0;

                // Log the input
                $this->log->write('resetToCalculated called with: parent_id=' . $parent_id . ', subcategory_id=' . $subcategory_id);

                if (!$parent_id || !$subcategory_id) {
                    $json['error'] = 'Missing category IDs (parent: ' . $parent_id . ', subcat: ' . $subcategory_id . ')';
                } else {
                    $result = $this->model_extension_module_adaptive_filter->resetToCalculated($parent_id, $subcategory_id);

                    if ($result) {
                        $json['success'] = 'Reset to calculated percentage';
                        $json['sales_mix'] = $this->model_extension_module_adaptive_filter->getAllSalesMix();
                    } else {
                        $json['error'] = 'Failed to reset percentage in database';
                    }
                }
            }
        } catch (Exception $e) {
            $this->log->write('resetToCalculated error: ' . $e->getMessage());
            $json['error'] = 'Exception: ' . $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Validate form input
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/adaptive_filter')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
