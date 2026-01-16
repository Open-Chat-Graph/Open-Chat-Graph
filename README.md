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

**起動時に ERROR: No such service: build が出た場合**
以下のコマンドで再ビルド
`docker compose -f docker-compose.yml -f docker-compose.mock.yml --verbose build --no-cache`

#### セットアップスクリプトの手動実行

既存の環境をリセットする場合：`./setup/local-setup.default.sh` (オプション: `-y` 確認なし、`-n` local-secrets.php保持、`-h` ヘルプ)

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
make up        # 起動
make down      # 停止
make restart   # 再起動（基本・Mock自動判定）
make rebuild   # 再ビルドして起動（基本・Mock自動判定）
make ssh       # コンテナにログイン（基本・Mock両対応）
```

**Mock付き環境:**
```bash
make up-mock      # 起動（docker/line-mock-api/.env.mockの設定を使用）
make cron         # Cron有効化（毎時30/35/40分に自動クローリング）
make cron-stop    # Cron無効化
```

**テスト・CI:**
```bash
make ci-test   # CI環境でテストを実行（ローカル専用）
```

**その他:**
```bash
make show      # 現在の起動モード・設定表示
make help      # 全コマンド表示
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

MySQLコマンド例: `docker exec oc-review-mock-mysql-1 mysql -uroot -ptest_root_pass -e "SELECT 1"`

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

### CI環境

**GitHub Actionsで自動テストを実行:**
- `.github/workflows/ci.yml`: PRマージ前に自動実行
- `docker-compose.ci.yml`: CI専用設定（SSL無効、Xdebug無効）
- Docker Layer Caching: 2回目以降のビルドを高速化

**ローカルでCIテストを実行:**
```bash
make ci-test
```

### テストスクリプト

Mock環境で時刻を進めながらクローリングをテスト：

```bash
# CI用（高速・効率的）
./test-ci.sh
# - 固定データ（80件/カテゴリ）、遅延なし
# - 日常的なテスト・CI環境用
# - クローリング完了後、自動的にデータ検証を実行

# デバッグ用（本番環境に近い設定）
./test-cron.sh
# - 大量データ（10万件）、本番並み遅延
# - 48時間テストに対応、本番環境の挙動を再現
```

**実行回数設定:** `docker/line-mock-api/.env.mock` で `TEST_JA_HOURS`（日本語）、`TEST_TW_HOURS`（繁体字）、`TEST_TH_HOURS`（タイ語）を変更

**データ検証:** `./.github/scripts/verify-test-data.sh` で以下を確認
- MySQLテーブルのレコード数:
  - `ocgraph_comment.open_chat`: 2000件以上
  - `ocgraph_ocreviewth.open_chat`: 1000件以上
  - `ocgraph_ocreviewtw.open_chat`: 1000件以上
  - `ocgraph_ocreview.statistics_ranking_hour`: 10件以上
  - `ocgraph_ocreview.statistics_ranking_hour24`: 10件以上
  - `ocgraph_ocreview.user_log`: 0件
  - `ocgraph_graph.recommend`: 500件以上
- 画像ファイルの生成:
  - `public/oc-img/0`: .webp画像10件以上
  - `public/oc-img/preview/0`: .webp画像10件以上

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
