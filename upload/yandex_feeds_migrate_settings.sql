-- ============================================================================
-- Yandex Feeds Settings Migration Script
-- Migrates settings from OC2 format to OC3 format
-- ============================================================================
--
-- IMPORTANT: Make a backup of your database before running this script!
--
-- This script renames setting keys from OC2 format to OC3 format:
-- - yandex_market_* -> feed_yandex_market_*
-- - yandex_sitemap_* -> feed_yandex_sitemap_*
--
-- Usage:
-- 1. Backup your database
-- 2. Replace 'ocus_' with your actual table prefix if different
-- 3. Run this script
-- ============================================================================

-- Check if old settings exist
SELECT 'Checking for old Yandex Market settings...' AS status;
SELECT * FROM ocus_setting WHERE `key` LIKE 'yandex_market_%';

SELECT 'Checking for old Yandex Sitemap settings...' AS status;
SELECT * FROM ocus_setting WHERE `key` LIKE 'yandex_sitemap_%';

-- Migrate Yandex Market settings
SELECT 'Migrating Yandex Market settings...' AS status;
UPDATE ocus_setting
SET `key` = REPLACE(`key`, 'yandex_market_', 'feed_yandex_market_'),
    `code` = 'feed_yandex_market'
WHERE `key` LIKE 'yandex_market_%'
  AND `code` = 'yandex_market';

-- Migrate Yandex Sitemap settings
SELECT 'Migrating Yandex Sitemap settings...' AS status;
UPDATE ocus_setting
SET `key` = REPLACE(`key`, 'yandex_sitemap_', 'feed_yandex_sitemap_'),
    `code` = 'feed_yandex_sitemap'
WHERE `key` LIKE 'yandex_sitemap_%'
  AND `code` = 'yandex_sitemap';

-- Verify migration
SELECT 'Verifying Yandex Market settings...' AS status;
SELECT * FROM ocus_setting WHERE `key` LIKE 'feed_yandex_market_%';

SELECT 'Verifying Yandex Sitemap settings...' AS status;
SELECT * FROM ocus_setting WHERE `key` LIKE 'feed_yandex_sitemap_%';

SELECT 'Migration complete!' AS status;

-- ============================================================================
-- Alternative: Manual INSERT statements if you prefer to keep old settings
-- ============================================================================
-- Uncomment the following if you want to create new settings while keeping old ones:

/*
-- Insert new Yandex Market settings based on old ones
INSERT INTO ocus_setting (store_id, code, `key`, `value`, serialized)
SELECT
    store_id,
    'feed_yandex_market' AS code,
    REPLACE(`key`, 'yandex_market_', 'feed_yandex_market_') AS `key`,
    `value`,
    serialized
FROM ocus_setting
WHERE `key` LIKE 'yandex_market_%'
  AND NOT EXISTS (
    SELECT 1 FROM ocus_setting s2
    WHERE s2.`key` = REPLACE(ocus_setting.`key`, 'yandex_market_', 'feed_yandex_market_')
  );

-- Insert new Yandex Sitemap settings based on old ones
INSERT INTO ocus_setting (store_id, code, `key`, `value`, serialized)
SELECT
    store_id,
    'feed_yandex_sitemap' AS code,
    REPLACE(`key`, 'yandex_sitemap_', 'feed_yandex_sitemap_') AS `key`,
    `value`,
    serialized
FROM ocus_setting
WHERE `key` LIKE 'yandex_sitemap_%'
  AND NOT EXISTS (
    SELECT 1 FROM ocus_setting s2
    WHERE s2.`key` = REPLACE(ocus_setting.`key`, 'yandex_sitemap_', 'feed_yandex_sitemap_')
  );
*/
