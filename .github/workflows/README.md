# GitHub Actions Workflows

## 概要

### ci.yml

PRマージ前の自動テスト実行

**実行タイミング:**

- `main`ブランチへのPull Request
- 手動実行（workflow_dispatch）

**ローカルでの実行:**

```bash
make ci-test
```

### post-pr-merge.yml

PRマージ時にX (Twitter) に通知を投稿

- `skip-post` ラベル: 投稿をスキップ
- draft PR: 自動的にスキップ

---

## CI環境の特徴

CI環境では`docker-compose.yml`と`docker-compose.ci.yml`を組み合わせて使用:

```bash
docker compose -f docker-compose.yml -f docker-compose.ci.yml up
```

**主な設定:**

- **HTTP通信のみ**: SSL証明書生成不要（ビルド高速化）
- **Xdebug無効**: `INSTALL_XDEBUG: false`
- **Mock API使用**: `line-mock-api`サービスでLINE公式サイトをエミュレート
- **自動クローリング無効**: `CRON=0`

### テスト内容

1. **クローリングテスト** (`.github/scripts/test-ci.sh`)
   - Mock APIを使用して25時間分のデータ収集
   - 日本語/繁体字/タイ語の並列テスト

2. **URLテスト** (`.github/scripts/test-urls.sh`)
   - 80以上のエンドポイントへのアクセステスト
   - 多言語ページ、検索、POSTリクエスト

3. **エラーログチェック** (`.github/scripts/check-error-log.sh`)
   - `storage/exception.log`の確認

---

## ローカル環境との違い

| 項目           | ローカル（Mock）          | CI環境                  |
| -------------- | ------------------------- | ----------------------- |
| Docker Compose | `docker-compose.mock.yml` | `docker-compose.ci.yml` |
| SSL/TLS        | HTTPS:8543                | HTTP:8000               |
| SSL証明書      | mkcert                    | 不要                    |
| Xdebug         | 設定可能                  | 無効                    |

---

## 必要なGitHub Secrets / Variables

### build-images.yml（Build and Push Docker Images）

自動提供のみ。手動設定不要。

- `GITHUB_TOKEN`（自動提供） — GitHub Container Registryへのログインに使用

### ci.yml（CI Test）

自動提供のみ。手動設定不要。

- `GITHUB_TOKEN`（自動提供） — プリビルドイメージのpullに使用

### pr-title-prefix.yml（PR Title Prefix）

自動提供のみ。手動設定不要。

- `github.token`（自動提供） — PRタイトルの更新に使用

### deploy-stg.yml（Deploy to Staging）

**Secrets:**

- `STG_SSH_PRIVATE_KEY` — ステージングサーバーへのSSH秘密鍵
- `STG_SSH_KNOWN_HOSTS` — ステージングサーバーのSSH known_hosts
- `STG_MYSQL_USER` — ステージングDBのMySQLユーザー名
- `STG_MYSQL_PASS` — ステージングDBのMySQLパスワード
- `TWITTER_API_KEY` — X (Twitter) API Key
- `TWITTER_API_SECRET` — X (Twitter) API Secret
- `TWITTER_ACCESS_TOKEN` — X (Twitter) Access Token
- `TWITTER_ACCESS_TOKEN_SECRET` — X (Twitter) Access Token Secret

**Variables:**

- `STG_SSH_PORT` — ステージングサーバーのSSHポート
- `STG_SSH_USER` — ステージングサーバーのSSHユーザー名
- `STG_SSH_HOST` — ステージングサーバーのホスト名/IP
- `STG_SSH_PATH` — ステージングサーバーのデプロイ先パス
- `STG_MYSQL_DB_COMMENT` — コメントDB名（日本語、例: `ocgraphdev_comment`）
- `STG_MYSQL_DB_COMMENT_TW` — コメントDB名（繁体字、例: `ocgraphdev_commenttw`）
- `STG_MYSQL_DB_COMMENT_TH` — コメントDB名（タイ語、例: `ocgraphdev_commentth`）

### deploy.yml（Deploy to Production）

**Secrets:**

- `SSH_PRIVATE_KEY` — 本番サーバーへのSSH秘密鍵
- `SSH_KNOWN_HOSTS` — 本番サーバーのSSH known_hosts
- `MYSQL_USER` — 本番DBのMySQLユーザー名
- `MYSQL_PASS` — 本番DBのMySQLパスワード
- `TWITTER_API_KEY` — X (Twitter) API Key（deploy-stg.ymlと共通）
- `TWITTER_API_SECRET` — X (Twitter) API Secret（deploy-stg.ymlと共通）
- `TWITTER_ACCESS_TOKEN` — X (Twitter) Access Token（deploy-stg.ymlと共通）
- `TWITTER_ACCESS_TOKEN_SECRET` — X (Twitter) Access Token Secret（deploy-stg.ymlと共通）

**Variables:**

- `SSH_PORT` — 本番サーバーのSSHポート
- `SSH_USER` — 本番サーバーのSSHユーザー名
- `SSH_HOST` — 本番サーバーのホスト名/IP
- `SSH_PATH` — 本番サーバーのデプロイ先パス
- `MYSQL_DB_COMMENT` — コメントDB名（日本語、例: `ocgraph_comment`）
- `MYSQL_DB_COMMENT_TW` — コメントDB名（繁体字、例: `ocgraph_commenttw`）
- `MYSQL_DB_COMMENT_TH` — コメントDB名（タイ語、例: `ocgraph_commentth`）

---

## 関連ファイル

### ワークフロー定義

- `.github/workflows/ci.yml`: CI設定
- `.github/workflows/post-pr-merge.yml`: PR通知設定

### テストスクリプト

- `.github/scripts/test-ci.sh`: クローリングテスト
- `.github/scripts/test-urls.sh`: URLテスト
- `.github/scripts/check-error-log.sh`: エラーログチェック

### Docker設定

- `docker-compose.yml`: 基本設定
- `docker-compose.ci.yml`: CI環境用オーバーライド
- `docker-compose.mock.yml`: ローカルMock環境用オーバーライド

### Apache設定

- `docker/app/apache2/sites-available/000-default-ci.conf`: CI用HTTP設定
- `docker/app/apache2/sites-available/000-default-ssl-disabled.conf`: SSL無効化用
