-- Schema for database: ocgraph_ranking
-- Extracted from: ocgraph_ranking.sql
-- Generated: Mon Jan 12 01:35:58 JST 2026

CREATE DATABASE IF NOT EXISTS `ocgraph_ranking` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ocgraph_ranking`;

DROP TABLE IF EXISTS `member`;
CREATE TABLE `member` (
  `open_chat_id` int(11) NOT NULL,
  `member` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY `open_chat_id` (`open_chat_id`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `ranking`;
CREATE TABLE `ranking` (
  `open_chat_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `category` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY `open_chat_id` (`open_chat_id`,`category`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `rising`;
CREATE TABLE `rising` (
  `open_chat_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `category` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY `open_chat_id` (`open_chat_id`,`category`,`time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `total_count`;
CREATE TABLE `total_count` (
  `total_count_rising` int(11) NOT NULL,
  `total_count_ranking` int(11) NOT NULL,
  `time` datetime NOT NULL,
  `category` int(11) NOT NULL,
  UNIQUE KEY `time` (`time`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
