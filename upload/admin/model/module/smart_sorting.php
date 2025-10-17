<?php
/**
 * Smart Sorting
 * 
 * @author  Cuispi
 * @version 2.3.6
 * @license Commercial License
 * @package admin
 * @subpackage  admin.model.module
 */
class ModelModuleSmartSorting extends Model {

  /**
   * Constructor.
   *
   * @param object $registry
   * @return void
   */
	public function __construct($registry) {
    parent::__construct($registry);
	}
  
  /**
   * Get the current admin language
   *
   * @param void
   * @return integer Language
   */
  public function getAdminLanguage() {
    $admin_language = null;

    $this->load->model('localisation/language');
    $languages = $this->model_localisation_language->getLanguages();
    
    if (isset($this->session->data['language']) && $this->session->data['language']) {
      $current_code = $this->session->data['language'];
    } else {
      $current_code = $this->config->get('config_admin_language');
    }
    
    foreach($languages as $code => $language) {
      if ($current_code == $code) {
        $admin_language = $language;
        break;
      }
    }

    return $admin_language;
  }
  
  /**
   * editSetting method
   *
   * @param string $code
   * @return array $data
   * @param integer $store_id
   */
	public function editSetting($code, $data, $store_id = 0) {
    
    if (version_compare(VERSION, '2.0.1.0', '<')) { // for OpenCart 2.0.0.x or earlier.
      
      $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$store_id . "' AND `group` = '" . $this->db->escape($code) . "'");

      foreach ($data as $key => $value) {
        if (!is_array($value)) {
          $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `group` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
        } else {
          $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `group` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape(serialize($value)) . "', serialized = '1'");
        }
      }
      
    } else { // for OpenCart 2.0.1.x or later.
      $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "'");

      foreach ($data as $key => $value) {
        if (!is_array($value)) {
          $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
          
        } else {
          if ($this->isOC2031orEarlier()) { // for OpenCart 2.0.3.1 or earlier.
            $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape(serialize($value)) . "', serialized = '1'");
          
          } else { // for OpenCart 2.1.0.0 or later.
            $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape(json_encode($value)) . "', serialized = '1'");
          }          
        }
      }
    }
	}
  
  /**
   * Check if there is a modification for this extension.
   * 
   * @param void
   * @return boolean True or false
   */  
  public function hasModification() {
    $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "modification` WHERE `code` = 'cuispi_smart_sorting' AND `status` = 1");
    
    if ($query->rows) {
      return true;
    }
    
    return false;
  }
  
  /**
   * Update a single setting value.
   *
   * @param string $code
   * @param string $key
   * @param mixed $value
   * @param integer $store_id
   * @return void
   */ 
	public function updateSettingValue($code, $key, $value, $store_id = 0) {
    
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "'");

    if (substr($key, 0, strlen($code)) == $code) {
      if (!is_array($value)) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
      } else {
        if ($this->isOC2031orEarlier()) { // for OpenCart 2.0.3.1 or earlier.
          $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape(serialize($value)) . "', serialized = '1'");
        } else { // for OpenCart 2.1.0.0 or later.
          $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape(json_encode($value)) . "', serialized = '1'");
        }
      }
      
      $query = $this->db->query("SELECT value, serialized FROM `" . DB_PREFIX . "setting` WHERE `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "' AND store_id = '0'");

      if ($query->row) {
        if (!$query->row['serialized']) {
          $value = $query->row['value'];
        } else {
          if ($this->isOC2031orEarlier()) { // for OpenCart 2.0.3.1 or earlier.
            $value = unserialize($query->row['value']);
          } else { // for OpenCart 2.1.0.0 or later.
            $value = json_decode($query->row['value']);
          }
        }
        return $value;
      }
    }
    
    return null;
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
  
}
