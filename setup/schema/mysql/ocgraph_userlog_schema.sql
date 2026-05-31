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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Alpha 通知/アラート機能 (ja のみ稼働 / 追加のみ・既存破壊なし)
-- user_id は cookie-user-id の sha3-256 (oc_list_user と同じ varchar(64))
-- ============================================================

-- ウォッチキーワード設定: 一致する「新しい部屋」を毎時検出する
DROP TABLE IF EXISTS `alpha_keyword_watch`;
CREATE TABLE `alpha_keyword_watch` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `keyword` varchar(190) NOT NULL,
  `category` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_keyword_category` (`user_id`,`keyword`,`category`),
  KEY `keyword` (`keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ウォッチ部屋設定: 指定部屋の人数±/%±を検出する
DROP TABLE IF EXISTS `alpha_room_watch`;
CREATE TABLE `alpha_room_watch` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `open_chat_id` int(11) NOT NULL,
  `up_member` int(11) DEFAULT NULL,
  `up_percent` float DEFAULT NULL,
  `down_member` int(11) DEFAULT NULL,
  `down_percent` float DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_oc` (`user_id`,`open_chat_id`),
  KEY `open_chat_id` (`open_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- マイリスト全体に効く既定%しきい値 (ユーザーにつき1組)
DROP TABLE IF EXISTS `alpha_mylist_threshold`;
CREATE TABLE `alpha_mylist_threshold` (
  `user_id` varchar(64) NOT NULL,
  `up_percent` float DEFAULT NULL,
  `down_percent` float DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- キーワード検出の重複防止: 既に通知済みの (キーワードウォッチ × emid)
DROP TABLE IF EXISTS `alpha_keyword_seen`;
CREATE TABLE `alpha_keyword_seen` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `keyword_watch_id` int(11) NOT NULL,
  `emid` varchar(255) NOT NULL,
  `seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_watch_emid` (`keyword_watch_id`,`emid`),
  KEY `keyword_watch_id` (`keyword_watch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 検索に出るが「オプチャグラフ未登録」の部屋を共有プールとして管理する。
-- 毎時、全ユーザーのアラートキーワードのユニーク集合で LINE公式検索を叩き、
-- open_chat に未登録の emid をここに upsert する（ユーザー横断で1行/部屋）。
-- keywords: その部屋を見つけたアラートキーワードの集合（カンマ連結・ユニーク）。
-- 配信は keyword_watch.created_at <= first_seen_at かつ K が keywords に完全一致 のものだけ。
DROP TABLE IF EXISTS `alpha_search_seen_room`;
CREATE TABLE `alpha_search_seen_room` (
  `emid` varchar(255) NOT NULL,
  `name` varchar(190) DEFAULT NULL,
  `member` int(11) DEFAULT NULL,
  `keywords` text NOT NULL,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`emid`),
  KEY `first_seen_at` (`first_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 検索ETA（プログレスバー用）: 正規化した query_key ごとに直近の検索処理 wall time を記録。
-- /alpha-api/search が毎回 upsert し、/alpha-api/search-eta が読む。
DROP TABLE IF EXISTS `alpha_search_timing`;
CREATE TABLE `alpha_search_timing` (
  `query_key` varchar(190) NOT NULL,
  `elapsed_ms` int(11) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`query_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 算出済み通知アイテム
-- type: 'keyword' | 'room' | 'mylist'
-- dedup_key: 同一通知の重複保存防止 (UNIQUE)
DROP TABLE IF EXISTS `alpha_notification`;
CREATE TABLE `alpha_notification` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `type` varchar(16) NOT NULL,
  `payload` text NOT NULL,
  `dedup_key` varchar(190) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_dedup` (`user_id`,`dedup_key`),
  KEY `user_created` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 外部キー制約チェックを再度有効化
SET FOREIGN_KEY_CHECKS=1;
