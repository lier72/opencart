<?php
/**
 * Smart Sorting
 *
 * @author  Cuispi
 * @version 2.3.6
 * @license Commercial License
 * @package admin
 * @subpackage  admin.controller.extension.module
 */
class ControllerExtensionModuleSmartSorting extends Controller {

  /**
   * Development mode
   *
   * @var boolean True or false
   */
  private $dev = false;

  /**
   * List of validation errors.
   *
   * @var array
   */
	private $error = array();

  /**
   * The code of this extension.
   *
   * @var string
   */  
  protected $code;

  /**
   * The short code of this extension.
   *
   * @var string
   */  
  protected $_code;

  /**
   * The partial path to the file of this extension.
   *
   * @var string
   */  
  protected $extension_path;  

  /**
   * The instantiated model class name of this extension.
   *
   * @var string
   */
  protected $model_name;
  
  /**
   * The key of the user token 
   *
   * @var string
   */  
  protected $user_token_key;
  
  /**
   * The value of the user token 
   *
   * @var string
   */  
  protected $user_token_value;

  /**
   * Hold error messages regarding the extension core library initialization.
   *
   * @var mixed Array or false  Defaults to false.
   */  
  protected $initialization_errors = false;
  
  /**
   * Variable for the Logger class instance.
   *
   * @var object
   */
  protected $logger;

  /**
   * Constructor.
   *
   * @param object $registry
   * @return void
   */
	public function __construct($registry) {
    parent::__construct($registry);

    $class_name = strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', __CLASS__));
	
    if (strpos($class_name, '_module_') !== false) {
      list(, $code) = explode('_module_', $class_name);
    } else {
      $code = null;
    }
    
    if (version_compare(VERSION, '3.0.0.0', '<')) { // OpenCart 2.3.0.2 or earlier.
      $this->code = $code;
      $this->_code = $code;
    } else { // OpenCart 3.0.0.0 or later.
      $this->code = 'module_' . $code;
      $this->_code = $code;
    }  
	
    if (version_compare(VERSION, '2.3.0.0', '<')) { // for OpenCart 2.2.0.0 or earlier.
      $this->extension_path = 'module/' . $this->_code;
      $this->model_name = 'model_module_' . $this->_code;
    } else {
      $this->extension_path = 'extension/module/' . $this->_code;
      $this->model_name = 'model_extension_module_' . $this->_code;
    }

    if (version_compare(VERSION, '3.0.0.0', '<')) { // OpenCart 2.3.0.2 or earlier.
      $this->user_token_key = 'token';
      $this->user_token_value = $this->session->data['token'];
    } else { // OpenCart 3.0.0.0 or later.
      $this->user_token_key = 'user_token';
      $this->user_token_value = $this->session->data['user_token'];
    }
    
    $this->logger = new Log($this->code . '.log');
	}

