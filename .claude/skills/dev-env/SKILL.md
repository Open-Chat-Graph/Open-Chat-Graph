---
name: dev-env
description: 開発環境の起動・操作ガイド。Docker/Makefile コマンド、Mock 環境（LINE Mock API）、Shared mode（別リポと DB 共有）、ポート一覧、Codespaces、CI 環境とプリビルドイメージの詳細。環境の起動・再構築・ローカル確認・CI デバッグの前に読む。
---

# 開発環境ガイド

> 前提: CLAUDE.md の環境保護ルールに従う。`DATA_PROTECTION=true` のとき `make up-mock` / `make ci-test` 等の mock 環境操作はユーザーの明示的な指示なしに実行しない。

## Docker Setup

**IMPORTANT**: `docker compose`（スペース区切り）を使う。`docker-compose`（ハイフン）は不可。

Makefile で Docker を管理する:

```bash
# 初期セットアップ
make init               # 対話形式（非対話は make init-y）

# 起動（どの環境かは up 系ターゲットで決まる）
make up                 # 基本環境（実際の LINE サーバーにアクセスする）
make up-mock            # Mock 環境（LINE Mock API 同梱）
make up-shared          # Shared mode（別リポの DB/storage を共有・下記）

# 操作（down / restart / rebuild / ssh は起動中の環境を自動判定して効く）
make down / restart / rebuild / ssh

# クローリング cron の起動・停止
make cron / cron-stop

# 現在の設定を表示・全ターゲット一覧（phpstan, css-check, build-frontend 等もある）
make show / help
```

Mock のデータ件数・遅延は `.env` の `MOCK_RANKING_COUNT` / `MOCK_RISING_COUNT` / `MOCK_DELAY_ENABLED` で制御する（`up-mock-slow` のような専用ターゲットは無い）。

## Shared Mode（共有モード）

別ディレクトリにある既存リポジトリの **MariaDB / storage / comment-img を実体共有**しつつ、コードはこのリポジトリで動かす2つ目のインスタンス。

- `make up-shared` … 初回は参照先リポジトリのパスを対話で尋ね `.shared.local.mk`（gitignore済・環境ごとに異なる）に保存。ポートが参照先と衝突する場合はプリセット（9000/9443 等）から選択して `.env` に保存。2回目以降は尋ねない。
- 参照先の docker ネットワークに app を直結し、storage/comment-img を bind mount する（`docker-compose.shared.yml`）。`translation.json` だけはこのリポジトリ側を使う。
- `DATA_PROTECTION=true` で動く（実データ共有のため）。詳細は [`README.shared.md`](../../../README.shared.md)。

## 環境詳細・ポート

> **接続先ポート(URL)は必ず `.env` を見て判断する**。`HTTPS_PORT` / `WEB_PORT` 等。下記の `8443` は
> 基本/Mock環境のデフォルト値にすぎず、**shared mode や複数インスタンスでは別ポートになる**（実際の
> 共有環境では `HTTPS_PORT=10443` 等）。ハードコードされたポートを信用してローカルを叩くと、別リポの
> インスタンスを見て「コードが効かない」等と誤判断する（実際に起きた）。curl・ブラウザ確認の前に `.env` を見る。

**基本環境:**

- HTTPS: https://localhost:8443
- MySQL: localhost:3306
- phpMyAdmin: http://localhost:8080
- 実際の LINE サーバーにアクセスする

**Mock 環境:**

- HTTPS: https://localhost:8443（基本環境と同じポートを使う）
- LINE Mock API: http://localhost:9000（外部アクセス）、http://line-mock-api（内部）
- MySQL: localhost:3306（共有）
- phpMyAdmin: http://localhost:8080

**Mock API の機能:**

- 内部通信は Docker Compose のサービス名（`line-mock-api`）を使用
- データ件数を設定可能（MOCK_RANKING_COUNT, MOCK_RISING_COUNT）
- 本番相当の遅延シミュレーション（MOCK_DELAY_ENABLED）
- 多言語対応（日本語・繁体字・タイ語）

**Cron 自動実行（CRON=1）:**

- 30分: 日本語クローリング
- 35分: 繁体字（/tw）
- 40分: タイ語（/th）

## 必要ツール

- Docker（Compose V2）
- `mkcert`（SSL証明書生成。CI では不要）

## GitHub Codespaces

Codespaces 環境はローカル開発環境と完全に同じ:

- 独立した Ubuntu コンテナ内で Docker 環境を起動
- ローカルと同じ Makefile コマンドが使用可能
- `make ci-test` などの全てのスクリプトが正常に動作

セットアップ:

1. post-create で Claude CLI / GitHub CLI 等がインストールされる（`make init-y` は自動実行されない）
2. `make init-y` → `make up-mock` で Mock 環境を起動
3. ポート転送タブから各サービスにアクセス

構成:

- `.devcontainer/Dockerfile`: Ubuntu + Docker + mkcert
- `.devcontainer/devcontainer.json`: シンプルな devcontainer 設定
- `.devcontainer/post-create-command.sh`: 初期セットアップスクリプト

## CI 環境

CI 専用の設定を使用:

- `docker-compose.ci.yml`: CI 専用のオーバーライド設定
- SSL 証明書生成をスキップ（HTTP 通信のみ）
- Xdebug インストール無効
- phpMyAdmin 除外

GitHub Actions:

- `.github/workflows/ci.yml`: 自動テスト実行（Mock 環境のクローリング＋URLテスト。**phpunit は実行しない**。ユニットテストはローカルで `docker compose exec app vendor/bin/phpunit <path>`）
- `.github/workflows/build-images.yml`: プリビルドイメージのビルド＆プッシュ（main push または手動実行）
- プリビルドイメージ: GitHub Container Registry (ghcr.io) に CI 用イメージを保存
  - `ghcr.io/{owner}/oc-review-mock-app:latest`: アプリケーションイメージ
  - `ghcr.io/{owner}/oc-review-mock-line-mock-api:latest`: LINE Mock API イメージ
- CI 実行時の動作:
  - Dockerfile/composer 関連ファイルに変更がある場合: 必ずビルド（最新の変更を反映）
  - 変更がない場合: プリビルドイメージを pull（高速化）
  - プリビルドイメージが存在しない場合: ビルド（フォールバック）
- Docker Layer Caching: `docker/build-push-action@v6` で GitHub Actions キャッシュを使用
- `cache-from/cache-to type=gha,scope={app|line-mock-api}`: 各イメージに一意の scope を設定
- プリビルドイメージ使用でビルド時間を大幅短縮（34秒 → 5-10秒）

ローカルで CI テストを実行（`DATA_PROTECTION=true` では禁止）:

```bash
make ci-test
```
