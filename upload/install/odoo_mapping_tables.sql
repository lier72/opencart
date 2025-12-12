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
-- Table structure for table `ocus_odoo_payment_acquirer_map`
--

DROP TABLE IF EXISTS `ocus_odoo_payment_acquirer_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocus_odoo_payment_acquirer_map` (
  `id` int NOT NULL AUTO_INCREMENT,
  `opencart_payment_code` varchar(64) NOT NULL COMMENT 'OpenCart payment method code',
  `opencart_payment_name` varchar(255) NOT NULL COMMENT 'OpenCart payment method name',
  `odoo_acquirer_id` int NOT NULL COMMENT 'Odoo payment acquirer ID',
  `odoo_acquirer_name` varchar(255) DEFAULT NULL COMMENT 'Odoo acquirer name for reference',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Is mapping active',
  `created_by` varchar(128) NOT NULL,
  `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_payment_code` (`opencart_payment_code`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COMMENT='Maps OpenCart payment methods to Odoo payment acquirers';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ocus_odoo_payment_acquirer_map`
--

LOCK TABLES `ocus_odoo_payment_acquirer_map` WRITE;
/*!40000 ALTER TABLE `ocus_odoo_payment_acquirer_map` DISABLE KEYS */;
INSERT INTO `ocus_odoo_payment_acquirer_map` VALUES (1,'alfabank','Alfabank Payment Gateway',24,'Alfabank',0,'system_init','2025-12-12 05:20:29','2025-12-12 06:01:06'),(2,'cod_cdek','CDEK Cash on Delivery',0,'Cash on Delivery',0,'system_init','2025-12-12 05:20:29','2025-12-12 05:20:29');
/*!40000 ALTER TABLE `ocus_odoo_payment_acquirer_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ocus_odoo_currency_map`
--

DROP TABLE IF EXISTS `ocus_odoo_currency_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocus_odoo_currency_map` (
  `id` int NOT NULL AUTO_INCREMENT,
  `opencart_currency_code` varchar(3) NOT NULL COMMENT 'OpenCart currency code (ISO 4217)',
  `opencart_currency_title` varchar(128) NOT NULL COMMENT 'OpenCart currency title',
  `odoo_currency_id` int NOT NULL COMMENT 'Odoo currency ID',
  `odoo_currency_name` varchar(64) DEFAULT NULL COMMENT 'Odoo currency name (for reference)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Is mapping active',
  `created_by` varchar(128) NOT NULL,
  `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_currency_code` (`opencart_currency_code`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COMMENT='Maps OpenCart currencies to Odoo currencies';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ocus_odoo_currency_map`
--

LOCK TABLES `ocus_odoo_currency_map` WRITE;
/*!40000 ALTER TABLE `ocus_odoo_currency_map` DISABLE KEYS */;
INSERT INTO `ocus_odoo_currency_map` VALUES (1,'RUB','Russian Ruble',31,'RUB',1,'system_init','2025-12-12 05:20:34','2025-12-12 05:41:46'),(2,'USD','US Dollar',3,'USD',1,'system_init','2025-12-12 05:20:34','2025-12-12 05:20:34'),(3,'EUR','Euro',1,'EUR',1,'system_init','2025-12-12 05:20:34','2025-12-12 05:40:45');
/*!40000 ALTER TABLE `ocus_odoo_currency_map` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-12  1:21:59
