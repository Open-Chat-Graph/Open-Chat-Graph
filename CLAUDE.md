# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

オプチャグラフ (OpenChat Graph) is a web application that tracks and displays growth trends for LINE OpenChat communities. It crawls the official LINE OpenChat site hourly to collect member statistics and displays rankings, search functionality, and growth analytics.

- **Live Site**: https://openchat-review.me
- **Language**: Primarily Japanese
- **License**: MIT

## Development Environment

### Docker Setup
```bash
# Start development environment (PHP 8.3 + MySQL + phpMyAdmin)
docker-compose up

# Default ports:
# - Web: http://localhost:8000
# - MySQL: localhost:3306
# - phpMyAdmin: http://localhost:8080
```

### Initial Setup
```bash
# Install PHP dependencies and setup local config
composer install
./local-setup.sh
```

## Architecture

### Backend
- **Framework**: Custom MimimalCMS (lightweight MVC framework by project author)
- **Language**: PHP 8.3
- **Database**: MySQL/MariaDB for main data, SQLite for rankings/statistics
- **Architecture**: Traditional MVC with dependency injection

### Frontend
- **Hybrid approach**: Server-side PHP templating + embedded React components
- **Languages**: TypeScript, JavaScript, React
- **Libraries**: MUI, Chart.js, Swiper.js

### Key Directories
- `/app/` - Main application (MVC structure)
  - `Config/` - Routing and application config
  - `Controllers/` - HTTP handlers (Api/ and Page/ subdirs)
  - `Models/` - Data access layer with repositories
  - `Services/` - Business logic
  - `Views/` - Templates and React components
- `/shadow/` - Custom MimimalCMS framework
- `/batch/` - Background processing, cron jobs
  - `cron/` - Scheduled tasks
  - `exec/` - CLI executables  
  - `sh/` - Shell scripts for deployment/backup
- `/shared/` - Framework configuration and DI mappings
- `/storage/` - Multi-language data files, SQLite databases

## Database Architecture

### MySQL/MariaDB
- Primary storage for OpenChat data
- Complex queries using raw SQL (no ORM)
- Foreign key relationships with manual optimization

### SQLite
- Rankings and statistics data
- Performance optimization for read-heavy operations
- Separate databases per data type in `/storage/`

### Key Patterns
- Repository pattern with interfaces
- Dependency injection via `/shared/MimimalCmsConfig.php`
- Raw SQL for complex queries and performance

### Database Access in Controllers

When you need to access the database in controllers, use the `Shadow\DB` class:

```php
use Shadow\DB;

// In your controller method:
DB::connect(); // Always connect first

// For SELECT queries that return multiple rows:
$stmt = DB::$pdo->prepare("SELECT * FROM table WHERE condition = ?");
$stmt->execute([$value]);
$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// For SELECT queries that return a single row:
$stmt = DB::$pdo->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(\PDO::FETCH_ASSOC);

// For INSERT/UPDATE/DELETE:
$stmt = DB::$pdo->prepare("INSERT INTO table (column1, column2) VALUES (?, ?)");
$stmt->execute([$value1, $value2]);
```

Note: The database configuration is automatically loaded from `local-secrets.php` for development environment.

## Code Quality

### Current Tools
- **PHPUnit 9.6**: Testing framework
- **EditorConfig**: Basic formatting rules
- **PSR-4**: Autoloading standard

### Missing Tools
No linting, static analysis, or code formatting tools are currently configured.

## Crawling System

### User Agent
```
Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/pika-0203/Open-Chat-Graph)
```

## Development Patterns

### Dependency Injection
- Interface-based DI configured in `/shared/MimimalCmsConfig.php`
- Repository pattern with concrete implementations
- SQLite vs MySQL implementations via interface switching

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
- Database credentials in Docker environment variables

## Frontend Components

### Separate Repositories
- Ranking pages: https://github.com/mimimiku778/Open-Chat-Graph-Frontend
- Graph display: https://github.com/mimimiku778/Open-Chat-Graph-Frontend-Stats-Graph  
- Comments: https://github.com/mimimiku778/Open-Chat-Graph-Comments

### Integration
- React components embedded in PHP templates
- Pre-built JavaScript bundles (no build process in main repo)
- Client-side rendering for interactive features

## Deployment

### Scripts Location
- `/batch/sh/` - Deployment and backup scripts
- Database dumps and sync utilities
- Server-specific deployment automation

