# オプチャグラフ データAPI

オプチャグラフ（[openchat-review.me](https://openchat-review.me/)）が集めた LINEオープンチャットのデータ（SQLite）を、SQL で取得できる読み取り専用APIです。

`…/database/{username}/query?stmt=<SQL>` に SELECT文を渡すと、結果が JSON で返ります。

`{username}` / `{password}` は、申請時に受け取る Basic認証のユーザー名・パスワードです（[取得方法](#認証情報の取得)）。

---

## データを取得する

`stmt` パラメータに SQL を渡すと、実行結果が JSON で返ります。

例：過去24時間で伸びている部屋トップ20

```bash
curl -u '{username}:{password}' \
  -G "https://openchat-review.me/database/{username}/query" \
  --data-urlencode "stmt=SELECT m.display_name, g.member_increase_count
                         FROM growth_ranking_past_24_hours g
                         JOIN openchat_master m ON m.openchat_id = g.openchat_id
                         ORDER BY g.ranking_position
                         LIMIT 20"
```

レスポンス：

```json
{
  "status": "success",
  "data": [
    { "display_name": "雑談部屋", "member_increase_count": 518 }
  ],
  "lastUpdate": "2026-06-10 23:30:00"
}
```

- 実行結果の行は、`data` 配列に入ります（レスポンス全体の説明は [レスポンスの形式](#レスポンスの形式)）。
- 認証は Basic認証です（`-u {username}:{password}`）。URLパスの `{username}` にも、同じユーザー名を入れます。
- SQL は `stmt` パラメータに渡します。URLエンコードが必要です（`curl` では `-G --data-urlencode` で自動的にエンコードされます）。
- 他のクエリも、`stmt` の SQL を [サンプル](#サンプルsql) のように差し替えて実行します。

---

## 認証情報の取得

Basic認証のユーザー名とパスワードが必要です。

X（旧Twitter）の [@openchat_graph](https://x.com/openchat_graph) のDMでご相談ください。利用目的を伺ったうえでお渡しします。

以下では、受け取った値を `{username}` / `{password}` と表記します。

---

## 制約事項

- 使えるのは `SELECT` のみです。`UPDATE` / `DELETE` / `INSERT` は拒否されます。
- 1回で取得できるのは**最大20件**です。
  - `LIMIT` 未指定なら自動で `LIMIT 20` が付きます。
  - `LIMIT` に20を超える値を指定するとエラーです。
  - 20件より多く取りたいときは、`OFFSET` でページングします（[GASサンプル](#google-apps-scriptgasから使う) 参照）。
- SQL は URLエンコードが必要です。
- SQL の長さは最大10000文字です。
- テーブルは `openchat_id`（コメント系のみ `open_chat_id`）と `category_id` でつながります（[スキーマ](#スキーマテーブル定義) 参照）。

---

## レスポンスの形式

| フィールド | 型 | 内容 |
| --- | --- | --- |
| `status` | string | `"success"` または `"error"` |
| `data` | array | 結果の配列。`SELECT` した列名をキーに持つオブジェクトが並ぶ。0件なら `[]` |
| `lastUpdate` | string | データ取り込みが最後に完了した日時（`YYYY-MM-DD HH:MM:SS`・JST）。毎時更新 |
| `message` | string | エラー内容（エラー時のみ。成功時は無し） |

`data` の各値の型は、列の型に従います。INTEGER・REAL は数値、TEXT は文字列、NULL は `null` です。日付・日時も文字列で、`statistics_date` は `YYYY-MM-DD`、`last_updated_at` は `YYYY-MM-DD HH:MM:SS` です。

---

## エラー時のレスポンス

エラー時は HTTPステータスがエラーコードになり、ボディの `status` が `"error"`、`message` にエラー内容が入ります。成功判定は HTTPステータス（`200`）か `status` で行えます。

```json
{
  "status": "error",
  "message": "LIMIT cannot exceed 20"
}
```

| 状況 | ステータス | `message` |
| --- | --- | --- |
| ユーザー名（URLパス）が未登録 | 403 | `User not found` |
| パスワードが違う／未指定 | 401 | `Basic authentication is required to access the database API.`（`WWW-Authenticate: Basic` ヘッダ付き） |
| `stmt` が未指定 | 400 | `The "stmt" parameter is required and must be a string.` |
| `SELECT` 以外を実行 | 400 | `UPDATE / DELETE / INSERT statements are not allowed` |
| `LIMIT` が20超 | 400 | `LIMIT cannot exceed 20` |
| SQL が10000文字超 | 400 | `Query too long` |
| SQL の文法ミスなど実行エラー | 400 | `SQLSTATE[...] ...`（DBのエラーメッセージ） |

---

## サンプルSQL

`stmt=` の中身を差し替えて使います。

ルーム一覧のサンプルは、`openchat_existing` を `JOIN` して現存ルームに絞り込んでいます（理由は [現存ルームの抽出](#現存ルームの抽出)）。

### メンバー数が多い部屋 トップ20

```sql
SELECT m.openchat_id, m.display_name, m.current_member_count
FROM openchat_master m
JOIN openchat_existing e ON e.openchat_id = m.openchat_id
ORDER BY m.current_member_count DESC
LIMIT 20
```

返り値: `openchat_id`（数値）, `display_name`（文字列）, `current_member_count`（数値）

### 過去24時間で伸びている部屋ランキング（部屋名つき）

```sql
SELECT g.ranking_position, m.display_name,
       g.member_increase_count, g.growth_rate_percent
FROM growth_ranking_past_24_hours g
JOIN openchat_master m ON m.openchat_id = g.openchat_id
ORDER BY g.ranking_position
LIMIT 20
```

返り値: `ranking_position`（数値）, `display_name`（文字列）, `member_increase_count`（数値）, `growth_rate_percent`（数値・小数）

### 特定カテゴリ（例：「雑談」）の部屋をメンバー数順に

```sql
SELECT m.display_name, m.current_member_count, c.category_name
FROM openchat_master m
JOIN categories c ON c.category_id = m.category_id
JOIN openchat_existing e ON e.openchat_id = m.openchat_id
WHERE c.category_name = '雑談'
ORDER BY m.current_member_count DESC
LIMIT 20
```

返り値: `display_name`（文字列）, `current_member_count`（数値）, `category_name`（文字列）

### ある部屋（openchat_id=12345）のメンバー数の推移（直近20日）

```sql
SELECT statistics_date, member_count
FROM daily_member_statistics
WHERE openchat_id = 12345
ORDER BY statistics_date DESC
LIMIT 20
```

返り値: `statistics_date`（文字列 `YYYY-MM-DD`）, `member_count`（数値）

### スペシャル（認証バッジ＝スペシャル）の部屋だけ取得

`verification_badge` は `NULL`（なし）/ `公式認証` / `スペシャル` の3種類です。

```sql
SELECT m.openchat_id, m.display_name, m.current_member_count
FROM openchat_master m
JOIN openchat_existing e ON e.openchat_id = m.openchat_id
WHERE m.verification_badge = 'スペシャル'
ORDER BY m.current_member_count DESC
LIMIT 20
```

返り値: `openchat_id`（数値）, `display_name`（文字列）, `current_member_count`（数値）

公式認証も含めるなら `m.verification_badge IS NOT NULL` とします。

---

## 現存ルームの抽出

このDBは過去に存在したルームも削除せず保持し続けるため、`openchat_master` には現在は存在しないルームも含まれます。

現存ルームだけを対象にするには、`openchat_existing`（現在オプチャグラフに存在するIDだけを毎時洗い替えで保持するテーブル）と `openchat_id` で `JOIN` します。上のサンプルのルーム一覧は、すべてこの方法で絞り込んでいます。

---

## Google Apps Script（GAS）から使う

Basic認証付きでデータを取得する関数の例です。`USER` / `PASS` を書き換えてください。取得後のデータの扱いは任意です。

```javascript
const USER = '{username}';
const PASS = '{password}';

// SQLを実行し、結果の行（配列）を返す
function query(sql) {
  const res = UrlFetchApp.fetch(
    'https://openchat-review.me/database/' + USER + '/query?stmt=' + encodeURIComponent(sql),
    {
      headers: { Authorization: 'Basic ' + Utilities.base64Encode(USER + ':' + PASS) },
      muteHttpExceptions: true,
    }
  );
  const json = JSON.parse(res.getContentText());
  if (json.status !== 'success') throw new Error(json.message);
  return json.data;
}

// ページングの例（1回の上限は20件）
function example() {
  let all = [], offset = 0, rows;
  do {
    rows = query('SELECT openchat_id, display_name FROM openchat_master'
      + ' ORDER BY openchat_id LIMIT 20 OFFSET ' + offset);
    all = all.concat(rows);
    offset += 20;
  } while (rows.length === 20);
  Logger.log(all.length);
}
```

---

## スキーマ（テーブル定義）

各テーブルの定義です。カラムのコメントに、取得できる内容を記載しています。

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
    record_date TEXT NOT NULL                     -- 記録日（ユニークキー用のカラム。取得不要）
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
    verification_badge TEXT,                -- 認証バッジ（NULL:なし, 公式認証, スペシャル）
    category_id INTEGER,                    -- カテゴリID（categoriesと紐づく）
    join_method TEXT NOT NULL,              -- 参加方法（全体公開, 参加承認制, 参加コード入力制）
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
    id INTEGER NOT NULL,                    -- 旧ID（互換性のため保持）
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

`ban_room` / `ban_user` / `comment_log` などの管理用テーブルもありますが、通常の分析では使いません。

---
---

## （オーナー向けメモ）APIユーザーの追加方法

APIにログインできるユーザーは `app/Config/ApiUser.php` の `ApiUser::$apiUser` 配列で定義します。`username` / `password` を追加してください。

ここに含まれる `username` のみが Basic認証でログインできます。含まれない `username` でアクセスすると 403 になります。

```php
namespace App\Config;

class ApiUser
{
    /** @var array<int, array{username:string, password:string}> */
    static array $apiUser = [
        [
            'username' => 'user1',
            'password' => 'password',
        ],
        [
            'username' => 'user2',
            'password' => 'password',
        ],
    ];
}
```
