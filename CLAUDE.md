# CLAUDE.md

オプチャグラフ (OpenChat Graph) — LINEオープンチャットの成長を追跡・表示するWebアプリ。公式サイトを毎時クロールしメンバー統計を集め、ランキング/検索/分析を出す。Live: https://openchat-review.me ／ 主言語: 日本語 ／ MIT。

## 最重要: 環境保護ルール
- `.env` の `DATA_PROTECTION=true` は**本番データ環境**。`make up-mock` / `make ci-test` 等の**mock環境操作・自動cron・DB破壊操作はユーザーの明示指示なしに実行しない**（本番DBが飛ぶ）。
- 禁止は「本番DBを壊す操作」だけ。**`php -l`・phpunit（`docker compose exec app vendor/bin/phpunit <path>`）・ローカルへの `curl`・SELECT・スキーマの加算反映は普通に実行してよい**。
- `DATA_PROTECTION=false` のときは mock操作もテストも自由。

## エージェント委譲（コンテキスト節約・最重要の運用方針）
この開発はユーザーが連続で指示を投げる。**メインのコンテキストには「作業全体の流れ・設計判断・TODO」を保持し、コード詳細の読み書き/実行はサブエージェントに逃がす**（メインで詳細を回すとパンクする）。精度は落とさずトークンを節約する。
- 極めて単純かつ決定論的（定型置換・一覧/文言整形・自明な1ファイル小修正・機械的な横展開）→ **Haiku**（`model: haiku`、最速）。
- 単純・機械的だが判断を伴う（仕様が明確な実装/リファクタ/配線）→ **Sonnet サブエージェント**（`Agent` with `model: sonnet`）。
- 既存の会話文脈は不要だが複雑（自己完結の機能実装・深い調査）→ **一時的な Opus サブエージェント**に精密な仕様（対象ファイルパス＋要件＋検証コマンド＋「コミットしない」）を渡して委譲。
- 探索/横断調査は **Explore** へ。**結論だけ**受け取る（ファイルダンプを本文に載せない）。UIレビュー（操作撮影/`frontend-design`/動線チェックの3観点）もサブで回し、メインは指摘＋スクショの**結論だけ**受ける。
- メイン自身がやるのは：ユーザー対話・設計/順序判断・短い編集・**安価な検証**（curl / スクショ1枚 / `php -l` / 型チェック）。サブの戻りは「変更ファイル＋検証結果の要約」のみ。

### 進め方
- 論理単位ごとに小さく頻繁にコミット（依存追加→UI部品→機能→統合→修正…）。一括大コミット禁止。常にビルド可能を保つ。
- 1機能 実装→再ビルド（`cd frontend/alpha && npm run build`）→`public/js/alpha` ごとコミット→ユーザーが `/alpha` で確認、のループ。

### フロント実装後の見た目チェック（必須フロー）
**ある程度のフロント実装をしたら、ユーザーに出す前に必ず「見た目チェック」を回す**（ユーザーに崩れ・はみ出し・幅/余白/重なりを指摘させない）。
- チェックは **Haiku か Sonnet のサブエージェント**に委譲し、**外見確認だけ**させる（メイン文脈を使わない）。playwright MCP（無ければヘッドレスChrome/CDP：別ポートで `--remote-allow-origins=*`）で、**スマホ390／PC1280** と主要な対話状態（dropdown/select/dialog/overlay開・スクロール後）のスクショを撮る。
- 見る観点: 要素が画面外へはみ出していないか（特に Select/プルダウン幅）、固定ヘッダ下への潜り、z-index の重なり、余白/タイポ/文言の崩れ。問題があればスクショ付きで指摘を返させ、メインが直す。
- 重い3観点レビュー（操作撮影／`frontend-design`／動線）は別途サブで回し、メインは結論だけ受ける。

