<?php
/**
 * Odoo Attribute Mapping Model
 *
 * Handles mapping between Odoo product attributes and OpenCart product options
 * User: max
 * Date: 23/12/24
 */

class ModelExtensionModuleOdooAttributeMapping extends Model {

    /**
     * Get all OpenCart product options
     *
     * @return array
     */
    public function getOpenCartOptions() {
        $query = $this->db->query("
            SELECT o.option_id, od.name
            FROM " . DB_PREFIX . "option o
            LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id)
            WHERE od.language_id = '" . (int)$this->config->get('config_language_id') . "'
            ORDER BY od.name ASC
        ");

        return $query->rows;
    }

    /**
     * Get Odoo product attributes via API
     *
     * @param array $connection Odoo connection data
     * @return array
     */
    public function getOdooAttributes($connection) {
        try {
            $models = $connection['client'];
            $db_name = $connection['db'];
            $uid = $connection['userId'];
            $password = $connection['pwd'];

            // Get all product attributes from Odoo
            $attributes = $models->execute_kw($db_name, $uid, $password,
                'product.attribute', 'search_read',
                array(array()),                                         // args: empty domain
                array('fields' => array('id', 'name', 'display_name')) // kwargs: separate argument
            );

            if (isset($attributes['faultCode'])) {
                $this->log->write('Error getting Odoo attributes: ' . $attributes['faultString']);
                return array();
            }

            return $attributes;

        } catch (Exception $e) {
            $this->log->write('Error getting Odoo attributes: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Get existing attribute mappings from odoo_config
     *
     * @return array
     */
    public function getMappings() {
        $query = $this->db->query("
            SELECT
                oc.id,
                oc.key as config_key,
                oc.value as opencart_option_id,
                od.name as opencart_option_name
            FROM " . DB_PREFIX . "odoo_config oc
            LEFT JOIN " . DB_PREFIX . "option_description od ON (od.option_id = oc.value AND od.language_id = '" . (int)$this->config->get('config_language_id') . "')
            WHERE oc.key LIKE 'odoo_attribute_%'
            ORDER BY oc.key
        ");

        $mappings = array();
        foreach ($query->rows as $row) {
            // Extract odoo_attribute_id from key "odoo_attribute_X"
            if (preg_match('/odoo_attribute_(\d+)/', $row['config_key'], $matches)) {
                $mappings[$matches[1]] = array(
                    'id' => $row['id'],
                    'odoo_attribute_id' => $matches[1],
                    'opencart_option_id' => $row['opencart_option_id'],
                    'opencart_option_name' => $row['opencart_option_name']
                );
            }
        }

        return $mappings;
    }

    /**
     * Save attribute mappings
     *
     * @param array $mappings Array of mappings [odoo_attribute_id => opencart_option_id]
     * @return void
     */
    public function saveMappings($mappings) {
        if (!is_array($mappings)) {
            return;
        }

        foreach ($mappings as $odoo_attribute_id => $opencart_option_id) {
            $odoo_attribute_id = (int)$odoo_attribute_id;
            $opencart_option_id = (int)$opencart_option_id;

            if ($odoo_attribute_id <= 0) {
                continue;
            }

            $config_key = 'odoo_attribute_' . $odoo_attribute_id;

            if ($opencart_option_id > 0) {
                // Create or update mapping
                $this->db->query("
                    INSERT INTO " . DB_PREFIX . "odoo_config (`key`, `value`)
                    VALUES ('" . $this->db->escape($config_key) . "', '" . (int)$opencart_option_id . "')
                    ON DUPLICATE KEY UPDATE `value` = '" . (int)$opencart_option_id . "'
                ");
            } else {
                // Delete mapping if opencart_option_id is 0 or empty
                $this->db->query("
                    DELETE FROM " . DB_PREFIX . "odoo_config
                    WHERE `key` = '" . $this->db->escape($config_key) . "'
                ");
            }
        }
    }

    /**
     * Delete a mapping
     *
     * @param int $odoo_attribute_id
     * @return void
     */
    public function deleteMapping($odoo_attribute_id) {
        $config_key = 'odoo_attribute_' . (int)$odoo_attribute_id;

        $this->db->query("
            DELETE FROM " . DB_PREFIX . "odoo_config
            WHERE `key` = '" . $this->db->escape($config_key) . "'
        ");
    }
}
