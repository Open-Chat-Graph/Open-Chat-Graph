# OpenChat Graph

A web service for visualizing LINE OpenChat membership trends and analyzing growth patterns

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Live](https://img.shields.io/badge/Live-openchat--review.me-green)](https://openchat-review.me)

![OpenChat Graph](/public/assets/image.jpg)

**Languages:** [English](README_EN.md) | [日本語](README.md)

## Overview

OpenChat Graph is a web application that tracks and analyzes growth trends for LINE OpenChat communities. It crawls over 150,000 OpenChats hourly, providing membership statistics, rankings, and growth analytics.

### Key Features

- 📊 **Growth Trend Visualization** - Display membership progression with interactive charts
- 🔍 **Advanced Search** - Search by keywords, tags, and categories
- 📈 **Real-time Rankings** - 1-hour/24-hour/weekly growth rankings
- 🌏 **Multi-language Support** - Japanese, Thai, Traditional Chinese
- 💬 **Comment System** - User discussions and information sharing
- 🏷️ **Recommendation Tags** - AI-powered related tag generation

## 🚀 Development Setup

### Prerequisites

- Docker & Docker Compose
- PHP 8.3+
- Composer
- Node.js 18+ (for frontend development)

### Quick Start

```bash
# Clone the repository
git clone https://github.com/pika-0203/Open-Chat-Graph.git
cd Open-Chat-Graph

# Install dependencies
composer install

# Local setup (requires sensitive configuration)
# ⚠️ Contact us via GitHub Issues for access to required secrets
./local-setup.sh

# Start Docker environment
docker-compose up -d
```

**Access URLs:**
- Web: http://localhost:8000
- phpMyAdmin: http://localhost:8080
- MySQL: localhost:3306

## 🏗️ Architecture

### Technology Stack

#### Backend
- **Framework**: [MimimalCMS](https://github.com/mimimiku778/MimimalCMS) (Custom lightweight MVC)
- **Language**: PHP 8.3
- **Database**: 
  - MySQL/MariaDB (main data)
  - SQLite (rankings & statistics)
- **Dependency Injection**: Custom DI container

#### Frontend
- **Languages**: TypeScript, JavaScript
- **Framework**: React (hybrid with server-side PHP)
- **UI Libraries**: MUI, Chart.js, Swiper.js
- **Build**: Pre-built bundles

### Database Design

For detailed database schema, see [db_schema.md](./db_schema.md).

### Directory Structure

```
/
├── app/                    # Application code (MVC)
│   ├── Config/            # Routing & configuration
│   ├── Controllers/       # HTTP handlers
│   ├── Models/           # Data access layer
│   ├── Services/         # Business logic
│   └── Views/            # Templates & React
├── shadow/                # MimimalCMS framework
├── batch/                 # Batch processing & cron jobs
├── shared/               # Shared config & DI definitions
├── storage/              # Data files & SQLite DBs
└── public/               # Public directory
```

## 🕷️ Crawling System


### User Agent

```
Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/pika-0203/Open-Chat-Graph)
```

## 💻 Implementation Details

### MVC Architecture

#### Model Layer: Repository Pattern

Interface-driven design ensures testability and maintainability:

```php
interface OpenChatRepositoryInterface
{
    public function addOpenChatFromDto(OpenChatDto $dto): int|false;
    public function getOpenChatIdAll(): array;
}

class OpenChatRepository implements OpenChatRepositoryInterface
{
    public function addOpenChatFromDto(OpenChatDto $dto): int|false
    {
        // High-performance INSERT with raw SQL
        $dto->registered_open_chat_id = DB::executeAndGetLastInsertId(
            "INSERT IGNORE INTO open_chat (...) VALUES (...)",
            [...] // Type-safe bound values
        );
        
        // Sync statistics data to SQLite
        $this->statisticsRepository->addNewOpenChatStatisticsFromDto($dto);
        
        return $dto->registered_open_chat_id;
    }
}
```

**Features:**
- Raw SQL for complex queries and high performance
- MySQL + SQLite hybrid configuration
- Type safety through DTO pattern

#### Controller Layer: Dependency Injection

```php
class IndexPageController
{
    function index(
        StaticDataFile $staticDataGeneration,
        RecentCommentListRepositoryInterface $recentCommentListRepository,
        PageBreadcrumbsListSchema $pageBreadcrumbsListSchema,
        OfficialPageList $officialPageList,
    ) {
        $dto = $staticDataGeneration->getTopPageData();
        
        // SEO-optimized schema generation
        $_schema = $_meta->generateTopPageSchema(...);
        
        return view('top_content', compact(...));
    }
}
```

**Design Philosophy:**
- Loose coupling for high extensibility
- SEO and performance optimization focus
- Clear separation of view and business logic

#### View Layer: Hybrid Integration

```php
<!-- PHP Template -->
<?php if (MimimalCmsConfig::$urlRoot === ''): ?>
    <div id="myListDiv"></div> <!-- React component mounts here -->
<?php endif ?>

<!-- JavaScript Integration -->
<script>
// DOM manipulation and React coordination
document.addEventListener('DOMContentLoaded', function() {
    ReactDOM.render(<MyListComponent />, document.getElementById('myListDiv'));
});
</script>
```

**Integration Approach:**
- **Server-side**: PHP template engine
- **Client-side**: React components
- **JavaScript**: DOM manipulation and event handling

### Dependency Injection System

Implementation switching via custom DI container:

```php
// shared/MimimalCmsConfig.php
public static array $constructorInjectionMap = [
    // Interface → Implementation class mapping
    \App\Models\Repositories\Statistics\StatisticsRepositoryInterface::class 
        => \App\Models\SQLite\Repositories\Statistics\SqliteStatisticsRepository::class,
    
    // Dynamic database implementation switching
    \App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface::class 
        => \App\Models\SQLite\Repositories\RankingPosition\SqliteRankingPositionRepository::class,
];
```

**Benefits:**
- Interface-driven implementation abstraction
- Easy switching between MySQL and SQLite
- Improved testing and maintenance


2. **State Management**: Progress tracking via database
3. **Error Handling**: Safe shutdown on failures
4. **Inter-process Communication**: Control via killFlag

### Cron Data Update System

#### Overall Coordination: SyncOpenChat

```php
class SyncOpenChat
{
    function handle(bool $dailyTest = false, bool $retryDailyTest = false)
    {
        $this->init();
        
        if (isDailyUpdateTime() || ($dailyTest && !$retryDailyTest)) {
            // Daily execution at 23:30
            $this->dailyTask();
        } else if ($this->isFailedDailyUpdate() || $retryDailyTest) {
            $this->retryDailyTask();
        } else {
            // Hourly execution at :30 (except 23:30)
            $this->hourlyTask();
        }
        
        $this->sitemap->generate();
    }
    
    private function hourlyTask()
    {
        set_time_limit(1620); // 27-minute timeout
        
        $this->state->setTrue(StateType::isHourlyTaskActive);
        $this->state->setFalse(StateType::isHourlyTaskActive);
        
        $this->hourlyTaskAfterDbMerge(
            !$this->rankingPositionHourChecker->isLastHourPersistenceCompleted()
        );
    }
}
```

**Cron Processing Complexity:**
1. **State Management**: Prevent overlap with execution flags
2. **Staged Processing**: Crawling → Image updates → Ranking recalculation
3. **Error Recovery**: Automatic retry on failures
4. **Notification System**: Discord notifications for monitoring

### Multi-language Architecture

#### Dynamic Switching by URL Root

```php
// Language determined by MimimalCmsConfig::$urlRoot
$urlRoot = ''; // Japanese
$urlRoot = '/tw'; // Taiwan (Traditional Chinese)
$urlRoot = '/th'; // Thai

// Dynamic database name determination
$dbName = match($urlRoot) {
    '' => 'ocgraph_ocreview',
    '/tw' => 'ocgraph_ocreviewtw', 
    '/th' => 'ocgraph_ocreviewth'
};
```

#### Translation System

```php
// Translation function usage in views
echo t('オプチャグラフ'); // Translates based on current language
echo t('オプチャグラフ', '/tw'); // Specific language designation
```

## 🧪 Testing

⚠️ **Current Test Implementation Status**

Current tests are implemented at a **functional verification level** and do not achieve comprehensive coverage.

```bash
# Run existing tests
./vendor/bin/phpunit

# Test specific directory
./vendor/bin/phpunit app/Services/test/

# Test specific file
./vendor/bin/phpunit app/Services/Recommend/test/RecommendUpdaterTest.php
```

### Test Configuration
- **Location**: `test/` subdirectories within each module
- **Naming Convention**: `*Test.php`
- **Framework**: PHPUnit 9.6
- **Coverage**: Partial (main functionality verification only)

### Future Improvements

- [ ] **Performance Tests**: Load testing for large data processing
- [ ] **E2E Tests**: Frontend and backend integration testing
- [ ] **Test Coverage**: More comprehensive unit testing

## 📊 Ranking System

### Listing Criteria

1. **Membership Changes**: Must have changes within the past week
2. **Minimum Members**: Current and comparison points must both have 10+ members

### Ranking Types

- **1-hour**: Growth rate in the last hour
- **24-hour**: Daily growth rate
- **Weekly**: Weekly growth rate

## 🔗 Related Repositories

### Frontend Components

- [Ranking Pages](https://github.com/mimimiku778/Open-Chat-Graph-Frontend)
- [Graph Display](https://github.com/mimimiku778/Open-Chat-Graph-Frontend-Stats-Graph)
- [Comment System](https://github.com/mimimiku778/Open-Chat-Graph-Comments)

## 🤝 Contributing

Pull requests and issue reports are welcome. For major changes, please create an issue first to discuss the proposed changes.

### Development Guidelines

#### 1. SOLID Principles First

- **S - Single Responsibility**: Each class has only one responsibility
- **O - Open/Closed**: Open for extension, closed for modification
- **L - Liskov Substitution**: Derived classes are substitutable for base classes
- **I - Interface Segregation**: Don't force dependence on unused methods
- **D - Dependency Inversion**: Depend on abstractions, not concretions

#### 2. Architecture Principles

- Follow PSR-4 autoloading conventions
- Abstract data access with repository pattern
- Ensure testability with dependency injection
- Achieve type-safe data transfer with DTOs

#### 3. Code Quality

- Write tests (using PHPUnit)
- Follow existing code style
- Use prepared statements for raw SQL
- Implement proper error handling

#### 4. Other

- Clear commit messages
- Discuss major changes in issues first

## ⚖️ License

This project is released under the [MIT License](LICENSE.md).

## 📞 Contact

- **Email**: [support@openchat-review.me](mailto:support@openchat-review.me)
- **Website**: [https://openchat-review.me](https://openchat-review.me)

## 🙏 Acknowledgments

This project is supported by many open source projects. Special thanks to:

- LINE Corporation
- PHP Community
- React Community

---

<p align="center">
  Made with ❤️ for the LINE OpenChat Community
</p>