### 静的解析（PHPStan）
- **PHPを変更したら適宜 PHPStan を通す**（level 0、`phpstan.neon`）。実行は必ず `--autoload-file` 付き:
  `docker compose exec app php vendor/bin/phpstan analyse --autoload-file=phpstan-bootstrap.php [<変更したパス>]`
  - これを付けないとアプリのエラーハンドラが phpstan 内部の `@include` を例外化して **phpstan 自体がクラッシュ**する（理由は `phpstan.neon` 冒頭コメント）。
  - 引数に変更したファイル/ディレクトリを渡せば速い。**自分が触ったPHPで新規エラーを出さない**こと。
  - 全体実行で出る5件（comment/openchat リポジトリ系）は**α無関係の既存エラー**。αコードは0件。

## 開発環境
`docker compose`（ハイフン無し）。Makefile管理:
```bash
make init                                   # 初期セットアップ
make up / down / restart / rebuild / ssh    # 基本環境（実LINEサーバへ）
make up-mock / down-mock / ...              # Mock環境（LINE Mock API同梱）
make up-mock-slow                           # 10万件・本番相当の遅延
make up-mock-cron                           # 自動クロール有効（30分:ja / 35分:tw / 40分:th）
make up-shared / shared-setup / down-shared # 共有モード
make ci-test                                # ローカルCI（Mockクロール+URLテスト。phpunitは含まない）
make show / help
```
- アクセス: HTTPS https://localhost:8443 ／ Mock https://localhost:8543 ／ MySQL localhost:3306 ／ phpMyAdmin http://localhost:8080 ／ LINE Mock API http://localhost:9000(外) `http://line-mock-api`(内)。
- 要件: Docker Compose V2、`mkcert`（CIでは不要）。
- **共有モード**: 別リポジトリの MariaDB/storage/comment-img を実体共有しコードはこちらで動かす2nd instance（`make up-shared`、`DATA_PROTECTION=true` で動く）。詳細 [`README.shared.md`](README.shared.md)。
- **CI/Codespaces**: `.github/workflows/ci.yml`（Mockクロール+URLテストのみ、**phpunitは実行しない**）、`build-images.yml`（ghcrへCI用プリビルドimage）。Codespacesはローカルと同等。

## アーキテクチャ
- **Backend**: 自作MVCフレームワーク MimimalCMS / PHP 8.5 / MySQL(MariaDB)＋SQLite / DIあり・生SQL（ORMなし）。
- **Frontend**: サーバPHPテンプレート＋埋め込みReact。TS/JS。MUI/Chart.js/Swiper。
- **主要ディレクトリ**: `/app`(MVC: `Config` ルーティング, `Controllers`(Api/Page), `Models` リポジトリ, `Services`(`Crawler/Config`), `ServiceProvider`, `Views`) / `/shadow`(フレームワーク) / `/batch`(cron・バッチ) / `/shared`(設定・DIマップ) / `/storage`(多言語データ・SQLite)。

## データベース
- **MySQL/MariaDB**: 主データ。**SQLite**: ランキング/統計（読み多用、データ種別ごとに `/storage` に分離）。設定は `local-secrets.php` から読まれる。
- **スキーマ変更（加算）**: `setup/schema/mysql/*.sql` を編集するだけ。デプロイ時に `batch/exec/sync_mysql_schema.php` が不足分を「追加だけ」自動反映（削除・型変更なし）。事前確認 `docker compose exec app php batch/exec/sync_mysql_schema.php --dry-run`。詳細 [`app/Services/Schema/README.md`](app/Services/Schema/README.md)。
- **コントローラからのDB**:
```php
use Shadow\DB;
DB::connect();
$stmt = DB::$pdo->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(\PDO::FETCH_ASSOC);
```

## 開発パターン
- **レイヤリング原則（必守）**: SQLは**必ずRepository**（`app/Models/`配下）に実装する。ロジックは**Service**、コントローラは薄く（受け取り→Service呼び出し→整形返却のみ、無駄に大きくしない）。**`batch/exec/` はServiceを呼ぶ最小限のエントリポイントだけ**（引数パース＋Service呼び出し＋出力程度。SQL・ビジネスロジック直書き禁止）。
- **DI**: インターフェースベース、`/shared/MimimalCmsConfig.php`。動的バインドは `/app/ServiceProvider/`（例 `OpenChatCrawlerConfigServiceProvider` が `AppConfig::$isMockEnvironment` で本番/mock切替）。
- **Autoload(PSR-4)**: `Shadow\\`→`shadow/`, `App\\`→`app/`, `Shared\\`→`shared/`。
- **設定**: `local-secrets.php`(gitignore) / `/shared/MimimalCMS_*.php` / `/app/Services/Crawler/Config/`。

