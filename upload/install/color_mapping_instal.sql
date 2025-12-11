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
-- Table structure for table `ocus_color_mapping`
--

DROP TABLE IF EXISTS `ocus_color_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocus_color_mapping` (
  `color_id` int NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) NOT NULL,
  `color_name_ru` varchar(100) NOT NULL,
  `color_name_en` varchar(100) NOT NULL,
  `hex_code` varchar(7) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`color_id`),
  KEY `keyword` (`keyword`)
) ENGINE=MyISAM AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ocus_color_mapping`
--

LOCK TABLES `ocus_color_mapping` WRITE;
/*!40000 ALTER TABLE `ocus_color_mapping` DISABLE KEYS */;
INSERT INTO `ocus_color_mapping` VALUES (1,'черн','Черный','Black','#000000',1),(2,'бел','Белый','White','#FFFFFF',2),(3,'красн','Красный','Red','#FF0000',3),(4,'син','Синий','Blue','#0000FF',4),(5,'зелен','Зеленый','Green','#00FF00',5),(6,'желт','Желтый','Yellow','#FFFF00',6),(7,'оранж','Оранжевый','Orange','#FFA500',7),(8,'роз','Розовый','Pink','#FFC0CB',8),(9,'фиолет','Фиолетовый','Purple','#800080',9),(10,'коричнев','Коричневый','Brown','#A52A2A',10),(11,'сер','Серый','Gray','#808080',11),(12,'бежев','Бежевый','Beige','#F5F5DC',12),(13,'бордов','Бордовый','Burgundy','#800020',13),(14,'голуб','Голубой','Light Blue','#87CEEB',14),(15,'салатов','Салатовый','Lime','#00FF00',15),(16,'хаки','Хаки','Khaki','#F0E68C',16),(17,'navy','Темно-синий','Navy','#000080',17),(18,'maroon','Темно-красный','Maroon','#800000',18),(20,'сиренев','Сиреневый','Lilac','#C8A2C8',19),(21,'бирюзов','Бирюзовый','Turquoise','#40E0D0',20),(22,'серебрист','Серебристый','Silver','#C0C0C0',21),(23,'золот','Золотистый','Gold','#FFD700',22),(24,'цветн','Разноцветный','Multicolor','#FF00FF',23);
/*!40000 ALTER TABLE `ocus_color_mapping` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-11 17:06:47
