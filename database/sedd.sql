-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: studentos_ai
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `studentos_ai`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `studentos_ai` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `studentos_ai`;

--
-- Table structure for table `admin_activity_logs`
--

DROP TABLE IF EXISTS `admin_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_activity_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_user_id` int(10) unsigned DEFAULT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_aal_admin` (`admin_id`),
  CONSTRAINT `fk_aal_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_activity_logs`
--

LOCK TABLES `admin_activity_logs` WRITE;
/*!40000 ALTER TABLE `admin_activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_profiles`
--

DROP TABLE IF EXISTS `admin_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `designation` varchar(100) NOT NULL DEFAULT 'Administrator',
  `phone` varchar(25) DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `fk_ap_dept` (`department_id`),
  CONSTRAINT `fk_ap_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ap_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_profiles`
--

LOCK TABLES `admin_profiles` WRITE;
/*!40000 ALTER TABLE `admin_profiles` DISABLE KEYS */;
INSERT INTO `admin_profiles` VALUES (1,1,'EMP-SA01',NULL,'Principal System Administrator','9876543210','Admin Block, Room 101','2026-09-05 18:36:29','2026-09-05 18:36:29'),(2,2,'EMP-AD01',1,'Academic Dean / Registrar','9876543211','Admin Block, Room 204','2026-09-05 18:36:29','2026-09-05 18:36:29');
/*!40000 ALTER TABLE `admin_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_conversations`
--

DROP TABLE IF EXISTS `ai_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_conversations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'New Academic Chat',
  `mode` enum('assistant','search','planner','pdf_qa') DEFAULT 'assistant',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_aiconv_user` (`user_id`),
  CONSTRAINT `fk_aiconv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_conversations`
--

LOCK TABLES `ai_conversations` WRITE;
/*!40000 ALTER TABLE `ai_conversations` DISABLE KEYS */;
INSERT INTO `ai_conversations` VALUES (1,4,'What is 1NF?...','assistant','2026-09-08 08:56:19','2026-09-08 08:56:20'),(2,4,'Explain Boyce-Codd Normal Form...','assistant','2026-09-08 09:20:59','2026-09-08 09:20:59'),(3,15,'Explain DBMS Normalization (1NF to BCNF) with...','assistant','2026-09-08 15:54:23','2026-09-08 15:55:21');
/*!40000 ALTER TABLE `ai_conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_messages`
--

DROP TABLE IF EXISTS `ai_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) unsigned NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` longtext NOT NULL,
  `sources_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sources_json`)),
  `tokens_used` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_aimsg_conv` (`conversation_id`),
  CONSTRAINT `fk_aimsg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_messages`
--

LOCK TABLES `ai_messages` WRITE;
/*!40000 ALTER TABLE `ai_messages` DISABLE KEYS */;
INSERT INTO `ai_messages` VALUES (1,1,'user','What is 1NF?',NULL,0,'2026-09-08 08:56:19'),(2,1,'assistant','### StudentOS AI Assistant Response\n\nI have analyzed your query regarding: **What is 1NF?**\n\nKey Takeaway: Consistent spaced repetition and focused practice are the most effective strategies for mastering this topic. Review your lecture notes in the Notes portal, practice end-of-unit problems, and take an AI mock quiz to gauge your understanding.\n\n*Tip: You can configure a Google Gemini API Key in Super Admin -> AI Settings to unlock live LLM capabilities.*',NULL,0,'2026-09-08 08:56:20'),(3,2,'user','Explain Boyce-Codd Normal Form',NULL,0,'2026-09-08 09:20:59'),(4,2,'assistant','### StudentOS AI Assistant Response\n\nI have analyzed your query regarding: **Explain Boyce-Codd Normal Form**\n\nKey Takeaway: Consistent spaced repetition and focused practice are the most effective strategies for mastering this topic. Review your lecture notes in the Notes portal, practice end-of-unit problems, and take an AI mock quiz to gauge your understanding.\n\n*Tip: You can configure a Google Gemini API Key in Super Admin -> AI Settings to unlock live LLM capabilities.*',NULL,0,'2026-09-08 09:20:59'),(5,3,'user','Explain DBMS Normalization (1NF to BCNF) with simple real-world examples.',NULL,0,'2026-09-08 15:54:23'),(6,3,'assistant','### Database Normalization Overview\n\n**Normalization** is the systematic process of organizing relational tables to minimize redundancy and prevent insert/update/delete anomalies.\n\n1. **1NF**: All table columns hold atomic (indivisible) values with no repeating groups.\n2. **2NF**: Table is in 1NF and contains no *partial functional dependencies* (all non-key attributes fully depend on the entire candidate key).\n3. **3NF**: Table is in 2NF and has no *transitive dependencies* (for every FD $X \\to A$, either $X$ is a superkey or $A$ is prime).\n4. **BCNF (Boyce-Codd Normal Form)**: A stricter variant of 3NF. For every non-trivial functional dependency $X \\to A$, $X$ MUST be a superkey.',NULL,0,'2026-09-08 15:54:23'),(7,3,'user','Explain DBMS Normalization (2NF to BCNF) with simple real-world examples.',NULL,0,'2026-09-08 15:55:01'),(8,3,'assistant','### Database Normalization Overview\n\n**Normalization** is the systematic process of organizing relational tables to minimize redundancy and prevent insert/update/delete anomalies.\n\n1. **1NF**: All table columns hold atomic (indivisible) values with no repeating groups.\n2. **2NF**: Table is in 1NF and contains no *partial functional dependencies* (all non-key attributes fully depend on the entire candidate key).\n3. **3NF**: Table is in 2NF and has no *transitive dependencies* (for every FD $X \\to A$, either $X$ is a superkey or $A$ is prime).\n4. **BCNF (Boyce-Codd Normal Form)**: A stricter variant of 3NF. For every non-trivial functional dependency $X \\to A$, $X$ MUST be a superkey.',NULL,0,'2026-09-08 15:55:01'),(9,3,'user','explain daa',NULL,0,'2026-09-08 15:55:21'),(10,3,'assistant','### StudentOS AI Assistant Response\n\nI have analyzed your query regarding: **explain daa**\n\nKey Takeaway: Consistent spaced repetition and focused practice are the most effective strategies for mastering this topic. Review your lecture notes in the Notes portal, practice end-of-unit problems, and take an AI mock quiz to gauge your understanding.\n\n*Tip: You can configure a Google Gemini API Key in Super Admin -> AI Settings to unlock live LLM capabilities.*',NULL,0,'2026-09-08 15:55:21');
/*!40000 ALTER TABLE `ai_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_quiz_questions`
--

DROP TABLE IF EXISTS `ai_quiz_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_quiz_questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` int(10) unsigned NOT NULL,
  `question_text` text NOT NULL,
  `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`options_json`)),
  `correct_answer` varchar(255) NOT NULL,
  `explanation` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_aiqq_quiz` (`quiz_id`),
  CONSTRAINT `fk_aiqq_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `ai_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_quiz_questions`
--

LOCK TABLES `ai_quiz_questions` WRITE;
/*!40000 ALTER TABLE `ai_quiz_questions` DISABLE KEYS */;
INSERT INTO `ai_quiz_questions` VALUES (1,1,'Which normal form requires eliminating partial dependencies on a candidate key?','[\"First Normal Form (1NF)\",\"Second Normal Form (2NF)\",\"Third Normal Form (3NF)\",\"Boyce-Codd Normal Form (BCNF)\"]','Second Normal Form (2NF)','2NF requires that every non-prime attribute is fully functionally dependent on the entire candidate key.'),(2,1,'In BCNF, for every functional dependency X -> Y, what condition must X satisfy?','[\"X must be a prime attribute\",\"X must be a superkey\",\"Y must be a superkey\",\"X must have only atomic values\"]','X must be a superkey','BCNF strictly dictates that the determinant X in any non-trivial functional dependency must be a superkey.'),(3,1,'What is an anomaly prevented by 3NF?','[\"Transitive dependency anomaly\",\"Partial dependency anomaly\",\"Non-atomic domain anomaly\",\"Cyclic dependency anomaly\"]','Transitive dependency anomaly','3NF explicitly removes transitive dependencies between non-prime attributes.'),(4,1,'Which of the following decomposition properties is strictly guaranteed by BCNF?','[\"Lossless join decomposition\",\"Dependency preservation in all cases\",\"Minimal key cardinality\",\"Equi-join redundancy\"]','Lossless join decomposition','BCNF always guarantees a lossless join decomposition, though dependency preservation is not always achievable without 3NF.'),(5,1,'A relation is in 1NF if and only if:','[\"All attribute domains contain only atomic values\",\"There are no foreign keys\",\"Every column has a unique name\",\"All candidate keys have size 1\"]','All attribute domains contain only atomic values','1NF disallows multi-valued attributes and composite repeating groups.'),(6,2,'Which normal form requires eliminating partial dependencies on a candidate key?','[\"First Normal Form (1NF)\",\"Second Normal Form (2NF)\",\"Third Normal Form (3NF)\",\"Boyce-Codd Normal Form (BCNF)\"]','Second Normal Form (2NF)','2NF requires that every non-prime attribute is fully functionally dependent on the entire candidate key.'),(7,2,'In BCNF, for every functional dependency X -> Y, what condition must X satisfy?','[\"X must be a prime attribute\",\"X must be a superkey\",\"Y must be a superkey\",\"X must have only atomic values\"]','X must be a superkey','BCNF strictly dictates that the determinant X in any non-trivial functional dependency must be a superkey.'),(8,2,'What is an anomaly prevented by 3NF?','[\"Transitive dependency anomaly\",\"Partial dependency anomaly\",\"Non-atomic domain anomaly\",\"Cyclic dependency anomaly\"]','Transitive dependency anomaly','3NF explicitly removes transitive dependencies between non-prime attributes.'),(9,2,'Which of the following decomposition properties is strictly guaranteed by BCNF?','[\"Lossless join decomposition\",\"Dependency preservation in all cases\",\"Minimal key cardinality\",\"Equi-join redundancy\"]','Lossless join decomposition','BCNF always guarantees a lossless join decomposition, though dependency preservation is not always achievable without 3NF.'),(10,2,'A relation is in 1NF if and only if:','[\"All attribute domains contain only atomic values\",\"There are no foreign keys\",\"Every column has a unique name\",\"All candidate keys have size 1\"]','All attribute domains contain only atomic values','1NF disallows multi-valued attributes and composite repeating groups.');
/*!40000 ALTER TABLE `ai_quiz_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_quizzes`
--

DROP TABLE IF EXISTS `ai_quizzes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_quizzes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `topic` varchar(255) NOT NULL,
  `total_questions` int(11) NOT NULL DEFAULT 5,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_aiq_user` (`user_id`),
  KEY `fk_aiq_sub` (`subject_id`),
  CONSTRAINT `fk_aiq_sub` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aiq_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_quizzes`
--

LOCK TABLES `ai_quizzes` WRITE;
/*!40000 ALTER TABLE `ai_quizzes` DISABLE KEYS */;
INSERT INTO `ai_quizzes` VALUES (1,4,1,'Operating Systems',5,'medium',NULL,'2026-09-08 08:56:46'),(2,4,1,'Database Normalization',5,'medium',NULL,'2026-09-08 09:21:00');
/*!40000 ALTER TABLE `ai_quizzes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_recommendations`
--

DROP TABLE IF EXISTS `ai_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_recommendations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` enum('study','assignment','attendance','exam','skill') DEFAULT 'study',
  `suggestion` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('unread','read','applied') DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_airec_user` (`user_id`),
  CONSTRAINT `fk_airec_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_recommendations`
--

LOCK TABLES `ai_recommendations` WRITE;
/*!40000 ALTER TABLE `ai_recommendations` DISABLE KEYS */;
INSERT INTO `ai_recommendations` VALUES (1,4,'Focus on Dynamic Programming Practice','study','Based on your upcoming Algorithms Internal Assessment on Sept 25, spend 45 minutes practicing 0/1 Knapsack variations.','high','unread','2026-09-05 18:36:31'),(2,4,'Review Operating Systems Virtual Memory','exam','Your last quiz indicated slight confusion in Page Replacement Algorithms (LRU vs FIFO). Review Unit 4 notes.','medium','unread','2026-09-05 18:36:31'),(3,6,'Focus Revision on Upcoming Midterm Subjects','study','Based on your enrolled courses and syllabus weightage, allocate 45 minutes to core theory revision today.','high','unread','2026-09-08 17:42:11'),(4,6,'Maintain Consistent Attendance Above 85%','attendance','Keep up your stellar classroom attendance to maintain eligibility for honors distinction.','medium','unread','2026-09-08 17:42:11'),(5,7,'Focus Revision on Upcoming Midterm Subjects','study','Based on your enrolled courses and syllabus weightage, allocate 45 minutes to core theory revision today.','high','unread','2026-09-08 17:42:11'),(6,7,'Maintain Consistent Attendance Above 85%','attendance','Keep up your stellar classroom attendance to maintain eligibility for honors distinction.','medium','unread','2026-09-08 17:42:11'),(7,12,'Focus Revision on Upcoming Midterm Subjects','study','Based on your enrolled courses and syllabus weightage, allocate 45 minutes to core theory revision today.','high','unread','2026-09-08 17:42:11'),(8,12,'Maintain Consistent Attendance Above 85%','attendance','Keep up your stellar classroom attendance to maintain eligibility for honors distinction.','medium','unread','2026-09-08 17:42:11'),(9,13,'Focus Revision on Upcoming Midterm Subjects','study','Based on your enrolled courses and syllabus weightage, allocate 45 minutes to core theory revision today.','high','unread','2026-09-08 17:42:11'),(10,13,'Maintain Consistent Attendance Above 85%','attendance','Keep up your stellar classroom attendance to maintain eligibility for honors distinction.','medium','unread','2026-09-08 17:42:11'),(11,14,'Focus Revision on Upcoming Midterm Subjects','study','Based on your enrolled courses and syllabus weightage, allocate 45 minutes to core theory revision today.','high','unread','2026-09-08 17:42:12'),(12,14,'Maintain Consistent Attendance Above 85%','attendance','Keep up your stellar classroom attendance to maintain eligibility for honors distinction.','medium','unread','2026-09-08 17:42:12'),(13,15,'Focus Revision on Upcoming Midterm Subjects','study','Based on your enrolled courses and syllabus weightage, allocate 45 minutes to core theory revision today.','high','unread','2026-09-08 17:42:12'),(14,15,'Maintain Consistent Attendance Above 85%','attendance','Keep up your stellar classroom attendance to maintain eligibility for honors distinction.','medium','unread','2026-09-08 17:42:12');
/*!40000 ALTER TABLE `ai_recommendations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_settings`
--

DROP TABLE IF EXISTS `ai_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_settings`
--

LOCK TABLES `ai_settings` WRITE;
/*!40000 ALTER TABLE `ai_settings` DISABLE KEYS */;
INSERT INTO `ai_settings` VALUES (1,'gemini_api_key','','Google Gemini API key for AI Tutor & RAG','2026-09-05 18:36:32'),(2,'default_model','gemini-1.5-flash','Default LLM model identifier','2026-09-05 18:36:32'),(3,'temperature','0.7','Sampling temperature for responses','2026-09-05 18:36:32'),(4,'max_output_tokens','2048','Maximum tokens per response','2026-09-05 18:36:32'),(5,'enable_rag','1','Enable PDF document chunking and contextual Q&A','2026-09-05 18:36:32');
/*!40000 ALTER TABLE `ai_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_study_plans`
--

DROP TABLE IF EXISTS `ai_study_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_study_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `plan_content` longtext NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_aisp_user` (`user_id`),
  KEY `fk_aisp_sub` (`subject_id`),
  CONSTRAINT `fk_aisp_sub` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aisp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_study_plans`
--

LOCK TABLES `ai_study_plans` WRITE;
/*!40000 ALTER TABLE `ai_study_plans` DISABLE KEYS */;
INSERT INTO `ai_study_plans` VALUES (1,4,1,'DBMS Midterm Prep - 14 Day Plan','Day 1-3: Relational Algebra & Calculus\nDay 4-7: SQL Queries, Triggers & Views\nDay 8-10: 1NF to BCNF Normalization\nDay 11-12: Transaction Management & ACID\nDay 13-14: Mock Quizzes & Past Year Papers','2026-09-08','2026-09-21',1,'2026-09-05 18:36:31'),(2,4,1,'Database Management Systems Exam Study Plan (14 Days)','### Database Normalization Overview\n\n**Normalization** is the systematic process of organizing relational tables to minimize redundancy and prevent insert/update/delete anomalies.\n\n1. **1NF**: All table columns hold atomic (indivisible) values with no repeating groups.\n2. **2NF**: Table is in 1NF and contains no *partial functional dependencies* (all non-key attributes fully depend on the entire candidate key).\n3. **3NF**: Table is in 2NF and has no *transitive dependencies* (for every FD  \\to A$, either $ is a superkey or $ is prime).\n4. **BCNF (Boyce-Codd Normal Form)**: A stricter variant of 3NF. For every non-trivial functional dependency  \\to A$, $ MUST be a superkey.','2026-09-08','2026-09-22',1,'2026-09-08 08:56:38'),(3,4,1,'Database Management Systems Exam Study Plan (14 Days)','### 📅 Structured Academic Study Plan\n\n**Day 1–2: Theoretical Foundations & Architecture**\n- Review core concepts, definitions, and architectural diagrams.\n- Complete 5 self-assessment diagnostic questions.\n\n**Day 3–4: Core Problem Solving & Decompositions**\n- Solve medium-difficulty algorithmic problems and case proofs.\n- Review previous semester examination patterns.\n\n**Day 5–6: Advanced Applications & Lab Implementations**\n- Practice practical implementations, edge cases, and optimizations.\n- Complete a 60-minute timed active recall session.\n\n**Day 7: Mock Exam & Comprehensive Revision**\n- Take an AI-generated mock quiz.\n- Focus on weak spots identified in review.','2026-09-08','2026-09-22',1,'2026-09-08 09:20:59');
/*!40000 ALTER TABLE `ai_study_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_usage_logs`
--

DROP TABLE IF EXISTS `ai_usage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_usage_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `feature` varchar(50) NOT NULL,
  `prompt_tokens` int(11) DEFAULT 0,
  `response_tokens` int(11) DEFAULT 0,
  `model` varchar(50) DEFAULT 'gemini-1.5-flash',
  `cost` decimal(8,6) DEFAULT 0.000000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_aiul_user` (`user_id`),
  CONSTRAINT `fk_aiul_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_usage_logs`
--

LOCK TABLES `ai_usage_logs` WRITE;
/*!40000 ALTER TABLE `ai_usage_logs` DISABLE KEYS */;
INSERT INTO `ai_usage_logs` VALUES (1,4,'assistant',3,115,'gemini-1.5-flash',0.000000,'2026-09-08 08:56:20'),(2,4,'pdf_qa',97,170,'gemini-1.5-flash',0.000000,'2026-09-08 08:56:26'),(3,4,'assistant',7,119,'gemini-1.5-flash',0.000000,'2026-09-08 09:20:59'),(4,4,'pdf_qa',236,241,'gemini-1.5-flash',0.000000,'2026-09-08 09:20:59'),(5,15,'assistant',18,172,'gemini-1.5-flash',0.000000,'2026-09-08 15:54:23'),(6,15,'assistant',18,172,'gemini-1.5-flash',0.000000,'2026-09-08 15:55:01'),(7,15,'assistant',2,115,'gemini-1.5-flash',0.000000,'2026-09-08 15:55:21');
/*!40000 ALTER TABLE `ai_usage_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignment_submissions`
--

DROP TABLE IF EXISTS `assignment_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `submission_text` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `marks_obtained` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(10) unsigned DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `graded_at` datetime DEFAULT NULL,
  `status` enum('submitted','late','graded','resubmitted') DEFAULT 'submitted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sub` (`assignment_id`,`student_id`),
  KEY `fk_asb_student` (`student_id`),
  KEY `fk_asb_grader` (`graded_by`),
  CONSTRAINT `fk_asb_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asb_grader` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_asb_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignment_submissions`
--

LOCK TABLES `assignment_submissions` WRITE;
/*!40000 ALTER TABLE `assignment_submissions` DISABLE KEYS */;
INSERT INTO `assignment_submissions` VALUES (1,1,4,'Uploaded comprehensive proof document demonstrating BCNF decomposition without loss of dependencies.',NULL,48.00,'Excellent mathematical proof and clear dependency diagrams!',3,'2026-09-05 18:36:30',NULL,'graded');
/*!40000 ALTER TABLE `assignment_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` int(10) unsigned NOT NULL,
  `faculty_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `deadline` datetime NOT NULL,
  `max_marks` int(11) NOT NULL DEFAULT 100,
  `attachment_path` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','closed') DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_asg_subject` (`subject_id`),
  KEY `fk_asg_faculty` (`faculty_id`),
  CONSTRAINT `fk_asg_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_asg_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
INSERT INTO `assignments` VALUES (1,1,3,'DBMS Normalization & BCNF Case Study','Decompose a university registrar relation into 3NF and BCNF. Provide dependency preservation proofs.','Submit typed PDF with dependency diagram and step-by-step reasoning.','2026-09-12 23:59:00',50,NULL,'published','2026-09-05 18:36:30','2026-09-05 18:36:30',NULL),(2,2,5,'Dynamic Programming: Knapsack & Edit Distance','Implement 0/1 Knapsack and Levenshtein distance algorithms. Analyze space optimization approaches.','Include GitHub repo link or zipped source code with automated benchmark tests.','2026-09-15 18:00:00',50,NULL,'published','2026-09-05 18:36:30','2026-09-05 18:36:30',NULL),(3,4,5,'Secure REST API Implementation in PHP/Flask','Build an authenticated endpoints suite with JWT, input sanitization, and rate-limiting.','Follow production security guidelines from OWASP Top 10.','2026-09-20 23:59:00',100,NULL,'published','2026-09-05 18:36:30','2026-09-05 18:36:30',NULL);
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `faculty_id` int(10) unsigned NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_att` (`subject_id`,`student_id`,`date`),
  KEY `fk_att_stu` (`student_id`),
  KEY `fk_att_fac` (`faculty_id`),
  CONSTRAINT `fk_att_fac` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_stu` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_sub` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,1,4,3,'2026-08-25','present','On time','2026-09-05 18:36:30'),(2,1,4,3,'2026-08-27','present','Active in discussion','2026-09-05 18:36:30'),(3,1,4,3,'2026-08-29','present','On time','2026-09-05 18:36:30'),(4,1,4,3,'2026-09-01','present','On time','2026-09-05 18:36:30'),(5,1,4,3,'2026-09-03','absent','Medical leave submitted','2026-09-05 18:36:30'),(6,2,4,5,'2026-08-26','present','Good participation','2026-09-05 18:36:30'),(7,2,4,5,'2026-08-28','present','On time','2026-09-05 18:36:30'),(8,2,4,5,'2026-09-02','present','On time','2026-09-05 18:36:30'),(9,2,4,5,'2026-09-04','late','Arrived 10 mins late','2026-09-05 18:36:30'),(10,3,4,3,'2026-08-26','present','On time','2026-09-05 18:36:30'),(11,3,4,3,'2026-08-28','present','On time','2026-09-05 18:36:30'),(12,3,4,3,'2026-09-02','present','On time','2026-09-05 18:36:30'),(13,4,4,5,'2026-08-25','present','Lab work completed','2026-09-05 18:36:30'),(14,4,4,5,'2026-08-29','present','Lab demo verified','2026-09-05 18:36:30');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `resource` varchar(100) NOT NULL,
  `resource_id` int(10) unsigned DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_al_user` (`user_id`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (3,10,'SYSTEM_CACHE_FLUSH','system',NULL,'Root Super Admin executed manual system cache flush','::1','2026-09-08 13:40:24');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_logs`
--

DROP TABLE IF EXISTS `backup_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL DEFAULT 0,
  `backup_type` enum('database','files','full') DEFAULT 'database',
  `status` enum('success','failed') DEFAULT 'success',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_bl_user` (`created_by`),
  CONSTRAINT `fk_bl_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_logs`
--

LOCK TABLES `backup_logs` WRITE;
/*!40000 ALTER TABLE `backup_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `backup_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calendar_events`
--

DROP TABLE IF EXISTS `calendar_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('class','exam','deadline','event','personal','session') DEFAULT 'personal',
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `is_all_day` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_calevents_user` (`user_id`),
  CONSTRAINT `fk_calevents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_events`
--

LOCK TABLES `calendar_events` WRITE;
/*!40000 ALTER TABLE `calendar_events` DISABLE KEYS */;
INSERT INTO `calendar_events` VALUES (1,4,'Midterm Examination Week','University midterm exam schedule','exam','2026-09-22 09:30:00','2026-09-22 12:30:00','Exam Hall 3',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(2,4,'Major Assignment Submission','Final submission deadline for coursework','deadline','2026-09-25 23:59:00','2026-09-25 23:59:00','Online Portal',1,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(3,4,'Generative AI Workshop','Hands-on LLM session','event','2026-09-28 10:00:00','2026-09-28 14:00:00','Auditorium Hall 1',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(4,4,'Guest Lecture: Modern Systems','Expert seminar','class','2026-10-02 11:00:00','2026-10-02 13:00:00','Seminar Hall B',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(5,6,'Midterm Examination Week','University midterm exam schedule','exam','2026-09-22 09:30:00','2026-09-22 12:30:00','Exam Hall 3',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(6,6,'Major Assignment Submission','Final submission deadline for coursework','deadline','2026-09-25 23:59:00','2026-09-25 23:59:00','Online Portal',1,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(7,6,'Generative AI Workshop','Hands-on LLM session','event','2026-09-28 10:00:00','2026-09-28 14:00:00','Auditorium Hall 1',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(8,6,'Guest Lecture: Modern Systems','Expert seminar','class','2026-10-02 11:00:00','2026-10-02 13:00:00','Seminar Hall B',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(9,7,'Midterm Examination Week','University midterm exam schedule','exam','2026-09-22 09:30:00','2026-09-22 12:30:00','Exam Hall 3',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(10,7,'Major Assignment Submission','Final submission deadline for coursework','deadline','2026-09-25 23:59:00','2026-09-25 23:59:00','Online Portal',1,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(11,7,'Generative AI Workshop','Hands-on LLM session','event','2026-09-28 10:00:00','2026-09-28 14:00:00','Auditorium Hall 1',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(12,7,'Guest Lecture: Modern Systems','Expert seminar','class','2026-10-02 11:00:00','2026-10-02 13:00:00','Seminar Hall B',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(13,12,'Midterm Examination Week','University midterm exam schedule','exam','2026-09-22 09:30:00','2026-09-22 12:30:00','Exam Hall 3',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(14,12,'Major Assignment Submission','Final submission deadline for coursework','deadline','2026-09-25 23:59:00','2026-09-25 23:59:00','Online Portal',1,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(15,12,'Generative AI Workshop','Hands-on LLM session','event','2026-09-28 10:00:00','2026-09-28 14:00:00','Auditorium Hall 1',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(16,12,'Guest Lecture: Modern Systems','Expert seminar','class','2026-10-02 11:00:00','2026-10-02 13:00:00','Seminar Hall B',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(17,13,'Midterm Examination Week','University midterm exam schedule','exam','2026-09-22 09:30:00','2026-09-22 12:30:00','Exam Hall 3',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(18,13,'Major Assignment Submission','Final submission deadline for coursework','deadline','2026-09-25 23:59:00','2026-09-25 23:59:00','Online Portal',1,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(19,13,'Generative AI Workshop','Hands-on LLM session','event','2026-09-28 10:00:00','2026-09-28 14:00:00','Auditorium Hall 1',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(20,13,'Guest Lecture: Modern Systems','Expert seminar','class','2026-10-02 11:00:00','2026-10-02 13:00:00','Seminar Hall B',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(21,14,'Midterm Examination Week','University midterm exam schedule','exam','2026-09-22 09:30:00','2026-09-22 12:30:00','Exam Hall 3',0,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(22,14,'Major Assignment Submission','Final submission deadline for coursework','deadline','2026-09-25 23:59:00','2026-09-25 23:59:00','Online Portal',1,'2026-09-08 17:42:07','2026-09-08 17:42:07'),(23,14,'Generative AI Workshop','Hands-on LLM session','event','2026-09-28 10:00:00','2026-09-28 14:00:00','Auditorium Hall 1',0,'2026-09-08 17:42:08','2026-09-08 17:42:08'),(24,14,'Guest Lecture: Modern Systems','Expert seminar','class','2026-10-02 11:00:00','2026-10-02 13:00:00','Seminar Hall B',0,'2026-09-08 17:42:08','2026-09-08 17:42:08'),(25,15,'Midterm Examination Week','University midterm exam schedule','exam','2026-09-22 09:30:00','2026-09-22 12:30:00','Exam Hall 3',0,'2026-09-08 17:42:08','2026-09-08 17:42:08'),(26,15,'Major Assignment Submission','Final submission deadline for coursework','deadline','2026-09-25 23:59:00','2026-09-25 23:59:00','Online Portal',1,'2026-09-08 17:42:08','2026-09-08 17:42:08'),(27,15,'Generative AI Workshop','Hands-on LLM session','event','2026-09-28 10:00:00','2026-09-28 14:00:00','Auditorium Hall 1',0,'2026-09-08 17:42:08','2026-09-08 17:42:08'),(28,15,'Guest Lecture: Modern Systems','Expert seminar','class','2026-10-02 11:00:00','2026-10-02 13:00:00','Seminar Hall B',0,'2026-09-08 17:42:08','2026-09-08 17:42:08');
/*!40000 ALTER TABLE `calendar_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_schedules`
--

DROP TABLE IF EXISTS `class_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` int(10) unsigned NOT NULL,
  `faculty_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned NOT NULL,
  `course_id` int(10) unsigned NOT NULL,
  `semester` varchar(20) NOT NULL,
  `section` varchar(10) NOT NULL DEFAULT 'A',
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_cs_subject` (`subject_id`),
  KEY `fk_cs_faculty` (`faculty_id`),
  CONSTRAINT `fk_cs_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cs_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_schedules`
--

LOCK TABLES `class_schedules` WRITE;
/*!40000 ALTER TABLE `class_schedules` DISABLE KEYS */;
INSERT INTO `class_schedules` VALUES (1,1,3,1,2,'5','A','Monday','09:00:00','10:00:00','LH-201','2026-09-05 18:36:30','2026-09-08 14:16:35'),(2,2,5,1,2,'5','A','Monday','10:15:00','11:15:00','LH-201','2026-09-05 18:36:30','2026-09-08 14:16:35'),(3,3,3,1,2,'5','A','Tuesday','09:00:00','10:00:00','LH-203','2026-09-05 18:36:30','2026-09-08 14:16:35'),(4,4,5,1,2,'5','A','Tuesday','11:30:00','12:30:00','Lab-3','2026-09-05 18:36:30','2026-09-08 14:16:35'),(5,1,3,1,2,'5','A','Wednesday','09:00:00','10:00:00','LH-201','2026-09-05 18:36:30','2026-09-08 14:16:35'),(6,2,5,1,2,'5','A','Thursday','10:00:00','11:00:00','LH-201','2026-09-05 18:36:30','2026-09-08 14:16:35'),(7,4,5,1,2,'5','A','Friday','14:00:00','16:00:00','Lab-3','2026-09-05 18:36:30','2026-09-08 14:16:35');
/*!40000 ALTER TABLE `class_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `department_id` int(10) unsigned NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_years` int(11) NOT NULL DEFAULT 4,
  `total_semesters` int(11) NOT NULL DEFAULT 8,
  `degree_type` varchar(50) NOT NULL DEFAULT 'Bachelor',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_courses_dept` (`department_id`),
  CONSTRAINT `fk_courses_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,4,'BBA','BBA','Bachelor of Business Administration',3,6,'Bachelor','active','2026-09-05 18:36:28','2026-09-08 14:16:35'),(2,1,'BCA','BCA','Bachelor of Computer Applications',3,6,'Bachelor','active','2026-09-05 18:36:28','2026-09-08 14:16:35');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `head_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'CSE','Computer Science & Engineering','Department of Computing, Software Systems and Artificial Intelligence',3,'active','2026-09-05 18:36:28','2026-09-05 18:36:28'),(2,'ECE','Electronics & Communication','Department of Microelectronics, Embedded Systems and Telecommunications',5,'active','2026-09-05 18:36:28','2026-09-05 18:36:28'),(3,'MECH','Mechanical Engineering','Department of Robotics, Mechanics and Thermal Engineering',NULL,'active','2026-09-05 18:36:28','2026-09-05 18:36:28'),(4,'MGMT','School of Business & Management','Department of Technology Leadership and Business Analytics',NULL,'active','2026-09-05 18:36:28','2026-09-05 18:36:28');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_chunks`
--

DROP TABLE IF EXISTS `document_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_chunks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `chunk_index` int(11) NOT NULL,
  `chunk_text` longtext NOT NULL,
  `embedding_vector` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`embedding_vector`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_dc_doc` (`document_id`),
  CONSTRAINT `fk_dc_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_chunks`
--

LOCK TABLES `document_chunks` WRITE;
/*!40000 ALTER TABLE `document_chunks` DISABLE KEYS */;
INSERT INTO `document_chunks` VALUES (1,1,1,'Unit 3: Functional Dependencies and Normalization. A functional dependency X -> Y specifies that the value of attribute set X uniquely determines the value of attribute set Y in a relation R. If X is a candidate key or superkey, the dependency is well-behaved. Trivial functional dependencies occur when Y is a subset of X.',NULL,'2026-09-08 09:19:28'),(2,1,2,'First Normal Form (1NF): A relation schema R is in 1NF if every attribute value in every tuple is atomic (indivisible) and there are no repeating groups or multivalued attributes. Second Normal Form (2NF): R is in 2NF if it is in 1NF and every non-prime attribute is fully functionally dependent on the entire primary key (no partial dependencies on a subset of a composite primary key).',NULL,'2026-09-08 09:19:28'),(3,1,3,'Third Normal Form (3NF): A relation schema R is in 3NF if whenever a non-trivial functional dependency X -> A holds in R, either X is a superkey of R, or A is a prime attribute (a member of some candidate key). 3NF eliminates transitive dependencies (e.g. A -> B and B -> C where non-prime C depends on non-prime B).',NULL,'2026-09-08 09:19:28'),(4,1,4,'Boyce-Codd Normal Form (BCNF): A relation schema R is in BCNF if whenever a non-trivial functional dependency X -> A holds in R, X is strictly a superkey of R. BCNF is stricter than 3NF because it removes the exception where A is a prime attribute. Decomposition into BCNF always achieves lossless join, but dependency preservation is not always guaranteed.',NULL,'2026-09-08 09:19:28'),(5,2,1,'Asymptotic Complexity and Big-O Notation: Worst-case upper bound is denoted by Big-O (O(n)), average-case tight bound by Big-Theta (Theta(n)), and lower bound by Big-Omega (Omega(n)). Common complexities include logarithmic O(log n) for binary search, linearithmic O(n log n) for merge sort and heap sort, and quadratic O(n^2) for naive bubble sort.',NULL,'2026-09-08 09:19:28'),(6,2,2,'Red-Black Trees: A self-balancing binary search tree where every node contains a color bit (red or black). Properties: 1. Root is always black. 2. No two consecutive red nodes (red node children must be black). 3. Every simple path from a node to descendant leaves contains the same number of black nodes (black height). Insertion and deletion operations take O(log n) time via color flips and tree rotations (left rotation, right rotation).',NULL,'2026-09-08 09:19:28'),(7,2,3,'Graph Traversal Algorithms: Breadth-First Search (BFS) uses a FIFO queue and visits vertices level by level; it finds the shortest path in unweighted graphs with time complexity O(V + E). Depth-First Search (DFS) uses a LIFO stack (or recursion) to explore paths deeply before backtracking, useful for topological sorting and cycle detection in directed acyclic graphs (DAGs).',NULL,'2026-09-08 09:19:28'),(8,3,1,'Process Synchronization and Critical Section Problem: A critical section is a segment of code where shared resources (memory, variables, files) are accessed. Any solution must satisfy three criteria: 1. Mutual Exclusion (only one process in critical section at a time). 2. Progress (selection of next process cannot be postponed indefinitely). 3. Bounded Waiting (limit on the number of times other processes enter critical section before a request is granted).',NULL,'2026-09-08 09:19:28'),(9,3,2,'Semaphores and Mutex Locks: A mutex lock provides binary mutual exclusion using acquire() and release(). A counting semaphore has an integer value with two atomic operations: wait() (or P) which decrements the counter and blocks if negative, and signal() (or V) which increments the counter and unblocks waiting processes. Misuse can lead to deadlocks or priority inversion.',NULL,'2026-09-08 09:19:28'),(10,3,3,'Deadlock Characterization and Coffman Conditions: Four simultaneous conditions cause deadlock: 1. Mutual Exclusion. 2. Hold and Wait. 3. No Preemption. 4. Circular Wait. Banker\'s Algorithm by Edsger Dijkstra is a deadlock avoidance algorithm that tests for safety by simulating resource allocation for maximum declared needs before granting any request, ensuring the system remains in a safe state.',NULL,'2026-09-08 09:19:28');
/*!40000 ALTER TABLE `document_chunks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `file_type` varchar(50) NOT NULL DEFAULT 'application/pdf',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_docs_user` (`user_id`),
  KEY `fk_docs_sub` (`subject_id`),
  CONSTRAINT `fk_docs_sub` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_docs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,1,1,'DBMS_Unit_3_Normalization_Complete.pdf','storage/uploads/documents/dbms_unit_3.pdf',1048576,'application/pdf','Comprehensive textbook chapter on Relational Database Design, Functional Dependencies, and Normal Forms 1NF through BCNF.',1,'2026-09-08 09:19:28','2026-09-08 09:19:28'),(2,1,2,'Data_Structures_Algorithms_Lectures_1_to_10.pdf','storage/uploads/documents/dsa_lectures.pdf',2097152,'application/pdf','Lecture notes covering asymptotic analysis, balanced search trees (AVL, Red-Black Trees), and graph traversal algorithms.',1,'2026-09-08 09:19:28','2026-09-08 09:19:28'),(3,1,3,'OS_Concurrency_Deadlocks_Slides.pdf','storage/uploads/documents/os_concurrency.pdf',1572864,'application/pdf','Lecture slides on process synchronization, mutex locks, semaphores, classic IPC problems, and Banker\'s Deadlock Avoidance Algorithm.',1,'2026-09-08 09:19:28','2026-09-08 09:19:28');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verification_tokens`
--

DROP TABLE IF EXISTS `email_verification_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_verification_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `fk_evt_user` (`user_id`),
  CONSTRAINT `fk_evt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_verification_tokens`
--

LOCK TABLES `email_verification_tokens` WRITE;
/*!40000 ALTER TABLE `email_verification_tokens` DISABLE KEYS */;
INSERT INTO `email_verification_tokens` VALUES (2,12,'b6589ddb99e78ed746c983a95ffae3a7e025623464524c71ffa569eea4630344','2026-09-09 16:18:22','2026-09-08 14:18:22'),(3,13,'183d33b2542d5b40eb0c2d07559b42e548c7c792f02b7c0381d048fddcd04251','2026-09-09 16:18:39','2026-09-08 14:18:39'),(4,14,'be03921efbeb4ebadc9a8e8653d1bc5eb4726f728def1495b1e4460af68329c8','2026-09-09 16:31:26','2026-09-08 14:31:26'),(5,15,'e18fa993972fc6ddcb4551bc7b2a044d6dd74a111c27d76ec08e3305f2b7d143','2026-09-09 17:48:52','2026-09-08 15:48:52');
/*!40000 ALTER TABLE `email_verification_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `venue` varchar(150) DEFAULT NULL,
  `organized_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
INSERT INTO `events` VALUES (1,'Generative AI & LLM Systems Workshop','Hands-on bootcamp on Large Language Models, prompt engineering, RAG pipelines, and building autonomous agent workflows.','2026-09-28','10:00:00','14:00:00','Auditorium Hall 1','Department of Computer Applications & AI Club','2026-09-08 17:42:06'),(2,'Annual 24-Hour Campus Hackathon 2026','Flagship inter-college hackathon focusing on AI for education, smart fintech, and sustainability solutions.','2026-10-05','09:00:00','09:00:00','Innovation Hub & Labs','Student Council & Tech Innovation Cell','2026-09-08 17:42:06'),(3,'Executive Guest Lecture: Corporate Strategy & Fintech','Distinguished leadership lecture on digital transformation, global financial markets, and strategic agile management.','2026-10-12','14:30:00','16:30:00','Management Seminar Hall B','Department of Management Studies','2026-09-08 17:42:06'),(4,'Cloud Infrastructure & Kubernetes Deep Dive','Interactive session covering microservices orchestration, Docker containers, and scalable cloud deployment.','2026-10-20','11:00:00','13:00:00','Seminar Hall C','Cloud Computing Research Group','2026-09-08 17:42:06');
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_answers`
--

DROP TABLE IF EXISTS `exam_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_answers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` int(10) unsigned NOT NULL,
  `question_id` int(10) unsigned NOT NULL,
  `selected_option_id` int(10) unsigned DEFAULT NULL,
  `answer_text` text DEFAULT NULL,
  `marks_awarded` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_ans_att` (`attempt_id`),
  KEY `fk_ans_q` (`question_id`),
  CONSTRAINT `fk_ans_att` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ans_q` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_answers`
--

LOCK TABLES `exam_answers` WRITE;
/*!40000 ALTER TABLE `exam_answers` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_attempts`
--

DROP TABLE IF EXISTS `exam_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `exam_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `started_at` datetime NOT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `status` enum('in_progress','submitted','evaluated') DEFAULT 'in_progress',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ea_exam` (`exam_id`),
  KEY `fk_ea_stu` (`student_id`),
  CONSTRAINT `fk_ea_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ea_stu` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_attempts`
--

LOCK TABLES `exam_attempts` WRITE;
/*!40000 ALTER TABLE `exam_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exams`
--

DROP TABLE IF EXISTS `exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` int(10) unsigned NOT NULL,
  `faculty_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `exam_type` enum('quiz','midterm','final','assignment_test') DEFAULT 'midterm',
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `total_marks` int(11) NOT NULL DEFAULT 100,
  `passing_marks` int(11) NOT NULL DEFAULT 40,
  `instructions` text DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_exam_sub` (`subject_id`),
  KEY `fk_exam_fac` (`faculty_id`),
  CONSTRAINT `fk_exam_fac` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_sub` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exams`
--

LOCK TABLES `exams` WRITE;
/*!40000 ALTER TABLE `exams` DISABLE KEYS */;
INSERT INTO `exams` VALUES (1,1,3,'DBMS Midterm Examination','midterm','2026-09-22','10:00:00','12:00:00',50,20,'Calculators allowed. No electronic devices. All questions mandatory.','Auditorium A','scheduled','2026-09-05 18:36:30','2026-09-05 18:36:30'),(2,2,5,'Algorithms Internal Assessment 1','quiz','2026-09-25','14:00:00','15:00:00',25,10,'MCQ and short proofs.','LH-201','scheduled','2026-09-05 18:36:30','2026-09-05 18:36:30'),(3,1,3,'CS501 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(4,2,3,'CS502 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(5,3,3,'CS503 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(6,4,3,'CS504 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(7,5,3,'BBA101 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(8,6,3,'BBA102 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(9,11,3,'CS505 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(10,12,3,'CS401 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(11,13,3,'CS402 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(12,14,3,'CS403 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(13,15,3,'CS404 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(14,16,3,'CS405 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(15,17,3,'CS101 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(16,18,3,'CS102 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(17,19,3,'CS103 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(18,20,3,'CS104 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(19,21,3,'CS105 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(20,22,3,'BBA501 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(21,23,3,'BBA502 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(22,24,3,'BBA503 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(23,25,3,'BBA504 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(24,26,3,'BBA505 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(25,27,3,'BBA401 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(26,28,3,'BBA402 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(27,29,3,'BBA403 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:22','2026-09-08 17:33:22'),(28,30,3,'BBA404 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:23','2026-09-08 17:33:23'),(29,31,3,'BBA405 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:23','2026-09-08 17:33:23'),(30,34,3,'BBA103 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:23','2026-09-08 17:33:23'),(31,35,3,'BBA104 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:23','2026-09-08 17:33:23'),(32,36,3,'BBA105 End-Semester Examination','final','2025-12-15','09:30:00','12:30:00',100,40,NULL,NULL,'completed','2026-09-08 17:33:23','2026-09-08 17:33:23');
/*!40000 ALTER TABLE `exams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faculty_profiles`
--

DROP TABLE IF EXISTS `faculty_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faculty_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `department_id` int(10) unsigned NOT NULL,
  `designation` varchar(100) NOT NULL DEFAULT 'Assistant Professor',
  `qualification` varchar(150) DEFAULT NULL,
  `specialization` varchar(200) DEFAULT NULL,
  `office_location` varchar(100) DEFAULT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `fk_fp_dept` (`department_id`),
  CONSTRAINT `fk_fp_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_fp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faculty_profiles`
--

LOCK TABLES `faculty_profiles` WRITE;
/*!40000 ALTER TABLE `faculty_profiles` DISABLE KEYS */;
INSERT INTO `faculty_profiles` VALUES (1,3,'FAC-CS101',1,'Professor & HOD','Ph.D in Distributed Systems','Database Systems, Cloud Computing & AI','CS Block, Room 302','9876543212','2020-07-15','2026-09-05 18:36:29','2026-09-05 18:36:29'),(2,5,'FAC-CS102',1,'Associate Professor','M.Tech in Software Engineering','Algorithms, Data Structures & Web Tech','CS Block, Room 305','9876543214','2022-01-10','2026-09-05 18:36:29','2026-09-05 18:36:29'),(3,8,'FAC-2026-GMAIL',1,'Associate Professor',NULL,NULL,NULL,NULL,NULL,'2026-09-08 09:25:22','2026-09-08 09:25:22');
/*!40000 ALTER TABLE `faculty_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `category` varchar(50) DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_files_user` (`user_id`),
  CONSTRAINT `fk_files_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `files`
--

LOCK TABLES `files` WRITE;
/*!40000 ALTER TABLE `files` DISABLE KEYS */;
INSERT INTO `files` VALUES (1,1,'Academic_Calendar_2026_Semester_5_6.pdf','acad_cal_2026.pdf','storage/uploads/files/acad_cal_2026.pdf','application/pdf',1450000,'academic','2026-09-08 17:42:08'),(2,1,'Student_Code_of_Conduct_Handbook.pdf','conduct_handbook.pdf','storage/uploads/files/conduct_handbook.pdf','application/pdf',2840000,'administrative','2026-09-08 17:42:08'),(3,1,'Laboratory_Safety_Guidelines_Manual.pdf','lab_safety_manual.pdf','storage/uploads/files/lab_safety_manual.pdf','application/pdf',980000,'guidelines','2026-09-08 17:42:08'),(4,1,'Project_Report_Standard_Template.docx','project_template.docx','storage/uploads/files/project_template.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',520000,'templates','2026-09-08 17:42:09'),(5,1,'Library_Digital_Resources_Access_Guide.pdf','library_guide.pdf','storage/uploads/files/library_guide.pdf','application/pdf',1120000,'resources','2026-09-08 17:42:09');
/*!40000 ALTER TABLE `files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goals`
--

DROP TABLE IF EXISTS `goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('daily','weekly','academic','skill','career') DEFAULT 'academic',
  `target_date` date DEFAULT NULL,
  `progress` int(11) NOT NULL DEFAULT 0,
  `status` enum('in_progress','completed','abandoned') DEFAULT 'in_progress',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_goals_user` (`user_id`),
  CONSTRAINT `fk_goals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goals`
--

LOCK TABLES `goals` WRITE;
/*!40000 ALTER TABLE `goals` DISABLE KEYS */;
INSERT INTO `goals` VALUES (1,4,'Achieve 9.0+ Semester GPA','Maintain top attendance and score A+ in DBMS and Algorithms.','academic','2026-12-20',85,'in_progress','2026-09-05 18:36:31','2026-09-05 18:36:31'),(2,4,'Master System Design & Microservices','Read DDIA and build two distributed API services.','career','2026-11-30',60,'in_progress','2026-09-05 18:36:31','2026-09-05 18:36:31'),(3,4,'30-Day Daily Coding Streak','Consistent daily algorithmic problem solving.','skill','2026-09-30',70,'in_progress','2026-09-05 18:36:31','2026-09-05 18:36:31'),(4,6,'Achieve 3.8+ SGPA in Semester Examinations','Maintain top attendance and score A+ in core degree subjects.','academic','2026-12-20',85,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(5,6,'Complete Full Stack Application Portfolio Project','Build and deploy a comprehensive software application with AI integration.','career','2026-11-30',70,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(6,6,'Master Data Structures & Algorithm Patterns','Solve 100 competitive problems covering Trees, Graphs, and DP.','skill','2026-10-31',60,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(7,7,'Achieve 3.8+ SGPA in Semester Examinations','Maintain top attendance and score A+ in core degree subjects.','academic','2026-12-20',85,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(8,7,'Complete Full Stack Application Portfolio Project','Build and deploy a comprehensive software application with AI integration.','career','2026-11-30',70,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(9,7,'Master Data Structures & Algorithm Patterns','Solve 100 competitive problems covering Trees, Graphs, and DP.','skill','2026-10-31',60,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(10,12,'Achieve 3.8+ SGPA in Semester Examinations','Maintain top attendance and score A+ in core degree subjects.','academic','2026-12-20',85,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(11,12,'Complete Full Stack Application Portfolio Project','Build and deploy a comprehensive software application with AI integration.','career','2026-11-30',70,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(12,12,'Master Data Structures & Algorithm Patterns','Solve 100 competitive problems covering Trees, Graphs, and DP.','skill','2026-10-31',60,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(13,13,'Achieve 3.8+ SGPA in Semester Examinations','Maintain top attendance and score A+ in core degree subjects.','academic','2026-12-20',85,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(14,13,'Complete Full Stack Application Portfolio Project','Build and deploy a comprehensive software application with AI integration.','career','2026-11-30',70,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(15,13,'Master Data Structures & Algorithm Patterns','Solve 100 competitive problems covering Trees, Graphs, and DP.','skill','2026-10-31',60,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(16,14,'Achieve 3.8+ SGPA in Semester Examinations','Maintain top attendance and score A+ in core degree subjects.','academic','2026-12-20',85,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(17,14,'Complete Full Stack Application Portfolio Project','Build and deploy a comprehensive software application with AI integration.','career','2026-11-30',70,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(18,14,'Master Data Structures & Algorithm Patterns','Solve 100 competitive problems covering Trees, Graphs, and DP.','skill','2026-10-31',60,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(19,15,'Achieve 3.8+ SGPA in Semester Examinations','Maintain top attendance and score A+ in core degree subjects.','academic','2026-12-20',85,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(20,15,'Complete Full Stack Application Portfolio Project','Build and deploy a comprehensive software application with AI integration.','career','2026-11-30',70,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10'),(21,15,'Master Data Structures & Algorithm Patterns','Solve 100 competitive problems covering Trees, Graphs, and DP.','skill','2026-10-31',60,'in_progress','2026-09-08 17:42:10','2026-09-08 17:42:10');
/*!40000 ALTER TABLE `goals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_logs`
--

DROP TABLE IF EXISTS `login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ll_user` (`user_id`),
  CONSTRAINT `fk_ll_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_logs`
--

LOCK TABLES `login_logs` WRITE;
/*!40000 ALTER TABLE `login_logs` DISABLE KEYS */;
INSERT INTO `login_logs` VALUES (1,4,1,'127.0.0.1','PHP-CLI Test',NULL,'a4497788933a93468e5c5dd8edb5b783925f89486de4db359d8f1392b7f4b97a','2026-09-05 18:38:51'),(2,4,1,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-IN) WindowsPowerShell/5.1.26100.9278',NULL,'3d523e9a302a026e414ef451089cf92d2451ca6b35be3177d2693817f803a695','2026-09-05 18:43:42'),(3,NULL,0,'::1','Unknown','User not found',NULL,'2026-09-07 12:01:49'),(4,4,1,'::1','Unknown',NULL,'0adec164122953a3cd539339b4f8c18159d0e78b58dfbcf546d7315b02682318','2026-09-07 12:28:07'),(5,NULL,0,'::1','Unknown','Invalid password',NULL,'2026-09-07 12:31:02'),(6,NULL,0,'::1','Unknown','Invalid password',NULL,'2026-09-07 12:31:14'),(7,4,1,'127.0.0.1','PHP-CLI Test',NULL,'ddb43248d89cba22974c3839cad3841ef431d9e92f30c6f43f089b07b62af21d','2026-09-07 12:32:55'),(8,NULL,0,'127.0.0.1','CLI','Invalid password',NULL,'2026-09-07 12:34:24'),(9,1,1,'127.0.0.1','CLI',NULL,'6459469f7d7edd87f12791100ded5d4bbb7ffbad190cc3a59fc30dd675ca2195','2026-09-07 12:34:25'),(10,1,1,'127.0.0.1','CLI',NULL,'ccaa2ca25d46947a57bcd0a6d086d29b7b51a60fd4396774b827553d5b561169','2026-09-07 12:35:41'),(11,1,1,'127.0.0.1','CLI',NULL,'e7f9e0459458fd8e97c0801f88ad4686c49817b858977785a87a55fb228136c8','2026-09-07 12:35:41'),(12,1,1,'::1','Unknown',NULL,'dbe275c7f80f793aa889f11eb95021b327a8d537cf77160fc93bb0b8079fc847','2026-09-07 12:37:21'),(13,1,1,'::1','Unknown',NULL,'3fd08fbeffe1c3dc94206b56aa1c92626a907908f377ddd13233797e90213dd1','2026-09-07 14:02:41'),(14,4,1,'::1','Unknown',NULL,'144d7e1d812ebc7f7217929be1ac7ac43b6759bbd8410e0dc8a92f6304e7e12c','2026-09-07 14:06:35'),(15,3,1,'::1','Unknown',NULL,'60a33bb297a1a9e01e703af84a5f17e9e341b539ea8010845e750491be729f1e','2026-09-07 14:34:01'),(16,NULL,0,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-IN) WindowsPowerShell/5.1.26100.9278','User not found',NULL,'2026-09-08 08:47:50'),(17,4,1,'127.0.0.1','CLI',NULL,'12bc1700d042a84bdcfc1052f1e4350584f0b10e4cb5f11f7cad1593316cb80b','2026-09-08 08:48:49'),(18,NULL,0,'::1','curl/8.21.0','User not found',NULL,'2026-09-08 08:49:00'),(19,4,1,'::1','Unknown',NULL,'bad947753e5acdbb2886a2cfb8f8169cc9a4fe9e1c8c279c8d79f9fd6d947260','2026-09-08 08:49:53'),(20,4,1,'::1','Unknown',NULL,'96a98fbe679924e3ef11735ee5378794315891380523dd492b2eae66a653c79c','2026-09-08 09:20:56'),(21,3,1,'::1','Unknown',NULL,'39b22d2c9def67068fd40e311ebcd80b6b6b56694091b35354bf7be3c3eaf902','2026-09-08 09:20:57'),(22,2,1,'::1','Unknown',NULL,'480867bb50fd0de7ce785564b2d0b8c2ccc79c2cc6bb1b4e6a0aa90e07d3abb5','2026-09-08 09:20:57'),(23,1,1,'::1','Unknown',NULL,'246ce7532f7284fc7ac62d98da69dd1c4e5abbbaf5315e617b1f9f1d8a87ecb5','2026-09-08 09:20:58'),(24,7,1,'::1','Unknown',NULL,'3f7a1bc86376b2abfbcb88be271a0bda12d42995cf9f3aa0f994ac10bee3ffe9','2026-09-08 09:25:39'),(25,8,1,'::1','Unknown',NULL,'7d62e9130c81f3c32abdf09ca38821296f4c0caeea112226e9143db0c0e12d29','2026-09-08 09:25:44'),(26,9,1,'::1','Unknown',NULL,'5e0db5b8273c8c8006402d22ca542ebfb6b9e80c19d3f62b851ba7fce58d37d4','2026-09-08 09:25:44'),(27,10,1,'::1','Unknown',NULL,'b00ec7f91c5cd0d751bc3d6315b9a28b6b1d34701790e53575e5d15a375d6f0d','2026-09-08 09:25:44'),(28,7,1,'::1','Unknown',NULL,'7e58ebf25b5fb9f1fde47c5994ab74ef3ddacb124c269a29f5123890e2ff258f','2026-09-08 09:34:47'),(29,8,1,'::1','Unknown',NULL,'db373ce8b4eb65f089cd986a9f574d545809cf90f513699c9bd0962f8a5f5e73','2026-09-08 09:34:52'),(30,9,1,'::1','Unknown',NULL,'c2221cc03323bb99b0ca99452a3553accc11f2215ce08629ee459b885443f89d','2026-09-08 09:34:58'),(31,10,1,'::1','Unknown',NULL,'b54527c627799b90f581626f065a5d926d59a29b6b8ede0acc355b934741a79e','2026-09-08 09:35:05'),(32,10,1,'::1','Unknown',NULL,'28d3ea89c16f7f33b66cba71d26c2de62eeb66fb6d342348f1388b26453c1b40','2026-09-08 13:39:11'),(33,8,1,'::1','Unknown',NULL,'a58bdb30adc95210fa72ed270162b37a99dcfb1fa33d437acfbf32eabd91227e','2026-09-08 13:40:53'),(34,12,1,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-IN) WindowsPowerShell/5.1.26100.9278',NULL,'edc85e1ef5e8394e652385eeb8d7c424f7b907fba0cb5a1e15c2e7f03ab55bd4','2026-09-08 14:18:33'),(35,14,1,'::1','Unknown',NULL,'2ff4b09f8b4a2256f05a6fd11778d7d078cc914448f981fec7e796339ed3aaaa','2026-09-08 14:31:53'),(36,12,1,'::1','Unknown',NULL,'1780e2909baed859195c72b22d4f089fe2a39e379ad952268a2be096fc159bc8','2026-09-08 15:00:44'),(37,13,1,'::1','Unknown',NULL,'89ed63e872da1f572cdfec5cdf5a6fabfa85ab627587da9f51249e6250b2e6a2','2026-09-08 15:00:52'),(38,4,1,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-IN) WindowsPowerShell/5.1.26100.9278',NULL,'c5a6263d627f698f1ff2319a44cf9e57462c5af892e4e8134c957c01f6b30cc6','2026-09-08 15:16:10'),(39,4,1,'::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-IN) WindowsPowerShell/5.1.26100.9278',NULL,'a2bc88814377494b583f2c42382e28d624969d07bb27ba9643a0ddb1fbc2ccc6','2026-09-08 15:16:18'),(40,4,1,'::1','Unknown',NULL,'bb2f462eee5e522d0df005f118b9d4936f9d7d6cffda71a8161b03d0e64e3eab','2026-09-08 15:16:32'),(41,12,1,'::1','Unknown',NULL,'a67e95f204bda7affc1f706bb677410011c9eef7b0ec83c87f67959a80f609ee','2026-09-08 15:16:38'),(42,13,1,'::1','Unknown',NULL,'36c3d686332609480870407d782398a811c61ab68d3ed8314cec98caef283719','2026-09-08 15:16:46'),(43,15,1,'::1','Unknown',NULL,'645cffcc6207ae09aa051a7e64d4c30ac2f60cfd3341908554282286b4ed2297','2026-09-08 15:49:21'),(44,NULL,0,'::1','Unknown','Invalid password',NULL,'2026-09-08 15:58:11'),(45,NULL,0,'::1','Unknown','Invalid password',NULL,'2026-09-08 15:58:38'),(46,NULL,0,'::1','Unknown','Invalid password',NULL,'2026-09-08 16:00:50'),(47,10,1,'::1','Unknown',NULL,'f1f1d6a65416bbf8f486753d86ed028b1f4ffc8112870d253d6354ca496f3165','2026-09-08 16:01:55'),(48,8,1,'::1','Unknown',NULL,'46beed81ff3cacaa06741e256ae4bf5ea82ef98f495ea343f621c888c7734844','2026-09-08 16:02:50'),(49,15,1,'::1','Unknown',NULL,'2fc8a4e3ea8b444c69f151940922846e0e5af8e0598480429a28c3f094892456','2026-09-08 16:48:41'),(50,9,1,'::1','Unknown',NULL,'4576c2dc814f3fa49c6a89a2258f02da0e8cb3d434f547339a87210d93e8a63b','2026-09-08 17:05:42'),(51,4,1,'::1','Unknown',NULL,'88ce7be0fb661c75f97fbaaa44b24705558be2668cce9d8c19c2f40b72646591','2026-09-08 17:36:08'),(52,4,1,'::1','Unknown',NULL,'decfa331ffff158c9480b4884df50b33429e58cc45abac73d29a305e447c8642','2026-09-08 17:36:18'),(53,12,1,'::1','Unknown',NULL,'fa3882171a1acbb478a1745839fc09ad72ec9311044dcd950a62f8a267f8fe96','2026-09-08 17:36:36');
/*!40000 ALTER TABLE `login_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notes`
--

DROP TABLE IF EXISTS `notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_favorite` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notes_user` (`user_id`),
  KEY `fk_notes_sub` (`subject_id`),
  CONSTRAINT `fk_notes_sub` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notes`
--

LOCK TABLES `notes` WRITE;
/*!40000 ALTER TABLE `notes` DISABLE KEYS */;
INSERT INTO `notes` VALUES (1,4,1,'Database Normalization Master Guide','### Key Rules for Relational Normalization\n\n1. **1NF**: Atomic values, no repeating groups.\n2. **2NF**: In 1NF and every non-prime attribute is fully functionally dependent on any candidate key (no partial dependency).\n3. **3NF**: In 2NF and no transitive dependencies (for X -> A, either X is a superkey or A is a prime attribute).\n4. **BCNF**: For every functional dependency X -> A, X must be a superkey.','dbms, normalization, sql, exam',1,1,'2026-09-05 18:36:31','2026-09-05 18:36:31'),(2,4,2,'Graph Traversal Algorithms (BFS vs DFS)','### BFS\n- Uses Queue (FIFO)\n- Computes shortest path in unweighted graphs\n- Time Complexity: O(V + E)\n\n### DFS\n- Uses Stack / Recursion\n- Topological Sorting, Cycle Detection\n- Time Complexity: O(V + E)','algorithms, graphs, dsa',1,1,'2026-09-05 18:36:31','2026-09-05 18:36:31'),(3,6,1,'Core Subject Quick Review & Formulas','### Important Key Definitions\n- Key Theorems and Architectural Principles\n- Core concepts reviewed during lectures.\n- Frequently asked exam questions and model solutions.','study, exam, notes',1,1,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(4,6,2,'Algorithm & Problem Solving Cheat Sheet','### Time and Space Complexities\n- Big-O analysis and worst/average case bounds\n- Traversal patterns and invariant maintenance.','algorithms, dsa, revision',1,0,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(5,7,1,'Core Subject Quick Review & Formulas','### Important Key Definitions\n- Key Theorems and Architectural Principles\n- Core concepts reviewed during lectures.\n- Frequently asked exam questions and model solutions.','study, exam, notes',1,1,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(6,7,2,'Algorithm & Problem Solving Cheat Sheet','### Time and Space Complexities\n- Big-O analysis and worst/average case bounds\n- Traversal patterns and invariant maintenance.','algorithms, dsa, revision',1,0,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(7,12,1,'Core Subject Quick Review & Formulas','### Important Key Definitions\n- Key Theorems and Architectural Principles\n- Core concepts reviewed during lectures.\n- Frequently asked exam questions and model solutions.','study, exam, notes',1,1,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(8,12,2,'Algorithm & Problem Solving Cheat Sheet','### Time and Space Complexities\n- Big-O analysis and worst/average case bounds\n- Traversal patterns and invariant maintenance.','algorithms, dsa, revision',1,0,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(9,13,1,'Core Subject Quick Review & Formulas','### Important Key Definitions\n- Key Theorems and Architectural Principles\n- Core concepts reviewed during lectures.\n- Frequently asked exam questions and model solutions.','study, exam, notes',1,1,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(10,13,2,'Algorithm & Problem Solving Cheat Sheet','### Time and Space Complexities\n- Big-O analysis and worst/average case bounds\n- Traversal patterns and invariant maintenance.','algorithms, dsa, revision',1,0,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(11,14,1,'Core Subject Quick Review & Formulas','### Important Key Definitions\n- Key Theorems and Architectural Principles\n- Core concepts reviewed during lectures.\n- Frequently asked exam questions and model solutions.','study, exam, notes',1,1,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(12,14,2,'Algorithm & Problem Solving Cheat Sheet','### Time and Space Complexities\n- Big-O analysis and worst/average case bounds\n- Traversal patterns and invariant maintenance.','algorithms, dsa, revision',1,0,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(13,15,1,'Core Subject Quick Review & Formulas','### Important Key Definitions\n- Key Theorems and Architectural Principles\n- Core concepts reviewed during lectures.\n- Frequently asked exam questions and model solutions.','study, exam, notes',1,1,'2026-09-08 17:42:10','2026-09-08 17:42:10'),(14,15,2,'Algorithm & Problem Solving Cheat Sheet','### Time and Space Complexities\n- Big-O analysis and worst/average case bounds\n- Traversal patterns and invariant maintenance.','algorithms, dsa, revision',1,0,'2026-09-08 17:42:10','2026-09-08 17:42:10');
/*!40000 ALTER TABLE `notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notices`
--

DROP TABLE IF EXISTS `notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `target_role` varchar(50) DEFAULT 'all',
  `department_id` int(10) unsigned DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `attachment_path` varchar(255) DEFAULT NULL,
  `posted_by` int(10) unsigned NOT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notice_user` (`posted_by`),
  KEY `fk_notice_dept` (`department_id`),
  CONSTRAINT `fk_notice_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notice_user` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notices`
--

LOCK TABLES `notices` WRITE;
/*!40000 ALTER TABLE `notices` DISABLE KEYS */;
INSERT INTO `notices` VALUES (1,'Mid-Semester Examination Schedule Announced','All 5th semester examinations will commence from September 22nd. Please check your personalized exam timetable in the Examination portal.','all',1,'high',NULL,2,NULL,'2026-09-05 18:36:31'),(2,'Annual Hackathon 2026 Registrations Open','The department is organizing HackAI 2026. Teams of up to 4 students can register before September 15th.','STUDENT',1,'medium',NULL,3,NULL,'2026-09-05 18:36:31'),(3,'Final Exam Registration & Timetable Publication','The comprehensive semester examination timetable is now published. All eligible students must review their enrolled subject dates. No late application will be accepted after the cutoff date.','all',1,'high',NULL,2,NULL,'2026-09-08 17:42:09'),(4,'Central University Library 24/7 Extended Hours','To support student revision ahead of upcoming examinations, the central campus library reading halls will remain open 24 hours daily with uninterrupted high-speed internet and digital learning terminals.','all',1,'medium',NULL,2,NULL,'2026-09-08 17:42:09');
/*!40000 ALTER TABLE `notices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_preferences`
--

DROP TABLE IF EXISTS `notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_preferences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `in_app_notifications` tinyint(1) DEFAULT 1,
  `attendance_alerts` tinyint(1) DEFAULT 1,
  `assignment_reminders` tinyint(1) DEFAULT 1,
  `exam_reminders` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_np_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_preferences`
--

LOCK TABLES `notification_preferences` WRITE;
/*!40000 ALTER TABLE `notification_preferences` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','deadline','attendance','exam') DEFAULT 'info',
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notif_user` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,4,'Assignment Graded','Your submission for DBMS Normalization has been evaluated: 48/50.','success','/student/assignments.php',0,'2026-09-05 18:36:31'),(2,4,'Upcoming Exam Alert','DBMS Midterm Examination is scheduled for September 22 at 10:00 AM in Auditorium A.','exam','/student/exams.php',0,'2026-09-05 18:36:31'),(3,4,'Attendance Good Standing','Your overall attendance is 94.2%. Keep it up!','info','/student/attendance.php',1,'2026-09-05 18:36:31'),(4,6,'New Assignment Published','A new coursework assignment has been assigned to your batch.','info','/student/assignments.php',0,'2026-09-08 17:42:10'),(5,6,'Upcoming Examination Alert','Review your upcoming schedule in the Examination Portal.','exam','/student/exams.php',0,'2026-09-08 17:42:10'),(6,6,'Attendance Notice','Your semester attendance has been updated. Check the Attendance tab for subject breakdown.','attendance','/student/attendance.php',1,'2026-09-08 17:42:10'),(7,7,'New Assignment Published','A new coursework assignment has been assigned to your batch.','info','/student/assignments.php',0,'2026-09-08 17:42:10'),(8,7,'Upcoming Examination Alert','Review your upcoming schedule in the Examination Portal.','exam','/student/exams.php',0,'2026-09-08 17:42:10'),(9,7,'Attendance Notice','Your semester attendance has been updated. Check the Attendance tab for subject breakdown.','attendance','/student/attendance.php',1,'2026-09-08 17:42:10'),(10,12,'New Assignment Published','A new coursework assignment has been assigned to your batch.','info','/student/assignments.php',0,'2026-09-08 17:42:10'),(11,12,'Upcoming Examination Alert','Review your upcoming schedule in the Examination Portal.','exam','/student/exams.php',0,'2026-09-08 17:42:10'),(12,12,'Attendance Notice','Your semester attendance has been updated. Check the Attendance tab for subject breakdown.','attendance','/student/attendance.php',1,'2026-09-08 17:42:10'),(13,13,'New Assignment Published','A new coursework assignment has been assigned to your batch.','info','/student/assignments.php',0,'2026-09-08 17:42:10'),(14,13,'Upcoming Examination Alert','Review your upcoming schedule in the Examination Portal.','exam','/student/exams.php',0,'2026-09-08 17:42:10'),(15,13,'Attendance Notice','Your semester attendance has been updated. Check the Attendance tab for subject breakdown.','attendance','/student/attendance.php',1,'2026-09-08 17:42:10'),(16,14,'New Assignment Published','A new coursework assignment has been assigned to your batch.','info','/student/assignments.php',0,'2026-09-08 17:42:10'),(17,14,'Upcoming Examination Alert','Review your upcoming schedule in the Examination Portal.','exam','/student/exams.php',0,'2026-09-08 17:42:10'),(18,14,'Attendance Notice','Your semester attendance has been updated. Check the Attendance tab for subject breakdown.','attendance','/student/attendance.php',1,'2026-09-08 17:42:10'),(19,15,'New Assignment Published','A new coursework assignment has been assigned to your batch.','info','/student/assignments.php',0,'2026-09-08 17:42:10'),(20,15,'Upcoming Examination Alert','Review your upcoming schedule in the Examination Portal.','exam','/student/exams.php',0,'2026-09-08 17:42:10'),(21,15,'Attendance Notice','Your semester attendance has been updated. Check the Attendance tab for subject breakdown.','attendance','/student/attendance.php',1,'2026-09-08 17:42:10');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prt_user` (`user_id`),
  CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
INSERT INTO `password_reset_tokens` VALUES (1,3,'430c8593ff532aa4bcb247ab3d5ca1674026222025677d9b17e08f4db547c608','2026-09-07 21:01:49','2026-09-07 18:01:49');
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `performance`
--

DROP TABLE IF EXISTS `performance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `performance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `semester` varchar(20) NOT NULL,
  `gpa` decimal(3,2) NOT NULL DEFAULT 0.00,
  `cgpa` decimal(3,2) NOT NULL DEFAULT 0.00,
  `rank` int(11) DEFAULT NULL,
  `attendance_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `credits_completed` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_perf` (`student_id`,`semester`),
  CONSTRAINT `fk_perf_stu` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `performance`
--

LOCK TABLES `performance` WRITE;
/*!40000 ALTER TABLE `performance` DISABLE KEYS */;
INSERT INTO `performance` VALUES (1,4,'4',3.76,3.76,2,94.20,17,'2026-09-08 17:33:28'),(2,4,'5',3.81,3.79,2,94.20,98,'2026-09-08 17:36:27'),(5,6,'4',3.93,3.93,1,96.50,17,'2026-09-08 17:33:28'),(6,6,'5',4.00,3.96,1,96.50,98,'2026-09-08 17:36:27'),(7,7,'4',3.76,3.76,3,92.00,17,'2026-09-08 17:33:29'),(8,7,'5',3.81,3.79,3,92.00,98,'2026-09-08 17:36:27'),(9,14,'4',3.69,3.69,3,92.00,17,'2026-09-08 17:33:29'),(10,14,'5',3.88,3.79,3,92.00,98,'2026-09-08 17:36:27'),(11,12,'1',3.85,3.85,2,92.00,16,'2026-09-08 17:33:29'),(12,12,'4',3.81,3.83,2,92.00,33,'2026-09-08 17:33:29'),(13,12,'5',3.81,3.82,2,92.00,50,'2026-09-08 17:33:29'),(14,13,'1',3.94,3.94,3,92.00,16,'2026-09-08 17:33:29'),(15,13,'4',3.69,3.82,3,92.00,33,'2026-09-08 17:33:29'),(16,13,'5',3.88,3.84,3,92.00,50,'2026-09-08 17:33:29'),(17,15,'1',3.69,3.69,3,92.00,16,'2026-09-08 17:33:29'),(18,15,'4',3.57,3.63,3,92.00,33,'2026-09-08 17:33:29');
/*!40000 ALTER TABLE `performance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'system','system.full_control','Full System Control','Unrestricted administrative platform access','2026-09-05 18:36:28'),(2,'users','users.manage','Manage Users','Create, update, activate and deactivate users','2026-09-05 18:36:28'),(3,'academic','academic.manage','Manage Academic','Manage departments, courses, subjects, schedules','2026-09-05 18:36:28'),(4,'students','students.manage','Manage Students','Manage student records and enrollments','2026-09-05 18:36:28'),(5,'faculty','faculty.manage','Manage Faculty','Manage faculty appointments and assignments','2026-09-05 18:36:28'),(6,'attendance','attendance.mark','Mark Attendance','Record and modify attendance records','2026-09-05 18:36:28'),(7,'assignments','assignments.manage','Manage Assignments','Create and grade assignments','2026-09-05 18:36:28'),(8,'exams','exams.manage','Manage Examinations','Schedule exams and evaluate submissions','2026-09-05 18:36:28'),(9,'ai','ai.use','Use AI Tools','Access AI Assistant, PDF Q&A and Study Planner','2026-09-05 18:36:28'),(10,'ai','ai.settings','Manage AI Settings','Configure API keys, models and token quotas','2026-09-05 18:36:28'),(11,'logs','logs.view','View Logs','Inspect audit and security logs','2026-09-05 18:36:28'),(12,'backup','backup.manage','Database Backup','Generate and restore database backups','2026-09-05 18:36:28');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question_options`
--

DROP TABLE IF EXISTS `question_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `question_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` int(10) unsigned NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_qo_q` (`question_id`),
  CONSTRAINT `fk_qo_q` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question_options`
--

LOCK TABLES `question_options` WRITE;
/*!40000 ALTER TABLE `question_options` DISABLE KEYS */;
/*!40000 ALTER TABLE `question_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `exam_id` int(10) unsigned NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('mcq','descriptive') NOT NULL DEFAULT 'mcq',
  `marks` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_q_exam` (`exam_id`),
  CONSTRAINT `fk_q_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identifier` varchar(150) NOT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rl_ident_time` (`identifier`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `results`
--

DROP TABLE IF EXISTS `results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `exam_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned NOT NULL,
  `marks_obtained` decimal(5,2) NOT NULL,
  `total_marks` decimal(5,2) NOT NULL DEFAULT 100.00,
  `grade` varchar(5) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_res` (`exam_id`,`student_id`),
  KEY `fk_res_stu` (`student_id`),
  KEY `fk_res_sub` (`subject_id`),
  CONSTRAINT `fk_res_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_res_stu` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_res_sub` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `results`
--

LOCK TABLES `results` WRITE;
/*!40000 ALTER TABLE `results` DISABLE KEYS */;
INSERT INTO `results` VALUES (2,3,4,1,88.00,100.00,'A','Grade A achieved with score 88% in CS501','2026-09-08 23:03:23','2026-09-08 17:33:23'),(3,4,4,2,82.00,100.00,'A-','Grade A- achieved with score 82% in CS502','2026-09-08 23:03:23','2026-09-08 17:33:23'),(4,5,4,3,79.00,100.00,'B+','Grade B+ achieved with score 79% in CS503','2026-09-08 23:03:23','2026-09-08 17:33:23'),(5,6,4,4,91.00,100.00,'A+','Grade A+ achieved with score 91% in CS504','2026-09-08 23:03:23','2026-09-08 17:33:23'),(6,9,4,11,94.00,100.00,'A+','Grade A+ achieved with score 94% in CS505','2026-09-08 23:03:23','2026-09-08 17:33:23'),(7,10,4,12,85.00,100.00,'A','Grade A achieved with score 85% in CS401','2026-09-08 23:03:23','2026-09-08 17:33:23'),(8,11,4,13,80.00,100.00,'A-','Grade A- achieved with score 80% in CS402','2026-09-08 23:03:23','2026-09-08 17:33:23'),(9,12,4,14,89.00,100.00,'A','Grade A achieved with score 89% in CS403','2026-09-08 23:03:23','2026-09-08 17:33:23'),(10,13,4,15,76.00,100.00,'B+','Grade B+ achieved with score 76% in CS404','2026-09-08 23:03:23','2026-09-08 17:33:23'),(11,14,4,16,92.00,100.00,'A+','Grade A+ achieved with score 92% in CS405','2026-09-08 23:03:23','2026-09-08 17:33:23'),(12,3,6,1,92.00,100.00,'A+','Grade A+ achieved with score 92% in CS501','2026-09-08 23:03:23','2026-09-08 17:33:23'),(13,4,6,2,89.00,100.00,'A','Grade A achieved with score 89% in CS502','2026-09-08 23:03:23','2026-09-08 17:33:23'),(14,5,6,3,86.00,100.00,'A','Grade A achieved with score 86% in CS503','2026-09-08 23:03:23','2026-09-08 17:33:23'),(15,6,6,4,95.00,100.00,'A+','Grade A+ achieved with score 95% in CS504','2026-09-08 23:03:24','2026-09-08 17:33:24'),(16,9,6,11,98.00,100.00,'A+','Grade A+ achieved with score 98% in CS505','2026-09-08 23:03:24','2026-09-08 17:33:24'),(17,10,6,12,90.00,100.00,'A+','Grade A+ achieved with score 90% in CS401','2026-09-08 23:03:24','2026-09-08 17:33:24'),(18,11,6,13,88.00,100.00,'A','Grade A achieved with score 88% in CS402','2026-09-08 23:03:24','2026-09-08 17:33:24'),(19,12,6,14,91.00,100.00,'A+','Grade A+ achieved with score 91% in CS403','2026-09-08 23:03:24','2026-09-08 17:33:24'),(20,13,6,15,84.00,100.00,'A-','Grade A- achieved with score 84% in CS404','2026-09-08 23:03:24','2026-09-08 17:33:24'),(21,14,6,16,96.00,100.00,'A+','Grade A+ achieved with score 96% in CS405','2026-09-08 23:03:24','2026-09-08 17:33:24'),(22,3,7,1,88.00,100.00,'A','Grade A achieved with score 88% in CS501','2026-09-08 23:03:24','2026-09-08 17:33:24'),(23,4,7,2,82.00,100.00,'A-','Grade A- achieved with score 82% in CS502','2026-09-08 23:03:24','2026-09-08 17:33:24'),(24,5,7,3,79.00,100.00,'B+','Grade B+ achieved with score 79% in CS503','2026-09-08 23:03:24','2026-09-08 17:33:24'),(25,6,7,4,91.00,100.00,'A+','Grade A+ achieved with score 91% in CS504','2026-09-08 23:03:24','2026-09-08 17:33:24'),(26,9,7,11,94.00,100.00,'A+','Grade A+ achieved with score 94% in CS505','2026-09-08 23:03:24','2026-09-08 17:33:24'),(27,10,7,12,85.00,100.00,'A','Grade A achieved with score 85% in CS401','2026-09-08 23:03:24','2026-09-08 17:33:24'),(28,11,7,13,80.00,100.00,'A-','Grade A- achieved with score 80% in CS402','2026-09-08 23:03:24','2026-09-08 17:33:24'),(29,12,7,14,89.00,100.00,'A','Grade A achieved with score 89% in CS403','2026-09-08 23:03:24','2026-09-08 17:33:24'),(30,13,7,15,76.00,100.00,'B+','Grade B+ achieved with score 76% in CS404','2026-09-08 23:03:24','2026-09-08 17:33:24'),(31,14,7,16,92.00,100.00,'A+','Grade A+ achieved with score 92% in CS405','2026-09-08 23:03:25','2026-09-08 17:33:25'),(32,3,14,1,86.00,100.00,'A','Grade A achieved with score 86% in CS501','2026-09-08 23:03:25','2026-09-08 17:33:25'),(33,4,14,2,84.00,100.00,'A-','Grade A- achieved with score 84% in CS502','2026-09-08 23:03:25','2026-09-08 17:33:25'),(34,5,14,3,80.00,100.00,'A-','Grade A- achieved with score 80% in CS503','2026-09-08 23:03:25','2026-09-08 17:33:25'),(35,6,14,4,88.00,100.00,'A','Grade A achieved with score 88% in CS504','2026-09-08 23:03:25','2026-09-08 17:33:25'),(36,9,14,11,95.00,100.00,'A+','Grade A+ achieved with score 95% in CS505','2026-09-08 23:03:25','2026-09-08 17:33:25'),(37,10,14,12,83.00,100.00,'A-','Grade A- achieved with score 83% in CS401','2026-09-08 23:03:25','2026-09-08 17:33:25'),(38,11,14,13,81.00,100.00,'A-','Grade A- achieved with score 81% in CS402','2026-09-08 23:03:25','2026-09-08 17:33:25'),(39,12,14,14,87.00,100.00,'A','Grade A achieved with score 87% in CS403','2026-09-08 23:03:25','2026-09-08 17:33:25'),(40,13,14,15,78.00,100.00,'B+','Grade B+ achieved with score 78% in CS404','2026-09-08 23:03:25','2026-09-08 17:33:25'),(41,14,14,16,90.00,100.00,'A+','Grade A+ achieved with score 90% in CS405','2026-09-08 23:03:25','2026-09-08 17:33:25'),(42,20,12,22,88.00,100.00,'A','Grade A achieved with score 88% in BBA501','2026-09-08 23:03:25','2026-09-08 17:33:25'),(43,21,12,23,82.00,100.00,'A-','Grade A- achieved with score 82% in BBA502','2026-09-08 23:03:25','2026-09-08 17:33:25'),(44,22,12,24,79.00,100.00,'B+','Grade B+ achieved with score 79% in BBA503','2026-09-08 23:03:26','2026-09-08 17:33:26'),(45,23,12,25,91.00,100.00,'A+','Grade A+ achieved with score 91% in BBA504','2026-09-08 23:03:26','2026-09-08 17:33:26'),(46,24,12,26,94.00,100.00,'A+','Grade A+ achieved with score 94% in BBA505','2026-09-08 23:03:26','2026-09-08 17:33:26'),(47,25,12,27,85.00,100.00,'A','Grade A achieved with score 85% in BBA401','2026-09-08 23:03:26','2026-09-08 17:33:26'),(48,26,12,28,80.00,100.00,'A-','Grade A- achieved with score 80% in BBA402','2026-09-08 23:03:26','2026-09-08 17:33:26'),(49,27,12,29,89.00,100.00,'A','Grade A achieved with score 89% in BBA403','2026-09-08 23:03:26','2026-09-08 17:33:26'),(50,28,12,30,76.00,100.00,'B+','Grade B+ achieved with score 76% in BBA404','2026-09-08 23:03:26','2026-09-08 17:33:26'),(51,29,12,31,90.00,100.00,'A+','Grade A+ achieved with score 90% in BBA405','2026-09-08 23:03:26','2026-09-08 17:33:26'),(52,7,12,5,89.00,100.00,'A','Grade A achieved with score 89% in BBA101','2026-09-08 23:03:26','2026-09-08 17:33:26'),(53,8,12,6,84.00,100.00,'A-','Grade A- achieved with score 84% in BBA102','2026-09-08 23:03:26','2026-09-08 17:33:26'),(54,30,12,34,82.00,100.00,'A-','Grade A- achieved with score 82% in BBA103','2026-09-08 23:03:26','2026-09-08 17:33:26'),(55,31,12,35,88.00,100.00,'A','Grade A achieved with score 88% in BBA104','2026-09-08 23:03:26','2026-09-08 17:33:26'),(56,32,12,36,92.00,100.00,'A+','Grade A+ achieved with score 92% in BBA105','2026-09-08 23:03:26','2026-09-08 17:33:26'),(57,3,13,1,87.00,100.00,'A','Grade A achieved with score 87% in CS501','2026-09-08 23:03:26','2026-09-08 17:33:26'),(58,4,13,2,83.00,100.00,'A-','Grade A- achieved with score 83% in CS502','2026-09-08 23:03:26','2026-09-08 17:33:26'),(59,5,13,3,80.00,100.00,'A-','Grade A- achieved with score 80% in CS503','2026-09-08 23:03:26','2026-09-08 17:33:26'),(60,6,13,4,90.00,100.00,'A+','Grade A+ achieved with score 90% in CS504','2026-09-08 23:03:26','2026-09-08 17:33:26'),(61,9,13,11,93.00,100.00,'A+','Grade A+ achieved with score 93% in CS505','2026-09-08 23:03:26','2026-09-08 17:33:26'),(62,10,13,12,84.00,100.00,'A-','Grade A- achieved with score 84% in CS401','2026-09-08 23:03:27','2026-09-08 17:33:27'),(63,11,13,13,82.00,100.00,'A-','Grade A- achieved with score 82% in CS402','2026-09-08 23:03:27','2026-09-08 17:33:27'),(64,12,13,14,88.00,100.00,'A','Grade A achieved with score 88% in CS403','2026-09-08 23:03:27','2026-09-08 17:33:27'),(65,13,13,15,77.00,100.00,'B+','Grade B+ achieved with score 77% in CS404','2026-09-08 23:03:27','2026-09-08 17:33:27'),(66,14,13,16,91.00,100.00,'A+','Grade A+ achieved with score 91% in CS405','2026-09-08 23:03:27','2026-09-08 17:33:27'),(67,15,13,17,88.00,100.00,'A','Grade A achieved with score 88% in CS101','2026-09-08 23:03:27','2026-09-08 17:33:27'),(68,16,13,18,85.00,100.00,'A','Grade A achieved with score 85% in CS102','2026-09-08 23:03:27','2026-09-08 17:33:27'),(69,17,13,19,82.00,100.00,'A-','Grade A- achieved with score 82% in CS103','2026-09-08 23:03:27','2026-09-08 17:33:27'),(70,18,13,20,87.00,100.00,'A','Grade A achieved with score 87% in CS104','2026-09-08 23:03:27','2026-09-08 17:33:27'),(71,19,13,21,95.00,100.00,'A+','Grade A+ achieved with score 95% in CS105','2026-09-08 23:03:27','2026-09-08 17:33:27'),(72,25,15,27,82.00,100.00,'A-','Grade A- achieved with score 82% in BBA401','2026-09-08 23:03:28','2026-09-08 17:33:28'),(73,26,15,28,78.00,100.00,'B+','Grade B+ achieved with score 78% in BBA402','2026-09-08 23:03:28','2026-09-08 17:33:28'),(74,27,15,29,84.00,100.00,'A-','Grade A- achieved with score 84% in BBA403','2026-09-08 23:03:28','2026-09-08 17:33:28'),(75,28,15,30,75.00,100.00,'B+','Grade B+ achieved with score 75% in BBA404','2026-09-08 23:03:28','2026-09-08 17:33:28'),(76,29,15,31,88.00,100.00,'A','Grade A achieved with score 88% in BBA405','2026-09-08 23:03:28','2026-09-08 17:33:28'),(77,7,15,5,85.00,100.00,'A','Grade A achieved with score 85% in BBA101','2026-09-08 23:03:28','2026-09-08 17:33:28'),(78,8,15,6,80.00,100.00,'A-','Grade A- achieved with score 80% in BBA102','2026-09-08 23:03:28','2026-09-08 17:33:28'),(79,30,15,34,78.00,100.00,'B+','Grade B+ achieved with score 78% in BBA103','2026-09-08 23:03:28','2026-09-08 17:33:28'),(80,31,15,35,83.00,100.00,'A-','Grade A- achieved with score 83% in BBA104','2026-09-08 23:03:28','2026-09-08 17:33:28'),(81,32,15,36,89.00,100.00,'A','Grade A achieved with score 89% in BBA105','2026-09-08 23:03:28','2026-09-08 17:33:28');
/*!40000 ALTER TABLE `results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_perm` (`role_id`,`permission_id`),
  KEY `fk_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1,1),(2,1,2),(3,1,3),(4,1,4),(5,1,5),(6,1,6),(7,1,7),(8,1,8),(9,1,9),(10,1,10),(11,1,11),(12,1,12),(13,2,2),(14,2,3),(15,2,4),(16,2,5),(17,2,6),(18,2,7),(19,2,8),(20,2,9),(21,2,11),(22,3,6),(23,3,7),(24,3,8),(25,3,9),(26,4,9);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'SUPER_ADMIN','Super Admin','Full system access, security controls, backup management, and audit inspection','2026-09-05 18:36:28','2026-09-05 18:36:28'),(2,'ADMIN','Administrator','Academic institute operations, course/subject scheduling, and reporting','2026-09-05 18:36:28','2026-09-05 18:36:28'),(3,'FACULTY','Faculty Member','Classroom management, student attendance, assignments, and exam grading','2026-09-05 18:36:28','2026-09-05 18:36:28'),(4,'STUDENT','Student','Academic learning, tasks, study planner, notes, and AI assistance','2026-09-05 18:36:28','2026-09-05 18:36:28');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_profiles`
--

DROP TABLE IF EXISTS `student_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `department_id` int(10) unsigned NOT NULL,
  `course_id` int(10) unsigned NOT NULL,
  `semester` varchar(20) NOT NULL DEFAULT '1',
  `section` varchar(10) DEFAULT 'A',
  `roll_number` varchar(50) DEFAULT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_phone` varchar(25) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `fk_sp_dept` (`department_id`),
  KEY `fk_sp_course` (`course_id`),
  CONSTRAINT `fk_sp_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `fk_sp_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_sp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_profiles`
--

LOCK TABLES `student_profiles` WRITE;
/*!40000 ALTER TABLE `student_profiles` DISABLE KEYS */;
INSERT INTO `student_profiles` VALUES (1,4,'STU2026001',1,2,'5','A','CS-2026-042','9876543213','2004-05-14','742 Evergreen Terrace, Tech Park, City','O+','David Johnson','9876543299','2026-09-05 18:36:29','2026-09-08 14:16:35'),(2,6,'STU2026002',1,2,'5','A','CS-2026-043','9876543215','2004-09-22','124 Innovation Blvd, City','A+','Chris Watson','9876543298','2026-09-05 18:36:29','2026-09-08 14:16:35'),(3,7,'STU-2026-GMAIL',1,2,'5','A','CS26-G01',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-08 09:25:06','2026-09-08 14:16:35'),(6,12,'STU-BBA-2026-101',4,1,'1','A','BBA-01',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-08 14:18:22','2026-09-08 14:18:22'),(7,13,'STU-BCA-2026-202',1,2,'1','A','BCA-02',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-08 14:18:39','2026-09-08 14:18:39'),(8,14,'STU-2026-305',1,2,'5','A','35',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-08 14:31:26','2026-09-08 14:31:26'),(9,15,'STU-2026-36',4,1,'3','A','36',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-08 15:48:51','2026-09-08 15:48:51');
/*!40000 ALTER TABLE `student_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_subjects`
--

DROP TABLE IF EXISTS `student_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_subjects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned NOT NULL,
  `semester` varchar(20) NOT NULL,
  `academic_year` varchar(20) NOT NULL DEFAULT '2026-2027',
  `status` enum('enrolled','completed','dropped') DEFAULT 'enrolled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_sub` (`student_id`,`subject_id`),
  KEY `fk_ss_subject` (`subject_id`),
  CONSTRAINT `fk_ss_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ss_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=176 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_subjects`
--

LOCK TABLES `student_subjects` WRITE;
/*!40000 ALTER TABLE `student_subjects` DISABLE KEYS */;
INSERT INTO `student_subjects` VALUES (1,4,1,'5','2026-2027','completed','2026-09-05 18:36:30'),(2,4,2,'5','2026-2027','completed','2026-09-05 18:36:30'),(3,4,3,'5','2026-2027','completed','2026-09-05 18:36:30'),(4,4,4,'5','2026-2027','completed','2026-09-05 18:36:30'),(5,6,1,'5','2026-2027','completed','2026-09-05 18:36:30'),(6,6,2,'5','2026-2027','completed','2026-09-05 18:36:30'),(7,7,1,'5','2026-2027','completed','2026-09-08 09:25:06'),(8,7,2,'5','2026-2027','completed','2026-09-08 09:25:06'),(9,7,3,'5','2026-2027','completed','2026-09-08 09:25:06'),(10,7,4,'5','2026-2027','completed','2026-09-08 09:25:06'),(20,4,11,'5','2025-2026','completed','2026-09-08 17:33:23'),(21,4,12,'4','2025-2026','completed','2026-09-08 17:33:23'),(22,4,13,'4','2025-2026','completed','2026-09-08 17:33:23'),(23,4,14,'4','2025-2026','completed','2026-09-08 17:33:23'),(24,4,15,'4','2025-2026','completed','2026-09-08 17:33:23'),(25,4,16,'4','2025-2026','completed','2026-09-08 17:33:23'),(28,6,3,'5','2025-2026','completed','2026-09-08 17:33:23'),(29,6,4,'5','2025-2026','completed','2026-09-08 17:33:24'),(30,6,11,'5','2025-2026','completed','2026-09-08 17:33:24'),(31,6,12,'4','2025-2026','completed','2026-09-08 17:33:24'),(32,6,13,'4','2025-2026','completed','2026-09-08 17:33:24'),(33,6,14,'4','2025-2026','completed','2026-09-08 17:33:24'),(34,6,15,'4','2025-2026','completed','2026-09-08 17:33:24'),(35,6,16,'4','2025-2026','completed','2026-09-08 17:33:24'),(40,7,11,'5','2025-2026','completed','2026-09-08 17:33:24'),(41,7,12,'4','2025-2026','completed','2026-09-08 17:33:24'),(42,7,13,'4','2025-2026','completed','2026-09-08 17:33:24'),(43,7,14,'4','2025-2026','completed','2026-09-08 17:33:24'),(44,7,15,'4','2025-2026','completed','2026-09-08 17:33:24'),(45,7,16,'4','2025-2026','completed','2026-09-08 17:33:25'),(46,14,1,'5','2025-2026','completed','2026-09-08 17:33:25'),(47,14,2,'5','2025-2026','completed','2026-09-08 17:33:25'),(48,14,3,'5','2025-2026','completed','2026-09-08 17:33:25'),(49,14,4,'5','2025-2026','completed','2026-09-08 17:33:25'),(50,14,11,'5','2025-2026','completed','2026-09-08 17:33:25'),(51,14,12,'4','2025-2026','completed','2026-09-08 17:33:25'),(52,14,13,'4','2025-2026','completed','2026-09-08 17:33:25'),(53,14,14,'4','2025-2026','completed','2026-09-08 17:33:25'),(54,14,15,'4','2025-2026','completed','2026-09-08 17:33:25'),(55,14,16,'4','2025-2026','completed','2026-09-08 17:33:25'),(56,12,22,'5','2025-2026','completed','2026-09-08 17:33:25'),(57,12,23,'5','2025-2026','completed','2026-09-08 17:33:25'),(58,12,24,'5','2025-2026','completed','2026-09-08 17:33:26'),(59,12,25,'5','2025-2026','completed','2026-09-08 17:33:26'),(60,12,26,'5','2025-2026','completed','2026-09-08 17:33:26'),(61,12,27,'4','2025-2026','completed','2026-09-08 17:33:26'),(62,12,28,'4','2025-2026','completed','2026-09-08 17:33:26'),(63,12,29,'4','2025-2026','completed','2026-09-08 17:33:26'),(64,12,30,'4','2025-2026','completed','2026-09-08 17:33:26'),(65,12,31,'4','2025-2026','completed','2026-09-08 17:33:26'),(66,12,5,'1','2025-2026','completed','2026-09-08 17:33:26'),(67,12,6,'1','2025-2026','completed','2026-09-08 17:33:26'),(68,12,34,'1','2025-2026','completed','2026-09-08 17:33:26'),(69,12,35,'1','2025-2026','completed','2026-09-08 17:33:26'),(70,12,36,'1','2025-2026','completed','2026-09-08 17:33:26'),(71,13,1,'5','2025-2026','completed','2026-09-08 17:33:26'),(72,13,2,'5','2025-2026','completed','2026-09-08 17:33:26'),(73,13,3,'5','2025-2026','completed','2026-09-08 17:33:26'),(74,13,4,'5','2025-2026','completed','2026-09-08 17:33:26'),(75,13,11,'5','2025-2026','completed','2026-09-08 17:33:27'),(76,13,12,'4','2025-2026','completed','2026-09-08 17:33:27'),(77,13,13,'4','2025-2026','completed','2026-09-08 17:33:27'),(78,13,14,'4','2025-2026','completed','2026-09-08 17:33:27'),(79,13,15,'4','2025-2026','completed','2026-09-08 17:33:27'),(80,13,16,'4','2025-2026','completed','2026-09-08 17:33:27'),(81,13,17,'1','2025-2026','completed','2026-09-08 17:33:27'),(82,13,18,'1','2025-2026','completed','2026-09-08 17:33:27'),(83,13,19,'1','2025-2026','completed','2026-09-08 17:33:27'),(84,13,20,'1','2025-2026','completed','2026-09-08 17:33:27'),(85,13,21,'1','2025-2026','completed','2026-09-08 17:33:27'),(86,15,27,'4','2025-2026','completed','2026-09-08 17:33:28'),(87,15,28,'4','2025-2026','completed','2026-09-08 17:33:28'),(88,15,29,'4','2025-2026','completed','2026-09-08 17:33:28'),(89,15,30,'4','2025-2026','completed','2026-09-08 17:33:28'),(90,15,31,'4','2025-2026','completed','2026-09-08 17:33:28'),(91,15,5,'1','2025-2026','completed','2026-09-08 17:33:28'),(92,15,6,'1','2025-2026','completed','2026-09-08 17:33:28'),(93,15,34,'1','2025-2026','completed','2026-09-08 17:33:28'),(94,15,35,'1','2025-2026','completed','2026-09-08 17:33:28'),(95,15,36,'1','2025-2026','completed','2026-09-08 17:33:28');
/*!40000 ALTER TABLE `student_subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `study_resources`
--

DROP TABLE IF EXISTS `study_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `study_resources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subject_id` int(10) unsigned NOT NULL,
  `uploaded_by` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `resource_type` enum('pdf','link','video','notes') DEFAULT 'pdf',
  `url` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sr_sub` (`subject_id`),
  KEY `fk_sr_user` (`uploaded_by`),
  CONSTRAINT `fk_sr_sub` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sr_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `study_resources`
--

LOCK TABLES `study_resources` WRITE;
/*!40000 ALTER TABLE `study_resources` DISABLE KEYS */;
/*!40000 ALTER TABLE `study_resources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `study_sessions`
--

DROP TABLE IF EXISTS `study_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `study_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `topic` varchar(255) NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 25,
  `notes` text DEFAULT NULL,
  `session_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ssess_user` (`user_id`),
  KEY `fk_ssess_sub` (`subject_id`),
  CONSTRAINT `fk_ssess_sub` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ssess_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `study_sessions`
--

LOCK TABLES `study_sessions` WRITE;
/*!40000 ALTER TABLE `study_sessions` DISABLE KEYS */;
INSERT INTO `study_sessions` VALUES (1,4,1,'BCNF Lossless Join Decomposition Proofs',45,'Completed 4 textbook exercise problems successfully.','2026-09-04','2026-09-05 18:36:31'),(2,4,2,'Dijkstra and Bellman-Ford comparisons',50,'Practiced negative cycle detection logic.','2026-09-05','2026-09-05 18:36:31'),(3,6,1,'Relational Schema Design & Normalization',60,'Completed practice questions from chapter 4.','2026-09-06','2026-09-08 17:42:11'),(4,6,2,'Balanced Search Trees and Graph Traversals',45,'Analyzed AVL rotations and BFS implementation.','2026-09-07','2026-09-08 17:42:11'),(5,7,1,'Relational Schema Design & Normalization',60,'Completed practice questions from chapter 4.','2026-09-06','2026-09-08 17:42:11'),(6,7,2,'Balanced Search Trees and Graph Traversals',45,'Analyzed AVL rotations and BFS implementation.','2026-09-07','2026-09-08 17:42:11'),(7,12,1,'Relational Schema Design & Normalization',60,'Completed practice questions from chapter 4.','2026-09-06','2026-09-08 17:42:11'),(8,12,2,'Balanced Search Trees and Graph Traversals',45,'Analyzed AVL rotations and BFS implementation.','2026-09-07','2026-09-08 17:42:11'),(9,13,1,'Relational Schema Design & Normalization',60,'Completed practice questions from chapter 4.','2026-09-06','2026-09-08 17:42:11'),(10,13,2,'Balanced Search Trees and Graph Traversals',45,'Analyzed AVL rotations and BFS implementation.','2026-09-07','2026-09-08 17:42:11'),(11,14,1,'Relational Schema Design & Normalization',60,'Completed practice questions from chapter 4.','2026-09-06','2026-09-08 17:42:11'),(12,14,2,'Balanced Search Trees and Graph Traversals',45,'Analyzed AVL rotations and BFS implementation.','2026-09-07','2026-09-08 17:42:11'),(13,15,1,'Relational Schema Design & Normalization',60,'Completed practice questions from chapter 4.','2026-09-06','2026-09-08 17:42:11'),(14,15,2,'Balanced Search Trees and Graph Traversals',45,'Analyzed AVL rotations and BFS implementation.','2026-09-07','2026-09-08 17:42:11');
/*!40000 ALTER TABLE `study_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(10) unsigned NOT NULL,
  `department_id` int(10) unsigned NOT NULL,
  `faculty_id` int(10) unsigned DEFAULT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `semester` varchar(20) NOT NULL DEFAULT '1',
  `credits` int(11) NOT NULL DEFAULT 3,
  `type` enum('core','elective','lab') DEFAULT 'core',
  `syllabus` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `fk_sub_course` (`course_id`),
  KEY `fk_sub_dept` (`department_id`),
  KEY `fk_sub_faculty` (`faculty_id`),
  CONSTRAINT `fk_sub_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,2,1,3,'CS501','Database Management Systems','5',4,'core','Unit 1: ER Models, Relational Algebra. Unit 2: SQL, Constraints. Unit 3: Normalization (1NF, 2NF, 3NF, BCNF). Unit 4: Transaction Processing, ACID, Concurrency. Unit 5: Indexing and NoSQL.','active','2026-09-05 18:36:29','2026-09-08 14:16:35'),(2,2,1,5,'CS502','Design & Analysis of Algorithms','5',4,'core','Unit 1: Asymptotic Analysis. Unit 2: Divide & Conquer. Unit 3: Greedy Algorithms. Unit 4: Dynamic Programming. Unit 5: Graph Algorithms, NP-Completeness.','active','2026-09-05 18:36:29','2026-09-08 14:16:35'),(3,2,1,3,'CS503','Operating Systems & Concurrency','5',3,'core','Processes, Threads, Scheduling, Memory Management, Virtual Memory, File Systems, Deadlocks.','active','2026-09-05 18:36:29','2026-09-08 14:16:35'),(4,2,1,5,'CS504','Computer Networks & Security','5',4,'core','Full-stack Web architecture, REST APIs, Microservices, Security, OAuth2, Caching.','active','2026-09-05 18:36:29','2026-09-08 17:33:21'),(5,1,4,5,'BBA101','Principles of Management & Organization','1',3,'core','Fundamentals of Management, Planning, Organizing, Staffing, Directing and Controlling.','active','2026-09-08 14:16:35','2026-09-08 17:33:22'),(6,1,4,3,'BBA102','Business Economics & Financial Accounting','1',4,'core','Managerial Economics, Cost Accounting, Financial Statements and Cash Flow.','active','2026-09-08 14:16:35','2026-09-08 17:33:22'),(11,2,1,3,'CS505','Cloud Computing & Web Lab','5',2,'lab',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(12,2,1,5,'CS401','Object Oriented Programming (Java)','4',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(13,2,1,3,'CS402','Discrete Mathematics & Graph Theory','4',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(14,2,1,5,'CS403','Computer Architecture & Organization','4',3,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(15,2,1,3,'CS404','Software Engineering & Testing','4',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(16,2,1,5,'CS405','Java Programming Laboratory','4',2,'lab',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(17,2,1,3,'CS101','Problem Solving with C Programming','1',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(18,2,1,5,'CS102','Mathematics for Computing I','1',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(19,2,1,3,'CS103','Digital Electronics & Logic Gates','1',3,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(20,2,1,5,'CS104','Communicative English & Technical Writing','1',3,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(21,2,1,3,'CS105','C Programming Laboratory','1',2,'lab',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(22,1,4,5,'BBA501','Financial Management & Investment Analysis','5',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(23,1,4,3,'BBA502','Marketing Management & Consumer Insights','5',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(24,1,4,5,'BBA503','Human Resource Management & Strategy','5',3,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(25,1,4,3,'BBA504','Business Research & Quantitative Methods','5',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(26,1,4,5,'BBA505','Business Analytics & Spreadsheet Lab','5',2,'lab',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(27,1,4,3,'BBA401','Organizational Behavior & Dynamics','4',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(28,1,4,5,'BBA402','Managerial Economics & Policy','4',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(29,1,4,3,'BBA403','Business Law & Corporate Governance','4',4,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(30,1,4,5,'BBA404','Cost & Management Accounting','4',3,'core',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(31,1,4,3,'BBA405','Corporate Communication Workshop','4',2,'lab',NULL,'active','2026-09-08 17:33:21','2026-09-08 17:33:21'),(34,1,4,5,'BBA103','Business Mathematics & Statistics','1',4,'core',NULL,'active','2026-09-08 17:33:22','2026-09-08 17:33:22'),(35,1,4,3,'BBA104','Business Communication & Etiquette','1',3,'core',NULL,'active','2026-09-08 17:33:22','2026-09-08 17:33:22'),(36,1,4,5,'BBA105','Computer Applications in Management','1',2,'lab',NULL,'active','2026-09-08 17:33:22','2026-09-08 17:33:22');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_tickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `response` text DEFAULT NULL,
  `assigned_to` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_st_user` (`user_id`),
  KEY `fk_st_admin` (`assigned_to`),
  CONSTRAINT `fk_st_admin` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_st_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_tickets`
--

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
INSERT INTO `support_tickets` VALUES (1,4,'Discrepancy in Attendance for Subject Lab','My attendance for the Friday lab session was marked absent due to biometric scanner sync issue.','medium','in_progress','Department coordinator is reviewing the physical lab register logs.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(2,4,'Course Material Access Permission','Unable to download reference PDF chapter for Unit 3 from the learning resources portal.','low','resolved','Digital library permissions have been refreshed. The document is now directly downloadable.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(3,6,'Discrepancy in Attendance for Subject Lab','My attendance for the Friday lab session was marked absent due to biometric scanner sync issue.','medium','in_progress','Department coordinator is reviewing the physical lab register logs.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(4,6,'Course Material Access Permission','Unable to download reference PDF chapter for Unit 3 from the learning resources portal.','low','resolved','Digital library permissions have been refreshed. The document is now directly downloadable.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(5,7,'Discrepancy in Attendance for Subject Lab','My attendance for the Friday lab session was marked absent due to biometric scanner sync issue.','medium','in_progress','Department coordinator is reviewing the physical lab register logs.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(6,7,'Course Material Access Permission','Unable to download reference PDF chapter for Unit 3 from the learning resources portal.','low','resolved','Digital library permissions have been refreshed. The document is now directly downloadable.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(7,12,'Discrepancy in Attendance for Subject Lab','My attendance for the Friday lab session was marked absent due to biometric scanner sync issue.','medium','in_progress','Department coordinator is reviewing the physical lab register logs.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(8,12,'Course Material Access Permission','Unable to download reference PDF chapter for Unit 3 from the learning resources portal.','low','resolved','Digital library permissions have been refreshed. The document is now directly downloadable.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(9,13,'Discrepancy in Attendance for Subject Lab','My attendance for the Friday lab session was marked absent due to biometric scanner sync issue.','medium','in_progress','Department coordinator is reviewing the physical lab register logs.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(10,13,'Course Material Access Permission','Unable to download reference PDF chapter for Unit 3 from the learning resources portal.','low','resolved','Digital library permissions have been refreshed. The document is now directly downloadable.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(11,14,'Discrepancy in Attendance for Subject Lab','My attendance for the Friday lab session was marked absent due to biometric scanner sync issue.','medium','in_progress','Department coordinator is reviewing the physical lab register logs.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(12,14,'Course Material Access Permission','Unable to download reference PDF chapter for Unit 3 from the learning resources portal.','low','resolved','Digital library permissions have been refreshed. The document is now directly downloadable.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(13,15,'Discrepancy in Attendance for Subject Lab','My attendance for the Friday lab session was marked absent due to biometric scanner sync issue.','medium','in_progress','Department coordinator is reviewing the physical lab register logs.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09'),(14,15,'Course Material Access Permission','Unable to download reference PDF chapter for Unit 3 from the learning resources portal.','low','resolved','Digital library permissions have been refreshed. The document is now directly downloadable.',2,'2026-09-08 17:42:09','2026-09-08 17:42:09');
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'app_name','StudentOS AI','Platform display brand name','2026-09-05 18:36:32'),(2,'academic_year','2026-2027','Current active academic year','2026-09-05 18:36:32'),(3,'allow_registration','1','Enable student self-registration','2026-09-05 18:36:32'),(4,'maintenance_mode','0','Put site in maintenance mode','2026-09-05 18:36:32'),(5,'max_upload_size_mb','20','Maximum file upload size in Megabytes','2026-09-05 18:36:32');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('todo','in_progress','completed') DEFAULT 'todo',
  `category` varchar(50) DEFAULT 'general',
  `deadline` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_tasks_user` (`user_id`),
  CONSTRAINT `fk_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
INSERT INTO `tasks` VALUES (1,4,'Complete DBMS BCNF decomposition assignment','Verify 3NF vs BCNF closure algorithms on given relation schema.','urgent','completed','academic','2026-09-10 23:59:00','2026-09-05 18:36:30','2026-09-05 18:36:30'),(2,4,'Prepare Dynamic Programming notes for Quiz','Review Memoization vs Tabulation for matrix multiplication and LCS.','high','in_progress','academic','2026-09-14 18:00:00','2026-09-05 18:36:30','2026-09-05 18:36:30'),(3,4,'Submit project proposal for Capstone','Finalize StudentOS AI architecture diagram and user flow.','medium','todo','project','2026-09-18 17:00:00','2026-09-05 18:36:30','2026-09-05 18:36:30'),(4,4,'Solve 5 LeetCode DP Problems','Focus on Coin Change, Longest Increasing Subsequence, and Word Break.','medium','in_progress','skill','2026-09-11 22:00:00','2026-09-05 18:36:30','2026-09-05 18:36:30'),(5,6,'Complete Unit Revision & Practice Problems','Review chapter slides and solve past semester exam questions.','high','in_progress','academic','2026-09-20 23:59:00','2026-09-08 17:42:10','2026-09-08 17:42:10'),(6,6,'Prepare Coursework Assignment Submission','Verify problem requirements and formatting before upload.','urgent','todo','academic','2026-09-18 18:00:00','2026-09-08 17:42:10','2026-09-08 17:42:10'),(7,6,'Study Session: System Architecture & Design','Group study and whiteboarding on distributed components.','medium','completed','academic','2026-09-12 16:00:00','2026-09-08 17:42:10','2026-09-08 17:42:10'),(8,7,'Complete Unit Revision & Practice Problems','Review chapter slides and solve past semester exam questions.','high','in_progress','academic','2026-09-20 23:59:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(9,7,'Prepare Coursework Assignment Submission','Verify problem requirements and formatting before upload.','urgent','todo','academic','2026-09-18 18:00:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(10,7,'Study Session: System Architecture & Design','Group study and whiteboarding on distributed components.','medium','completed','academic','2026-09-12 16:00:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(11,12,'Complete Unit Revision & Practice Problems','Review chapter slides and solve past semester exam questions.','high','in_progress','academic','2026-09-20 23:59:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(12,12,'Prepare Coursework Assignment Submission','Verify problem requirements and formatting before upload.','urgent','todo','academic','2026-09-18 18:00:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(13,12,'Study Session: System Architecture & Design','Group study and whiteboarding on distributed components.','medium','completed','academic','2026-09-12 16:00:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(14,13,'Complete Unit Revision & Practice Problems','Review chapter slides and solve past semester exam questions.','high','in_progress','academic','2026-09-20 23:59:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(15,13,'Prepare Coursework Assignment Submission','Verify problem requirements and formatting before upload.','urgent','todo','academic','2026-09-18 18:00:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(16,13,'Study Session: System Architecture & Design','Group study and whiteboarding on distributed components.','medium','completed','academic','2026-09-12 16:00:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(17,14,'Complete Unit Revision & Practice Problems','Review chapter slides and solve past semester exam questions.','high','in_progress','academic','2026-09-20 23:59:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(18,14,'Prepare Coursework Assignment Submission','Verify problem requirements and formatting before upload.','urgent','todo','academic','2026-09-18 18:00:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(19,14,'Study Session: System Architecture & Design','Group study and whiteboarding on distributed components.','medium','completed','academic','2026-09-12 16:00:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(20,15,'Complete Unit Revision & Practice Problems','Review chapter slides and solve past semester exam questions.','high','in_progress','academic','2026-09-20 23:59:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(21,15,'Prepare Coursework Assignment Submission','Verify problem requirements and formatting before upload.','urgent','todo','academic','2026-09-18 18:00:00','2026-09-08 17:42:11','2026-09-08 17:42:11'),(22,15,'Study Session: System Architecture & Design','Group study and whiteboarding on distributed components.','medium','completed','academic','2026-09-12 16:00:00','2026-09-08 17:42:11','2026-09-08 17:42:11');
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `two_factor_methods`
--

DROP TABLE IF EXISTS `two_factor_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `two_factor_methods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `method` enum('app','email','sms') NOT NULL DEFAULT 'app',
  `secret` varchar(255) NOT NULL,
  `is_default` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_2fa_user` (`user_id`),
  CONSTRAINT `fk_2fa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `two_factor_methods`
--

LOCK TABLES `two_factor_methods` WRITE;
/*!40000 ALTER TABLE `two_factor_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `two_factor_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_sessions_user` (`user_id`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES (1,4,'a4497788933a93468e5c5dd8edb5b783925f89486de4db359d8f1392b7f4b97a','127.0.0.1','PHP-CLI Test','2026-09-06 00:08:51','2026-09-06 20:38:51','2026-09-05 18:38:51'),(2,4,'3d523e9a302a026e414ef451089cf92d2451ca6b35be3177d2693817f803a695','::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-IN) WindowsPowerShell/5.1.26100.9278','2026-09-06 00:13:42','2026-09-06 20:43:42','2026-09-05 18:43:42'),(3,4,'0adec164122953a3cd539339b4f8c18159d0e78b58dfbcf546d7315b02682318','::1','Unknown','2026-09-07 17:58:07','2026-09-08 14:28:07','2026-09-07 12:28:07'),(4,4,'ddb43248d89cba22974c3839cad3841ef431d9e92f30c6f43f089b07b62af21d','127.0.0.1','PHP-CLI Test','2026-09-07 18:02:55','2026-09-08 14:32:55','2026-09-07 12:32:55'),(5,1,'6459469f7d7edd87f12791100ded5d4bbb7ffbad190cc3a59fc30dd675ca2195','127.0.0.1','CLI','2026-09-07 18:04:25','2026-09-08 14:34:25','2026-09-07 12:34:25'),(6,1,'ccaa2ca25d46947a57bcd0a6d086d29b7b51a60fd4396774b827553d5b561169','127.0.0.1','CLI','2026-09-07 18:05:41','2026-09-08 14:35:41','2026-09-07 12:35:41'),(7,1,'e7f9e0459458fd8e97c0801f88ad4686c49817b858977785a87a55fb228136c8','127.0.0.1','CLI','2026-09-07 18:05:41','2026-09-08 14:35:41','2026-09-07 12:35:41'),(9,1,'3fd08fbeffe1c3dc94206b56aa1c92626a907908f377ddd13233797e90213dd1','::1','Unknown','2026-09-07 19:32:41','2026-09-08 16:02:41','2026-09-07 14:02:41'),(12,4,'12bc1700d042a84bdcfc1052f1e4350584f0b10e4cb5f11f7cad1593316cb80b','127.0.0.1','CLI','2026-09-08 14:18:49','2026-09-09 10:48:49','2026-09-08 08:48:49'),(13,4,'bad947753e5acdbb2886a2cfb8f8169cc9a4fe9e1c8c279c8d79f9fd6d947260','::1','Unknown','2026-09-08 14:19:53','2026-09-09 10:49:53','2026-09-08 08:49:53'),(14,4,'96a98fbe679924e3ef11735ee5378794315891380523dd492b2eae66a653c79c','::1','Unknown','2026-09-08 14:51:00','2026-09-09 11:20:56','2026-09-08 09:20:56'),(15,3,'39b22d2c9def67068fd40e311ebcd80b6b6b56694091b35354bf7be3c3eaf902','::1','Unknown','2026-09-08 14:51:00','2026-09-09 11:20:57','2026-09-08 09:20:57'),(16,2,'480867bb50fd0de7ce785564b2d0b8c2ccc79c2cc6bb1b4e6a0aa90e07d3abb5','::1','Unknown','2026-09-08 14:51:00','2026-09-09 11:20:57','2026-09-08 09:20:57'),(17,1,'246ce7532f7284fc7ac62d98da69dd1c4e5abbbaf5315e617b1f9f1d8a87ecb5','::1','Unknown','2026-09-08 14:50:58','2026-09-09 11:20:58','2026-09-08 09:20:58'),(18,7,'3f7a1bc86376b2abfbcb88be271a0bda12d42995cf9f3aa0f994ac10bee3ffe9','::1','Unknown','2026-09-08 14:55:39','2026-09-09 11:25:39','2026-09-08 09:25:39'),(19,8,'7d62e9130c81f3c32abdf09ca38821296f4c0caeea112226e9143db0c0e12d29','::1','Unknown','2026-09-08 14:55:43','2026-09-09 11:25:43','2026-09-08 09:25:43'),(20,9,'5e0db5b8273c8c8006402d22ca542ebfb6b9e80c19d3f62b851ba7fce58d37d4','::1','Unknown','2026-09-08 14:55:44','2026-09-09 11:25:44','2026-09-08 09:25:44'),(21,10,'b00ec7f91c5cd0d751bc3d6315b9a28b6b1d34701790e53575e5d15a375d6f0d','::1','Unknown','2026-09-08 14:55:44','2026-09-09 11:25:44','2026-09-08 09:25:44'),(22,7,'7e58ebf25b5fb9f1fde47c5994ab74ef3ddacb124c269a29f5123890e2ff258f','::1','Unknown','2026-09-08 15:04:47','2026-09-09 11:34:47','2026-09-08 09:34:47'),(23,8,'db373ce8b4eb65f089cd986a9f574d545809cf90f513699c9bd0962f8a5f5e73','::1','Unknown','2026-09-08 15:04:52','2026-09-09 11:34:52','2026-09-08 09:34:52'),(24,9,'c2221cc03323bb99b0ca99452a3553accc11f2215ce08629ee459b885443f89d','::1','Unknown','2026-09-08 15:04:58','2026-09-09 11:34:58','2026-09-08 09:34:58'),(25,10,'b54527c627799b90f581626f065a5d926d59a29b6b8ede0acc355b934741a79e','::1','Unknown','2026-09-08 15:05:04','2026-09-09 11:35:04','2026-09-08 09:35:04'),(27,8,'a58bdb30adc95210fa72ed270162b37a99dcfb1fa33d437acfbf32eabd91227e','::1','Unknown','2026-09-08 19:10:56','2026-09-09 15:40:53','2026-09-08 13:40:53'),(28,12,'edc85e1ef5e8394e652385eeb8d7c424f7b907fba0cb5a1e15c2e7f03ab55bd4','::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-IN) WindowsPowerShell/5.1.26100.9278','2026-09-08 19:48:33','2026-09-09 16:18:33','2026-09-08 14:18:33'),(30,12,'1780e2909baed859195c72b22d4f089fe2a39e379ad952268a2be096fc159bc8','::1','Unknown','2026-09-08 20:30:44','2026-09-09 17:00:44','2026-09-08 15:00:44'),(31,13,'89ed63e872da1f572cdfec5cdf5a6fabfa85ab627587da9f51249e6250b2e6a2','::1','Unknown','2026-09-08 20:30:52','2026-09-09 17:00:52','2026-09-08 15:00:52'),(32,4,'c5a6263d627f698f1ff2319a44cf9e57462c5af892e4e8134c957c01f6b30cc6','::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-IN) WindowsPowerShell/5.1.26100.9278','2026-09-08 20:46:10','2026-09-09 17:16:10','2026-09-08 15:16:10'),(33,4,'a2bc88814377494b583f2c42382e28d624969d07bb27ba9643a0ddb1fbc2ccc6','::1','Mozilla/5.0 (Windows NT; Windows NT 10.0; en-IN) WindowsPowerShell/5.1.26100.9278','2026-09-08 20:46:17','2026-09-09 17:16:17','2026-09-08 15:16:17'),(34,4,'bb2f462eee5e522d0df005f118b9d4936f9d7d6cffda71a8161b03d0e64e3eab','::1','Unknown','2026-09-08 20:46:32','2026-09-09 17:16:32','2026-09-08 15:16:32'),(35,12,'a67e95f204bda7affc1f706bb677410011c9eef7b0ec83c87f67959a80f609ee','::1','Unknown','2026-09-08 20:46:38','2026-09-09 17:16:38','2026-09-08 15:16:38'),(36,13,'36c3d686332609480870407d782398a811c61ab68d3ed8314cec98caef283719','::1','Unknown','2026-09-08 20:46:46','2026-09-09 17:16:46','2026-09-08 15:16:46'),(38,10,'f1f1d6a65416bbf8f486753d86ed028b1f4ffc8112870d253d6354ca496f3165','::1','Unknown','2026-09-08 21:31:55','2026-09-09 18:01:55','2026-09-08 16:01:55'),(39,8,'46beed81ff3cacaa06741e256ae4bf5ea82ef98f495ea343f621c888c7734844','::1','Unknown','2026-09-08 22:32:58','2026-09-09 18:02:49','2026-09-08 16:02:49'),(41,9,'4576c2dc814f3fa49c6a89a2258f02da0e8cb3d434f547339a87210d93e8a63b','::1','Unknown','2026-09-08 22:35:42','2026-09-09 19:05:42','2026-09-08 17:05:42'),(42,4,'88ce7be0fb661c75f97fbaaa44b24705558be2668cce9d8c19c2f40b72646591','::1','Unknown','2026-09-08 23:06:08','2026-09-09 19:36:08','2026-09-08 17:36:08'),(43,4,'decfa331ffff158c9480b4884df50b33429e58cc45abac73d29a305e447c8642','::1','Unknown','2026-09-08 23:06:18','2026-09-09 19:36:18','2026-09-08 17:36:18'),(44,12,'fa3882171a1acbb478a1745839fc09ad72ec9311044dcd950a62f8a267f8fe96','::1','Unknown','2026-09-08 23:06:36','2026-09-09 19:36:36','2026-09-08 17:36:36');
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default-avatar.png',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_email` (`email`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'superadmin@studentos.ai','$2y$12$8ws3j8bu7mE0GahK.EccVewq4V7dc9j.w4vBnEHWZegnlX55gcntO','Marcus','Vance','9876543210','default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 14:50:58','2026-09-05 18:36:28','2026-09-08 09:20:58',NULL),(2,2,'admin@studentos.ai','$2y$12$8ws3j8bu7mE0GahK.EccVewq4V7dc9j.w4vBnEHWZegnlX55gcntO','Elena','Rostova','9876543211','default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 14:50:57','2026-09-05 18:36:28','2026-09-08 09:20:57',NULL),(3,3,'faculty@studentos.ai','$2y$12$jHdMXr.mQrAZ.GD6j4VnbOCFs4RbNrI/5qastKzodQWdMawSnqDIW','Dr. Alan','Turing','9876543212','default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 14:50:57','2026-09-05 18:36:28','2026-09-08 09:20:57',NULL),(4,4,'student@studentos.ai','$2y$12$LoDxKa4jUf054bDJjOVXn.8CKDmBA5yUzrn0Wnw/ygClHKRz3zAN2','Alex','Johnson','9876543213','default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 23:06:18','2026-09-05 18:36:28','2026-09-08 17:36:18',NULL),(5,3,'sarah.connor@studentos.ai','$2y$12$oeElY5JWtihGrIFzyxmTCOoMbDUI0yRXsFJnuiYypuWqVPp1RK39y','Prof. Sarah','Connor','9876543214','default-avatar.png',1,1,0,NULL,NULL,0,NULL,'2026-09-05 18:36:28','2026-09-05 18:36:28',NULL),(6,4,'emma.watson@studentos.ai','$2y$12$LoDxKa4jUf054bDJjOVXn.8CKDmBA5yUzrn0Wnw/ygClHKRz3zAN2','Emma','Watson','9876543215','default-avatar.png',1,1,0,NULL,NULL,0,NULL,'2026-09-05 18:36:28','2026-09-05 18:36:28',NULL),(7,4,'student@gmail.com','$2y$10$83qZ4NYhEp8t6oaIsGpwbuWcfi4UvHI882izy5eR74UrFTaIwJRCq','Alex','Johnson',NULL,'default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 15:04:47','2026-09-08 09:25:06','2026-09-08 09:34:47',NULL),(8,3,'faculty@gmail.com','$2y$10$m58RZupmap7QhdfhCS4zbuaCmj2A6syhoOERn76NFxZHbXJxgH0X2','Dr. Alan','Turing',NULL,'default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 21:32:49','2026-09-08 09:25:22','2026-09-08 16:02:49',NULL),(9,2,'admin@gmail.com','$2y$10$KEwncXPPG50sW1VVY.ShnOBcctcXuGvLYBcapS/NGB1paMTj9jiAm','Elena','Rostova',NULL,'default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 22:35:42','2026-09-08 09:25:22','2026-09-08 17:05:42',NULL),(10,1,'superadmin@gmail.com','$2y$10$KEwncXPPG50sW1VVY.ShnOBcctcXuGvLYBcapS/NGB1paMTj9jiAm','Marcus','Vance',NULL,'default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 21:31:55','2026-09-08 09:25:22','2026-09-08 16:01:55',NULL),(12,4,'rohit.bba@studentos.ai','$2y$12$RuFtt3uadEn7BLPkyqR1MOYEZuUZCVykLZLcO0yHzAH4ZuX1RMMly','Rohit','Sharma',NULL,'default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 23:06:36','2026-09-08 14:18:22','2026-09-08 17:36:36',NULL),(13,4,'priya.bca@studentos.ai','$2y$12$ltTxKFBHzeWxT8B73iM7Ge.7gfoaHVMYKhILn2XOvN34snJA5Mada','Priya','Patel',NULL,'default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 20:46:46','2026-09-08 14:18:38','2026-09-08 15:16:46',NULL),(14,4,'Chandan@gmail.com','$2y$12$eHqdIsELNyTJFnZ4xxder.Eu4Q7ZU0eFh1aK0tQiFdUV73XBL7p1O','Chandan','Kuiri',NULL,'default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 20:01:53','2026-09-08 14:31:25','2026-09-08 14:31:53',NULL),(15,4,'rakesh@gmail.com','$2y$12$sAzswqfxWl0Uk/Ez4tBzTuRTo0TVxpMNvcLjPsJOU57KviB.EphZq','rakesh','kuiri',NULL,'default-avatar.png',1,1,0,NULL,NULL,0,'2026-09-08 22:18:41','2026-09-08 15:48:51','2026-09-08 16:48:41',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-08 23:25:09
