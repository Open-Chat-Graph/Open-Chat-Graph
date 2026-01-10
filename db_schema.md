# データベーススキーマ

オプチャグラフ（OpenChat Graph）のデータベース構成とスキーマの詳細。

## 多言語対応のデータベース切り替え

URLのパス（`''`, `'/tw'`, `'/th'`）に応じて、異なるデータベースに自動接続します。

**実装:**
- [`MimimalCMS_Settings.php`](https://github.com/pika-0203/Open-Chat-Graph/blob/main/shared/MimimalCMS_Settings.php#L40-L46) - リクエストURI（`$_SERVER['REQUEST_URI']`）に基づいて`MimimalCmsConfig::$urlRoot`を動的に設定
  - `/th`で始まる場合: `/th`
  - `/tw`で始まる場合: `/tw`
  - それ以外（日本語）: `''`（空文字列）
- [`AppConfig::$dbName`](https://github.com/pika-0203/Open-Chat-Graph/blob/main/app/Config/AppConfig.php#L238-L241) - 言語別データベース名のマッピング定義
- [`DB::connect()`](https://github.com/pika-0203/Open-Chat-Graph/blob/main/app/Models/Repositories/DB.php#L18) - `MimimalCmsConfig::$urlRoot`をキーにして`AppConfig::$dbName`から対応するDB名を取得

**データベース名:**

| 用途 | 日本語（''） | 台湾版（'/tw'） | タイ版（'/th'） |
|------|-------------|----------------|----------------|
| メインデータ | `ocgraph_ocreview` | `ocgraph_ocreviewtw` | `ocgraph_ocreviewth` |
| ランキングポジション | `ocgraph_ranking` | `ocgraph_rankingtw` | `ocgraph_rankingth` |
| ユーザーログ | `ocgraph_userlog` | `ocgraph_userlog` | `ocgraph_userlog` |
| コメント | `ocgraph_comment` | `ocgraph_commenttw` | `ocgraph_commentth` |

## 1. MySQL メインデータベース

### 1.1 open_chat（OpenChatメインデータ）

LINE OpenChatの基本情報を格納するメインテーブル。

```sql
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**主要カラム:**
- `id`: プライマリキー、全ての関連テーブルで参照される
- `emid`: LINE内部管理ID（ユニーク制約）
- `member`: 現在のメンバー数（リアルタイム更新）
- `category`: カテゴリID
- `emblem`: 公式バッジ（0=なし、1=スペシャル、2=公式認証）

**使用ファイル:**
- [`OpenChatRepository`](/app/Models/Repositories/OpenChatRepository.php)
- [`OpenChatPageRepository`](/app/Models/Repositories/OpenChatPageRepository.php)
- [`OpenChatListRepository`](/app/Models/Repositories/OpenChatListRepository.php)
- [`UpdateOpenChatRepository`](/app/Models/Repositories/UpdateOpenChatRepository.php)
- [`DailyUpdateCronService`](/app/Services/DailyUpdateCronService.php)

### 1.2 統計・ランキングテーブル

#### ⚠️ 重要な制約
- **`created_at`カラムは存在しない** - 時刻情報は`open_chat.updated_at`を使用
- **`id`カラムはランキング順位** - id=1が1位
- **毎時間完全再構築** - データは都度削除・再挿入される

#### statistics_ranking_hour（1時間成長ランキング）

```sql
CREATE TABLE `statistics_ranking_hour` (
  `id` int(11) NOT NULL,
  `open_chat_id` int(11) NOT NULL,
  `diff_member` int(11) NOT NULL,
  `percent_increase` float NOT NULL,
  PRIMARY KEY (`id`),
  KEY `open_chat_id` (`open_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**使用ファイル:**
- [`SqliteStatisticsRankingUpdaterRepository`](/app/Models/SQLite/Repositories/Statistics/SqliteStatisticsRankingUpdaterRepository.php)
- [`UpdateHourlyMemberRankingService`](/app/Services/UpdateHourlyMemberRankingService.php)
- [`OpenChatStatsRankingApiRepository`](/app/Models/ApiRepositories/OpenChatStatsRankingApiRepository.php)

#### statistics_ranking_hour24（24時間成長ランキング）

```sql
CREATE TABLE `statistics_ranking_hour24` (
  `id` int(11) NOT NULL,
  `open_chat_id` int(11) NOT NULL,
  `diff_member` int(11) NOT NULL,
  `percent_increase` float NOT NULL,
  PRIMARY KEY (`id`),
  KEY `open_chat_id` (`open_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**使用ファイル:**
- [`SqliteStatisticsRankingUpdaterRepository`](/app/Models/SQLite/Repositories/Statistics/SqliteStatisticsRankingUpdaterRepository.php)
- [`UpdateHourlyMemberRankingService`](/app/Services/UpdateHourlyMemberRankingService.php)

#### statistics_ranking_day（日別成長ランキング）

```sql
CREATE TABLE `statistics_ranking_day` (
  `id` int(11) NOT NULL,
  `open_chat_id` int(11) NOT NULL,
  `diff_member` int(11) NOT NULL,
  `percent_increase` float NOT NULL,
  PRIMARY KEY (`id`),
  KEY `open_chat_id` (`open_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**使用ファイル:**
- [`SqliteStatisticsRankingUpdaterRepository`](/app/Models/SQLite/Repositories/Statistics/SqliteStatisticsRankingUpdaterRepository.php)
- [`UpdateHourlyMemberRankingService`](/app/Services/UpdateHourlyMemberRankingService.php)

#### statistics_ranking_week（週間成長ランキング）

```sql
CREATE TABLE `statistics_ranking_week` (
  `id` int(11) NOT NULL,
  `open_chat_id` int(11) NOT NULL,
  `diff_member` int(11) NOT NULL,
  `percent_increase` float NOT NULL,
  PRIMARY KEY (`id`),
  KEY `open_chat_id` (`open_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**使用ファイル:**
- [`SqliteStatisticsRankingUpdaterRepository`](/app/Models/SQLite/Repositories/Statistics/SqliteStatisticsRankingUpdaterRepository.php)
- [`UpdateHourlyMemberRankingService`](/app/Services/UpdateHourlyMemberRankingService.php)

### 1.3 推薦・タグシステム

#### recommend（推薦タグ）

OpenChatに関連付けられた推薦タグの管理。`recommend.id = open_chat.id`（1対1関係）

```sql
CREATE TABLE `recommend` (
  `id` int(11) NOT NULL,
  `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`(768))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**使用ファイル:**
- [`RecommendRankingRepository`](/app/Models/RecommendRepositories/RecommendRankingRepository.php)
- [`CategoryRankingRepository`](/app/Models/RecommendRepositories/CategoryRankingRepository.php)
- [`RecommendUpdater`](/app/Services/Recommend/RecommendUpdater.php)

#### modify_recommend（推薦データ変更履歴）

```sql
CREATE TABLE `modify_recommend` (
  `id` int(11) NOT NULL,
  `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

**使用ファイル:**
- [`RecommendUpdater`](/app/Services/Recommend/RecommendUpdater.php)

#### oc_tag、oc_tag2（OpenChatタグ）

```sql
CREATE TABLE `oc_tag` (
  `id` int(11) NOT NULL,
  `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`(768))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**使用ファイル:**
- [`OpenChatPageRepository`](/app/Models/Repositories/OpenChatPageRepository.php)

### 1.4 管理・制御テーブル

#### ranking_ban（ランキングBANリスト）

不正な成長をしているチャットをランキングから除外。

```sql
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
  `emid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `deleted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**使用ファイル:**
- [`DeleteOpenChatRepository`](/app/Models/Repositories/DeleteOpenChatRepository.php)
- [`DailyUpdateCronService`](/app/Services/DailyUpdateCronService.php)

## 2. MySQL 専用データベース（ocgraph_ranking）

### member（メンバー履歴）

OpenChatのメンバー数の時系列データ。

```sql
CREATE TABLE `member` (
  `open_chat_id` int(11) NOT NULL,
  `member` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY `open_chat_id` (`open_chat_id`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

**使用ファイル:**
- [`RankingPositionHourRepository`](/app/Models/RankingPositionDB/Repositories/RankingPositionHourRepository.php)

### ranking（ランキング位置履歴）

```sql
CREATE TABLE `ranking` (
  `open_chat_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `category` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY `open_chat_id` (`open_chat_id`,`category`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

**使用ファイル:**
- [`RankingPositionHourRepository`](/app/Models/RankingPositionDB/Repositories/RankingPositionHourRepository.php)

### rising（急上昇ランキング履歴）

```sql
CREATE TABLE `rising` (
  `open_chat_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `category` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY `open_chat_id` (`open_chat_id`,`category`,`time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

**使用ファイル:**
- [`RankingPositionHourRepository`](/app/Models/RankingPositionDB/Repositories/RankingPositionHourRepository.php)

### total_count（総数情報）

```sql
CREATE TABLE `total_count` (
  `total_count_rising` int(11) NOT NULL,
  `total_count_ranking` int(11) NOT NULL,
  `time` datetime NOT NULL,
  `category` int(11) NOT NULL,
  UNIQUE KEY `time` (`time`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

**使用ファイル:**
- [`RankingPositionHourRepository`](/app/Models/RankingPositionDB/Repositories/RankingPositionHourRepository.php)

## 3. MySQL ユーザーログデータベース（ocgraph_userlog）

### oc_list_user（ユーザーリスト）

```sql
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
```

**使用ファイル:**
- [`UserLogRepository`](/app/Models/UserLogRepositories/UserLogRepository.php)

### oc_list_user_list_show_log（ユーザーリスト表示ログ）

⚠️ このテーブルのみ明示的な外部キー制約が設定されています。

```sql
CREATE TABLE `oc_list_user_list_show_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_id` FOREIGN KEY (`user_id`) REFERENCES `oc_list_user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

**使用ファイル:**
- [`UserLogRepository`](/app/Models/UserLogRepositories/UserLogRepository.php)

## 4. SQLiteデータベース

パフォーマンス最適化のため、読み取り専用のデータや集計データはSQLiteで管理しています。

**データベース種類:**

| データベース | パス | 多言語対応 | 用途 |
|------------|------|----------|-----|
| `sqlapi.db` | `/storage/ja/SQLite/ocgraph_sqlapi/` | ❌（日本語のみ） | 外部API用統合データ |
| `statistics.db` | `/storage/{lang}/SQLite/statistics/` | ✅ | メンバー数の日別履歴 |
| `ranking_position.db` | `/storage/{lang}/SQLite/ranking_position/` | ✅ | ランキング位置履歴 |

**パフォーマンス最適化設定:**
- WALモード（Write-Ahead Logging）- 並行読み書き性能向上
- NORMAL同期モード - パフォーマンスと耐久性のバランス
- busy timeout設定 - 並行アクセス時の待機時間

### sqlapi.db（外部API用統合データベース）

`/storage/ja/SQLite/ocgraph_sqlapi/sqlapi.db`

外部API用の統合データベース（**日本語版のみ**、多言語対応なし）。

**特徴:**
- 外部アクセス用のAPIデータを一元管理
- WALモード、NORMAL同期モード、10秒busy timeout等のパフォーマンス最適化
- MySQL、SQLiteの複数データソースからデータをインポート

**主要テーブル:**

#### openchat_master（OpenChatマスターデータ）
```sql
CREATE TABLE IF NOT EXISTS "openchat_master" (
  "openchat_id" INTEGER NOT NULL,
  "display_name" TEXT NOT NULL,
  "profile_image_url" VARCHAR(128) NOT NULL,
  "description" TEXT NOT NULL,
  "current_member_count" INTEGER NOT NULL,
  "first_seen_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "last_updated_at" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "line_internal_id" VARCHAR(255) NULL,
  "established_at" DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  "invitation_url" TEXT NULL,
  "verification_badge" VARCHAR(20) NULL,
  "join_method" VARCHAR(30) NOT NULL,
  "category_id" INTEGER NULL,
  PRIMARY KEY ("openchat_id"),
  FOREIGN KEY("category_id") REFERENCES "categories" ("category_id")
);
```

#### daily_member_statistics（日別メンバー統計）
```sql
CREATE TABLE IF NOT EXISTS "daily_member_statistics" (
  "record_id" INTEGER NOT NULL,
  "openchat_id" INTEGER NOT NULL,
  "member_count" INTEGER NOT NULL,
  "statistics_date" DATE NOT NULL,
  PRIMARY KEY ("record_id")
);
```

#### growth_ranking_past_hour, growth_ranking_past_24_hours, growth_ranking_past_week（成長ランキング）
```sql
CREATE TABLE IF NOT EXISTS "growth_ranking_past_hour" (
  "ranking_position" INTEGER NOT NULL,
  "openchat_id" INTEGER NOT NULL,
  "member_increase_count" INTEGER NOT NULL,
  "growth_rate_percent" FLOAT NOT NULL,
  PRIMARY KEY ("ranking_position")
);
```

#### comment, comment_like（コメント関連）
```sql
CREATE TABLE comment (
  comment_id INTEGER PRIMARY KEY,
  open_chat_id INTEGER NOT NULL,
  id INTEGER NOT NULL,
  user_id TEXT NOT NULL,
  name TEXT NOT NULL,
  text TEXT NOT NULL,
  time TEXT NOT NULL,
  flag INTEGER NOT NULL DEFAULT 0
);
```

**使用ファイル:**
- [`SQLiteOcgraphSqlapi`](/app/Models/SQLite/SQLiteOcgraphSqlapi.php) - データベース接続クラス
- [`OcreviewApiDataImporter`](/app/Services/Cron/OcreviewApiDataImporter.php) - データインポートサービス
- [`ApiOpenChatPageRepository`](/app/Models/Repositories/Api/ApiOpenChatPageRepository.php)
- [`ApiStatisticsPageRepository`](/app/Models/Repositories/Api/ApiStatisticsPageRepository.php)
- [`ApiCommentListRepository`](/app/Models/CommentRepositories/Api/ApiCommentListRepository.php)

### statistics（統計履歴）

`/storage/{lang}/SQLite/statistics/statistics.db`

メンバー数の日別履歴データ（読み取り専用最適化、**多言語対応**）。

```sql
CREATE TABLE IF NOT EXISTS "statistics" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  open_chat_id INTEGER NOT NULL,
  "member" INTEGER NOT NULL,
  date TEXT NOT NULL
);
CREATE UNIQUE INDEX statistics2_open_chat_id_IDX ON "statistics" (open_chat_id,date);
```

**使用ファイル:**
- [`SqliteStatisticsRepository`](/app/Models/SQLite/Repositories/Statistics/SqliteStatisticsRepository.php)
- [`SqliteStatisticsPageRepository`](/app/Models/SQLite/Repositories/Statistics/SqliteStatisticsPageRepository.php)

### rising（急上昇ランキング位置）

`/storage/{lang}/SQLite/ranking_position/ranking_position.db`（**多言語対応**）

```sql
CREATE TABLE rising (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  open_chat_id INTEGER NOT NULL,
  category INTEGER NOT NULL,
  "position" INTEGER NOT NULL,
  time TEXT NOT NULL,
  date INTEGER DEFAULT ('2024-01-01') NOT NULL
);
CREATE UNIQUE INDEX rising_open_chat_id_IDX ON rising (open_chat_id,category,date);
```

**使用ファイル:**
- [`SqliteRankingPositionRepository`](/app/Models/SQLite/Repositories/RankingPosition/SqliteRankingPositionRepository.php)
- [`SqliteRankingPositionPageRepository`](/app/Models/SQLite/Repositories/RankingPosition/SqliteRankingPositionPageRepository.php)

### ranking（通常ランキング位置）

`/storage/{lang}/SQLite/ranking_position/ranking_position.db`（**多言語対応**）

```sql
CREATE TABLE ranking (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  open_chat_id INTEGER NOT NULL,
  category INTEGER NOT NULL,
  "position" INTEGER NOT NULL,
  time TEXT NOT NULL,
  date INTEGER DEFAULT ('2024-01-01') NOT NULL
);
CREATE UNIQUE INDEX ranking_open_chat_id_IDX2 ON ranking (open_chat_id,category,date);
```

**使用ファイル:**
- [`SqliteRankingPositionRepository`](/app/Models/SQLite/Repositories/RankingPosition/SqliteRankingPositionRepository.php)
- [`SqliteRankingPositionPageRepository`](/app/Models/SQLite/Repositories/RankingPosition/SqliteRankingPositionPageRepository.php)

### total_count（総数情報）

`/storage/{lang}/SQLite/ranking_position/ranking_position.db`（**多言語対応**）

```sql
CREATE TABLE total_count (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  total_count_rising INTEGER NOT NULL,
  total_count_ranking INTEGER NOT NULL,
  time TEXT NOT NULL,
  category INTEGER NOT NULL
);
CREATE UNIQUE INDEX total_count_time_IDX ON total_count (time,category);
```

**使用ファイル:**
- [`SqliteRankingPositionRepository`](/app/Models/SQLite/Repositories/RankingPosition/SqliteRankingPositionRepository.php)

## 5. カテゴリマッピング

### 日本語版カテゴリID

```php
const OPEN_CHAT_CATEGORY = [
    'ゲーム' => 17,
    'スポーツ' => 16,
    '芸能人・有名人' => 26,
    '同世代' => 7,
    'アニメ・漫画' => 22,
    '金融・ビジネス' => 40,
    '学校・同窓会' => 5,
    'ファッション・美容' => 37,
    '恋愛・出会い' => 33,
    '音楽' => 28,
    '学問・勉強' => 6,
    '旅行' => 29,
    '映画・ドラマ' => 30,
    '地域' => 8,
    '趣味' => 19,
    'グルメ' => 20,
    '相談・雑談' => 2,
    '健康・ダイエット・メンタル' => 41,
    'テレビ番組' => 27,
    '職業・職場' => 24,
    '写真・画像' => 23,
    'ニュース・最新情報' => 11,
    '乗り物' => 18,
    'その他' => 12
];
```

## 6. パフォーマンス最適化

### データベース使い分け戦略

- **MySQL**: リアルタイム更新が必要なデータ（`open_chat`、`statistics_ranking_*`）
- **SQLite**: 読み取り専用の集計データ（履歴データ、ランキング位置履歴）

### 重要な制約

1. **statistics_ranking_* テーブルは時刻情報なし** - 時刻は`open_chat.updated_at`を参照
2. **idカラムはランキング順位** - ORDER BY idでランキング順
3. **open_chat_idが全関係の中心** - 全てのJOINの基準となるキー

### データ更新処理

実装詳細は以下を参照:
- [`SyncOpenChat`](/app/Services/Cron/SyncOpenChat.php) - 全体調整とスケジューリング
- [`OpenChatApiDbMerger`](/app/Services/OpenChat/OpenChatApiDbMerger.php) - データ取得とDB更新
- [`UpdateHourlyMemberRankingService`](/app/Services/UpdateHourlyMemberRankingService.php) - ランキング更新

## 7. 注意事項

### データ整合性

- `open_chat.emid`: LINE内部ID（ユニーク）
- `open_chat.url`: 招待リンク（ユニーク、NULL可能）
- 削除されたチャットは`open_chat_deleted`に記録

### 文字エンコーディング

- **utf8mb4_unicode_520_ci**: 絵文字対応、名前・説明文用
- **utf8mb4_bin**: バイナリ照合、URL・ID用

### インデックス戦略

- 検索頻度の高いカラム（`member`, `updated_at`, `emid`）
- 複合インデックス（SQLiteで多用）
- JOINパフォーマンス最適化

## 8. 現在未使用のテーブル

### ads（広告データ）

```sql
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### ads_tag_map（広告とタグのマッピング）

```sql
CREATE TABLE `ads_tag_map` (
  `tag` varchar(255) NOT NULL,
  `ads_id` int(11) NOT NULL,
  UNIQUE KEY `tag` (`tag`),
  KEY `ads_tag` (`ads_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
