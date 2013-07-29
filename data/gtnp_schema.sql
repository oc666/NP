-- MySQL dump 10.13  Distrib 5.1.41, for debian-linux-gnu (i486)
--
-- Host: localhost    Database: gtnp
-- ------------------------------------------------------
-- Server version	5.1.41-3ubuntu12.10

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Activity_Process`
--

DROP TABLE IF EXISTS `Activity_Process`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Activity_Process` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `process_type` char(6) NOT NULL DEFAULT '',
  `process_parent_status` char(10) DEFAULT NULL,
  `from` char(2) NOT NULL DEFAULT '',
  `to` char(2) DEFAULT NULL,
  `agg_date` char(19) DEFAULT NULL,
  `recipient` char(2) DEFAULT NULL,
  `donor` char(2) DEFAULT NULL,
  `count` int(12) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1335 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Activity_Timers`
--

DROP TABLE IF EXISTS `Activity_Timers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Activity_Timers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` char(21) NOT NULL DEFAULT '',
  `timer` char(6) DEFAULT NULL,
  `transaction_time` datetime DEFAULT NULL,
  `network_type` char(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id_idx` (`request_id`),
  KEY `timer_idx` (`timer`),
  KEY `transaction_time_idx` (`transaction_time`)
) ENGINE=InnoDB AUTO_INCREMENT=275 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Activity_Timers_Agg`
--

DROP TABLE IF EXISTS `Activity_Timers_Agg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Activity_Timers_Agg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timer_type` varchar(10) NOT NULL,
  `start_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `process_on_time_count` int(10) DEFAULT NULL,
  `process_delay_count` int(10) DEFAULT NULL,
  `obligated_op` char(2) DEFAULT NULL,
  `waiting_op` char(2) DEFAULT NULL,
  `process_delay_percentage` varchar(4) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8 COMMENT='utf8_general_ci';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Logs`
--

DROP TABLE IF EXISTS `Logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `process_type` char(6) DEFAULT NULL,
  `msg_type` char(30) DEFAULT NULL,
  `number` char(16) DEFAULT NULL,
  `from` char(2) DEFAULT NULL,
  `to` char(2) DEFAULT NULL,
  `time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `additional` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13836 DEFAULT CHARSET=utf8 COMMENT='utf8_general_ci';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Requests`
--

DROP TABLE IF EXISTS `Requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` char(21) NOT NULL DEFAULT 'NULL',
  `from_provider` char(2) NOT NULL,
  `to_provider` char(2) NOT NULL,
  `status` tinyint(4) DEFAULT NULL,
  `last_requests_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_transaction` char(21) NOT NULL DEFAULT 'NULL',
  `flags` char(1) DEFAULT NULL,
  `number` char(16) DEFAULT 'NULL',
  `transfer_time` timestamp NULL DEFAULT NULL,
  `cron_lock` tinyint(4) NOT NULL DEFAULT '0',
  `auto_check` tinyint(4) DEFAULT '0',
  `disconnect_time` timestamp NULL DEFAULT NULL,
  `connect_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `KEY1` (`last_transaction`),
  KEY `KEY2` (`request_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2786 DEFAULT CHARSET=utf8 COMMENT='utf8_general_ci';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Shutdown`
--

DROP TABLE IF EXISTS `Shutdown`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Shutdown` (
  `provider` varchar(2) NOT NULL,
  `type` varchar(4) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `duration` int(50) DEFAULT NULL,
  PRIMARY KEY (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='utf8_general_ci';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Timers_Activity`
--

DROP TABLE IF EXISTS `Timers_Activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Timers_Activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` char(21) NOT NULL DEFAULT '',
  `timer` char(6) DEFAULT NULL,
  `transaction_time` datetime DEFAULT NULL,
  `network_type` char(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='utf8_general_ci';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Transactions`
--

DROP TABLE IF EXISTS `Transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trx_no` char(14) DEFAULT NULL,
  `request_id` char(21) NOT NULL,
  `message_type` char(30) NOT NULL,
  `last_transactions_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `requested_transfer_time` timestamp NULL DEFAULT NULL,
  `ack_code` char(5) DEFAULT NULL,
  `reject_reason_code` char(5) DEFAULT NULL,
  `target` char(2) DEFAULT NULL,
  `donor` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `Trx_no` (`trx_no`)
) ENGINE=InnoDB AUTO_INCREMENT=11282 DEFAULT CHARSET=utf8 COMMENT='utf8_general_ci';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2012-04-22 18:51:39
