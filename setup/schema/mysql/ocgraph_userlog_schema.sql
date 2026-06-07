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

-- 外部キー制約チェックを再度有効化
SET FOREIGN_KEY_CHECKS=1;

-- ============================================================
-- オプチャグラフα関連テーブル
-- αテーブルは言語サフィックス方式（当面 _ja のみ。多言語化時は _tw 等を増設する）。
-- userlog は言語共有DBのため、言語は DB ではなくテーブル名サフィックスで表現する。
-- ============================================================

-- Alpha Labs: 部屋別の日次アクセス/検索流入（GA4 / Search Console を日次バッチで集計して保存）。
-- ja(base)専用。open_chat と join して「アクセス数ランキング」「検索流入(SEO)ランキング」を出す。
DROP TABLE IF EXISTS `alpha_room_access_daily_ja`;
CREATE TABLE `alpha_room_access_daily_ja` (
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
DROP TABLE IF EXISTS `alpha_page_access_daily_ja`;
CREATE TABLE `alpha_page_access_daily_ja` (
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
DROP TABLE IF EXISTS `alpha_search_query_daily_ja`;
CREATE TABLE `alpha_search_query_daily_ja` (
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
DROP TABLE IF EXISTS `alpha_room_search_query_daily_ja`;
CREATE TABLE `alpha_room_search_query_daily_ja` (
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
DROP TABLE IF EXISTS `alpha_room_referrer_daily_ja`;
CREATE TABLE `alpha_room_referrer_daily_ja` (
  `open_chat_id` int(11) NOT NULL,
  `referrer` varchar(190) NOT NULL,
  `date` date NOT NULL,
  `pageviews` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`open_chat_id`,`referrer`,`date`),
  KEY `date_idx` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alpha Labs: 非部屋ページ（トップ '/' / おすすめ '/recommend/{tag}'）の日次入室数（近似）事前集計。
-- alpha_room_referrer_daily_ja × alpha_room_access_daily_ja の LIKE 相関スキャンを日次バッチで事前集計し、
-- getPageScopeRanking の高速化に使う（リクエスト毎の重い LIKE スキャンを廃止）。
-- 意味: 日 D において、部屋の当日 jump_clicks / jump_clicks_organic を「page_path 経由の流入PV ÷ 全 referrer PV」で
-- 按分（PV比按分の近似）した合計。分母は外部・(direct) を含む全 referrer PV のため、外部由来分は内部ページに帰属させない
-- （ページ合計 ≦ 部屋合計）。
CREATE TABLE IF NOT EXISTS `alpha_page_jump_daily_ja` (
  `page_path` varchar(190) NOT NULL,
  `date` date NOT NULL,
  `jump_clicks` int(11) NOT NULL DEFAULT 0,
  `jump_clicks_organic` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`page_path`,`date`),
  KEY `date_idx` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Alpha 通知/アラート機能 (ja のみ稼働 / 追加のみ・既存破壊なし)
-- user_id は cookie-user-id の sha3-256 (同DBの oc_list_user と同じ varchar(64)。マイリスト本体は oc_list_user)
-- ============================================================

-- ウォッチキーワード設定: 一致する「新しい部屋」を毎時検出する
DROP TABLE IF EXISTS `alpha_keyword_watch_ja`;
CREATE TABLE `alpha_keyword_watch_ja` (
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
DROP TABLE IF EXISTS `alpha_room_watch_ja`;
CREATE TABLE `alpha_room_watch_ja` (
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

-- マイリスト変動しきい値 (ユーザーにつき1組)
-- scope: 'all'(マイリスト全体) | 'root'(ルート直下のみ) | 'folder'(特定フォルダ配下のみ)
-- up_member/down_member: 人数しきい値（%と併用可。部屋ウォッチと同じ意味）
-- target_oc_ids: scope に応じてフロントが解決した対象 open_chat_id の JSON 配列。
--   マイリストのフォルダ構造は localStorage のみでサーバに無いため、対象集合は
--   フロント（構造を持つ側）が解決してここに保存する。NULL の旧行は従来どおり
--   oc_list_user.oc_list（全体）にフォールバックする。
DROP TABLE IF EXISTS `alpha_mylist_threshold_ja`;
CREATE TABLE `alpha_mylist_threshold_ja` (
  `user_id` varchar(64) NOT NULL,
  `up_percent` float DEFAULT NULL,
  `down_percent` float DEFAULT NULL,
  `up_member` int(11) DEFAULT NULL,
  `down_member` int(11) DEFAULT NULL,
  `scope` varchar(16) NOT NULL DEFAULT 'all',
  `target_oc_ids` text DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- キーワード検出の重複防止: 既に通知済みの (キーワードウォッチ × emid)
DROP TABLE IF EXISTS `alpha_keyword_seen_ja`;
CREATE TABLE `alpha_keyword_seen_ja` (
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
DROP TABLE IF EXISTS `alpha_search_seen_room_ja`;
CREATE TABLE `alpha_search_seen_room_ja` (
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
DROP TABLE IF EXISTS `alpha_search_timing_ja`;
CREATE TABLE `alpha_search_timing_ja` (
  `query_key` varchar(190) NOT NULL,
  `elapsed_ms` int(11) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`query_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 算出済み通知アイテム
-- type: 'keyword' | 'room' | 'mylist'
-- dedup_key: 同一通知の重複保存防止 (UNIQUE)
DROP TABLE IF EXISTS `alpha_notification_ja`;
CREATE TABLE `alpha_notification_ja` (
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

-- Web Push 購読（α: ペイロード無し tickle 送信用）
-- endpoint は長い（FCM等で~400字）ため UNIQUE は SHA-256 ハッシュ列に張る
DROP TABLE IF EXISTS `alpha_push_subscription_ja`;
CREATE TABLE `alpha_push_subscription_ja` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `endpoint_hash` varchar(64) NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(64) NOT NULL,
  `fail_count` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_endpoint_hash` (`endpoint_hash`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
