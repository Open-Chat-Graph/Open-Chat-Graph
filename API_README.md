# オプチャグラフ データAPI

オプチャグラフ（[openchat-review.me](https://openchat-review.me/)）が収集した LINEオープンチャットのデータ（SQLite）に対する、読み取り専用の SQL API。

- エンドポイント: `POST /database/{username}/query`
- POSTボディに `stmt`（SQL）と `password` を渡す。結果は JSON。
- `password` は申請時に渡すパスワードの **SHA256（16進）**。`{username}` はパスに入れる（[認証情報の取得](#認証情報の取得)）。

---

## 認証情報の取得

利用には `username` / `password` が必要です。

X（旧Twitter）の [@openchat_graph](https://x.com/openchat_graph) のDMでご相談ください。利用目的を伺ったうえでお渡しします。

以下、受け取った値を `{username}` / `{password}` と表記します。リクエストでは `password` をそのままではなく SHA256（16進）にして送ります。

---

## リクエスト

`POST`。ボディに `stmt`（SELECT文）と `password`（パスワードの SHA256・16進）を渡す。レスポンスは下記の形の JSON。

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
- スキーマ定義（DDL）の取得は `/database/{username}/schema`（同じく `POST` + `password`）。

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

### レートリミット

- **同時リクエストは1件まで**。前のリクエストの完了を待たずに次を送ると 429 が返る。
- **5分間で取得できるレコード数は合計1000件まで**。超えると 429 が返り、`Retry-After` ヘッダに再試行までの秒数が入る。
- 取得件数は「実際に返ってきた行数」でカウントされる（エラーになったリクエストはカウントされない）。

---

## エラー

エラー時は HTTPステータスがエラーコードになり、`status` が `"error"`、`message` に内容が入る。

| 状況 | ステータス | `message` |
| --- | --- | --- |
| `{username}`（URLパス）が未登録 | 403 | `User not found` |
| `password` が一致しない／未指定 | 401 | `Authentication failed.` |
| `stmt` が未指定 | 400 | `The "stmt" parameter is required and must be a string.` |
| `SELECT` 以外 | 400 | `UPDATE / DELETE / INSERT statements are not allowed` |
| `LIMIT` が20超 | 400 | `LIMIT cannot exceed 20` |
| `stmt` が10000文字超 | 400 | `Query too long` |
| SQL 実行エラー | 400 | `SQLSTATE[...] ...`（DBのエラーメッセージ） |
| 同時リクエストが2件以上 | 429 | `Too many concurrent requests: ...` |
| 5分間の取得レコード数が1000件超 | 429 | `Rate limit exceeded: ...`（`Retry-After` ヘッダ付き） |

---

## スキーマ

カラム定義（DDL）は `POST /database/{username}/schema`（`password` 必須）で取得する。以下は各テーブルの概要のみ。

| テーブル | 概要 |
| --- | --- |
| `categories` | カテゴリのマスタ |
| `daily_member_statistics` | 各部屋のメンバー数の日次推移 |
| `growth_ranking_past_hour` | 直近1時間の成長ランキング |
| `growth_ranking_past_24_hours` | 直近24時間の成長ランキング |
| `growth_ranking_past_week` | 直近1週間の成長ランキング |
| `line_official_activity_ranking_history` | LINE公式「ランキング」の順位履歴 |
| `line_official_activity_trending_history` | LINE公式「急上昇」の順位履歴 |
| `line_official_ranking_total_count` | 公式ランキング/急上昇の総件数履歴 |
| `openchat_master` | 部屋の基本情報（マスタ） |
| `openchat_existing` | 現存する部屋のID一覧 |
| `comment` | コメント |
| `comment_like` | コメントのいいね |