  /**
   * index method
   *
   * @param void
   * @return response
   */
  public function index() {

    $this->language->load($this->extension_path);
    $this->load->model($this->extension_path);

    try {
      if (!$this->{$this->model_name}->hasModification()) {
        throw new Exception($this->language->get('error_modification_not_loaded'));
      }
    }
    catch (Exception $e) {
      $this->logger->write('The modification of this extension does not exist. ' . __FILE__ . ' Line ' . __LINE__ . ': ' . $e->getMessage());
      $this->initialization_errors['error_modification_not_loaded'] = $e->getMessage();
    }

    $this->document->setTitle($this->language->get('heading_title'));


		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

      // Assign the submission type to a variable before destroying it.
      $submission_type = $this->request->post['submission_type'];
      unset($this->request->post['submission_type']);

      // Set module version
      $this->request->post['config'][$this->code . '_version'] = SmartSorting::version();

      // Whitelist the entries.
      $whitelist = array($this->code);
      $post_data = array_intersect_key($this->request->post, array_flip($whitelist));

      $_post_data = array_merge($post_data, $this->request->post['config']);
      $this->load->model($this->extension_path);
      $this->{$this->model_name}->editSetting($this->code, $_post_data);

      $this->session->data['success'] = $this->language->get('text_success');

      // Determine if it is the "Save" action or "Save and close" action.
      $url = '';

      if ($submission_type == 'save') {
        // Submit form and stay on same page
        $url = $this->url->link($this->extension_path, $this->user_token_key . '=' . $this->user_token_value, true);

      } elseif ($submission_type == 'save-and-close') {
        if (version_compare(VERSION, '2.3.0.0', '<')) { // OpenCart 2.2.0.0 or earlier.
          $url = $this->url->link('extension/module', $this->user_token_key . '=' . $this->user_token_value, true);
        } elseif (version_compare(VERSION, '3.0.0.0', '<')) { // OpenCart 2.3.0.2 or earlier.
          $url = $this->url->link('extension/extension', $this->user_token_key . '=' . $this->user_token_value . '&type=module', true);
        } else { // OpenCart 3.0.0.0 or later.
          $url = $this->url->link('marketplace/extension', $this->user_token_key . '=' . $this->user_token_value . '&type=module', true);
        }
      } else {
        $url = $this->url->link($this->extension_path, $this->user_token_key . '=' . $this->user_token_value, true);
      }

      $this->response->redirect($url);
		}

