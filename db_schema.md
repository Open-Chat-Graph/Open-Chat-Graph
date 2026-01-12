# データベーススキーマ

## 多言語対応

URLパス（`''`, `/tw`, `/th`）で自動切り替え - [`app/Models/Repositories/DB.php`](app/Models/Repositories/DB.php)

各言語で同一構造のDBが存在（tw=台湾版、th=タイ版）

---

## MySQL: ocgraph_ocreview（メインデータ）

**用途:** OpenChatの基本情報・ランキング・タグを管理

**使用箇所:** [`app/Models/Repositories/`](app/Models/Repositories/)
- [`OpenChatRepository.php`](app/Models/Repositories/OpenChatRepository.php)
- [`OpenChatPageRepository.php`](app/Models/Repositories/OpenChatPageRepository.php)
- [`OpenChatListRepository.php`](app/Models/Repositories/OpenChatListRepository.php)
- [`UpdateOpenChatRepository.php`](app/Models/Repositories/UpdateOpenChatRepository.php)
- [`DeleteOpenChatRepository.php`](app/Models/Repositories/DeleteOpenChatRepository.php)
- [`SyncOpenChatStateRepository.php`](app/Models/Repositories/SyncOpenChatStateRepository.php)
- [`MemberChangeFilterCacheRepository.php`](app/Models/Repositories/MemberChangeFilterCacheRepository.php)

### open_chat - OpenChat基本情報

```sql
CREATE TABLE `open_chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emid` varchar(255) NOT NULL,
  `name` text NOT NULL,
  `description` text NOT NULL,
  `member` int(11) NOT NULL,
  `category` int(11) DEFAULT NULL,
  `img_url` varchar(128) NOT NULL,
  `local_img_url` varchar(128) DEFAULT '',
  `emblem` int(11) DEFAULT NULL,
  `join_method_type` int(11) NOT NULL DEFAULT 0,
  `url` text DEFAULT NULL,
  `api_created_at` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY (`emid`)
);
```

### statistics_ranking_* - 成長ランキング

hour / hour24 / day / week の4テーブル

**使用箇所:** [`app/Services/UpdateHourlyMemberRankingService.php`](app/Services/UpdateHourlyMemberRankingService.php), [`app/Models/ApiRepositories/OpenChatStatsRankingApiRepository.php`](app/Models/ApiRepositories/OpenChatStatsRankingApiRepository.php)

```sql
CREATE TABLE `statistics_ranking_hour` (
  `id` int(11) NOT NULL,              -- ランキング順位（1位=1）
  `open_chat_id` int(11) NOT NULL,
  `diff_member` int(11) NOT NULL,
  `percent_increase` float NOT NULL,
  PRIMARY KEY (`id`)
);
```

**重要:** `id`=ランキング順位、`created_at`なし（時刻は`open_chat.updated_at`参照）

### recommend, oc_tag, oc_tag2 - タグシステム

**使用箇所:** [`app/Models/RecommendRepositories/`](app/Models/RecommendRepositories/)
- [`RecommendRankingRepository.php`](app/Models/RecommendRepositories/RecommendRankingRepository.php)
- [`CategoryRankingRepository.php`](app/Models/RecommendRepositories/CategoryRankingRepository.php)
- [`app/Services/Recommend/RecommendUpdater.php`](app/Services/Recommend/RecommendUpdater.php)

```sql
CREATE TABLE `recommend` (
  `id` int(11) NOT NULL,              -- open_chat.idと1対1
  `tag` text NOT NULL,
  PRIMARY KEY (`id`)
);
```

### ranking_ban - ランキング除外

**使用箇所:** [`app/Models/RankingBanRepositories/`](app/Models/RankingBanRepositories/)
- [`RankingBanPageRepository.php`](app/Models/RankingBanRepositories/RankingBanPageRepository.php)
- [`app/Services/RankingBan/RankingBanTableUpdater.php`](app/Services/RankingBan/RankingBanTableUpdater.php)

```sql
CREATE TABLE `ranking_ban` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `open_chat_id` int(11) NOT NULL,
  `datetime` datetime NOT NULL,
  `percentage` int(11) NOT NULL,
  `member` int(11) NOT NULL,
  `flag` int(11) NOT NULL DEFAULT 0,
  `end_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**使用ファイル:**
- [`RankingBanTableUpdater`](/app/Services/RankingBan/RankingBanTableUpdater.php)
- [`RankingBanPageRepository`](/app/Models/RankingBanRepositories/RankingBanPageRepository.php)
- [`RankingBanLabsPageController`](/app/Controllers/Pages/RankingBanLabsPageController.php)

