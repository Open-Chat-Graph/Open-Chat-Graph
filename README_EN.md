# OpenChat Graph

A web service for visualizing LINE OpenChat membership trends and analyzing growth patterns

**🌐 Official Site**: https://openchat-review.me

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

![OpenChat Graph](/public/assets/image.jpg)

**Languages:** [English](README_EN.md) | [日本語](README.md)

---

**Related Repositories:**
- [Ranking Pages](https://github.com/mimimiku778/Open-Chat-Graph-Frontend) - React, MUI, Swiper.js
- [Graph Display](https://github.com/mimimiku778/Open-Chat-Graph-Frontend-Stats-Graph) - Preact, MUI, Chart.js
- [Comment System](https://github.com/mimimiku778/Open-Chat-Graph-Comments) - React, MUI

---

## Overview

OpenChat Graph is a web application that tracks and analyzes growth trends for LINE OpenChat communities. It crawls over 150,000 OpenChats regularly, providing membership statistics, rankings, and growth analytics.

- **Official Site**: https://openchat-review.me
- **License**: MIT

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

### Quick Start

```bash
# Clone the repository
git clone https://github.com/pika-0203/Open-Chat-Graph.git
cd Open-Chat-Graph

# Start Docker environment
docker compose up -d

# Install dependencies inside the container
docker compose exec app composer install
```

**Access URLs:**
- Web: http://localhost:8000
- phpMyAdmin: http://localhost:8080
- MySQL: localhost:3306

**⚠️ Detailed Local Setup**

Production-equivalent data and configuration files (including sensitive information) are required. For details, please contact via X (Twitter) [@openchat_graph](https://x.com/openchat_graph).

## 🏗️ Architecture

### Technology Stack

#### Backend
- **Framework**: [MimimalCMS](https://github.com/mimimiku778/MimimalCMS) - Custom lightweight MVC framework (see link for details)
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

### Database Design

For detailed database schema, see [db_schema.md](./db_schema.md).

**Design Strategy:**
- **MySQL**: Real-time updates (member counts, rankings)
- **SQLite**: Read-only aggregated data (history, statistics)
- **Hybrid Configuration**: Optimized for performance

## 💻 Implementation Features

### MVC Architecture

**Model Layer: Repository Pattern**

Interface-driven design ensures testability and maintainability. For implementation details:
- [`OpenChatRepositoryInterface`](/app/Models/Repositories/OpenChatRepositoryInterface.php)
- [`OpenChatRepository`](/app/Models/Repositories/OpenChatRepository.php)

Features:
- Raw SQL for complex queries and high performance
- MySQL + SQLite hybrid configuration
- Type safety through DTO pattern

**Controller Layer: Dependency Injection**

Loose coupling for high extensibility. Example implementation:
- [`IndexPageController`](/app/Controllers/Pages/IndexPageController.php)

**View Layer: Hybrid Integration**

Server-side PHP templates + client-side React components.

### Dependency Injection System

Implementation switching via custom DI container:
- [`MimimalCmsConfig.php`](/shared/MimimalCmsConfig.php)

Benefits:
- Interface-driven implementation abstraction
- Easy switching between MySQL and SQLite
- Improved testing and maintenance

### Data Update System (Cron)

OpenChat Graph updates data hourly and daily through scheduled cron jobs.

#### Execution Schedule

**Hourly Task (hourlyTask)**
- Execution Time: :30 (Japanese), :35 (Taiwan), :40 (Thai) every hour
- Timeout: 27 minutes
- Processing: OpenChat data crawling, image updates, ranking updates

**Daily Task (dailyTask)**
- Execution Time: 23:30 (Japanese), 0:35 (Taiwan), 1:40 (Thai)
- Timeout: 90 minutes
- Processing: Full data update, detecting deleted OpenChats

**Implementation Details:**
- [`SyncOpenChat`](/app/Services/Cron/SyncOpenChat.php) - Coordination and scheduling
- [`OpenChatApiDbMerger`](/app/Services/OpenChat/OpenChatApiDbMerger.php) - Data fetching and DB updates
- [`DailyUpdateCronService`](/app/Services/DailyUpdateCronService.php) - Daily task control

#### Error Recovery Mechanism

To ensure process robustness:
- **Process Monitoring**: Anomaly detection via execution state flags
- **Automatic Retry**: Re-execution on failure ([`retryHourlyTask()`](/app/Services/Cron/SyncOpenChat.php), [`retryDailyTask()`](/app/Services/Cron/SyncOpenChat.php))
- **Safe Shutdown**: Kill flag for graceful termination
- **Notification System**: Discord notifications for monitoring

See [`SyncOpenChat::handleHalfHourCheck()`](/app/Services/Cron/SyncOpenChat.php) for details.

### Multi-language Support

Automatic switching between databases and translation files based on URL Root (`''`, `'/tw'`, `'/th'`). Implementation details:
- [`MimimalCmsConfig.php`](/shared/MimimalCmsConfig.php) - Language-specific configuration
- [`App\Models\Repositories\DB`](/app/Models/Repositories/DB.php) - Language-specific database connection

## 🔧 Crawling System

### User Agent

```
Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/pika-0203/Open-Chat-Graph)
```

### Crawling Process

Efficient crawling system for processing ~150,000 OpenChats. Implementation details:
- [`OpenChatApiRankingDownloader`](/app/Services/OpenChat/Crawler/OpenChatApiRankingDownloader.php) - Data fetching from LINE API
- [`OpenChatDailyCrawling`](/app/Services/OpenChat/OpenChatDailyCrawling.php) - Daily crawling process

## 📊 Ranking System

### Listing Criteria

1. **Membership Changes**: Must have changes within the past week
2. **Minimum Members**: Current and comparison points must both have 10+ members

### Ranking Types

- **1-hour**: Growth rate in the last hour
- **24-hour**: Daily growth rate
- **Weekly**: Weekly growth rate

Implementation details:
- [`UpdateHourlyMemberRankingService`](/app/Services/UpdateHourlyMemberRankingService.php)

## 🧪 Testing

⚠️ Current tests are implemented at a **functional verification level** and do not achieve comprehensive coverage.

```bash
# Run existing tests
./vendor/bin/phpunit

# Test specific directory
./vendor/bin/phpunit app/Services/test/

# Test specific file
./vendor/bin/phpunit app/Services/Recommend/test/RecommendUpdaterTest.php
```

## 🤝 Contributing

Pull requests and issue reports are welcome. For major changes, please create an issue first to discuss the proposed changes.

### Development Guidelines

#### 1. SOLID Principles First

This project is designed based on SOLID principles:

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
- **X (Twitter)**: [@openchat_graph](https://x.com/openchat_graph)

## 🙏 Acknowledgments

This project is supported by many open source projects. Special thanks to:

- LINE Corporation
- PHP Community
- React Community

---

<p align="center">
  Made with ❤️ for the LINE OpenChat Community
</p>
