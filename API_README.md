# オプチャグラフ データAPI

オプチャグラフ（[openchat-review.me](https://openchat-review.me/)）が収集した LINEオープンチャットのデータ（SQLite）に対する、読み取り専用の SQL API。

- エンドポイント: `POST /database/{username}/query`
- **Basic認証**（`{username}` / `{password}` をそのまま）を付け、POSTボディに `stmt`（SQL）と `password` を渡す。結果は JSON。
- POSTボディの `password` は申請時に渡すパスワードの **SHA256（16進）**。`{username}` はパスに入れる（[認証情報の取得](#認証情報の取得)）。
- **認証不要で使いたい場合は [MCP サーバー](#mcp-サーバー認証不要ai-アシスタント向け)** もある（AI アシスタント向け・レートリミットあり）。

---

## MCP サーバー（認証不要・AI アシスタント向け）

Claude・ChatGPT などの AI アシスタントやエージェントから、申請なしでこのデータベースへアクセスできる
[MCP (Model Context Protocol)](https://modelcontextprotocol.io/) サーバーを公開している。

- エンドポイント: `https://openchat-review.me/mcp`（Streamable HTTP・POST・レスポンスは常に JSON。SSE/セッション管理なしのステートレス実装）
- 認証: 不要
- ツール:
  | ツール | 内容 |
  | --- | --- |
  | `search_openchat` | 部屋名・説明文のキーワード検索（メンバー数順・最大20件） |
  | `get_openchat_stats` | 部屋の基本情報＋直近24時間の毎時メンバー数推移＋直近30日の日次推移＋LINE公式ランキング順位 |
  | `get_database_schema` | テーブル定義（DDL・日本語コメント付き）を取得 |
  | `query_database` | 読み取り専用 SQL（SELECT / WITH のみ・1クエリ100行まで。超える分は OFFSET でページング） |
- 回数のレートリミットは無し（使い放題）。サイト全体の同時実行2件までの保護だけある（非力なサーバーなので手加減してほしい）
- 非公開テーブル: `ban_user`・`comment_log`・`ban_room`（IPアドレス等を含む運営用データ）にはアクセスできない
- 出典表記のお願い: データを引用する際は「オプチャグラフ (openchat-review.me)」と部屋ページ URL（`https://openchat-review.me/oc/{id}`）を添えてほしい

Claude Code / Claude Desktop での接続例:

```bash
claude mcp add --transport http openchat-graph https://openchat-review.me/mcp
```

```json
{
  "mcpServers": {
    "openchat-graph": {
      "type": "http",
      "url": "https://openchat-review.me/mcp"
    }
  }
}
```

サイト概要の機械可読版は [`https://openchat-review.me/llms.txt`](https://openchat-review.me/llms.txt) にもある。

---

## 認証情報の取得

利用には `username` / `password` が必要です。

X（旧Twitter）の [@openchat_graph](https://x.com/openchat_graph) のDMでご相談ください。利用目的を伺ったうえでお渡しします。

以下、受け取った値を `{username}` / `{password}` と表記します。リクエストでは2つの認証を両方付けます。

- Basic認証: `{username}` / `{password}` をそのまま
- POSTボディの `password`: `{password}` の SHA256（16進）

---

## リクエスト

`POST`。Basic認証（`{username}` / `{password}`）を付け、ボディに `stmt`（SELECT文）と `password`（パスワードの SHA256・16進）を渡す。レスポンスは下記の形の JSON。

```bash
curl -u "{username}:{password}" -X POST "https://openchat-review.me/database/{username}/query" \
  --data-urlencode "password={passwordのSHA256}" \
  --data-urlencode "stmt=SELECT display_name FROM openchat_master LIMIT 5"
```

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
- スキーマ定義（DDL）の取得は `/database/{username}/schema`（同じく `POST` + Basic認証 + `password`）。

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
| Basic認証が未指定／一致しない | 401 | `Basic authentication is required to access the database API. Sorry, we initially forgot to include this requirement in the docs.` |
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

カラム定義（DDL）は `POST /database/{username}/schema`（Basic認証 + `password` 必須）で取得する。以下は各テーブルの概要のみ。

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
