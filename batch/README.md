# batch/ ディレクトリ構成

PHP コードからの起動は必ず `BatchScript` enum + `BatchScriptLauncher`
（`app/Services/Cron/`）を経由する。パス解決・プロセスkillはそこに一元化されている。
エントリスクリプトは「実行を始めるだけの数行」とし、処理本体はサービス層に置く。

## cron/

- `cron_crawling.php` — crontab エントリ（毎時 :30 ja / :35 tw / :40 th）。`SyncOpenChat` を起動する

## exec/ — 自動フロー・本番機能から起動される必須スクリプト

- `update_oc_page_cache.php` — ルーム個別ページキャッシュ生成（`SyncOpenChat` 毎時/日次からBG起動。管理画面から全件バックフィルも可）
- `update_recommend_static_data.php` — おすすめ静的データ生成＋タグ定義JSON変更の自動検知（毎時処理からBG起動）
- `tag_update.php` — おすすめタグ全レコード再適用（管理画面タグエディタ「全レコードに即時反映」から起動）
- `ocreview_api_data_import_background.php` — アーカイブDBインポート（毎時処理からBG起動・jaのみ）
- `persist_ranking_position_background.php` — 毎時ランキングDB反映（毎時処理からBG起動）
- `sync_mysql_schema.php` — スキーマ自動反映（デプロイ時に実行。`--dry-run` 可）
- `update_api_db.php` — アーカイブDBインポートの手動実行（`batch/sh/prod-sync` と管理画面 apidb_test から）

## exec/admin/ — 管理画面から手動起動する保守ツール

- `genetop_exec.php` — 静的キャッシュデータの手動再生成（管理画面 genetop）
- `retry_daily_task.php` — 日次処理のリトライ（障害復旧用。管理画面 retry_daily_test）

## sh/

- `prod-sync/` — 本番データ同期用シェルスクリプト
