-- ============================================================================
-- Odoo Attribute to OpenCart Option Mapping Configuration
-- ============================================================================
-- This script helps configure the mapping between Odoo product attributes and 
-- OpenCart product options in the ocus_odoo_config table.
--
-- STEP 1: Find your OpenCart option IDs
-- ============================================================================
SELECT o.option_id, od.name 
FROM ocus_option o 
LEFT JOIN ocus_option_description od ON (o.option_id = od.option_id) 
WHERE od.language_id = 1
ORDER BY od.name;

-- STEP 2: Find your Odoo attribute IDs (you'll need to query Odoo for this)
-- ============================================================================
-- In Odoo, run: SELECT id, name FROM product_attribute;
-- Common examples:
--   id=5  -> "Size"
--   id=7  -> "Color"
--   id=12 -> "Material"

-- STEP 3: Create mappings
-- ============================================================================
-- Format: INSERT INTO ocus_odoo_config (`key`, `value`) 
--         VALUES ('odoo_attribute_{odoo_attribute_id}', '{opencart_option_id}');

-- Example: Map Odoo attribute_id 5 (Size) to OpenCart option_id 13
INSERT INTO ocus_odoo_config (`key`, `value`) VALUES ('odoo_attribute_5', '13');

-- Example: Map Odoo attribute_id 7 (Color) to OpenCart option_id 5
-- INSERT INTO ocus_odoo_config (`key`, `value`) VALUES ('odoo_attribute_7', '5');

-- Example: Map Odoo attribute_id 12 (Material) to OpenCart option_id 8
-- INSERT INTO ocus_odoo_config (`key`, `value`) VALUES ('odoo_attribute_12', '8');


-- ============================================================================
-- View existing mappings
-- ============================================================================
SELECT 
    oc.`key` as config_key,
    oc.`value` as opencart_option_id,
    od.name as opencart_option_name
FROM ocus_odoo_config oc
LEFT JOIN ocus_option_description od ON (od.option_id = oc.`value` AND od.language_id = 1)
WHERE oc.`key` LIKE 'odoo_attribute_%'
ORDER BY oc.`key`;


-- ============================================================================
-- Delete a mapping (if needed)
-- ============================================================================
-- DELETE FROM ocus_odoo_config WHERE `key` = 'odoo_attribute_5';


-- ============================================================================
-- IMPORTANT NOTES:
-- ============================================================================
-- 1. The mapping key format is: 'odoo_attribute_{odoo_attribute_id}'
-- 2. The mapping value is the OpenCart option_id (integer)
-- 3. The OpenCart option_id must exist in the ocus_option table
-- 4. Option values will be matched automatically using LIKE '%{code}%'
--    Example: Odoo code "S" matches OpenCart option values like:
--             "S", "Small", "us(S)", "Asia (S)", etc.
-- 5. This mapping is REQUIRED before creating products with options via the API
-- 6. No new options or option values are created automatically
