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
docker compose exec app bash "/var/www/html/local-setup.default.sh"
```

- Web: http://localhost:7000
- phpMyAdmin: http://localhost:7070
- MySQL: localhost:3307

### モックAPI環境（インターネット接続不要）

```bash
docker compose -f docker-compose.dev.yml --env-file .env.dev up -d
docker compose -f docker-compose.dev.yml exec app bash "/var/www/html/local-setup.default.sh"
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
├── Config/         # ルーティング・設定
├── Controllers/    # HTTPハンドラー
├── Models/         # リポジトリ・DTO
├── Services/       # ビジネスロジック
└── Views/          # テンプレート
shadow/             # MimimalCMSフレームワーク
batch/              # Cronジョブ・バッチ処理
shared/             # DI設定
storage/            # SQLite・ログ・キャッシュ
setup/              # データベーススキーマ・初期化スクリプト
public/             # Webルート
docker/             # Docker設定
```

---

## 📞 連絡先

- Email: support@openchat-review.me
- X (Twitter): [@openchat_graph](https://x.com/openchat_graph)
