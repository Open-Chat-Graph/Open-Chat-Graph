# オプチャグラフ（OpenChat Graph）

LINE OpenChatのメンバー数推移を可視化し、トレンドを分析できるWebサービス

---

**関連リポジトリ:**
- ランキング画面: https://github.com/mimimiku778/Open-Chat-Graph-Frontend
  - React, MUI, Swiper.js
- グラフ画面: https://github.com/mimimiku778/Open-Chat-Graph-Frontend-Stats-Graph
  - Preact, MUI, Chart.js
- コメント画面: https://github.com/mimimiku778/Open-Chat-Graph-Comments
  - React, MUI 

---

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Live](https://img.shields.io/badge/Live-openchat--review.me-green)](https://openchat-review.me)

![オプチャグラフ](/public/assets/image.jpg)

**言語:** [日本語](README.md) | [English](README_EN.md)

## 概要

オプチャグラフは、LINE OpenChatコミュニティの成長トレンドを追跡・分析するWebアプリケーションです。15万以上のOpenChatを毎時間クロールし、メンバー数の推移、ランキング、統計データを提供します。

### 主な機能

- 📊 **成長トレンド可視化** - メンバー数の推移をグラフで表示
- 🔍 **高度な検索機能** - キーワード、タグ、カテゴリでの検索
- 📈 **リアルタイムランキング** - 1時間/24時間/週間の成長ランキング
- 🌏 **多言語対応** - 日本語、タイ語、繁体字中国語に対応
- 💬 **コメント機能** - ユーザー同士の情報交換
- 🏷️ **推奨タグシステム** - AIによる関連タグの自動生成

## 🏗️ アーキテクチャ

### 技術スタック

#### バックエンド
- **フレームワーク**: [MimimalCMS](https://github.com/mimimiku778/MimimalCMS) (カスタム軽量MVC)
- **言語**: PHP 8.3
- **データベース**: 
  - MySQL/MariaDB (メインデータ)
  - SQLite (ランキング・統計データ)
- **依存性注入**: カスタムDIコンテナ

#### フロントエンド
- **言語**: TypeScript, JavaScript
- **フレームワーク**: React (サーバーサイドPHPとのハイブリッド)
- **UIライブラリ**: MUI, Chart.js, Swiper.js
- **ビルド**: 事前ビルド済みバンドル

### データベース設計

詳細なデータベーススキーマについては [db_schema.md](./db_schema.md) を参照してください。

### ディレクトリ構造

```
/
├── app/                    # アプリケーションコード (MVC)
│   ├── Config/            # ルーティング・設定
│   ├── Controllers/       # HTTPハンドラー
│   ├── Models/           # データアクセス層
│   ├── Services/         # ビジネスロジック
│   └── Views/            # テンプレート・React
├── shadow/                # MimimalCMSフレームワーク
├── batch/                 # バッチ処理・クロンジョブ
├── shared/               # 共通設定・DI定義
├── storage/              # データファイル・SQLite DB
└── public/               # 公開ディレクトリ
```

## 🚀 開発環境のセットアップ

### 前提条件

- Docker & Docker Compose
- PHP 8.3+
- Composer
- Node.js 18+ (フロントエンド開発時)

### クイックスタート

```bash
# リポジトリのクローン
git clone https://github.com/pika-0203/Open-Chat-Graph.git
cd Open-Chat-Graph

# Docker環境の起動
docker-compose up -d

# 依存関係のインストール
composer install

# ローカル設定のセットアップ
# ⚠️ 機密情報が必要です - GitHubのIssueでお問い合わせください
./local-setup.sh
```

**アクセスURL:**
- Web: http://localhost:8000
- phpMyAdmin: http://localhost:8080
- MySQL: localhost:3306

## 💻 実装詳細

### MVCアーキテクチャ

#### Model層：リポジトリパターン

インターフェース駆動設計により、テスト容易性と保守性を確保：

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
        // Raw SQLによる高パフォーマンスINSERT
        $dto->registered_open_chat_id = DB::executeAndGetLastInsertId(
            "INSERT IGNORE INTO open_chat (...) VALUES (...)",
            [...] // 型安全なバインド値
        );
        
        // SQLiteへの統計データ同期
        $this->statisticsRepository->addNewOpenChatStatisticsFromDto($dto);
        
        return $dto->registered_open_chat_id;
    }
}
```

**特徴:**
- Raw SQLによる複雑クエリと高パフォーマンス
- MySQL + SQLiteハイブリッド構成
- DTOパターンによる型安全性

#### Controller層：依存性注入

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
        
        // SEO最適化スキーマ生成
        $_schema = $_meta->generateTopPageSchema(...);
        
        return view('top_content', compact(...));
    }
}
```