#### open_chat_deleted（削除OpenChat履歴）

```sql
CREATE TABLE `open_chat_deleted` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emid` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
);
```

### その他

- `modify_recommend` - 推薦タグ変更履歴
- `reject_room` - 拒否ルーム
- `recovery` - リカバリーデータ
- `api_data_download_state` - APIダウンロード状態
- `sync_open_chat_state` - 同期状態
- `ads`, `ads_tag_map` - 広告（未使用）
- `user_log` - ユーザーログ（後方互換）

---

## MySQL: ocgraph_ranking（ランキング位置履歴）

**用途:** メンバー数・ランキング位置の時系列データを保存

**使用箇所:** [`app/Models/RankingPositionDB/Repositories/`](app/Models/RankingPositionDB/Repositories/)
- [`RankingPositionHourRepository.php`](app/Models/RankingPositionDB/Repositories/RankingPositionHourRepository.php)

### member - メンバー数履歴

```sql
CREATE TABLE `member` (
  `open_chat_id` int(11) NOT NULL,
  `member` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY (`open_chat_id`,`time`)
);
```

### ranking, rising - ランキング位置

```sql
CREATE TABLE `ranking` (
  `open_chat_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `category` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY (`open_chat_id`,`category`,`time`)
);
```

### total_count - 総数情報

```sql
CREATE TABLE `total_count` (
  `total_count_rising` int(11) NOT NULL,
  `total_count_ranking` int(11) NOT NULL,
  `time` datetime NOT NULL,
  `category` int(11) NOT NULL,
  UNIQUE KEY (`time`,`category`)
);
```

---

## MySQL: ocgraph_comment（コメント）

**用途:** ユーザーコメント・いいね・BAN管理

**使用箇所:** [`app/Models/CommentRepositories/`](app/Models/CommentRepositories/)
- [`CommentListRepository.php`](app/Models/CommentRepositories/CommentListRepository.php)
- [`CommentPostRepository.php`](app/Models/CommentRepositories/CommentPostRepository.php)
- [`CommentLogRepository.php`](app/Models/CommentRepositories/CommentLogRepository.php)
- [`LikePostRepository.php`](app/Models/CommentRepositories/LikePostRepository.php)
- [`DeleteCommentRepository.php`](app/Models/CommentRepositories/DeleteCommentRepository.php)
- [`RecentCommentListRepository.php`](app/Models/CommentRepositories/RecentCommentListRepository.php)

```sql
CREATE TABLE `comment` (
  `comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `open_chat_id` int(11) NOT NULL,
  `user_id` varchar(64) NOT NULL,
  `name` text NOT NULL,
  `text` text NOT NULL,
  `time` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`comment_id`)
);

CREATE TABLE `like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL,
  `user_id` varchar(64) NOT NULL,
  `type` varchar(8) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`comment_id`,`user_id`)
);

CREATE TABLE `ban_room` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `open_chat_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
);

CREATE TABLE `ban_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `ip` varchar(128) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
);

CREATE TABLE `log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_id` int(11) NOT NULL,
  `type` text NOT NULL,
  `data` text NOT NULL,
  `ip` text NOT NULL,
  `ua` text NOT NULL,
  PRIMARY KEY (`id`)
);
```

---

## MySQL: ocgraph_userlog（ユーザーログ - 全言語共通）

**用途:** ユーザーのマイリスト管理

**使用箇所:** [`app/Models/UserLogRepositories/`](app/Models/UserLogRepositories/)
- [`UserLogRepository.php`](app/Models/UserLogRepositories/UserLogRepository.php)

