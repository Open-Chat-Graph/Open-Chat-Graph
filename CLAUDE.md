# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

オプチャグラフ (OpenChat Graph) is a web application that tracks and displays growth trends for LINE OpenChat communities. It crawls the official LINE OpenChat site hourly to collect member statistics and displays rankings, search functionality, and growth analytics.

- **Live Site**: https://openchat-review.me
- **Language**: Primarily Japanese
- **License**: MIT

## 重要: 環境保護ルール

- `.env` の `DATA_PROTECTION=true` のときは本番データを使用した環境。**`make up-mock` / `make ci-test` 等の mock環境操作はユーザーの明示的な指示なしに実行してはいけない**（本番DBが破壊される）
- ただし禁止対象は「本番DBを壊す mock環境操作」だけ。**phpunit（`docker compose exec app vendor/bin/phpunit <path>`）や curl でローカル環境を叩く類のテストは普通に実行してよい**（「テスト全般がNG」ではない）
- `DATA_PROTECTION=false` のときはテスト実行も mock環境の操作も自己判断で自由に行ってよい

## Development Environment

### Docker Setup

**IMPORTANT**: Use `docker compose` (with space), not `docker-compose` (with hyphen).

This project uses Makefile for easy Docker management:

```bash
# Initial setup
make init

# Basic environment (accesses real LINE servers)
make up / down / restart / rebuild / ssh

# Mock environment (includes LINE Mock API)
make up-mock / down-mock / restart-mock / rebuild-mock / ssh-mock
make up-mock-slow       # 100k items with production-like delay
make up-mock-cron       # Auto-crawling enabled

# Show current configuration
make show / help

# Shared mode (share another repo's DB/storage; run this repo's code as a 2nd instance)
make up-shared / shared-setup / down-shared
```

### Shared Mode（共有モード）

別ディレクトリにある既存リポジトリの **MariaDB / storage / comment-img を実体共有**しつつ、コードはこのリポジトリで動かす2つ目のインスタンス。

- `make up-shared` … 初回は参照先リポジトリのパスを対話で尋ね `.shared.local.mk`（gitignore済・環境ごとに異なる）に保存。ポートが参照先と衝突する場合はプリセット（9000/9443 等）から選択して `.env` に保存。2回目以降は尋ねない。
- 参照先の docker ネットワークに app を直結し、storage/comment-img を bind mount する（`docker-compose.shared.yml`）。`translation.json` だけはこのリポジトリ側を使う。
- `DATA_PROTECTION=true` で動く（実データ共有のため）。詳細は [`README.shared.md`](README.shared.md)。

### Environment Details

**Basic Environment:**

- HTTPS: https://localhost:8443
- MySQL: localhost:3306
- phpMyAdmin: http://localhost:8080
- Accesses external LINE servers

**Mock Environment:**

- HTTPS (Basic): https://localhost:8443
- HTTPS (Mock): https://localhost:8543
- LINE Mock API: http://localhost:9000 (external access), http://line-mock-api (internal)
- MySQL: localhost:3306 (shared)
- phpMyAdmin: http://localhost:8080

**Mock API Features:**

- Uses Docker Compose service name (`line-mock-api`) for internal communication
- Configurable data counts (MOCK_RANKING_COUNT, MOCK_RISING_COUNT)
- Production-like delay simulation (MOCK_DELAY_ENABLED)
- Multi-language support (Japanese, Traditional Chinese, Thai)

**Cron Auto-Execution (CRON=1):**

- 30 min: Japanese crawling
- 35 min: Traditional Chinese (/tw)
- 40 min: Thai (/th)

### Requirements

- Docker with Compose V2
- `mkcert` for SSL certificate generation (not required for CI)

### GitHub Codespaces Environment

**Codespaces環境はローカル開発環境と完全に同じ:**

- 独立したUbuntuコンテナ内でDocker環境を起動
- ローカルと同じMakefileコマンドが使用可能
- `make ci-test`などの全てのスクリプトが正常に動作

**セットアップ:**

1. Codespacesが起動すると自動的に`make init-y`が実行される
2. `make up-mock`でMock環境を起動
3. ポート転送タブから各サービスにアクセス

**構成:**

- `.devcontainer/Dockerfile`: Ubuntu + Docker + mkcert
- `.devcontainer/devcontainer.json`: シンプルなdevcontainer設定
- `.devcontainer/post-create-command.sh`: 初期セットアップスクリプト

### CI Environment

**CI環境では専用の設定を使用:**

- `docker-compose.ci.yml`: CI専用のオーバーライド設定
- SSL証明書生成をスキップ（HTTP通信のみ）
- Xdebugインストール無効
- PHPMyAdmin除外

**GitHub Actions:**

- `.github/workflows/ci.yml`: 自動テスト実行
- `.github/workflows/build-images.yml`: プリビルドイメージのビルド＆プッシュ（main pushまたは手動実行）
- プリビルドイメージ: GitHub Container Registry (ghcr.io) にCI用イメージを保存
  - `ghcr.io/{owner}/oc-review-mock-app:latest`: アプリケーションイメージ
  - `ghcr.io/{owner}/oc-review-mock-line-mock-api:latest`: LINE Mock APIイメージ
