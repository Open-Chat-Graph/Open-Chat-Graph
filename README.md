# オプチャグラフ（OpenChat Graph）

LINE OpenChatのメンバー数推移を可視化し、トレンドを分析できるWebサービス

**🌐 公式サイト**: https://openchat-review.me

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

![オプチャグラフ](/public/assets/image.jpg)

**言語:** [日本語](README.md) | [English](README_EN.md)

---

**関連リポジトリ:**
- [ランキング画面](https://github.com/mimimiku778/Open-Chat-Graph-Frontend) - React, MUI, Swiper.js
- [グラフ画面](https://github.com/mimimiku778/Open-Chat-Graph-Frontend-Stats-Graph) - Preact, MUI, Chart.js
- [コメント画面](https://github.com/mimimiku778/Open-Chat-Graph-Comments) - React, MUI

---

## 概要

オプチャグラフは、LINE OpenChatコミュニティの成長トレンドを追跡・分析するWebアプリケーションです。15万以上のOpenChatを定期的にクロールし、メンバー数の推移、ランキング、統計データを提供します。

- **公式サイト**: https://openchat-review.me
- **ライセンス**: MIT

### 主な機能

- 📊 **成長トレンド可視化** - メンバー数の推移をグラフで表示
- 🔍 **高度な検索機能** - キーワード、タグ、カテゴリでの検索
- 📈 **リアルタイムランキング** - 1時間/24時間/週間の成長ランキング
- 🌏 **多言語対応** - 日本語、タイ語、繁体字中国語に対応
- 💬 **コメント機能** - ユーザー同士の情報交換
- 🏷️ **推奨タグシステム** - AIによる関連タグの自動生成

## 🚀 開発環境のセットアップ

### 前提条件

- Docker & Docker Compose
- PHP 8.3+
- Composer

### クイックスタート

```bash
# リポジトリのクローン
git clone https://github.com/pika-0203/Open-Chat-Graph.git
cd Open-Chat-Graph

# Docker環境の起動
docker compose up -d

# コンテナ内で依存関係のインストール
docker compose exec app composer install
```

**アクセスURL:**
- Web: http://localhost:8000
- phpMyAdmin: http://localhost:8080
- MySQL: localhost:3306

**⚠️ ローカル環境の詳細なセットアップ**

本番環境と同等のデータおよび設定ファイル（機密情報を含む）が必要です。詳細については、X（Twitter）[@openchat_graph](https://x.com/openchat_graph) でお問い合わせください。

## 🏗️ アーキテクチャ

### 技術スタック

#### バックエンド
- **フレームワーク**: [MimimalCMS](https://github.com/mimimiku778/MimimalCMS) - 自前のカスタム軽量MVCフレームワーク（詳細はリンク先を参照）
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
├── batch/                 # バッチ処理・cronジョブ
├── shared/               # 共通設定・DI定義
├── storage/              # データファイル・SQLite DB
└── public/               # 公開ディレクトリ
```

### データベース設計

詳細なデータベーススキーマについては [db_schema.md](./db_schema.md) を参照してください。

**設計戦略:**
- **MySQL**: リアルタイム更新が必要なデータ（メンバー数、ランキング）
- **SQLite**: 読み取り専用の集計データ（履歴、統計）
- **使い分け**: パフォーマンス最適化のためのハイブリッド構成

## 💻 実装の特徴

### MVCアーキテクチャ

**Model層: リポジトリパターン**

インターフェース駆動設計により、テスト容易性と保守性を確保しています。実装の詳細は以下を参照:
- [`OpenChatRepositoryInterface`](/app/Models/Repositories/OpenChatRepositoryInterface.php)
- [`OpenChatRepository`](/app/Models/Repositories/OpenChatRepository.php)

特徴:
- Raw SQLによる複雑クエリと高パフォーマンス
- MySQL + SQLiteハイブリッド構成
- DTOパターンによる型安全性

**Controller層: 依存性注入**

疎結合設計により高い拡張性を実現。実装例:
- [`IndexPageController`](/app/Controllers/Pages/IndexPageController.php)

**View層: ハイブリッド統合**

サーバーサイドPHPテンプレート + クライアントサイドReactコンポーネントのハイブリッド構成。

### 依存性注入システム

カスタムDIコンテナによる実装切り替えを実現:
- [`MimimalCmsConfig.php`](/shared/MimimalCmsConfig.php)

メリット:
- インターフェース駆動で実装を抽象化
- MySQLとSQLiteの切り替えが容易
- テストとメンテナンスの向上

### データ更新システム（Cron）

オプチャグラフは、毎時間および毎日定期的にOpenChatデータを更新します。

#### 実行スケジュール

**毎時処理（hourlyTask）**
- 実行時間: 毎時30分（日本語版）、毎時35分（台湾版）、毎時40分（タイ版）
- タイムアウト: 27分
- 処理内容: OpenChatデータのクローリング、画像更新、ランキング更新

**日次処理（dailyTask）**
- 実行時間: 23:30（日本語版）、0:35（台湾版）、1:40（タイ版）
- タイムアウト: 90分
- 処理内容: 全データの完全更新、削除されたOpenChatの検出

**実装の詳細:**
- [`SyncOpenChat`](/app/Services/Cron/SyncOpenChat.php) - 全体調整とスケジューリング
- [`OpenChatApiDbMerger`](/app/Services/OpenChat/OpenChatApiDbMerger.php) - データ取得とDB更新
- [`DailyUpdateCronService`](/app/Services/DailyUpdateCronService.php) - 日次処理の制御

#### エラー回復メカニズム

処理の堅牢性を確保するため、以下の仕組みを実装:
- **プロセス監視**: 実行状態フラグで異常検知
- **自動リトライ**: 失敗時の再実行（[`retryHourlyTask()`](/app/Services/Cron/SyncOpenChat.php), [`retryDailyTask()`](/app/Services/Cron/SyncOpenChat.php)）
- **強制終了**: killFlagによる安全な停止
- **通知システム**: Discord通知による監視

詳細は [`SyncOpenChat::handleHalfHourCheck()`](/app/Services/Cron/SyncOpenChat.php) を参照。

### 多言語対応

URL Root（`''`, `'/tw'`, `'/th'`）に基づいて、異なるデータベースと翻訳ファイルに自動切り替え。実装の詳細:
- [`MimimalCmsConfig.php`](/shared/MimimalCmsConfig.php) - 言語別設定
- [`App\Models\Repositories\DB`](/app/Models/Repositories/DB.php) - 言語別データベース接続

## 🔧 クローリングシステム

### ユーザーエージェント

```
Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/pika-0203/Open-Chat-Graph)
```

### クローリング処理

約15万件のOpenChatを効率的に処理するためのクローリングシステム。実装の詳細:
- [`OpenChatApiRankingDownloader`](/app/Services/OpenChat/Crawler/OpenChatApiRankingDownloader.php) - LINE APIからのデータ取得
- [`OpenChatDailyCrawling`](/app/Services/OpenChat/OpenChatDailyCrawling.php) - 日次クローリング処理

## 📊 ランキングシステム

### 掲載条件

1. **メンバー数変動**: 過去1週間で変動があること
2. **最低メンバー数**: 現在・比較時点ともに10人以上

### ランキング種別

- **1時間**: 直近1時間の成長率
- **24時間**: 日次成長率
- **週間**: 週間成長率

実装の詳細:
- [`UpdateHourlyMemberRankingService`](/app/Services/UpdateHourlyMemberRankingService.php)

## 🧪 テスト

⚠️ 現在のテストは**動作確認レベル**の実装であり、全体をカバーする完成度には達していません。

```bash
# 既存テストの実行
./vendor/bin/phpunit

# 特定ディレクトリのテスト
./vendor/bin/phpunit app/Services/test/

# 特定ファイルのテスト
./vendor/bin/phpunit app/Services/Recommend/test/RecommendUpdaterTest.php
```

## 🤝 コントリビューション

プルリクエストやイシューの報告を歓迎します。大きな変更を加える場合は、まずイシューを作成して変更内容について議論してください。

### 開発ガイドライン

#### 1. SOLID原則を第一に

このプロジェクトは、SOLID原則に基づいて設計されています:

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
- **X (Twitter)**: [@openchat_graph](https://x.com/openchat_graph)

## 🙏 謝辞

このプロジェクトは多くのオープンソースプロジェクトに支えられています。特に以下に感謝します：

- LINE Corporation
- PHPコミュニティ
- Reactコミュニティ

---

<p align="center">
  Made with ❤️ for the LINE OpenChat Community
</p>
