# MySQL スキーマ同期 (Schema Sync)

新しいテーブルやカラムを追加するたびに `deploy.yml` へ手書きしていた運用をやめ、
`setup/schema/mysql/*.sql` を基準(唯一の正)として、各DBに足りないテーブル・カラム・索引を
**追加だけ**自動反映する。今後は**スキーマファイルを編集するだけ**で本番に反映され、
既存データは壊れない(追加しかしない)。デプロイ時とローカルで同じスクリプトを使う。

## 対応環境 / 前提条件

- **DB**: MySQL 5.7+ / 8.x、MariaDB 10.x の**両対応**(MariaDB 専用構文に非依存)
- **PHP**: 8.5 / PDO
- **接続ユーザ権限**: `CREATE` / `ALTER` / `INDEX` が必要。**`DROP` は不要**
- DB名・接続情報は `local-secrets.php` が解決(prefix 付きDB名など環境差は自動対応)

## 安全性

実行するのは**追加だけの SQL**:

- `CREATE TABLE IF NOT EXISTS` (新規テーブル)
- `ALTER TABLE ... ADD COLUMN ...` (不足カラム)
- `ALTER TABLE ... ADD KEY/UNIQUE KEY ...` (不足索引)

削除・変更系(`DROP` / `MODIFY` / `CHANGE`)は**構造的に作らない**(コードパスが無い)。
冪等性は「実DBに足りない分だけ追加する」差分判定で担保。スキーマに無くDBにある要素(ドリフト)は
**警告ログのみ・削除しない**。→ 仮にバグがあってもデータは失われない。破壊的変更・型変更・
PRIMARY/FOREIGN KEY 追加は手動運用。

### ロック競合・失敗時の挙動

- 本番のテーブルがロック中/長時間トランザクション中でも**ハングしない**。DDL のロック待ちを
  30秒で打ち切り(`lock_wait_timeout`)、取れなければそのDDLは失敗する(待ち続けて後続クエリを
  詰まらせない)。
- あるDBで失敗しても**他DBの反映は続行**し、最後に失敗一覧を出して**非ゼロ終了**(デプロイで
  気付ける)。加算・冪等なので、原因解消後に再実行すれば復旧できる。
- 生成する全DDLは `` `db`.`table` `` 修飾。接続断→自動再接続でDB切替が外れても、別DBに流れない。
- 列追加は `AFTER` を付けず**末尾に追加**するため、大きなテーブルでも INSTANT ADD が効きやすい
  (列順はスキーマ定義と一致しないが機能影響なし)。
- データは失われないが**失敗はしうる**(例: 重複データのあるテーブルへの UNIQUE 追加、既存行が
  あり DEFAULT なしの NOT NULL 列追加を厳格モードで実行)。その場合は上記のとおり通知される。

## 使い方

```bash
# 実行される SQL を事前確認 (DBは変更しない)
docker compose exec app php batch/exec/sync_mysql_schema.php --dry-run

# 実反映 (足りない分だけ追加)
docker compose exec app php batch/exec/sync_mysql_schema.php
```

デプロイ時は `deploy.yml` の "Sync MySQL schema" ステップが同じスクリプトを自動実行する。

## テーブル / カラムの追加手順

1. `setup/schema/mysql/<db>_schema.sql` の `CREATE TABLE` を編集
2. ローカルで `--dry-run` → 反映 を確認
3. PR をマージ → デプロイで stg→本番に自動反映

**スキーマファイルを編集するだけ。deploy.yml もコードも触らない。**

## 構成

純粋ロジックと DB I/O を分離(テスト容易性):

| 役割 | クラス / ファイル | テスト |
|---|---|---|
| SQL解析 (純粋) | `SchemaParser` | DB不要・単体 |
| 差分→追加SQL生成 (純粋) | `SchemaDiffer` | DB不要・単体 |
| 実DB読取 + 実行 | `SchemaSyncRunner` | 使い捨てDB結合 |
| CLI エントリ | `batch/exec/sync_mysql_schema.php` | — |

## テスト

```bash
# 純粋ロジック (DB不要・高速)
docker compose exec app vendor/bin/phpunit app/Services/Schema/test/SchemaParserTest.php
docker compose exec app vendor/bin/phpunit app/Services/Schema/test/SchemaDifferTest.php

# 結合 (ローカルDBに使い捨てDBを作成→検証→DROP)
docker compose exec app vendor/bin/phpunit app/Services/Schema/test/SchemaSyncRunnerTest.php
```

結合テストは `ocgraph_schemasync_test_<random>` を作成→検証→DROP する(実データに触れない)。
接続ユーザに CREATE/DROP DATABASE 権限が必要。

## 対象外 (手動運用)

カラム/テーブル削除、型・DEFAULT変更、PRIMARY/FOREIGN KEY 追加、既存カラムの定義差分検出、
SQLite スキーマ。
