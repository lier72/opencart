--
-- Table structure for table `ocus_filter_seo_facet_config`
--
-- Admin-editable per-facet slug rules (attribute/option/filter group level):
-- a custom prefix qualifier (optionally standing in for the WHOLE prefix,
-- not just combined with the facet name), and whether to strip a trailing
-- "(...)"/"[...]" from generated values' slugs. Read by
-- FilterSeoUrl::loadConfig() - see system/library/filterseourl.php.
--

DROP TABLE IF EXISTS `ocus_filter_seo_facet_config`;
CREATE TABLE `ocus_filter_seo_facet_config` (
  `filter_seo_facet_config_id` int NOT NULL AUTO_INCREMENT,
  `type` enum('fa','fo','ff') NOT NULL COMMENT 'fa=attribute, fo=option, ff=filter',
  `type_id` int NOT NULL COMMENT 'fa: attribute_id | fo: option_id | ff: filter_group_id',
  `prefix_override` varchar(255) NOT NULL DEFAULT '' COMMENT 'combined with the facet''s own name as the qualifier, e.g. raketki-tsvet-*, unless omit_facet_name',
  `omit_facet_name` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'when set, prefix_override (or nothing) IS the whole prefix - the facet''s own name is dropped entirely',
  `strip_parenthetical` tinyint(1) NOT NULL DEFAULT '0',
  `strip_brackets` tinyint(1) NOT NULL DEFAULT '0',
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`filter_seo_facet_config_id`),
  UNIQUE KEY `facet` (`type`,`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin-editable filter SEO facet-level slug rules';

--
-- Table structure for table `ocus_filter_seo_value_config`
--
-- Admin-editable per-value slug text override. Keyed like
-- ocus_filter_seo_url's reverse_lookup: for 'fa' by the exact literal
-- attribute text (value_hash), for 'fo'/'ff' by the numeric value_id.
--

DROP TABLE IF EXISTS `ocus_filter_seo_value_config`;
CREATE TABLE `ocus_filter_seo_value_config` (
  `filter_seo_value_config_id` int NOT NULL AUTO_INCREMENT,
  `type` enum('fa','fo','ff') NOT NULL,
  `type_id` int NOT NULL,
  `value_id` int NOT NULL DEFAULT '0' COMMENT 'fo: option_value_id | ff: filter_id | fa: unused',
  `value_text` varchar(500) NOT NULL DEFAULT '' COMMENT 'fa only: TRIM()d attribute text this override applies to',
  `value_hash` char(32) NOT NULL DEFAULT '' COMMENT 'fa only: MD5(value_text)',
  `override` varchar(255) NOT NULL DEFAULT '' COMMENT 'slug text to use instead of the auto-derived one',
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`filter_seo_value_config_id`),
  UNIQUE KEY `value` (`type`,`type_id`,`value_id`,`value_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin-editable filter SEO per-value slug overrides';

--
-- Seed data - matches this store's actual configured facets, so a fresh
-- install/clone starts with the same customizations already applied via
-- Admin > Catalog > Filter SEO rather than the auto-derived defaults.
--

INSERT INTO `ocus_filter_seo_facet_config` (`type`, `type_id`, `prefix_override`, `omit_facet_name`, `strip_parenthetical`, `strip_brackets`, `date_modified`) VALUES
('fo', 30, '', 0, 1, 0, NOW()),          -- string colour: strip manufacturer codes like "(C066)"
('fo', 11, '', 0, 0, 1, NOW()),          -- clothing size: strip "[Asia (S)]" bracket notation
('fa', 19, 'raketki', 1, 0, 0, NOW()),   -- "Спецификация ракеток" colour -> raketki-belyy (no "tsvet"), collides with fa70's "Общий" colour
('fa', 70, '', 1, 1, 0, NOW());          -- "Общий" colour -> just "belyy" (no prefix at all), hex code stripped
