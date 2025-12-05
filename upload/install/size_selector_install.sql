-- Journal3 Size Selector Module
-- Database schema for option-to-gender/size-type mapping

-- Table: Size Option Mapping
-- Maps OpenCart product options to gender and size type
CREATE TABLE IF NOT EXISTS `ocus_j3_size_option_mapping` (
  `mapping_id` int(11) NOT NULL AUTO_INCREMENT,
  `option_id` int(11) NOT NULL COMMENT 'OpenCart option_id',
  `gender` enum('women','universal','unisex') NOT NULL DEFAULT 'unisex' COMMENT 'Gender category (universal = men/unisex)',
  `size_type` enum('shoes','apparel') NOT NULL COMMENT 'Type of sizing system',
  `source_system` enum('EU','US','UK','Asian','mm') NOT NULL COMMENT 'Original size system in option values',
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Enable size selector for this option',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `option_id` (`option_id`),
  KEY `gender_type` (`gender`, `size_type`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Maps product options to size selector configuration';

-- Table: Size Guide Content
-- Stores size guide HTML/text content per category or global
CREATE TABLE IF NOT EXISTS `ocus_j3_size_guide` (
  `guide_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL COMMENT 'Specific category or NULL for global',
  `gender` enum('women','universal','unisex') NOT NULL,
  `size_type` enum('shoes','apparel') NOT NULL,
  `guide_content` text COMMENT 'HTML content for size guide',
  `measurement_image` varchar(255) DEFAULT NULL COMMENT 'Path to measurement diagram',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`guide_id`),
  KEY `category_id` (`category_id`),
  KEY `gender_type` (`gender`, `size_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Size guide content and measurement tables';

-- Table: Module Settings
-- Stores module configuration
CREATE TABLE IF NOT EXISTS `ocus_j3_size_selector_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Size selector module settings';

-- Insert default settings
INSERT INTO `ocus_j3_size_selector_settings` (`setting_key`, `setting_value`) VALUES
('module_enabled', '1'),
('default_size_system', 'EU'),
('show_stock_status', '1'),
('enable_size_guide', '1'),
('size_button_style', 'grid'),
('mobile_optimized', '1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Example mapping data (you'll configure this via admin)
-- Uncomment and modify for your actual option IDs:
-- INSERT INTO `ocus_j3_size_option_mapping` (`option_id`, `gender`, `size_type`, `source_system`, `enabled`) VALUES
-- (5, 'women', 'shoes', 'EU', 1),
-- (12, 'universal', 'shoes', 'EU', 1),
-- (8, 'unisex', 'apparel', 'Asian', 1);
