-- Schema for database: ocgraph_ocreview
-- Extracted from: ocgraph_ocreview.sql
-- Generated: Mon Jan 12 01:35:54 JST 2026

CREATE DATABASE IF NOT EXISTS `ocgraph_ocreview` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ocgraph_ocreview`;

DROP TABLE IF EXISTS `ads`;
CREATE TABLE `ads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ads_title` text NOT NULL,
  `ads_sponsor_name` text NOT NULL,
  `ads_paragraph` text NOT NULL,
  `ads_href` text NOT NULL,
  `ads_img_url` text NOT NULL,
  `ads_tracking_url` text NOT NULL,
  `ads_title_button` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DROP TABLE IF EXISTS `ads_tag_map`;
CREATE TABLE `ads_tag_map` (
  `tag` varchar(255) NOT NULL,
  `ads_id` int(11) NOT NULL,
  UNIQUE KEY `tag` (`tag`),
  KEY `ads_tag` (`ads_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `ranking_position` int(11) DEFAULT NULL,
  `ranking_total` int(11) DEFAULT NULL,
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
-- Alpha Labs: 部屋別の日次アクセス/検索流入（GA4 / Search Console を日次バッチで集計して保存）。
-- ja(base)専用。open_chat と join して「アクセス数ランキング」「検索流入(SEO)ランキング」を出す。
DROP TABLE IF EXISTS `alpha_room_access_daily`;
CREATE TABLE `alpha_room_access_daily` (
  `open_chat_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `pageviews` int(11) NOT NULL DEFAULT 0,
  `search_clicks` int(11) NOT NULL DEFAULT 0,
  `search_impressions` int(11) NOT NULL DEFAULT 0,
  `search_position` float DEFAULT NULL,
  `active_users` int(11) NOT NULL DEFAULT 0,
  `jump_clicks` int(11) NOT NULL DEFAULT 0,
  `jump_clicks_organic` int(11) NOT NULL DEFAULT 0,
  `engagement_seconds` float DEFAULT NULL,
  PRIMARY KEY (`open_chat_id`,`date`),
  KEY `date_idx` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alpha Labs: 非部屋ページ（トップ '/' / おすすめ '/recommend/{tag}'）の日次アクセス/検索流入。
-- path をキーに日次で upsert。ランキングAPIの pages 別枠で返す。
DROP TABLE IF EXISTS `alpha_page_access_daily`;
CREATE TABLE `alpha_page_access_daily` (
  `path` varchar(190) NOT NULL,
  `date` date NOT NULL,
  `label` varchar(190) NOT NULL DEFAULT '',
  `pageviews` int(11) NOT NULL DEFAULT 0,
  `active_users` int(11) NOT NULL DEFAULT 0,
  `search_clicks` int(11) NOT NULL DEFAULT 0,
  `search_impressions` int(11) NOT NULL DEFAULT 0,
  `search_position` float DEFAULT NULL,
  PRIMARY KEY (`path`,`date`),
  KEY `date_idx` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alpha Labs: GSC の上位検索クエリ（query ディメンション）を日次で保存。
-- 検索クエリランキング(/alpha-api/search-query-ranking)の元データ。
DROP TABLE IF EXISTS `alpha_search_query_daily`;
CREATE TABLE `alpha_search_query_daily` (
  `query` varchar(190) NOT NULL,
  `date` date NOT NULL,
  `clicks` int(11) NOT NULL DEFAULT 0,
  `impressions` int(11) NOT NULL DEFAULT 0,
  `position` float DEFAULT NULL,
  PRIMARY KEY (`query`,`date`),
  KEY `date_idx` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alpha Labs: 部屋別の流入検索クエリ（GSC searchAnalytics dimensions=[page, query] を /oc/{id} に畳んで日次保存）。
-- 詳細画面「流入キーワード」(room-metrics の searchQueries)の元データ。
DROP TABLE IF EXISTS `alpha_room_search_query_daily`;
CREATE TABLE `alpha_room_search_query_daily` (
  `open_chat_id` int(11) NOT NULL,
  `query` varchar(190) NOT NULL,
  `date` date NOT NULL,
  `clicks` int(11) NOT NULL DEFAULT 0,
  `impressions` int(11) NOT NULL DEFAULT 0,
  `position` float DEFAULT NULL,
  PRIMARY KEY (`open_chat_id`,`query`,`date`),
  KEY `date_idx` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alpha Labs: 部屋別のリファラ元（GA4 dimensions=[pagePath, pageReferrer] を /oc/{id} に畳んで日次保存）。
-- 詳細画面「リファラ元」(room-metrics の referrers)の元データ。空/(not set) は '(direct)' に正規化済み。
DROP TABLE IF EXISTS `alpha_room_referrer_daily`;
CREATE TABLE `alpha_room_referrer_daily` (
  `open_chat_id` int(11) NOT NULL,
  `referrer` varchar(190) NOT NULL,
  `date` date NOT NULL,
  `pageviews` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`open_chat_id`,`referrer`,`date`),
  KEY `date_idx` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alpha Labs: 非部屋ページ（トップ '/' / おすすめ '/recommend/{tag}'）の日次入室数（近似）事前集計。
-- alpha_room_referrer_daily × alpha_room_access_daily の LIKE 相関スキャンを日次バッチで事前集計し、
-- getPageScopeRanking の高速化に使う（リクエスト毎の重い LIKE スキャンを廃止）。
-- 意味: 日 D において、部屋の当日 jump_clicks / jump_clicks_organic を「page_path 経由の流入PV ÷ 全 referrer PV」で
-- 按分（PV比按分の近似）した合計。分母は外部・(direct) を含む全 referrer PV のため、外部由来分は内部ページに帰属させない
-- （ページ合計 ≦ 部屋合計）。
CREATE TABLE IF NOT EXISTS `alpha_page_jump_daily` (
  `page_path` varchar(190) NOT NULL,
  `date` date NOT NULL,
  `jump_clicks` int(11) NOT NULL DEFAULT 0,
  `jump_clicks_organic` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`page_path`,`date`),
  KEY `date_idx` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
