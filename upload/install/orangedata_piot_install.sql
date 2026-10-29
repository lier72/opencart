--
-- OrangeData ТС ПИоТ / разрешительный режим (постановление РФ № 1944) schema.
--
-- Five tables:
--   ocus_order_marking_code — one row PER PHYSICAL UNIT (qty 3 => 3 rows). Holds the scanned
--       marking code, the ТС ПИоТ check verdict, and the tag1265 later written into the receipt.
--   ocus_product_marking    — per-product "is this a marked good + which товарная группа" flag,
--       kept in its own table so the core `ocus_product` table is not altered.
--   ocus_odoo_variant_barcode — per-variant (product_option_value) EAN cache, backfilled from Odoo by
--       cli/odoo_variant_barcode_sync.php; drives EAN-first scan routing on the order panel.
--   ocus_order_return_code — customer returns (возврат прихода), one row per returned sale-receipt position,
--       grouped into legs; built from the registered sale receipt (authoritative price + КМ).
--   ocus_order_fiscal_receipt — one row per submitted SALE receipt, for every fiscalizable order (including
--       orders with no marked goods, which have no order_marking_code rows at all). The only local record that
--       an order's closing receipt was fiscalized; the cron (cronFiscalizeOrders) relies on it to never resend.
--

DROP TABLE IF EXISTS `ocus_order_marking_code`;
CREATE TABLE `ocus_order_marking_code` (
  `marking_code_id`   int NOT NULL AUTO_INCREMENT,
  `order_id`          int NOT NULL,
  `order_product_id`  int NOT NULL COMMENT 'FK to ocus_order_product.order_product_id',
  `product_id`        int NOT NULL DEFAULT '0',
  `unit_index`        int NOT NULL DEFAULT '0' COMMENT '0-based physical unit within the order line (for qty>1)',
  `km_raw`            text COMMENT 'Marking code exactly as scanned (raw, incl. GS \\x1d) — used as receipt itemCode (tag 1163)',
  `km_base64`         text COMMENT 'Base64(km_raw) — used as cis in the ТС ПИоТ check request',
  `gtin`              varchar(20)  NOT NULL DEFAULT '' COMMENT 'from check response',
  `pg`                int DEFAULT NULL COMMENT 'товарная группа id sent in the check (2=footwear, 1=apparel)',
  `is_sale_allowed`   tinyint(1) DEFAULT NULL COMMENT 'NULL=not checked; 1=продажа разрешена; 0=запрещена',
  `status_code`       int DEFAULT NULL COMMENT 'ТС ПИоТ statusCode: 200 ok / 203 аварийный / 514 ЧЗ down / -1 ТС ПИоТ down',
  `reason_code`       int DEFAULT NULL COMMENT 'checkResult.reason.code when sale is denied',
  `reason_text`       varchar(1024) NOT NULL DEFAULT '',
  `req_id`            varchar(64)  NOT NULL DEFAULT '' COMMENT 'reqId (UUID) from the check',
  `req_timestamp`     bigint DEFAULT NULL COMMENT 'reqTimestamp (UTC ms) from the check',
  `tag1265`           varchar(255) NOT NULL DEFAULT '' COMMENT 'UUID=<reqId>&Time=<reqTimestamp> — value of отраслевой реквизит 1265',
  `is_checked_offline` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'isCheckedOffline (ЛМ ЧЗ offline check)',
  `check_backend`     varchar(16)  NOT NULL DEFAULT '' COMMENT 'orangedata | lmchz — backend that produced the verdict',
  `response_json`     mediumtext COMMENT 'full raw check response for this code (audit)',
  `document_id`       varchar(64)  NOT NULL DEFAULT '' COMMENT 'OrangeData receipt id once fiscalized',
  `fiscalized`        tinyint(1) NOT NULL DEFAULT '0',
  `checked_at`        datetime DEFAULT NULL,
  `date_added`        datetime NOT NULL,
  `date_modified`     datetime NOT NULL,
  PRIMARY KEY (`marking_code_id`),
  KEY `order_id` (`order_id`),
  KEY `order_product_id` (`order_product_id`),
  UNIQUE KEY `unit_unique` (`order_product_id`,`unit_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-unit marking codes + ТС ПИоТ verdict for разрешительный режим';

DROP TABLE IF EXISTS `ocus_product_marking`;
CREATE TABLE `ocus_product_marking` (
  `product_id`        int NOT NULL,
  `is_marked`         tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = subject to обязательная маркировка / разрешительный режим',
  `marking_group_id`  int NOT NULL DEFAULT '0' COMMENT 'товарная группа id (Приложение 1): 2=обувь, 1=одежда, 0=не задано',
  `date_modified`     datetime NOT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-product marked-goods flag + товарная группа';

-- Rebuildable cache (IF NOT EXISTS: a manual re-run must not wipe synced barcodes). The authoritative
-- creator is OrangeDataRepository::install(); refresh the data with cli/odoo_variant_barcode_sync.php.
CREATE TABLE IF NOT EXISTS `ocus_odoo_variant_barcode` (
  `product_option_value_id` int NOT NULL COMMENT 'FK to ocus_product_option_value; = ocus_odoo_product_variant_map.opencart_product_option_id',
  `product_id`              int NOT NULL DEFAULT '0',
  `odoo_product_id`         int NOT NULL DEFAULT '0' COMMENT 'source Odoo product.product id (traceability)',
  `ean`                     varchar(20) NOT NULL DEFAULT '' COMMENT 'Odoo product.product.barcode for this variant',
  `date_modified`           datetime NOT NULL,
  PRIMARY KEY (`product_option_value_id`),
  KEY `product_id` (`product_id`),
  KEY `ean` (`ean`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-variant EAN cache for EAN-first DataMatrix scan routing';

-- Customer returns (возврат прихода). Built from the REGISTERED sale receipt, so each row references a sale
-- position: pos_ref = 'km:<sha1(itemCode)>' (marked unit, returned once) or 'pos:<index>' (non-marked, quantity
-- capped at sold qty). Grouped into legs (oc-<order>-return-<leg>). Authoritative creator: OrangeDataRepository::install().
CREATE TABLE IF NOT EXISTS `ocus_order_return_code` (
  `return_code_id`   int NOT NULL AUTO_INCREMENT,
  `order_id`         int NOT NULL,
  `return_leg`       int NOT NULL DEFAULT '1' COMMENT '1-based: several partial returns per order',
  `sale_document_id` varchar(80) NOT NULL DEFAULT '' COMMENT 'the sale receipt this return is built from',
  `pos_ref`          varchar(80) NOT NULL COMMENT "km:<sha1(itemCode)> | pos:<saleIndex>",
  `item_code`        text COMMENT 'КМ sent on the return (sold code reused, or scanned override)',
  `text`             varchar(255) NOT NULL DEFAULT '',
  `price`            decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'per-unit refund price, copied from the sale receipt',
  `quantity`         decimal(15,3) NOT NULL DEFAULT '1.000',
  `document_id`      varchar(80) NOT NULL DEFAULT '',
  `fp`               varchar(32) NOT NULL DEFAULT '',
  `fiscalized`       tinyint(1) NOT NULL DEFAULT '0',
  `date_added`       datetime NOT NULL,
  `date_modified`    datetime NOT NULL,
  PRIMARY KEY (`return_code_id`),
  KEY `order_id` (`order_id`),
  UNIQUE KEY `leg_pos` (`order_id`,`return_leg`,`pos_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer return (возврат прихода) positions per leg';

-- One row per submitted SALE receipt, for EVERY fiscalizable order — including orders with no marked goods,
-- which have no ocus_order_marking_code rows at all. Without this there is no local record such an order was
-- fiscalized, so the cron could not tell "not fiscalized yet" from "fiscalized, nothing to store" and would
-- re-send it on every run. Also the only place the sale receipt's ФП is kept (returns already keep theirs, in
-- ocus_order_return_code). Authoritative creator: OrangeDataRepository::install().
CREATE TABLE IF NOT EXISTS `ocus_order_fiscal_receipt` (
  `fiscal_receipt_id` int NOT NULL AUTO_INCREMENT,
  `order_id`          int NOT NULL,
  `document_id`       varchar(80) NOT NULL COMMENT 'OrangeData receipt id (oc-<order>-ship[-n] | …-return-<leg>)',
  `kind`              varchar(8) NOT NULL DEFAULT 'sale' COMMENT 'sale | return',
  `return_leg`        int NOT NULL DEFAULT '0' COMMENT '0 for a sale, else the return leg',
  `fiscalized`        tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 only once the ФН returned an ФП',
  `fp`                varchar(32) NOT NULL DEFAULT '' COMMENT 'фискальный признак',
  `document_number`   varchar(32) NOT NULL DEFAULT '',
  `amount`            decimal(15,2) NOT NULL DEFAULT '0.00',
  `payment_type`      int DEFAULT NULL COMMENT 'checkClose payment type: 14 зачёт аванса / 2 безнал / 1 нал',
  `last_error`        varchar(1024) NOT NULL DEFAULT '',
  `date_added`        datetime NOT NULL,
  `date_modified`     datetime NOT NULL,
  PRIMARY KEY (`fiscal_receipt_id`),
  KEY `order_id` (`order_id`),
  UNIQUE KEY `document` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One row per submitted sale/return receipt, for cron idempotency';