**設計思想:**
- 疎結合設計による高い拡張性
- SEOとパフォーマンス最適化を重視
- ビューとビジネスロジックの明確な分離

#### View層：ハイブリッド統合

```php
<!-- PHP テンプレート -->
<?php if (MimimalCmsConfig::$urlRoot === ''): ?>
    <div id="myListDiv"></div> <!-- React コンポーネントがマウント -->
<?php endif ?>

<!-- JavaScript統合 -->
<script>
// DOM操作とReactの協調動作
document.addEventListener('DOMContentLoaded', function() {
    ReactDOM.render(<MyListComponent />, document.getElementById('myListDiv'));
});
</script>
```

**統合方式:**
- **サーバーサイド**: PHP テンプレートエンジン
- **クライアントサイド**: React コンポーネント
- **JavaScript**: DOM操作とイベントハンドリング

### 依存性注入システム

カスタムDIコンテナによる実装切り替え：

```php
// shared/MimimalCmsConfig.php
public static array $constructorInjectionMap = [
    // インターフェース → 実装クラスのマッピング
    \App\Models\Repositories\Statistics\StatisticsRepositoryInterface::class 
        => \App\Models\SQLite\Repositories\Statistics\SqliteStatisticsRepository::class,
    
    // データベース実装の動的切り替え
    \App\Models\Repositories\RankingPosition\RankingPositionRepositoryInterface::class 
        => \App\Models\SQLite\Repositories\RankingPosition\SqliteRankingPositionRepository::class,
];
```

**メリット:**
- インターフェース駆動で実装を抽象化
- MySQLとSQLiteの切り替えが容易
- テストとメンテナンスの向上

### 並列クローリングシステム

#### 親プロセス：並列実行制御

```php
class OpenChatApiDbMergerWithParallelDownloader
{
    function fetchOpenChatApiRankingAll()
    {
        // 状態初期化
        $this->setKillFlagFalse();
        $this->stateRepository->cleanUpAll();
        
        // 24並列プロセスでダウンロード実行
        foreach ($categoryArray as $key => $category) {
            $this->download([
                [RankingType::Ranking, $category], 
                [RankingType::Rising, $categoryReverse[$key]]
            ]);
        }
        
        // 完了まで監視・マージ処理
        while (!$flag) {
            sleep(10);
            foreach ([RankingType::Ranking, RankingType::Rising] as $type)
                foreach ($categoryReverse as $category)
                    $this->mergeProcess($type, $category);
            
            $flag = $this->stateRepository->isCompletedAll();
        }
    }
}
```

#### 子プロセス：ダウンロード処理

```php
class ParallelDownloadOpenChat
{
    function handle(array $args)
    {
        try {
            foreach ($args as $api) {
                $type = RankingType::from($api['type']);
                $category = $api['category'];
                $this->download($type, $category);
            }
        } catch (ApplicationException $e) {
            $this->handleDetectStopFlag($args, $e);
        } catch (\Throwable $e) {
            // 全プロセス強制終了
            OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();
            $this->handleGeneralException($api['type'], $api['category'], $e);
        }
    }
}
```

