-- Schema for database: ocgraph_ocreview
-- Extracted from: ocgraph_ocreview.sql
-- Generated: Mon Jan 12 01:35:54 JST 2026

CREATE DATABASE IF NOT EXISTS `ocgraph_ocreview` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ocgraph_ocreview`;

DROP TABLE IF EXISTS `api_data_download_state`;
CREATE TABLE `api_data_download_state` (
  `category` int(11) NOT NULL,
  `ranking` int(11) NOT NULL,
  `rising` int(11) NOT NULL,
  PRIMARY KEY (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `modify_recommend`;
CREATE TABLE `modify_recommend` (
  `id` int(11) NOT NULL,
  `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
DROP TABLE IF EXISTS `oc_tag`;
CREATE TABLE `oc_tag` (
  `id` int(11) NOT NULL,
  `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`(768))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `oc_tag2`;
CREATE TABLE `oc_tag2` (
  `id` int(11) NOT NULL,
  `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`(768))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `open_chat`;
CREATE TABLE `open_chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `img_url` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `local_img_url` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `member` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `emid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `category` int(11) DEFAULT NULL,
  `api_created_at` int(11) DEFAULT NULL,
  `emblem` int(11) DEFAULT NULL,
  `join_method_type` int(11) NOT NULL DEFAULT 0,
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `update_items` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emid` (`emid`),
  KEY `member` (`member`),
  KEY `updated_at` (`updated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `oc_sitemap_lastmod`;
CREATE TABLE `oc_sitemap_lastmod` (
  `open_chat_id` int(11) NOT NULL,
  `lastmod` datetime NOT NULL,
  `member_snapshot` int(11) NOT NULL,
  PRIMARY KEY (`open_chat_id`),
  KEY `lastmod` (`lastmod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `open_chat_deleted`;
CREATE TABLE `open_chat_deleted` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `deleted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `ranking_ban`;
CREATE TABLE `ranking_ban` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `open_chat_id` int(11) NOT NULL,
  `datetime` datetime NOT NULL,
  `percentage` int(11) NOT NULL,
  `member` int(11) NOT NULL,
  `flag` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL,
  `update_items` text DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ranking_ban_open_chat_datetime` (`open_chat_id`,`datetime`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
DROP TABLE IF EXISTS `recommend`;
CREATE TABLE `recommend` (
  `id` int(11) NOT NULL,
  `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`(768))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `recovery`;
CREATE TABLE `recovery` (
  `id` int(11) NOT NULL,
  `name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
DROP TABLE IF EXISTS `reject_room`;
CREATE TABLE `reject_room` (
  `emid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`emid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
DROP TABLE IF EXISTS `statistics_ranking_day`;
CREATE TABLE `statistics_ranking_day` (
  `id` int(11) NOT NULL,
  `open_chat_id` int(11) NOT NULL,
  `diff_member` int(11) NOT NULL,
  `percent_increase` float NOT NULL,
  PRIMARY KEY (`id`),
  KEY `open_chat_id` (`open_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
DROP TABLE IF EXISTS `statistics_ranking_hour`;
CREATE TABLE `statistics_ranking_hour` (
  `id` int(11) NOT NULL,
  `open_chat_id` int(11) NOT NULL,
  `diff_member` int(11) NOT NULL,
  `percent_increase` float NOT NULL,
  PRIMARY KEY (`id`),
  KEY `open_chat_id` (`open_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
DROP TABLE IF EXISTS `statistics_ranking_hour24`;
CREATE TABLE `statistics_ranking_hour24` (
  `id` int(11) NOT NULL,
  `open_chat_id` int(11) NOT NULL,
  `diff_member` int(11) NOT NULL,
  `percent_increase` float NOT NULL,
  PRIMARY KEY (`id`),
  KEY `open_chat_id` (`open_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
DROP TABLE IF EXISTS `statistics_ranking_week`;
CREATE TABLE `statistics_ranking_week` (
  `id` int(11) NOT NULL,
  `open_chat_id` int(11) NOT NULL,
  `diff_member` int(11) NOT NULL,
  `percent_increase` float NOT NULL,
  PRIMARY KEY (`id`),
  KEY `open_chat_id` (`open_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
DROP TABLE IF EXISTS `sync_open_chat_state`;
CREATE TABLE `sync_open_chat_state` (
  `type` varchar(64) NOT NULL,
  `bool` int(11) NOT NULL DEFAULT 0,
  `extra` text NOT NULL DEFAULT '',
  UNIQUE KEY `name_2` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `user_log`;
CREATE TABLE `user_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `time` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(100) NOT NULL,
  `message` text DEFAULT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `ip` varchar(50) NOT NULL,
  `ua` varchar(512) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `oc_page_cache`;
-- ルーム個別ページの「分析文(narrative)」事前計算データ（部屋単位・再生成可能なキャッシュ）。
-- narrative_data は JSON {summary,detail,meta_description,pattern}（空=データ無し）。
-- chart_meta はグラフ初回ロードのタブ/ボタン出し分け「可用性メタ」の JSON（NULL=未計算→meta=1でライブ計算）。
-- /oc 表示時は open_chat への LEFT JOIN(getOpenChatByIdWithTag)で1クエリ取得する。
CREATE TABLE `oc_page_cache` (
  `open_chat_id` int(11) NOT NULL,
  `narrative_data` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `chart_meta` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`open_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