```sql
CREATE TABLE `oc_list_user` (
  `user_id` varchar(64) NOT NULL,
  `oc_list` text NOT NULL,
  `list_count` int(11) NOT NULL,
  `expires` int(11) NOT NULL,
  `ip` text NOT NULL,
  `ua` text NOT NULL,
  PRIMARY KEY (`user_id`)
);

CREATE TABLE `oc_list_user_list_show_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `time` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `oc_list_user` (`user_id`) ON DELETE CASCADE
);
```

---

## SQLite（読み取り専用・パフォーマンス最適化）

### statistics.db - 日別メンバー数履歴

**用途:** 日別のメンバー数推移を記録（グラフ表示用）

**パス:** `/storage/{lang}/SQLite/statistics/statistics.db`

**使用箇所:** [`app/Models/SQLite/Repositories/Statistics/`](app/Models/SQLite/Repositories/Statistics/)
- [`SqliteStatisticsRepository.php`](app/Models/SQLite/Repositories/Statistics/SqliteStatisticsRepository.php)
- [`SqliteStatisticsPageRepository.php`](app/Models/SQLite/Repositories/Statistics/SqliteStatisticsPageRepository.php)
- [`SqliteStatisticsRankingUpdaterRepository.php`](app/Models/SQLite/Repositories/Statistics/SqliteStatisticsRankingUpdaterRepository.php)

```sql
CREATE TABLE "statistics" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  open_chat_id INTEGER NOT NULL,
  "member" INTEGER NOT NULL,
  date TEXT NOT NULL
);
CREATE UNIQUE INDEX statistics2_open_chat_id_IDX ON "statistics" (open_chat_id,date);
```

### ranking_position.db - ランキング位置履歴

**用途:** ランキング・急上昇の位置履歴を保存（読み取り専用最適化）

**パス:** `/storage/{lang}/SQLite/ranking_position/ranking_position.db`

**使用箇所:** [`app/Models/SQLite/Repositories/RankingPosition/`](app/Models/SQLite/Repositories/RankingPosition/)
- [`SqliteRankingPositionRepository.php`](app/Models/SQLite/Repositories/RankingPosition/SqliteRankingPositionRepository.php)
- [`SqliteRankingPositionPageRepository.php`](app/Models/SQLite/Repositories/RankingPosition/SqliteRankingPositionPageRepository.php)

```sql
CREATE TABLE rising (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  open_chat_id INTEGER NOT NULL,
  category INTEGER NOT NULL,
  "position" INTEGER NOT NULL,
  time TEXT NOT NULL,
  date INTEGER NOT NULL
);

CREATE TABLE ranking (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  open_chat_id INTEGER NOT NULL,
  category INTEGER NOT NULL,
  "position" INTEGER NOT NULL,
  time TEXT NOT NULL,
  date INTEGER NOT NULL
);

CREATE TABLE total_count (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  total_count_rising INTEGER NOT NULL,
  total_count_ranking INTEGER NOT NULL,
  time TEXT NOT NULL,
  category INTEGER NOT NULL
);

CREATE UNIQUE INDEX rising_open_chat_id_IDX ON rising (open_chat_id,category,date);
CREATE UNIQUE INDEX ranking_open_chat_id_IDX2 ON ranking (open_chat_id,category,date);
CREATE UNIQUE INDEX total_count_time_IDX ON total_count (time,category);
```

### sqlapi.db - 外部API用統合データ（日本語のみ）

**用途:** 外部API提供用に最適化された統合データベース（日本語版のみ、約6000万レコード）

**パス:** `/storage/ja/SQLite/ocgraph_sqlapi/sqlapi.db`

**使用箇所:**
- [`app/Services/Cron/OcreviewApiDataImporter.php`](app/Services/Cron/OcreviewApiDataImporter.php)
- [`app/Models/Repositories/Api/ApiOpenChatPageRepository.php`](app/Models/Repositories/Api/ApiOpenChatPageRepository.php)
- [`app/Models/Repositories/Api/ApiStatisticsPageRepository.php`](app/Models/Repositories/Api/ApiStatisticsPageRepository.php)
- [`app/Models/CommentRepositories/Api/ApiCommentListRepository.php`](app/Models/CommentRepositories/Api/ApiCommentListRepository.php)

主要テーブル:
- `openchat_master` - OpenChatマスター
- `daily_member_statistics` - 日別メンバー統計（約6000万レコード）
- `growth_ranking_past_hour/24_hours/week` - 成長ランキング
- `line_official_activity_ranking_history` - LINE公式ランキング履歴
- `line_official_activity_trending_history` - LINE公式急上昇履歴
- `line_official_ranking_total_count` - LINE公式総件数
- `comment`, `comment_like` - コメント関連
- `ban_room`, `ban_user` - BAN管理
- `open_chat_deleted` - 削除履歴
- `categories` - カテゴリマスター

詳細: [`setup/schema/sqlite/sqlapi.sql`](setup/schema/sqlite/sqlapi.sql)

---

## カテゴリID（日本語版）

```
ゲーム:17, スポーツ:16, 芸能人:26, 同世代:7, アニメ・漫画:22,
金融・ビジネス:40, 学校:5, ファッション・美容:37, 恋愛:33,
音楽:28, 学問:6, 旅行:29, 映画:30, 地域:8, 趣味:19,
グルメ:20, 相談・雑談:2, 健康:41, テレビ:27, 職業:24,
写真:23, ニュース:11, 乗り物:18, その他:12
```
