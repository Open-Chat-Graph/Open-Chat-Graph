# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## プロジェクト概要

オプチャグラフ (OpenChat Graph) — LINE オープンチャットの統計を毎時クロールし、ランキング・検索・成長グラフを提供する Web アプリ。

- 本番: https://openchat-review.me（日本語・MIT ライセンス）
- PHP 8.5 + 自作 MimimalCMS（軽量MVC・DI・生SQL）+ MySQL/MariaDB（主データ）+ SQLite（ランキング・統計）+ React（`frontend/`）
- 主要ディレクトリ: `app/`（MVC）・`shadow/`（フレームワーク本体）・`batch/`（cron/バッチ）・`shared/`（設定・DIバインド）・`storage/`（SQLite・多言語データ）

## 詳細ガイド（プロジェクトスキル）

手順・コマンド・書き方の詳細は `.claude/skills/` のスキルに分離してある。該当する作業を始める前に読む:

- **dev-env** — 環境の起動・操作（Docker/Makefile・Mock・Shared mode・Codespaces・CI・ポート一覧）
- **coding-guide** — 実装（ページ追加MVC・DBアクセス・スキーマ変更・DI/ServiceProvider・クローラ設定・フロントビルド・キャッシュ生成/genetop の詳細）
- **pr-guide** — PR・コミット（タイトル/本文の書き方・スクショの撮り方/添付・skip-ci/skip-post・デプロイ確認手順・署名の詳細）

## インフラ操作（oc-infra スキル・本人のみ）

GA4/GTM/Search Console/AdSense・Cloudflare・本番/stg の SSH・MySQL など、このリポの外側のインフラは
private リポ `oc-infra` をスキルとして登録して操作する（本人 mimimiku778 のみアクセス可。詳細は oc-infra の `SKILL.md`）:

```bash
git clone https://github.com/mimimiku778/oc-infra.git ~/repos/oc-infra
ln -sfn ~/repos/oc-infra ~/.claude/skills/oc-infra   # /oc-infra スキルとして登録
```

`batch/sh/prod-sync` の機密(`secrets/`)もこのリポから取得する（Makefile の `PROD_SYNC_CONFIG_URL`）。

## 必守ルール（憲法）

### 環境保護（最重要）

- `.env` の `DATA_PROTECTION=true` のときは本番データを使用した環境。**`make up-mock` / `make ci-test` 等の mock環境操作はユーザーの明示的な指示なしに実行してはいけない**（本番DBが破壊される）
- ただし禁止対象は「本番DBを壊す mock環境操作」だけ。phpunit（`docker compose exec app vendor/bin/phpunit <path>`）や curl でローカル環境を叩く類のテストは普通に実行してよい（「テスト全般がNG」ではない）
- `DATA_PROTECTION=false` のときはテスト実行も mock環境の操作も自己判断で自由に行ってよい

### MimimalCMS フレームワーク本体は改造しない

本体はアプリと別管理で、改変すると本体アップデートと衝突し保守不能になる。

- 改造禁止: `shadow/` 配下すべて、`shared/MimimalCMS_ExceptionHandler.php` / `_HelperFunctions.php` / `_Settings.php` / `_Enums.php`
- 編集可: `shared/MimimalCmsConfig.php`（DIバインド・`$httpErrors` 等の拡張点）、`shared/bootstrap.php`、`app/` 配下すべて
- 横断的な振る舞いはアプリ側の拡張点で実現する（拡張点の一覧は coding-guide）
- 本体に手を入れないと解決できない／本体側の問題だと判断したら、勝手に直さず必ずユーザーに提案する

### Repository は必ず Interface とセットで作る

新規 Repository は必ず `XxxRepositoryInterface` を作り `/shared/MimimalCmsConfig.php` で DI バインドする。
具象クラスを直接 use／type-hint しない。ストレージ差し替え（SQLite↔MySQL 等）・テスト用モック化を容易に
するため（具象直結による影響拡大が実際に起きた）。

### 接続先ポート・URL は必ず .env を見る

curl・ブラウザ確認の前に `.env` の `HTTPS_PORT` / `WEB_PORT` 等を確認する。8443 はデフォルト値に
すぎず、shared mode や複数インスタンスでは別ポートになる。ハードコードされたポートを信用すると
別リポのインスタンスを見て「コードが効かない」と誤診する（実際に起きた）。

### スキーマ変更は setup/schema/mysql/*.sql の編集だけ

テーブル・カラムを追加したいときはスキーマ SQL を編集するだけ。デプロイ時に
`batch/exec/sync_mysql_schema.php` が不足分を「追加だけ」自動反映する（削除・型変更はしない）。
`deploy.yml` もコードも触らない。dry-run・注意点は coding-guide と `app/Services/Schema/README.md`。

### ドキュメント・スキルを実態と同期する

CLAUDE.md と `.claude/skills/` は実装・環境と対の正本ドキュメント。コマンド・ポート・ディレクトリ構成・
CI/デプロイの挙動など実態を変えたら、対応する記述も同じ PR で更新して齟齬を残さない。

### キャッシュ生成を足したら genetop・ドキュメントを同期する

- 毎時・日次 cron にキャッシュ生成を追加したら、必ず管理画面 genetop からも全ルーム一括再生成（全件）できるようにする（毎時/日次は一部の部屋しか回らず、genetop が無いとデプロイ後に全件バックフィルできない）
- ページ系キャッシュ（`oc_page_cache`・おすすめ `.dat`・毎時/日次 cron フロー）を変えたら、`README.md` の生成アーキテクチャ図と `app/Views/admin/log_index.php` を同時に対で更新する（正本ドキュメントの齟齬を残さない）

対象クラス・具体例は coding-guide。

### PR・コミット

- `docker compose`（スペース区切り）を使う。`docker-compose` は不可
- UI を変えた PR は実装後のスクショを本文に必ず添付する
- PR タイトルは一般人に伝わる書き方（コード用語を避け、具体数値と業務影響。SNS に自動投稿される）
- PHP 非変更の PR はデフォルトで skip-ci（確実に効かせるため PR タイトルを `skip-ci:` 始まりに。例外条件あり）
- マージで終わりにしない。本番 Deploy job（`deploy.yml`）が success になるまで見届けてから完了報告する。本番 SSH はしない
- 全コミット・全 GitHub 投稿（PR本文・issue・コメント）の末尾に環境署名を付ける（`Co-Authored-By` は付けない。他リポにも適用）:

  ```
  🤖 Generated with Claude Code (<モデルID>)
  Committed from: <hostname>:<作業ディレクトリ(~短縮)>
  ```

  GitHub 投稿では `Committed from:` を `Posted from:` にし、本文と `---` で区切る。

撮り方・skip-ci の例外・デプロイ確認手順など詳細はすべて pr-guide。
