# オプチャグラフ データAPI

オプチャグラフ（[openchat-review.me](https://openchat-review.me/)）が収集した LINEオープンチャットのデータ（SQLite）に対する、読み取り専用の SQL API。

- エンドポイント: `GET /database/{username}/query`
- SQL は `stmt` パラメータに渡す。結果は JSON。
- 認証は Basic。`{username}` / `{password}` は申請時に渡す（[認証情報の取得](#認証情報の取得)）。

---

## 認証情報の取得

利用には Basic認証の `username` / `password` が必要です。

X（旧Twitter）の [@openchat_graph](https://x.com/openchat_graph) のDMでご相談ください。利用目的を伺ったうえでお渡しします。

以下、受け取った値を `{username}` / `{password}` と表記します。

---

## リクエスト

`stmt` に SELECT文を渡して `GET`。レスポンスは下記の形の JSON。

```json
{
  "status": "success",
  "data": [
    { "display_name": "雑談部屋", "member_increase_count": 518 }
  ],
  "lastUpdate": "2026-06-10 23:30:00"
}
```

- `data` が結果行の配列。`SELECT` した列名がキー。0件なら `[]`。
- `stmt` はクエリ文字列に載るため、相応にエンコードすること。
- 認証は Basic。

---

## レスポンス

| フィールド | 型 | 内容 |
| --- | --- | --- |
| `status` | string | `"success"` / `"error"` |
| `data` | array | 結果行の配列。列名をキーに持つオブジェクトが並ぶ。0件なら `[]` |
| `lastUpdate` | string | データ取り込みが最後に完了した日時（`YYYY-MM-DD HH:MM:SS`・JST）。毎時更新 |
| `message` | string | エラー内容（エラー時のみ） |

値の型は列の型に従う。INTEGER・REAL は数値、TEXT は文字列、NULL は `null`。`statistics_date` は `YYYY-MM-DD`、`last_updated_at` は `YYYY-MM-DD HH:MM:SS`。

---

## 制約

- `SELECT` のみ。`UPDATE` / `DELETE` / `INSERT` は拒否。
- `LIMIT` は最大 20。未指定なら自動で `LIMIT 20`、20超はエラー。20件より多く取るときは `OFFSET` でページング。
- `stmt` は最大 10000 文字。

---

## エラー

エラー時は HTTPステータスがエラーコードになり、`status` が `"error"`、`message` に内容が入る。

| 状況 | ステータス | `message` |
| --- | --- | --- |
| `{username}`（URLパス）が未登録 | 403 | `User not found` |
| 認証が一致しない／未指定 | 401 | `Basic authentication is required to access the database API.`（`WWW-Authenticate: Basic` 付き） |
| `stmt` が未指定 | 400 | `The "stmt" parameter is required and must be a string.` |
| `SELECT` 以外 | 400 | `UPDATE / DELETE / INSERT statements are not allowed` |
| `LIMIT` が20超 | 400 | `LIMIT cannot exceed 20` |
| `stmt` が10000文字超 | 400 | `Query too long` |
| SQL 実行エラー | 400 | `SQLSTATE[...] ...`（DBのエラーメッセージ） |

---

## スキーマ（テーブル定義）

各カラムのコメントに取得できる内容を記載。

```sql
-- カテゴリマスタテーブル（25レコード）
CREATE TABLE categories (
    category_id INTEGER PRIMARY KEY,        -- カテゴリID
    category_name TEXT NOT NULL             -- カテゴリ名
);

-- オープンチャットのメンバー数統計（毎日1件、オープンチャットIDと日付でユニーク）約6000万レコード以上
CREATE TABLE daily_member_statistics (
    record_id INTEGER PRIMARY KEY,          -- レコードID
    openchat_id INTEGER NOT NULL,           -- オプチャグラフでオープンチャットを識別するための主キー（openchat_masterと紐づく）
    member_count INTEGER NOT NULL,          -- メンバー数
    statistics_date TEXT NOT NULL           -- 統計日
);

-- 成長ランキング（過去24時間・毎時更新）
CREATE TABLE growth_ranking_past_24_hours (
    ranking_position INTEGER PRIMARY KEY,   -- 順位（1位、2位...）
    openchat_id INTEGER NOT NULL,           -- 主キー（openchat_masterと紐づく）
    member_increase_count INTEGER NOT NULL, -- メンバー増加数
    growth_rate_percent REAL NOT NULL       -- 成長率（%）
);

-- 成長ランキング（過去1時間・毎時更新）
CREATE TABLE growth_ranking_past_hour (
    ranking_position INTEGER PRIMARY KEY,   -- 順位（1位、2位...）
    openchat_id INTEGER NOT NULL,           -- 主キー（openchat_masterと紐づく）
    member_increase_count INTEGER NOT NULL, -- メンバー増加数
    growth_rate_percent REAL NOT NULL       -- 成長率（%）
);

-- 成長ランキング（過去1週間・毎時更新）
CREATE TABLE growth_ranking_past_week (
    ranking_position INTEGER PRIMARY KEY,   -- 順位（1位、2位...）
    openchat_id INTEGER NOT NULL,           -- 主キー（openchat_masterと紐づく）
    member_increase_count INTEGER NOT NULL, -- メンバー増加数
    growth_rate_percent REAL NOT NULL       -- 成長率（%）
);

-- LINE公式サイトの「ランキング」履歴（カテゴリ別・全体、1日1件、中央値保存）
CREATE TABLE line_official_activity_ranking_history (
    record_id INTEGER PRIMARY KEY,                -- レコードID
    openchat_id INTEGER NOT NULL,                 -- 主キー（openchat_masterと紐づく）
    category_id INTEGER NOT NULL,                 -- カテゴリID（0=すべて、1以上=各カテゴリ）（categoriesと紐づく）
    activity_ranking_position INTEGER NOT NULL,   -- その日のLINE公式「ランキング」順位（中央値、何件中何位かはline_official_ranking_total_countで確認）
    recorded_at TEXT NOT NULL,                    -- 記録日時（line_official_ranking_total_countと紐づく）
    record_date TEXT NOT NULL                     -- 記録日
);

-- LINE公式サイトの「急上昇」履歴（カテゴリ別・全体、1日1件、最大値保存）
CREATE TABLE line_official_activity_trending_history (
    record_id INTEGER PRIMARY KEY,                -- レコードID
    openchat_id INTEGER NOT NULL,                 -- 主キー（openchat_masterと紐づく）
    category_id INTEGER NOT NULL,                 -- カテゴリID（0=すべて、1以上=各カテゴリ）（categoriesと紐づく）
    activity_trending_position INTEGER NOT NULL,  -- その日のLINE公式「急上昇」順位（最大値、何件中何位かはline_official_ranking_total_countで確認）
    recorded_at TEXT NOT NULL,                    -- 記録日時
    record_date TEXT NOT NULL                     -- 記録日
);

-- LINE公式サイトの全ランキング総件数履歴（「ランキング」・「急上昇」、カテゴリ別・全体、毎時間記録）
CREATE TABLE line_official_ranking_total_count (
    record_id INTEGER PRIMARY KEY,                  -- レコードID
    activity_trending_total_count INTEGER NOT NULL, -- その時間のLINE公式「急上昇」総件数（何件中何位かを知るために使用）
    activity_ranking_total_count INTEGER NOT NULL,  -- その時間のLINE公式「ランキング」総件数（何件中何位かを知るために使用）
    recorded_at TEXT NOT NULL,                      -- 記録日時（毎時間更新）
    category_id INTEGER NOT NULL                    -- カテゴリID（0=すべて、1以上=各カテゴリ）（categoriesと紐づく）
);

-- オープンチャットマスターテーブル（部屋の基本情報）
CREATE TABLE openchat_master (
    openchat_id INTEGER PRIMARY KEY,        -- オプチャグラフでオープンチャットを識別するための主キー
    line_internal_id TEXT,                  -- LINE内部ID（emid）
    display_name TEXT NOT NULL,             -- オープンチャット名
    invitation_url TEXT,                    -- オープンチャット招待用URL（参加リンク）
    description TEXT,                        -- 説明
    profile_image_url TEXT,                 -- オープンチャットのメイン画像
    current_member_count INTEGER NOT NULL DEFAULT 0,  -- 現在のメンバー数
    verification_badge TEXT,                -- 認証バッジ（無印 / 公式 / スペシャルの別。値は実データ参照）
    category_id INTEGER,                    -- カテゴリID（categoriesと紐づく）
    join_method TEXT NOT NULL,              -- 参加方法（値は実データ参照）
    established_at TEXT,                     -- オープンチャットの開設日時
    first_seen_at TEXT NOT NULL,            -- 初回取得日時
    last_updated_at TEXT NOT NULL           -- 名前・画像・説明・バッジ・参加方法・カテゴリのいずれかが最後に更新された日時
);

-- 現在オプチャグラフに存在するオープンチャットのID一覧（毎時、全件洗い替え）
CREATE TABLE openchat_existing (
    openchat_id INTEGER PRIMARY KEY         -- 現存するオープンチャットID（openchat_master.openchat_idと紐づく）
);

-- コメントテーブル
CREATE TABLE comment (
    comment_id INTEGER PRIMARY KEY,         -- コメントID
    open_chat_id INTEGER NOT NULL,          -- オープンチャットID（openchat_master.openchat_idと紐づく）
    id INTEGER NOT NULL,                    -- ルーム内の連番
    user_id TEXT NOT NULL,                  -- ユーザーID
    name TEXT NOT NULL,                     -- ユーザー名
    text TEXT NOT NULL,                     -- コメント本文
    time TEXT NOT NULL,                     -- 投稿日時
    flag INTEGER NOT NULL DEFAULT 0         -- フラグ（0: 通常、その他: 削除・非表示など）
);

-- いいねテーブル
CREATE TABLE comment_like (
    id INTEGER PRIMARY KEY,                 -- いいねID
    comment_id INTEGER NOT NULL,            -- コメントID（comment.comment_idと紐づく）
    user_id TEXT NOT NULL,                  -- ユーザーID
    type TEXT NOT NULL,                     -- いいねタイプ
    time TEXT NOT NULL                      -- いいね日時
);
```
