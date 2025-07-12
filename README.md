# オプチャグラフ（OpenChat Graph）

LINE OpenChatのメンバー数推移を可視化し、トレンドを分析できるWebサービス

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Live](https://img.shields.io/badge/Live-openchat--review.me-green)](https://openchat-review.me)

![オプチャグラフ](/public/assets/image.jpg)

## 概要

オプチャグラフは、LINE OpenChatコミュニティの成長トレンドを追跡・分析するWebアプリケーションです。15万以上のOpenChatを毎時間クロールし、メンバー数の推移、ランキング、統計データを提供します。

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
- Node.js 18+ (フロントエンド開発時)

### クイックスタート

```bash
# リポジトリのクローン
git clone https://github.com/pika-0203/Open-Chat-Graph.git
cd Open-Chat-Graph

# 依存関係のインストール
composer install

# ローカル設定のセットアップ
# ⚠️ 機密情報が必要です - GitHubのIssueでお問い合わせください
./local-setup.sh

# Docker環境の起動
docker-compose up -d
```

**アクセスURL:**
- Web: http://localhost:8000
- phpMyAdmin: http://localhost:8080
- MySQL: localhost:3306

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

## 🧪 テスト

```bash
# 全テストの実行
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

## 📊 ランキングシステム

### 掲載条件

1. **メンバー数変動**: 過去1週間で変動があること
2. **最低メンバー数**: 現在・比較時点ともに10人以上

### ランキング種別

- **1時間**: 直近1時間の成長率
- **24時間**: 日次成長率
- **週間**: 週間成長率

## 🔗 関連リポジトリ

### フロントエンドコンポーネント

- [ランキングページ](https://github.com/mimimiku778/Open-Chat-Graph-Frontend)
- [グラフ表示](https://github.com/mimimiku778/Open-Chat-Graph-Frontend-Stats-Graph)
- [コメント機能](https://github.com/mimimiku778/Open-Chat-Graph-Comments)

## 🤝 コントリビューション

プルリクエストやイシューの報告を歓迎します。大きな変更を加える場合は、まずイシューを作成して変更内容について議論してください。

### 開発ガイドライン

1. PSR-4オートローディング規約に従う
2. テストを書く（PHPUnit使用）
3. 既存のコードスタイルに合わせる
4. コミットメッセージは明確に

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
