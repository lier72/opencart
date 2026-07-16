<?php
/**
 * Odoo Attribute Value Mapping Model
 *
 * Stores explicit vendor-code → OpenCart option_value_id overrides that take precedence
 * over the default LIKE-based matching in the product sync and quantity sync pipelines.
 *
 * Purpose: different shoe vendors label sizes with their own mm reference tables
 * (e.g. vendor says 260mm = US 8, store chart says 260mm = US 8.5). This model lets
 * admins define the correct per-vendor mapping without changing product data.
 *
 * Key lookup method: resolveOptionValueId() — call this before any LIKE fallback when
 * resolving an Odoo attribute value code to an OpenCart option_value_id.
 */
class ModelExtensionModuleOdooAttributeValueMapping extends Model {

    /**
     * Create the mapping table if it does not exist.
     * Called from the controller on first page load.
     */
    public function ensureTable() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "odoo_attribute_value_mapping` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `odoo_attribute_id` int(11) NOT NULL,
            `odoo_value_code` varchar(64) NOT NULL,
            `opencart_option_value_id` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_mapping` (`odoo_attribute_id`, `odoo_value_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Maps Odoo attribute value codes to OpenCart option_value_ids'");
    }

    /**
     * Returns all Odoo attributes that have an attribute-level mapping in odoo_config,
     * together with the mapped OpenCart option name and its option_value list.
     *
     * Used to build the per-attribute panels in the admin UI.
     *
     * @return array [['odoo_attribute_id', 'opencart_option_id', 'option_name', 'option_values', 'mappings'], ...]
     */
    public function getMappedAttributes() {
        // GROUP BY oc.key deduplicated in case odoo_config has no UNIQUE constraint on `key`
        $query = $this->db->query("
            SELECT
                oc.key,
                MAX(oc.value) AS opencart_option_id,
                od.name       AS option_name
            FROM " . DB_PREFIX . "odoo_config oc
            LEFT JOIN " . DB_PREFIX . "option_description od
                ON (od.option_id = oc.value
                AND od.language_id = '" . (int)$this->config->get('config_language_id') . "')
            WHERE oc.key LIKE 'odoo_attribute_%'
            GROUP BY oc.key
            ORDER BY oc.key ASC
        ");

        $attributes = array();
        foreach ($query->rows as $row) {
            if (!preg_match('/odoo_attribute_(\d+)/', $row['key'], $matches)) {
                continue;
            }
            $odoo_attribute_id  = (int)$matches[1];
            $opencart_option_id = (int)$row['opencart_option_id'];

            $attributes[] = array(
                'odoo_attribute_id'  => $odoo_attribute_id,
                'opencart_option_id' => $opencart_option_id,
                'option_name'        => $row['option_name'],
                'option_values'      => $this->getOpenCartOptionValues($opencart_option_id),
                'mappings'           => $this->getValueMappings($odoo_attribute_id),
            );
        }

        return $attributes;
    }

    /**
     * Returns all value mappings for one Odoo attribute, joined with the option value name.
     *
     * @param int $odoo_attribute_id
     * @return array [['id', 'odoo_value_code', 'opencart_option_value_id', 'option_value_name'], ...]
     */
    public function getValueMappings($odoo_attribute_id) {
        $query = $this->db->query("
            SELECT
                m.id,
                m.odoo_value_code,
                m.opencart_option_value_id,
                ovd.name AS option_value_name
            FROM " . DB_PREFIX . "odoo_attribute_value_mapping m
            LEFT JOIN " . DB_PREFIX . "option_value_description ovd
                ON (ovd.option_value_id = m.opencart_option_value_id
                AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "')
            WHERE m.odoo_attribute_id = '" . (int)$odoo_attribute_id . "'
            ORDER BY m.odoo_value_code ASC
        ");

        return $query->rows;
    }

    /**
     * Returns all option values for a given OpenCart option_id, for use in the admin dropdown.
     *
     * @param int $option_id
     * @return array [['option_value_id', 'name'], ...]
     */
    public function getOpenCartOptionValues($option_id) {
        $query = $this->db->query("
            SELECT ov.option_value_id, ovd.name
            FROM " . DB_PREFIX . "option_value ov
            LEFT JOIN " . DB_PREFIX . "option_value_description ovd
                ON (ov.option_value_id = ovd.option_value_id
                AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "')
            WHERE ov.option_id = '" . (int)$option_id . "'
            ORDER BY ovd.name ASC
        ");

        return $query->rows;
    }

    /**
     * Insert or update a single value mapping.
     *
     * @param int    $odoo_attribute_id
     * @param string $odoo_value_code          e.g. "260"
     * @param int    $opencart_option_value_id  oc_option_value.option_value_id
     * @return bool true on success
     */
    public function saveMapping($odoo_attribute_id, $odoo_value_code, $opencart_option_value_id) {
        $this->db->query("
            INSERT INTO " . DB_PREFIX . "odoo_attribute_value_mapping
                (odoo_attribute_id, odoo_value_code, opencart_option_value_id)
            VALUES
                ('" . (int)$odoo_attribute_id . "',
                 '" . $this->db->escape($odoo_value_code) . "',
                 '" . (int)$opencart_option_value_id . "')
            ON DUPLICATE KEY UPDATE
                opencart_option_value_id = '" . (int)$opencart_option_value_id . "'
        ");

        return true;
    }

    /**
     * Delete a value mapping by its primary key id.
     *
     * @param int $id
     */
    public function deleteMapping($id) {
        $this->db->query("
            DELETE FROM " . DB_PREFIX . "odoo_attribute_value_mapping
            WHERE id = '" . (int)$id . "'
        ");
    }

    /**
     * Delete all value mappings for one Odoo attribute.
     * Called before a bulk import so stale entries are removed.
     *
     * @param int $odoo_attribute_id
     */
    public function clearMappingsForAttribute($odoo_attribute_id) {
        $this->db->query("
            DELETE FROM " . DB_PREFIX . "odoo_attribute_value_mapping
            WHERE odoo_attribute_id = '" . (int)$odoo_attribute_id . "'
        ");
    }

    /**
     * Look up the OpenCart option_value_id for a given Odoo attribute + vendor code.
     *
     * This is the primary API used by the product sync pipeline and quantity sync script.
     * Returns null when no explicit mapping exists (caller should then fall back to LIKE).
     *
     * Usage: call this before LIKE-based option value resolution in:
     *   - catalog/controller/api/product.php::prepareProductOptions()
     *   - catalog/controller/api/product.php::createVariantMappings()
     *   - admin/update_product_quantity.php::CreateProductMappingTable()
     *
     * @param int    $odoo_attribute_id
     * @param string $odoo_value_code  e.g. "260"
     * @return int|null  opencart option_value_id, or null if not mapped
     */
    /**
     * Returns all size conversion tables that have a mm column, for the import dropdown.
     *
     * @return array [['table_id', 'gender_type', 'size_type', 'table_name', 'has_mm'], ...]
     */
    public function getSizeCharts() {
        $query = $this->db->query("
            SELECT table_id, gender_type, size_type, table_name, table_data
            FROM " . DB_PREFIX . "j3_size_conversion_table
            WHERE enabled = 1
            ORDER BY gender_type, size_type
        ");

        $charts = array();
        foreach ($query->rows as $row) {
            $data = json_decode($row['table_data'], true);
            $charts[] = array(
                'table_id'    => $row['table_id'],
                'gender_type' => $row['gender_type'],
                'size_type'   => $row['size_type'],
                'table_name'  => $row['table_name'],
                'has_mm'      => isset($data['mm']),
            );
        }

        return $charts;
    }

    /**
     * Import mm→US size mappings from j3_size_conversion_table into odoo_attribute_value_mapping.
     *
     * Reads the mm[] and US[] arrays from the conversion table (they are index-aligned),
     * then for each mm value looks up the OpenCart option_value_id by matching the US size
     * name against option_value_description for the given option_id.
     *
     * @param int $odoo_attribute_id
     * @param int $opencart_option_id  The OpenCart option (e.g. "Размер обуви унисекс (US)")
     * @param int $table_id            ocus_j3_size_conversion_table.table_id
     * @return array ['imported'=>int, 'skipped'=>int, 'errors'=>string[]]
     */
    public function importFromSizeChart($odoo_attribute_id, $opencart_option_id, $table_id) {
        $result = array('imported' => 0, 'skipped' => 0, 'errors' => array());

        $q = $this->db->query("
            SELECT table_data FROM " . DB_PREFIX . "j3_size_conversion_table
            WHERE table_id = '" . (int)$table_id . "'
            LIMIT 1
        ");

        if (!$q->num_rows) {
            $result['errors'][] = 'Size chart table_id ' . (int)$table_id . ' not found.';
            return $result;
        }

        $data = json_decode($q->row['table_data'], true);
        if (!isset($data['mm']) || !isset($data['US'])) {
            $result['errors'][] = 'Size chart has no mm or US columns.';
            return $result;
        }

        $mm_values = $data['mm'];
        $us_values = $data['US'];

        if (count($mm_values) !== count($us_values)) {
            $result['errors'][] = 'mm and US arrays have different lengths.';
            return $result;
        }

        // Build a name→option_value_id lookup for this option
        $ov_query = $this->db->query("
            SELECT ovd.option_value_id, ovd.name
            FROM " . DB_PREFIX . "option_value ov
            LEFT JOIN " . DB_PREFIX . "option_value_description ovd
                ON (ov.option_value_id = ovd.option_value_id
                AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "')
            WHERE ov.option_id = '" . (int)$opencart_option_id . "'
        ");

        // Index by name for fast lookup; also strip whitespace
        $ov_by_name = array();
        foreach ($ov_query->rows as $ov) {
            $ov_by_name[trim($ov['name'])] = (int)$ov['option_value_id'];
        }

        foreach ($mm_values as $i => $mm) {
            $mm      = trim((string)$mm);
            $us_size = trim((string)$us_values[$i]);

            if ($mm === '' || $us_size === '') {
                $result['skipped']++;
                continue;
            }

            // Option value names use comma as decimal separator: "40 1/3 us(7,5)"
            // Chart uses dot: "7.5" → normalize to comma for matching
            $us_size_comma = str_replace('.', ',', $us_size);

            // Match the US size only when surrounded by parentheses to avoid
            // partial matches (e.g. "4" must not match "40 1/3 us(7,5)")
            $option_value_id = null;
            foreach ($ov_by_name as $name => $id) {
                $lower = mb_strtolower($name);
                if (strpos($lower, '(' . $us_size_comma . ')') !== false ||
                    strpos($lower, '(' . $us_size . ')') !== false) {
                    $option_value_id = $id;
                    break;
                }
            }

            if (!$option_value_id) {
                $result['skipped']++;
                $result['errors'][] = "US size \"$us_size\" (mm=$mm) — no matching option value found.";
                continue;
            }

            $this->db->query("
                INSERT INTO " . DB_PREFIX . "odoo_attribute_value_mapping
                    (odoo_attribute_id, odoo_value_code, opencart_option_value_id)
                VALUES
                    ('" . (int)$odoo_attribute_id . "',
                     '" . $this->db->escape($mm) . "',
                     '" . (int)$option_value_id . "')
                ON DUPLICATE KEY UPDATE
                    opencart_option_value_id = '" . (int)$option_value_id . "'
            ");

            $result['imported']++;
        }

        return $result;
    }

    /**
     * Look up the OpenCart option_value_id for a given Odoo attribute + vendor code.
     *
     * This is the primary API used by the product sync pipeline and quantity sync script.
     * Returns null when no explicit mapping exists (caller should then fall back to LIKE).
     *
     * Usage: call this before LIKE-based option value resolution in:
     *   - catalog/controller/api/product.php::prepareProductOptions()
     *   - catalog/controller/api/product.php::createVariantMappings()
     *   - admin/update_product_quantity.php::CreateProductMappingTable()
     *
     * @param int    $odoo_attribute_id
     * @param string $odoo_value_code  e.g. "260"
     * @return int|null  opencart option_value_id, or null if not mapped
     */
    public function resolveOptionValueId($odoo_attribute_id, $odoo_value_code) {
        $query = $this->db->query("
            SELECT opencart_option_value_id
            FROM " . DB_PREFIX . "odoo_attribute_value_mapping
            WHERE odoo_attribute_id = '" . (int)$odoo_attribute_id . "'
            AND odoo_value_code     = '" . $this->db->escape($odoo_value_code) . "'
            LIMIT 1
        ");

        if ($query->num_rows) {
            return (int)$query->row['opencart_option_value_id'];
        }

        return null;
    }
}
