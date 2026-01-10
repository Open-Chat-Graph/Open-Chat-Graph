# オプチャグラフ データベーススキーマ詳細ドキュメント

## 概要

このドキュメントは、オプチャグラフ（OpenChat Graph）プロジェクトで使用している全データベースのスキーマと用途を詳細に解説します。LLMがデータ分析SQLを精度高く組み立てるため、および開発者がデータ構造を理解するための包括的なリファレンスです。

## データベース構成

### 多言語対応アーキテクチャ

プロジェクトは多言語対応のため、URL Root（`''`, `'/tw'`, `'/th'`）に基づいて異なるデータベースに自動接続されます。

**データベース接続設定:**
- **ホスト**: `mysql` (Docker環境)
- **認証情報**: `root` / `test_root_pass`

**データベース名一覧:**

| 用途 | 日本語（''） | 台湾版（'/tw'） | タイ版（'/th'） |
|------|-------------|----------------|----------------|
| メインデータ | `ocgraph_ocreview` | `ocgraph_ocreviewtw` | `ocgraph_ocreviewth` |
| ランキングポジション | `ocgraph_ranking` | `ocgraph_rankingtw` | `ocgraph_rankingth` |
| ユーザーログ | `ocgraph_userlog` | `ocgraph_userlog` | `ocgraph_userlog` |
| コメント | `ocgraph_comment` | `ocgraph_commenttw` | `ocgraph_commentth` |

## 1. MySQL メインデータベース

### 1.1 core テーブル群

#### open_chat（OpenChatメインデータ）

**用途**: LINE OpenChatの基本情報を格納するメインテーブル

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

**主要カラム解説:**
- `id`: プライマリキー、全ての関連テーブルで参照される中心的キー
- `name`: チャット名（絵文字対応）
- `img_url`: プロフィール画像のハッシュ値（128文字固定）
- `local_img_url`: ローカルサーバー保存画像URL
- `description`: チャット説明文（絵文字対応）
- `member`: **現在のメンバー数**（リアルタイム更新）
- `emid`: LINE内部管理ID（**ユニーク制約**）
- `category`: カテゴリID（後述のカテゴリマッピング参照）
- `api_created_at`: LINE API上の作成日時（UNIX timestamp）
- `emblem`: 公式バッジ（0=なし、1=スペシャル、2=公式認証）
- `join_method_type`: 参加方法タイプ
- `url`: 招待リンク（ユニーク）

### 1.2 統計・ランキングテーブル群

#### ⚠️ 重要な制約事項
**statistics_ranking_* テーブルには `created_at` カラムが存在しません**
- 時刻情報は `open_chat.updated_at` を使用
- `id` カラムは**ランキング順位**を表す（id=1が1位）
- データは毎時間完全再構築される

#### statistics_ranking_hour（1時間成長ランキング）

**用途**: 直近1時間のメンバー増加数ランキング

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

**カラム解説:**
- `id`: **ランキング順位**（1位、2位、...）
- `open_chat_id`: open_chat.idへの参照
- `diff_member`: 1時間でのメンバー増加数
- `percent_increase`: メンバー増加率（%）

#### statistics_ranking_hour24（24時間成長ランキング）

**用途**: 直近24時間のメンバー増加数ランキング

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

#### statistics_ranking_day（日別成長ランキング）

**用途**: 日別のメンバー増加数ランキング

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

#### statistics_ranking_week（週間成長ランキング）

**用途**: 週間のメンバー増加数ランキング

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

### 1.3 推薦・タグシステム

#### recommend（推薦タグ）

**用途**: OpenChatに関連付けられた推薦タグの管理