**並列処理の要点:**
1. **24並列実行**: 全カテゴリ同時ダウンロード
2. **状態管理**: データベースで進行状況追跡
3. **エラーハンドリング**: 障害時の安全な停止
4. **プロセス間通信**: killFlagによる制御

### Cronデータ更新システム

#### 全体調整：SyncOpenChat

```php
class SyncOpenChat
{
    function handle(bool $dailyTest = false, bool $retryDailyTest = false)
    {
        $this->init();
        
        if (isDailyUpdateTime() || ($dailyTest && !$retryDailyTest)) {
            // 毎日23:30実行
            $this->dailyTask();
        } else if ($this->isFailedDailyUpdate() || $retryDailyTest) {
            $this->retryDailyTask();
        } else {
            // 毎時30分実行（23:30除く）
            $this->hourlyTask();
        }
        
        $this->sitemap->generate();
    }
    
    private function hourlyTask()
    {
        set_time_limit(1620); // 27分タイムアウト
        
        $this->state->setTrue(StateType::isHourlyTaskActive);
        $this->merger->fetchOpenChatApiRankingAll(); // 並列クローリング
        $this->state->setFalse(StateType::isHourlyTaskActive);
        
        $this->hourlyTaskAfterDbMerge(
            !$this->rankingPositionHourChecker->isLastHourPersistenceCompleted()
        );
    }
}
```

**Cron処理の複雑性:**
1. **状態管理**: 実行中フラグで重複防止
2. **段階的処理**: クローリング → 画像更新 → ランキング再計算
3. **エラー回復**: 失敗時の自動リトライ
4. **通知システム**: Discord通知による監視

#### 再試行フローの詳細

**実行時間の設定:**
```php
// 言語別のcron実行時間
const CRON_START_MINUTE = [
    '' =>    30,  // 日本語: 毎時30分
    '/tw' => 35,  // 台湾: 毎時35分  
    '/th' => 40,  // タイ: 毎時40分
];

const CRON_MERGER_HOUR_RANGE_START = [
    '' =>    23,  // 日本語: 23:30（日次処理）
    '/tw' => 0,   // 台湾: 0:35（日次処理）
    '/th' => 1,   // タイ: 1:40（日次処理）
];
```

**1. 毎時処理の再試行フロー:**
```php
// SyncOpenChat::handleHalfHourCheck() - 毎時0分実行
function handleHalfHourCheck()
{
    if ($this->state->getBool(StateType::isHourlyTaskActive)) {
        // 前回の処理が継続中の場合、再試行
        $this->retryHourlyTask();
    } elseif (!$this->rankingPositionHourChecker->isLastHourPersistenceCompleted()) {
        // ランキング永続化が未完了の場合、後続処理のみ実行
        $this->hourlyTaskAfterDbMerge(true);
    }
}

private function retryHourlyTask()
{
    addCronLog('Retry hourlyTask');
    AdminTool::sendDiscordNotify('Retry hourlyTask');
    
    // 実行中の並列プロセスを強制終了
    OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();
    sleep(30); // プロセス終了待機
    
    $this->handle(); // 再実行
}
```

**2. 日次処理の再試行フロー:**
```php
private function retryDailyTask()
{
    // 6:30以降（通知時間後）の場合のみDiscord通知
    if ($this->isAfterRetryNotificationTime()) {
        AdminTool::sendDiscordNotify('Retrying dailyTask');
    }
    
    // 全プロセス強制終了
    OpenChatApiDbMergerWithParallelDownloader::setKillFlagTrue();
    OpenChatDailyCrawling::setKillFlagTrue();
    sleep(30);
    
    $this->dailyTask(); // 日次処理再実行
}

// 通知制御: 6時間以内の再試行では通知を抑制
function isAfterRetryNotificationTime(): bool
{
    return !isDailyUpdateTime()
        && !isDailyUpdateTime(new \DateTime('-1 hour'), new \DateTime('-1 hour'))
        && !isDailyUpdateTime(new \DateTime('-2 hour'), new \DateTime('-2 hour'))
        && !isDailyUpdateTime(new \DateTime('-3 hour'), new \DateTime('-3 hour'))
        && !isDailyUpdateTime(new \DateTime('-4 hour'), new \DateTime('-4 hour'))
        && !isDailyUpdateTime(new \DateTime('-5 hour'), new \DateTime('-5 hour'))
        && !isDailyUpdateTime(new \DateTime('-6 hour'), new \DateTime('-6 hour'));
}
```

