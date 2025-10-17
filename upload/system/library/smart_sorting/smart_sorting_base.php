<?php
/**
 * Smart Sorting
 *
 * @author  Cuispi
 * @version 2.3.6
 * @license Commercial License
 * @package system
 * @subpackage  system.library.smart_sorting
 */
class SmartSortingBase {

  /**
   * Holds the current version of the extension.
   *
   * @var string
   */
  protected $version = '2.3.6';

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
   * Variable for the Logger class instance.
   *
   * @var Logger instance
   */
  protected $logger;

  /**
   * The singleton instance of the SmartSortingBase class.
   *
   * @var SmartSortingBase
   */
  protected static $instance;

  /**
   * Create a new SmartSortingBase instance.
   *
   */
  private function __construct() {}

  /**
   * Get or create the singleton SmartSortingBase instance.
   *
   * @return SmartSortingBase
   */
  public static function getInstance() {
    if (!(self::$instance instanceof self)) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Initialize and setup the SmartSortingBase instance.
   *
   * @param  object $registry
   * @return $this
   */
  public function init($registry) {
		$this->registry = $registry;
		$this->config = $registry->get('config');

    if (($pos = strripos(__CLASS__, '\\')) !== false) {
      $class = substr(__CLASS__, $pos + 1);
    } else {
      $class = __CLASS__;
    }

    $class_name = strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $class));

    if (strpos($class_name, '_base') !== false) {
      list($code, ) = explode('_base', $class_name);
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

    $this->logger = new Log($this->code . '.log');

    return $this;
  }

  /**
   * Check whether or not the extension is enabled.
   *
   * @param void
   * @return boolean
   */
  public function isExtensionEnabled() {
    return (bool)$this->config->get($this->code . '_status');
  }

  /**
   * Check whether or not the smart sorting of the given type is enabled.
   *
   * @param string $type category, manufacturer, search, or special
   * @return boolean
   */
  public function isSmartSortingEnabled($type) {
    $status = (bool)$this->getSetting($type . '_status');
    return $this->isExtensionEnabled() === true && $status === true ? true : false;
  }

  /**
   * Get a value of a given key from the Smart Sorting settings.
   *
   * @param string $key Expression containing the name of the key setting to return.
   * @return A value of a given key from the Smart Sorting settings.
   */
  public function getSetting($key) {
    $return = null;

    $settings = $this->config->get($this->code);

    if (isset($settings[(int)$this->config->get('config_store_id')][$key])) {
      $return = $settings[(int)$this->config->get('config_store_id')][$key];
    }

    return $return;
  }

  /**
   * Returns a list of key settings and their respective values.
   *
   * @param void
   * @return Returns a list of key settings and their respective values from an
   *          extension's entry in the Config registry.
   */
  public function getAllSettings() {
    return $this->config->get($this->code);
  }

  /**
   * Get a value of a given key from the Configuration settings.
   *
   * @param string $key Expression containing the name of the key setting to return.
   * @return A value of a given key from the Configuration settings.
   */
  public function getConfig($key) {
    $return = null;

    $settings = $this->config->get($this->code. '_config');

    if (isset($settings[$key])) {
      $return = $settings[$key];
    }

    return $return;
  }

  /**
   * Gets a sort key.
   *
   * @param string $type category, manufacturer, search, or special
   * @return Sort key
   */
  public function getSortKey($type) {
    $default_sort_order = $this->getSetting($type . '_default_sort_order');

		if (strpos($default_sort_order, '-') !== false) {
			list($key, $direction) = explode('-', $default_sort_order);
      return strtolower($key);
		}

    return false;
  }

  /**
   * Gets a sort direction.
   *
   * @param string $type category, manufacturer, search, or special
   * @return Sort direction
   */
  public function getSortDirection($type) {
    $default_sort_order = $this->getSetting($type . '_default_sort_order');

		if (strpos($default_sort_order, '-') !== false) {
			list($key, $direction) = explode('-', $default_sort_order);
      return strtoupper($direction);
		}

    return false;
  }

  /**
   * Remove the specified sorting method(s) from the full list of sorting methods.
   *
   * @param string $type category, manufacturer, search, or special
   * @param string $sorts The full list of sorting methods
   * @return Returns the rest of the sorting methods after the removal.
   */
  public function removeSortingMethods($type, $sorts) {
    $disabled_methods = $this->getSetting($type . '_disable_sorting_methods');

    if ($disabled_methods) {
      foreach ($sorts as $index => $sort) {
        foreach ($disabled_methods as $value) {
          if ($sort['value'] == $value) {
            unset($sorts[$index]);
          }
        }
      }
    }

    return $sorts;
  }

  /**
   * Get the current version of the extension.
   *
   * @param void
   * @return string The current version of the extension.
   */
  public function getVersion() {
    return $this->version;
  }

}
