<?php
/**
 * OrangeDataRepository
 *
 * Data access for the module's two tables (order_marking_code, product_marking), written against the raw
 * OpenCart DB object so it works in BOTH admin and catalog (event) contexts — unlike an OpenCart Model,
 * which is resolved per-application and would fatal when loaded from the wrong side.
 *
 * Scope: used by OrangeDataService (admin + catalog) and delegated to by the admin Model so there is a
 * single source of truth for these queries.
 */
class OrangeDataRepository {

    /** @var object OpenCart DB wrapper (query/escape/getLastId). */
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /** Creates the tables if absent (idempotent). Scope: module install + settings save. */
    public function install() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_marking_code` (
              `marking_code_id`    int NOT NULL AUTO_INCREMENT,
              `order_id`           int NOT NULL,
              `order_product_id`   int NOT NULL,
              `product_id`         int NOT NULL DEFAULT '0',
              `unit_index`         int NOT NULL DEFAULT '0',
              `km_raw`             text,
              `km_base64`          text,
              `gtin`               varchar(20) NOT NULL DEFAULT '',
              `pg`                 int DEFAULT NULL,
              `is_sale_allowed`    tinyint(1) DEFAULT NULL,
              `status_code`        int DEFAULT NULL,
              `reason_code`        int DEFAULT NULL,
              `reason_text`        varchar(1024) NOT NULL DEFAULT '',
              `req_id`             varchar(64) NOT NULL DEFAULT '',
              `req_timestamp`      bigint DEFAULT NULL,
              `tag1265`            varchar(255) NOT NULL DEFAULT '',
              `is_checked_offline` tinyint(1) NOT NULL DEFAULT '0',
              `check_backend`      varchar(16) NOT NULL DEFAULT '',
              `response_json`      mediumtext,
              `document_id`        varchar(80) NOT NULL DEFAULT '',
              `fiscalized`         tinyint(1) NOT NULL DEFAULT '0',
              `checked_at`         datetime DEFAULT NULL,
              `date_added`         datetime NOT NULL,
              `date_modified`      datetime NOT NULL,
              PRIMARY KEY (`marking_code_id`),
              KEY `order_id` (`order_id`),
              KEY `order_product_id` (`order_product_id`),
              UNIQUE KEY `unit_unique` (`order_product_id`,`unit_index`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_marking` (
              `product_id`       int NOT NULL,
              `is_marked`        tinyint(1) NOT NULL DEFAULT '0',
              `marking_group_id` int NOT NULL DEFAULT '0',
              `date_modified`    datetime NOT NULL,
              PRIMARY KEY (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Per-variant EAN cache: one barcode per product_option_value (size/colour), resolved from Odoo
        // through odoo_product_variant_map and refreshed by cli/odoo_variant_barcode_sync.php. Drives the
        // EAN-first scan routing on the order panel (a single ocus_product.ean cannot hold a barcode per size).
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "odoo_variant_barcode` (
              `product_option_value_id` int NOT NULL,
              `product_id`              int NOT NULL DEFAULT '0',
              `odoo_product_id`         int NOT NULL DEFAULT '0',
              `ean`                     varchar(20) NOT NULL DEFAULT '',
              `date_modified`           datetime NOT NULL,
              PRIMARY KEY (`product_option_value_id`),
              KEY `product_id` (`product_id`),
              KEY `ean` (`ean`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Migration for installs created before check_backend existed (records which backend produced the verdict).
        $col = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "order_marking_code` LIKE 'check_backend'");
        if (!$col->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "order_marking_code` ADD `check_backend` varchar(16) NOT NULL DEFAULT '' AFTER `is_checked_offline`");
        }
    }

    /* ---- per-variant barcodes (EAN-first scan routing) ---- */

    /**
     * getVariantEans
     *
     * Returns [product_option_value_id => ean] for the given option-value ids, from the odoo_variant_barcode
     * cache. Blank/zero ids are ignored. Scope: order panel (order()) resolves each order line's own size
     * barcode so an EAN scan routes to the exact line; empties mean "not yet synced from Odoo".
     *
     * @param array $pov_ids product_option_value_id list
     * @return array [int product_option_value_id => string ean]
     */
    public function getVariantEans(array $pov_ids) {
        $ids = array();
        foreach ($pov_ids as $id) { if ((int) $id > 0) $ids[(int) $id] = (int) $id; }
        if (!$ids) return array();
        // Degrade gracefully to "no EAN" if the cache table was not created yet (fresh deploy before the module
        // settings are saved or the backfill CLI is run), instead of 500-ing the whole order panel.
        try {
            $q = $this->db->query("SELECT product_option_value_id, ean FROM `" . DB_PREFIX . "odoo_variant_barcode` WHERE ean <> '' AND product_option_value_id IN (" . implode(',', $ids) . ")");
        } catch (Exception $e) {
            return array();
        }
        $out = array();
        foreach ($q->rows as $r) { $out[(int) $r['product_option_value_id']] = $r['ean']; }
        return $out;
    }

