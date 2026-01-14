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

#### `make init` の動作

- SSL証明書を生成（mkcert）
- `local-secrets.php` が存在しない場合、自動的にセットアップスクリプトを実行
- MySQLコンテナが停止している場合、自動的に起動→セットアップ→停止
- **初期状態**（データベースとSQLiteファイルが存在しない）の場合、確認なしで自動実行

**引数を渡す:**
```bash
# 対話型（デフォルト）
make init

# 確認なしでDB・storage初期化、local-secrets.phpも作成
make init-y

# 確認なしでDB・storage初期化、local-secrets.phpは保持
make init-y-n
```

#### セットアップスクリプトの手動実行

既存の環境をリセットする場合や、カスタムセットアップが必要な場合：

```bash
# 対話型（既存データがある場合は確認プロンプト表示）
./setup/local-setup.default.sh

# DB・storage初期化を自動実行、local-secrets.phpも作成
./setup/local-setup.default.sh -y

# DB・storage初期化を自動実行、local-secrets.phpは保持
./setup/local-setup.default.sh -y -n

# ヘルプ表示
./setup/local-setup.default.sh -h
```

**引数の説明:**
- **第1引数**: DB・storage初期化（`-y`/`yes` = 自動実行、省略時 = 確認）
- **第2引数**: local-secrets.php上書き（省略時 = 作成、`-n`/`no` = 保持）

### 環境の種類

**基本環境（make up）:**
- 実際のLINEサーバーにアクセス（インターネット接続必要）

**Mock付き環境（make up-mock）:**
- LINE Mock APIを含む開発環境
- Docker Composeのサービス名（line-mock-api）でMock APIにアクセス
- インターネット接続不要
- データ件数・遅延・Cron自動実行を制御可能

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
- LINE Mock API: http://localhost:9000

**注意:**
- HTTPは自動的にHTTPSにリダイレクトされます
- 両環境でMySQLデータベースは共有されます

### Xdebugの有効化

デフォルトでは**Xdebugは無効**です。デバッグが必要な場合のみ有効化してください：

```bash
# 起動時に有効化
ENABLE_XDEBUG=1 make up
ENABLE_XDEBUG=1 make up-mock
```

**⚠️ 注意:** Xdebugはパフォーマンスを大幅に低下させます。デバッグ時のみ使用してください。

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
│   └── Crawler/    # クローラー関連（Config含む）
└── Views/          # テンプレート
shadow/             # MimimalCMSフレームワーク
batch/              # Cronジョブ・バッチ処理
shared/             # DI設定
storage/            # SQLite・ログ・キャッシュ
```

---

## 📞 連絡先

- Email: support@openchat-review.me
- X (Twitter): [@openchat_graph](https://x.com/openchat_graph)
