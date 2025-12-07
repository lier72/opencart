-- MySQL dump 10.13  Distrib 8.3.0, for macos12.6 (x86_64)
--
-- Host: localhost    Database: a1627-unqs-oc3
-- ------------------------------------------------------
-- Server version	8.3.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ocus_j3_size_category_mapping`
--

DROP TABLE IF EXISTS `ocus_j3_size_category_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocus_j3_size_category_mapping` (
  `mapping_id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `gender_type` enum('men','women','kids','unisex') NOT NULL DEFAULT 'unisex',
  `age_group` enum('adult','kids','baby') NOT NULL DEFAULT 'adult',
  `inherit_children` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `category_id` (`category_id`),
  KEY `gender_type` (`gender_type`),
  KEY `age_group` (`age_group`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ocus_j3_size_category_mapping`
--

LOCK TABLES `ocus_j3_size_category_mapping` WRITE;
/*!40000 ALTER TABLE `ocus_j3_size_category_mapping` DISABLE KEYS */;
INSERT INTO `ocus_j3_size_category_mapping` VALUES (1,62,'men','adult',1,'2025-12-07 00:12:36','2025-12-07 00:12:36'),(2,63,'women','adult',1,'2025-12-07 00:13:15','2025-12-07 00:13:15'),(3,91,'kids','kids',1,'2025-12-07 00:36:08','2025-12-07 00:36:08');
/*!40000 ALTER TABLE `ocus_j3_size_category_mapping` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ocus_j3_size_conversion_table`
--

DROP TABLE IF EXISTS `ocus_j3_size_conversion_table`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocus_j3_size_conversion_table` (
  `table_id` int NOT NULL AUTO_INCREMENT,
  `gender_type` enum('men','women','kids','baby','unisex') NOT NULL,
  `size_type` enum('shoes','apparel') NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `table_data` text NOT NULL COMMENT 'JSON encoded conversion data',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`table_id`),
  UNIQUE KEY `gender_size_type` (`gender_type`,`size_type`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ocus_j3_size_conversion_table`
--

LOCK TABLES `ocus_j3_size_conversion_table` WRITE;
/*!40000 ALTER TABLE `ocus_j3_size_conversion_table` DISABLE KEYS */;
INSERT INTO `ocus_j3_size_conversion_table` VALUES (1,'women','shoes','Женская обувь','{\"EU\":[\"33 2/3\",\"34 1/3\",\"35\",\"35 2/3\",\"36 1/3\",\"37\",\"37 2/3\",\"38 1/3\",\"39\",\"39 2/3\",\"40 1/3\",\"41\",\"41 2/3\",\"42 1/3\",\"43\",\"43 2/3\",\"44 1/3\"],\"US\":[\"4\",\"4.5\",\"5\",\"5.5\",\"6\",\"6.5\",\"7\",\"7.5\",\"8\",\"8.5\",\"9\",\"9.5\",\"10\",\"10.5\",\"11\",\"11.5\",\"12\"],\"UK\":[\"1.5\",\"2\",\"2.5\",\"3\",\"3.5\",\"4\",\"4.5\",\"5\",\"5.5\",\"6\",\"6.5\",\"7\",\"7.5\",\"8\",\"8.5\",\"9\",\"9.5\"],\"mm\":[\"205\",\"210\",\"215\",\"220\",\"225\",\"230\",\"235\",\"240\",\"245\",\"250\",\"255\",\"260\",\"265\",\"270\",\"275\",\"280\",\"285\"]}',1,'2025-12-06 21:54:00','2025-12-06 21:54:00'),(2,'men','shoes','Мужская обувь','{\"EU\":[\"35 2/3\",\"36 1/3\",\"37\",\"37 2/3\",\"38 1/3\",\"39\",\"39 2/3\",\"40 1/3\",\"41\",\"41 2/3\",\"42 1/3\",\"43\",\"43 2/3\",\"44 1/3\",\"45\",\"45 2/3\",\"46 1/3\",\"47\",\"47 2/3\",\"48 1/3\",\"49\"],\"US\":[\"4\",\"4.5\",\"5\",\"5.5\",\"6\",\"6.5\",\"7\",\"7.5\",\"8\",\"8.5\",\"9\",\"9.5\",\"10\",\"10.5\",\"11\",\"11.5\",\"12\",\"12.5\",\"13\",\"13.5\",\"14\"],\"UK\":[\"3\",\"3.5\",\"4\",\"4.5\",\"5\",\"5.5\",\"6\",\"6.5\",\"7\",\"7.5\",\"8\",\"8.5\",\"9\",\"9.5\",\"10\",\"10.5\",\"11\",\"11.5\",\"12\",\"12.5\",\"13\"],\"mm\":[\"215\",\"220\",\"225\",\"230\",\"235\",\"240\",\"245\",\"250\",\"255\",\"260\",\"265\",\"270\",\"275\",\"280\",\"285\",\"290\",\"295\",\"300\",\"305\",\"310\",\"315\"]}',1,'2025-12-06 21:54:00','2025-12-06 21:54:00'),(3,'unisex','shoes','Универсальная обувь','{\"EU\":[\"35 2/3\",\"36 1/3\",\"37\",\"37 2/3\",\"38 1/3\",\"39\",\"39 2/3\",\"40 1/3\",\"41\",\"41 2/3\",\"42 1/3\",\"43\",\"43 2/3\",\"44 1/3\",\"45\",\"45 2/3\",\"46 1/3\",\"47\",\"47 2/3\",\"48 1/3\",\"49\"],\"US\":[\"4\",\"4.5\",\"5\",\"5.5\",\"6\",\"6.5\",\"7\",\"7.5\",\"8\",\"8.5\",\"9\",\"9.5\",\"10\",\"10.5\",\"11\",\"11.5\",\"12\",\"12.5\",\"13\",\"13.5\",\"14\"],\"UK\":[\"3\",\"3.5\",\"4\",\"4.5\",\"5\",\"5.5\",\"6\",\"6.5\",\"7\",\"7.5\",\"8\",\"8.5\",\"9\",\"9.5\",\"10\",\"10.5\",\"11\",\"11.5\",\"12\",\"12.5\",\"13\"],\"mm\":[\"215\",\"220\",\"225\",\"230\",\"235\",\"240\",\"245\",\"250\",\"255\",\"260\",\"265\",\"270\",\"275\",\"280\",\"285\",\"290\",\"295\",\"300\",\"305\",\"310\",\"315\"]}',1,'2025-12-06 21:54:00','2025-12-06 21:54:00'),(4,'kids','shoes','Детская обувь','{\"EU\":[\"31\",\"31 2\\/3\",\"32\",\"33\",\"34 1\\/3\",\"35\",\"35 2\\/3\",\"36 1\\/3\",\"37\",\"37 2\\/3\",\"38 1\\/3\",\"39\",\"39 2\\/3\",\"40 1\\/3\",\"41\",\"41 2\\/3\",\"42 1\\/3\"],\"US\":[\"0.5\",\"1\",\"1.5\",\"2\",\"3\",\"3.5\",\"4\",\"4.5\",\"5\",\"5.5\",\"6\",\"6.5\",\"7\",\"7.5\",\"8\",\"8.5\",\"9\"],\"UK\":[\"12\",\"13\",\"13.5\",\"1.5\",\"2.5\",\"3\",\"3.5\",\"3.5\",\"4\",\"4.5\",\"5\",\"5.5\",\"6\",\"6.5\",\"7\",\"7.5\",\"8\"],\"mm\":[\"180\",\"190\",\"195\",\"200\",\"210\",\"215\",\"220\",\"225\",\"230\",\"235\",\"240\",\"245\",\"250\",\"255\",\"260\",\"265\",\"270\"]}',1,'2025-12-06 21:54:00','2025-12-07 05:49:15'),(5,'baby','shoes','Обувь для малышей','{\"EU\":[\"23\",\"24\",\"25\",\"25.5\",\"26\",\"26.5\",\"27\",\"28\",\"29\",\"30\"],\"US\":[\"7C\",\"8C\",\"9C\",\"8TC\",\"9TC\",\"10TC\",\"10C\",\"11C\",\"11TC\",\"12TC\"],\"UK\":[\"0.5\",\"1\",\"2\",\"3\",\"4\",\"4.5\",\"5.5\"],\"mm\":[\"140\",\"145\",\"145\",\"150\",\"155\",\"160\",\"165\",\"170\",\"180\",\"185\"]}',1,'2025-12-06 21:54:00','2025-12-07 05:43:54'),(6,'men','apparel','Мужская одежда','{\"Asian\":[\"XS\",\"S\",\"M\",\"L\",\"XL\",\"XXL\",\"3XL\",\"4XL\"],\"EU\":[\"XXS\",\"XS\",\"S\",\"M\",\"L\",\"XL\",\"XXL\",\"3XL\"],\"US\":[\"3XS\",\"XXS\",\"XS\",\"S\",\"M\",\"L\",\"XL\",\"XXL\"]}',1,'2025-12-06 21:54:00','2025-12-07 05:54:35'),(7,'women','apparel','Женская одежда','{\"Asian\":[\"XS\",\"S\",\"M\",\"L\",\"XL\",\"XXL\",\"3XL\"],\"EU\":[\"XXS\",\"XS\",\"S\",\"M\",\"L\",\"XL\",\"XXL\"],\"US\":[\"3XS\",\"XXS\",\"XS\",\"S\",\"M\",\"L\",\"XL\"]}',1,'2025-12-06 21:54:00','2025-12-07 07:20:30'),(8,'kids','apparel','Детская одежда','{\"Asian\":[\"110\",\"116\",\"122\",\"128\",\"134\",\"140\",\"146\",\"152\",\"158\",\"164\"],\"EU\":[\"110\",\"116\",\"122\",\"128\",\"134\",\"140\",\"146\",\"152\",\"158\",\"164\"],\"US\":[\"5\",\"6\",\"7\",\"8\",\"10\",\"12\",\"14\",\"16\",\"18\",\"20\"]}',1,'2025-12-06 21:54:00','2025-12-06 21:54:00');
/*!40000 ALTER TABLE `ocus_j3_size_conversion_table` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ocus_j3_size_guide`
--

DROP TABLE IF EXISTS `ocus_j3_size_guide`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocus_j3_size_guide` (
  `guide_id` int NOT NULL AUTO_INCREMENT,
  `category_id` int DEFAULT NULL COMMENT 'Specific category or NULL for global',
  `gender` enum('women','men','kids','universal','unisex') NOT NULL,
  `size_type` enum('shoes','apparel') NOT NULL,
  `guide_content` text COMMENT 'HTML content for size guide',
  `measurement_image` varchar(255) DEFAULT NULL COMMENT 'Path to measurement diagram',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`guide_id`),
  KEY `category_id` (`category_id`),
  KEY `gender_type` (`gender`,`size_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Size guide content and measurement tables';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ocus_j3_size_guide`
--

LOCK TABLES `ocus_j3_size_guide` WRITE;
/*!40000 ALTER TABLE `ocus_j3_size_guide` DISABLE KEYS */;
/*!40000 ALTER TABLE `ocus_j3_size_guide` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ocus_j3_size_option_mapping`
--

DROP TABLE IF EXISTS `ocus_j3_size_option_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocus_j3_size_option_mapping` (
  `mapping_id` int NOT NULL AUTO_INCREMENT,
  `option_id` int NOT NULL COMMENT 'OpenCart option_id',
  `gender` enum('women','men','kids','universal','unisex') NOT NULL DEFAULT 'unisex' COMMENT 'Gender category',
  `size_type` enum('shoes','apparel') NOT NULL COMMENT 'Type of sizing system',
  `source_system` enum('EU','US','UK','Asian','mm') NOT NULL COMMENT 'Original size system in option values',
  `enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Enable size selector for this option',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `option_id` (`option_id`),
  KEY `gender_type` (`gender`,`size_type`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3 COMMENT='Maps product options to size selector configuration';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ocus_j3_size_option_mapping`
--

LOCK TABLES `ocus_j3_size_option_mapping` WRITE;
/*!40000 ALTER TABLE `ocus_j3_size_option_mapping` DISABLE KEYS */;
INSERT INTO `ocus_j3_size_option_mapping` VALUES (1,11,'unisex','apparel','Asian',1,'2025-12-04 06:25:20','2025-12-04 06:25:20'),(2,22,'women','shoes','US',1,'2025-12-04 06:25:20','2025-12-07 03:36:18'),(3,23,'unisex','shoes','US',1,'2025-12-04 06:25:20','2025-12-07 03:38:04'),(4,26,'kids','shoes','US',1,'2025-12-04 06:25:20','2025-12-07 03:45:55');
/*!40000 ALTER TABLE `ocus_j3_size_option_mapping` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ocus_j3_size_selector_settings`
--

DROP TABLE IF EXISTS `ocus_j3_size_selector_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocus_j3_size_selector_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Size selector module settings';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ocus_j3_size_selector_settings`
--

LOCK TABLES `ocus_j3_size_selector_settings` WRITE;
/*!40000 ALTER TABLE `ocus_j3_size_selector_settings` DISABLE KEYS */;
INSERT INTO `ocus_j3_size_selector_settings` VALUES ('default_size_system','EU','2025-12-04 06:25:14'),('enable_size_guide','1','2025-12-04 06:25:14'),('mobile_optimized','1','2025-12-04 06:25:14'),('module_enabled','1','2025-12-04 06:25:14'),('show_stock_status','1','2025-12-04 06:25:14'),('size_button_style','grid','2025-12-04 06:25:14');
/*!40000 ALTER TABLE `ocus_j3_size_selector_settings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-07 14:34:20
