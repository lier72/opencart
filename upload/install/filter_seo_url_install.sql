--
-- Table structure for table `ocus_filter_seo_url`
--
-- Maps Journal3 filter values (fa{attribute_id}, fo{option_id}, ff{filter_id})
-- to pretty URL slugs, so catalog/controller/startup/seo_url.php can turn
-- ?fa70=... query params into /colour-belyi path segments and back.
--

DROP TABLE IF EXISTS `ocus_filter_seo_url`;
CREATE TABLE `ocus_filter_seo_url` (
  `filter_seo_url_id` int NOT NULL AUTO_INCREMENT,
  `store_id` int NOT NULL,
  `language_id` int NOT NULL,
  `type` enum('fa','fo','ff') NOT NULL COMMENT 'fa=attribute, fo=option, ff=filter',
  `type_id` int NOT NULL COMMENT 'fa: attribute_id | fo: option_id | ff: filter_group_id',
  `value_id` int NOT NULL DEFAULT '0' COMMENT 'fo: option_value_id | ff: filter_id | fa: unused',
  `value_text` varchar(500) NOT NULL DEFAULT '' COMMENT 'fa only: TRIM()d attribute text',
  `value_hash` char(32) NOT NULL DEFAULT '' COMMENT 'fa only: MD5(value_text)',
  `slug` varchar(255) NOT NULL COMMENT 'e.g. colour-belyi, razmer-42',
  `needs_review` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'set when slug collided and had to be numerically suffixed',
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`filter_seo_url_id`),
  UNIQUE KEY `slug_unique` (`store_id`,`language_id`,`slug`),
  UNIQUE KEY `reverse_lookup` (`store_id`,`language_id`,`type`,`type_id`,`value_id`,`value_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal3 filter value to SEO URL slug mapping';