- CI実行時の動作:
  - Dockerfile/composer関連ファイルに変更がある場合: 必ずビルド（最新の変更を反映）
  - 変更がない場合: プリビルドイメージをpull（高速化）
  - プリビルドイメージが存在しない場合: ビルド（フォールバック）
- Docker Layer Caching: `docker/build-push-action@v6`でGitHub Actionsキャッシュを使用
- `cache-from/cache-to type=gha,scope={app|line-mock-api}`: 各イメージに一意のscopeを設定
- プリビルドイメージ使用でビルド時間を大幅短縮（34秒 → 5-10秒）

**ローカルでCIテストを実行:**

```bash
make ci-test
```

## Architecture

### Backend

- **Framework**: Custom MimimalCMS (lightweight MVC framework)
- **Language**: PHP 8.5
- **Database**: MySQL/MariaDB for main data, SQLite for rankings/statistics
- **Pattern**: Traditional MVC with dependency injection

### Frontend

- Server-side PHP templating + embedded React components
- TypeScript, JavaScript, React
- Libraries: MUI, Chart.js, Swiper.js

### Key Directories

- `/app/` - Main application (MVC structure)
  - `Config/` - Routing and application config
  - `Controllers/` - HTTP handlers (Api/ and Page/)
  - `Models/` - Data access layer with repositories
  - `Services/` - Business logic
    - `Crawler/Config/` - Crawler configuration (OpenChatCrawlerConfig)
  - `ServiceProvider/` - Service provider for DI
  - `Views/` - Templates and React components
- `/shadow/` - Custom MimimalCMS framework
- `/batch/` - Background processing, cron jobs
- `/shared/` - Framework configuration and DI mappings
- `/storage/` - Multi-language data files, SQLite databases

## Database Architecture

### MySQL/MariaDB

- Primary storage for OpenChat data
- Complex queries using raw SQL (no ORM)

### スキーマ変更（テーブル・カラム追加）

テーブルやカラムを追加したいときは **`setup/schema/mysql/*.sql` を編集するだけ**。デプロイ時に
`batch/exec/sync_mysql_schema.php` が各DBへ不足分を「追加だけ」自動反映する（既存データは壊さない。
削除・型変更はしない）。`deploy.yml` もコードも触らなくてよい。

- 反映前に確認: `docker compose exec app php batch/exec/sync_mysql_schema.php --dry-run`
- 詳細・注意点: [`app/Services/Schema/README.md`](app/Services/Schema/README.md)

### SQLite

- Rankings and statistics data
- Performance optimization for read-heavy operations
- Separate databases per data type in `/storage/`

### Database Access in Controllers

```php
use Shadow\DB;

DB::connect(); // Always connect first

// SELECT multiple rows
$stmt = DB::$pdo->prepare("SELECT * FROM table WHERE condition = ?");
$stmt->execute([$value]);
$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// SELECT single row
$stmt = DB::$pdo->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(\PDO::FETCH_ASSOC);
```

Note: Database configuration is loaded from `local-secrets.php`

## Development Patterns

### Dependency Injection

- Interface-based DI configured in `/shared/MimimalCmsConfig.php`
- Service providers in `/app/ServiceProvider/` for dynamic binding
- Example: `OpenChatCrawlerConfigServiceProvider` switches between production and mock configs based on `AppConfig::$isMockEnvironment`

### Autoloading

```php
"psr-4": {
    "Shadow\\": "shadow/",
    "App\\": "app/",
    "Shared\\": "shared/"
}
```

### Configuration

- Environment-specific config in `local-secrets.php` (gitignored)
- Framework config in `/shared/MimimalCMS_*.php` files
- OpenChatCrawlerConfig in `/app/Services/Crawler/Config/`

## Crawling System

### Configuration Classes

- `OpenChatCrawlerConfig` - Production environment (uses real LINE URLs)
- `MockOpenChatCrawlerConfig` - Mock environment (uses `line-mock-api` service)
- Service provider automatically switches based on `AppConfig::$isMockEnvironment`

### User Agent

```
Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/Open-Chat-Graph/Open-Chat-Graph)
```

## Frontend Components

React ソースは**このリポ内 `frontend/`**（別リポではない）。各サブdirが Vite+TS: `ranking`(ランキング) / `oc-app`(個別ルーム) / `all-room-stats` / `stats-graph` / `comments`。

- **ビルド**: `cd frontend/<name> && npm run build` → `public/js/...` にハッシュ付きバンドル出力（成果物は **gitignore**、コミット不要）。手ビルドはローカル確認用。
- **PHP参照**: `getFilePath('js/react','main-*.js')`（内部 glob、`app/Helpers/functions.php`）でハッシュ名を解決 → HTML側の手修正は不要。
- **翻訳**: 各 `frontend/<name>/src/config/translation.ts` は PHP の `storage/translation.json` と**別物**。両方直す。
- **デプロイ**: `deploy.yml` が `frontend/<name>/**` の変更を検知し自動で `npm ci && npm run build` → 配信。

