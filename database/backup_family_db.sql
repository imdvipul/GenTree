-- MySQL dump 10.13  Distrib 9.7.1, for macos26.4 (arm64)
--
-- Host: localhost    Database: family_db
-- ------------------------------------------------------
-- Server version	9.7.1

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
-- Table structure for table `bill_items`
--

DROP TABLE IF EXISTS `bill_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bill_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bill_id` int unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bill_item` (`bill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bill_items`
--

LOCK TABLES `bill_items` WRITE;
/*!40000 ALTER TABLE `bill_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `bill_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bills`
--

DROP TABLE IF EXISTS `bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bills` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `vendor_name` varchar(255) NOT NULL DEFAULT '',
  `bill_number` varchar(100) DEFAULT NULL,
  `consumer_number` varchar(100) DEFAULT NULL,
  `billing_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('paid','unpaid','overdue') NOT NULL DEFAULT 'unpaid',
  `currency` varchar(10) NOT NULL DEFAULT 'INR',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_bill` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bills`
--

LOCK TABLES `bills` WRITE;
/*!40000 ALTER TABLE `bills` DISABLE KEYS */;
/*!40000 ALTER TABLE `bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_participants`
--

DROP TABLE IF EXISTS `chat_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_participants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_user` (`chat_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_participants`
--

LOCK TABLES `chat_participants` WRITE;
/*!40000 ALTER TABLE `chat_participants` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chats`
--

DROP TABLE IF EXISTS `chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chats` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('direct','group') NOT NULL DEFAULT 'direct',
  `name` varchar(200) DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chats`
--

LOCK TABLES `chats` WRITE;
/*!40000 ALTER TABLE `chats` DISABLE KEYS */;
/*!40000 ALTER TABLE `chats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_suvichars`
--

DROP TABLE IF EXISTS `daily_suvichars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_suvichars` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `thought` text NOT NULL,
  `author` varchar(255) DEFAULT 'Unknown',
  `language` varchar(10) NOT NULL DEFAULT 'en',
  `category` varchar(50) NOT NULL DEFAULT 'General',
  `image_bg` varchar(500) DEFAULT NULL,
  `display_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_suvichars`
--

LOCK TABLES `daily_suvichars` WRITE;
/*!40000 ALTER TABLE `daily_suvichars` DISABLE KEYS */;
INSERT INTO `daily_suvichars` VALUES (1,'Kindness','A small act of kindness can make someone\'s entire day better.','Unknown','en','Kindness',NULL,'2026-08-17',1,'2026-08-16 18:43:49','2026-08-16 18:43:49'),(2,'Motivation','The only way to do great work is to love what you do.','Steve Jobs','en','Motivation',NULL,'2026-08-18',1,'2026-08-16 18:43:49','2026-08-16 18:43:49'),(3,'Unity','Strength lies in differences, not in similarities.','Stephen R. Covey','en','Unity',NULL,NULL,1,'2026-08-16 18:43:49','2026-08-16 18:43:49'),(4,'નમ્રતા','નમ્રતા એ સદ્ગુણોની સુવાસ છે.','ગાંધીજી','gu','Positivity',NULL,'2026-08-17',1,'2026-08-16 18:43:49','2026-08-16 18:43:49'),(5,'એકતા','સંપ ત્યાં જંપ, એકતામાં જ સાચું બળ છે.','લોકવાયકા','gu','Unity',NULL,NULL,1,'2026-08-16 18:43:49','2026-08-16 18:43:49'),(6,'सच्चाई','सत्य कभी पराजित नहीं हो सकता, केवल परेशान हो सकता है।','सत्यवादी','hi','Peace',NULL,'2026-08-17',1,'2026-08-16 18:43:49','2026-08-16 18:43:49'),(7,'अनुशासन','अनुशासन ही देश को महान बनाता है।','विवेकानंद','hi','Discipline',NULL,NULL,1,'2026-08-16 18:43:49','2026-08-16 18:43:49');
/*!40000 ALTER TABLE `daily_suvichars` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_fields`
--