    /**
     * getProductEans
     *
     * Returns [product_id => ean] from ocus_product for the given products (non-empty only). Scope: order
     * panel fallback for single-variant goods, which have no option row and so no per-variant barcode.
     *
     * @param array $product_ids
     * @return array [int product_id => string ean]
     */
    public function getProductEans(array $product_ids) {
        $ids = array();
        foreach ($product_ids as $id) { if ((int) $id > 0) $ids[(int) $id] = (int) $id; }
        if (!$ids) return array();
        $q = $this->db->query("SELECT product_id, ean FROM `" . DB_PREFIX . "product` WHERE ean <> '' AND product_id IN (" . implode(',', $ids) . ")");
        $out = array();
        foreach ($q->rows as $r) { $out[(int) $r['product_id']] = $r['ean']; }
        return $out;
    }

    /**
     * saveVariantBarcode
     *
     * Upserts one product_option_value's barcode. Scope: cli/odoo_variant_barcode_sync.php backfill — the
     * only writer of odoo_variant_barcode; the order panel only reads it.
     *
     * @param int    $pov_id          product_option_value_id (primary key)
     * @param int    $product_id
     * @param int    $odoo_product_id source Odoo variant id (for traceability)
     * @param string $ean
     */
    public function saveVariantBarcode($pov_id, $product_id, $odoo_product_id, $ean) {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "odoo_variant_barcode` SET
                product_option_value_id = '" . (int) $pov_id . "', product_id = '" . (int) $product_id . "',
                odoo_product_id = '" . (int) $odoo_product_id . "', ean = '" . $this->db->escape($ean) . "', date_modified = NOW()
            ON DUPLICATE KEY UPDATE
                product_id = '" . (int) $product_id . "', odoo_product_id = '" . (int) $odoo_product_id . "',
                ean = '" . $this->db->escape($ean) . "', date_modified = NOW()
        ");
    }

