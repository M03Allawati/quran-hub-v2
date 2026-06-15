-- MySQL dump 10.13  Distrib 8.0.45, for Linux (aarch64)
--
-- Host: localhost    Database: quran_hub
-- ------------------------------------------------------
-- Server version	8.0.45

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
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created_by` int unsigned DEFAULT NULL,
  `mosque_id` int unsigned DEFAULT NULL,
  `title_en` varchar(500) NOT NULL,
  `title_ar` varchar(500) DEFAULT NULL,
  `body_en` text NOT NULL,
  `body_ar` text,
  `audience` enum('all','teachers','parents','students') DEFAULT 'all',
  `send_email` tinyint(1) DEFAULT '1',
  `is_pinned` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `email_sent_count` int DEFAULT '0',
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int unsigned NOT NULL,
  `class_id` int unsigned NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late','excused') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att` (`student_id`,`class_id`,`date`),
  KEY `class_id` (`class_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,2,2,'2026-04-19','present',NULL,10,'2026-04-19 11:25:09'),(2,1,1,'2026-04-22','late',NULL,9,'2026-04-22 16:09:20'),(3,3,1,'2026-04-23','present',NULL,9,'2026-04-23 06:21:23'),(4,1,1,'2026-04-23','present',NULL,9,'2026-04-23 06:21:23');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `action` varchar(200) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int unsigned DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,3,'register','users',3,NULL,'2026-04-13 21:42:24'),(2,4,'register','users',4,NULL,'2026-04-13 21:43:48'),(3,6,'register','users',6,NULL,'2026-04-13 21:46:35'),(4,7,'register','users',7,NULL,'2026-04-13 21:47:48'),(5,8,'register','users',8,NULL,'2026-04-13 21:49:02'),(6,14,'register','users',14,NULL,'2026-04-23 06:09:24'),(7,15,'register','users',15,NULL,'2026-04-27 09:29:44'),(8,16,'register','users',16,NULL,'2026-04-27 09:31:49'),(9,17,'register','users',17,NULL,'2026-04-27 09:37:08');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `badges`
--

DROP TABLE IF EXISTS `badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `badges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name_en` varchar(200) NOT NULL,
  `name_ar` varchar(200) NOT NULL,
  `description_ar` text,
  `icon` varchar(10) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#2D6A4F',
  `category` enum('memorization','attendance','progress','community','milestone') DEFAULT 'milestone',
  `points` smallint unsigned DEFAULT '0',
  `condition_type` varchar(100) DEFAULT NULL,
  `condition_value` int unsigned DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `badges`
--

LOCK TABLES `badges` WRITE;
/*!40000 ALTER TABLE `badges` DISABLE KEYS */;
INSERT INTO `badges` VALUES (6,'First Surah','First Surah','Complete first surah','🌟','#2D6A4F','memorization',10,'surah_completed',1,1),(7,'Perfect Attendance','Perfect Attendance','30 days attendance','✅','#1A6FAB','attendance',50,'streak_30',30,1),(8,'Tajweed Master','Tajweed Master','Level 5 tajweed','🎯','#8B4513','progress',40,'tajweed_5',1,1),(9,'Community Star','Community Star','50 messages sent','��','#9B59B6','community',25,'messages_sent',50,1),(10,'Juz Amma','Juz Amma','Complete Juz 30','🕌','#D4A017','memorization',100,'juz30_complete',1,1);
/*!40000 ALTER TABLE `badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name_en` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` enum('Quran Memorization','Tajweed','Fiqh','Dua & Dhikr','Arabic','Tafsir','Hadith','Memorization','Kids','Recitation','Converts') COLLATE utf8mb4_unicode_ci NOT NULL,
  `teacher_id` int unsigned NOT NULL,
  `mosque_id` int unsigned NOT NULL,
  `level` enum('Beginner','Intermediate','Advanced') COLLATE utf8mb4_unicode_ci DEFAULT 'Beginner',
  `schedule_day` set('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `max_students` tinyint unsigned DEFAULT '20',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `slot` enum('A','B') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `mosque_id` (`mosque_id`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`mosque_id`) REFERENCES `mosques` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,'Quran Memorization','Ø­ÙØ¸ Ø§Ù„Ù‚Ø±Ø¢Ù† Ø§Ù„ÙƒØ±ÙŠÙ…','Quran Memorization',9,1,'Beginner','Sunday,Tuesday,Thursday','16:00:00','17:00:00',20,1,'2026-04-11 20:32:34','A'),(2,'Kids Quran Program','Ø¨Ø±Ù†Ø§Ù…Ø¬ Ø§Ù„Ù‚Ø±Ø¢Ù† Ù„Ù„Ø£Ø·ÙØ§Ù„','Tajweed',10,1,'Beginner','Monday,Wednesday','17:00:00','18:00:00',20,1,'2026-04-11 20:32:34','B'),(10,'Al-Husn Mosque - Program A','مسجد الحصن - البرنامج أ','Quran Memorization',9,2,'Beginner','Sunday','16:00:00','17:00:00',20,1,'2026-04-23 06:13:31','A'),(11,'Al-Amerat Grand Mosque - Program A','مسجد العامرات الكبير - البرنامج أ','Quran Memorization',9,3,'Beginner','Sunday','16:00:00','17:00:00',20,1,'2026-04-23 06:20:45','A');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_participants`
--

DROP TABLE IF EXISTS `conversation_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_participants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role` enum('member','admin') DEFAULT 'member',
  `last_read_at` datetime DEFAULT NULL,
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conv_user` (`conversation_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `conversation_participants_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_participants`
--

LOCK TABLES `conversation_participants` WRITE;
/*!40000 ALTER TABLE `conversation_participants` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('direct','group','community','announcement') NOT NULL DEFAULT 'direct',
  `name_en` varchar(200) DEFAULT NULL,
  `name_ar` varchar(200) DEFAULT NULL,
  `community_type` enum('all_teachers','all_parents','class_group','all_users','admin_broadcast') DEFAULT NULL,
  `class_id` int unsigned DEFAULT NULL,
  `mosque_id` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversations`
--

LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_levels`
--

DROP TABLE IF EXISTS `course_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_levels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `program_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level_number` int NOT NULL,
  `name_en` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_level` (`program_type`,`level_number`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_levels`
--

LOCK TABLES `course_levels` WRITE;
/*!40000 ALTER TABLE `course_levels` DISABLE KEYS */;
INSERT INTO `course_levels` VALUES (1,'Memorization',1,'Juz Amma','جزء عم','Short surahs from Juz 30','السور القصيرة من الجزء الثلاثين',1),(2,'Memorization',2,'Juz Tabarak','جزء تبارك','Juz 29 surahs','سور الجزء التاسع والعشرين',2),(3,'Memorization',3,'Middle Juz','الأجزاء الوسطى','Juz 20-28','الأجزاء من 20 إلى 28',3),(4,'Memorization',4,'Full Quran','حفظ القرآن كاملاً','Complete Quran memorization','حفظ القرآن الكريم كاملاً',4),(5,'Tajweed',1,'Basics','أساسيات التجويد','Noon & Meem rules','أحكام النون والميم',1),(6,'Tajweed',2,'Madd Rules','أحكام المد','Madd and Qasr rules','أحكام المد والقصر',2),(7,'Tajweed',3,'Advanced','التجويد المتقدم','Waqf and Ibtida','الوقف والابتداء',3),(8,'Kids',1,'Letters','الحروف الهجائية','Arabic alphabet recognition','تعلم الحروف الهجائية',1),(9,'Kids',2,'Short Words','الكلمات القصيرة','Simple words and reading','الكلمات البسيطة والقراءة',2),(10,'Kids',3,'Short Surahs','السور القصيرة','Memorize short surahs','حفظ السور القصيرة',3),(11,'Recitation',1,'Basic Reading','القراءة الأساسية','Read with harakat','القراءة بالحركات',1),(12,'Recitation',2,'Fluent Reading','القراءة الطلقة','Read fluently with basic tajweed','القراءة الطلقة مع التجويد الأساسي',2),(13,'Recitation',3,'Applied Tajweed','تطبيق التجويد','Apply tajweed while reading','تطبيق أحكام التجويد في القراءة',3),(14,'Tafseer',1,'Introduction','مقدمة في التفسير','Introduction to Quran sciences','مقدمة في علوم القرآن',1),(15,'Tafseer',2,'Juz Amma Tafseer','تفسير جزء عم','Detailed tafseer of Juz 30','تفسير مفصّل لجزء عم',2),(16,'Tafseer',3,'Deeper Study','الدراسة المعمّقة','Advanced tafseer methodology','منهجية التفسير المتقدمة',3),(17,'Converts',1,'Arabic Letters','الحروف العربية','Learn Arabic alphabet','تعلم الحروف العربية',1),(18,'Converts',2,'Basic Surahs','السور الأساسية','Al-Fatiha & short surahs','الفاتحة والسور القصيرة',2),(19,'Converts',3,'Prayer Surahs','سور الصلاة','Surahs used in prayer','السور المستخدمة في الصلاة',3);
/*!40000 ALTER TABLE `course_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_queue`
--

DROP TABLE IF EXISTS `email_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_queue` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(255) DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `body_html` longtext NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` tinyint unsigned DEFAULT '0',
  `sent_at` datetime DEFAULT NULL,
  `error_msg` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_queue`
--

LOCK TABLES `email_queue` WRITE;
/*!40000 ALTER TABLE `email_queue` DISABLE KEYS */;
INSERT INTO `email_queue` VALUES (1,'teacher_a@gmail.com','Ahmed Teacher','Welcome to Digital Quran Hub','<html><body style=\'font-family:Arial;padding:20px\'>\n<div style=\'max-width:520px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)\'>\n<div style=\'background:linear-gradient(135deg,#1B4332,#40916C);padding:24px;text-align:center;color:#fff\'>\n<h2 style=\'margin:0;color:#F4D03F\'>&#128332; Welcome to Digital Quran Hub!</h2></div>\n<div style=\'padding:24px\'>\n<p>Hello <b>Ahmed Teacher</b>,</p>\n<p>Account created. Username: <b>teacher_a</b></p>\n<p>Role: <b>Teacher</b> &nbsp;|&nbsp; Governorate: <b>Muscat</b></p>\n<a href=\'http://localhost:8080/login.php\' style=\'display:inline-block;background:#2D6A4F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;margin-top:12px\'>Login Now</a>\n</div></div></body></html>','pending',0,NULL,NULL,'2026-04-13 21:42:24'),(2,'teacher_b@gmail.com','Sara Teacher','Welcome to Digital Quran Hub','<html><body style=\'font-family:Arial;padding:20px\'>\n<div style=\'max-width:520px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)\'>\n<div style=\'background:linear-gradient(135deg,#1B4332,#40916C);padding:24px;text-align:center;color:#fff\'>\n<h2 style=\'margin:0;color:#F4D03F\'>&#128332; Welcome to Digital Quran Hub!</h2></div>\n<div style=\'padding:24px\'>\n<p>Hello <b>Sara Teacher</b>,</p>\n<p>Account created. Username: <b>teacher_b</b></p>\n<p>Role: <b>Teacher</b> &nbsp;|&nbsp; Governorate: <b>Muscat</b></p>\n<a href=\'http://localhost:8080/login.php\' style=\'display:inline-block;background:#2D6A4F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;margin-top:12px\'>Login Now</a>\n</div></div></body></html>','pending',0,NULL,NULL,'2026-04-13 21:43:48'),(3,'student1@gmail.com','Omar Student','Welcome to Digital Quran Hub','<html><body style=\'font-family:Arial;padding:20px\'>\n<div style=\'max-width:520px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)\'>\n<div style=\'background:linear-gradient(135deg,#1B4332,#40916C);padding:24px;text-align:center;color:#fff\'>\n<h2 style=\'margin:0;color:#F4D03F\'>&#128332; Welcome to Digital Quran Hub!</h2></div>\n<div style=\'padding:24px\'>\n<p>Hello <b>Omar Student</b>,</p>\n<p>Account created. Username: <b>student1</b></p>\n<p>Role: <b>Student</b> &nbsp;|&nbsp; Governorate: <b>Muscat</b></p>\n<a href=\'http://localhost:8080/login.php\' style=\'display:inline-block;background:#2D6A4F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;margin-top:12px\'>Login Now</a>\n</div></div></body></html>','pending',0,NULL,NULL,'2026-04-13 21:46:35'),(4,'student1@gmail.com','Omar Student','Welcome to Digital Quran Hub','<html><body style=\'font-family:Arial;padding:20px\'>\n<div style=\'max-width:520px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)\'>\n<div style=\'background:linear-gradient(135deg,#1B4332,#40916C);padding:24px;text-align:center;color:#fff\'>\n<h2 style=\'margin:0;color:#F4D03F\'>&#128332; Welcome to Digital Quran Hub!</h2></div>\n<div style=\'padding:24px\'>\n<p>Hello <b>Omar Student</b>,</p>\n<p>Account created. Username: <b>student1</b></p>\n<p>Role: <b>Student</b> &nbsp;|&nbsp; Governorate: <b>Muscat</b></p>\n<a href=\'http://localhost:8080/login.php\' style=\'display:inline-block;background:#2D6A4F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;margin-top:12px\'>Login Now</a>\n</div></div></body></html>','pending',0,NULL,NULL,'2026-04-13 21:47:48'),(5,'parent1@gmail.com','Ali Parent','Welcome to Digital Quran Hub','<html><body style=\'font-family:Arial;padding:20px\'>\n<div style=\'max-width:520px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)\'>\n<div style=\'background:linear-gradient(135deg,#1B4332,#40916C);padding:24px;text-align:center;color:#fff\'>\n<h2 style=\'margin:0;color:#F4D03F\'>&#128332; Welcome to Digital Quran Hub!</h2></div>\n<div style=\'padding:24px\'>\n<p>Hello <b>Ali Parent</b>,</p>\n<p>Account created. Username: <b>parent1</b></p>\n<p>Role: <b>Parent</b> &nbsp;|&nbsp; Governorate: <b>Muscat</b></p>\n<a href=\'http://localhost:8080/login.php\' style=\'display:inline-block;background:#2D6A4F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;margin-top:12px\'>Login Now</a>\n</div></div></body></html>','pending',0,NULL,NULL,'2026-04-13 21:49:02'),(6,'mc@gmail.com','Mc','Welcome to Digital Quran Hub','<html><body style=\'font-family:Arial;padding:20px\'>\n<div style=\'max-width:520px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)\'>\n<div style=\'background:linear-gradient(135deg,#1B4332,#40916C);padding:24px;text-align:center;color:#fff\'>\n<h2 style=\'margin:0;color:#F4D03F\'>&#128332; Welcome to Digital Quran Hub!</h2></div>\n<div style=\'padding:24px\'>\n<p>Hello <b>Mc</b>,</p>\n<p>Account created. Username: <b>mccollege</b></p>\n<p>Role: <b>Student</b> &nbsp;|&nbsp; Governorate: <b>Muscat</b></p>\n<a href=\'http://localhost:8080/login.php\' style=\'display:inline-block;background:#2D6A4F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;margin-top:12px\'>Login Now</a>\n</div></div></body></html>','pending',0,NULL,NULL,'2026-04-23 06:09:24'),(7,'abdulaziz@gmail.com','Abdulazieez','Welcome to Digital Quran Hub','<html><body style=\'font-family:Arial;padding:20px\'>\n<div style=\'max-width:520px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)\'>\n<div style=\'background:linear-gradient(135deg,#1B4332,#40916C);padding:24px;text-align:center;color:#fff\'>\n<h2 style=\'margin:0;color:#F4D03F\'>&#128332; Welcome to Digital Quran Hub!</h2></div>\n<div style=\'padding:24px\'>\n<p>Hello <b>Abdulazieez</b>,</p>\n<p>Account created. Username: <b>student2</b></p>\n<p>Role: <b>Student</b> &nbsp;|&nbsp; Governorate: <b>Muscat</b></p>\n<a href=\'http://localhost:8080/login.php\' style=\'display:inline-block;background:#2D6A4F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;margin-top:12px\'>Login Now</a>\n</div></div></body></html>','pending',0,NULL,NULL,'2026-04-27 09:29:44'),(8,'azooz@gmail.com','Azooz','Welcome to Digital Quran Hub','<html><body style=\'font-family:Arial;padding:20px\'>\n<div style=\'max-width:520px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)\'>\n<div style=\'background:linear-gradient(135deg,#1B4332,#40916C);padding:24px;text-align:center;color:#fff\'>\n<h2 style=\'margin:0;color:#F4D03F\'>&#128332; Welcome to Digital Quran Hub!</h2></div>\n<div style=\'padding:24px\'>\n<p>Hello <b>Azooz</b>,</p>\n<p>Account created. Username: <b>student4</b></p>\n<p>Role: <b>Student</b> &nbsp;|&nbsp; Governorate: <b>Muscat</b></p>\n<a href=\'http://localhost:8080/login.php\' style=\'display:inline-block;background:#2D6A4F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;margin-top:12px\'>Login Now</a>\n</div></div></body></html>','pending',0,NULL,NULL,'2026-04-27 09:31:49'),(9,'abood@gmail.com','abood','Welcome to Digital Quran Hub','<html><body style=\'font-family:Arial;padding:20px\'>\n<div style=\'max-width:520px;margin:0 auto;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.1)\'>\n<div style=\'background:linear-gradient(135deg,#1B4332,#40916C);padding:24px;text-align:center;color:#fff\'>\n<h2 style=\'margin:0;color:#F4D03F\'>&#128332; Welcome to Digital Quran Hub!</h2></div>\n<div style=\'padding:24px\'>\n<p>Hello <b>abood</b>,</p>\n<p>Account created. Username: <b>parent2</b></p>\n<p>Role: <b>Parent</b> &nbsp;|&nbsp; Governorate: <b>Muscat</b></p>\n<a href=\'http://localhost:8080/login.php\' style=\'display:inline-block;background:#2D6A4F;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;margin-top:12px\'>Login Now</a>\n</div></div></body></html>','pending',0,NULL,NULL,'2026-04-27 09:37:08');
/*!40000 ALTER TABLE `email_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int unsigned NOT NULL,
  `class_id` int unsigned NOT NULL,
  `enrolled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','dropped','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enroll` (`student_id`,`class_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (1,1,1,'2026-04-16 16:38:08','active'),(2,2,2,'2026-04-16 16:38:20','dropped'),(3,3,1,'2026-04-23 06:09:24','active'),(4,4,2,'2026-04-24 12:15:02','dropped'),(5,5,1,'2026-04-27 09:29:44','active'),(6,6,1,'2026-04-27 09:31:49','active'),(7,7,2,'2026-04-27 09:38:40','active');
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fixed_courses`
--

DROP TABLE IF EXISTS `fixed_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fixed_courses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slot` enum('A','B') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` enum('student','child') COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `description_ar` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 0xF09F9396,
  `level_number` int DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `is_active` tinyint DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fixed_courses`
--

LOCK TABLES `fixed_courses` WRITE;
/*!40000 ALTER TABLE `fixed_courses` DISABLE KEYS */;
INSERT INTO `fixed_courses` VALUES (1,'A','student','Juz Amma Memorization','حفظ جزء عم','Memorize the 30th Juz short surahs with tajweed','حفظ سور الجزء الثلاثين مع التجويد','📖',1,1,1),(2,'A','student','Tajweed Basics','أساسيات التجويد','Learn Noon Sakinah, Meem Sakinah and Madd rules','تعلم أحكام النون الساكنة والميم الساكنة والمد','🎵',1,2,1),(3,'A','student','Fluent Recitation','التلاوة الطلقة','Read Quran fluently with basic tajweed rules applied','قراءة القرآن بطلاقة مع تطبيق أحكام التجويد الأساسية','🔊',2,3,1),(4,'A','student','Juz Tabarak Memorization','حفظ جزء تبارك','Memorize surahs from Juz 29','حفظ سور الجزء التاسع والعشرين','📖',2,4,1),(5,'A','student','Advanced Tajweed','التجويد المتقدم','Waqf, Ibtida and advanced recitation rules','الوقف والابتداء وأحكام التلاوة المتقدمة','🎙️',3,5,1),(6,'A','student','Tafseer Introduction','مقدمة في التفسير','Introduction to Quran sciences and basic tafseer','مقدمة في علوم القرآن والتفسير الأساسي','📚',3,6,1),(7,'A','student','Quran for Converts','القرآن للمسلمين الجدد','Special program for new Muslims learning to read','برنامج خاص للمسلمين الجدد لتعلم القراءة','🌙',1,7,1),(8,'B','child','Arabic Letters','الحروف الهجائية','Learn and recognize all Arabic alphabet letters','تعلم والتعرف على جميع حروف الأبجدية العربية','🔤',1,1,1),(9,'B','child','Short Words & Reading','الكلمات القصيرة والقراءة','Read simple Arabic words and short sentences','قراءة الكلمات العربية البسيطة والجمل القصيرة','📝',1,2,1),(10,'B','child','Al-Fatihah & Short Surahs','الفاتحة والسور القصيرة','Memorize Al-Fatihah and last 10 surahs of Quran','حفظ الفاتحة وآخر عشر سور من القرآن','🌟',2,3,1),(11,'B','child','Juz Amma for Kids','جزء عم للأطفال','Fun memorization of Juz 30 surahs for children','حفظ ممتع لسور الجزء الثلاثين للأطفال','👶',2,4,1),(12,'B','child','Basic Tajweed for Kids','تجويد الأطفال الأساسي','Simple tajweed rules explained for children','أحكام التجويد البسيطة شرح مبسط للأطفال','🎵',3,5,1),(13,'B','child','Quran Stories & Values','قصص القرآن والقيم','Quranic stories and Islamic values for children','قصص القرآن الكريم والقيم الإسلامية للأطفال','📖',3,6,1);
/*!40000 ALTER TABLE `fixed_courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `governorates`
--

DROP TABLE IF EXISTS `governorates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `governorates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capital_en` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capital_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `wilayat_count` tinyint unsigned DEFAULT '0',
  `code` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `governorates`
--

LOCK TABLES `governorates` WRITE;
/*!40000 ALTER TABLE `governorates` DISABLE KEYS */;
/*!40000 ALTER TABLE `governorates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_plans`
--

DROP TABLE IF EXISTS `lesson_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_plans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `program_id` int unsigned NOT NULL,
  `teacher_id` int unsigned NOT NULL,
  `level_id` int unsigned DEFAULT NULL,
  `plan_type` enum('monthly','weekly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `title_en` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_ar` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','active','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `program_id` (`program_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `lesson_plans_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `mosque_programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_plans_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_plans`
--

LOCK TABLES `lesson_plans` WRITE;
/*!40000 ALTER TABLE `lesson_plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `lesson_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int unsigned NOT NULL,
  `sender_id` int unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `msg_type` enum('text','image','file','system') DEFAULT 'text',
  `reply_to_id` int unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`),
  KEY `sender_id` (`sender_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mosque_programs`
--

DROP TABLE IF EXISTS `mosque_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mosque_programs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mosque_id` int unsigned NOT NULL,
  `template_id` int unsigned DEFAULT NULL,
  `name_en` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `program_type` enum('Quran Memorization','Tajweed','Arabic','Tafsir','Fiqh','Dua & Dhikr','Hadith','Memorization','Recitation','Kids','Converts','Custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Quran Memorization',
  `slot` enum('A','B','private') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'A',
  `target_type` enum('student','child','both') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `days` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `teacher_id` int unsigned DEFAULT NULL,
  `max_students` tinyint unsigned DEFAULT '20',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mosque_slot` (`mosque_id`,`slot`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `mosque_programs_ibfk_1` FOREIGN KEY (`mosque_id`) REFERENCES `mosques` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mosque_programs_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=264 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mosque_programs`
--

LOCK TABLES `mosque_programs` WRITE;
/*!40000 ALTER TABLE `mosque_programs` DISABLE KEYS */;
INSERT INTO `mosque_programs` VALUES (1,1,NULL,'Quran Memorization','حفظ القرآن','Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',9,20,1,'2026-04-11 22:01:14'),(2,2,NULL,'Al-Husn Mosque - Program A','مسجد الحصن - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',9,20,1,'2026-04-11 22:01:14'),(3,3,NULL,'Al-Amerat Grand Mosque - Program A','مسجد العامرات الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',9,20,1,'2026-04-11 22:01:14'),(4,4,NULL,'Ruwi Mosque - Program A','مسجد الروي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(5,5,NULL,'Al-Khuwair Mosque - Program A','مسجد الخوير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(6,6,NULL,'Sultan Taimur Mosque - Program A','جامع السلطان تيمور - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(7,7,NULL,'Al Zulfah Mosque - Program A','جامع الزلفى - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(8,8,NULL,'Al Seeb Grand Mosque - Program A','جامع السيب الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(9,9,NULL,'Muttrah Grand Mosque - Program A','جامع مطرح الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(10,10,NULL,'Al Khor Old Mosque - Program A','جامع الخور القديم - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(11,11,NULL,'Qurayyat Mosque - Program A','جامع قريات الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(12,12,NULL,'Al Amerat Mosque - Program A','جامع العامرات - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(13,13,NULL,'Wadi Kabir Mosque - Program A','جامع وادي كبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(14,14,NULL,'Saeed Bin Taimur Mosque - Program A','جامع صيد بن تيمور - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(15,15,NULL,'Al Ghubrah Mosque - Program A','جامع الغبرة - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(16,16,NULL,'Nizwa Grand Mosque - Program A','جامع نزوى الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(17,17,NULL,'Al Khor Mosque Nizwa - Program A','جامع الخور نزوى - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(18,18,NULL,'Bahla Grand Mosque - Program A','جامع بهلاء الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(19,19,NULL,'Bahla Old Mosque - Program A','مسجد بهلاء الأثري - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(20,20,NULL,'Samail Grand Mosque - Program A','جامع سمائل الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(21,21,NULL,'Izki Grand Mosque - Program A','جامع إزكي الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(22,22,NULL,'Manah Ancient Mosque - Program A','جامع منح الأثري - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(23,23,NULL,'Adam Grand Mosque - Program A','جامع آدم الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(24,24,NULL,'Al Hamra Mosque - Program A','جامع الحمراء - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(25,25,NULL,'Bid Bid Mosque - Program A','جامع بدبد - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(26,26,NULL,'Sultan Qaboos Mosque Sohar - Program A','جامع السلطان قابوس صحار - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(27,27,NULL,'Sohar Ancient Mosque - Program A','جامع صحار القديم - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(28,28,NULL,'Shinas Grand Mosque - Program A','جامع شناص الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(29,29,NULL,'Liwa Central Mosque - Program A','جامع لوى المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(30,30,NULL,'Saham Grand Mosque - Program A','جامع صحم الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(31,31,NULL,'Al Khaburah Mosque - Program A','جامع الخابورة - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(32,32,NULL,'Al Suwaiq Mosque - Program A','جامع السويق - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(33,33,NULL,'Sohar Al Falaj Mosque - Program A','جامع الفلج صحار - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(34,34,NULL,'Sohar Corniche Mosque - Program A','جامع كورنيش صحار - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(35,35,NULL,'Shinas Coastal Mosque - Program A','جامع شناص الساحلي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(36,36,NULL,'Rustaq Grand Mosque - Program A','جامع الرستاق الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(37,37,NULL,'Nakhal Fortress Mosque - Program A','جامع نخل الأثري - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(38,38,NULL,'Barka Grand Mosque - Program A','جامع بركاء الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(39,39,NULL,'Awabi Mosque - Program A','جامع عوابي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(40,40,NULL,'Al Masnaah Mosque - Program A','جامع المصنعة - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(41,41,NULL,'Rustaq Old Mosque - Program A','جامع الرستاق القديم - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(42,42,NULL,'Wadi Maawil Mosque - Program A','جامع وادي المعاول - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(43,43,NULL,'Barka Al Falaj Mosque - Program A','جامع الفلج بركاء - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(44,44,NULL,'Nakhal Valley Mosque - Program A','جامع وادي نخل - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(45,45,NULL,'Rustaq Fort Mosque - Program A','جامع قلعة الرستاق - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(46,46,NULL,'Ibra Grand Mosque - Program A','جامع إبراء الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(47,47,NULL,'Mudaybi Mosque - Program A','جامع المضيبي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(48,48,NULL,'Sinaw Grand Mosque - Program A','جامع سناو الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(49,49,NULL,'Al Qabil Mosque - Program A','جامع القابل - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(50,50,NULL,'Dima Mosque - Program A','جامع دما - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(51,51,NULL,'Ibra Old Mosque - Program A','جامع إبراء القديم - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(52,52,NULL,'Sinaw Heritage Mosque - Program A','جامع سناو التراثي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(53,53,NULL,'Mudaybi Central Mosque - Program A','جامع المضيبي المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(54,54,NULL,'Al Mudhaibi Mosque - Program A','جامع المضيبي الكبرى - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(55,55,NULL,'Ibra Al Khoud Mosque - Program A','جامع الخود إبراء - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(56,56,NULL,'Sur Grand Mosque - Program A','جامع صور الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(57,57,NULL,'Al Ayjah Heritage Mosque - Program A','جامع العيجة التراثي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(58,58,NULL,'Jalan BBA Mosque - Program A','جامع جعلان بني بو علي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(59,59,NULL,'Masirah Island Mosque - Program A','جامع جزيرة مصيرة - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(60,60,NULL,'Al Kamil Mosque - Program A','جامع الكامل - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(61,61,NULL,'Tiwi Coastal Mosque - Program A','جامع طيوي الساحلي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(62,62,NULL,'Sur Corniche Mosque - Program A','جامع كورنيش صور - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(63,63,NULL,'Jalan BBH Mosque - Program A','جامع جعلان بني بو حسن - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(64,64,NULL,'Sur Old Mosque - Program A','جامع صور القديم - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(65,65,NULL,'Wadi Bani Khalid Mosque - Program A','جامع وادي بني خالد - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(66,66,NULL,'Ibri Grand Mosque - Program A','جامع عبري الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(67,67,NULL,'Yanqul Mosque - Program A','جامع ينقل الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(68,68,NULL,'Dank Grand Mosque - Program A','جامع ضنك الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(69,69,NULL,'Ibri Old Mosque - Program A','جامع عبري القديم - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(70,70,NULL,'Yanqul Central Mosque - Program A','جامع ينقل المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(71,71,NULL,'Ibri Al Falaj Mosque - Program A','جامع الفلج عبري - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(72,72,NULL,'Dank Heritage Mosque - Program A','جامع ضنك التراثي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(73,73,NULL,'Ibri New Mosque - Program A','جامع عبري الجديد - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(74,74,NULL,'Yanqul Valley Mosque - Program A','جامع وادي ينقل - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(75,75,NULL,'Dank Central Mosque - Program A','جامع ضنك المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(76,76,NULL,'Sultan Qaboos Mosque Salalah - Program A','جامع السلطان قابوس صلالة - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(77,77,NULL,'Al Uyoun Mosque - Program A','جامع العيون صلالة - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(78,78,NULL,'Nabi Ayoub Mosque - Program A','جامع النبي أيوب - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(79,79,NULL,'Thumrait Mosque - Program A','جامع ثمريت المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(80,80,NULL,'Taqah Heritage Mosque - Program A','جامع طاقة التراثي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(81,81,NULL,'Mirbat Grand Mosque - Program A','جامع مرباط الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(82,82,NULL,'Sadah Mosque - Program A','جامع سدح - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(83,83,NULL,'Salalah Al Hafah Mosque - Program A','جامع الحافة صلالة - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(84,84,NULL,'Salalah New Mosque - Program A','جامع صلالة الجديد - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(85,85,NULL,'Thumrait Village Mosque - Program A','جامع قرية ثمريت - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(86,86,NULL,'Al Buraymi Grand Mosque - Program A','جامع البريمي الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(87,87,NULL,'Mahdha Central Mosque - Program A','جامع محضة المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(88,88,NULL,'As Sinainah Mosque - Program A','جامع السنينة - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(89,89,NULL,'Al Buraymi Old Mosque - Program A','جامع البريمي القديم - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(90,90,NULL,'Al Buraymi New Mosque - Program A','جامع البريمي الجديد - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(91,91,NULL,'Mahdha Village Mosque - Program A','جامع قرية محضة - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(92,92,NULL,'Al Buraymi Central Mosque - Program A','جامع البريمي المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(93,93,NULL,'Sinainah Village Mosque - Program A','جامع قرية السنينة - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(94,94,NULL,'Al Buraymi Friday Mosque - Program A','جامع الجمعة البريمي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(95,95,NULL,'Buraymi Heritage Mosque - Program A','جامع البريمي التراثي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(96,96,NULL,'Khasab Grand Mosque - Program A','جامع خصب الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(97,97,NULL,'Bukha Mosque - Program A','جامع بخاء - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(98,98,NULL,'Dibba Mosque - Program A','جامع دبا - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(99,99,NULL,'Madha Mosque - Program A','جامع مضحى - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(100,100,NULL,'Khasab Old Mosque - Program A','جامع خصب القديم - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(101,101,NULL,'Khasab Waterfront Mosque - Program A','جامع كورنيش خصب - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(102,102,NULL,'Bukha Central Mosque - Program A','جامع بخاء المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(103,103,NULL,'Dibba Central Mosque - Program A','جامع دبا المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(104,104,NULL,'Khasab New Mosque - Program A','جامع خصب الجديد - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(105,105,NULL,'Madha Central Mosque - Program A','جامع مضحى المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(106,106,NULL,'Haima Grand Mosque - Program A','جامع هيماء الكبير - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(107,107,NULL,'Duqm Central Mosque - Program A','جامع الدقم المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(108,108,NULL,'Mahout Mosque - Program A','جامع محوت - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(109,109,NULL,'Al Jazir Mosque - Program A','جامع الجازر - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(110,110,NULL,'Haima New Mosque - Program A','جامع هيماء الجديد - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(111,111,NULL,'Duqm Port Mosque - Program A','جامع ميناء الدقم - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(112,112,NULL,'Haima Central Mosque - Program A','جامع هيماء المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(113,113,NULL,'Duqm New Mosque - Program A','جامع الدقم الجديد - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(114,114,NULL,'Mahout Village Mosque - Program A','جامع قرية محوت - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(115,115,NULL,'Al Wusta Central Mosque - Program A','جامع الوسطى المركزي - البرنامج أ','Quran Memorization','A','student','Sunday,Tuesday,Thursday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(128,1,NULL,'Kids Quran Program - Slot B','برنامج التجويد - ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',10,20,1,'2026-04-11 22:01:14'),(129,2,NULL,'Al-Husn Mosque - Program B','مسجد الحصن - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(130,3,NULL,'Al-Amerat Grand Mosque - Program B','مسجد العامرات الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(131,4,NULL,'Ruwi Mosque - Program B','مسجد الروي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(132,5,NULL,'Al-Khuwair Mosque - Program B','مسجد الخوير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(133,6,NULL,'Sultan Taimur Mosque - Program B','جامع السلطان تيمور - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(134,7,NULL,'Al Zulfah Mosque - Program B','جامع الزلفى - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(135,8,NULL,'Al Seeb Grand Mosque - Program B','جامع السيب الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(136,9,NULL,'Muttrah Grand Mosque - Program B','جامع مطرح الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(137,10,NULL,'Al Khor Old Mosque - Program B','جامع الخور القديم - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(138,11,NULL,'Qurayyat Mosque - Program B','جامع قريات الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(139,12,NULL,'Al Amerat Mosque - Program B','جامع العامرات - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(140,13,NULL,'Wadi Kabir Mosque - Program B','جامع وادي كبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(141,14,NULL,'Saeed Bin Taimur Mosque - Program B','جامع صيد بن تيمور - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(142,15,NULL,'Al Ghubrah Mosque - Program B','جامع الغبرة - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(143,16,NULL,'Nizwa Grand Mosque - Program B','جامع نزوى الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(144,17,NULL,'Al Khor Mosque Nizwa - Program B','جامع الخور نزوى - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(145,18,NULL,'Bahla Grand Mosque - Program B','جامع بهلاء الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(146,19,NULL,'Bahla Old Mosque - Program B','مسجد بهلاء الأثري - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(147,20,NULL,'Samail Grand Mosque - Program B','جامع سمائل الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(148,21,NULL,'Izki Grand Mosque - Program B','جامع إزكي الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(149,22,NULL,'Manah Ancient Mosque - Program B','جامع منح الأثري - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(150,23,NULL,'Adam Grand Mosque - Program B','جامع آدم الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(151,24,NULL,'Al Hamra Mosque - Program B','جامع الحمراء - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(152,25,NULL,'Bid Bid Mosque - Program B','جامع بدبد - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(153,26,NULL,'Sultan Qaboos Mosque Sohar - Program B','جامع السلطان قابوس صحار - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(154,27,NULL,'Sohar Ancient Mosque - Program B','جامع صحار القديم - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(155,28,NULL,'Shinas Grand Mosque - Program B','جامع شناص الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(156,29,NULL,'Liwa Central Mosque - Program B','جامع لوى المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(157,30,NULL,'Saham Grand Mosque - Program B','جامع صحم الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(158,31,NULL,'Al Khaburah Mosque - Program B','جامع الخابورة - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(159,32,NULL,'Al Suwaiq Mosque - Program B','جامع السويق - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(160,33,NULL,'Sohar Al Falaj Mosque - Program B','جامع الفلج صحار - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(161,34,NULL,'Sohar Corniche Mosque - Program B','جامع كورنيش صحار - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(162,35,NULL,'Shinas Coastal Mosque - Program B','جامع شناص الساحلي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(163,36,NULL,'Rustaq Grand Mosque - Program B','جامع الرستاق الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(164,37,NULL,'Nakhal Fortress Mosque - Program B','جامع نخل الأثري - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(165,38,NULL,'Barka Grand Mosque - Program B','جامع بركاء الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(166,39,NULL,'Awabi Mosque - Program B','جامع عوابي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(167,40,NULL,'Al Masnaah Mosque - Program B','جامع المصنعة - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(168,41,NULL,'Rustaq Old Mosque - Program B','جامع الرستاق القديم - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(169,42,NULL,'Wadi Maawil Mosque - Program B','جامع وادي المعاول - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(170,43,NULL,'Barka Al Falaj Mosque - Program B','جامع الفلج بركاء - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(171,44,NULL,'Nakhal Valley Mosque - Program B','جامع وادي نخل - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(172,45,NULL,'Rustaq Fort Mosque - Program B','جامع قلعة الرستاق - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(173,46,NULL,'Ibra Grand Mosque - Program B','جامع إبراء الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(174,47,NULL,'Mudaybi Mosque - Program B','جامع المضيبي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(175,48,NULL,'Sinaw Grand Mosque - Program B','جامع سناو الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(176,49,NULL,'Al Qabil Mosque - Program B','جامع القابل - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(177,50,NULL,'Dima Mosque - Program B','جامع دما - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(178,51,NULL,'Ibra Old Mosque - Program B','جامع إبراء القديم - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(179,52,NULL,'Sinaw Heritage Mosque - Program B','جامع سناو التراثي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(180,53,NULL,'Mudaybi Central Mosque - Program B','جامع المضيبي المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(181,54,NULL,'Al Mudhaibi Mosque - Program B','جامع المضيبي الكبرى - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(182,55,NULL,'Ibra Al Khoud Mosque - Program B','جامع الخود إبراء - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(183,56,NULL,'Sur Grand Mosque - Program B','جامع صور الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(184,57,NULL,'Al Ayjah Heritage Mosque - Program B','جامع العيجة التراثي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(185,58,NULL,'Jalan BBA Mosque - Program B','جامع جعلان بني بو علي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(186,59,NULL,'Masirah Island Mosque - Program B','جامع جزيرة مصيرة - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(187,60,NULL,'Al Kamil Mosque - Program B','جامع الكامل - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(188,61,NULL,'Tiwi Coastal Mosque - Program B','جامع طيوي الساحلي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(189,62,NULL,'Sur Corniche Mosque - Program B','جامع كورنيش صور - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(190,63,NULL,'Jalan BBH Mosque - Program B','جامع جعلان بني بو حسن - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(191,64,NULL,'Sur Old Mosque - Program B','جامع صور القديم - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(192,65,NULL,'Wadi Bani Khalid Mosque - Program B','جامع وادي بني خالد - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(193,66,NULL,'Ibri Grand Mosque - Program B','جامع عبري الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(194,67,NULL,'Yanqul Mosque - Program B','جامع ينقل الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(195,68,NULL,'Dank Grand Mosque - Program B','جامع ضنك الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(196,69,NULL,'Ibri Old Mosque - Program B','جامع عبري القديم - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(197,70,NULL,'Yanqul Central Mosque - Program B','جامع ينقل المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(198,71,NULL,'Ibri Al Falaj Mosque - Program B','جامع الفلج عبري - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(199,72,NULL,'Dank Heritage Mosque - Program B','جامع ضنك التراثي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(200,73,NULL,'Ibri New Mosque - Program B','جامع عبري الجديد - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(201,74,NULL,'Yanqul Valley Mosque - Program B','جامع وادي ينقل - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(202,75,NULL,'Dank Central Mosque - Program B','جامع ضنك المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(203,76,NULL,'Sultan Qaboos Mosque Salalah - Program B','جامع السلطان قابوس صلالة - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(204,77,NULL,'Al Uyoun Mosque - Program B','جامع العيون صلالة - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(205,78,NULL,'Nabi Ayoub Mosque - Program B','جامع النبي أيوب - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(206,79,NULL,'Thumrait Mosque - Program B','جامع ثمريت المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(207,80,NULL,'Taqah Heritage Mosque - Program B','جامع طاقة التراثي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(208,81,NULL,'Mirbat Grand Mosque - Program B','جامع مرباط الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(209,82,NULL,'Sadah Mosque - Program B','جامع سدح - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(210,83,NULL,'Salalah Al Hafah Mosque - Program B','جامع الحافة صلالة - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(211,84,NULL,'Salalah New Mosque - Program B','جامع صلالة الجديد - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(212,85,NULL,'Thumrait Village Mosque - Program B','جامع قرية ثمريت - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(213,86,NULL,'Al Buraymi Grand Mosque - Program B','جامع البريمي الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(214,87,NULL,'Mahdha Central Mosque - Program B','جامع محضة المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(215,88,NULL,'As Sinainah Mosque - Program B','جامع السنينة - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(216,89,NULL,'Al Buraymi Old Mosque - Program B','جامع البريمي القديم - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(217,90,NULL,'Al Buraymi New Mosque - Program B','جامع البريمي الجديد - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(218,91,NULL,'Mahdha Village Mosque - Program B','جامع قرية محضة - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(219,92,NULL,'Al Buraymi Central Mosque - Program B','جامع البريمي المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(220,93,NULL,'Sinainah Village Mosque - Program B','جامع قرية السنينة - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(221,94,NULL,'Al Buraymi Friday Mosque - Program B','جامع الجمعة البريمي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(222,95,NULL,'Buraymi Heritage Mosque - Program B','جامع البريمي التراثي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(223,96,NULL,'Khasab Grand Mosque - Program B','جامع خصب الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(224,97,NULL,'Bukha Mosque - Program B','جامع بخاء - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(225,98,NULL,'Dibba Mosque - Program B','جامع دبا - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(226,99,NULL,'Madha Mosque - Program B','جامع مضحى - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(227,100,NULL,'Khasab Old Mosque - Program B','جامع خصب القديم - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(228,101,NULL,'Khasab Waterfront Mosque - Program B','جامع كورنيش خصب - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(229,102,NULL,'Bukha Central Mosque - Program B','جامع بخاء المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(230,103,NULL,'Dibba Central Mosque - Program B','جامع دبا المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(231,104,NULL,'Khasab New Mosque - Program B','جامع خصب الجديد - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(232,105,NULL,'Madha Central Mosque - Program B','جامع مضحى المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(233,106,NULL,'Haima Grand Mosque - Program B','جامع هيماء الكبير - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(234,107,NULL,'Duqm Central Mosque - Program B','جامع الدقم المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(235,108,NULL,'Mahout Mosque - Program B','جامع محوت - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(236,109,NULL,'Al Jazir Mosque - Program B','جامع الجازر - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(237,110,NULL,'Haima New Mosque - Program B','جامع هيماء الجديد - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(238,111,NULL,'Duqm Port Mosque - Program B','جامع ميناء الدقم - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(239,112,NULL,'Haima Central Mosque - Program B','جامع هيماء المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(240,113,NULL,'Duqm New Mosque - Program B','جامع الدقم الجديد - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(241,114,NULL,'Mahout Village Mosque - Program B','جامع قرية محوت - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14'),(242,115,NULL,'Al Wusta Central Mosque - Program B','جامع الوسطى المركزي - البرنامج ب','Kids','B','child','Monday,Wednesday,Friday','16:00:00','17:00:00',NULL,20,1,'2026-04-11 22:01:14');
/*!40000 ALTER TABLE `mosque_programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mosques`
--

DROP TABLE IF EXISTS `mosques`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mosques` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wilayat` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `governorate` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Muscat',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT 'mosque_default.jpg',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_grand` tinyint(1) DEFAULT '0',
  `is_historic` tinyint(1) DEFAULT '0',
  `established_year` smallint DEFAULT NULL,
  `capacity` int DEFAULT '500',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `wilayat_id` int unsigned DEFAULT NULL,
  `governorate_id` int unsigned DEFAULT NULL,
  `admin_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mosques`
--

LOCK TABLES `mosques` WRITE;
/*!40000 ALTER TABLE `mosques` DISABLE KEYS */;
INSERT INTO `mosques` VALUES (1,'Sultan Qaboos Grand Mosque','مسجد السلطان قابوس الأكبر','Al Ghubrah, Muscat','Bausher','Muscat','+968 2469 1111','info@sqgm.om','mosque_default.jpg',1,'2026-04-11 20:32:34',0,0,NULL,500,NULL,NULL,NULL,NULL,11),(2,'Al-Husn Mosque','مسجد الحصن','Al Khoud, Muscat','Al Seeb','Muscat','+968 2454 2222','alhusn@mosque.om','mosque_default.jpg',1,'2026-04-11 20:32:34',0,0,NULL,500,NULL,NULL,NULL,NULL,NULL),(3,'Al-Amerat Grand Mosque','مسجد العامرات الكبير','Al Amerat, Muscat','Al Amerat','Muscat','+968 2441 3333','alamerat@mosque.om','mosque_default.jpg',1,'2026-04-11 20:32:34',0,0,NULL,500,NULL,NULL,NULL,NULL,NULL),(4,'Ruwi Mosque','مسجد الروي','Ruwi, Muscat','Mutrah','Muscat','+968 2477 4444','ruwi@mosque.om','mosque_default.jpg',1,'2026-04-11 20:32:34',0,0,NULL,500,NULL,NULL,NULL,NULL,NULL),(5,'Al-Khuwair Mosque','مسجد الخوير','Al Khuwair, Muscat','Bausher','Muscat','+968 2469 5555','alkhuwair@mosque.om','mosque_default.jpg',1,'2026-04-11 20:32:34',0,0,NULL,500,NULL,NULL,NULL,NULL,NULL),(6,'Sultan Taimur Mosque','جامع السلطان تيمور','Al Mabelah, Muscat','Bausher','Muscat','+968 2466 1001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1200,23.5700000,58.6100000,NULL,NULL,NULL),(7,'Al Zulfah Mosque','جامع الزلفى','Al Khuwair, Muscat','Bausher','Muscat','+968 2469 1002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,2000,23.6001000,58.5200000,NULL,NULL,NULL),(8,'Al Seeb Grand Mosque','جامع السيب الكبير','Al Seeb City','Al Seeb','Muscat','+968 2454 1003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,2500,23.6800000,58.1900000,NULL,NULL,NULL),(9,'Muttrah Grand Mosque','جامع مطرح الكبير','Muttrah Corniche','Mutrah','Muscat','+968 2477 1004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,1,NULL,1500,23.6270000,58.5620000,NULL,NULL,NULL),(10,'Al Khor Old Mosque','جامع الخور القديم','Old Muscat','Muscat','Muscat','+968 2469 1005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,800,23.6190000,58.5930000,NULL,NULL,NULL),(11,'Qurayyat Mosque','جامع قريات الكبير','Qurayyat','Qurayyat','Muscat','+968 2466 1006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,23.2790000,58.9100000,NULL,NULL,NULL),(12,'Al Amerat Mosque','جامع العامرات','Al Amerat','Al Amerat','Muscat','+968 2441 1007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1500,23.5450000,58.7100000,NULL,NULL,NULL),(13,'Wadi Kabir Mosque','جامع وادي كبير','Wadi Kabir','Mutrah','Muscat','+968 2477 1008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,900,23.6180000,58.5720000,NULL,NULL,NULL),(14,'Saeed Bin Taimur Mosque','جامع صيد بن تيمور','Al Khuwair','Bausher','Muscat','+968 2469 1009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,900,23.6050000,58.5310000,NULL,NULL,NULL),(15,'Al Ghubrah Mosque','جامع الغبرة','Al Ghubrah','Bausher','Muscat','+968 2469 1010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1100,23.6100000,58.4900000,NULL,NULL,NULL),(16,'Nizwa Grand Mosque','جامع نزوى الكبير','Nizwa City','Nizwa','Al Dakhiliyah','+968 2541 2001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,4000,22.9310000,57.5340000,NULL,NULL,NULL),(17,'Al Khor Mosque Nizwa','جامع الخور نزوى','Old Nizwa','Nizwa','Al Dakhiliyah','+968 2541 2002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,600,22.9280000,57.5260000,NULL,NULL,NULL),(18,'Bahla Grand Mosque','جامع بهلاء الكبير','Bahla','Bahla','Al Dakhiliyah','+968 2541 2003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,1,NULL,2000,22.9680000,57.3040000,NULL,NULL,NULL),(19,'Bahla Old Mosque','مسجد بهلاء الأثري','Old Bahla','Bahla','Al Dakhiliyah','+968 2541 2004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,300,22.9650000,57.3000000,NULL,NULL,NULL),(20,'Samail Grand Mosque','جامع سمائل الكبير','Samail','Samail','Al Dakhiliyah','+968 2541 2005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,23.3000000,57.9850000,NULL,NULL,NULL),(21,'Izki Grand Mosque','جامع إزكي الكبير','Izki','Izki','Al Dakhiliyah','+968 2541 2006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1200,22.9370000,57.7740000,NULL,NULL,NULL),(22,'Manah Ancient Mosque','جامع منح الأثري','Manah','Manah','Al Dakhiliyah','+968 2541 2007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,700,22.8950000,57.5780000,NULL,NULL,NULL),(23,'Adam Grand Mosque','جامع آدم الكبير','Adam','Adam','Al Dakhiliyah','+968 2541 2008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,1500,22.3970000,57.5190000,NULL,NULL,NULL),(24,'Al Hamra Mosque','جامع الحمراء','Al Hamra','Al Hamra','Al Dakhiliyah','+968 2541 2009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,700,23.1230000,57.2940000,NULL,NULL,NULL),(25,'Bid Bid Mosque','جامع بدبد','Bid Bid','Bid Bid','Al Dakhiliyah','+968 2541 2010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,23.2488000,58.1089000,NULL,NULL,NULL),(26,'Sultan Qaboos Mosque Sohar','جامع السلطان قابوس صحار','Sohar City','Sohar','North Al Batinah','+968 2685 3001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,5000,24.3450000,56.7060000,NULL,NULL,NULL),(27,'Sohar Ancient Mosque','جامع صحار القديم','Old Sohar','Sohar','North Al Batinah','+968 2685 3002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,2000,24.3500000,56.7100000,NULL,NULL,NULL),(28,'Shinas Grand Mosque','جامع شناص الكبير','Shinas','Shinas','North Al Batinah','+968 2685 3003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1500,24.7440000,56.4610000,NULL,NULL,NULL),(29,'Liwa Central Mosque','جامع لوى المركزي','Liwa','Liwa','North Al Batinah','+968 2685 3004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,24.5100000,56.5400000,NULL,NULL,NULL),(30,'Saham Grand Mosque','جامع صحم الكبير','Saham','Saham','North Al Batinah','+968 2685 3005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1800,24.1720000,56.8840000,NULL,NULL,NULL),(31,'Al Khaburah Mosque','جامع الخابورة','Al Khaburah','Al Khaburah','North Al Batinah','+968 2685 3006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1200,23.9700000,57.0920000,NULL,NULL,NULL),(32,'Al Suwaiq Mosque','جامع السويق','Al Suwaiq','Al Suwaiq','North Al Batinah','+968 2685 3007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,23.8480000,57.4400000,NULL,NULL,NULL),(33,'Sohar Al Falaj Mosque','جامع الفلج صحار','Al Falaj, Sohar','Sohar','North Al Batinah','+968 2685 3008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,24.3600000,56.7200000,NULL,NULL,NULL),(34,'Sohar Corniche Mosque','جامع كورنيش صحار','Sohar Corniche','Sohar','North Al Batinah','+968 2685 3009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,900,24.3400000,56.6900000,NULL,NULL,NULL),(35,'Shinas Coastal Mosque','جامع شناص الساحلي','Shinas Coast','Shinas','North Al Batinah','+968 2685 3010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,700,24.7500000,56.4700000,NULL,NULL,NULL),(36,'Rustaq Grand Mosque','جامع الرستاق الكبير','Rustaq City','Rustaq','South Al Batinah','+968 2687 4001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,3000,23.3900000,57.4260000,NULL,NULL,NULL),(37,'Nakhal Fortress Mosque','جامع نخل الأثري','Nakhal','Nakhal','South Al Batinah','+968 2687 4002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,800,23.3840000,57.8320000,NULL,NULL,NULL),(38,'Barka Grand Mosque','جامع بركاء الكبير','Barka','Barka','South Al Batinah','+968 2687 4003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,2000,23.6910000,57.8910000,NULL,NULL,NULL),(39,'Awabi Mosque','جامع عوابي','Awabi','Awabi','South Al Batinah','+968 2687 4004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,900,23.2930000,57.5265000,NULL,NULL,NULL),(40,'Al Masnaah Mosque','جامع المصنعة','Al Masnaah','Al Masnaah','South Al Batinah','+968 2687 4005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1200,23.8107000,57.6352000,NULL,NULL,NULL),(41,'Rustaq Old Mosque','جامع الرستاق القديم','Old Rustaq','Rustaq','South Al Batinah','+968 2687 4006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,600,23.3950000,57.4300000,NULL,NULL,NULL),(42,'Wadi Maawil Mosque','جامع وادي المعاول','Wadi Maawil','Wadi Maawil','South Al Batinah','+968 2687 4007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,700,23.6127000,57.5678000,NULL,NULL,NULL),(43,'Barka Al Falaj Mosque','جامع الفلج بركاء','Al Falaj Barka','Barka','South Al Batinah','+968 2687 4008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,23.6800000,57.8800000,NULL,NULL,NULL),(44,'Nakhal Valley Mosque','جامع وادي نخل','Nakhal Valley','Nakhal','South Al Batinah','+968 2687 4009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,600,23.3700000,57.8100000,NULL,NULL,NULL),(45,'Rustaq Fort Mosque','جامع قلعة الرستاق','Rustaq Fort Area','Rustaq','South Al Batinah','+968 2687 4010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,500,23.3850000,57.4200000,NULL,NULL,NULL),(46,'Ibra Grand Mosque','جامع إبراء الكبير','Ibra City','Ibra','North Al Sharqiyah','+968 2558 5001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,3000,22.6900000,58.5340000,NULL,NULL,NULL),(47,'Mudaybi Mosque','جامع المضيبي','Mudaybi','Mudaybi','North Al Sharqiyah','+968 2558 5002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,22.5626000,58.1390000,NULL,NULL,NULL),(48,'Sinaw Grand Mosque','جامع سناو الكبير','Sinaw','Sinaw','North Al Sharqiyah','+968 2558 5003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1500,22.4210000,58.1140000,NULL,NULL,NULL),(49,'Al Qabil Mosque','جامع القابل','Al Qabil','Al Qabil','North Al Sharqiyah','+968 2558 5004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,22.6776000,58.5210000,NULL,NULL,NULL),(50,'Dima Mosque','جامع دما','Dima','Dima','North Al Sharqiyah','+968 2558 5005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,700,22.7882000,57.9467000,NULL,NULL,NULL),(51,'Ibra Old Mosque','جامع إبراء القديم','Old Ibra','Ibra','North Al Sharqiyah','+968 2558 5006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,500,22.6850000,58.5300000,NULL,NULL,NULL),(52,'Sinaw Heritage Mosque','جامع سناو التراثي','Old Sinaw','Sinaw','North Al Sharqiyah','+968 2558 5007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,400,22.4150000,58.1100000,NULL,NULL,NULL),(53,'Mudaybi Central Mosque','جامع المضيبي المركزي','Mudaybi Centre','Mudaybi','North Al Sharqiyah','+968 2558 5008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,900,22.5700000,58.1450000,NULL,NULL,NULL),(54,'Al Mudhaibi Mosque','جامع المضيبي الكبرى','Al Mudhaibi','Al Mudhaibi','North Al Sharqiyah','+968 2558 5009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,22.5501000,58.2026000,NULL,NULL,NULL),(55,'Ibra Al Khoud Mosque','جامع الخود إبراء','Al Khoud Ibra','Ibra','North Al Sharqiyah','+968 2558 5010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,700,22.6950000,58.5400000,NULL,NULL,NULL),(56,'Sur Grand Mosque','جامع صور الكبير','Sur City','Sur','South Al Sharqiyah','+968 2554 6001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,4000,22.5670000,59.5280000,NULL,NULL,NULL),(57,'Al Ayjah Heritage Mosque','جامع العيجة التراثي','Al Ayjah Sur','Sur','South Al Sharqiyah','+968 2554 6002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,1200,22.5700000,59.5350000,NULL,NULL,NULL),(58,'Jalan BBA Mosque','جامع جعلان بني بو علي','Jalan BBA','Jalan BBA','South Al Sharqiyah','+968 2554 6003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1500,22.2230000,59.0530000,NULL,NULL,NULL),(59,'Masirah Island Mosque','جامع جزيرة مصيرة','Masirah','Masirah','South Al Sharqiyah','+968 2554 6004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,20.7000000,58.9000000,NULL,NULL,NULL),(60,'Al Kamil Mosque','جامع الكامل','Al Kamil','Al Kamil','South Al Sharqiyah','+968 2554 6005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,22.2250000,59.1820000,NULL,NULL,NULL),(61,'Tiwi Coastal Mosque','جامع طيوي الساحلي','Tiwi','Tiwi','South Al Sharqiyah','+968 2554 6006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,600,22.8283000,59.2677000,NULL,NULL,NULL),(62,'Sur Corniche Mosque','جامع كورنيش صور','Sur Corniche','Sur','South Al Sharqiyah','+968 2554 6007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,22.5650000,59.5260000,NULL,NULL,NULL),(63,'Jalan BBH Mosque','جامع جعلان بني بو حسن','Jalan BBH','Jalan BBH','South Al Sharqiyah','+968 2554 6008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,900,22.0500000,59.0000000,NULL,NULL,NULL),(64,'Sur Old Mosque','جامع صور القديم','Old Sur','Sur','South Al Sharqiyah','+968 2554 6009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,500,22.5680000,59.5300000,NULL,NULL,NULL),(65,'Wadi Bani Khalid Mosque','جامع وادي بني خالد','Wadi Bani Khalid','Wadi Bani Khalid','South Al Sharqiyah','+968 2554 6010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,700,22.6000000,58.9000000,NULL,NULL,NULL),(66,'Ibri Grand Mosque','جامع عبري الكبير','Ibri City','Ibri','Al Dhahirah','+968 2569 7001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,3500,23.2260000,56.5090000,NULL,NULL,NULL),(67,'Yanqul Mosque','جامع ينقل الكبير','Yanqul','Yanqul','Al Dhahirah','+968 2569 7002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,23.5710000,56.0890000,NULL,NULL,NULL),(68,'Dank Grand Mosque','جامع ضنك الكبير','Dank','Dank','Al Dhahirah','+968 2569 7003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,900,23.5330000,56.2589000,NULL,NULL,NULL),(69,'Ibri Old Mosque','جامع عبري القديم','Old Ibri','Ibri','Al Dhahirah','+968 2569 7004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,600,23.2300000,56.5100000,NULL,NULL,NULL),(70,'Yanqul Central Mosque','جامع ينقل المركزي','Yanqul Centre','Yanqul','Al Dhahirah','+968 2569 7005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,23.5750000,56.0950000,NULL,NULL,NULL),(71,'Ibri Al Falaj Mosque','جامع الفلج عبري','Al Falaj Ibri','Ibri','Al Dhahirah','+968 2569 7006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,700,23.2200000,56.5000000,NULL,NULL,NULL),(72,'Dank Heritage Mosque','جامع ضنك التراثي','Old Dank','Dank','Al Dhahirah','+968 2569 7007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,500,23.5400000,56.2600000,NULL,NULL,NULL),(73,'Ibri New Mosque','جامع عبري الجديد','New Ibri','Ibri','Al Dhahirah','+968 2569 7008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1200,23.2150000,56.4950000,NULL,NULL,NULL),(74,'Yanqul Valley Mosque','جامع وادي ينقل','Yanqul Valley','Yanqul','Al Dhahirah','+968 2569 7009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,600,23.5600000,56.0800000,NULL,NULL,NULL),(75,'Dank Central Mosque','جامع ضنك المركزي','Dank Centre','Dank','Al Dhahirah','+968 2569 7010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,700,23.5350000,56.2650000,NULL,NULL,NULL),(76,'Sultan Qaboos Mosque Salalah','جامع السلطان قابوس صلالة','Salalah City','Salalah','Dhofar','+968 2328 8001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,8000,17.0140000,54.0914000,NULL,NULL,NULL),(77,'Al Uyoun Mosque','جامع العيون صلالة','Al Uyoun Salalah','Salalah','Dhofar','+968 2328 8002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,2000,17.0200000,54.1000000,NULL,NULL,NULL),(78,'Nabi Ayoub Mosque','جامع النبي أيوب','Salalah Ancient','Salalah','Dhofar','+968 2328 8003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,500,17.0050000,54.0920000,NULL,NULL,NULL),(79,'Thumrait Mosque','جامع ثمريت المركزي','Thumrait','Thumrait','Dhofar','+968 2328 8004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,17.6650000,54.0220000,NULL,NULL,NULL),(80,'Taqah Heritage Mosque','جامع طاقة التراثي','Taqah','Taqah','Dhofar','+968 2328 8005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,800,17.0330000,54.3830000,NULL,NULL,NULL),(81,'Mirbat Grand Mosque','جامع مرباط الكبير','Mirbat','Mirbat','Dhofar','+968 2328 8006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,1200,16.9990000,54.6950000,NULL,NULL,NULL),(82,'Sadah Mosque','جامع سدح','Sadah','Sadah','Dhofar','+968 2328 8007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,600,16.9180000,55.0611000,NULL,NULL,NULL),(83,'Salalah Al Hafah Mosque','جامع الحافة صلالة','Al Hafah Salalah','Salalah','Dhofar','+968 2328 8008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,3000,17.0180000,54.1050000,NULL,NULL,NULL),(84,'Salalah New Mosque','جامع صلالة الجديد','New Salalah','Salalah','Dhofar','+968 2328 8009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,2500,17.0100000,54.0800000,NULL,NULL,NULL),(85,'Thumrait Village Mosque','جامع قرية ثمريت','Thumrait Village','Thumrait','Dhofar','+968 2328 8010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,600,17.6700000,54.0300000,NULL,NULL,NULL),(86,'Al Buraymi Grand Mosque','جامع البريمي الكبير','Al Buraymi City','Al Buraymi','Al Buraymi','+968 2565 9001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,3000,24.2330000,55.7870000,NULL,NULL,NULL),(87,'Mahdha Central Mosque','جامع محضة المركزي','Mahdha','Mahdha','Al Buraymi','+968 2565 9002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,23.9320000,56.0010000,NULL,NULL,NULL),(88,'As Sinainah Mosque','جامع السنينة','As Sinainah','As Sinainah','Al Buraymi','+968 2565 9003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,700,24.1001000,55.9001000,NULL,NULL,NULL),(89,'Al Buraymi Old Mosque','جامع البريمي القديم','Old Buraymi','Al Buraymi','Al Buraymi','+968 2565 9004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,500,24.2400000,55.7900000,NULL,NULL,NULL),(90,'Al Buraymi New Mosque','جامع البريمي الجديد','New Buraymi','Al Buraymi','Al Buraymi','+968 2565 9005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1200,24.2280000,55.7820000,NULL,NULL,NULL),(91,'Mahdha Village Mosque','جامع قرية محضة','Mahdha Village','Mahdha','Al Buraymi','+968 2565 9006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,600,23.9400000,56.0100000,NULL,NULL,NULL),(92,'Al Buraymi Central Mosque','جامع البريمي المركزي','Buraymi Centre','Al Buraymi','Al Buraymi','+968 2565 9007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,24.2350000,55.7850000,NULL,NULL,NULL),(93,'Sinainah Village Mosque','جامع قرية السنينة','Sinainah Village','As Sinainah','Al Buraymi','+968 2565 9008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,500,24.1050000,55.9050000,NULL,NULL,NULL),(94,'Al Buraymi Friday Mosque','جامع الجمعة البريمي','Friday Mosque Buraymi','Al Buraymi','Al Buraymi','+968 2565 9009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,900,24.2310000,55.7860000,NULL,NULL,NULL),(95,'Buraymi Heritage Mosque','جامع البريمي التراثي','Heritage Buraymi','Al Buraymi','Al Buraymi','+968 2565 9010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,400,24.2450000,55.7920000,NULL,NULL,NULL),(96,'Khasab Grand Mosque','جامع خصب الكبير','Khasab City','Khasab','Musandam','+968 2673 0001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,2000,26.1860000,56.2470000,NULL,NULL,NULL),(97,'Bukha Mosque','جامع بخاء','Bukha','Bukha','Musandam','+968 2673 0002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,700,26.1550000,56.1670000,NULL,NULL,NULL),(98,'Dibba Mosque','جامع دبا','Dibba Al Bayah','Dibba','Musandam','+968 2673 0003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,25.6160000,56.2680000,NULL,NULL,NULL),(99,'Madha Mosque','جامع مضحى','Madha Enclave','Madha','Musandam','+968 2673 0004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,500,24.9830000,56.3060000,NULL,NULL,NULL),(100,'Khasab Old Mosque','جامع خصب القديم','Old Khasab','Khasab','Musandam','+968 2673 0005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,1,NULL,400,26.1900000,56.2500000,NULL,NULL,NULL),(101,'Khasab Waterfront Mosque','جامع كورنيش خصب','Khasab Waterfront','Khasab','Musandam','+968 2673 0006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,600,26.1840000,56.2440000,NULL,NULL,NULL),(102,'Bukha Central Mosque','جامع بخاء المركزي','Bukha Centre','Bukha','Musandam','+968 2673 0007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,500,26.1580000,56.1700000,NULL,NULL,NULL),(103,'Dibba Central Mosque','جامع دبا المركزي','Dibba Centre','Dibba','Musandam','+968 2673 0008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,25.6200000,56.2700000,NULL,NULL,NULL),(104,'Khasab New Mosque','جامع خصب الجديد','New Khasab','Khasab','Musandam','+968 2673 0009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,700,26.1820000,56.2460000,NULL,NULL,NULL),(105,'Madha Central Mosque','جامع مضحى المركزي','Madha Centre','Madha','Musandam','+968 2673 0010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,400,24.9850000,56.3080000,NULL,NULL,NULL),(106,'Haima Grand Mosque','جامع هيماء الكبير','Haima Town','Haima','Al Wusta','+968 2569 1001',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',1,0,NULL,1500,19.9510000,56.2740000,NULL,NULL,NULL),(107,'Duqm Central Mosque','جامع الدقم المركزي','Duqm SEZ','Duqm','Al Wusta','+968 2569 1002',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,2000,19.6500000,57.7030000,NULL,NULL,NULL),(108,'Mahout Mosque','جامع محوت','Mahout Island','Mahout','Al Wusta','+968 2569 1003',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,600,20.6470000,58.1930000,NULL,NULL,NULL),(109,'Al Jazir Mosque','جامع الجازر','Al Jazir','Al Jazir','Al Wusta','+968 2569 1004',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,500,19.0001000,57.0001000,NULL,NULL,NULL),(110,'Haima New Mosque','جامع هيماء الجديد','New Haima','Haima','Al Wusta','+968 2569 1005',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1000,19.9550000,56.2780000,NULL,NULL,NULL),(111,'Duqm Port Mosque','جامع ميناء الدقم','Duqm Port','Duqm','Al Wusta','+968 2569 1006',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1200,19.6550000,57.7080000,NULL,NULL,NULL),(112,'Haima Central Mosque','جامع هيماء المركزي','Haima Centre','Haima','Al Wusta','+968 2569 1007',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,800,19.9480000,56.2720000,NULL,NULL,NULL),(113,'Duqm New Mosque','جامع الدقم الجديد','New Duqm','Duqm','Al Wusta','+968 2569 1008',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,1500,19.6480000,57.7000000,NULL,NULL,NULL),(114,'Mahout Village Mosque','جامع قرية محوت','Mahout Village','Mahout','Al Wusta','+968 2569 1009',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,400,20.6500000,58.1960000,NULL,NULL,NULL),(115,'Al Wusta Central Mosque','جامع الوسطى المركزي','Al Wusta','Haima','Al Wusta','+968 2569 1010',NULL,'mosque_default.jpg',1,'2026-04-11 21:06:46',0,0,NULL,600,19.9530000,56.2760000,NULL,NULL,NULL);
/*!40000 ALTER TABLE `mosques` ENABLE KEYS */;
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
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','success','warning','alert') COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,12,'Progress','Fatima Child — النبأ — Good','success',1,'2026-04-19 11:26:41'),(2,12,'Progress','Fatima Child — سجل 22 Apr 2026 — Excellent','success',1,'2026-04-22 16:03:53'),(3,13,'Progress','Omar Student — الكوثر — Excellent','success',1,'2026-04-22 16:09:47'),(4,9,'New Student: Mc','Mc joined: Quran Memorization','info',1,'2026-04-23 06:09:24'),(5,10,'New Child','hassan joined Kids Quran Program - Slot B','info',0,'2026-04-24 12:15:02'),(6,9,'New Student: Abdulazieez','Abdulazieez joined: Quran Memorization','info',0,'2026-04-27 09:29:44'),(7,9,'New Student: Azooz','Azooz joined: Quran Memorization','info',0,'2026-04-27 09:31:49'),(8,10,'New Child','salim joined Kids Quran Program - Slot B','info',0,'2026-04-27 09:38:40');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `private_program_requests`
--

DROP TABLE IF EXISTS `private_program_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `private_program_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned NOT NULL,
  `student_id` int unsigned NOT NULL,
  `teacher_id` int unsigned DEFAULT NULL COMMENT 'NULL until parent selects',
  `mosque_id` int unsigned NOT NULL,
  `preferred_days` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT 'Sunday,Tuesday,Thursday',
  `preferred_time` time DEFAULT '16:00:00',
  `status` enum('pending','accepted','rejected','active','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `student_id` (`student_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `mosque_id` (`mosque_id`),
  CONSTRAINT `private_program_requests_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `private_program_requests_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `private_program_requests_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `private_program_requests_ibfk_4` FOREIGN KEY (`mosque_id`) REFERENCES `mosques` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `private_program_requests`
--

LOCK TABLES `private_program_requests` WRITE;
/*!40000 ALTER TABLE `private_program_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `private_program_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `program_enrollments`
--

DROP TABLE IF EXISTS `program_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `program_enrollments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `program_id` int unsigned NOT NULL,
  `student_id` int unsigned NOT NULL,
  `status` enum('active','dropped','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `enrolled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prog_student` (`program_id`,`student_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `program_enrollments_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `mosque_programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `program_enrollments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `program_enrollments`
--

LOCK TABLES `program_enrollments` WRITE;
/*!40000 ALTER TABLE `program_enrollments` DISABLE KEYS */;
INSERT INTO `program_enrollments` VALUES (1,1,1,'active','2026-04-16 16:38:04'),(2,128,2,'dropped','2026-04-16 16:38:17'),(3,1,3,'active','2026-04-23 06:09:24'),(4,128,4,'dropped','2026-04-24 12:15:02'),(5,1,5,'active','2026-04-27 09:29:44'),(6,1,6,'active','2026-04-27 09:31:49'),(7,128,7,'active','2026-04-27 09:38:40');
/*!40000 ALTER TABLE `program_enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `program_templates`
--

DROP TABLE IF EXISTS `program_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `program_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name_en` varchar(100) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `description_en` text,
  `description_ar` text,
  `icon` varchar(10) DEFAULT 'ðŸ“–',
  `min_age` int DEFAULT '5',
  `max_age` int DEFAULT '99',
  `level` enum('Beginner','Intermediate','Advanced','All Levels') DEFAULT 'All Levels',
  `program_type` varchar(50) DEFAULT 'Quran',
  `is_active` tinyint DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `recommended_slot` enum('A','B','both') DEFAULT 'A',
  `target_audience` enum('student','child','both') DEFAULT 'student',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `program_templates`
--

LOCK TABLES `program_templates` WRITE;
/*!40000 ALTER TABLE `program_templates` DISABLE KEYS */;
INSERT INTO `program_templates` VALUES (1,'Quran Memorization','حفظ القرآن','Students memorize Quran surahs with teacher supervision and tracking','حفظ سور القرآن الكريم تحت إشراف المعلم مع متابعة دقيقة','ðŸ“–',6,99,'Beginner','Memorization',1,1,'A','student'),(2,'Tajweed','تجويد القرآن','Learn proper Quran recitation rules and pronunciation','تعلم أحكام التجويد والنطق الصحيح لحروف القرآن الكريم','ðŸŽµ',8,99,'All Levels','Tajweed',1,2,'A','student'),(3,'Quran Recitation','تلاوة القرآن','Fluent reading of Quran with basic tajweed rules','قراءة القرآن الكريم بطلاقة مع تطبيق أحكام التجويد الأساسية','ðŸ”Š',7,99,'Beginner','Recitation',1,3,'B','child'),(4,'Kids Quran','القرآن للأطفال','Fun and engaging Quran learning for young children ages 5-10','تعلم القرآن الكريم بأسلوب ممتع وتفاعلي للأطفال من سن 5 إلى 10','ðŸ‘¶',5,12,'Beginner','Kids',1,4,'B','child'),(5,'Tafseer','تفسير القرآن','Understanding the meaning and interpretation of Quran verses','فهم معاني وتفسير آيات القرآن الكريم للمتقدمين','ðŸ“š',14,99,'Advanced','Tafseer',1,5,'A','student'),(6,'Quran for Converts','القرآن للمسلمين الجدد','Special program for new Muslims learning to read Quran','برنامج خاص للمسلمين الجدد لتعلم قراءة القرآن الكريم','ðŸŒ™',10,99,'Beginner','Converts',1,6,'A','student');
/*!40000 ALTER TABLE `program_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `progress`
--

DROP TABLE IF EXISTS `progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `progress` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int unsigned NOT NULL,
  `class_id` int unsigned NOT NULL,
  `surah_number` tinyint unsigned NOT NULL COMMENT '1-114',
  `surah_name_en` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `surah_name_ar` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ayah_from` smallint unsigned DEFAULT '1',
  `ayah_to` smallint unsigned DEFAULT NULL,
  `tajweed_level` enum('1','2','3','4','5') COLLATE utf8mb4_unicode_ci DEFAULT '1' COMMENT '1=poor 5=excellent',
  `memorization_pct` tinyint unsigned DEFAULT '0' COMMENT '0-100%',
  `evaluation` enum('Excellent','Good','Needs Improvement','Repeat') COLLATE utf8mb4_unicode_ci DEFAULT 'Good',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `updated_by` int unsigned NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `class_id` (`class_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progress_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progress_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progress`
--

LOCK TABLES `progress` WRITE;
/*!40000 ALTER TABLE `progress` DISABLE KEYS */;
INSERT INTO `progress` VALUES (1,2,2,78,'An-Naba','النبأ',1,10,'2',10,'Good','today child fatma was excellent ',10,'2026-04-19 11:26:41'),(2,2,2,200,'Record 22 Apr 2026','سجل 22 Apr 2026',0,0,'1',17,'Excellent','hahah',10,'2026-04-22 16:03:53'),(3,1,1,108,'Al-Kawthar','الكوثر',1,3,'1',100,'Excellent','student meet the requirements',9,'2026-04-22 16:09:47');
/*!40000 ALTER TABLE `progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports_log`
--

DROP TABLE IF EXISTS `reports_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `generated_by` int unsigned NOT NULL,
  `report_type` enum('attendance','progress','class','full') COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` enum('pdf','csv') COLLATE utf8mb4_unicode_ci NOT NULL,
  `mosque_id` int unsigned DEFAULT NULL,
  `class_id` int unsigned DEFAULT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `reports_log_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports_log`
--

LOCK TABLES `reports_log` WRITE;
/*!40000 ALTER TABLE `reports_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_badges`
--

DROP TABLE IF EXISTS `student_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_badges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int unsigned NOT NULL,
  `badge_id` int unsigned NOT NULL,
  `earned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sb` (`student_id`,`badge_id`),
  KEY `badge_id` (`badge_id`),
  CONSTRAINT `student_badges_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_badges`
--

LOCK TABLES `student_badges` WRITE;
/*!40000 ALTER TABLE `student_badges` DISABLE KEYS */;
INSERT INTO `student_badges` VALUES (1,1,6,'2026-04-23 06:19:16');
/*!40000 ALTER TABLE `student_badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_points`
--

DROP TABLE IF EXISTS `student_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_points` (
  `student_id` int unsigned NOT NULL,
  `total_points` int unsigned DEFAULT '0',
  `level` tinyint unsigned DEFAULT '1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  CONSTRAINT `student_points_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_points`
--

LOCK TABLES `student_points` WRITE;
/*!40000 ALTER TABLE `student_points` DISABLE KEYS */;
INSERT INTO `student_points` VALUES (1,10,1,'2026-04-23 06:19:16');
/*!40000 ALTER TABLE `student_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name_ar` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female') COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_type` enum('student','child') COLLATE utf8mb4_unicode_ci DEFAULT 'student',
  `slot` enum('A','B') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `program_id` int unsigned DEFAULT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `mosque_id` int unsigned NOT NULL,
  `enrollment_date` date DEFAULT (curdate()),
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int unsigned DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `mosque_id` (`mosque_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`mosque_id`) REFERENCES `mosques` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,'Omar Student','عمر ','2005-01-01','male','student','A',NULL,NULL,1,'2026-04-16',NULL,1,'2026-04-16 16:37:57',13,'student1@quran.com'),(2,'Fatima Child','فاطمة','2016-01-01','female','child','B',NULL,12,1,'2026-04-16',NULL,0,'2026-04-16 16:38:00',NULL,NULL),(3,'Mc','college','2021-03-01','male','student','A',NULL,NULL,1,'2026-04-23',NULL,1,'2026-04-23 06:09:24',14,'mc@gmail.com'),(4,'hassan','hasssan','2016-01-01','male','child','B',NULL,12,1,'2026-04-24',NULL,0,'2026-04-24 12:15:02',NULL,NULL),(5,'Abdulazieez','Ahmed','0002-07-04','male','student','A',NULL,NULL,1,'2026-04-27',NULL,1,'2026-04-27 09:29:44',15,'abdulaziz@gmail.com'),(6,'Azooz','battahi','2010-01-01','male','student','A',NULL,NULL,1,'2026-04-27',NULL,1,'2026-04-27 09:31:49',16,'azooz@gmail.com'),(7,'salim','Ahmed','2018-01-01','male','child','B',NULL,17,1,'2026-04-27',NULL,1,'2026-04-27 09:38:40',NULL,NULL);
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `surahs`
--

DROP TABLE IF EXISTS `surahs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `surahs` (
  `number` tinyint unsigned NOT NULL,
  `name_en` varchar(80) NOT NULL,
  `name_ar` varchar(80) NOT NULL,
  `ayah_count` smallint unsigned NOT NULL,
  `juz_start` tinyint unsigned NOT NULL,
  `difficulty` enum('Easy','Medium','Hard') DEFAULT 'Medium',
  PRIMARY KEY (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `surahs`
--

LOCK TABLES `surahs` WRITE;
/*!40000 ALTER TABLE `surahs` DISABLE KEYS */;
INSERT INTO `surahs` VALUES (1,'Al-Fatihah','Ø§Ù„ÙØ§ØªØ­Ø©',7,1,'Easy'),(2,'Al-Baqarah','Ø§Ù„Ø¨Ù‚Ø±Ø©',286,1,'Hard'),(3,'Ali Imran','Ø¢Ù„ Ø¹Ù…Ø±Ø§Ù†',200,3,'Hard'),(36,'Ya-Sin','ÙŠØ³',83,22,'Easy'),(67,'Al-Mulk','Ø§Ù„Ù…Ù„Ùƒ',30,29,'Easy'),(78,'An-Naba','Ø§Ù„Ù†Ø¨Ø£',40,30,'Easy'),(112,'Al-Ikhlas','Ø§Ù„Ø¥Ø®Ù„Ø§Øµ',4,30,'Easy'),(113,'Al-Falaq','Ø§Ù„ÙÙ„Ù‚',5,30,'Easy'),(114,'An-Nas','Ø§Ù„Ù†Ø§Ø³',6,30,'Easy');
/*!40000 ALTER TABLE `surahs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_course_selections`
--

DROP TABLE IF EXISTS `teacher_course_selections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_course_selections` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `program_id` int unsigned NOT NULL,
  `course_id` int unsigned NOT NULL,
  `start_date` date NOT NULL,
  `is_active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_prog` (`teacher_id`,`program_id`),
  KEY `program_id` (`program_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `teacher_course_selections_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_course_selections_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `mosque_programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_course_selections_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `fixed_courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_course_selections`
--

LOCK TABLES `teacher_course_selections` WRITE;
/*!40000 ALTER TABLE `teacher_course_selections` DISABLE KEYS */;
INSERT INTO `teacher_course_selections` VALUES (1,10,128,8,'2026-04-19',1,'2026-04-19 11:29:41');
/*!40000 ALTER TABLE `teacher_course_selections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_program_applications`
--

DROP TABLE IF EXISTS `teacher_program_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_program_applications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `program_id` int unsigned NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `notes` text,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_prog` (`teacher_id`,`program_id`),
  KEY `program_id` (`program_id`),
  CONSTRAINT `teacher_program_applications_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_program_applications_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `mosque_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_program_applications`
--

LOCK TABLES `teacher_program_applications` WRITE;
/*!40000 ALTER TABLE `teacher_program_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `teacher_program_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name_ar` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','mosque_admin','teacher','parent','student') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `mosque_id` int unsigned DEFAULT NULL,
  `governorate` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_card_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lang_pref` enum('en','ar') COLLATE utf8mb4_unicode_ci DEFAULT 'ar',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `slot` enum('A','B') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `mosque_id` (`mosque_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`mosque_id`) REFERENCES `mosques` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Abdulrahman Al-Battashi','عبدالرحمن البطاشي','admin','admin@quranhub.om','$2y$12$4oHJlM55EM6HY0jsoivVcenGhEE.3ceXCQbhYwxKw3BQkoh4MZPSG','admin',1,'Muscat','+968 9100 0001',NULL,'ar',1,'2026-04-29 19:14:51','2026-04-11 20:32:34','2026-04-29 19:14:51',NULL),(9,'Ahmed Teacher','Ø§Ù„Ø£Ø³ØªØ§Ø° Ø£Ø­Ù…Ø¯','teacher_a','teacher_a@quran.com','$2y$12$4oHJlM55EM6HY0jsoivVcenGhEE.3ceXCQbhYwxKw3BQkoh4MZPSG','teacher',1,'Muscat',NULL,NULL,'ar',1,'2026-04-29 19:14:50','2026-04-16 16:31:57','2026-04-29 19:14:50','A'),(10,'Sara Teacher','Ø§Ù„Ø£Ø³ØªØ§Ø°Ø© Ø³Ø§Ø±Ø©','teacher_b','teacher_b@quran.com','$2y$12$4oHJlM55EM6HY0jsoivVcenGhEE.3ceXCQbhYwxKw3BQkoh4MZPSG','teacher',1,'Muscat',NULL,NULL,'ar',1,'2026-04-29 19:14:52','2026-04-16 16:31:57','2026-04-29 19:14:52','B'),(11,'Mosque Manager',NULL,'mosque_mgr','mosque@quran.com','$2y$12$4oHJlM55EM6HY0jsoivVcenGhEE.3ceXCQbhYwxKw3BQkoh4MZPSG','mosque_admin',1,'Muscat',NULL,NULL,'ar',1,'2026-04-22 15:53:10','2026-04-16 16:37:24','2026-04-22 15:53:10',NULL),(12,'Parent One',NULL,'parent1','parent1@quran.com','$2y$12$4oHJlM55EM6HY0jsoivVcenGhEE.3ceXCQbhYwxKw3BQkoh4MZPSG','parent',1,'Muscat','91000001',NULL,'ar',1,'2026-04-29 19:14:50','2026-04-16 16:37:31','2026-04-29 19:14:50',NULL),(13,'Omar Student',NULL,'student1','student1@quran.com','$2y$12$4oHJlM55EM6HY0jsoivVcenGhEE.3ceXCQbhYwxKw3BQkoh4MZPSG','student',1,'Muscat',NULL,NULL,'ar',1,'2026-04-29 19:14:48','2026-04-16 16:37:36','2026-04-29 19:14:48',NULL),(14,'Mc','college','mccollege','mc@gmail.com','$2y$12$uMz2NGoct1i2/3GLHuA4W.b8HykNwT/trxQKKDyaOwqW.WfqE5.du','student',1,'Muscat','123456789123456789','ids/id_69e9b793f2838.png','ar',1,'2026-04-23 06:42:13','2026-04-23 06:09:24','2026-04-23 06:42:13',NULL),(15,'Abdulazieez','Ahmed','student2','abdulaziz@gmail.com','$2y$12$frQvmiyhXsJPv3d2hpmzsOgQ3WMtOaEb027QcWDe7/X1aqxNodEtO','student',1,'Muscat','97761907',NULL,'ar',1,NULL,'2026-04-27 09:29:44','2026-04-27 09:29:44',NULL),(16,'Azooz','battahi','student4','azooz@gmail.com','$2y$12$S/3o2AcjRgmuK8yB/gpcaOmsLjX.Q3pAvJ29dk5G6yxKWsvzqsjQe','student',1,'Muscat','97761234','ids/id_69ef2d051dc28.pdf','ar',1,'2026-04-27 09:32:14','2026-04-27 09:31:49','2026-04-27 09:32:14',NULL),(17,'abood','buttashi','parent2','abood@gmail.com','$2y$12$sPoEs0hFEaFG0/sToKJUMeZxbzZ6z76WQieIto6HUULTV2k.cG7U2','parent',1,'Muscat','97761907','ids/id_69ef2e4407114.pdf','ar',1,'2026-04-27 09:37:28','2026-04-27 09:37:08','2026-04-27 09:37:28',NULL),(18,'Valid Student','','validstudentX7854','valid7854@test.com','$2y$12$2qPSp1mlBwYZI9SoG7H7HeOTZkN3RlkC5845/DpCFz1gmUk3DzM5K','student',1,'Muscat','',NULL,'ar',1,NULL,'2026-04-29 19:14:49','2026-04-29 19:14:49',NULL),(19,'<script>alert(\'xss\')</script>','','xsstest999x','xss999x@test.com','$2y$12$6ITvJLYSqG30KnvdUaDsCOgDZB9TJF7cLDDyQXBP2drP9JGG4SwZq','student',1,'Muscat','',NULL,'ar',1,NULL,'2026-04-29 19:14:51','2026-04-29 19:14:51',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `weekly_lessons`
--

DROP TABLE IF EXISTS `weekly_lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `weekly_lessons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int unsigned DEFAULT NULL,
  `program_id` int unsigned NOT NULL,
  `teacher_id` int unsigned NOT NULL,
  `level_id` int unsigned DEFAULT NULL,
  `week_start` date NOT NULL,
  `week_end` date NOT NULL,
  `surah_number` int DEFAULT NULL,
  `surah_name_en` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surah_name_ar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ayah_from` int DEFAULT NULL,
  `ayah_to` int DEFAULT NULL,
  `topic_en` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `topic_ar` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `objectives` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `homework` text COLLATE utf8mb4_unicode_ci,
  `status` enum('planned','in_progress','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'planned',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `program_id` (`program_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `weekly_lessons_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `mosque_programs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `weekly_lessons_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `weekly_lessons`
--

LOCK TABLES `weekly_lessons` WRITE;
/*!40000 ALTER TABLE `weekly_lessons` DISABLE KEYS */;
INSERT INTO `weekly_lessons` VALUES (1,NULL,128,10,8,'2026-04-19','2026-04-25',78,'An-Naba','النبأ',1,10,'amma','amma','meaning of ayah','haha','study aya 10 - aya 20','planned','2026-04-19 11:24:59'),(2,NULL,1,9,1,'2026-04-22','2026-04-28',108,'Al-Kawthar','الكوثر',1,3,'al kawther ','الكوثر','to memorize','next class','writre it 10 times','planned','2026-04-22 16:09:14'),(3,NULL,128,10,8,'2026-04-23','2026-04-29',NULL,'','',NULL,NULL,'al kawther ','الكوثر','reading','-','none','planned','2026-04-23 05:56:55'),(4,NULL,1,9,4,'2026-04-23','2026-04-29',NULL,'','',NULL,NULL,'','','','ws','','planned','2026-04-23 06:13:45');
/*!40000 ALTER TABLE `weekly_lessons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wilayats`
--

DROP TABLE IF EXISTS `wilayats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wilayats` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `governorate_id` int unsigned NOT NULL,
  `name_en` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_ar` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `governorate_id` (`governorate_id`),
  CONSTRAINT `wilayats_ibfk_1` FOREIGN KEY (`governorate_id`) REFERENCES `governorates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wilayats`
--

LOCK TABLES `wilayats` WRITE;
/*!40000 ALTER TABLE `wilayats` DISABLE KEYS */;
/*!40000 ALTER TABLE `wilayats` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-15 21:10:30
