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

require_once DIR_SYSTEM . 'library/smart_sorting/smart_sorting_base.php';

class SmartSorting {

  /**
   * Registry
   *
   * @var object
   */     
  private static $registry;

  /**
   * Constructor.
   *
   * @param object $registry
   */  
	public function __construct($registry) {
    self::$registry = $registry;
  }

  /**
   * Check whether or not the extension is enabled.
   *
   * @param void
   * @return boolean
   */
  public static function isExtensionEnabled() {
    return SmartSortingBase::getInstance()->init(self::$registry)->isExtensionEnabled();
  }
  
  /**
   * Check whether or not the smart sorting of the given type is enabled.
   *
   * @param string $type category, manufacturer, search, or special
   * @return boolean
   */
  public static function isSmartSortingEnabled($type) {
    return SmartSortingBase::getInstance()->init(self::$registry)->isSmartSortingEnabled($type);
  }
  
  /**
   * Get a value of a given key from the Smart Sorting settings.
   *
   * @param string $key Expression containing the name of the key setting to return.
   * @return A value of a given key from the Smart Sorting settings.
   */
  public static function getSetting($key) {
    return SmartSortingBase::getInstance()->init(self::$registry)->getSetting($key);
  }
  
  /**
   * Returns a list of key settings and their respective values.
   *
   * @param void
   * @return Returns a list of key settings and their respective values from an 
   *          extension's entry in the Config registry.
   */
  public static function getAllSettings() {
    return SmartSortingBase::getInstance()->init(self::$registry)->getAllSettings();
  }
  
  /**
   * Get a value of a given key from the Configuration settings.
   *
   * @param string $key Expression containing the name of the key setting to return.
   * @return A value of a given key from the Configuration settings.
   */
  public static function getConfig($key) {
    return SmartSortingBase::getInstance()->init(self::$registry)->getConfig($key);
  }

  /**
   * Gets a sort key.
   *
   * @param string $type category, manufacturer, search, or special
   * @return Sort key
   */
  public static function getSortKey($type) {
    return SmartSortingBase::getInstance()->init(self::$registry)->getSortKey($type);
  }
  
  /**
   * Gets a sort direction.
   *
   * @param string $type category, manufacturer, search, or special
   * @return Sort direction
   */
  public static function getSortDirection($type) {
    return SmartSortingBase::getInstance()->init(self::$registry)->getSortDirection($type);
  }
  
  /**
   * Remove the specified sorting method(s) from the full list of sorting methods.
   * 
   * @param string $type category, manufacturer, search, or special
   * @param string $sorts The full list of sorting methods
   * @return Returns the rest of the sorting methods after the removal.
   */
  public static function removeSortingMethods($type, $sorts) {
    return SmartSortingBase::getInstance()->init(self::$registry)->removeSortingMethods($type, $sorts);
  }

  /**
   * Get the current version of the extension.
   *
   * @param void
   * @return SmartSortingBase::getVersion()
   */
  public static function version() {
    return SmartSortingBase::getInstance()->getVersion();
  }  
  
}