    /**
     * getVariantMapRows
     *
     * Returns the Odoo↔OpenCart variant mapping rows that carry a product_option_value_id, so the backfill can
     * ask Odoo for each variant's barcode and store it per option value. Scope: cli backfill only.
     *
     * @return array rows of [odoo_product_id, opencart_product_id, product_option_value_id]
     */
    public function getVariantMapRows() {
        return $this->db->query("
            SELECT odoo_product_id, opencart_product_id, opencart_product_option_id AS product_option_value_id
            FROM `" . DB_PREFIX . "odoo_product_variant_map`
            WHERE opencart_product_option_id > 0
        ")->rows;
    }

    /* ---- product marking ---- */

    /**
     * parseCategoryMap
     *
     * Normalises the marked-categories setting into [category_id => group_id]. Accepts the structured form
     * (array of ['category_id','group_id'] rows, as saved by the picker UI) or the legacy
     * "category_id:group_id" per-line string. Scope: callers pass the result to getProductMarking().
     *
     * @param array|string $value
     * @return array
     */
    public static function parseCategoryMap($value) {
        $map = array();
        if (is_array($value)) {
            foreach ($value as $row) {
                if (isset($row['category_id']) && isset($row['group_id']) && (int) $row['category_id']) {
                    $map[(int) $row['category_id']] = (int) $row['group_id'];
                }
            }
            return $map;
        }
        foreach (preg_split('/\r\n|\r|\n/', (string) $value) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ':') === false) continue;
            list($c, $g) = explode(':', $line, 2);
            $map[(int) trim($c)] = (int) trim($g);
        }
        return $map;
    }

    /** Returns the explicit per-product override row, or null when the product uses category defaults. */
    public function getMarkingOverride($product_id) {
        $q = $this->db->query("SELECT is_marked, marking_group_id FROM `" . DB_PREFIX . "product_marking` WHERE product_id = '" . (int)$product_id . "'");
        return $q->num_rows ? array('is_marked' => (int)$q->row['is_marked'], 'marking_group_id' => (int)$q->row['marking_group_id']) : null;
    }

    /**
     * getProductMarking
     *
     * Resolves whether a product is a marked good and its товарная группа. Precedence:
     *   1. explicit per-product override (product_marking row) — force yes/no;
     *   2. otherwise, category membership: marked if any of the product's categories (or their ancestors)
     *      appears in $categoryGroupMap, taking that group.
     *
     * @param int   $product_id
     * @param array $categoryGroupMap [category_id => group_id] from parseCategoryMap().
     * @return array ['is_marked' => int, 'marking_group_id' => int]
     */
    public function getProductMarking($product_id, array $categoryGroupMap = array()) {
        $override = $this->getMarkingOverride($product_id);
        if ($override !== null) {
            return $override; // authoritative (force mark or force unmark)
        }
        if ($categoryGroupMap) {
            $q = $this->db->query("
                SELECT DISTINCT cp.path_id
                FROM `" . DB_PREFIX . "product_to_category` p2c
                JOIN `" . DB_PREFIX . "category_path` cp ON cp.category_id = p2c.category_id
                WHERE p2c.product_id = '" . (int)$product_id . "'
            ");
            foreach ($q->rows as $r) {
                $cid = (int) $r['path_id'];
                if (isset($categoryGroupMap[$cid])) {
                    return array('is_marked' => 1, 'marking_group_id' => (int) $categoryGroupMap[$cid]);
                }
            }
        }
        return array('is_marked' => 0, 'marking_group_id' => 0);
    }

    public function setProductMarking($product_id, $is_marked, $marking_group_id) {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "product_marking` SET
                product_id = '" . (int)$product_id . "', is_marked = '" . (int)$is_marked . "',
                marking_group_id = '" . (int)$marking_group_id . "', date_modified = NOW()
            ON DUPLICATE KEY UPDATE
                is_marked = '" . (int)$is_marked . "', marking_group_id = '" . (int)$marking_group_id . "', date_modified = NOW()
        ");
    }

    /** Removes a per-product override so the product falls back to category-based marking. */
    public function deleteProductMarking($product_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_marking` WHERE product_id = '" . (int)$product_id . "'");
    }

    /* ---- marking codes ---- */

    public function getOrderMarkingCodes($order_id) {
        return $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_marking_code` WHERE order_id = '" . (int)$order_id . "' ORDER BY order_product_id, unit_index")->rows;
    }

    public function saveScannedCode(array $d) {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "order_marking_code` SET
                order_id = '" . (int)$d['order_id'] . "', order_product_id = '" . (int)$d['order_product_id'] . "',
                product_id = '" . (int)$d['product_id'] . "', unit_index = '" . (int)$d['unit_index'] . "',
                km_raw = '" . $this->db->escape($d['km_raw']) . "', km_base64 = '" . $this->db->escape($d['km_base64']) . "',
                date_added = NOW(), date_modified = NOW()
            ON DUPLICATE KEY UPDATE
                marking_code_id = LAST_INSERT_ID(marking_code_id),
                km_raw = '" . $this->db->escape($d['km_raw']) . "', km_base64 = '" . $this->db->escape($d['km_base64']) . "',
                is_sale_allowed = NULL, status_code = NULL, reason_code = NULL, reason_text = '',
                req_id = '', req_timestamp = NULL, tag1265 = '', response_json = NULL, date_modified = NOW()
        ");
        return (int)$this->db->getLastId();
    }

    public function saveCheckResult($id, array $r) {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "order_marking_code` SET
                is_sale_allowed = " . (isset($r['is_sale_allowed']) && $r['is_sale_allowed'] !== null ? "'" . (int)$r['is_sale_allowed'] . "'" : "NULL") . ",
                status_code = " . (isset($r['status_code']) && $r['status_code'] !== null ? "'" . (int)$r['status_code'] . "'" : "NULL") . ",
                reason_code = " . (isset($r['reason_code']) && $r['reason_code'] !== null ? "'" . (int)$r['reason_code'] . "'" : "NULL") . ",
                reason_text = '" . $this->db->escape(isset($r['reason_text']) ? $r['reason_text'] : '') . "',
                req_id = '" . $this->db->escape(isset($r['req_id']) ? $r['req_id'] : '') . "',
                req_timestamp = " . (isset($r['req_timestamp']) && $r['req_timestamp'] !== null ? "'" . (int)$r['req_timestamp'] . "'" : "NULL") . ",
                tag1265 = '" . $this->db->escape(isset($r['tag1265']) ? $r['tag1265'] : '') . "',
                is_checked_offline = '" . (int)(!empty($r['is_checked_offline'])) . "',
                check_backend = '" . $this->db->escape(isset($r['check_backend']) ? $r['check_backend'] : '') . "',
                gtin ='" . $this->db->escape(isset($r['gtin']) ? $r['gtin'] : '') . "',
                " . (isset($r['pg']) && $r['pg'] !== null ? "pg = '" . (int)$r['pg'] . "'," : "") . "
                response_json = '" . $this->db->escape(isset($r['response_json']) ? $r['response_json'] : '') . "',
                checked_at = NOW(), date_modified = NOW()
            WHERE marking_code_id = '" . (int)$id . "'
        ");
    }

    /** Records the document id on all order codes (pending, before ФН confirmation). */
    public function setDocumentId($order_id, $document_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "order_marking_code` SET document_id = '" . $this->db->escape($document_id) . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
    }

    /** Marks codes fiscalized (only after the ФН confirms, i.e. fp present). */
    public function markFiscalized($order_id, $document_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "order_marking_code` SET fiscalized = '1', document_id = '" . $this->db->escape($document_id) . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
    }

    public function deleteCode($id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "order_marking_code` WHERE marking_code_id = '" . (int)$id . "' AND fiscalized = '0'");
    }
}
