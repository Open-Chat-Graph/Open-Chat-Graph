# オプチャグラフ（OpenChat Graph）

LINE OpenChatのメンバー数推移を可視化し、トレンドを分析するWebサービス

**🌐 公式サイト**: https://openchat-review.me
**ライセンス**: MIT
**言語:** [日本語](README.md) | [English](README_EN.md)

---

## 🚀 開発環境のセットアップ

### 必要なツール

- Docker with Compose V2 (`docker compose` コマンド)
- mkcert（SSL証明書生成用）

### 初回セットアップ

```bash
# SSL証明書生成 + 初期設定
make init

# 基本環境を起動
make up
```

### 環境の種類

**基本環境（make up）:**
- 通常の開発環境
- 外部の実際のLINEサーバーにアクセス
- インターネット接続が必要

**Mock付き環境（make up-mock）:**
- LINE Mock APIを含む開発環境
- LINEドメイン（openchat.line.me等）をローカルのMock APIにリダイレクト
- インターネット接続不要でLINE APIをエミュレート
- データ件数・遅延・Cron自動実行を制御可能
- ルーム出現パターン: 60%通常/30%断続的/10%削除済み（クローリング対象判定テスト用）

### 利用可能なコマンド

**基本環境:**
```bash
make up           # 起動
make up-cron      # 起動（Cron自動実行モード）
make down         # 停止
make restart      # 再起動
make rebuild      # 再ビルドして起動
make ssh          # コンテナにログイン
```

**Mock付き環境:**
```bash
make up-mock            # 起動（1万件、遅延なし）
make up-mock-slow       # 起動（10万件、本番並み遅延）
make up-mock-cron       # 起動（1万件、Cron自動実行）
make up-mock-slow-cron  # 起動（10万件、遅延+Cron）
make down-mock          # 停止
make restart-mock       # 再起動
make rebuild-mock       # 再ビルドして起動
make ssh-mock           # コンテナにログイン
```

**その他:**
```bash
make show         # 現在の起動モード表示
make help         # 全コマンド表示
```

**Cron自動実行モード:**
- 毎時30分: 日本語クローリング
- 毎時35分: 繁体字中国語クローリング
- 毎時40分: タイ語クローリング

### アクセスURL

**基本環境（make up）:**
- HTTPS: https://localhost:8443
- phpMyAdmin: http://localhost:8080
- MySQL: localhost:3306

**Mock付き環境（make up-mock）:**
- HTTPS（基本）: https://localhost:8443
- HTTPS（Mock）: https://localhost:8543
- phpMyAdmin: http://localhost:8080
- MySQL: localhost:3306（共有）
- LINE Mock API: http://localhost:9000 ([実装](docker/line-mock-api/public/index.php))

**注意:**
- HTTPアクセスは自動的にHTTPSにリダイレクトされます
- SSL証明書は`mkcert`により自動生成されます
- 両環境でMySQLデータベースは共有されます

### Xdebugの有効化

デフォルトでは**Xdebugは無効**です。デバッグが必要な場合のみ、以下のいずれかの方法で有効化してください：

**方法1: 環境変数で起動時に有効化（推奨）**

```bash
# 基本環境
ENABLE_XDEBUG=1 make up

# Mock環境
ENABLE_XDEBUG=1 make up-mock

# または再起動時
ENABLE_XDEBUG=1 make restart
ENABLE_XDEBUG=1 make restart-mock
```

**方法2: docker-compose.ymlを編集して常に有効化**

`docker-compose.yml`の`app`サービスの`environment`セクションに追加：

```yaml
services:
  app:
    environment:
      - ENABLE_XDEBUG=1  # この行を追加
```

その後、コンテナを再起動：

```bash
make restart      # 基本環境
make restart-mock # Mock環境
```

**Xdebug設定:**
- モード: debug
- クライアントホスト: host.docker.internal
- ポート: 9003

**⚠️ 注意:** Xdebugを有効化するとパフォーマンスが大幅に低下します。デバッグ時のみ使用してください。

---

## 🏗️ 技術スタック

- PHP 8.3 + [MimimalCMS](https://github.com/mimimiku778/MimimalCMS)（自作MVCフレームワーク）
- MySQL/MariaDB + SQLite
- React + TypeScript（事前ビルド済み）
- 外部リポジトリ: [ランキング](https://github.com/mimimiku778/Open-Chat-Graph-Frontend) / [グラフ](https://github.com/mimimiku778/Open-Chat-Graph-Frontend-Stats-Graph) / [コメント](https://github.com/mimimiku778/Open-Chat-Graph-Comments)

## 📁 ディレクトリ構造

```
app/
├── Config/         # ルーティング・設定
├── Controllers/    # HTTPハンドラー
├── Models/         # リポジトリ・DTO
├── Services/       # ビジネスロジック
└── Views/          # テンプレート
shadow/             # MimimalCMSフレームワーク
batch/              # Cronジョブ・バッチ処理
shared/             # DI設定
storage/            # SQLite・ログ・キャッシュ
setup/              # データベーススキーマ・初期化スクリプト
public/             # Webルート
docker/             # Docker設定
```

---

## 📞 連絡先

- Email: support@openchat-review.me
- X (Twitter): [@openchat_graph](https://x.com/openchat_graph)