**3. 状態管理による制御:**
```php
enum SyncOpenChatStateType: string
{
    case isDailyTaskActive = 'isDailyTaskActive';
    case isHourlyTaskActive = 'isHourlyTaskActive';
    case openChatApiDbMergerKillFlag = 'openChatApiDbMergerKillFlag';
    case openChatDailyCrawlingKillFlag = 'openChatDailyCrawlingKillFlag';
    case isUpdateInvitationTicketActive = 'isUpdateInvitationTicketActive';
}
```

**4. エラー回復メカニズム:**

- **プロセス監視**: 実行状態フラグで異常検知
- **強制終了**: killFlagによる安全な停止
- **段階的復旧**: 部分的に失敗した処理の継続実行
- **通知制御**: 頻繁な通知を避けるタイムウィンドウ
- **データ整合性**: 途中失敗時の状態復元

**5. 多言語環境での分散実行:**

各言語版が異なる時間に実行されることで、サーバー負荷を分散：

- **日本語**: 23:30, X:30（毎時）
- **台湾版**: 0:35, X:35（毎時） 
- **タイ版**: 1:40, X:40（毎時）

この設計により、大規模データ処理でも高い可用性を実現しています。

### ハイブリッドデータベース設計

#### MySQL（リアルタイム更新）

```sql
-- statistics_ranking_hour: 毎時間完全再構築
CREATE TABLE `statistics_ranking_hour` (
  `id` int(11) NOT NULL,           -- ❗ランキング順位（1位、2位...）
  `open_chat_id` int(11) NOT NULL, -- open_chat.idへの参照
  `diff_member` int(11) NOT NULL,  -- 1時間での増加数
  `percent_increase` float NOT NULL -- 増加率
  -- ❗created_atカラムは存在しない
);
```

#### SQLite（読み取り専用最適化）

```sql
-- statistics: 履歴データ高速読み取り
CREATE TABLE "statistics" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  open_chat_id INTEGER NOT NULL,
  "member" INTEGER NOT NULL,
  date TEXT NOT NULL
);
CREATE UNIQUE INDEX statistics2_open_chat_id_IDX ON "statistics" (open_chat_id,date);
```

**設計戦略:**
- **MySQL**: 書き込み重視、複雑JOIN
- **SQLite**: 読み取り重視、履歴データ
- **使い分け**: パフォーマンス最適化

### 多言語対応アーキテクチャ

#### URL Rootによる動的切り替え

```php
// MimimalCmsConfig::$urlRoot で言語決定
$urlRoot = ''; // 日本語
$urlRoot = '/tw'; // 台湾（繁体字中国語）
$urlRoot = '/th'; // タイ語

// データベース名動的決定
$dbName = match($urlRoot) {
    '' => 'ocgraph_ocreview',
    '/tw' => 'ocgraph_ocreviewtw', 
    '/th' => 'ocgraph_ocreviewth'
};
```

#### 翻訳システム

```php
// ビューでの翻訳関数使用
echo t('オプチャグラフ'); // 現在言語に応じて翻訳
echo t('オプチャグラフ', '/tw'); // 特定言語指定
```

## 🔧 複雑性の理由と対策

### 高負荷処理への対応

- **15万件大量データ**: メモリ効率的な処理
- **リアルタイム更新**: キャッシュとバッチ処理の最適化

### 堅牢性の確保

- **エラー回復**: 自動リトライとフォールバック
- **監視システム**: Discord通知とログ記録
- **データ整合性**: トランザクション管理
- **プロセス制御**: 安全な強制終了機能

## 🧪 テスト

⚠️ **現状のテスト実装について**

