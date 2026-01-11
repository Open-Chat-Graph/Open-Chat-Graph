# オプチャグラフ（OpenChat Graph）

LINE OpenChatのメンバー数推移を可視化し、トレンドを分析するWebサービス

**🌐 公式サイト**: https://openchat-review.me
**ライセンス**: MIT
**言語:** [日本語](README.md) | [English](README_EN.md)

---

## 🚀 開発環境のセットアップ

### 通常環境

```bash
docker compose up -d
docker compose exec app bash
cd /var/www/html
export MYSQL_HOST=mysql
export MYSQL_PASSWORD=test_root_pass
./database/init-database.sh
composer install
```

- Web: http://localhost:7000
- phpMyAdmin: http://localhost:7070
- MySQL: localhost:3307

### モックAPI環境（インターネット接続不要）

```bash
docker compose -f docker-compose.dev.yml --env-file .env.dev up -d
docker compose -f docker-compose.dev.yml exec app bash
cd /var/www/html
./database/init-database.sh
composer install
```

- Web: http://localhost:8100
- phpMyAdmin: http://localhost:8180
- MySQL: localhost:3308
- LINE Mock API: http://localhost:9000 ([実装](docker/line-mock-api/public/index.php))

---

## 🏗️ 技術スタック

- PHP 8.3 + [MimimalCMS](https://github.com/mimimiku778/MimimalCMS)（自作MVCフレームワーク）
- MySQL/MariaDB + SQLite
- React + TypeScript（事前ビルド済み）
- 外部リポジトリ: [ランキング](https://github.com/mimimiku778/Open-Chat-Graph-Frontend) / [グラフ](https://github.com/mimimiku778/Open-Chat-Graph-Frontend-Stats-Graph) / [コメント](https://github.com/mimimiku778/Open-Chat-Graph-Comments)

## 📁 ディレクトリ構造

```
app/
├── Config/         # ルーティング
├── Controllers/    # HTTPハンドラー
├── Models/         # リポジトリ・DTO
├── Services/       # ビジネスロジック
└── Views/          # テンプレート
shadow/             # MimimalCMSフレームワーク
batch/              # Cronジョブ
shared/             # DI設定
storage/            # SQLite・ログ
database/           # スキーマ・初期化スクリプト
```

## 💻 主要ファイル

**MVC**
- リポジトリ: [`OpenChatRepositoryInterface`](app/Models/Repositories/OpenChatRepositoryInterface.php), [`OpenChatRepository`](app/Models/Repositories/OpenChatRepository.php)
- コントローラー: [`IndexPageController`](app/Controllers/Pages/IndexPageController.php), [`OpenChatApiController`](app/Controllers/Api/OpenChatApiController.php)
- DI設定: [`MimimalCmsConfig.php`](shared/MimimalCmsConfig.php)

**クローリング**
- スケジューラ: [`SyncOpenChat`](app/Services/Cron/SyncOpenChat.php)
- API取得: [`OpenChatApiDbMerger`](app/Services/OpenChat/OpenChatApiDbMerger.php), [`OpenChatApiRankingDownloader`](app/Services/OpenChat/Crawler/OpenChatApiRankingDownloader.php)
- 日次処理: [`DailyUpdateCronService`](app/Services/DailyUpdateCronService.php)

**ランキング**
- [`UpdateHourlyMemberRankingService`](app/Services/UpdateHourlyMemberRankingService.php)

**データベース**
- スキーマ詳細: [`db_schema.md`](db_schema.md)
- スキーマファイル: [`database/schema/`](database/schema/)
- 言語別接続: [`App\Models\Repositories\DB`](app/Models/Repositories/DB.php)

---

## 📞 連絡先

- Email: support@openchat-review.me
- X (Twitter): [@openchat_graph](https://x.com/openchat_graph)