    $this->getForm();
  }

  /**
   * getForm method
   *
   * @param void
   * @return response
   */
	protected function getForm() {

    $this->load->model($this->extension_path);

    $this->document->addScript('view/javascript/' . $this->_code . '/es6-promise/4.1.1/es6-promise.min.js');
    $this->document->addScript('view/javascript/' . $this->_code . '/es6-promise/4.1.1/es6-promise.auto.min.js'); 
    
    $this->document->addScript('view/javascript/' . $this->_code . '/jquery.kh-cookie.min.js');

    $this->document->addScript('view/javascript/' . $this->_code . '/bootstrap-switch/3.3.2/js/bootstrap-switch.min.js');
    $this->document->addStyle('view/javascript/' . $this->_code . '/bootstrap-switch/3.3.2/css/bootstrap-switch.min.css');

    $this->document->addStyle('view/stylesheet/' . $this->_code . '/' . $this->_code . '.css');
    
    if (is_file(DIR_APPLICATION . 'view/javascript/' . $this->_code . '/' . $this->_code . (!$this->dev ? '.min' : '') . '.js')) {
      $this->document->addScript('view/javascript/' . $this->_code . '/' . $this->_code . (!$this->dev ? '.min' : '') . '.js' . ($this->dev ? '?'.  time() : ''));
    } else {
      $this->initialization_errors['error_admin_js_not_loaded'] = $this->language->get('error_admin_js_not_loaded');
    }
    

    $data = array();

    // Version
    if (is_callable(array('SmartSorting', 'version'), false)) {
      $data['version'] = SmartSorting::version();
    } else {
      $data['version'] = false;
    }

    if ($this->initialization_errors) {
      $data['initialization_errors'] = $this->initialization_errors;
    } else {
      $data['initialization_errors'] = false;
    }

    // Heading title
    $data['heading_title'] = $this->language->get('heading_title');

    // Text
    $data['text_edit'] = $this->language->get('text_edit');
    $data['text_on'] = $this->language->get('text_on');
    $data['text_off'] = $this->language->get('text_off');
    $data['text_enabled'] = $this->language->get('text_enabled');
    $data['text_disabled'] = $this->language->get('text_disabled');
    $data['text_status'] = $this->language->get('text_status');
		$data['text_check_all'] = $this->language->get('text_check_all');
		$data['text_uncheck_all'] = $this->language->get('text_uncheck_all');
		$data['text_general'] = $this->language->get('text_general');
    $data['text_heading_category'] = $this->language->get('text_heading_category');
    $data['text_heading_manufacturer'] = $this->language->get('text_heading_manufacturer');
    $data['text_heading_search'] = $this->language->get('text_heading_search');
    $data['text_heading_special'] = $this->language->get('text_heading_special');

    // Entry
    $data['entry_category_status'] = $this->language->get('entry_category_status');
    $data['entry_category_disable_sorting_methods'] = $this->language->get('entry_category_disable_sorting_methods');
    $data['entry_category_default_sort_order'] = $this->language->get('entry_category_default_sort_order');
    $data['entry_category_product_limit_status'] = $this->language->get('entry_category_product_limit_status');
    $data['entry_category_product_limits'] = $this->language->get('entry_category_product_limits');
    $data['entry_category_default_product_limit'] = $this->language->get('entry_category_default_product_limit');
    $data['entry_manufacturer_status'] = $this->language->get('entry_manufacturer_status');
    $data['entry_manufacturer_disable_sorting_methods'] = $this->language->get('entry_manufacturer_disable_sorting_methods');
    $data['entry_manufacturer_default_sort_order'] = $this->language->get('entry_manufacturer_default_sort_order');
    $data['entry_manufacturer_product_limit_status'] = $this->language->get('entry_manufacturer_product_limit_status');
    $data['entry_manufacturer_product_limits'] = $this->language->get('entry_manufacturer_product_limits');
    $data['entry_manufacturer_default_product_limit'] = $this->language->get('entry_manufacturer_default_product_limit');
    $data['entry_search_status'] = $this->language->get('entry_search_status');
    $data['entry_search_disable_sorting_methods'] = $this->language->get('entry_search_disable_sorting_methods');
    $data['entry_search_default_sort_order'] = $this->language->get('entry_search_default_sort_order');
    $data['entry_search_product_limit_status'] = $this->language->get('entry_search_product_limit_status');
    $data['entry_search_product_limits'] = $this->language->get('entry_search_product_limits');
    $data['entry_search_default_product_limit'] = $this->language->get('entry_search_default_product_limit');
    $data['entry_special_status'] = $this->language->get('entry_special_status');
    $data['entry_special_disable_sorting_methods'] = $this->language->get('entry_special_disable_sorting_methods');
    $data['entry_special_default_sort_order'] = $this->language->get('entry_special_default_sort_order');
    $data['entry_special_product_limit_status'] = $this->language->get('entry_special_product_limit_status');
    $data['entry_special_product_limits'] = $this->language->get('entry_special_product_limits');
    $data['entry_special_default_product_limit'] = $this->language->get('entry_special_default_product_limit');
    $data['entry_config_status'] = $this->language->get('entry_config_status');
    $data['entry_config_js_debug'] = $this->language->get('entry_config_js_debug');
    $data['entry_config_sold_time_range'] = $this->language->get('entry_config_sold_time_range');
    $data['entry_config_reviewed_time_range'] = $this->language->get('entry_config_reviewed_time_range');

    // Help
    $data['help_category_status'] = $this->language->get('help_category_status');
    $data['help_category_product_limits'] = $this->language->get('help_category_product_limits');
    $data['help_category_default_product_limit'] = $this->language->get('help_category_default_product_limit');
    $data['help_manufacturer_status'] = $this->language->get('help_manufacturer_status');
    $data['help_manufacturer_product_limits'] = $this->language->get('help_manufacturer_product_limits');
    $data['help_manufacturer_default_product_limit'] = $this->language->get('help_manufacturer_default_product_limit');
    $data['help_search_status'] = $this->language->get('help_search_status');
    $data['help_search_product_limits'] = $this->language->get('help_search_product_limits');
    $data['help_search_default_product_limit'] = $this->language->get('help_search_default_product_limit');
    $data['help_special_status'] = $this->language->get('help_special_status');
    $data['help_special_product_limits'] = $this->language->get('help_special_product_limits');
    $data['help_special_default_product_limit'] = $this->language->get('help_special_default_product_limit');
    $data['help_config_status'] = $this->language->get('help_config_status');
    $data['help_config_js_debug'] = $this->language->get('help_config_js_debug');
    $data['help_config_sold_time_range'] = $this->language->get('help_config_sold_time_range');
    $data['help_config_reviewed_time_range'] = $this->language->get('help_config_reviewed_time_range');
    
    // Button
    $data['button_save'] = $this->language->get('button_save');
    $data['button_save_and_close'] = $this->language->get('button_save_and_close');
    $data['button_cancel'] = $this->language->get('button_cancel');

    // Tab
    $data['tab_smart_sorting'] = $this->language->get('tab_smart_sorting');
    $data['tab_smart_sorting_category'] = $this->language->get('tab_smart_sorting_category');
    $data['tab_smart_sorting_manufacturer'] = $this->language->get('tab_smart_sorting_manufacturer');
    $data['tab_smart_sorting_search'] = $this->language->get('tab_smart_sorting_search');
    $data['tab_smart_sorting_special'] = $this->language->get('tab_smart_sorting_special');
    $data['tab_config'] = $this->language->get('tab_config');
    $data['tab_help'] = $this->language->get('tab_help');

		// Success
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

    // Warning
    if (isset($this->error['warning'])) {
      $data['error_warning'] = $this->error['warning'];
    } else {
      $data['error_warning'] = '';
    }

    // Breadcrumbs
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
        'text' => $this->language->get('text_home'),
        'href' => $this->url->link('common/dashboard', $this->user_token_key . '=' . $this->user_token_value, true)
		);

    if (version_compare(VERSION, '2.3.0.0', '<')) { // OpenCart 2.2.0.0 or earlier.
      $data['breadcrumbs'][] = array(
          'text' => $this->language->get('text_module'),
          'href' => $this->url->link('extension/module', $this->user_token_key . '=' . $this->user_token_value, true)
      );
    } elseif (version_compare(VERSION, '3.0.0.0', '<')) { // OpenCart 2.3.0.2 or earlier.
      $data['breadcrumbs'][] = array(
          'text' => $this->language->get('text_extension'),
          'href' => $this->url->link('extension/extension', $this->user_token_key . '=' . $this->user_token_value . '&type=module', true)
      );    
    } else { // OpenCart 3.0.0.0 or later.
      $data['breadcrumbs'][] = array(
          'text' => $this->language->get('text_extension'),
          'href' => $this->url->link('marketplace/extension', $this->user_token_key . '=' . $this->user_token_value . '&type=module', true)
      );    
    } 

    $data['breadcrumbs'][] = array(
        'text' => $this->language->get('heading_title'),
        'href' => $this->url->link($this->extension_path, $this->user_token_key . '=' . $this->user_token_value, true)
    );

    // Action
    $data['action'] = $this->url->link($this->extension_path, $this->user_token_key . '=' . $this->user_token_value, true);

    // Cancel
    if (version_compare(VERSION, '2.3.0.0', '<')) { // OpenCart 2.2.0.0 or earlier.
      $data['cancel'] = $this->url->link('extension/module', $this->user_token_key . '=' . $this->user_token_value, true);
    } elseif (version_compare(VERSION, '3.0.0.0', '<')) { // OpenCart 2.3.0.2 or earlier.
      $data['cancel'] = $this->url->link('extension/extension', $this->user_token_key . '=' . $this->user_token_value . '&type=module', true);
    } else { // OpenCart 3.0.0.0 or later.
      $data['cancel'] = $this->url->link('marketplace/extension', $this->user_token_key . '=' . $this->user_token_value . '&type=module', true);
    }

    //
    // Stores
    // --------------------------------------------------

		$this->load->model('setting/store');

		$data['stores'] = array();

    $data['stores'][] = array(
			'store_id' => 0,
			'name' => $this->config->get('config_name'),
		);

    $stores = array_merge($data['stores'], $this->model_setting_store->getStores());
    
		$data['stores'] = $stores;

    //
    // Sorting methods
    // --------------------------------------------------

		$sorting_methods = array(
        'default' => array(
            'text'  => $this->language->get('text_default_sort_order'),
            'value' => 'p.sort_order-ASC',
        ),
        'name_asc' => array(
            'text'  => $this->language->get('text_name_asc'),
            'value' => 'pd.name-ASC',
        ),
        'name_desc' => array(
            'text'  => $this->language->get('text_name_desc'),
            'value' => 'pd.name-DESC',
        ),
        'price_asc' => array(
            'text'  => $this->language->get('text_price_asc'),
            'value' => 'p.price-ASC',
        ),
        'price_desc' => array(
            'text'  => $this->language->get('text_price_desc'),
            'value' => 'p.price-DESC',
        ),
        'special_price_asc' => array(
            'text'  => $this->language->get('text_price_asc'),
            'value' => 'ps.price-ASC',
        ),
        'special_price_desc' => array(
            'text'  => $this->language->get('text_price_desc'),
            'value' => 'ps.price-DESC',
        ),
        'rating_desc' => array(
            'text'  => $this->language->get('text_rating_desc'),
            'value' => 'rating-DESC',
        ),
        'rating_asc' => array(
            'text'  => $this->language->get('text_rating_asc'),
            'value' => 'rating-ASC',
        ),
        'model_asc' => array(
            'text'  => $this->language->get('text_model_asc'),
            'value' => 'p.model-ASC',
        ),
        'model_desc' => array(
            'text'  => $this->language->get('text_model_desc'),
            'value' => 'p.model-DESC',
        ),
        'date_added_desc' => array(
            'text'  => $this->language->get('text_date_added_desc'),
            'value' => 'p.date_added-DESC',
        ),
        'date_added_asc' => array(
				  'text'  => $this->language->get('text_date_added_asc'),
				  'value' => 'p.date_added-ASC',
        ),
        'date_modified_desc' => array(
            'text'  => $this->language->get('text_date_modified_desc'),
            'value' => 'p.date_modified-DESC',
        ),
        'date_modified_asc' => array(
            'text'  => $this->language->get('text_date_modified_asc'),
            'value' => 'p.date_modified-ASC',
        ),
        'quantity_desc' => array(
            'text'  => $this->language->get('text_quantity_desc'),
            'value' => 'p.quantity-DESC',
        ),
        'quantity_asc' => array(
            'text'  => $this->language->get('text_quantity_asc'),
            'value' => 'p.quantity-ASC',
        ),
        'viewed_desc' => array(
            'text'  => $this->language->get('text_viewed_desc'),
  				  'value' => 'p.viewed-DESC',
        ),
        'viewed_asc' => array(
    			  'text'  => $this->language->get('text_viewed_asc'),
      		  'value' => 'p.viewed-ASC',
        ),
        'sold_count_desc' => array(
        	  'text'  => $this->language->get('text_sold_count_desc'),
            'value' => 'sold_count-DESC',
        ),
        'sold_count_asc' => array(
            'text'  => $this->language->get('text_sold_count_asc'),
            'value' => 'sold_count-ASC',
        ),
        'saving_desc' => array(
            'text'  => $this->language->get('text_saving_desc'),
            'value' => 'saving-DESC',
        ),
        'saving_asc' => array(
            'text'  => $this->language->get('text_saving_asc'),
            'value' => 'saving-ASC',
        ),
        'saving_perc_desc' => array(
            'text'  => $this->language->get('text_saving_perc_desc'),
            'value' => 'saving_perc-DESC',
        ),
        'saving_perc_asc' => array(
            'text'  => $this->language->get('text_saving_perc_asc'),
            'value' => 'saving_perc-ASC',
        ),
        'review_count_desc' => array(
            'text'  => $this->language->get('text_review_count_desc'),
            'value' => 'review_count-DESC',
        ),
        'review_count_asc' => array(
            'text'  => $this->language->get('text_review_count_asc'),
            'value' => 'review_count-ASC',
        ),
        'weight_asc' => array(
            'text'  => $this->language->get('text_weight_asc'),
            'value' => 'p.weight-ASC',
        ),
        'weight_desc' => array(
            'text'  => $this->language->get('text_weight_desc'),
            'value' => 'p.weight-DESC',
        ),
        'length_asc' => array(
            'text'  => $this->language->get('text_length_asc'),
            'value' => 'p.length-ASC',
        ),
        'length_desc' => array(
            'text'  => $this->language->get('text_length_desc'),
            'value' => 'p.length-DESC',
        ),
        'width_asc' => array(
            'text'  => $this->language->get('text_width_asc'),
            'value' => 'p.width-ASC',
        ),
        'width_desc' => array(
            'text'  => $this->language->get('text_width_desc'),
            'value' => 'p.width-DESC',
        ),
        'height_asc' => array(
            'text'  => $this->language->get('text_height_asc'),
            'value' => 'p.height-ASC',
        ),
        'height_desc' => array(
            'text'  => $this->language->get('text_height_desc'),
            'value' => 'p.height-DESC',
        ),
        'points_desc' => array(
            'text'  => $this->language->get('text_points_desc'),
            'value' => 'p.points-DESC',
        ),
        'points_asc' => array(
            'text'  => $this->language->get('text_points_asc'),
            'value' => 'p.points-ASC',
        ),
    );

    $category_sorting_methods = $sorting_methods;
    unset($category_sorting_methods['special_price_asc'], $category_sorting_methods['special_price_desc']);
    $data['category_sorting_methods'] = $category_sorting_methods;

    $manufacturer_sorting_methods = $sorting_methods;
    unset($manufacturer_sorting_methods['special_price_asc'], $manufacturer_sorting_methods['special_price_desc']);
    $data['manufacturer_sorting_methods'] = $manufacturer_sorting_methods;

    $search_sorting_methods = $sorting_methods;
    unset($search_sorting_methods['special_price_asc'], $search_sorting_methods['special_price_desc']);
    $data['search_sorting_methods'] = $search_sorting_methods;

    $special_sorting_methods = $sorting_methods;
    unset($special_sorting_methods['price_asc'], $special_sorting_methods['price_desc']);
    $data['special_sorting_methods'] = $special_sorting_methods;

    //
    // Languages
    // --------------------------------------------------

    $this->load->model('localisation/language');

    $languages = $this->model_localisation_language->getLanguages();

    $data['languages'] = $languages;
    $data['primary_language'] = $this->{$this->model_name}->getAdminLanguage();

    //
    // Config
    // --------------------------------------------------

    $config_data = array(
        $this->code . '_status' => false,
        $this->code . '_config' => array(
            'js_debug' => false,
            'sold_time_range' => 90,
            'reviewed_time_range' => 90,
        ),
    );

    $extension_status = (bool)$this->config->get($this->code . '_status');
    $config_info = (array)$this->config->get($this->code . '_config');

		if (count($this->request->post)) {
			$config_data = array_merge($config_data, $this->request->post['config']);

    } elseif (!empty($config_info)) {
      $config_data = array_replace_recursive($config_data, array_merge(array(
          $this->code . '_status' => $extension_status,
          $this->code . '_config' => $config_info
      )));
    }

    $data['config'] = $config_data;

    //
    // Settings data
    // --------------------------------------------------

    $settings_data = array();

		if (isset($this->request->post[$this->code])) {
			$settings_data = $this->request->post[$this->code];
      
		} elseif ($this->config->get($this->code)) {
			$settings_data = $this->config->get($this->code);
		}

    if (version_compare(VERSION, '2.2.0.0', '<')) { // OpenCart 2.1.0.2 or earlier.
      $product_limit = $this->config->get('config_product_limit');
    } elseif (version_compare(VERSION, '3.0.0.0', '<')) { // OpenCart 2.3.0.2 or earlier.
      $product_limit = $this->config->get($this->config->get('config_theme') . '_product_limit');
    } else { // OpenCart 3.0.0.0 or later.
      $product_limit = $this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
    } 

    $default_settings = array(
        'category_status' => false,
        'category_disable_sorting_methods' => array(),
        'category_default_sort_order' => 'p.sort_order-ASC',
        'category_product_limit_status' => false,
        'category_product_limits' => implode(', ', array_unique(array($product_limit, 25, 50, 75, 100))),
        'category_default_product_limit' => 15,
        
        'manufacturer_status' => false,
        'manufacturer_disable_sorting_methods' => array(),
        'manufacturer_default_sort_order' => 'p.sort_order-ASC',
        'manufacturer_product_limit_status' => false,
        'manufacturer_product_limits' => implode(', ', array_unique(array($product_limit, 25, 50, 75, 100))),
        'manufacturer_default_product_limit' => 15,

        'search_status' => false,
        'search_disable_sorting_methods' => array(),
        'search_default_sort_order' => 'p.sort_order-ASC',
        'search_product_limit_status' => false,
        'search_product_limits' => implode(', ', array_unique(array($product_limit, 25, 50, 75, 100))),
        'search_default_product_limit' => 15,

        'special_status' => false,
        'special_disable_sorting_methods' => array(),
        'special_default_sort_order' => 'p.sort_order-ASC',
        'special_product_limit_status' => false,
        'special_product_limits' => implode(', ', array_unique(array($product_limit, 25, 50, 75, 100))),
        'special_default_product_limit' => 15,
    );

    foreach($data['stores'] as $store) {
      if (isset($settings_data[$store['store_id']])) {
        $data[$this->code][$store['store_id']] = array_merge($default_settings, (array)$settings_data[$store['store_id']]);

        foreach(array('category', 'manufacturer', 'search', 'special') as $type) {
          if (isset($data[$this->code][$store['store_id']][$type . '_product_limits'])) {
            $data[$this->code][$store['store_id']][$type . '_product_limits'] = implode(', ', array_unique(array_map('trim', explode(',', $data[$this->code][$store['store_id']][$type . '_product_limits']))));
          }
        }
      } else {
        $data[$this->code][$store['store_id']] = $default_settings;
      }
    }

    //
    // Misc
    // --------------------------------------------------

		$data['code'] = $this->code;
		$data['_code'] = $this->_code;

    $data['dev'] = $this->dev;

		$data['extension_path'] = $this->extension_path;

    // Token
		$data['user_token_key'] = $this->user_token_key;
		$data['user_token_value'] = $this->user_token_value;
    
    
		if (isset($this->request->post['config'][$this->code . '_lic'])) {
			$lic_data = $this->request->post['config'][$this->code . '_lic'];
		} else {
      $lic_data = $this->config->get($this->code . '_lic');
		}
    
    $data['config'][$this->code . '_lic'] = $lic_data;
    
    $data['lic_key'] = $lic_data['key'];
    
    $data['server_name'] = $_SERVER['SERVER_NAME'] ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'];
    $data['admin_language'] = $this->config->get('config_admin_language');
    $data['date_default_timezone'] = date_default_timezone_get();
    $data['server_addr'] = isset($_SERVER['LOCAL_ADDR']) ? $_SERVER['LOCAL_ADDR'] : $_SERVER['SERVER_ADDR'];
    
    $data['copyright_notice_year'] = call_user_func(function($y) {
      $c = date('Y');
      return $y . (($y != $c) ? '-' . $c : '');
    }, 2013);

    //
    // Template
    // --------------------------------------------------

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

    if (version_compare(VERSION, '2.2.0.0', '<')) { // for OpenCart 2.1.0.2 or earlier.
      $this->response->setOutput($this->load->view($this->extension_path . '.tpl', $data));
    } else {
      $this->response->setOutput($this->load->view($this->extension_path, $data));
    }
	}

  /**
   * validateForm method
   *
   * @param void
   * @return boolean True on success or false on failure.
   */
  protected function validateForm() {

    if (!$this->user->hasPermission('modify', $this->extension_path)) {
      $this->error['warning'] = $this->language->get('error_permission');
    }

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

    if ($this->error) {
      return false;
    }

    return true;
  }

  /**
   * Checks if OpenCart 2.0.3.1 or earlier
   *
   * @param void
   * @return bool True or false
   */
  protected function isOC2031orEarlier() {
    return version_compare(str_replace('_rc1', '.RC.1', VERSION), '2.1.0.0.RC.1', '<');
  }

  /**
   * Get the lic data
   *
   * @param void
   * @return response
   */   
	public function get_lic() {
    if (!(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
      $this->logger->write('Error: Invalid Ajax call: ' . __METHOD__ . ' on ' . __LINE__ . ' in ' . __FILE__);
      $this->logger->write($_SERVER);
      return false;
    }
    
    $data = $this->config->get($this->code . '_lic');
    
    if (!$data) {
      $this->logger->write('Error: Failed to fetch license data: ' . __METHOD__ . ' on ' . __LINE__ . ' in ' . __FILE__);
      $this->logger->write($_SERVER);
    }
    
    return $this->response->setOutput(json_encode($data));
  }
  
  /**
   * Save the lic data
   *
   * @param void
   * @return response
   */  
	public function save_lic() {
    if (!(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
      $this->logger->write('Error: Invalid Ajax call: ' . __METHOD__ . ' on ' . __LINE__ . ' in ' . __FILE__);
      $this->logger->write($_SERVER);
      return false;
    }
    
    $value = array(
        'key' => $this->request->post['key'],
        'licensee' => array(
            'name' => $this->request->post['licensee']['name'],
        ),
        'server' => $this->request->post['server'],
        'purchased_at' => array(
            'raw' => $this->request->post['purchased_at']['raw'],
            'formatted' => $this->request->post['purchased_at']['formatted'],
        ),
        'expires_at' => array(
            'raw' => $this->request->post['expires_at']['raw'],
            'formatted' => $this->request->post['expires_at']['formatted'],
        ),
        'status' => array(
            'id' => $this->request->post['status']['id'],
            'name' => $this->request->post['status']['name'],
            'icon' => array(
                'name' => $this->request->post['status']['icon']['name'],
                'color' => $this->request->post['status']['icon']['color']
            ),
        ),
        'checked_at' => array(
            'raw' => $this->request->post['checked_at']['raw'],
            'formatted' => $this->request->post['checked_at']['formatted'],
        ),
        'urls' => array(
            'list' => $this->request->post['urls']['list'],
            'detail' => $this->request->post['urls']['detail'],
        ),
    );
    
    $this->load->model($this->extension_path); 
    $data = $this->{$this->model_name}->updateSettingValue($this->code, $this->code . '_lic', $value);

    if (!$data) {
      $this->logger->write('Error: Failed to save license data: ' . __METHOD__ . ' on ' . __LINE__ . ' in ' . __FILE__);
      $this->logger->write($_SERVER);
    }
    
    if (! $this->isOC2031orEarlier()) { // for OpenCart 2.1.0.0 or later.
      // Convert an object to an array
      $data = json_decode(json_encode($data), true);
    }  

		$this->response->setOutput(json_encode($data));
	}

  /**
   * install method
   *
   * @param void
   * @return void
   */
  public function install() {
    //
  }

  /**
   * uninstall method
   *
   * @param void
   * @return void
   */
  public function uninstall() {
    //
  }

}
