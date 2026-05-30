# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

オプチャグラフ (OpenChat Graph) is a web application that tracks and displays growth trends for LINE OpenChat communities. It crawls the official LINE OpenChat site hourly to collect member statistics and displays rankings, search functionality, and growth analytics.

- **Live Site**: https://openchat-review.me
- **Language**: Primarily Japanese
- **License**: MIT

## Development Best Practices

### Efficient Agent Usage

**CRITICAL: Use sub-agents to separate concerns and avoid context confusion**

#### When to Use Sub-Agents (Task Tool)

Use the Task tool to spawn sub-agents for:

1. **Parallel Independent Tasks**
   - Research/investigation while implementing
   - Documentation generation after implementation
   - Code review after completing features
   - Testing while fixing bugs

2. **Complex Multi-Step Operations**
   - Codebase exploration (use `subagent_type=Explore`)
   - Implementation planning (use `subagent_type=Plan`)
   - Thorough investigation requiring multiple file reads/searches

3. **Context Separation**
   - When switching between unrelated tasks
   - When deep investigation might pollute current context
   - When you need specialized knowledge (e.g., claude-code-guide agent)

#### Best Practices

**DO:**
- ✅ Keep main agent focused on current primary task
- ✅ Delegate auxiliary tasks to sub-agents immediately
- ✅ Run independent sub-agents in parallel (single message, multiple Task calls)
- ✅ Define clear, specific roles for each agent
- ✅ Use Explore agent for codebase understanding tasks
- ✅ Use Plan agent for breaking down complex features

**DON'T:**
- ❌ Mix multiple complex tasks in single agent
- ❌ Let main agent context bloat with exploratory searches
- ❌ Run sequential sub-agents when they could run parallel
- ❌ Use main agent for deep codebase exploration
- ❌ Start implementing before delegating to Plan agent

#### Example: Efficient Agent Workflow

```
User: "Add user profile page with avatar upload and bio editing"

Main Agent:
1. Immediately spawn Plan agent to break down the task
2. Wait for plan approval
3. Focus only on implementation of approved plan
4. Spawn Explore agent if need to understand existing patterns
5. After implementation, spawn code-reviewer agent

Sub-Agents (parallel):
- Plan agent: Creates implementation plan
- Explore agent: Investigates file upload patterns in codebase
- code-reviewer agent: Reviews completed implementation
```

#### Anti-Pattern: Context Confusion

```
❌ BAD:
User: "Add profile page with upload"
Main Agent:
- Searches for upload examples
- Reads 10 different files
- Gets confused about which pattern to use
- Implements half-following one pattern, half another
- Context is polluted with irrelevant code

✅ GOOD:
User: "Add profile page with upload"
Main Agent:
- Spawns Explore agent: "Find file upload implementation patterns"
- Explore agent returns: "Use the pattern from SettingsPage"
- Main agent implements cleanly with clear reference
- Context stays focused on implementation
```

### Task Planning and Execution

**IMPORTANT: Follow these steps for ALL non-trivial implementation tasks:**

1. **Use Plan Agent for Multi-Step Tasks**
   - When given multiple features or complex requirements, use `EnterPlanMode` or Task tool with `subagent_type=Plan`
   - Let the Plan agent break down the work and create a structured approach
   - Get user approval before starting implementation

2. **Commit Frequently with Meaningful Granularity**
   - **Never implement everything and commit once at the end**
   - Commit after each logical unit of work:
     - After installing new dependencies
     - After adding new UI components
     - After implementing each feature component
     - After integrating components
     - After fixing bugs or errors
   - Each commit should be independently reviewable
   - Use the commit-message skill for consistent formatting

3. **Test with Playwright After Implementation**
   - **Always test the implementation matches requirements**
   - Use Playwright browser automation to verify:
     - UI renders correctly (desktop and mobile if applicable)
     - User interactions work as expected
     - Features behave according to requirements
   - Take screenshots for visual confirmation
   - Fix any issues discovered during testing
   - Commit fixes separately

### Example Good Workflow

```
User: "Add folder management with drag-and-drop and mobile bottom nav"

1. Plan (if complex):
   - Use Plan agent or EnterPlanMode to break down tasks

2. Implementation with commits:
   - Install dependencies → Commit
   - Add UI components → Commit
   - Implement FolderDialog → Commit
   - Implement FolderTree → Commit
   - Update storage functions → Commit
   - Integrate in MyListPage → Commit
   - Add mobile bottom nav → Commit
   - Update responsive layout → Commit

3. Test:
   - Use Playwright to test folder creation
   - Test drag and drop functionality
   - Test mobile/desktop responsive behavior
   - Take screenshots to verify
   - Fix any issues → Commit fixes

4. Summary:
   - Report what was implemented
   - Show test results
```

### Example Bad Workflow (DON'T DO THIS)

```
User: "Add folder management with drag-and-drop and mobile bottom nav"

1. Implement everything at once
2. No commits during implementation
3. Test at the end (if at all)
4. One big commit with all changes ❌
```

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

### Parallel Processing
- Crawls ~150,000 OpenChats across 24 categories
- 24 parallel processes for simultaneous downloads
- Custom optimization for high-performance data updates

### Key Files
- `app/Services/OpenChat/OpenChatApiDbMergerWithParallelDownloader.php` - Parent process
- `app/Services/Cron/ParallelDownloadOpenChat.php` - Child process via exec
- `app/Services/OpenChat/OpenChatApiDataParallelDownloader.php` - Data processing

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

### Graph Component Build and Deploy

**IMPORTANT**: When modifying the graph component (`/home/user/oc-review-graph`), always rebuild and deploy:

```bash
# Build graph component and deploy to openchat-alpha
cd /home/user/oc-review-graph && \
npm run build && \
cp dist/assets/index-*.js /home/user/openchat-alpha/public/js/preact-chart/assets/index.js
```

**Key Points:**
- Graph component is built separately in `/home/user/oc-review-graph`
- Built bundle is deployed to `/home/user/openchat-alpha/public/js/preact-chart/assets/index.js`
- The bundle is served by Vite dev server from `public/` directory (no proxy)
- Always run this command after making changes to graph component files

### Preact Component Integration Pattern

When integrating external Preact components (like chart displays) into a React SPA, use cache busting to ensure proper remounting on navigation:

```typescript
const preactScriptRef = useRef<HTMLScriptElement | null>(null)

// Cleanup on unmount or ID change
useEffect(() => {
  return () => {
    if (preactScriptRef.current?.parentNode) {
      preactScriptRef.current.remove()
      preactScriptRef.current = null
    }
    const appDiv = document.getElementById('app')
    if (appDiv) {
      appDiv.innerHTML = ''
    }
  }
}, [id])

// Load script with cache busting
useEffect(() => {
  if (!data) return

  // Clear mount point
  const appDiv = document.getElementById('app')
  if (appDiv) {
    appDiv.innerHTML = ''
  }

  // Load Preact bundle with timestamp for cache busting
  setTimeout(() => {
    preactScriptRef.current = document.createElement('script')
    preactScriptRef.current.type = 'module'
    preactScriptRef.current.src = `/js/preact-chart/assets/index.js?t=${Date.now()}`
    document.body.appendChild(preactScriptRef.current)
  }, 50)
}, [data])
```

**Key Points:**
- Use `useRef` to track script elements, not global variables
- Clean up script elements and DOM on unmount
- Use timestamp query parameters (`?t=${Date.now()}`) for cache busting
- Browser ES module caching requires different URLs to re-execute code
- Avoid custom events for remounting - they don't work with cached modules

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