## クローリング
- 設定クラス: `OpenChatCrawlerConfig`(本番・実URL) / `MockOpenChatCrawlerConfig`(`line-mock-api`)。Service Providerが自動切替。
- User Agent: `... Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/Open-Chat-Graph/Open-Chat-Graph)`。

## フロントエンド
- 別リポジトリ: ランキング(Open-Chat-Graph-Frontend) / グラフ(Frontend-Stats-Graph) / コメント(Comments)。
- **オプチャグラフα**: このリポジトリに統合済み `frontend/alpha`（React19+Vite+TS+Tailwind+shadcn/ui+SWR）。`make build-frontend:alpha` で `public/js/alpha` へビルド（成果物コミット＝git ベースデプロイ）。`/alpha` で配信（ja のみ）。α-APIは `app/Controllers/Api/AlphaApiController.php`＋`routing.php`（`MimimalCmsConfig::$urlRoot===''` ガード）。
  - **αの実装済み機能・UI/バックエンド構成・API/ルート/テーブル/バッチは [`ALPHA_README.md`](ALPHA_README.md) に集約。α に機能を追加/変更したら必ず ALPHA_README.md を更新する。**
  - αの通知/アラート毎時処理は `/admin/alphahourly`（admin認証）で手動実行できる（テスト用。結果は cronログ＝/admin/log/cron に残る）。
  - α規約: 重ね順は tailwind の `zIndex` トークン（生 `z-[NN]` 禁止。dropdown/select=popover>header）／入力は `text-base md:text-sm`(モバイル16px＝iOS拡大防止)／固定ヘッダ高さは ResizeObserver で実測／モーダル・上に重ねる画面は **ブラウザバックで閉じる**（`useBackDismiss` or URLルート駆動）。
- **ローカルPreactグラフ**: 別管理（source: `/home/user/oc-review-graph`）。ビルド済バンドルが `public/js/preact-chart/assets/index.js`。チャート変更後は再ビルドして配置。
- 統合: ReactをPHPテンプレートに埋め込み、ビルド済JSを配信。

## 新規ページ（MVC）
1. `/app/Config/routing.php` に `Route::path('your-path', [\App\Controllers\Pages\YourController::class, 'method']);`
2. `/app/Controllers/Pages/` にコントローラ（`DB::connect()`→クエリ→`$_meta = meta(); $_meta->title = '...';`→`return view('view_name', [...]);`）。`App\Models\Repositories\DB` を使う。view返却メソッドに戻り型は付けない。
3. `/app/Views/` に `.php` ビュー。変数直接参照（`$data`,`$_meta`）、ヘルパ `url()`/`t()`/`fileUrl()`。

## プルリクエスト
- **タイトルは一般人に伝わる言葉で**（SNSに出る）。コード用語(クラス/メソッド名)を避け、具体数値と業務影響を先に。
  - ❌ `fix: getMemberChangeWithinLastWeekCacheArray()の重複実行を防止`
  - ✅ `fix: 統計データ抽出クエリの重複実行を防止してDB負荷を軽減` / `perf: 日次更新のタイムアウトを解決（9〜11時間→1〜2時間）`
- **本文**: ①業務/ユーザー影響 ②技術的問題を平易に ③該当コードへのリンク ④対処内容。
- 用語: dailyTask=日次データ更新処理(毎日23:30) / hourlyTask=毎時ランキング更新(毎時30分)。
- **skip-ci**: PRに `skip-ci` ラベル or タイトル接頭 `skip-ci:`。`ci.yml`(Mockクロール+URLテスト)と deploy の「Check CI status」ゲートを飛ばすが、**デプロイ自体は止まらない**（マージで deploy は走る）。head=stg のPRは元から CI 自動スキップ。
- **skip-post**: `skip-post` ラベル/接頭でマージ後のX自動投稿だけ抑止（CI/デプロイは通常実行）。