## Creating New Pages (MVC Pattern)

### 1. Add Route

In `/app/Config/routing.php`:

```php
Route::path('your-path', [\App\Controllers\Pages\YourController::class, 'method']);
```

### 2. Create Controller

Controllers go in `/app/Controllers/Pages/`:

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Pages;

use Shadow\Kernel\Reception;
use App\Models\Repositories\DB;

class YourController
{
    public function index(Reception $reception)
    {
        DB::connect();
        $stmt = DB::$pdo->prepare("SELECT ...");
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $_meta = meta();
        $_meta->title = 'Page Title';

        return view('view_name', ['data' => $data, '_meta' => $_meta]);
    }
}
```

### 3. Create View

Views go in `/app/Views/`:

- Use `.php` extension
- Access variables directly: `$data`, `$_meta`
- Helper functions: `url()`, `t()`, `fileUrl()`

## Pull Request Guidelines

### Signing GitHub Posts（必須）

GitHubに投稿する本文（PR本文・issue・PR/issueコメント等）の**末尾**に、区切り線を入れて以下の署名ブロックを付ける。どのマシン・ディレクトリから、どのモデル・ツールで投稿されたかを残すため。

```markdown
---
🤖 Generated with Claude Code (<モデル表示名> / `<モデルID>`)
Posted from: `<hostname>:<作業ディレクトリ>`
```

- モデルはその時のセッションの実際のモデルを書く（例: Opus 4.8 → `Generated with Claude Code (Opus 4.8 / `claude-opus-4-8[1m]`)`、Sonnet 4.6 のときは Sonnet 4.6）
- `<hostname>` は `hostname`、`<作業ディレクトリ>` は `pwd` の値（例: `user-B550M-Pro4:/home/user/repos/Open-Chat-Graph`）
- これは本文末尾の表示用ブロック。コミットメッセージ末尾の `Co-Authored-By: Claude ...` とは別物（コミットは従来どおり Co-Authored-By を付ける）

### Writing Clear Titles

**IMPORTANT**: PR titles appear on social media and should be understandable by the general public.

**❌ BAD:**

```
perf: dailyTask処理時間の大幅短縮とタイムアウト問題の解決
fix: getMemberChangeWithinLastWeekCacheArray()の重複実行を防止
```

**✅ GOOD:**

```
perf: 日次データ更新処理のタイムアウト問題を解決（9〜11時間→1〜2時間）
fix: 統計データ抽出クエリの重複実行を防止してDB負荷を軽減
```

**Guidelines:**

- Avoid code terminology (class/method/variable names)
- Include concrete numbers (processing time, data volume)
- Explain business impact, not technical details

### Writing Clear Descriptions

**Structure:**

1. Start with business/user impact
2. Explain technical problem in plain language
3. Link to specific code locations
4. Provide implementation details

**Example:**

```markdown
## 問題の概要

オープンチャットの日次データ更新処理が9〜11時間かかり完了しない

### 具体的な問題

全statisticsテーブル（8700万行）から「メンバー数が変動している部屋」を抽出する処理が、
以下の2箇所で重複実行されている:

- クローリング対象の絞り込み処理 ([`DailyUpdateCronService::getTargetOpenChatIdArray()`](link))
- ランキング用キャッシュ保存処理 ([`UpdateHourlyMemberRankingService::saveFiltersCacheAfterDailyTask()`](link))

## 対処内容

クエリ結果をプロパティに保存し、2回目で再利用
```

### Common Terms Translation

- dailyTask → オープンチャットの日次データ更新処理（毎日23:30実行）
- hourlyTask → オープンチャットの毎時ランキング更新処理（毎時30分実行）
- getMemberChangeWithinLastWeekCacheArray → 統計データ抽出処理（メンバー数が変動している部屋を取得）

### Bypassing CI/CD

**Skip CI Tests:**
CI Test (`ci.yml`) は Mock環境のクローリング+URLテストのみで、**phpunit は実行しない**。
これらが意味を持たない変更（typo・ドキュメント・デプロイ時にだけ動くロジック等）では CI を飛ばせる:

- Add `skip-ci` label to the PR
- Or prefix the PR title with `skip-ci:`

Example: `skip-ci: Fix typo in README`

**Important**: When `skip-ci` is used:

- CI tests (`ci.yml` の Mock クローリング+URLテスト) are skipped
- `deploy.yml` の「Check CI status」ゲートも飛ばされる（CI 成功を要求しなくなる）
- **デプロイは止まらない**。PR がマージされれば deploy job は通常どおり走り、stg/本番へ反映される
  （deploy job の発火条件はマージ/手動実行だけで、skip-ci はデプロイを止めない）
- 補足: `stg` を head にした PR は skip-ci 無しでも CI が自動スキップされる（`ci.yml`）

**Skip Social Media Post:**
To skip the automatic X (Twitter) post after merge (but still run CI and deploy):

- Add `skip-post` label to the PR
- Or prefix the PR title with `skip-post:`

Example: `skip-post: Internal configuration update`