DROP TABLE IF EXISTS `document_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_fields` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_value` text,
  `field_type` varchar(50) NOT NULL DEFAULT 'text',
  `confidence` double NOT NULL DEFAULT '1',
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_field` (`document_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_fields`
--

LOCK TABLES `document_fields` WRITE;
/*!40000 ALTER TABLE `document_fields` DISABLE KEYS */;
INSERT INTO `document_fields` VALUES (1,1,'Document Title','Personal Notes','text',0.85,0,'2026-08-16 21:41:22'),(2,1,'Reference Date','2026-08-17','date',0.8,0,'2026-08-16 21:41:22'),(3,2,'Document Title','Personal Notes','text',0.85,0,'2026-09-06 19:56:32'),(4,2,'Reference Date','2026-09-07','date',0.8,0,'2026-09-06 19:56:32'),(5,3,'Document Title','Personal Notes','text',0.85,0,'2026-10-31 18:11:28'),(6,3,'Reference Date','2026-10-31','date',0.8,0,'2026-10-31 18:11:28');
/*!40000 ALTER TABLE `document_fields` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_reminders`
--

DROP TABLE IF EXISTS `document_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_reminders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `reminder_type` varchar(50) NOT NULL DEFAULT 'due_date',
  `reminder_date` date NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_reminder` (`document_id`),
  KEY `idx_user_reminder` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_reminders`
--

LOCK TABLES `document_reminders` WRITE;
/*!40000 ALTER TABLE `document_reminders` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `document_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `ocr_text` longtext,
  `summary` text,
  `confidence` double NOT NULL DEFAULT '1',
  `isdelete` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_doc` (`user_id`,`isdelete`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,2,'Other Document','Other Document - 1786916066750','uploads/documents/doc_6a822ddf0410a_1786916319.jpg','application/octet-stream',NULL,NULL,'none','GenTree document upload. Scanned on 2026-08-17. Contains generic text and miscellaneous details.','A general scanned document containing uncategorized personal notes.',0.83,0,'2026-08-16 21:41:22','2026-08-16 21:41:22'),(2,2,'Other Document','Other Document - 1788724541886','uploads/documents/doc_6a9dc56484318_1788724580.jpg','application/octet-stream',NULL,NULL,'none','GenTree document upload. Scanned on 2026-09-07. Contains generic text and miscellaneous details.','A general scanned document containing uncategorized personal notes.',0.83,0,'2026-09-06 19:56:31','2026-09-06 19:56:31'),(3,2,'Other Document','Other Document - 1793470225248','uploads/documents/doc_6ae62f49b9f34_1793470281.jpg','application/octet-stream',NULL,NULL,'none','GenTree document upload. Scanned on 2026-10-31. Contains generic text and miscellaneous details.','A general scanned document containing uncategorized personal notes.',0.83,0,'2026-10-31 18:11:28','2026-10-31 18:11:28');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_otps`
--

DROP TABLE IF EXISTS `email_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_otps` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_otps`
--

LOCK TABLES `email_otps` WRITE;
/*!40000 ALTER TABLE `email_otps` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `families`
--

DROP TABLE IF EXISTS `families`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `families` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `bio` text,
  `cover_image` varchar(500) DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `iddelete` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `families`
--

LOCK TABLES `families` WRITE;
/*!40000 ALTER TABLE `families` DISABLE KEYS */;
INSERT INTO `families` VALUES (1,'Parmar Family','My Family','uploads/families/family_6a5e6c75a8a84.jpg',2,0,'2026-07-20 18:44:05');
/*!40000 ALTER TABLE `families` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `family_invites`
--

DROP TABLE IF EXISTS `family_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `family_invites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned NOT NULL,
  `token` varchar(200) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'member',
  `created_by` int unsigned NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_invites`
--

LOCK TABLES `family_invites` WRITE;
/*!40000 ALTER TABLE `family_invites` DISABLE KEYS */;
/*!40000 ALTER TABLE `family_invites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `family_members`
--

DROP TABLE IF EXISTS `family_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `family_members` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `nickname` varchar(100) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `bio` text,
  `avatar_url` varchar(500) DEFAULT NULL,
  `is_default_viewpoint` tinyint(1) NOT NULL DEFAULT '0',
  `iddelete` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_members`
--

LOCK TABLES `family_members` WRITE;
/*!40000 ALTER TABLE `family_members` DISABLE KEYS */;
INSERT INTO `family_members` VALUES (1,NULL,2,'Vipul','Parmar','Babu','male','1991-03-22','Hi...','uploads/family/members/member_1_6ae7807376bbb.jpg',0,0,'2026-07-21 17:51:58'),(2,NULL,2,'Bhanabhai','Parmar','','male','1959-06-01','',NULL,0,0,'2026-07-21 17:52:47'),(3,NULL,2,'Leelaben','Parmar','','female','1974-01-10','','uploads/family/members/member_3_6ae782c9e1831.jpg',0,0,'2026-07-21 17:53:51'),(4,NULL,2,'Rahul','Parmar','Ramesh','male','1992-12-20','','uploads/family/members/member_4_6ae78229392cd.jpg',0,0,'2026-07-21 17:56:06'),(5,NULL,2,'Vanita','Gohil','','female','1989-08-18','','uploads/family/members/member_5_6ae780d35cbe6.jpg',0,0,'2026-07-21 17:58:00'),(6,NULL,2,'Aarti','Parmar','','female','1994-12-20','',NULL,0,0,'2026-07-21 17:59:33'),(7,NULL,2,'Mahipat','Gohil','','male','1977-01-14','','uploads/family/members/member_7_6ae782594838b.jpg',0,0,'2026-07-21 18:00:49'),(8,NULL,2,'Angel','Gohil','','female','2015-03-15','',NULL,0,0,'2026-07-21 18:38:37'),(9,NULL,2,'Valabhai','Parmar','','male','1944-01-01','',NULL,0,0,'2026-07-21 18:40:46'),(10,NULL,2,'Laxmi','Parmar','','female','1948-01-01','',NULL,0,0,'2026-07-21 18:41:28'),(11,NULL,2,'Tanusk','Gohil','','male','2020-01-16','','uploads/family/members/member_11_6ae7818390e2c.jpg',0,0,'2026-07-22 18:12:54'),(12,NULL,2,'Arvindbhai','Parmar','','male','1968-01-11','',NULL,0,0,'2026-08-29 06:02:03'),(13,NULL,2,'Gauriben','Parmar','','female','1973-01-19','','uploads/family/members/member_13_6ae783ad28338.jpg',0,0,'2026-08-29 06:02:58'),(14,NULL,2,'Ashish','Parmar','','male','1995-06-15','',NULL,0,0,'2026-08-29 06:04:08'),(15,NULL,2,'Jay','Parmar','','male','2000-01-22','','uploads/family/members/member_15_6ae78312ccd33.jpg',0,0,'2026-08-29 06:04:53'),(16,NULL,2,'Dharmanshi','Parmar','','female','1997-01-26','',NULL,0,0,'2026-08-29 06:06:06'),(17,NULL,2,'Falguni','Parmar','','female','1998-01-23','',NULL,0,0,'2026-08-29 06:07:01');
/*!40000 ALTER TABLE `family_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `family_memberships`
--

DROP TABLE IF EXISTS `family_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `family_memberships` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'member',
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_family` (`user_id`,`family_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_memberships`
--

LOCK TABLES `family_memberships` WRITE;
/*!40000 ALTER TABLE `family_memberships` DISABLE KEYS */;
INSERT INTO `family_memberships` VALUES (1,1,2,'owner','active','2026-07-20 18:44:05');
/*!40000 ALTER TABLE `family_memberships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `insurance_policies`
--

DROP TABLE IF EXISTS `insurance_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_policies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `policy_type` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `premium_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sum_insured` decimal(12,2) NOT NULL DEFAULT '0.00',
  `renewal_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_insurance` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `insurance_policies`
--

LOCK TABLES `insurance_policies` WRITE;
/*!40000 ALTER TABLE `insurance_policies` DISABLE KEYS */;
/*!40000 ALTER TABLE `insurance_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `life_event_families`
--

DROP TABLE IF EXISTS `life_event_families`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_event_families` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `family_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_family` (`event_id`,`family_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `life_event_families`
--

LOCK TABLES `life_event_families` WRITE;
/*!40000 ALTER TABLE `life_event_families` DISABLE KEYS */;
INSERT INTO `life_event_families` VALUES (1,1,1),(2,2,1),(3,3,1),(4,4,1),(5,5,1),(6,6,1),(7,7,1),(8,8,1),(9,9,1),(10,10,1),(11,11,1),(12,12,1),(13,13,1),(14,14,1),(15,15,1),(16,16,1),(17,17,1);
/*!40000 ALTER TABLE `life_event_families` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `life_event_members`
--

DROP TABLE IF EXISTS `life_event_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_event_members` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `member_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_member` (`event_id`,`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `life_event_members`
--

LOCK TABLES `life_event_members` WRITE;
/*!40000 ALTER TABLE `life_event_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `life_event_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `life_events`
--

DROP TABLE IF EXISTS `life_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `life_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int unsigned DEFAULT NULL,
  `audience_type` varchar(50) NOT NULL DEFAULT 'family',
  `title` varchar(255) NOT NULL,
  `description` text,
  `event_date` date DEFAULT NULL,
  `date_precision` varchar(20) NOT NULL DEFAULT 'day',
  `location` varchar(255) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `emoji` varchar(20) DEFAULT NULL,
  `iddelete` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `life_events`
--

LOCK TABLES `life_events` WRITE;
/*!40000 ALTER TABLE `life_events` DISABLE KEYS */;
INSERT INTO `life_events` VALUES (1,8,'family','Angel\'s Birthday','Celebrating Angel\'s Birthday! 🎂','2015-03-15','day',NULL,NULL,'🎂',0,'2026-07-22 18:59:05'),(2,1,'family','Vipul\'s Birthday','Celebrating Vipul\'s Birthday! 🎂','1991-03-22','day',NULL,NULL,'🎂',0,'2026-07-22 19:11:03'),(3,2,'family','Bhanabhai\'s Birthday','Celebrating Bhanabhai\'s Birthday! 🎂','1959-06-01','day',NULL,NULL,'🎂',0,'2026-07-22 19:11:03'),(4,3,'family','Leelaben\'s Birthday','Celebrating Leelaben\'s Birthday! 🎂','1974-01-10','day',NULL,NULL,'🎂',0,'2026-07-22 19:11:03'),(5,4,'family','Rahul\'s Birthday','Celebrating Rahul\'s Birthday! 🎂','1992-12-20','day',NULL,NULL,'🎂',0,'2026-07-22 19:11:03'),(6,5,'family','Vanita\'s Birthday','Celebrating Vanita\'s Birthday! 🎂','1989-08-18','day',NULL,NULL,'🎂',0,'2026-07-22 19:11:03'),(7,6,'family','Aarti\'s Birthday','Celebrating Aarti\'s Birthday! 🎂','1994-12-20','day',NULL,NULL,'🎂',0,'2026-07-22 19:11:03'),(8,7,'family','Mahipat\'s Birthday','Celebrating Mahipat\'s Birthday! 🎂','1977-01-14','day',NULL,NULL,'🎂',0,'2026-07-22 19:11:03'),(9,9,'family','Valabhai\'s Birthday','Celebrating Valabhai\'s Birthday! 🎂','1944-01-01','day',NULL,NULL,'🎂',0,'2026-07-22 19:11:03'),(10,10,'family','Laxmi\'s Birthday','Celebrating Laxmi\'s Birthday! 🎂','1948-01-01','day',NULL,NULL,'🎂',0,'2026-07-22 19:11:03'),(11,11,'family','Tanusk\'s Birthday','Celebrating Tanusk\'s Birthday! 🎂','2020-01-16','day',NULL,NULL,'🎂',0,'2026-07-22 19:11:03'),(12,12,'family','Arvindbhai\'s Birthday','Celebrating Arvindbhai\'s Birthday! 🎂','1968-01-11','day',NULL,NULL,'🎂',0,'2026-08-29 06:02:03'),(13,13,'family','Gauriben\'s Birthday','Celebrating Gauriben\'s Birthday! 🎂','1973-01-19','day',NULL,NULL,'🎂',0,'2026-08-29 06:02:58'),(14,14,'family','Ashish\'s Birthday','Celebrating Ashish\'s Birthday! 🎂','1995-06-15','day',NULL,NULL,'🎂',0,'2026-08-29 06:04:08'),(15,15,'family','Jay\'s Birthday','Celebrating Jay\'s Birthday! 🎂','2000-01-22','day',NULL,NULL,'🎂',0,'2026-08-29 06:04:53'),(16,16,'family','Dharmanshi\'s Birthday','Celebrating Dharmanshi\'s Birthday! 🎂','1997-01-26','day',NULL,NULL,'🎂',0,'2026-08-29 06:06:06'),(17,17,'family','Falguni\'s Birthday','Celebrating Falguni\'s Birthday! 🎂','1998-01-23','day',NULL,NULL,'🎂',0,'2026-08-29 06:07:01'),(18,1,'everyone','Learned to Ride a Bike','A moment of confidence and freedom. Learning balance, courage, and joy through persistence.','2026-11-05','dateTime',NULL,'uploads/events/event_6ae740f60e3a3.jpg',NULL,0,'2026-11-01 13:36:31');
/*!40000 ALTER TABLE `life_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text,
  `type` varchar(100) DEFAULT NULL,
  `reference_token` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
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
-- Table structure for table `relationships`
--

DROP TABLE IF EXISTS `relationships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `relationships` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `related_person_id` int unsigned NOT NULL,
  `relation_type` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=131 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `relationships`
--

LOCK TABLES `relationships` WRITE;
/*!40000 ALTER TABLE `relationships` DISABLE KEYS */;
INSERT INTO `relationships` VALUES (29,1,10,2,'parent',1,'2026-07-21 18:42:26'),(30,1,2,10,'child',1,'2026-07-21 18:42:26'),(39,1,9,2,'parent',1,'2026-07-21 18:44:06'),(40,1,2,9,'child',1,'2026-07-21 18:44:06'),(41,1,9,10,'spouse',1,'2026-07-21 18:44:06'),(42,1,10,9,'spouse',1,'2026-07-21 18:44:06'),(57,1,9,12,'parent',1,'2026-08-29 06:02:03'),(58,1,12,9,'child',1,'2026-08-29 06:02:03'),(59,1,10,12,'parent',1,'2026-08-29 06:02:03'),(60,1,12,10,'child',1,'2026-08-29 06:02:03'),(63,1,12,14,'parent',1,'2026-08-29 06:04:08'),(64,1,14,12,'child',1,'2026-08-29 06:04:08'),(71,1,12,16,'parent',1,'2026-08-29 06:06:06'),(72,1,16,12,'child',1,'2026-08-29 06:06:06'),(75,1,17,14,'spouse',1,'2026-08-29 06:07:01'),(76,1,14,17,'spouse',1,'2026-08-29 06:07:01'),(79,1,2,1,'parent',1,'2026-11-01 18:09:55'),(80,1,1,2,'child',1,'2026-11-01 18:09:55'),(83,1,2,5,'parent',1,'2026-11-01 18:11:31'),(84,1,5,2,'child',1,'2026-11-01 18:11:31'),(87,1,5,8,'parent',1,'2026-11-01 18:11:31'),(88,1,8,5,'child',1,'2026-11-01 18:11:31'),(93,1,5,11,'parent',1,'2026-11-01 18:14:27'),(94,1,11,5,'child',1,'2026-11-01 18:14:27'),(97,1,2,4,'parent',1,'2026-11-01 18:17:13'),(98,1,4,2,'child',1,'2026-11-01 18:17:13'),(99,1,4,6,'spouse',1,'2026-11-01 18:17:13'),(100,1,6,4,'spouse',1,'2026-11-01 18:17:13'),(101,1,7,8,'parent',1,'2026-11-01 18:18:01'),(102,1,8,7,'child',1,'2026-11-01 18:18:01'),(103,1,7,11,'parent',1,'2026-11-01 18:18:01'),(104,1,11,7,'child',1,'2026-11-01 18:18:01'),(105,1,7,5,'spouse',1,'2026-11-01 18:18:01'),(106,1,5,7,'spouse',1,'2026-11-01 18:18:01'),(107,1,3,1,'parent',1,'2026-11-01 18:19:53'),(108,1,1,3,'child',1,'2026-11-01 18:19:53'),(109,1,3,5,'parent',1,'2026-11-01 18:19:53'),(110,1,5,3,'child',1,'2026-11-01 18:19:53'),(111,1,3,4,'parent',1,'2026-11-01 18:19:53'),(112,1,4,3,'child',1,'2026-11-01 18:19:53'),(113,1,3,2,'spouse',1,'2026-11-01 18:19:53'),(114,1,2,3,'spouse',1,'2026-11-01 18:19:53'),(119,1,12,15,'parent',1,'2026-11-01 18:21:06'),(120,1,15,12,'child',1,'2026-11-01 18:21:06'),(123,1,13,14,'parent',1,'2026-11-01 18:23:41'),(124,1,14,13,'child',1,'2026-11-01 18:23:41'),(125,1,13,16,'parent',1,'2026-11-01 18:23:41'),(126,1,16,13,'child',1,'2026-11-01 18:23:41'),(127,1,13,15,'parent',1,'2026-11-01 18:23:41'),(128,1,15,13,'child',1,'2026-11-01 18:23:41'),(129,1,13,12,'spouse',1,'2026-11-01 18:23:41'),(130,1,12,13,'spouse',1,'2026-11-01 18:23:41');
/*!40000 ALTER TABLE `relationships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `societies`
--

DROP TABLE IF EXISTS `societies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `societies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `societies`
--

LOCK TABLES `societies` WRITE;
/*!40000 ALTER TABLE `societies` DISABLE KEYS */;
INSERT INTO `societies` VALUES (1,'Greenwood Heights','S.G. Highway, Ahmedabad, Gujarat','2026-08-16 18:43:49');
/*!40000 ALTER TABLE `societies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `society_notices`
--

DROP TABLE IF EXISTS `society_notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `society_notices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `society_id` int unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'General',
  `priority` enum('low','medium','high','emergency') NOT NULL DEFAULT 'medium',
  `image_banner` varchar(500) DEFAULT NULL,
  `attachment_url` varchar(500) DEFAULT NULL,
  `target_type` enum('all','building','wing','floor') NOT NULL DEFAULT 'all',
  `target_value` varchar(255) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `society_id` (`society_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `society_notices_ibfk_1` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `society_notices_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `society_notices`
--

LOCK TABLES `society_notices` WRITE;
/*!40000 ALTER TABLE `society_notices` DISABLE KEYS */;
INSERT INTO `society_notices` VALUES (1,1,'🔴 Emergency Water Shutdown','Water supply will remain unavailable from 10:00 AM to 1:00 PM tomorrow due to main pipeline maintenance. Please store sufficient water in advance.','Water','emergency',NULL,NULL,'all',NULL,'2026-08-17','2026-08-18',0,1,2,'2026-08-16 18:43:49','2026-08-16 18:43:49'),(2,1,'📌 Annual General Meeting (AGM)','Dear members, the Annual General Meeting is scheduled for Sunday at 6:00 PM in the Clubhouse. Attendance is mandatory for all unit owners to discuss yearly maintenance audits.','Meeting','high',NULL,NULL,'all',NULL,'2026-08-17','2026-08-22',1,1,2,'2026-08-16 18:43:49','2026-08-16 18:43:49'),(3,1,'🔧 Building A Lift Maintenance','The passenger lift in Building A will be undergoing regular safety checks and servicing today between 2:00 PM and 4:00 PM. Please use the service lift or stairs.','Maintenance','medium',NULL,NULL,'building','A','2026-08-17','2026-08-17',0,1,2,'2026-08-16 18:43:49','2026-08-16 18:43:49'),(4,1,'🎉 Janmashtami Celebration','Get ready for Dahi Handi and cultural celebrations at the Central Lawn on Janmashtami evening starting from 7:00 PM onwards. Prasadam will be served.','Festival','low',NULL,NULL,'all',NULL,'2026-08-17','2026-08-27',0,1,2,'2026-08-16 18:43:49','2026-08-16 18:43:49'),(5,1,'🇮🇳 Independence Day Celebration','Society members are invited to join the flag hoisting ceremony at 8:30 AM on August 15th near the main entrance gates.','Festival','medium',NULL,NULL,'all',NULL,'2026-08-12','2026-08-16',0,1,2,'2026-08-16 18:43:49','2026-08-16 18:43:49');
/*!40000 ALTER TABLE `society_notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `thoughtful_message_likes`
--

DROP TABLE IF EXISTS `thoughtful_message_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `thoughtful_message_likes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_like` (`message_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `thoughtful_message_likes`
--

LOCK TABLES `thoughtful_message_likes` WRITE;
/*!40000 ALTER TABLE `thoughtful_message_likes` DISABLE KEYS */;
/*!40000 ALTER TABLE `thoughtful_message_likes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `thoughtful_message_members`
--

DROP TABLE IF EXISTS `thoughtful_message_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `thoughtful_message_members` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int unsigned NOT NULL,
  `member_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_msg_member` (`message_id`,`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `thoughtful_message_members`
--

LOCK TABLES `thoughtful_message_members` WRITE;
/*!40000 ALTER TABLE `thoughtful_message_members` DISABLE KEYS */;
/*!40000 ALTER TABLE `thoughtful_message_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `thoughtful_messages`
--

DROP TABLE IF EXISTS `thoughtful_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `thoughtful_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family_id` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `message` text NOT NULL,
  `image` varchar(500) DEFAULT NULL,
  `is_for_all_members` tinyint(1) NOT NULL DEFAULT '1',
  `iddelete` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `thoughtful_messages`
--

LOCK TABLES `thoughtful_messages` WRITE;
/*!40000 ALTER TABLE `thoughtful_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `thoughtful_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_member_links`
--

DROP TABLE IF EXISTS `user_member_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_member_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `family_id` int unsigned NOT NULL,
  `member_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_family_member` (`user_id`,`family_id`,`member_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_member_links`
--

LOCK TABLES `user_member_links` WRITE;
/*!40000 ALTER TABLE `user_member_links` DISABLE KEYS */;
INSERT INTO `user_member_links` VALUES (1,2,1,1),(2,2,1,2),(3,2,1,3),(4,2,1,4),(5,2,1,5),(6,2,1,6),(7,2,1,7),(8,2,1,8),(9,2,1,9),(10,2,1,10),(11,2,1,11),(12,2,1,12),(13,2,1,13),(14,2,1,14),(15,2,1,15),(16,2,1,16),(17,2,1,17);
/*!40000 ALTER TABLE `user_member_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `family_id` int unsigned DEFAULT NULL,
  `society_id` int unsigned DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `wing` varchar(50) DEFAULT NULL,
  `floor` varchar(50) DEFAULT NULL,
  `unit_number` varchar(50) DEFAULT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'email',
  `provider_id` varchar(255) DEFAULT NULL,
  `avatar` varchar(500) DEFAULT NULL,
  `fcm_token` text,
  `isdelete` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `society_id` (`society_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`society_id`) REFERENCES `societies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Vipul','Parmar','vipul@test.com','$2y$12$KBptvQ1it4FB8W50gD7CLeT5xxYXlwTUuFyD0DDnkcTzCMl7npkSe','9876543210','member',NULL,NULL,NULL,NULL,NULL,NULL,'email',NULL,NULL,NULL,0,'2026-07-20 18:25:54'),(2,'Vipul','Parmar','imdvipul@gmail.com','$2y$12$3Nynm437uGHqa2Y38suE1.nVCNm.DodpmMYKmgW6zFFTAHNbCvuKS','9722855015','member',NULL,1,'A','B','3','304','google','106814850025159333944',NULL,'diKStOsgTeK2x95dKNzNtU:APA91bETS31LuYeO1VRuhWMmIipNu1ifbUoyQphGsmyioRBBmxdnj9MiL7XUkErM40-V5d1ax5Hp9xV5sLT-lEXuYbfwTrm5nTuGMW5ECqDPAvVTT3PZI88',0,'2026-07-20 18:27:15');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warranties`
--

DROP TABLE IF EXISTS `warranties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warranties` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int unsigned NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `seller` varchar(255) DEFAULT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `status` enum('active','expired') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_warranty` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warranties`
--

LOCK TABLES `warranties` WRITE;
/*!40000 ALTER TABLE `warranties` DISABLE KEYS */;
/*!40000 ALTER TABLE `warranties` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-02  1:06:29
