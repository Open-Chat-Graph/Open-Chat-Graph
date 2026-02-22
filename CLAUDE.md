# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

オプチャグラフ (OpenChat Graph) is a web application that tracks and displays growth trends for LINE OpenChat communities. It crawls the official LINE OpenChat site hourly to collect member statistics and displays rankings, search functionality, and growth analytics.

- **Live Site**: https://openchat-review.me
- **Language**: Primarily Japanese
- **License**: MIT

## 重要: 環境保護ルール

- `.env` の `DATA_PROTECTION=true` のときは本番データを使用した環境であり、mock環境（`make up-mock`、`make ci-test` 等）を使ってはいけない
- `DATA_PROTECTION=false` のときはテスト実行やmock環境の操作を自己判断で自由に行ってよい
- `DATA_PROTECTION=true` のときは `make ci-test`、`make up-mock` 等をユーザーの明示的な指示なしに実行してはいけない（環境が破壊される）。テスト実行もユーザーの指示がある場合のみ

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
```

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
- **Language**: PHP 8.3
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
Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/mimimiku778/Open-Chat-Graph)
```

## Frontend Components

### Separate Repositories

- Ranking pages: https://github.com/mimimiku778/Open-Chat-Graph-Frontend
- Graph display: https://github.com/mimimiku778/Open-Chat-Graph-Frontend-Stats-Graph
- Comments: https://github.com/mimimiku778/Open-Chat-Graph-Comments

### Integration

- React components embedded in PHP templates
- Pre-built JavaScript bundles (no build process in main repo)

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

**Skip CI Tests and Deployment:**
For urgent fixes or trivial changes (typos, documentation updates) that don't need testing or production deployment:

- Add `skip-ci` label to the PR
- Or prefix the PR title with `skip-ci:`

Example: `skip-ci: Fix typo in README`

**Important**: When `skip-ci` is used:

- CI tests are skipped
- Production deployment is completely skipped
- Changes are merged to main but NOT deployed to the live site

**Skip Social Media Post:**
To skip the automatic X (Twitter) post after merge (but still run CI and deploy):

- Add `skip-post` label to the PR
- Or prefix the PR title with `skip-post:`

Example: `skip-post: Internal configuration update`