現在のテストは**動作確認レベル**の実装であり、全体をカバーする完成度には達していません。

```bash
# 既存テストの実行
./vendor/bin/phpunit

# 特定ディレクトリのテスト
./vendor/bin/phpunit app/Services/test/

# 特定ファイルのテスト
./vendor/bin/phpunit app/Services/Recommend/test/RecommendUpdaterTest.php
```

### テスト構成
- **配置**: 各モジュールの `test/` サブディレクトリ
- **命名規則**: `*Test.php`
- **フレームワーク**: PHPUnit 9.6
- **カバレッジ**: 部分的（主要機能の動作確認のみ）

### 今後の課題

- [ ] **統合テスト**: 並列クローリングシステムのフルテスト
- [ ] **パフォーマンステスト**: 大量データ処理の負荷テスト  
- [ ] **E2Eテスト**: フロントエンドとバックエンドの統合テスト
- [ ] **テストカバレッジ**: より包括的なユニットテスト

## 📊 ランキングシステム

### 掲載条件

1. **メンバー数変動**: 過去1週間で変動があること
2. **最低メンバー数**: 現在・比較時点ともに10人以上

### ランキング種別

- **1時間**: 直近1時間の成長率
- **24時間**: 日次成長率
- **週間**: 週間成長率

## 🕷️ クローリングシステム

### 並列処理アーキテクチャ

約15万件のOpenChatを効率的に処理するための高速並列クローリングシステムを実装しています。

- **24並列プロセス**: 全カテゴリを同時処理
- **独自最適化**: 高速レンダリング・DB更新技術
- **自動リトライ**: エラー処理とフォールバック

#### 主要コンポーネント

1. [OpenChatApiDbMergerWithParallelDownloader](app/Services/OpenChat/OpenChatApiDbMergerWithParallelDownloader.php) - 親プロセス
2. [ParallelDownloadOpenChat](app/Services/Cron/ParallelDownloadOpenChat.php) - 子プロセス
3. [OpenChatApiDataParallelDownloader](app/Services/OpenChat/OpenChatApiDataParallelDownloader.php) - データ処理

### ユーザーエージェント

```
Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/pika-0203/Open-Chat-Graph)
```

## 🤝 コントリビューション

プルリクエストやイシューの報告を歓迎します。大きな変更を加える場合は、まずイシューを作成して変更内容について議論してください。

### 開発ガイドライン

#### 1. SOLID原則を第一に

- **S - 単一責任原則**: 各クラスは一つの責任のみを持つ
- **O - 開放閉鎖原則**: 拡張に開いて、修正に閉じている
- **L - リスコフの置換原則**: 派生クラスは基底クラスと置換可能
- **I - インターフェース分離原則**: 使用しないメソッドへの依存を強制しない
- **D - 依存性逆転原則**: 抽象に依存し、具象に依存しない

#### 2. アーキテクチャ原則

- PSR-4オートローディング規約に従う
- リポジトリパターンでデータアクセスを抽象化
- 依存性注入でテスト容易性を確保
- DTOで型安全なデータ転送を実現

#### 3. コード品質

- テストを書く（PHPUnit使用）
- 既存のコードスタイルに合わせる
- Raw SQLは準備済みステートメントを使用
- エラーハンドリングを適切に実装

#### 4. その他

- コミットメッセージは明確に
- 大きな変更前は必ずイシューで議論

## ⚖️ ライセンス

このプロジェクトは [MIT License](LICENSE.md) の下で公開されています。

## 📞 連絡先

- **Email**: [support@openchat-review.me](mailto:support@openchat-review.me)
- **Website**: [https://openchat-review.me](https://openchat-review.me)

## 🙏 謝辞

このプロジェクトは多くのオープンソースプロジェクトに支えられています。特に以下のプロジェクトに感謝します：

- LINE Corporation
- PHPコミュニティ
- Reactコミュニティ

---

<p align="center">
  Made with ❤️ for the LINE OpenChat Community
</p>