```sql
CREATE TABLE `recommend` (
  `id` int(11) NOT NULL,
  `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`(768))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**関係性**: `recommend.id = open_chat.id`（1対1関係）

#### modify_recommend（推薦データ変更履歴）

```sql
CREATE TABLE `modify_recommend` (
  `id` int(11) NOT NULL,
  `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `time` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

#### oc_tag、oc_tag2（OpenChatタグ）

**用途**: OpenChatに関連付けられたタグ情報

```sql
CREATE TABLE `oc_tag` (
  `id` int(11) NOT NULL,
  `tag` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`(768))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.4 管理・制御テーブル

#### ranking_ban（ランキングBANリスト）

**用途**: 不正な成長をしているチャットをランキングから除外

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

#### open_chat_deleted（削除OpenChat履歴）

**用途**: 削除されたOpenChatの記録を保持

```sql
CREATE TABLE `open_chat_deleted` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `deleted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.5 システム管理テーブル

#### api_data_download_state（API データダウンロード状態）

**用途**: カテゴリ別のAPI データダウンロード進行状況を管理

```sql
CREATE TABLE `api_data_download_state` (
  `category` int(11) NOT NULL,
  `ranking` int(11) NOT NULL,
  `rising` int(11) NOT NULL,
  PRIMARY KEY (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### 1.6 広告関連テーブル

#### ads（広告データ）

**用途**: 表示する広告の管理

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

#### ads_tag_map（広告とタグのマッピング）

**用途**: 特定のタグに関連する広告の表示制御

```sql
CREATE TABLE `ads_tag_map` (
  `tag` varchar(255) NOT NULL,
  `ads_id` int(11) NOT NULL,
  UNIQUE KEY `tag` (`tag`),
  KEY `ads_tag` (`ads_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 2. MySQL 専用データベース

### 2.1 ランキングデータベース（ocgraph_ranking）

#### member（メンバー履歴）

**用途**: OpenChatのメンバー数の時系列データ

```sql
CREATE TABLE `member` (
  `open_chat_id` int(11) NOT NULL,
  `member` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY `open_chat_id` (`open_chat_id`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

#### ranking（ランキング位置履歴）

**用途**: カテゴリ別のランキング位置の履歴

```sql
CREATE TABLE `ranking` (
  `open_chat_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `category` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY `open_chat_id` (`open_chat_id`,`category`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

#### rising（急上昇ランキング履歴）

**用途**: 急上昇ランキングの位置履歴

```sql
CREATE TABLE `rising` (
  `open_chat_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `category` int(11) NOT NULL,
  `time` datetime NOT NULL,
  UNIQUE KEY `open_chat_id` (`open_chat_id`,`category`,`time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

#### total_count（総数情報）

**用途**: カテゴリ別の総チャット数の履歴

```sql
CREATE TABLE `total_count` (
  `total_count_rising` int(11) NOT NULL,
  `total_count_ranking` int(11) NOT NULL,
  `time` datetime NOT NULL,
  `category` int(11) NOT NULL,
  UNIQUE KEY `time` (`time`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### 2.2 ユーザーログデータベース（ocgraph_userlog）

#### oc_list_user（ユーザーリスト）

**用途**: ユーザーが作成したOpenChatリストの管理

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

#### oc_list_user_list_show_log（ユーザーリスト表示ログ）

**用途**: ユーザーリストの表示回数のログ

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

**⚠️ 注意**: このテーブルのみ明示的な外部キー制約が設定されています。

## 3. SQLiteデータベース  

### 3.1 統計データ（/storage/{lang}/SQLite/statistics/statistics.db）

#### statistics（統計履歴）

**用途**: メンバー数の日別履歴データ（読み取り専用最適化）

```sql
CREATE TABLE IF NOT EXISTS "statistics" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  open_chat_id INTEGER NOT NULL,
  "member" INTEGER NOT NULL,
  date TEXT NOT NULL
);
CREATE UNIQUE INDEX statistics2_open_chat_id_IDX ON "statistics" (open_chat_id,date);
```

**重要**: MySQLより高速な読み取りパフォーマンスを提供

### 3.2 ランキング位置データ（/storage/{lang}/SQLite/ranking_position/ranking_position.db）

#### rising（急上昇ランキング位置）

**用途**: 急上昇ランキングの位置履歴（SQLite最適化版）

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

#### ranking（通常ランキング位置）

**用途**: 通常ランキングの位置履歴（SQLite最適化版）

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

#### total_count（総数情報）

**用途**: カテゴリ別総数の履歴（SQLite最適化版）

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

## 4. カテゴリマッピング

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

## 5. LLM向け基本的なSQLクエリパターン

### 5.1 現在のランキング取得

```sql
-- 1時間成長ランキング（上位10位）
SELECT 
    srh.id as ranking_position,
    oc.id,
    oc.name,
    oc.member,
    oc.category,
    srh.diff_member,
    srh.percent_increase,
    r.tag,
    oc.updated_at
FROM statistics_ranking_hour srh
JOIN open_chat oc ON srh.open_chat_id = oc.id
LEFT JOIN recommend r ON r.id = oc.id
WHERE srh.id <= 10
ORDER BY srh.id;

-- 24時間成長ランキング
SELECT 
    srh24.id as ranking_position,
    oc.name,
    oc.member,
    srh24.diff_member,
    srh24.percent_increase
FROM statistics_ranking_hour24 srh24
JOIN open_chat oc ON srh24.open_chat_id = oc.id
ORDER BY srh24.id
LIMIT 10;

-- 週間成長ランキング
SELECT 
    srw.id as ranking_position,
    oc.name,
    oc.member,
    srw.diff_member,
    srw.percent_increase
FROM statistics_ranking_week srw
JOIN open_chat oc ON srw.open_chat_id = oc.id
ORDER BY srw.id
LIMIT 10;
```

### 5.2 カテゴリ別ランキング

```sql
-- 特定カテゴリ（例：ゲーム=17）の1時間成長ランキング
SELECT 
    srh.id as ranking_position,
    oc.name,
    oc.member,
    srh.diff_member,
    srh.percent_increase
FROM statistics_ranking_hour srh
JOIN open_chat oc ON srh.open_chat_id = oc.id
WHERE oc.category = 17
ORDER BY srh.id
LIMIT 20;
```

### 5.3 検索クエリ

```sql
-- 名前で検索
SELECT 
    oc.id,
    oc.name,
    oc.description,
    oc.member,
    oc.category,
    r.tag
FROM open_chat oc
LEFT JOIN recommend r ON r.id = oc.id
WHERE oc.name LIKE '%キーワード%'
ORDER BY oc.member DESC
LIMIT 50;

-- タグで検索
SELECT 
    oc.id,
    oc.name,
    oc.member,
    r.tag
FROM recommend r
JOIN open_chat oc ON r.id = oc.id
WHERE r.tag LIKE '%タグ%'
ORDER BY oc.member DESC;
```

### 5.4 履歴データ取得（SQLite）

```sql
-- 特定チャットのメンバー数履歴（直近30日）
SELECT 
    open_chat_id,
    member,
    date
FROM statistics
WHERE open_chat_id = ? 
  AND date >= date('now', '-30 days')
ORDER BY date ASC;

-- 特定チャットのランキング位置履歴
SELECT 
    open_chat_id,
    category,
    position,
    time,
    date
FROM ranking
WHERE open_chat_id = ?
  AND category = ?
ORDER BY date DESC
LIMIT 30;
```

## 6. パフォーマンス最適化のポイント

### 6.1 データベース使い分け戦略

- **MySQL**: リアルタイム更新が必要なデータ
  - `open_chat`（メンバー数更新）
  - `statistics_ranking_*`（ランキング再計算）

- **SQLite**: 読み取り専用の集計データ
  - 履歴データ（`statistics`）
  - ランキング位置履歴（`ranking_position`）

### 6.2 重要な制約

1. **statistics_ranking_* テーブルは時刻情報なし**
   - 時刻は `open_chat.updated_at` を参照
   - データは毎時間完全再構築

2. **idカラムはランキング順位**
   - ORDER BY id でランキング順
   - 1位、2位、3位...の順序

3. **open_chat_idが全関係の中心**
   - 全てのJOINの基準となるキー

### 6.3 バッチ処理パターン

```php
// 統計ランキング更新の処理フロー例
// 1. 既存データ削除
DB::execute("TRUNCATE TABLE statistics_ranking_hour");

// 2. 新しいランキングデータ一括挿入
$this->inserter->import(DB::connect(), 'statistics_ranking_hour', $calculatedData);

// 3. SQLite履歴データ更新
$this->sqliteRepository->updateStatistics($historicalData);
```

## 7. 注意事項とベストプラクティス

### 7.1 データ整合性

- `open_chat.emid` は LINE の内部ID（ユニーク）
- `open_chat.url` は招待リンク（ユニーク、NULL可能）
- 削除されたチャットは `open_chat_deleted` に記録

### 7.2 文字エンコーディング

- **utf8mb4_unicode_520_ci**: 絵文字対応、名前・説明文用
- **utf8mb4_bin**: バイナリ照合、URL・ID用

### 7.3 インデックス戦略

- 検索頻度の高いカラム（`member`, `updated_at`, `emid`）
- 複合インデックス（SQLiteで多用）
- JOINパフォーマンス最適化

このドキュメントにより、LLMと開発者の両方が、オプチャグラフのデータ構造を正確に理解し、効率的なデータ分析と開発が可能になります。