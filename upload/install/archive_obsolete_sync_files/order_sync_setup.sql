-- ============================================
-- OpenCart Order Synchronization Infrastructure
-- Phase 1: Basic Tables for OC2 <-> OC3 Sync
-- TEMPORARY SOLUTION - No modification to core tables
-- ============================================

-- 1. Sync Queue Table
-- Captures all order changes for synchronization
CREATE TABLE IF NOT EXISTS `ocus_order_sync_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_db` enum('oc2','oc3') NOT NULL COMMENT 'Which database generated this change',
  `table_name` varchar(64) NOT NULL COMMENT 'Table being synced (order, order_product, odoo_order_map, etc)',
  `operation` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `record_id` int(11) NOT NULL COMMENT 'Primary key of the record (order_id, etc)',
  `parent_order_id` int(11) DEFAULT NULL COMMENT 'Associated order_id for child tables',
  `data_json` text NOT NULL COMMENT 'Full record data as JSON',
  `sync_status` enum('pending','synced','error','skip') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `synced_at` timestamp NULL DEFAULT NULL,
  `retry_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`sync_status`, `created_at`),
  KEY `idx_table_record` (`table_name`, `record_id`),
  KEY `idx_parent_order` (`parent_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Queue for order sync between OC2 and OC3';

-- 2. Order ID Mapping Table
-- Maps order_id between OC2 and OC3
-- Also tracks sync metadata without touching core tables
CREATE TABLE IF NOT EXISTS `ocus_order_id_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `oc2_order_id` int(11) DEFAULT NULL COMMENT 'Order ID in OC2 database',
  `oc3_order_id` int(11) DEFAULT NULL COMMENT 'Order ID in OC3 database',
  `last_synced_from` enum('oc2','oc3') NOT NULL COMMENT 'Last sync direction',
  `last_synced_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sync_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable sync for this order',
  `sync_lock` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Prevents infinite loop during sync',
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_oc2_order` (`oc2_order_id`),
  UNIQUE KEY `idx_oc3_order` (`oc3_order_id`),
  KEY `idx_synced_from` (`last_synced_from`, `last_synced_at`),
  KEY `idx_sync_lock` (`sync_lock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Maps order IDs between OC2 and OC3';

-- 3. Sync Log Table
-- Detailed logging of sync operations
CREATE TABLE IF NOT EXISTS `ocus_order_sync_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sync_queue_id` int(11) DEFAULT NULL,
  `operation` varchar(64) NOT NULL,
  `source_db` enum('oc2','oc3') NOT NULL,
  `target_db` enum('oc2','oc3') NOT NULL,
  `order_id_source` int(11) DEFAULT NULL,
  `order_id_target` int(11) DEFAULT NULL,
  `status` enum('success','error','warning') NOT NULL,
  `message` text,
  `execution_time_ms` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue_id` (`sync_queue_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Detailed sync operation logs';

-- ============================================
-- Verification
-- ============================================
SELECT 'Sync infrastructure tables created successfully' AS status;
