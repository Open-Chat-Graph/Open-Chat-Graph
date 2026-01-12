-- Schema for database: ocgraph_userlog
-- Extracted from: ocgraph_userlog.sql
-- Generated: Mon Jan 12 01:36:00 JST 2026

CREATE DATABASE IF NOT EXISTS `ocgraph_userlog` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ocgraph_userlog`;

-- 外部キー制約チェックを一時的に無効化
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `oc_list_user`;
CREATE TABLE `oc_list_user` (
  `user_id` varchar(64) NOT NULL,
  `oc_list` text NOT NULL,
  `list_count` int(11) NOT NULL,
  `expires` int(11) NOT NULL,
  `ip` text NOT NULL,
  `ua` text NOT NULL,
  PRIMARY KEY (`user_id`),
  KEY `expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `oc_list_user_list_show_log`;
CREATE TABLE `oc_list_user_list_show_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_id` FOREIGN KEY (`user_id`) REFERENCES `oc_list_user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24316 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 外部キー制約チェックを再度有効化
SET FOREIGN_KEY_CHECKS=1;
