-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: kich_ban_sat_v3
-- ------------------------------------------------------
-- Server version	8.0.44

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
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user_action` (`user_id`,`action`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dm_assignments`
--

DROP TABLE IF EXISTS `dm_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dm_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `dm_user_id` bigint unsigned NOT NULL,
  `assigned_by` bigint unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'PENDING',
  `assigned_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `responded_at` datetime(3) DEFAULT NULL,
  `revoked_at` datetime(3) DEFAULT NULL,
  `reason` text,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dm_assignment_room_id` (`game_room_id`,`id`),
  KEY `idx_dm_assignments_dm_status` (`dm_user_id`,`status`),
  KEY `idx_dm_assignments_room_status` (`game_room_id`,`status`),
  KEY `fk_dm_assignment_assigner` (`assigned_by`),
  KEY `idx_dm_assign_user_status` (`dm_user_id`,`status`),
  CONSTRAINT `fk_dm_assignment_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_dm_assignment_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_dm_assignment_user` FOREIGN KEY (`dm_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_dm_assignment_status` CHECK ((`status` in (_utf8mb4'PENDING',_utf8mb4'ACCEPTED',_utf8mb4'REJECTED',_utf8mb4'REVOKED')))
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dm_assignments`
--

LOCK TABLES `dm_assignments` WRITE;
/*!40000 ALTER TABLE `dm_assignments` DISABLE KEYS */;
INSERT INTO `dm_assignments` VALUES (1,5,3,1,'ACCEPTED','2026-11-26 00:24:18.882','2026-11-26 00:24:18.000',NULL,NULL,'2026-11-26 00:24:18.882','2026-11-26 00:24:18.882'),(2,6,4,1,'ACCEPTED','2026-11-26 00:24:18.960','2026-11-26 00:24:18.000',NULL,NULL,'2026-11-26 00:24:18.960','2026-11-26 00:24:18.960'),(3,7,5,1,'PENDING','2026-11-26 00:24:19.042',NULL,NULL,NULL,'2026-11-26 00:24:19.042','2026-11-26 00:24:19.042'),(4,8,6,1,'PENDING','2026-11-26 00:24:19.111',NULL,NULL,NULL,'2026-11-26 00:24:19.111','2026-11-26 00:24:19.111'),(5,9,3,1,'ACCEPTED','2026-11-26 00:24:19.248','2026-11-25 19:00:00.000',NULL,NULL,'2026-11-26 00:24:19.248','2026-11-26 00:24:19.248'),(6,10,4,1,'ACCEPTED','2026-11-26 00:24:19.345','2026-11-23 20:00:00.000',NULL,NULL,'2026-11-26 00:24:19.345','2026-11-26 00:24:19.345'),(7,11,5,1,'ACCEPTED','2026-11-26 00:24:19.448','2026-11-21 14:00:00.000',NULL,NULL,'2026-11-26 00:24:19.448','2026-11-26 00:24:19.448');
/*!40000 ALTER TABLE `dm_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `game_configurations`
--

DROP TABLE IF EXISTS `game_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `game_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint unsigned NOT NULL,
  `version` int unsigned NOT NULL,
  `configuration` json NOT NULL,
  `description` text,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `created_by` bigint unsigned NOT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_game_configuration_version` (`game_id`,`version`),
  UNIQUE KEY `uq_game_configuration_game_id_id` (`game_id`,`id`),
  KEY `idx_game_configuration_active` (`game_id`,`is_active`),
  KEY `fk_game_configuration_creator` (`created_by`),
  CONSTRAINT `fk_game_configuration_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_game_configuration_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_game_configuration_active` CHECK ((`is_active` in (0,1)))
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `game_configurations`
--

LOCK TABLES `game_configurations` WRITE;
/*!40000 ALTER TABLE `game_configurations` DISABLE KEYS */;
INSERT INTO `game_configurations` VALUES (1,1,1,'{\"male\": 4, \"female\": 3}','Bản chuẩn (7-7 người)',1,1,'2026-11-26 00:24:17.941'),(2,2,1,'{\"male\": 4, \"female\": 4}','Bản chuẩn (8-8 người)',1,1,'2026-11-26 00:24:17.961'),(3,3,1,'{\"male\": 4, \"female\": 3}','Bản chuẩn (7-7 người)',1,1,'2026-11-26 00:24:17.979'),(4,4,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:17.998'),(5,5,1,'{\"male\": 4, \"female\": 4}','Bản chuẩn (8-8 người)',1,1,'2026-11-26 00:24:18.020'),(6,6,1,'{\"male\": 3, \"female\": 2}','Bản chuẩn (5-5 người)',1,1,'2026-11-26 00:24:18.039'),(7,7,1,'{\"male\": 4, \"female\": 3}','Bản chuẩn (7-7 người)',1,1,'2026-11-26 00:24:18.056'),(8,8,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.073'),(9,9,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.091'),(10,10,1,'{\"male\": 3, \"female\": 2}','Bản chuẩn (5-5 người)',1,1,'2026-11-26 00:24:18.107'),(11,11,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.127'),(12,12,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.149'),(13,13,1,'{\"male\": 3, \"female\": 4}','Bản chuẩn (7-7 người)',1,1,'2026-11-26 00:24:18.166'),(14,14,1,'{\"male\": 2, \"female\": 4}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.185'),(15,15,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.201'),(16,16,1,'{\"male\": 4, \"female\": 3}','Bản chuẩn (7-7 người)',1,1,'2026-11-26 00:24:18.221'),(17,17,1,'{\"male\": 4, \"female\": 3}','Bản chuẩn (7-7 người)',1,1,'2026-11-26 00:24:18.238'),(18,18,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.257'),(19,19,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.274'),(20,20,1,'{\"male\": 4, \"female\": 3}','Bản chuẩn (6-7 người)',1,1,'2026-11-26 00:24:18.290'),(21,21,1,'{\"male\": 5, \"female\": 5}','Bản chuẩn (7-10 người)',1,1,'2026-11-26 00:24:18.306'),(22,22,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.324'),(23,23,1,'{\"male\": 4, \"female\": 3}','Bản chuẩn (7-7 người)',1,1,'2026-11-26 00:24:18.343'),(24,24,1,'{\"male\": 4, \"female\": 4}','Bản chuẩn (6-8 người)',1,1,'2026-11-26 00:24:18.360'),(25,25,1,'{\"male\": 2, \"female\": 3}','Bản chuẩn (5-5 người)',1,1,'2026-11-26 00:24:18.377'),(26,26,1,'{\"male\": 3, \"female\": 2}','Bản chuẩn (5-5 người)',1,1,'2026-11-26 00:24:18.396'),(27,27,1,'{\"male\": 3, \"female\": 2}','Bản chuẩn (5-5 người)',1,1,'2026-11-26 00:24:18.414'),(28,28,1,'{\"male\": 3, \"female\": 2}','Bản chuẩn (5-5 người)',1,1,'2026-11-26 00:24:18.430'),(29,29,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.448'),(30,30,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.467'),(31,31,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.486'),(32,32,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.504'),(33,33,1,'{\"male\": 3, \"female\": 2}','Bản chuẩn (5-5 người)',1,1,'2026-11-26 00:24:18.521'),(34,34,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.538'),(35,35,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.556'),(36,36,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.574'),(37,37,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.594'),(38,38,1,'{\"male\": 2, \"female\": 2}','Bản chuẩn (4-4 người)',1,1,'2026-11-26 00:24:18.612'),(39,39,1,'{\"male\": 3, \"female\": 2}','Bản chuẩn (5-5 người)',1,1,'2026-11-26 00:24:18.631'),(40,40,1,'{\"male\": 3, \"female\": 2}','Bản chuẩn (5-5 người)',1,1,'2026-11-26 00:24:18.653'),(41,41,1,'{\"male\": 3, \"female\": 3}','Bản chuẩn (6-6 người)',1,1,'2026-11-26 00:24:18.675'),(42,42,1,'{\"male\": 3, \"female\": 2}','Bản chuẩn (5-5 người)',1,1,'2026-11-26 00:24:18.693');
/*!40000 ALTER TABLE `game_configurations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `game_prices`
--

DROP TABLE IF EXISTS `game_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `game_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint unsigned NOT NULL,
  `price_per_person` bigint unsigned NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_game_prices_lookup` (`game_id`,`status`,`effective_from`,`effective_until`),
  CONSTRAINT `fk_game_prices_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_game_prices_amount` CHECK ((`price_per_person` > 0)),
  CONSTRAINT `chk_game_prices_period` CHECK (((`effective_until` is null) or (`effective_until` >= `effective_from`))),
  CONSTRAINT `chk_game_prices_status` CHECK ((`status` in (_utf8mb4'ACTIVE',_utf8mb4'INACTIVE'))),
  CONSTRAINT `chk_game_prices_time` CHECK ((`start_time` < `end_time`))
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `game_prices`
--

LOCK TABLES `game_prices` WRITE;
/*!40000 ALTER TABLE `game_prices` DISABLE KEYS */;
INSERT INTO `game_prices` VALUES (1,1,199000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:17.946','2026-11-26 00:24:17.946'),(2,1,239000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:17.951','2026-11-26 00:24:17.951'),(3,2,249000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:17.966','2026-11-26 00:24:17.966'),(4,2,289000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:17.970','2026-11-26 00:24:17.970'),(5,3,269000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:17.983','2026-11-26 00:24:17.983'),(6,3,309000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:17.987','2026-11-26 00:24:17.987'),(7,4,369000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.002','2026-11-26 00:24:18.002'),(8,4,409000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.010','2026-11-26 00:24:18.010'),(9,5,239000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.025','2026-11-26 00:24:18.025'),(10,5,279000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.030','2026-11-26 00:24:18.030'),(11,6,289000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.043','2026-11-26 00:24:18.043'),(12,6,329000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.047','2026-11-26 00:24:18.047'),(13,7,199000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.060','2026-11-26 00:24:18.060'),(14,7,239000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.064','2026-11-26 00:24:18.064'),(15,8,189000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.078','2026-11-26 00:24:18.078'),(16,8,229000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.082','2026-11-26 00:24:18.082'),(17,9,269000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.095','2026-11-26 00:24:18.095'),(18,9,309000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.099','2026-11-26 00:24:18.099'),(19,10,199000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.111','2026-11-26 00:24:18.111'),(20,10,239000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.118','2026-11-26 00:24:18.118'),(21,11,189000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.132','2026-11-26 00:24:18.132'),(22,11,229000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.136','2026-11-26 00:24:18.136'),(23,12,269000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.153','2026-11-26 00:24:18.153'),(24,12,309000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.158','2026-11-26 00:24:18.158'),(25,13,299000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.171','2026-11-26 00:24:18.171'),(26,13,339000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.175','2026-11-26 00:24:18.175'),(27,14,229000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.189','2026-11-26 00:24:18.189'),(28,14,269000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.193','2026-11-26 00:24:18.193'),(29,15,269000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.206','2026-11-26 00:24:18.206'),(30,15,309000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.210','2026-11-26 00:24:18.210'),(31,16,229000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.226','2026-11-26 00:24:18.226'),(32,16,269000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.230','2026-11-26 00:24:18.230'),(33,17,239000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.242','2026-11-26 00:24:18.242'),(34,17,279000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.246','2026-11-26 00:24:18.246'),(35,18,269000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.261','2026-11-26 00:24:18.261'),(36,18,309000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.265','2026-11-26 00:24:18.265'),(37,19,169000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.278','2026-11-26 00:24:18.278'),(38,19,209000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.282','2026-11-26 00:24:18.282'),(39,20,199000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.294','2026-11-26 00:24:18.294'),(40,20,239000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.298','2026-11-26 00:24:18.298'),(41,21,199000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.311','2026-11-26 00:24:18.311'),(42,21,239000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.315','2026-11-26 00:24:18.315'),(43,22,199000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.330','2026-11-26 00:24:18.330'),(44,22,239000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.334','2026-11-26 00:24:18.334'),(45,23,269000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.347','2026-11-26 00:24:18.347'),(46,23,309000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.351','2026-11-26 00:24:18.351'),(47,24,219000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.364','2026-11-26 00:24:18.364'),(48,24,259000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.368','2026-11-26 00:24:18.368'),(49,25,149000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.382','2026-11-26 00:24:18.382'),(50,25,189000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.387','2026-11-26 00:24:18.387'),(51,26,219000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.401','2026-11-26 00:24:18.401'),(52,26,259000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.405','2026-11-26 00:24:18.405'),(53,27,199000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.418','2026-11-26 00:24:18.418'),(54,27,239000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.422','2026-11-26 00:24:18.422'),(55,28,229000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.435','2026-11-26 00:24:18.435'),(56,28,269000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.440','2026-11-26 00:24:18.440'),(57,29,249000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.452','2026-11-26 00:24:18.452'),(58,29,289000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.457','2026-11-26 00:24:18.457'),(59,30,249000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.471','2026-11-26 00:24:18.471'),(60,30,289000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.475','2026-11-26 00:24:18.475'),(61,31,199000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.491','2026-11-26 00:24:18.491'),(62,31,239000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.495','2026-11-26 00:24:18.495'),(63,32,259000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.508','2026-11-26 00:24:18.508'),(64,32,299000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.512','2026-11-26 00:24:18.512'),(65,33,299000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.525','2026-11-26 00:24:18.525'),(66,33,339000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.529','2026-11-26 00:24:18.529'),(67,34,269000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.542','2026-11-26 00:24:18.542'),(68,34,309000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.547','2026-11-26 00:24:18.547'),(69,35,349000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.560','2026-11-26 00:24:18.560'),(70,35,389000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.564','2026-11-26 00:24:18.564'),(71,36,279000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.580','2026-11-26 00:24:18.580'),(72,36,319000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.584','2026-11-26 00:24:18.584'),(73,37,159000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.598','2026-11-26 00:24:18.598'),(74,37,199000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.602','2026-11-26 00:24:18.602'),(75,38,179000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.616','2026-11-26 00:24:18.616'),(76,38,219000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.621','2026-11-26 00:24:18.621'),(77,39,179000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.635','2026-11-26 00:24:18.635'),(78,39,219000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.641','2026-11-26 00:24:18.641'),(79,40,189000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.660','2026-11-26 00:24:18.660'),(80,40,229000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.664','2026-11-26 00:24:18.664'),(81,41,249000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.679','2026-11-26 00:24:18.679'),(82,41,289000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.684','2026-11-26 00:24:18.684'),(83,42,219000,'09:00:00','19:59:59','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.697','2026-11-26 00:24:18.697'),(84,42,259000,'20:00:00','23:00:00','2026-01-01','2035-12-31','ACTIVE','2026-11-26 00:24:18.703','2026-11-26 00:24:18.703');
/*!40000 ALTER TABLE `game_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `game_rooms`
--

DROP TABLE IF EXISTS `game_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `game_rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint unsigned NOT NULL,
  `game_configuration_id` bigint unsigned NOT NULL,
  `creator_id` bigint unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `min_players` smallint unsigned NOT NULL,
  `max_players` smallint unsigned NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'FORMING',
  `formation_deadline_at` datetime(3) NOT NULL,
  `cancel_reason` text,
  `cancelled_at` datetime(3) DEFAULT NULL,
  `replacement_opened_by` bigint unsigned DEFAULT NULL,
  `replacement_reason` text,
  `replacement_started_at` datetime(3) DEFAULT NULL,
  `replacement_deadline_at` datetime(3) DEFAULT NULL,
  `replacement_status` varchar(20) DEFAULT NULL,
  `replacement_closed_at` datetime(3) DEFAULT NULL,
  `price_total_participants` smallint unsigned DEFAULT NULL,
  `price_subtotal` bigint unsigned DEFAULT NULL,
  `price_discount_amount` bigint unsigned NOT NULL DEFAULT '0',
  `price_final_amount` bigint unsigned DEFAULT NULL,
  `price_deposit_total` bigint unsigned DEFAULT NULL,
  `price_remaining_total` bigint unsigned DEFAULT NULL,
  `price_promotion_id` bigint unsigned DEFAULT NULL,
  `price_details` json DEFAULT NULL,
  `price_calculated_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_game_room_game_id_id` (`game_id`,`id`),
  KEY `idx_game_rooms_status_created` (`status`,`created_at`),
  KEY `idx_game_rooms_game_status` (`game_id`,`status`),
  KEY `idx_game_rooms_creator_status` (`creator_id`,`status`),
  KEY `idx_game_rooms_replacement_deadline` (`replacement_status`,`replacement_deadline_at`),
  KEY `idx_game_rooms_price_promotion` (`price_promotion_id`),
  KEY `fk_game_rooms_configuration` (`game_id`,`game_configuration_id`),
  KEY `fk_game_rooms_replacement_opener` (`replacement_opened_by`),
  KEY `idx_rooms_status_created` (`status`,`created_at`),
  CONSTRAINT `fk_game_rooms_configuration` FOREIGN KEY (`game_id`, `game_configuration_id`) REFERENCES `game_configurations` (`game_id`, `id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_game_rooms_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_game_rooms_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_game_rooms_price_promotion` FOREIGN KEY (`price_promotion_id`) REFERENCES `promotions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_game_rooms_replacement_opener` FOREIGN KEY (`replacement_opened_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_game_rooms_player_range` CHECK (((`min_players` > 0) and (`max_players` >= `min_players`))),
  CONSTRAINT `chk_game_rooms_price` CHECK (((`price_subtotal` is null) or ((`price_discount_amount` <= `price_subtotal`) and (`price_final_amount` is not null) and (`price_deposit_total` is not null) and (`price_remaining_total` is not null) and (`price_details` is not null) and (`price_total_participants` is not null) and (`price_final_amount` = (`price_subtotal` - `price_discount_amount`)) and ((`price_deposit_total` + `price_remaining_total`) = `price_final_amount`) and (`price_total_participants` > 0)))),
  CONSTRAINT `chk_game_rooms_replacement_status` CHECK (((`replacement_status` is null) or (`replacement_status` in (_utf8mb4'OPEN',_utf8mb4'CLOSED',_utf8mb4'EXPIRED')))),
  CONSTRAINT `chk_game_rooms_replacement_time` CHECK (((`replacement_started_at` is null) or (`replacement_deadline_at` is null) or (`replacement_deadline_at` > `replacement_started_at`)))
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `game_rooms`
--

LOCK TABLES `game_rooms` WRITE;
/*!40000 ALTER TABLE `game_rooms` DISABLE KEYS */;
INSERT INTO `game_rooms` VALUES (1,1,1,7,'Ghép nhóm phá án 2/12 tối Thứ 6','Tuyển thêm bạn cùng gu trinh thám kinh dị, không toxic!',7,7,'FORMING','2026-11-26 04:24:18.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.000'),(2,2,2,10,'Đội Điểm Danh cuối tuần — Bình Thạnh','Cần thêm 3 thám tử chơi ca chiều Thứ 7.',6,6,'FORMING','2026-11-26 04:24:18.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.000'),(3,3,3,13,'Phá án Yandere — Kèo vui vẻ','Ưu tiên bạn đã từng chơi Jubensha, cọc 50k giữ ghế.',6,8,'FORMING','2026-11-26 04:24:18.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.000'),(4,5,5,15,'Hội Suy Luận — Kịch Bản Cổ Trang','Cần thêm 3 bạn thích thể loại cổ trang tâm lý.',6,7,'FORMING','2026-11-26 04:24:18.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.000'),(5,6,6,19,'Ca tối Thứ 7 — Martinez Kinh Kỳ Quán','Phòng đã đủ người và chốt lịch chính thức.',6,6,'SCHEDULED','2026-11-27 19:00:00.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.000'),(6,7,7,25,'Ca chiều Chủ Nhật — Biệt Thự Bí Ẩn','Phòng đã đủ người và chốt lịch chính thức.',6,6,'SCHEDULED','2026-11-28 14:00:00.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.000'),(7,8,8,31,'Ca tối Thứ 2 — Án Mạng Nửa Đêm','Phòng đã đủ người và chốt lịch chính thức.',6,6,'SCHEDULED','2026-11-29 20:00:00.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.000'),(8,9,9,37,'Ca chiều Thứ 4 — Mật Thất Cổ Điển','Phòng đã đủ người và chốt lịch chính thức.',6,6,'SCHEDULED','2026-11-30 15:00:00.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.000'),(9,10,10,43,'Ca hoàn thành #01 — Phá án thành công','Buổi chơi đã hoàn thành.',6,6,'COMPLETED','2026-11-25 19:00:00.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-25 19:00:00.000','2026-11-25 19:00:00.000'),(10,11,11,49,'Ca hoàn thành #02 — Tối Thứ 5','Buổi chơi đã hoàn thành.',6,6,'COMPLETED','2026-11-23 20:00:00.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-23 20:00:00.000','2026-11-23 20:00:00.000'),(11,12,12,7,'Ca hoàn thành #03 — Chiều Chủ Nhật','Buổi chơi đã hoàn thành.',6,6,'COMPLETED','2026-11-21 14:00:00.000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-21 14:00:00.000','2026-11-21 14:00:00.000');
/*!40000 ALTER TABLE `game_rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `games`
--

DROP TABLE IF EXISTS `games`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `games` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `description` text,
  `poster_url` varchar(255) DEFAULT NULL,
  `difficulty` varchar(50) DEFAULT NULL,
  `synopsis` text,
  `image_url` varchar(500) DEFAULT NULL,
  `min_players` smallint unsigned NOT NULL,
  `max_players` smallint unsigned NOT NULL,
  `duration_minutes` smallint unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `display_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_games_slug` (`slug`),
  CONSTRAINT `chk_games_player_range` CHECK (((`min_players` > 0) and (`max_players` >= `min_players`))),
  CONSTRAINT `chk_games_status` CHECK ((`status` in (_utf8mb4'ACTIVE',_utf8mb4'INACTIVE',_utf8mb4'DRAFT')))
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `games`
--

LOCK TABLES `games` WRITE;
/*!40000 ALTER TABLE `games` DISABLE KEYS */;
INSERT INTO `games` VALUES (1,'2/12','kbs-212','Hàng loạt sự kiện kỳ lạ xảy ra với học sinh trường Chân Lý: mất tích bí ẩn, ác mộng ám ảnh và cảm giác luôn bị rình rập.','./src/assets/images/2-12.png','★★★★ (4 sao)','Hàng loạt sự kiện kỳ lạ và đáng sợ xảy ra với học sinh: một số mất tích bí ẩn, một số khác bị ác mộng ám ảnh quay quắt, và có người thậm chí cảm thấy như đang bị ai đó rình rập, truy đuổi. Ngôi trường vốn dĩ bình yên, bỗng chốc trở thành hiện trường của một sự kiện kinh hoàng khiến ai nấy không khỏi hoài nghi về những gì thực sự đang diễn ra. Liệu có phải lời nguyền nào đó đang được khơi dậy, hay một bí mật đen tối nào đó từ lâu đã được giấu kín trong những bức tường của trường Chân Lý?','./src/assets/images/2-12.png',7,7,270,'ACTIVE',1,'2026-11-26 00:24:17.936','2026-11-26 00:24:17.936'),(2,'Điểm Danh','kbs-diemdanh','Tại Học viện Tư thục Inada, con búp bê Geisha khoác kimono đỏ thẫm trên nóc tủ cất lên lời nguyền rợn gáy: \"Tất cả các ngươi… đều phải chết.\"','./src/assets/images/diem-danh.png','★★★★ (4 sao)','Tại Học viện Tư thục Inada tồn tại một Câu lạc bộ Nghiên cứu Hiện tượng Siêu nhiên, nơi những kẻ ưa khám phá bóng tối tụ họp để điều tra các hiện tượng bí ẩn, đồng thời đối mặt với những sự kiện rợn người mà khoa học đành bất lực.\nTrong căn phòng sinh hoạt vắng lặng của câu lạc bộ, bỗng dưng một luồng gió lạnh buốt nổi lên, dẫu cho tất cả cửa sổ và cửa ra vào đều đóng chặt. Bàn ghế bắt đầu tự động cọt kẹt xê dịch, cửa kính lạch cạch khép mở như có bàn tay vô hình nào đó đang thao túng. Giữa sự im lặng ngột ngạt, một tràng cười chói tai, the thé, vang vọng khắp không trung khiến sống lưng lạnh buốt.\nLần theo âm thanh quái đản ấy, mọi ánh mắt đổ dồn về phía nóc tủ. Ở đó, một con búp bê Geisha đang ngồi chễm chệ. Nó khoác lên mình bộ kimono đỏ thẫm như thấm đẫm máu, từng đường nét tinh xảo. Đôi mắt đen sâu hoắm của nó lấp lánh như thể trong đó giam giữ linh hồn một con người đầy oán hận.\nRồi, trong khoảng không nặng nề, nó khẽ cúi đầu, mở miệng thốt ra lời nguyền rợn gáy:\n“Tất cả các ngươi… đều phải chết.”','./src/assets/images/diem-danh.png',8,8,300,'ACTIVE',2,'2026-11-26 00:24:17.955','2026-11-26 00:24:17.955'),(3,'Tần Số Cuối','kbs-tsc','Bảy sinh viên đam mê Livestream hiện tượng siêu nhiên tổ chức buổi gọi hồn vào đêm không trăng tại khu nhà hoang Văn Thành.','./src/assets/images/tan-so-cuoi.png','★★★★ (4 sao)','Bảy sinh viên đam mê Livestream các hiện tượng siêu nhiên. Trong một buổi tối không trăng, họ quyết tâm tổ chức một buổi livestream gọi hồn ở khu nhà hoang Văn Thành, nổi tiếng với những câu chuyện rùng rợn, với camera ghi hình từng góc tối, buổi gọi hồn bắt đầu nhưng không lâu sau, những sự kiện kỳ bí và đáng sợ bắt đầu xảy ra. Tiếng động lạ, bóng người hiện hữu nơi góc khuất, và những hiện tượng không thể giải thích được, đe dọa sự an toàn của họ. Trong khi cố gắng khám phá sự thật đằng sau những bí ẩn của khu nhà hoang, liệu họ có phát hiện ra điều gì vượt quá khả năng chịu đựng của con người không?\nTham gia “Tần Số Cuối” để khám phá liệu những thế lực siêu nhiên có thật sự tồn tại, hay chỉ là những trò chơi tâm lý của bóng tối.','./src/assets/images/tan-so-cuoi.png',7,7,240,'ACTIVE',3,'2026-11-26 00:24:17.974','2026-11-26 00:24:17.974'),(4,'Vòng Lặp Mưu Sát Đảo Mèo','kbs-daomeo','Sáu cuốn nhật ký được niêm phong trên Đảo Mèo mở ra chuỗi án mạng lặp lại đầy ám ảnh và động cơ tội ác méo mó của kẻ sát nhân.','./src/assets/images/vong-lap-muu-sat-dao-meo.png','★★★★★★ (6 sao)','Cậu thiếu niên cô độc Momoyama Yuu đã rời khỏi đảo rồi tự sát một cách đầy bí ẩn. Để truy tìm chân tướng năm ấy, một giáo sư tâm lý học đã niêm phong sáu cuốn nhật ký ghi lại toàn bộ sự việc, đồng thời mời sáu vị khách tưởng chừng không hề liên quan đến nhau tham dự bữa tiệc suy luận này.\nNgười bị biến thành nhân trệ chết đuối trong vại máu; Những phần thi thể tàn khuyết trên giá sắt; Chiếc đầu bị chặt rời xuất hiện đầy bí ẩn giữa cơn mưa lớn; Mảng da đầu mờ nhòe dưới chân vách núi; Bộ xương đã bị than hóa trong lò luyện; Bóng người biến mất khỏi căn phòng kín.\nLời nguyền chưa từng được hóa giải chính là khởi nguồn của vòng lặp mưu sát. Tên hung thủ điên loạn đến mức bệnh hoạn đang tận hưởng động cơ phạm tội méo mó và biến thái của mình.\nCó lẽ, chỉ những người đủ thấu hiểu sự biến thái mới có thể nhìn thấu chân tướng tuyệt vọng được che giấu trên Đảo Mèo. Hoặc cũng có thể, chỉ những kẻ thực sự biến thái mới có thể lý giải được chân tướng của sự biến thái.\nVậy thì, hỡi những vị khách đã vượt đường xa tới đây để tham gia cuộc suy luận này, rốt cuộc các vị thuộc về kiểu người nào đây?','./src/assets/images/vong-lap-muu-sat-dao-meo.png',6,6,420,'ACTIVE',4,'2026-11-26 00:24:17.993','2026-11-26 00:24:17.993'),(5,'Bệnh Viện Phụ Sản Ái Ấu','kbs-bvpsaa','Đêm rằm tháng Bảy năm 2019, trước cổng Bệnh viện Sản Phụ Ái Ấu xây trên nền ngôi chùa cũ của thôn Ngọc Hồ, một nhóm người đang lặng lẽ tụ tập.','./src/assets/images/benh-vien-phu-san-ai-au.png','★★★ (3 sao)','Ở thôn Ngọc Hồ yên tĩnh và thanh bình, bốn phía được bao quanh bởi núi non trùng điệp. Thế nhưng một trận lũ dữ kéo dài suốt năm ngày năm đêm đã nhấn chìm toàn bộ ngôi làng, không để lại một người sống sót.\nSau thảm họa, theo lời một vị cao nhân, người ta dựng lên một ngôi chùa trên nền đất cũ để siêu độ vong linh dân làng. Nhiều năm sau, ngôi chùa bị phá bỏ và thay thế bởi Bệnh viện Sản Phụ Ái Ấu.\nMọi chuyện vốn chẳng có gì bất thường cho đến ngày ông chủ bệnh viện đột ngột nhảy lầu tự sát. Kể từ đó, những lời đồn đáng sợ bắt đầu xuất hiện. Có người nói ngôi chùa năm xưa được xây để phong ấn oan hồn dân làng Ngọc Hồ, và khi nó bị phá hủy, những linh hồn ấy đã được giải thoát. Có người lại nói những đứa trẻ chết yểu tại bệnh viện chưa từng rời đi mà vẫn quanh quẩn bên cạnh mẹ mình. Thậm chí còn có lời đồn rằng bệnh viện được xây dựng nhằm lợi dụng sinh khí của trẻ sơ sinh để hóa giải oán khí tích tụ dưới lòng đất.\nThế nhưng, chẳng ai biết đâu mới là sự thật.\nChỉ biết rằng hôm nay là đêm rằm tháng Bảy năm 2019, và trước cổng Bệnh viện Sản Phụ Ái Ấu, một nhóm người đang lặng lẽ tụ tập...','./src/assets/images/benh-vien-phu-san-ai-au.png',8,8,300,'ACTIVE',5,'2026-11-26 00:24:18.015','2026-11-26 00:24:18.015'),(6,'Cậu Bé Váy Đỏ','kbs-cbvd','Năm người xa lạ sau khi xem đoạn video từ phần mềm bí ẩn \"Cậu Bé Váy Đỏ\" đều nhận được lời mời đến một căn nhà ma ám bỏ hoang.','./src/assets/images/cau-be-vay-do.png','★★★★ (4 sao)','\"Cậu Bé Váy Đỏ Trùng Khánh\", Đại học Nam Kinh, Canada, Bạch Ngân... Những cái tên từng khiến cả xã hội rúng động. Có vụ đã khép lại, có vụ vẫn còn vô vàn tranh cãi.\nTheo thời gian, chân tướng dần bị chôn vùi, chỉ còn lại những lời đồn chưa bao giờ biến mất.\nLiệu ma quỷ có thật? Hay chính con người mới là thứ đáng sợ nhất?\nGần đây, trên mạng lan truyền một phần mềm bí ẩn mang tên \"Cậu Bé Váy Đỏ\". Không ai biết nó xuất hiện từ khi nào. Cũng không ai biết, sau khi xem đoạn video bên trong, vì sao tất cả đều nhận được cùng một lời mời đến một căn nhà ma ám đã bị bỏ hoang từ rất lâu.\nNăm người xa lạ. Năm mục đích khác nhau. Một căn nhà không nên bước vào.\nĐêm xuống, gió lạnh xuyên qua từng khe cửa. Tiếng bước chân vang lên giữa hành lang tối. Bóng người lướt qua trong ánh đèn leo lét. Rồi một thi thể lặng lẽ xuất hiện dưới xà nhà.\nĐó là lời nguyền. Là ma quỷ. Hay chỉ là màn mở đầu của một kế hoạch đã được sắp đặt từ rất lâu?\nKhi cánh cửa căn nhà khép lại, mỗi người đều có thể là nạn nhân... Hoặc cũng có thể là kẻ đang che giấu sự thật. Liệu họ có tìm được chân tướng trước khi trở thành cái tên tiếp theo trong Thập Đại Kỳ Án?','./src/assets/images/cau-be-vay-do.png',5,5,300,'ACTIVE',6,'2026-11-26 00:24:18.034','2026-11-26 00:24:18.034'),(7,'Yandere 1 (Tâm Thần Phân Liệt)','kbs-yandere1','Một cậu bé mang 7 nhân cách khác biệt đối mặt với cái chết bí ẩn của cả gia đình — chỉ 7 cuốn nhật ký mới vén màn sự thật.','./src/assets/images/yandere-1.png','★★★★ (4 sao)','Một cậu bé mang trong mình 7 nhân cách khác biệt đang phải đối mặt với bi kịch khủng khiếp của gia đình. Bố mẹ và em trai của cậu đã chết một cách bí ẩn, để lại đằng sau những dấu vết mơ hồ và những bí mật đen tối. Chỉ có 7 cuốn nhật ký của cậu bé mới vén màn sự thật kinh hoàng ẩn giấu phía sau. Liệu các thám tử tài ba có đủ khả năng giải mã từng mảnh ghép ký ức hỗn loạn của cậu bé để tìm ra kẻ thủ ác? Hãy bước vào thế giới đầy rẫy sự lừa dối và hoang tưởng, nơi mỗi bước đi đều có thể dẫn đến một sự thật rợn người mà bạn chưa từng tưởng tượng.','./src/assets/images/yandere-1.png',7,7,270,'ACTIVE',7,'2026-11-26 00:24:18.051','2026-11-26 00:24:18.051'),(8,'Yandere 2 (Tình Yêu)','kbs-yandere2','Phần tiếp nối của Nhật Ký (1) Yandere: nếu cậu bé tâm thần phân liệt năm đó tự sát không thành và được cứu sống kịp thời...','./src/assets/images/yandere-2.png','★★★★ (4 sao)','Khép lại Nhật Ký (1) Yandere - Tâm Thần Phân Liệt là sự việc cậu bé tâm thần phân liệt đáng thương tự sát. Vậy nếu cậu ấy năm đó tự sát không thành công, tôi nói là nếu cậu ấy đã được cứu sống kịp thời... Thì mọi người biết câu chuyện tiếp theo sẽ như thế nào không?\nCùng khám phá ngay câu chuyện được viết tiếp như thế nào với phần 2: NHẬT KÝ YANDERE 2 - TÌNH YÊU.','./src/assets/images/yandere-2.png',6,6,210,'ACTIVE',8,'2026-11-26 00:24:18.069','2026-11-26 00:24:18.069'),(9,'Tuyết Hương Liên Hoàn Án','kbs-thlha','Cải biên dựa trên vụ án có thật \"Vụ án 218\" tại vùng Đông Bắc Trung Quốc những năm 90 với hai màn đưa bạn đến hiện trường thực sự.','./src/assets/images/tuyet-huong-lien-hoan-an.png','★★★ (3 sao)','Câu chuyện này được cải biên dựa trên vụ án có thật “Vụ án 218” tại khu vực Đông Bắc Trung Quốc vào những năm 90.\nTác giả đã quay lại hiện trường vụ án để thu thập tài liệu và biến nó thành một trò chơi Kịch Bản Sát với hai màn, dẫn dắt bạn đến với hiện trường vụ án thực sự.\nChúc bạn có một hành trình đón giao thừa đầy khác biệt.\nVà bây giờ, trò chơi chính thức bắt đầu...','./src/assets/images/tuyet-huong-lien-hoan-an.png',6,6,240,'ACTIVE',9,'2026-11-26 00:24:18.086','2026-11-26 00:24:18.086'),(10,'Lên Đường','kbs-lenduong','Một gia đình năm người trên chiếc SUV lướt qua xa lộ dưới bầu trời xám xịt — họ đang đi đâu, và quan trọng hơn, họ đang trốn chạy điều gì?','./src/assets/images/len-duong.png','★★★★ (4 sao)','Trong khung cảnh của một bầu trời xám xịt, một gia đình năm người ngồi trong chiếc xe SUV đang lướt qua những con đường quen thuộc trên xa lộ. Tuy nhiên, không khí trong xe không mang hơi thở của chuyến đi chủ nhật bình thường. Thay vào đó, là không gian bao trùm bởi sự căng thẳng, với ánh mắt lo lắng của người cha cầm lái và vẻ mặt bồn chồn của người mẹ bên cạnh. Phía sau, ba đứa trẻ thì thầm với nhau, sự phân vân và lo lắng lộ rõ trên từng gương mặt nhỏ bé.\nChuyến đi lần này khác biệt, nặng nề với những bí mật chưa được tiết lộ. Câu hỏi được đặt ra không chỉ là họ đang đi đâu, mà quan trọng hơn, họ đang trốn chạy điều gì?','./src/assets/images/len-duong.png',5,5,210,'ACTIVE',10,'2026-11-26 00:24:18.103','2026-11-26 00:24:18.103'),(11,'1 Giờ 30','kbs-1g30','Vào đêm không trăng mỗi tháng một lần, hội kể truyện quái dị đô thị tụ họp — một khi đã ngồi vào vòng tròn, không ai được phép rời đi.','./src/assets/images/1-gio-30.png','★★★ (3 sao)','Chào mừng bạn đến với “1 GIỜ 30”, buổi tụ họp bí ẩn của hội kể truyện quái dị đô thị. Vào đêm không trăng, mỗi tháng một lần, những người từ mọi nẻo đường, dù tin vào thần thánh hay không, đều tập hợp và chia sẻ những câu chuyện kỳ bí nhất mà họ đã gặp phải, nhằm xua tan cô đơn và sợ hãi. Nhưng một khi đã ngồi vào vòng tròn của hội, không ai được phép rời đi cho tới khi buổi tụ họp chấm dứt. Dù xảy ra chuyện gì, hãy chuẩn bị sẵn sàng để đối mặt với bóng tối, và khám phá ra, liệu bạn có đủ dũng cảm để tồn tại qua đêm không?','./src/assets/images/1-gio-30.png',6,6,210,'ACTIVE',11,'2026-11-26 00:24:18.122','2026-11-26 00:24:18.122'),(12,'Giày Thêu Hoa','kbs-gth','Bắc Kinh năm 1985, tại một tứ hợp viện cũ kỹ ngoại ô phía Đông Nam, tiếng gõ cửa dồn dập lúc 9 giờ đêm mở màn cho vụ án mạng tàn bạo.','./src/assets/images/giay-theu-hoa.png','★★★ (3 sao)','Bắc Kinh, 1985, giữa thời đại đầy rẫy mê tín phong kiến và tư tưởng “trọng nam khinh nữ”, ranh giới giữa lý trí và cuồng loạn trở nên mong manh. Ở vùng ngoại ô phía Đông Nam thành phố, một tứ hợp viện cũ kỹ lặng lẽ ẩn mình dưới lớp bụi thời gian. Một tấm giấy thông báo cho thuê, viết vội bằng nét chữ nguệch ngoạc, đã kéo theo những thanh niên tỉnh lẻ đổ về Bắc Kinh với giấc mộng đổi đời. Nhưng rồi, một vụ án mạng kinh hoàng xảy ra, cách thức giết người tàn bạo đến mức khiến ai nấy rùng mình. Đằng sau sự bình yên giả tạo của tứ hợp viện, một bí ẩn đen tối đang dần lộ diện, một thứ tội ác khoác lên mình bộ mặt từ bi nhưng mang trái tim rắn độc. Đêm đó, kim đồng hồ vừa điểm chín giờ.\n“Thình! Thình! Thình.....”\nTiếng gõ cửa dồn dập xé toạc màn đêm tĩnh mịch... Liệu các bạn có đủ can đảm bước vào căn tứ hợp viện này không?','./src/assets/images/giay-theu-hoa.png',6,6,210,'ACTIVE',12,'2026-11-26 00:24:18.140','2026-11-26 00:24:18.140'),(13,'Thanh Lâu','kbs-thanhlau','Bước vào Ngọc Mãn Lâu danh tiếng nhất Trường An, nơi quy tụ bốn ca kỹ tài sắc và bốn công tử giàu có cùng mạng lưới ân oán đời trước.','./src/assets/images/thanh-lau.png','★★★ (3 sao)','Bước vào Ngọc Mãn Lâu, Thanh Lâu danh tiếng nhất Trường An, nơi quy tụ bốn ca kỹ tài sắc vẹn toàn và bốn chàng công tử giàu có. Dưới vẻ ngoài lộng lẫy của nghệ thuật và tình yêu, ẩn chứa một mạng lưới ân oán từ đời trước, kéo những người trẻ vào cuộc đua tranh đầy kịch tính, khám phá bí mật đằng sau những nụ cười rạng rỡ và tiếng đàn mê hoặc, liệu bạn có thể vén lên màn sương của sự dối trá và tham vọng?','./src/assets/images/thanh-lau.png',7,7,330,'ACTIVE',13,'2026-11-26 00:24:18.162','2026-11-26 00:24:18.162'),(14,'Hậu Cung','kbs-haucung','Trong Hậu Cung lộng lẫy nhưng đầy sóng gió, mỗi nụ cười của bốn nàng phi tần đều ẩn chứa mưu đồ và hàng loạt vụ án bí ẩn liên tiếp xảy ra.','./src/assets/images/hau-cung.png','★★ (2 sao)','Chào mừng bạn đến với triều đại huy hoàng nhưng đầy rối ren, nơi có một vị hoàng đế không thể nắm bắt được những sóng gió đang ập đến. Trong lòng của Hậu Cung lộng lẫy, bốn nàng phi tần không chỉ sở hữu vẻ đẹp kiêu sa mà còn ẩn chứa tài trí và tham vọng sâu sắc: Mỗi bước đi của họ đều được tính toán kỹ lưỡng, mỗi nụ cười là một mưu đồ, mỗi ánh mắt là một âm mưu toan tính.\nTrong bức tranh Hoàng Gia này, hàng loạt các vụ án bí ẩn liên tiếp xảy ra, mỗi cái chết đều vẽ lên một dấu hỏi lớn không lời giải. Từ những cái chết bất thường của các phi tần, đến những mất tích không dấu vết của cung nữ, mỗi sự kiện đều khiến Hậu Cung thêm phần u ám.','./src/assets/images/hau-cung.png',6,6,210,'ACTIVE',14,'2026-11-26 00:24:18.180','2026-11-26 00:24:18.180'),(15,'Thanh Thanh Mạn','kbs-ttm','Tựa game cổ trang đượm hồn thi ca đưa bạn sống lại yêu hận tình thù ba kiếp và tự mình viết nên hồi kết cho câu chuyện ngàn năm.','./src/assets/images/thanh-thanh-man.png','★★★ (3 sao)','“Một sợi tơ hồng vương ba kiếp, tam sinh thạch khắc chữ kết duyên, hết thảy huyền cơ nay giao cho các ngươi tự giải”\nTiếng bước ngân vang, dĩ vãng tiền trần dần hóa hư không. Chén canh Mạnh Bà đã uống cạn, đổi lấy một đời lệ rơi khắc tận tâm can. Ái tình vương thù hận, một kiếp nổi trôi rồi tan biến, muốn quên mà chẳng thể giữ lòng thanh tịnh.\n“Thanh Thanh Mạn” - tựa game cổ trang đượm hồn thi ca, sẽ đưa bạn vào một khung cảnh ngập tràn giai điệu u hoài của quá khứ. Không chỉ là một trò chơi, Thanh Thanh Mạn còn là một giấc mộng cổ phong - nơi bạn sống lại yêu hận tình thù, và tự mình viết nên hồi kết cho câu chuyện ngàn năm chưa dứt.','./src/assets/images/thanh-thanh-man.png',6,6,240,'ACTIVE',15,'2026-11-26 00:24:18.197','2026-11-26 00:24:18.197'),(16,'Khách Điếm Hồ Ly','kbs-kdhl','Một khách điếm bối cảnh Nhật chỉ xuất hiện khi trong lòng con người còn những ước nguyện chưa thành hay nuối tiếc chưa kịp buông.','./src/assets/images/khach-diem-ho-ly.png','★★★ (3 sao)','Một nơi chỉ xuất hiện khi trong lòng con người còn tồn tại những điều chưa thể gọi tên - có thể là một ước nguyện chưa thành, một nuối tiếc chưa kịp buông, hay đơn giản là một câu hỏi mà bạn vẫn chưa tìm được lời đáp.\nTại đây, mỗi người bước vào đều mang theo câu chuyện của riêng mình. Có người tìm kiếm một tia hy vọng le lói, có người lặng lẽ trốn khỏi thực tại ngột ngạt, cũng có người chỉ mong một lần được chạm tới điều mà họ đã khao khát từ rất lâu… dù chỉ là trong khoảnh khắc.\nKhách điếm Hồ Ly chưa bao giờ hứa hẹn những phép màu. Điều mà nơi này mang lại là cơ hội để mỗi người dừng lại, lắng nghe, và đối diện với chính mình.\n“Xin hỏi… bạn đã tìm thấy Khách điếm Hồ Ly chưa? Điều ước của bạn… đã thành hiện thực chưa?”\nBởi vì có những điều, chỉ khi dừng lại… bạn mới thực sự hiểu.','./src/assets/images/khach-diem-ho-ly.png',7,7,240,'ACTIVE',16,'2026-11-26 00:24:18.215','2026-11-26 00:24:18.215'),(17,'Ly Xuyên Quái Đàm Bộ','kbs-lxqdb','Bảy người tỉnh dậy bên trong tòa lầu khóa kín giữa lòng hồ quỷ dị vùng Thường Thế, nơi chủ nhân Taira no Masakado đã chết thảm.','./src/assets/images/ly-xuyen-quai-dam-bo.png','★★★★ (4 sao)','“Thường Thế — vùng đất nơi những tinh quái được sinh ra từ mặt hồ quỷ dị giữa màn sương bất tận.\nGiữa lòng hồ tồn tại một tòa lầu chưa từng có ai bước vào.\nCho đến một ngày có 7 người tỉnh dậy bên trong đó… Cánh cửa đã khóa kín. Không còn đường lui. Và chủ nhân của tòa lầu, Taira no Masakado đại nhân đã chết thảm trong căn mật thất.\nNhưng càng điều tra, họ càng nhận ra: Ở nơi này… ký ức có thể là giả, thời gian có thể bị thao túng, và con người chưa chắc đã còn là con người.”','./src/assets/images/ly-xuyen-quai-dam-bo.png',7,7,270,'ACTIVE',17,'2026-11-26 00:24:18.234','2026-11-26 00:24:18.234'),(18,'Đền Thần Shimizu Hachiman','kbs-dtsh','Nhật Bản năm 794 thời kỳ Heian đầy rối ren, ngôi làng nhỏ bên đền thờ Shimizu Hachiman bị cuốn vào vòng xoáy oan hồn và âm mưu đen tối.','./src/assets/images/den-than-shimizu-hachiman.png','★★★★ (4 sao)','Bước vào thế giới bí ẩn của Nhật Bản vào năm 794 công nguyên, trong bối cảnh di dời đô thành tới Heian Kyo, khởi đầu cho thời kỳ Heian đầy rối ren. Trong game “Đền Thần Shimizu Hachiman”, bạn sẽ khám phá một làng nhỏ tách biệt, nơi cuộc sống bình yên bị phá vỡ bởi những oan hồn và ma quỷ. Onmyoji, những bậc thầy về giải quyết họa phúc, giữ vai trò là người cân bằng giữa thế giới người và linh hồn. Tại đền thờ Shimizu Hachiman, lễ cúng không chỉ là nghi lễ tôn giáo, mà còn là bức tường chắn cuối cùng chống lại thế lực tà ác. Khi một âm mưu đen tối bắt đầu nhắm vào một gia đình nông dân bình thường, cuộc sống của họ và toàn bộ làng bị đẩy vào vòng xoáy của sự kiện kinh hoàng. Hãy tham gia và khám phá, liệu bạn có thể phá giải bí ẩn và khôi phục lại bình yên không?','./src/assets/images/den-than-shimizu-hachiman.png',6,6,240,'ACTIVE',18,'2026-11-26 00:24:18.250','2026-11-26 00:24:18.250'),(19,'Điêu Dân','kbs-dieudan','Khi đội khảo sát tới ngôi làng nhỏ nằm sâu trong hẻm núi muốn di dời dân cư, hàng loạt chuyện kỳ lạ liên tiếp xảy ra.','./src/assets/images/dieu-dan.png','★★★ (3 sao)','Ngôi làng nhỏ nằm sâu trong hẻm núi bao năm vẫn luôn bình yên, người dân trong làng thật thà chất phác.\nBỗng một ngày, đội khảo sát tới làng muốn di dời người dân để đầu tư khu du lịch. Cũng từ đây bao nhiêu chuyện kỳ lạ xảy ra, trong làng có người đột nhiên qua đời rồi vội vàng chôn ngay trong ngày, khu núi mồ của làng đột ngột bốc cháy, cá trong ao của một hộ gia đình đột ngột chết sạch....\nLiệu bí ẩn gì đang xảy ra tại ngôi làng vốn dĩ bình yên này?','./src/assets/images/dieu-dan.png',6,6,210,'ACTIVE',19,'2026-11-26 00:24:18.269','2026-11-26 00:24:18.269'),(20,'Tội Diễn Sư','kbs-tds','Năm 2026, khi máy truy vết CX-5A không có trực giác hay cảm xúc, Tội Diễn Sư ra đời để diễn lại tội ác và biến giả thuyết thành bằng chứng sống.','./src/assets/images/toi-dien-su.jpg','★★★ (3 sao)','Năm 2026, Máy Truy Vết Tội Phạm CX - 5A ra đời - một bước ngoặt trong điều tra hình sự. Chỉ cần quét qua thi thể, nó có thể nhanh chóng xác định vị trí nghi phạm. Nhưng công lý không thể chỉ dựa vào những phán đoán máy móc. CX - 5A không có trực giác, không cảm xúc, không thấy được sự dối trá và càng không thể đưa ra bằng chứng buộc tội. Vì thế, Tội Diễn Sư đã ra đời.\nHỌ KHÔNG NHƯ CẢNH SÁT. HỌ KHÔNG SUY LUẬN NHƯ THÁM TỬ. Họ diễn lại tội ác - Họ chính là nghệ thuật trong trinh thám.\nTội Diễn Sư nhập vai - tái hiện tâm lý và hành vi của nghi phạm lẫn nạn nhân tại thời điểm gây án. Họ biến giả thuyết thành bằng chứng sống. Họ là người cuối cùng đưa sự thật ra ánh sáng.','./src/assets/images/toi-dien-su.jpg',6,7,210,'ACTIVE',20,'2026-11-26 00:24:18.286','2026-11-26 00:24:18.286'),(21,'Kiếm Tiền','kbs-kiemtien','Bữa tiệc thường niên của tập đoàn Kim giàu có bậc nhất khu vực, nơi các nhà đầu tư trẻ dùng mưu trí để giành lấy cơ hội vàng.','./src/assets/images/kiem-tien.png','★★ (2 sao)','Hãy bước vào thế giới của tập đoàn Kim. Tập đoàn bí ẩn và giàu có bậc nhất khu vực. Mỗi năm một lần, họ mở cửa tổ chức một buổi tiệc hoành tráng, nơi quy tụ các nhà đầu tư trẻ và những nhà kinh doanh đầy tham vọng. Buổi tiệc không chỉ là dịp để mọi người thể hiện khả năng và tìm kiếm cơ hội đầu tư, mà còn là cửa ngõ để có thể gia nhập hàng ngũ của tập đoàn Kim đánh giá. Trong buổi tiệc hôm nay, các nhà đầu tư phải sử dụng mọi kỹ năng và mưu trí của mình để chứng minh giá trị và giành lấy cơ hội vàng này. Liệu họ có thể vượt qua các thử thách, áp lực và sự cạnh tranh khốc liệt để tỏa sáng?\nTham gia “Kiếm Tiền”, một trò chơi của sự khéo léo, chiến lược và lòng dũng cảm, nơi chỉ những người xứng đáng mới có thể chiến thắng.','./src/assets/images/kiem-tien.png',7,10,270,'ACTIVE',21,'2026-11-26 00:24:18.302','2026-11-26 00:24:18.302'),(22,'Cuộc Gọi Đến','kbs-cgd','Khi cuộc sống dồn ép, lằn ranh giữa đúng và sai trở nên mong manh — một cuộc gọi có thể đưa con người thoát khỏi nghèo đói hoặc nhấn chìm vào tội lỗi.','./src/assets/images/cuoc-goi-den.png','★★ (2 sao)','Khi thời đại thay đổi, con người có thể bị chôn vùi hoặc vươn lên từ những khó khăn để chào đón cuộc sống mới. Cuộc sống tạm bợ, an phận là điều thường thấy, nhưng nhiều người vẫn mơ ước bay cao mà không dám đánh thức giấc mơ để bước ra con đường rộng mở. Dù sự thuần khiết của ánh mặt trời che đậy những tham vọng xấu xa, bản chất con người vẫn dễ hiểu: Thiện lương, nhưng lòng người lại khó đoán.\nKhi cuộc sống dồn ép, lằn ranh giữa đúng và sai trở nên mong manh. Một cuộc gọi có thể thay đổi cả số phận, đưa con người thoát khỏi nghèo đói, hoặc nhấn chìm họ vào vòng xoáy tội lỗi.\n“Cuộc Gọi Đến” - có đơn giản là trò chơi của những kẻ lạc lối giữa lương thiện và tham vọng, bạn có dám tham gia?','./src/assets/images/cuoc-goi-den.png',6,6,240,'ACTIVE',22,'2026-11-26 00:24:18.320','2026-11-26 00:24:18.320'),(23,'Thiên Hạ Đệ Nhất Tửu','kbs-thdnt','Bảy tay uống rượu kiệt xuất mang theo bí mật riêng bước vào vòng xoáy của men rượu, hài hước và sự dối trá.','./src/assets/images/thien-ha-de-nhat-tuu.png','★★ (2 sao)','7 người chơi hóa thân thành những tay uống rượu kiệt xuất: Từng người sẽ mang trong mình những bí mật và mục tiêu riêng. Họ sẽ phải đối mặt với những âm mưu sâu xa, vạch trần hung thủ và giải mã những bí mật cùng trung tâm.\nLiệu bạn có đủ tỉnh táo để thoát khỏi vòng xoáy của men rượu và sự dối trá.\n“Thiên Hạ Đệ Nhất Tửu” - Một kịch bản vui vẻ, giải trí nhưng không kém phần kịch tính. Với những bất ngờ, hài hước trong cách chơi. Hãy chuẩn bị tinh thần bước vào một thế giới - nơi men say và bí ẩn giao thoa.','./src/assets/images/thien-ha-de-nhat-tuu.png',7,7,210,'ACTIVE',23,'2026-11-26 00:24:18.339','2026-11-26 00:24:18.339'),(24,'Ly Hôn Đi! Nhanh Lên','kbs-lhdnl','Đám cưới \"môn đăng hộ đối\" rộn ràng pháo cưới của hai hộ gia đình lại chính là khởi đầu cho những cơn bão ngầm hôn nhân.','./src/assets/images/ly-hon-di-nhanh-len.png','★★ (2 sao)','“Kết hôn, sinh con – ai rồi cũng phải trải qua. Thế nhưng, sau tiếng pháo cưới rộn ràng, những lời chúc tụng ngọt ngào, đập vào mặt là hàng tá chuyện phải lo: cơm áo gạo tiền, con cái học hành, mẹ chồng nàng dâu, ai cũng có nỗi khổ riêng….\nHôm nay, ở khu dân cư nọ có hai hộ gia đình kèn trống rộn ràng dán chữ Hỉ rực rỡ. Môn đăng hộ đối, trai tài gái sắc, cả làng hân hoan gọi đây là “mối duyên trời ban”! Cha mẹ đôi bên, bà con họ hàng tề tựu đông đủ, chứng kiến giây phút trọng đại của người thân yêu.\nAi mà có ngờ, ngày vui ấy lại chính là khởi đầu cho những “cơn bão ngầm” đang dần kéo tới…”','./src/assets/images/ly-hon-di-nhanh-len.png',6,8,270,'ACTIVE',24,'2026-11-26 00:24:18.355','2026-11-26 00:24:18.355'),(25,'100 Cách Chết NYC','kbs-100cc','Năm người bạn tụ họp trong một buổi tiệc và phát hiện những điểm bất thường kỳ lạ của người bạn trai quá cố xuất hiện ở bạn trai của các thành viên khác.','./src/assets/images/100-cach-chet-cua-nyc.png','★★ (2 sao)','Nhịp sống sôi động của thành phố, năm người bạn - mỗi người với cuộc sống riêng biệt và mối tình của mình - tập hợp lại với nhau trong 1 buổi tiệc, không ai ngờ rằng, đêm đó sẽ trở thành khởi đầu của một chuỗi sự kiện kinh hoàng.\nCái chết đột ngột và bí ẩn của bạn trai một thành viên trong nhóm chỉ là màn khởi đầu, sự việc ngày càng thêm rắc rối khi những điểm bất thường của người bạn trai quá cố này bắt đầu xuất hiện ở bạn trai của các thành viên khác. Một mô hình kỳ lạ và đáng sợ dần hé lộ, khiến mọi người không khỏi hoài nghi và sợ hãi.','./src/assets/images/100-cach-chet-cua-nyc.png',5,5,210,'ACTIVE',25,'2026-11-26 00:24:18.372','2026-11-26 00:24:18.372'),(26,'Thông Báo Tìm Người','kbs-tbtn','Ngày 13/8/2019, Bắc Giang chấn động khi cảnh sát phát hiện một khuôn đúc thép khổng lồ cùng đoạn video có người bị ném vào dòng thép nóng chảy.','./src/assets/images/thong-bao-tim-nguoi.png','★★★★ (4 sao)','Ngày 13/8/2019, Bắc Giang chấn động khi cảnh sát phát hiện một khuôn đúc thép khổng lồ bị lấp đầy thép nguội, kèm đoạn video mờ ám cho thấy có người từng bị ném sống vào dòng thép nóng chảy. Thi thể đã tan biến hoàn toàn, ADN không thể trích xuất, buộc cảnh sát lập đội điều tra đặc biệt để truy tìm danh tính nạn nhân giữa hàng loạt vụ án mất tích bí ẩn. Đêm 14/8, năm người dân lần lượt xuất hiện tại đồn công an trong trạng thái hoảng loạn tột độ, hé lộ những tình tiết khiến cả đội điều tra phải đối mặt với một âm mưu đen tối và cái ác chưa từng có tiền lệ.','./src/assets/images/thong-bao-tim-nguoi.png',5,5,240,'ACTIVE',26,'2026-11-26 00:24:18.391','2026-11-26 00:24:18.391'),(27,'Thược Dược Đen','kbs-tdd','Tại khách sạn Cecil đầy ám ảnh, năm cá nhân không liên quan bị cuốn vào cuộc điều tra siêu nhiên dựa trên vụ án có thật.','./src/assets/images/thuoc-duoc-den.png ','★★★★ (4 sao)','Tại khách sạn Cecil, nơi mà những bí ẩn chưa được giải đáp và câu chuyện ma quái tụ họp, năm cá nhân không liên quan tới nhau đã bị cuốn vào một vụ điều tra siêu nhiên bí ẩn, mỗi người mang theo một mục đích riêng, họ tập hợp dưới một mái nhà với hy vọng khám phá ra những sự thật đen tối ẩn sau những bức tường của khách sạn này. Khi họ bắt đầu đi sâu vào những hiện tượng kỳ lạ và những bóng ma của quá khứ bắt đầu thách thức hiện tại của họ, liệu họ có thể làm việc cùng nhau để giải mã bí ẩn, hay những bí mật mà họ khám phá sẽ tạo ra những hậu quả đáng sợ hơn họ tưởng tượng?\nTham gia “Thược Dược Đen”, một hành trình khám phá nơi ranh giới giữa sự thật và ám ảnh trở nên mờ ảo.','./src/assets/images/thuoc-duoc-den.png ',5,5,240,'ACTIVE',27,'2026-11-26 00:24:18.409','2026-11-26 00:24:18.409'),(28,'Niên Luân','kbs-nienluan','Dưới đáy giếng cũ kỹ tại ngôi làng từng chìm trong biển lửa mười năm trước, năm con người mất người thân được triệu tập bởi một bức thư không tên.','./src/assets/images/nien-luan.png','★★★★★ (5 sao)','Dưới đáy giếng cũ kỹ, năm con người đứng lặng, ánh mắt hướng lên bầu trời u ám. Họ không quen biết nhau, nhưng có một điểm chung - mỗi người đều mất đi một người thân trong những cái chết bí ẩn.\nMột bức thư không tên, một lời kêu gọi từ kẻ bí ẩn đã dẫn họ đến đây - ngôi làng bị lãng quên, nơi mười năm trước từng chìm trong biển lửa. Ai là kẻ đã triệu tập họ? Là sự tình cờ, hay có một bí mật kinh hoàng đang chờ được phơi bày?\nNhững cái chết trong quá khứ thực sự là tai nạn, hay chỉ là màn mở đầu cho một kế hoạch chưa hoàn tất?','./src/assets/images/nien-luan.png',5,5,240,'ACTIVE',28,'2026-11-26 00:24:18.426','2026-11-26 00:24:18.426'),(29,'Vụ Nha Quán','kbs-vnq','Thị trưởng Ngô Miên bị sát hại trong phòng làm việc khóa kín tại Vụ Nha Quán, mọi dấu vết đều chỉ về cái tên Bạch Nha.','./src/assets/images/vu-nha-quan.png','★★★★★ (5 sao)','“Tách, tách...” Tiếng búng tay vang lên, kéo bạn ra khỏi cơn mộng mị.\nTrên bàn là một xấp tài liệu về vụ án giết người kỳ quái nhất gần đây. Thị trưởng Ngô Miên bị sát hại trong phòng làm việc tại căn biệt thự Vụ Nha Quán. Cửa khóa kín, cửa sổ bị bịt chặt. Nạn nhân bị quấn kín trong lớp băng dính lạnh lẽo, như một lời thách thức đầy ngạo nghễ của hung thủ.\nMọi dấu vết đều chỉ về một cái tên đã vắng bóng nhiều năm - Bạch Nha. Là huyền thoại, là kẻ trừng phạt, hay chỉ là một bóng ma trở về từ quá khứ?\nĐây mới chỉ là sự khởi đầu mà thôi....','./src/assets/images/vu-nha-quan.png',6,6,270,'ACTIVE',29,'2026-11-26 00:24:18.444','2026-11-26 00:24:18.444'),(30,'Yandere 3','kbs-yandere3','Phần thứ ba của chuỗi Nhật Ký Tâm Thần Phân Liệt: điều gì xảy ra nếu quá khứ bị viết lại và biến cố định mệnh năm đó chưa từng bắt đầu?','./src/assets/images/yandere-3.png','★★★★★ (5 sao)','Không bao giờ thay đổi, đó có thật sự là tình yêu, hay chỉ là một sự ràng buộc khoác lên mình chiếc mặt nạ của tình yêu? Có những mối quan hệ được sinh ra từ niềm tin rằng tình yêu phải vĩnh cửu, phải bất biến. Nhưng cũng chính thứ tình cảm cực đoan ấy đã kéo hai con người xa lạ vào một mối ràng buộc nghiệt ngã, nơi khổ đau, cái chết và những vết thương tâm lý chồng chất, không bao giờ lành lại.\nNhiều người cho rằng kẻ đáng thương nhất là người “vô tình” gặp đúng định mệnh của mình. Nhưng nếu năm đó, biến cố ấy không xảy ra thì sao? Nếu nạn nhân không bị bắt cóc, không bước chân vào con đường nhuốm màu điên loạn ấy, liệu cuộc đời họ có rẽ sang một hướng khác? Hay số phận vốn đã được định sẵn, chỉ chờ một hình thức khác để lặp lại?\nYandere 3: Nhật kí tâm thần phân liệt không kể lại câu chuyện của những gì đã xảy ra, mà đặt ra một giả thuyết đáng sợ hơn: Điều gì sẽ xảy ra nếu mọi thứ ngay từ đầu đã không bắt đầu như ta vẫn tưởng?\nKhi sự thật rẽ sang một hướng hoàn toàn khác, ranh giới giữa lựa chọn và định mệnh dần trở nên mơ hồ. Liệu con người có thật sự thoát khỏi số phận khi thiếu đi biến cố từng được xem là “định đoạt tất cả?” Và nếu quá khứ bị viết lại, những con người ta đã gặp... liệu họ vẫn còn là chính họ?','./src/assets/images/yandere-3.png',6,6,300,'ACTIVE',30,'2026-11-26 00:24:18.461','2026-11-26 00:24:18.461'),(31,'Xin Chào','kbs-xinchao','Một tác phẩm kịch bản tình cảm sâu sắc khám phá những cung bậc của tình yêu, sự đồng cảm, tha thứ và ý nghĩa của những cuộc chia ly.','./src/assets/images/xin-chao.png','★★ (2 sao)','“Xin Chào” là một tác phẩm kịch bản tình cảm sâu sắc, khám phá những cung bậc của tình yêu và cuộc sống, cũng như những thăng trầm không thể tránh khỏi mà mỗi người một quan niệm: Có người cho rằng chia ly là không bao giờ gặp lại, nhưng cũng có người tin rằng chỉ khi nào chúng ta quên lãng, chia ly mới thực sự xảy ra.\nKịch bản này mời gọi bạn dừng chân, lắng nghe và cảm nhận sự yên bình quý giá của cuộc sống. Trong mỗi tương tác, mỗi câu chuyện, “Xin Chào” không chỉ là một lời từ biệt, một sự khởi đầu mới trong sự hiểu biết và chia sẻ, khám phá những khoảnh khắc tình yêu, sự đồng cảm và tha thứ trong thế giới phức tạp này, và để mỗi “Xin Chào” dẫn lối đến một trải nghiệm sâu sắc hơn về cuộc sống và mối quan hệ.','./src/assets/images/xin-chao.png',6,6,240,'ACTIVE',31,'2026-11-26 00:24:18.479','2026-11-26 00:24:18.479'),(32,'Thi Khúc Li Biệt','kbs-tklb','Bản tình ca dành cho thanh xuân đã qua — mười năm sau ngày rời ghế nhà trường, sáu người bạn ngoảnh đầu nhìn lại những điều chưa từng kịp nói.','./src/assets/images/thi-khuc-ly-biet.png','★★★ (3 sao)','[THI KHÚC LI BIỆT] – [BẢN TÌNH CA DÀNH CHO THANH XUÂN ĐÃ QUA]\nBạn có bao giờ nghĩ rằng tuổi trẻ cũng có màu sắc? Nếu có, đó hẳn là sắc trắng tinh khôi của đồng phục học sinh, hòa cùng gam xám nhạt của những cuộc chia ly.\n\"Thi Khúc Ly Biệt\" giống như một lá thư tình đã úa màu theo năm tháng, cất giữ những tâm sự vụng về của tuổi thiếu niên. Mở lá thư ấy ra, là cả một mùa hè rực rỡ của sáu con người từng cùng nhau lớn lên.\nHọ từng chạy đua trên sân trường, lén chuyền cho nhau những mảnh giấy nhỏ trong lớp học, cùng nghe chung một chiếc tai nghe trên con đường tan học. Thế nhưng trưởng thành chưa bao giờ là một hành trình êm đềm. Những tổn thương từ gia đình, những lời yêu chưa kịp nói, cùng sự trêu ngươi của số phận đã khiến họ dần lạc mất nhau nơi ngã tư tuổi trẻ.\nNgười ta vẫn nói thời gian có thể chữa lành mọi thứ. Nhưng \"Thi Khúc Ly Biệt\" lại kể cho bạn một điều khác: \"Thời gian không chữa lành mọi thứ, nó chỉ dạy chúng ta cách giả vờ mạnh mẽ.\"\nMười năm sau, khi ngoảnh đầu nhìn lại chính mình của những năm tháng mặc áo đồng phục ấy, bạn sẽ mỉm cười hoài niệm... hay bật khóc vì những điều chưa từng kịp nói?','./src/assets/images/thi-khuc-ly-biet.png',6,6,270,'ACTIVE',32,'2026-11-26 00:24:18.499','2026-11-26 00:24:18.499'),(33,'Dư Hương','kbs-duhuong','Cái chết của người bố làm dấy lên cuộc tái ngộ của năm anh chị em — liệu đó chỉ là tai nạn hay đằng sau là một âm mưu đen tối?','./src/assets/images/du-huong.png','★★★★★ (5 sao)','Câu chuyện của năm anh chị em, mỗi người mang những hiểu lầm và quan điểm riêng biệt do những thăng trầm của cuộc sống. Tình cảm gia đình đã thay đổi, nứt vỡ dần qua thời gian. Cái chết của người bố đã làm dấy lên cơ hội hiếm hoi để họ tái ngộ. Những bí mật lâu nay giấu kín bắt đầu được phơi bày, và mọi người phải đối mặt với những hiểu lầm và mâu thuẫn đã tích tụ bao năm. Liệu cuộc họp gia đình này có thể hàn gắn những rạn nứt, hay nó chỉ khiến cho hiểu lầm càng trở nên trầm trọng? Và quan trọng hơn, cái chết của người cha thực sự chỉ là một tai nạn, hay đằng sau đó là một âm mưu đen tối?\nHãy cùng khám phá sự thật “Dư Hương”.','./src/assets/images/du-huong.png',5,5,300,'ACTIVE',33,'2026-11-26 00:24:18.516','2026-11-26 00:24:18.516'),(34,'Cổ Thụ Ngân Nga','kbs-ctnn','Giữa lòng Hà Nội dưới tán đa ngàn năm tuổi, sáu học sinh trường THPT K.T. tỉnh lại trong phiên bản méo mó của ngôi trường vào ngày lễ Tạ Phúc Cầu An.','./src/assets/images/co-thu-ngan-nga.png','★★★★★ (5 sao)','Giữa lòng Hà Nội, dưới tán đa ngàn năm tuổi nơi từng vang vọng tiếng trống lễ Tạ Phúc Cầu An, người dân tin rằng nơi ấy hội tụ linh khí đất trời, là chốn thần linh ngự trị.\nRồi một dự án xây dựng được phê duyệt trên chính nền đất thiêng ấy. Kể từ đó, mỗi năm, vào đúng đêm lễ tế, lại có người chết trong những hoàn cảnh thảm khốc. Lời nguyền của “Cổ thụ” bắt đầu lan rộng, và khu đất ấy dần bị quên lãng…\nNhiều năm sau, một ngôi trường mang tên THPT K.T. được xây dựng lại trên mảnh đất xưa. Sáu học sinh của trường – những người tưởng chừng chẳng hề liên quan, bất ngờ rơi vào trạng thái mất ý thức trong ngày diễn ra lễ Tạ Phúc Cầu An.\nKhi tỉnh lại, họ thấy mình mắc kẹt trong một phiên bản méo mó của chính ngôi trường ấy: Ánh sáng đỏ như máu, tiếng cười trẻ con văng vẳng, những bóng người thật giả lẫn lộn, và gốc đa cổ thụ như đang thở than.\nĐể thoát ra, họ buộc phải đối diện với những ký ức bị chôn vùi, những linh hồn chưa siêu thoát và cả sự thật bị che giấu suốt hàng chục năm dưới lời nguyền đất thiêng.','./src/assets/images/co-thu-ngan-nga.png',6,6,300,'ACTIVE',34,'2026-11-26 00:24:18.533','2026-11-26 00:24:18.533'),(35,'Martinez Chết Tại Kinh Kỳ Quán','kbs-martinez','Được mời đến Kinh Kỳ Quán trên quần đảo Sevalin để tìm Cuốn Sách Kinh Kỳ, các ứng cử viên phải đối mặt với chuỗi vụ án phòng kín bất khả thi.','./src/assets/images/martinez-chet-tai-kinh-ky-quan.png','★★★★★★ (6 sao)','Trên đại lục Bruges, không ai biết Ngài Kỳ Tích Martinez là ai. Chỉ biết rằng mọi lời tiên tri của ông đều trở thành hiện thực.\nTrước khi biến mất, Martinez tiết lộ mọi phép màu đều bắt nguồn từ Cuốn Sách Kinh Kỳ, hiện được cất giữ tại Kinh Kỳ Quán trên quần đảo Sevalin. Chỉ người được cuốn sách lựa chọn mới có thể thay đổi vận mệnh thế giới.\nLà một trong những ứng cử viên được mời đến, bạn lên đường với hy vọng và những bí mật của riêng mình. Nhưng chờ đợi phía trước không phải phép màu, mà là chuỗi vụ án bất khả thi, những căn phòng kín, mê cung tuyết trắng và hệ thống giám sát, cùng những cánh cửa bị phong kín tưởng chừng không thể mở.\nMỗi bước tiến đều là một phép thử. Mỗi lựa chọn đều phải đánh đổi.\nBởi trong Kinh Kỳ Quán, có một thứ mà bạn nhất định phải tìm được. Dù phải đối mặt với cái chết, dù phải phá vỡ mọi xiềng xích của số phận... Bạn vẫn sẽ tiếp tục tiến về phía trước.','./src/assets/images/martinez-chet-tai-kinh-ky-quan.png',6,6,360,'ACTIVE',35,'2026-11-26 00:24:18.551','2026-11-26 00:24:18.551'),(36,'Lập Phương Quán','kbs-lpq','Sáu người cùng bước vào khối kiến trúc kỳ lạ giữa vùng đất hoang và vài ngày sau cảnh sát tìm thấy đủ 6 thi thể trong hiện trường khép kín tuyệt đối.','./src/assets/images/lap-phuong-quan.png','★★★★★★ (6 sao)','Giữa một vùng đất hoang gần như tách biệt hoàn toàn với thế giới bên ngoài, tồn tại một khối kiến trúc kỳ lạ. Không ai biết nó được xây từ bao giờ, cũng chẳng ai biết bên trong thực sự có gì. Người ta chỉ gọi nơi ấy bằng một cái tên: “Lập Phương Quán”.\nMột ngày, 6 người cùng bước vào. Vài ngày sau, cảnh sát tìm thấy đủ 6 thi thể. Không nhân chứng, không dấu vết bên ngoài xâm nhập, hiện trường gần như khép kín tuyệt đối. Nhưng cái chết của họ lại xảy ra theo những cách gần như không thể lý giải bằng logic thông thường.\nBạn bắt đầu điều tra, lần theo manh mối, dựng lại hiện trường và tìm hung thủ. Một đáp án xuất hiện: Hợp lý, chặt chẽ, gần như hoàn hảo. Nhưng rồi chỉ một chi tiết nhỏ cũng đủ khiến toàn bộ suy luận sụp đổ. Bạn tìm ra lời giải thứ hai, rồi thứ ba… và mỗi lần tưởng đã chạm tới sự thật, vụ án lại bị lật ngược.\nCàng lấn sâu vào, câu hỏi không còn là “Hung thủ đã giết họ như thế nào?”, mà là: “Những gì bạn tin từ đầu… có thật sự đúng không?”','./src/assets/images/lap-phuong-quan.png',6,6,330,'ACTIVE',36,'2026-11-26 00:24:18.569','2026-11-26 00:24:18.569'),(37,'Cô Gái Bên Cửa Sổ','kbs-cgbcs','Mười năm trước, một nữ sinh bị sát hại một cách tàn nhẫn, nhưng thủ phạm vẫn chưa bị bắt. Duy nhất một diễn đàn trực tuyến vẫn không ngừng theo dõi và cập nhật về vụ án này.','./src/assets/images/co-gai-ben-cua-so.png','★★★ (3 sao)','Mười năm trước, một nữ sinh bị sát hại một cách tàn nhẫn, nhưng thủ phạm vẫn chưa bị bắt. Duy nhất một diễn đàn trực tuyến vẫn không ngừng theo dõi và cập nhật về vụ án này. Ngày kỉ niệm mười năm vụ án, những thông tin mới bất ngờ xuất hiện khiến diễn đàn một lần nữa sục sôi.\nHãy cùng khám phá sự thật đằng sau bức màn bí mật, liệu bạn có thể tìm ra manh mối giải quyết vụ án? Mọi bí ẩn sẽ dần được hé lộ qua từng trang kịch bản mà bạn mở.','./src/assets/images/co-gai-ben-cua-so.png',6,6,180,'ACTIVE',37,'2026-11-26 00:24:18.589','2026-11-26 00:24:18.589'),(38,'Gương','kbs-guong','Tháng 7/2013, Biệt thự Sương Mai rực rỡ ánh đèn, chào đón những vị khách danh giá đến buổi dạ tiệc hóa trang xa hoa của Vương Sở Phàm.','./src/assets/images/guong.png','★★★ (3 sao)','Khi bạn nhìn chăm chú vào gương, người trong gương cũng đang chăm chú nhìn vào bạn.\nTháng 7/2013, Biệt thự Sương Mai rực rỡ ánh đèn, chào đón những vị khách danh giá đến buổi dạ tiệc hóa trang xa hoa của Vương Sở Phàm. Một bữa tiệc, một lời tuyên bố, và một bí ẩn bao trùm cả tòa biệt thự. Nhưng khi những ly rượu nâng lên, bạn chợt nhận ra - những tấm gương quanh mình dường như có gì đó sai lệch. Hình ảnh phản chiếu không chỉ là bóng dáng của bạn... mà còn là một ánh mắt khác, lạnh lẽo và thầm lặng.\nĐêm nay, liệu bạn có dám nhìn thẳng vào gương?','./src/assets/images/guong.png',4,4,180,'ACTIVE',38,'2026-11-26 00:24:18.607','2026-11-26 00:24:18.607'),(39,'Nguyệt Lạc Oa','kbs-nlo','Ngày 20/9/1914 (năm Dân Quốc thứ ba), tại vùng giáp ranh Giang Tô - Chiết Giang có một trấn cổ tên Tây Đường, nơi sông nước chằng chịt và cây cối rậm rạp.','./src/assets/images/nguyet-lac-oa.png','★★★ (3 sao)','Ngày 20/9/1914 (năm Dân Quốc thứ ba), tại vùng giáp ranh Giang Tô - Chiết Giang có một trấn cổ tên Tây Đường, nơi sông nước chằng chịt và cây cối rậm rạp.\nVài chục năm trước, một thiên thạch rơi xuống khu rừng phía tây nam trấn, tạo thành vùng trũng rồi dần hóa thành một mặt hồ. Người dân tin rằng đó là đá giữ thời gian của cung trăng rơi xuống trần gian, nên gọi nơi này là Nguyệt Lạc Oa.\nSau này, một học giả họ An đã xây dựng Thời Lạc Trang gần hồ và đưa gia đình đến sinh sống. Ông thường xuyên qua lại giữa đây và Thượng Hải, đồng thời là giảng viên thỉnh giảng của khóa học \"Sự tồn tại của linh hồn\" tại Đại học St. John.\nCó phải nơi này thực sự có thứ gì đó.... vượt ngoài khoa học?\nVà hôm nay... Bạn chính là những người bước vào Thời Lạc Trang. Sự thật của Nguyệt Lạc Oa đang chờ được hé lộ.','./src/assets/images/nguyet-lac-oa.png',5,5,210,'ACTIVE',39,'2026-11-26 00:24:18.627','2026-11-26 00:24:18.627'),(40,'Sinh Tử','kbs-sinhtu','Ngày 28 tháng 07 năm 2008, thành phố Hạ Long chìm trong cơn mưa u ám, một vụ án rùng rợn xảy ra tại phòng 401 khách sạn Minh Vũ.','./src/assets/images/sinh-tu.png','★★★ (3 sao)','Ngày 28 tháng 07 năm 2008, thành phố Hạ Long chìm trong cơn mưa u ám, một vụ án rùng rợn xảy ra tại phòng 401 khách sạn Minh Vũ, thi thể không đầu, với chiếc đầu heo thay thế, nằm giữa mùi máu tanh và dòng chữ oán hận viết bằng máu trên tường, bí ẩn phủ kín, nạn nhân được gọi là \"Súc Sinh\".\nMang theo một quá khứ đen tối, cái chết này là sự trừng phạt, hay chỉ là sự khởi đầu cho tội ác mới?\nKhi màn đêm buông xuống, bí mật đen tối dần hé lộ, không một tội ác nào thoát khỏi số phận.','./src/assets/images/sinh-tu.png',5,5,210,'ACTIVE',40,'2026-11-26 00:24:18.646','2026-11-26 00:24:18.646'),(41,'Lý Luận Hư Cấu','kbs-llhc','\"Lý Luận Hư Cấu\" mở ra một thế giới nơi thật giả đan xen, biến mọi sự phán đoán thành con dao hai lưỡi.','./src/assets/images/ly-luan-hu-cau.png','★★★★★★ (6 sao)','\"Lý Luận Hư Cấu\" mở ra một thế giới nơi thật giả đan xen, biến mọi sự phán đoán thành con dao hai lưỡi. Thư Mời Tham Gia Câu Lạc Bộ Manchester được gửi đi, cùng lời chào mừng đầy vẻ lịch thiệp, nhưng ẩn giấu sau đó là trò chơi Trốn Tìm quái gở.\nNgười tham gia đeo mặt nạ, bước lên sân khấu kể hai câu chuyện: một là ký ức thật chưa từng tiết lộ, một là lời bịa đặt khéo léo giăng bẫy người nghe.\nKhông bàn luận, không gọi tên, chỉ có những lá phiếu lặng lẽ phơi bày ai lộ sơ hở. Kẻ bại trận sẽ ôm lấy trách nhiệm chuộc lỗi, bằng tiền bạc và một bữa tiệc kỳ quái, nơi sự hoài nghi chưa từng khép lại.','./src/assets/images/ly-luan-hu-cau.png',6,6,300,'ACTIVE',41,'2026-11-26 00:24:18.669','2026-11-26 00:24:18.669'),(42,'Ngôi Nhà Ngọt Ngào','kbs-nnna','Thần linh đã biến mất. Dấu chân con người cũng chẳng còn. Vậy còn gì để nương tựa? Chỉ có gia đình - gia đình ngọt ngào.','./src/assets/images/ngoi-nha-ngot-ngao.png','★★★★ (4 sao)','Giờ đây, tôi sẽ cho các bạn thấy toàn cảnh ngôi nhà. Dòng sông chảy máu, xương trắng kêu răng rắc trong những dây leo khô.\nHãy cảnh giác những trái quả như đầu lâu. Chúng sẽ bào mòn lương tri, nuốt đi ký ức, làm mờ ý thức, che khuất đôi mắt bạn.\nThần linh đã biến mất. Dấu chân con người cũng chẳng còn.\nVậy còn gì để nương tựa? Chỉ có gia đình - gia đình ngọt ngào.\nNhững vị khách đáng kính... Chào mừng đến với Ngôi Nhà Ngọt Ngào!!!!!!','./src/assets/images/ngoi-nha-ngot-ngao.png',5,5,240,'ACTIVE',42,'2026-11-26 00:24:18.688','2026-11-26 00:24:18.688');
/*!40000 ALTER TABLE `games` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `is_read` tinyint NOT NULL DEFAULT '0',
  `read_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read_created` (`user_id`,`is_read`,`created_at`),
  KEY `idx_notif_user_read_created` (`user_id`,`is_read`,`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_notifications_is_read` CHECK ((`is_read` in (0,1)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `participant_price_snapshots`
--

DROP TABLE IF EXISTS `participant_price_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `participant_price_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `room_participant_id` bigint unsigned NOT NULL,
  `base_price` bigint unsigned NOT NULL,
  `discount_amount` bigint unsigned NOT NULL DEFAULT '0',
  `final_amount` bigint unsigned NOT NULL,
  `deposit_amount` bigint unsigned NOT NULL,
  `remaining_amount` bigint unsigned NOT NULL,
  `promotion_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_participant_price_snapshot` (`room_participant_id`),
  KEY `idx_participant_snapshot_room` (`game_room_id`),
  KEY `idx_participant_snapshot_promotion` (`promotion_id`),
  KEY `fk_participant_snapshot_room_participant` (`game_room_id`,`room_participant_id`),
  CONSTRAINT `fk_participant_snapshot_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_participant_snapshot_room_participant` FOREIGN KEY (`game_room_id`, `room_participant_id`) REFERENCES `room_participants` (`game_room_id`, `id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_participant_snapshot_amounts` CHECK (((`discount_amount` <= `base_price`) and (`final_amount` = (`base_price` - `discount_amount`)) and ((`deposit_amount` + `remaining_amount`) = `final_amount`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `participant_price_snapshots`
--

LOCK TABLES `participant_price_snapshots` WRITE;
/*!40000 ALTER TABLE `participant_price_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `participant_price_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_transactions`
--

DROP TABLE IF EXISTS `payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `player_payment_id` bigint unsigned NOT NULL,
  `idempotency_key` varchar(100) NOT NULL,
  `transaction_reference` varchar(150) DEFAULT NULL,
  `amount` bigint unsigned NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'PENDING',
  `provider_response` json DEFAULT NULL,
  `requested_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `processed_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_transaction_idempotency` (`idempotency_key`),
  KEY `idx_payment_transactions_payment_created` (`player_payment_id`,`created_at`),
  CONSTRAINT `fk_payment_transactions_payment` FOREIGN KEY (`player_payment_id`) REFERENCES `player_payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_payment_transaction_amount` CHECK ((`amount` > 0)),
  CONSTRAINT `chk_payment_transaction_status` CHECK ((`status` in (_utf8mb4'PENDING',_utf8mb4'SUCCESS',_utf8mb4'FAILED',_utf8mb4'CANCELLED')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_transactions`
--

LOCK TABLES `payment_transactions` WRITE;
/*!40000 ALTER TABLE `payment_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `phinxlog`
--

DROP TABLE IF EXISTS `phinxlog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phinxlog` (
  `version` bigint NOT NULL,
  `migration_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phinxlog`
--

LOCK TABLES `phinxlog` WRITE;
/*!40000 ALTER TABLE `phinxlog` DISABLE KEYS */;
INSERT INTO `phinxlog` VALUES (20261121000000,'InitKichBanSatV3Schema','2026-11-20 18:10:34','2026-11-20 18:10:36',0);
/*!40000 ALTER TABLE `phinxlog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `physical_rooms`
--

DROP TABLE IF EXISTS `physical_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `physical_rooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `venue_id` bigint unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `capacity` smallint unsigned NOT NULL,
  `description` text,
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_physical_room_venue_name` (`venue_id`,`name`),
  UNIQUE KEY `uq_physical_room_venue_id` (`venue_id`,`id`),
  KEY `idx_physical_rooms_venue_status` (`venue_id`,`status`),
  CONSTRAINT `fk_physical_rooms_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_physical_room_capacity` CHECK ((`capacity` > 0)),
  CONSTRAINT `chk_physical_room_status` CHECK ((`status` in (_utf8mb4'ACTIVE',_utf8mb4'INACTIVE')))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `physical_rooms`
--

LOCK TABLES `physical_rooms` WRITE;
/*!40000 ALTER TABLE `physical_rooms` DISABLE KEYS */;
INSERT INTO `physical_rooms` VALUES (1,1,'Phòng Mật Thất 01',8,'Tầng 2 — Cách âm tiêu chuẩn, ánh sáng điện ảnh','ACTIVE','2026-11-26 00:24:17.931','2026-11-26 00:24:17.931'),(2,1,'Phòng Biệt Thự Cổ 02',8,'Tầng 2 — Bối cảnh dinh thự cổ điển','ACTIVE','2026-11-26 00:24:17.931','2026-11-26 00:24:17.931'),(3,1,'Phòng Án Mạng Đêm 03',7,'Tầng 3 — Chuyên kịch bản kinh dị & tâm linh','ACTIVE','2026-11-26 00:24:17.931','2026-11-26 00:24:17.931'),(4,1,'Phòng Gương Vỡ 04',6,'Tầng 3 — Không gian suy luận tâm lý kín','ACTIVE','2026-11-26 00:24:17.931','2026-11-26 00:24:17.931'),(5,1,'Phòng Thẩm Vấn 05',8,'Tầng 4 — Phục vụ nhóm đông 6–8 người','ACTIVE','2026-11-26 00:24:17.931','2026-11-26 00:24:17.931');
/*!40000 ALTER TABLE `physical_rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `player_payments`
--

DROP TABLE IF EXISTS `player_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_participant_id` bigint unsigned NOT NULL,
  `payment_type` varchar(20) NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `paid_amount` bigint unsigned NOT NULL DEFAULT '0',
  `status` varchar(30) NOT NULL DEFAULT 'PENDING',
  `deadline_at` datetime(3) DEFAULT NULL,
  `paid_at` datetime(3) DEFAULT NULL,
  `expired_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_player_payment_type` (`room_participant_id`,`payment_type`),
  KEY `idx_player_payments_status_deadline` (`status`,`deadline_at`),
  KEY `idx_payments_status_type_created` (`status`,`payment_type`,`created_at`),
  CONSTRAINT `fk_player_payments_participant` FOREIGN KEY (`room_participant_id`) REFERENCES `room_participants` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_player_payment_amount` CHECK (((`amount` > 0) and (`paid_amount` <= `amount`))),
  CONSTRAINT `chk_player_payment_status` CHECK ((`status` in (_utf8mb4'PENDING',_utf8mb4'PROCESSING',_utf8mb4'PAID',_utf8mb4'PARTIALLY_PAID',_utf8mb4'FAILED',_utf8mb4'EXPIRED',_utf8mb4'CANCELLED'))),
  CONSTRAINT `chk_player_payment_type` CHECK ((`payment_type` in (_utf8mb4'DEPOSIT',_utf8mb4'REMAINING')))
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `player_payments`
--

LOCK TABLES `player_payments` WRITE;
/*!40000 ALTER TABLE `player_payments` DISABLE KEYS */;
INSERT INTO `player_payments` VALUES (1,1,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.727'),(2,4,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.754'),(3,7,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.779'),(4,9,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.798'),(5,13,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.826'),(6,14,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.835'),(7,15,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.845'),(8,16,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.855'),(9,17,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.864'),(10,18,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.872'),(11,19,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.902'),(12,20,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.911'),(13,21,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.920'),(14,22,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.929'),(15,23,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.941'),(16,24,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.950'),(17,25,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.982'),(18,26,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.991'),(19,27,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:18.999'),(20,28,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:19.010'),(21,29,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:19.024'),(22,30,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:19.033'),(23,31,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:19.060'),(24,32,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:19.068'),(25,33,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:19.077'),(26,34,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:19.086'),(27,35,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:19.094'),(28,36,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-26 00:24:18.000',NULL,'2026-11-26 00:24:18.000','2026-11-26 00:24:19.103'),(29,37,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-25 19:00:00.000',NULL,'2026-11-25 19:00:00.000','2026-11-26 00:24:19.168'),(30,37,'REMAINING',149000,0,'PAID',NULL,'2026-11-25 23:00:00.000',NULL,'2026-11-25 23:00:00.000','2026-11-26 00:24:19.173'),(31,38,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-25 19:00:00.000',NULL,'2026-11-25 19:00:00.000','2026-11-26 00:24:19.182'),(32,38,'REMAINING',149000,0,'PAID',NULL,'2026-11-25 23:00:00.000',NULL,'2026-11-25 23:00:00.000','2026-11-26 00:24:19.186'),(33,39,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-25 19:00:00.000',NULL,'2026-11-25 19:00:00.000','2026-11-26 00:24:19.196'),(34,39,'REMAINING',149000,0,'PAID',NULL,'2026-11-25 23:00:00.000',NULL,'2026-11-25 23:00:00.000','2026-11-26 00:24:19.201'),(35,40,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-25 19:00:00.000',NULL,'2026-11-25 19:00:00.000','2026-11-26 00:24:19.209'),(36,40,'REMAINING',149000,0,'PAID',NULL,'2026-11-25 23:00:00.000',NULL,'2026-11-25 23:00:00.000','2026-11-26 00:24:19.213'),(37,41,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-25 19:00:00.000',NULL,'2026-11-25 19:00:00.000','2026-11-26 00:24:19.222'),(38,41,'REMAINING',149000,0,'PAID',NULL,'2026-11-25 23:00:00.000',NULL,'2026-11-25 23:00:00.000','2026-11-26 00:24:19.226'),(39,42,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-25 19:00:00.000',NULL,'2026-11-25 19:00:00.000','2026-11-26 00:24:19.235'),(40,42,'REMAINING',149000,0,'PAID',NULL,'2026-11-25 23:00:00.000',NULL,'2026-11-25 23:00:00.000','2026-11-26 00:24:19.239'),(41,43,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-23 20:00:00.000',NULL,'2026-11-23 20:00:00.000','2026-11-26 00:24:19.265'),(42,43,'REMAINING',149000,0,'PAID',NULL,'2026-11-24 00:00:00.000',NULL,'2026-11-24 00:00:00.000','2026-11-26 00:24:19.270'),(43,44,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-23 20:00:00.000',NULL,'2026-11-23 20:00:00.000','2026-11-26 00:24:19.278'),(44,44,'REMAINING',149000,0,'PAID',NULL,'2026-11-24 00:00:00.000',NULL,'2026-11-24 00:00:00.000','2026-11-26 00:24:19.282'),(45,45,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-23 20:00:00.000',NULL,'2026-11-23 20:00:00.000','2026-11-26 00:24:19.291'),(46,45,'REMAINING',149000,0,'PAID',NULL,'2026-11-24 00:00:00.000',NULL,'2026-11-24 00:00:00.000','2026-11-26 00:24:19.295'),(47,46,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-23 20:00:00.000',NULL,'2026-11-23 20:00:00.000','2026-11-26 00:24:19.304'),(48,46,'REMAINING',149000,0,'PAID',NULL,'2026-11-24 00:00:00.000',NULL,'2026-11-24 00:00:00.000','2026-11-26 00:24:19.310'),(49,47,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-23 20:00:00.000',NULL,'2026-11-23 20:00:00.000','2026-11-26 00:24:19.318'),(50,47,'REMAINING',149000,0,'PAID',NULL,'2026-11-24 00:00:00.000',NULL,'2026-11-24 00:00:00.000','2026-11-26 00:24:19.323'),(51,48,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-23 20:00:00.000',NULL,'2026-11-23 20:00:00.000','2026-11-26 00:24:19.332'),(52,48,'REMAINING',149000,0,'PAID',NULL,'2026-11-24 00:00:00.000',NULL,'2026-11-24 00:00:00.000','2026-11-26 00:24:19.336'),(53,49,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-21 14:00:00.000',NULL,'2026-11-21 14:00:00.000','2026-11-26 00:24:19.362'),(54,49,'REMAINING',149000,0,'PAID',NULL,'2026-11-21 18:00:00.000',NULL,'2026-11-21 18:00:00.000','2026-11-26 00:24:19.366'),(55,50,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-21 14:00:00.000',NULL,'2026-11-21 14:00:00.000','2026-11-26 00:24:19.376'),(56,50,'REMAINING',149000,0,'PAID',NULL,'2026-11-21 18:00:00.000',NULL,'2026-11-21 18:00:00.000','2026-11-26 00:24:19.380'),(57,51,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-21 14:00:00.000',NULL,'2026-11-21 14:00:00.000','2026-11-26 00:24:19.391'),(58,51,'REMAINING',149000,0,'PAID',NULL,'2026-11-21 18:00:00.000',NULL,'2026-11-21 18:00:00.000','2026-11-26 00:24:19.396'),(59,52,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-21 14:00:00.000',NULL,'2026-11-21 14:00:00.000','2026-11-26 00:24:19.405'),(60,52,'REMAINING',149000,0,'PAID',NULL,'2026-11-21 18:00:00.000',NULL,'2026-11-21 18:00:00.000','2026-11-26 00:24:19.410'),(61,53,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-21 14:00:00.000',NULL,'2026-11-21 14:00:00.000','2026-11-26 00:24:19.420'),(62,53,'REMAINING',149000,0,'PAID',NULL,'2026-11-21 18:00:00.000',NULL,'2026-11-21 18:00:00.000','2026-11-26 00:24:19.425'),(63,54,'DEPOSIT',50000,0,'PAID',NULL,'2026-11-21 14:00:00.000',NULL,'2026-11-21 14:00:00.000','2026-11-26 00:24:19.434'),(64,54,'REMAINING',149000,0,'PAID',NULL,'2026-11-21 18:00:00.000',NULL,'2026-11-21 18:00:00.000','2026-11-26 00:24:19.439');
/*!40000 ALTER TABLE `player_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promotions`
--

DROP TABLE IF EXISTS `promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promotions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `promotion_type` varchar(30) NOT NULL,
  `discount_type` varchar(20) NOT NULL,
  `discount_value` bigint unsigned NOT NULL,
  `start_at` datetime(3) NOT NULL,
  `end_at` datetime(3) NOT NULL,
  `rules` json DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_promotions_code` (`code`),
  KEY `idx_promotions_active_period` (`is_active`,`start_at`,`end_at`),
  CONSTRAINT `chk_promotions_active` CHECK ((`is_active` in (0,1))),
  CONSTRAINT `chk_promotions_discount_type` CHECK ((`discount_type` in (_utf8mb4'PERCENT',_utf8mb4'FIXED'))),
  CONSTRAINT `chk_promotions_discount_value` CHECK ((`discount_value` > 0)),
  CONSTRAINT `chk_promotions_percent` CHECK (((`discount_type` <> _utf8mb4'PERCENT') or (`discount_value` <= 100))),
  CONSTRAINT `chk_promotions_period` CHECK ((`end_at` > `start_at`))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promotions`
--

LOCK TABLES `promotions` WRITE;
/*!40000 ALTER TABLE `promotions` DISABLE KEYS */;
INSERT INTO `promotions` VALUES (1,'TUESDAY_30','Ưu đãi vui vẻ - Thứ Ba Giảm 30%','WEEKDAY','PERCENT',30,'2026-01-01 00:00:00.000','2035-12-31 23:59:59.999','{\"weekdays\": [2], \"stackable\": false, \"applies_to\": \"ANY_GAME\", \"valid_from\": \"09:00:00\", \"valid_until\": \"19:00:00\", \"minimum_booking_hours\": 24}',1,'2026-11-26 00:24:18.708','2026-11-26 00:24:18.708'),(2,'HSSV_20000','Ưu đãi học sinh sinh viên (Kiểm tra thẻ tại quán)','STUDENT','FIXED',20000,'2026-01-01 00:00:00.000','2035-12-31 23:59:59.999','{\"weekdays\": [1, 2, 3, 4], \"stackable\": false, \"valid_from\": \"09:00:00\", \"valid_until\": \"19:00:00\", \"discount_per_eligible_person\": 20000, \"requires_student_verification\": true}',1,'2026-11-26 00:24:18.708','2026-11-26 00:24:18.708');
/*!40000 ALTER TABLE `promotions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refunds`
--

DROP TABLE IF EXISTS `refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refunds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `player_payment_id` bigint unsigned NOT NULL,
  `amount` bigint unsigned NOT NULL,
  `reason` text NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'PENDING',
  `requested_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` datetime(3) DEFAULT NULL,
  `completed_at` datetime(3) DEFAULT NULL,
  `review_reason` text,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_refunds_payment_status` (`player_payment_id`,`status`),
  KEY `idx_refunds_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`player_payment_id`) REFERENCES `player_payments` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_refunds_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_refunds_amount` CHECK ((`amount` > 0)),
  CONSTRAINT `chk_refunds_status` CHECK ((`status` in (_utf8mb4'PENDING',_utf8mb4'APPROVED',_utf8mb4'REJECTED',_utf8mb4'PROCESSING',_utf8mb4'COMPLETED',_utf8mb4'FAILED')))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refunds`
--

LOCK TABLES `refunds` WRITE;
/*!40000 ALTER TABLE `refunds` DISABLE KEYS */;
INSERT INTO `refunds` VALUES (1,1,50000,'Bận việc đột xuất, báo hủy trước 24 giờ theo chính sách.','PENDING','2026-11-26 00:24:19.458',NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.458','2026-11-26 00:24:19.458'),(2,2,50000,'Xin đổi sang tuần sau nên hủy ghế phòng hiện tại.','PENDING','2026-11-26 00:24:19.465',NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.465','2026-11-26 00:24:19.465');
/*!40000 ALTER TABLE `refunds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'PLAYER','Người chơi','Thành viên tham gia ghép phòng và đặt lịch chơi','2026-11-26 00:24:17.090','2026-11-26 00:24:17.090'),(2,'DM','Quản trò (DM)','Quản trò dẫn dắt buổi chơi Kịch Bản Sát','2026-11-26 00:24:17.090','2026-11-26 00:24:17.090'),(3,'ADMIN','Quản trị viên','Quản trị toàn hệ thống Kịch Bản Sát V16','2026-11-26 00:24:17.090','2026-11-26 00:24:17.090');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `room_participants`
--

DROP TABLE IF EXISTS `room_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `room_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'PENDING_DEPOSIT',
  `active_user_id` bigint unsigned GENERATED ALWAYS AS ((case when (`status` in (_utf8mb4'PENDING_DEPOSIT',_utf8mb4'DEPOSITED',_utf8mb4'LEAVE_REQUESTED')) then `user_id` else NULL end)) STORED,
  `joined_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `deposit_deadline_at` datetime(3) DEFAULT NULL,
  `deposited_at` datetime(3) DEFAULT NULL,
  `left_at` datetime(3) DEFAULT NULL,
  `cancelled_at` datetime(3) DEFAULT NULL,
  `cancel_reason` text,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_room_participant_room_id` (`game_room_id`,`id`),
  UNIQUE KEY `uq_room_active_user` (`game_room_id`,`active_user_id`),
  KEY `idx_participants_room_status` (`game_room_id`,`status`),
  KEY `idx_participants_user_created` (`user_id`,`created_at`),
  KEY `idx_participants_deposit_deadline` (`status`,`deposit_deadline_at`),
  CONSTRAINT `fk_participants_game_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_participant_status` CHECK ((`status` in (_utf8mb4'PENDING_DEPOSIT',_utf8mb4'DEPOSITED',_utf8mb4'LEAVE_REQUESTED',_utf8mb4'LEFT',_utf8mb4'CANCELLED_UNPAID',_utf8mb4'CANCELLED_BY_ADMIN')))
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `room_participants`
--

LOCK TABLES `room_participants` WRITE;
/*!40000 ALTER TABLE `room_participants` DISABLE KEYS */;
INSERT INTO `room_participants` (`id`, `game_room_id`, `user_id`, `status`, `joined_at`, `deposit_deadline_at`, `deposited_at`, `left_at`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES (1,1,7,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.721','2026-11-26 00:24:18.721'),(2,1,8,'PENDING_DEPOSIT','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.733','2026-11-26 00:24:18.733'),(3,1,9,'PENDING_DEPOSIT','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.738','2026-11-26 00:24:18.738'),(4,2,10,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.748','2026-11-26 00:24:18.748'),(5,2,11,'PENDING_DEPOSIT','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.759','2026-11-26 00:24:18.759'),(6,2,12,'PENDING_DEPOSIT','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.765','2026-11-26 00:24:18.765'),(7,3,13,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.775','2026-11-26 00:24:18.775'),(8,3,14,'PENDING_DEPOSIT','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.783','2026-11-26 00:24:18.783'),(9,4,15,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.794','2026-11-26 00:24:18.794'),(10,4,16,'PENDING_DEPOSIT','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.802','2026-11-26 00:24:18.802'),(11,4,17,'PENDING_DEPOSIT','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.807','2026-11-26 00:24:18.807'),(12,4,18,'PENDING_DEPOSIT','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.813','2026-11-26 00:24:18.813'),(13,5,19,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.822','2026-11-26 00:24:18.822'),(14,5,20,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.831','2026-11-26 00:24:18.831'),(15,5,21,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.840','2026-11-26 00:24:18.840'),(16,5,22,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.849','2026-11-26 00:24:18.849'),(17,5,23,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.859','2026-11-26 00:24:18.859'),(18,5,24,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.868','2026-11-26 00:24:18.868'),(19,6,25,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.898','2026-11-26 00:24:18.898'),(20,6,26,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.907','2026-11-26 00:24:18.907'),(21,6,27,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.916','2026-11-26 00:24:18.916'),(22,6,28,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.925','2026-11-26 00:24:18.925'),(23,6,29,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.936','2026-11-26 00:24:18.936'),(24,6,30,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.946','2026-11-26 00:24:18.946'),(25,7,31,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.977','2026-11-26 00:24:18.977'),(26,7,32,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.986','2026-11-26 00:24:18.986'),(27,7,33,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.995','2026-11-26 00:24:18.995'),(28,7,34,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.004','2026-11-26 00:24:19.004'),(29,7,35,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.020','2026-11-26 00:24:19.020'),(30,7,36,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.029','2026-11-26 00:24:19.029'),(31,8,37,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.055','2026-11-26 00:24:19.055'),(32,8,38,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.064','2026-11-26 00:24:19.064'),(33,8,39,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.073','2026-11-26 00:24:19.073'),(34,8,40,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.081','2026-11-26 00:24:19.081'),(35,8,41,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.090','2026-11-26 00:24:19.090'),(36,8,42,'DEPOSITED','2026-11-26 00:24:18.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.098','2026-11-26 00:24:19.098'),(37,9,43,'DEPOSITED','2026-11-25 19:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.126','2026-11-26 00:24:19.126'),(38,9,44,'DEPOSITED','2026-11-25 19:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.178','2026-11-26 00:24:19.178'),(39,9,45,'DEPOSITED','2026-11-25 19:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.191','2026-11-26 00:24:19.191'),(40,9,46,'DEPOSITED','2026-11-25 19:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.205','2026-11-26 00:24:19.205'),(41,9,47,'DEPOSITED','2026-11-25 19:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.218','2026-11-26 00:24:19.218'),(42,9,48,'DEPOSITED','2026-11-25 19:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.230','2026-11-26 00:24:19.230'),(43,10,49,'DEPOSITED','2026-11-23 20:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.261','2026-11-26 00:24:19.261'),(44,10,50,'DEPOSITED','2026-11-23 20:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.274','2026-11-26 00:24:19.274'),(45,10,51,'DEPOSITED','2026-11-23 20:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.287','2026-11-26 00:24:19.287'),(46,10,52,'DEPOSITED','2026-11-23 20:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.299','2026-11-26 00:24:19.299'),(47,10,53,'DEPOSITED','2026-11-23 20:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.314','2026-11-26 00:24:19.314'),(48,10,54,'DEPOSITED','2026-11-23 20:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.328','2026-11-26 00:24:19.328'),(49,11,7,'DEPOSITED','2026-11-21 14:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.358','2026-11-26 00:24:19.358'),(50,11,8,'DEPOSITED','2026-11-21 14:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.371','2026-11-26 00:24:19.371'),(51,11,9,'DEPOSITED','2026-11-21 14:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.386','2026-11-26 00:24:19.386'),(52,11,55,'DEPOSITED','2026-11-21 14:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.400','2026-11-26 00:24:19.400'),(53,11,56,'DEPOSITED','2026-11-21 14:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.414','2026-11-26 00:24:19.414'),(54,11,10,'DEPOSITED','2026-11-21 14:00:00.000',NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.430','2026-11-26 00:24:19.430');
/*!40000 ALTER TABLE `room_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule_proposals`
--

DROP TABLE IF EXISTS `schedule_proposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedule_proposals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `proposed_by` bigint unsigned NOT NULL,
  `proposed_start_at` datetime(3) NOT NULL,
  `proposed_end_at` datetime(3) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'PROPOSED',
  `active_game_room_id` bigint unsigned GENERATED ALWAYS AS ((case when (`status` = _utf8mb4'PROPOSED') then `game_room_id` else NULL end)) STORED,
  `expires_at` datetime(3) NOT NULL,
  `confirmed_at` datetime(3) DEFAULT NULL,
  `rejected_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_proposal_room_id` (`game_room_id`,`id`),
  UNIQUE KEY `uq_active_schedule_proposal` (`active_game_room_id`),
  KEY `idx_schedule_proposals_room_status` (`game_room_id`,`status`),
  KEY `fk_schedule_proposal_user` (`proposed_by`),
  CONSTRAINT `fk_schedule_proposal_room` FOREIGN KEY (`game_room_id`) REFERENCES `game_rooms` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_schedule_proposal_user` FOREIGN KEY (`proposed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_schedule_proposal_status` CHECK ((`status` in (_utf8mb4'PROPOSED',_utf8mb4'CONFIRMED',_utf8mb4'REJECTED',_utf8mb4'EXPIRED',_utf8mb4'CANCELLED'))),
  CONSTRAINT `chk_schedule_proposal_time` CHECK ((`proposed_start_at` < `proposed_end_at`))
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule_proposals`
--

LOCK TABLES `schedule_proposals` WRITE;
/*!40000 ALTER TABLE `schedule_proposals` DISABLE KEYS */;
INSERT INTO `schedule_proposals` (`id`, `game_room_id`, `proposed_by`, `proposed_start_at`, `proposed_end_at`, `status`, `expires_at`, `confirmed_at`, `rejected_at`, `created_at`) VALUES (1,5,19,'2026-11-27 19:00:00.000','2026-11-27 23:00:00.000','CONFIRMED','2026-11-26 12:24:18.000',NULL,NULL,'2026-11-26 00:24:18.877'),(2,6,25,'2026-11-28 14:00:00.000','2026-11-28 18:00:00.000','CONFIRMED','2026-11-26 12:24:18.000',NULL,NULL,'2026-11-26 00:24:18.954'),(3,7,31,'2026-11-29 20:00:00.000','2026-11-30 00:00:00.000','CONFIRMED','2026-11-26 12:24:18.000',NULL,NULL,'2026-11-26 00:24:19.037'),(4,8,37,'2026-11-30 15:00:00.000','2026-11-30 19:00:00.000','CONFIRMED','2026-11-26 12:24:19.000',NULL,NULL,'2026-11-26 00:24:19.107'),(5,9,43,'2026-11-25 19:00:00.000','2026-11-25 23:00:00.000','CONFIRMED','2026-11-25 19:00:00.000',NULL,NULL,'2026-11-26 00:24:19.243'),(6,10,49,'2026-11-23 20:00:00.000','2026-11-24 00:00:00.000','CONFIRMED','2026-11-23 20:00:00.000',NULL,NULL,'2026-11-26 00:24:19.341'),(7,11,7,'2026-11-21 14:00:00.000','2026-11-21 18:00:00.000','CONFIRMED','2026-11-21 14:00:00.000',NULL,NULL,'2026-11-26 00:24:19.444');
/*!40000 ALTER TABLE `schedule_proposals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule_responses`
--

DROP TABLE IF EXISTS `schedule_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedule_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `schedule_proposal_id` bigint unsigned NOT NULL,
  `room_participant_id` bigint unsigned NOT NULL,
  `response` varchar(20) NOT NULL DEFAULT 'PENDING',
  `responded_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_response_participant` (`schedule_proposal_id`,`room_participant_id`),
  KEY `idx_schedule_responses_room_response` (`game_room_id`,`response`),
  KEY `fk_schedule_response_proposal` (`game_room_id`,`schedule_proposal_id`),
  KEY `fk_schedule_response_participant` (`game_room_id`,`room_participant_id`),
  CONSTRAINT `fk_schedule_response_participant` FOREIGN KEY (`game_room_id`, `room_participant_id`) REFERENCES `room_participants` (`game_room_id`, `id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_schedule_response_proposal` FOREIGN KEY (`game_room_id`, `schedule_proposal_id`) REFERENCES `schedule_proposals` (`game_room_id`, `id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_schedule_response` CHECK ((`response` in (_utf8mb4'ACCEPTED',_utf8mb4'REJECTED',_utf8mb4'PENDING')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule_responses`
--

LOCK TABLES `schedule_responses` WRITE;
/*!40000 ALTER TABLE `schedule_responses` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedule_responses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_room_id` bigint unsigned NOT NULL,
  `schedule_proposal_id` bigint unsigned NOT NULL,
  `dm_assignment_id` bigint unsigned NOT NULL,
  `venue_id` bigint unsigned NOT NULL,
  `physical_room_id` bigint unsigned NOT NULL,
  `start_at` datetime(3) NOT NULL,
  `end_at` datetime(3) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'SCHEDULED',
  `started_at` datetime(3) DEFAULT NULL,
  `completed_at` datetime(3) DEFAULT NULL,
  `cancelled_at` datetime(3) DEFAULT NULL,
  `cancel_reason` text,
  `session_notes` text,
  `incident_report` text,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_game_room` (`game_room_id`),
  UNIQUE KEY `uq_session_schedule_proposal` (`schedule_proposal_id`),
  KEY `idx_sessions_room_status` (`game_room_id`,`status`),
  KEY `idx_sessions_physical_time` (`physical_room_id`,`status`,`start_at`,`end_at`),
  KEY `fk_sessions_proposal` (`game_room_id`,`schedule_proposal_id`),
  KEY `fk_sessions_dm_assignment` (`game_room_id`,`dm_assignment_id`),
  KEY `fk_sessions_physical_room` (`venue_id`,`physical_room_id`),
  CONSTRAINT `fk_sessions_dm_assignment` FOREIGN KEY (`game_room_id`, `dm_assignment_id`) REFERENCES `dm_assignments` (`game_room_id`, `id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_sessions_physical_room` FOREIGN KEY (`venue_id`, `physical_room_id`) REFERENCES `physical_rooms` (`venue_id`, `id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_sessions_proposal` FOREIGN KEY (`game_room_id`, `schedule_proposal_id`) REFERENCES `schedule_proposals` (`game_room_id`, `id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_sessions_status` CHECK ((`status` in (_utf8mb4'SCHEDULED',_utf8mb4'IN_PROGRESS',_utf8mb4'COMPLETED',_utf8mb4'CANCELLED'))),
  CONSTRAINT `chk_sessions_time` CHECK ((`start_at` < `end_at`))
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES (1,5,1,1,1,1,'2026-11-27 19:00:00.000','2026-11-27 23:00:00.000','SCHEDULED',NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.887','2026-11-26 00:24:18.887'),(2,6,2,2,1,2,'2026-11-28 14:00:00.000','2026-11-28 18:00:00.000','SCHEDULED',NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:18.966','2026-11-26 00:24:18.966'),(3,7,3,3,1,3,'2026-11-29 20:00:00.000','2026-11-30 00:00:00.000','SCHEDULED',NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.047','2026-11-26 00:24:19.047'),(4,8,4,4,1,4,'2026-11-30 15:00:00.000','2026-11-30 19:00:00.000','SCHEDULED',NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-26 00:24:19.116','2026-11-26 00:24:19.116'),(5,9,5,5,1,1,'2026-11-25 19:00:00.000','2026-11-25 23:00:00.000','COMPLETED',NULL,NULL,NULL,NULL,'Nhóm tìm ra đúng hung thủ ở phút cuối, không có sự cố.',NULL,'2026-11-26 00:24:19.252','2026-11-26 00:24:19.252'),(6,10,6,6,1,2,'2026-11-23 20:00:00.000','2026-11-24 00:00:00.000','COMPLETED',NULL,NULL,NULL,NULL,'Buổi chơi diễn ra suôn sẻ, khách đánh giá 5 sao.',NULL,'2026-11-26 00:24:19.349','2026-11-26 00:24:19.349'),(7,11,7,7,1,5,'2026-11-21 14:00:00.000','2026-11-21 18:00:00.000','COMPLETED',NULL,NULL,NULL,NULL,'Hoàn thành đúng tiến độ, đạo cụ đầy đủ.',NULL,'2026-11-26 00:24:19.453','2026-11-26 00:24:19.453');
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `student_card_url` varchar(500) DEFAULT NULL,
  `student_card_status` varchar(20) NOT NULL DEFAULT 'NONE',
  `student_verified_at` datetime(3) DEFAULT NULL,
  `student_reject_reason` text,
  `avatar_url` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `email_verified_at` datetime(3) DEFAULT NULL,
  `last_login_at` datetime(3) DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_status` (`role_id`,`status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_users_status` CHECK ((`status` in (_utf8mb4'ACTIVE',_utf8mb4'INACTIVE',_utf8mb4'SUSPENDED')))
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,3,'Nguyễn Xuân Hiếu','nguyenxuanhieu@gmail.com','$2y$12$0bXZJhukCesLVK.cPJv65u9veeVmTlgr/2AU7ULPQEBbdRIlWkA.u','0901000001',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.679','2026-11-26 00:24:17.679'),(2,3,'Lê Nguyễn Nhật Trường','lenguyennhattruong@gmail.com','$2y$12$0bXZJhukCesLVK.cPJv65u9veeVmTlgr/2AU7ULPQEBbdRIlWkA.u','0901000002',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.685','2026-11-26 00:24:17.685'),(3,2,'Nguyễn Phạm Y Huỳnh','nguyenphamyhuynh@gmail.com','$2y$12$2p2Es1WaNGf7y5e2ix1.iOZshxk.yF42MJjo6CdJ4X8khzVQQPE/.','0902000001',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.690','2026-11-26 00:24:17.690'),(4,2,'Lê Hữu Châu','lehuuchau@gmail.com','$2y$12$2p2Es1WaNGf7y5e2ix1.iOZshxk.yF42MJjo6CdJ4X8khzVQQPE/.','0902000002',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.696','2026-11-26 00:24:17.696'),(5,2,'Nguyễn Anh Thư','nguyenanhthu@gmail.com','$2y$12$2p2Es1WaNGf7y5e2ix1.iOZshxk.yF42MJjo6CdJ4X8khzVQQPE/.','0902000003',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.700','2026-11-26 00:24:17.700'),(6,2,'Nguyễn Thị Thảo Như','nguyenthithaonhu@gmail.com','$2y$12$2p2Es1WaNGf7y5e2ix1.iOZshxk.yF42MJjo6CdJ4X8khzVQQPE/.','0902000004',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.704','2026-11-26 00:24:17.704'),(7,1,'Người chơi 01','nguoichoi01@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000001',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.708','2026-11-26 00:24:17.708'),(8,1,'Người chơi 02','nguoichoi02@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000002',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.712','2026-11-26 00:24:17.712'),(9,1,'Người chơi 03','nguoichoi03@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000003',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.716','2026-11-26 00:24:17.716'),(10,1,'Người chơi 04','nguoichoi04@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000004',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.721','2026-11-26 00:24:17.721'),(11,1,'Người chơi 05','nguoichoi05@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000005',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.725','2026-11-26 00:24:17.725'),(12,1,'Người chơi 06','nguoichoi06@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000006',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.729','2026-11-26 00:24:17.729'),(13,1,'Người chơi 07','nguoichoi07@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000007',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.734','2026-11-26 00:24:17.734'),(14,1,'Người chơi 08','nguoichoi08@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000008',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.738','2026-11-26 00:24:17.738'),(15,1,'Người chơi 09','nguoichoi09@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000009',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.742','2026-11-26 00:24:17.742'),(16,1,'Người chơi 10','nguoichoi10@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000010',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.746','2026-11-26 00:24:17.746'),(17,1,'Người chơi 11','nguoichoi11@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000011',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.750','2026-11-26 00:24:17.750'),(18,1,'Người chơi 12','nguoichoi12@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000012',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.754','2026-11-26 00:24:17.754'),(19,1,'Người chơi 13','nguoichoi13@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000013',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.758','2026-11-26 00:24:17.758'),(20,1,'Người chơi 14','nguoichoi14@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000014',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.762','2026-11-26 00:24:17.762'),(21,1,'Người chơi 15','nguoichoi15@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000015',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.767','2026-11-26 00:24:17.767'),(22,1,'Người chơi 16','nguoichoi16@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000016',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.771','2026-11-26 00:24:17.771'),(23,1,'Người chơi 17','nguoichoi17@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000017',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.776','2026-11-26 00:24:17.776'),(24,1,'Người chơi 18','nguoichoi18@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000018',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.780','2026-11-26 00:24:17.780'),(25,1,'Người chơi 19','nguoichoi19@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000019',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.785','2026-11-26 00:24:17.785'),(26,1,'Người chơi 20','nguoichoi20@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000020',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.789','2026-11-26 00:24:17.789'),(27,1,'Người chơi 21','nguoichoi21@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000021',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.793','2026-11-26 00:24:17.793'),(28,1,'Người chơi 22','nguoichoi22@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000022',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.798','2026-11-26 00:24:17.798'),(29,1,'Người chơi 23','nguoichoi23@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000023',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.802','2026-11-26 00:24:17.802'),(30,1,'Người chơi 24','nguoichoi24@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000024',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.810','2026-11-26 00:24:17.810'),(31,1,'Người chơi 25','nguoichoi25@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000025',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.814','2026-11-26 00:24:17.814'),(32,1,'Người chơi 26','nguoichoi26@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000026',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.819','2026-11-26 00:24:17.819'),(33,1,'Người chơi 27','nguoichoi27@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000027',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.823','2026-11-26 00:24:17.823'),(34,1,'Người chơi 28','nguoichoi28@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000028',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.827','2026-11-26 00:24:17.827'),(35,1,'Người chơi 29','nguoichoi29@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000029',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.832','2026-11-26 00:24:17.832'),(36,1,'Người chơi 30','nguoichoi30@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000030',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.836','2026-11-26 00:24:17.836'),(37,1,'Người chơi 31','nguoichoi31@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000031',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.841','2026-11-26 00:24:17.841'),(38,1,'Người chơi 32','nguoichoi32@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000032',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.845','2026-11-26 00:24:17.845'),(39,1,'Người chơi 33','nguoichoi33@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000033',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.850','2026-11-26 00:24:17.850'),(40,1,'Người chơi 34','nguoichoi34@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000034',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.854','2026-11-26 00:24:17.854'),(41,1,'Người chơi 35','nguoichoi35@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000035',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.859','2026-11-26 00:24:17.859'),(42,1,'Người chơi 36','nguoichoi36@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000036',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.863','2026-11-26 00:24:17.863'),(43,1,'Người chơi 37','nguoichoi37@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000037',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.867','2026-11-26 00:24:17.867'),(44,1,'Người chơi 38','nguoichoi38@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000038',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.872','2026-11-26 00:24:17.872'),(45,1,'Người chơi 39','nguoichoi39@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000039',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.876','2026-11-26 00:24:17.876'),(46,1,'Người chơi 40','nguoichoi40@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000040',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.880','2026-11-26 00:24:17.880'),(47,1,'Người chơi 41','nguoichoi41@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000041',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.884','2026-11-26 00:24:17.884'),(48,1,'Người chơi 42','nguoichoi42@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000042',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.889','2026-11-26 00:24:17.889'),(49,1,'Người chơi 43','nguoichoi43@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000043',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.893','2026-11-26 00:24:17.893'),(50,1,'Người chơi 44','nguoichoi44@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000044',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.897','2026-11-26 00:24:17.897'),(51,1,'Người chơi 45','nguoichoi45@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000045',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.901','2026-11-26 00:24:17.901'),(52,1,'Người chơi 46','nguoichoi46@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000046',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.905','2026-11-26 00:24:17.905'),(53,1,'Người chơi 47','nguoichoi47@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000047',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.910','2026-11-26 00:24:17.910'),(54,1,'Người chơi 48','nguoichoi48@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000048',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.914','2026-11-26 00:24:17.914'),(55,1,'Người chơi 49','nguoichoi49@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000049',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.918','2026-11-26 00:24:17.918'),(56,1,'Người chơi 50','nguoichoi50@gmail.com','$2y$12$n9E6UjfGGrYQqQORqse8ZOKuKIydtmYiyOcrXM6pFHL0Z9L09sGJO','0903000050',NULL,'NONE',NULL,NULL,NULL,'ACTIVE',NULL,NULL,'2026-11-26 00:24:17.922','2026-11-26 00:24:17.922');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `venues`
--

DROP TABLE IF EXISTS `venues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `venues` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `address` varchar(500) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `description` text,
  `status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_venues_status` (`status`),
  CONSTRAINT `chk_venues_status` CHECK ((`status` in (_utf8mb4'ACTIVE',_utf8mb4'INACTIVE')))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `venues`
--

LOCK TABLES `venues` WRITE;
/*!40000 ALTER TABLE `venues` DISABLE KEYS */;
INSERT INTO `venues` VALUES (1,'Kịch Bản Sát — Bình Thạnh','36/52 Nguyễn Gia Trí, P.25, Q.Bình Thạnh, TP.HCM','0915690033','Mở cửa 09:00 – 23:00 mỗi ngày. Không gian nhập vai phá án Murder Mystery chuẩn điện ảnh.','ACTIVE','2026-11-26 00:24:17.926','2026-11-26 00:24:17.926');
/*!40000 ALTER TABLE `venues` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'kich_ban_sat_v3'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-26  0:24:19