### Multi-language Support
- Japanese (primary), Thai, Traditional Chinese
- Separate data directories per language
- Internationalized content in `/storage/` subdirectories

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
        // Database access if needed
        DB::connect();
        $stmt = DB::$pdo->prepare("SELECT ...");
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Set meta data
        $_meta = meta();
        $_meta->title = 'Page Title';
        $_meta->description = 'Page description';
        
        // Return view
        return view('view_name', [
            'data' => $data,
            '_meta' => $_meta,
        ]);
    }
}
```

### 3. Create View
Views go in `/app/Views/`:
- Use `.php` extension
- Access variables directly (e.g., `$data`, `$_meta`)
- Use `viewComponent()` for reusable components
- Use `url()` for generating URLs
- Use `t()` for translations
- Use `fileUrl()` for asset URLs

### Important Notes
- Controllers don't use return type hints for view-returning methods
- Use `App\Models\Repositories\DB` (not `Shadow\DB`) for database access
- The DB class automatically selects the correct database based on `MimimalCmsConfig::$urlRoot`
- Meta data is accessed via `meta()` helper function, not a service class
- Views are returned directly with `view()`, not wrapped in Response object

## Pull Request Guidelines

When creating pull requests, follow these guidelines to ensure clarity for reviewers who may not be familiar with the codebase:

### Writing Clear Titles

**IMPORTANT**: PR titles appear on social media (X/Twitter timeline) and should be understandable by the general public.

**❌ BAD - Using code terminology:**
```
perf: dailyTask処理時間の大幅短縮とタイムアウト問題の解決
fix: getMemberChangeWithinLastWeekCacheArray()の重複実行を防止
```

**✅ GOOD - Explaining impact in plain language:**
```
perf: 日次データ更新処理のタイムアウト問題を解決（9〜11時間→1〜2時間）
perf: オープンチャットランキング更新の処理時間を大幅短縮
fix: 統計データ抽出クエリの重複実行を防止してDB負荷を軽減
```

**Title Guidelines:**
- Avoid code terminology (class names, method names, variable names)
- Include concrete numbers when possible (processing time, data volume)
- Explain the business impact, not the technical change
- Keep it concise but informative (50-80 characters ideal)

### Writing Clear Descriptions

**❌ BAD - Using code terminology directly:**
```markdown
## 問題
dailyTaskのタイムアウト
getMemberChangeWithinLastWeekCacheArray()が2回実行される
```

**✅ GOOD - Explaining business logic first, then linking to code:**
```markdown
## 問題
### オープンチャットの日次データ更新処理のタイムアウト
毎日23:30に実行される全データ更新処理が9〜11時間かかり完了しない問題。

### 統計データ抽出クエリの重複実行
全statisticsテーブル（8700万行）から「メンバー数が変動している部屋」を抽出する処理が、
以下の2箇所で重複実行されている:
- クローリング対象の絞り込み処理 ([`DailyUpdateCronService::getTargetOpenChatIdArray()`](link))
- ランキング用キャッシュ保存処理 ([`UpdateHourlyMemberRankingService::saveFiltersCacheAfterDailyTask()`](link))
```

### Key Principles

1. **Avoid code terminology in titles and summaries**
   - ❌ "dailyTask", "hourlyTask", "getMemberChangeWithinLastWeekCacheArray"
   - ✅ "オープンチャットの日次データ更新処理", "統計データ抽出処理"

2. **Explain "what" before "where"**
   - First: Describe what the code does in business/user terms
   - Then: Link to the actual code with class/method names in the link text

3. **Provide context for technical terms**
   - When using method names, explain their purpose first
   - Example: "統計データ抽出処理 ([`SqliteStatisticsRepository::getMemberChangeWithinLastWeekCacheArray()`](link))"

4. **Structure information hierarchically**
   - Start with business impact
   - Explain the technical problem
   - Link to specific code locations
   - Provide implementation details

5. **Balance abstraction and concrete details**
   - **Abstraction (ビジネスロジック)**: Explain what the system does and why it matters
   - **Concrete (具体的コード)**: Show how it's implemented with code references
   - **Both are necessary**: Start with abstraction, then provide concrete technical details
   - Example flow:
     1. Business problem: "オープンチャットの日次データ更新処理が9時間かかる"
     2. Technical cause: "全statisticsテーブル（8700万行）をスキャンする処理を2回実行"
     3. Code location: [`DailyUpdateCronService::getTargetOpenChatIdArray()`](link)
     4. Implementation details: "クエリ結果をプロパティに保存し、2回目で再利用"

6. **Separate problem and solution clearly**
   - **Problem section**: Link to code BEFORE the fix (main branch or earlier commit)
   - **Solution section**: Link to code AFTER the fix (current commit)
   - **Why**: Allows reviewers to compare before/after and understand the change
   - Example:
     - ❌ Problem section linking to fixed code: "Problem: duplicate queries ([fixed code link])"
     - ✅ Problem section linking to old code: "Problem: duplicate queries ([old code link])"
     - ✅ Solution section linking to new code: "Solution: reuse query results ([new code link])"

7. **Explain actual situation from logs**
   - **Add timeline section** after problem overview
   - **Show real timestamps** from cron logs to visualize the problem
   - **Explain for third parties** who are not familiar with the codebase
   - **Include context**: What the system does, what data it processes, why it matters
   - Example structure:
     ```markdown
     ### ログから見る実際の状況

     #### 日次データ更新処理のタイムライン（典型的な実行例）

     [Context explanation for third parties]

     ```
     23:30  【開始】日次データ更新処理
     23:35  ├─ 統計データ抽出（1回目）
            │  └─ [What this does and why]
     ...
     ```

     **問題**: [Specific problem identified from logs]
     ```

### Example PR Structure

```markdown
## 問題の概要
[High-level business problem in plain Japanese]

### 具体的な問題
1. **[User-facing issue]**
   - 説明: [What users experience]
   - 原因: [Technical cause in plain language]
   - 該当コード: [`ClassName::methodName()`](link)

## 対処内容
### 1. [Change title in plain language]
**変更内容**: [What was changed in business terms]
**実装詳細**: [Technical details]
**該当ファイル**: [Links to code]
**効果**: [Expected impact]
```

### Common Terms Translation

Use these translations when writing PRs:
- dailyTask → オープンチャットの日次データ更新処理（毎日23:30実行）
- hourlyTask → オープンチャットの毎時ランキング更新処理（毎時30分実行）
- getMemberChangeWithinLastWeekCacheArray → 統計データ抽出処理（メンバー数が変動している部屋を取得）
- saveFiltersCacheAfterDailyTask → ランキング用フィルターキャッシュ保存処理
- OpenChatDailyCrawling → オープンチャットデータのクローリング処理