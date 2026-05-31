# オプチャグラフα (ALPHA)

本家オプチャグラフ([openchat-review.me](https://openchat-review.me))の上に乗る、有料/限定向けの「メタ」ツール。`/alpha` で配信、日本語(ja)専用。広告・SEO・初見導線に縛られず、本家のリスト品質を下回らずに「通知/アラート」と分析機能で差別化する。

> このドキュメントはαの実装済み機能とアーキテクチャの正典。**αに機能を追加/変更したら必ずここを更新する**（[`CLAUDE.md`](CLAUDE.md) 参照）。

## 実装済み機能

- 検索: キーワード＋カテゴリ絞り込み、ソート軸（人数 / 1時間・24時間・1週間増減 / 作成日）＋昇降トグル、検索条件の保存（localStorage）、無限スクロール、読み込みスピナー。カードは本家風のコンパクト行で、ソート軸に対応する増減を表示。
- 詳細: 丸アイコンヘッダ＋画像モーダル（URL駆動）、人数＋3期間の増減、Preactグラフ（人数＋順位線）、ランキング掲載履歴（遅延オーバーレイ・件数表示／0件はグレーアウト）、高次の考察ブロック、アクション（LINEで開く / マイリスト / この部屋の増減をアラート）。
- 高次の考察: 一目で分からない高次シグナルだけを提示。種類: 急上昇ランキング順位 / 公式ランキング（全体, 上位300位以内のみ）の推移・最高位 / 同カテゴリのメンバー数順位・シェア・規模 / 単日最大増加 / ペース異常。データ不足・下位・ノイズは黙る。
- 任意のN日増減 (`/period-growth`): キーワード(＋カテゴリ)一致のうち「N日前と現在の両方に統計がある部屋」に絞り、その期間の増減で並べる。期間 7/30/90/半年/1年/任意。本家ソートより長い任意期間＋「始点から継続」絞り込みが差別点。
- フォルダ統合グラフ (`/mylist/:folderId/chart`): マイリストのフォルダ配下の全部屋の人数推移を1グラフに重ねる(recharts)。期間 24時間/1週間/1ヶ月/全期間/任意。下のチェックで線の表示/非表示。
- マイリスト: フォルダ管理・並べ替え・一括操作・モバイル下部ナビ。フォルダ内に統合グラフ導線。
- アラート通知: 毎時クロール後のα処理(`alpha_hourly`)で3種検出 — ①アラートキーワードに一致する新しい部屋（LINE公式検索APIで未登録部屋も最速）②アラート対象部屋の±％ ③マイリスト全体の±％。通知タブは新着部屋＋増減を1本の時系列タイムラインに混在表示、未読バッジ・既読化。アラート設定(`/watch`)はキーワードとマイリスト全体の2つ。部屋ごとのアラートは詳細画面で設定/解除（解除は確認ダイアログ）。ユーザー識別はマイリストと同じCookie。
- Labs (`/labs`): 本家 `/oc/{id}` のアクセス数ランキング(GA4 PV)・検索流入ランキング(GSC クリック/表示/平均順位)。日次バッチ(`alpha_ga_sync`)で部屋別集計。設定の分析ツールから到達。
- PWA: manifest＋Service Worker（scope `/alpha`）。
- αトーン「Quiet Signal」: ダーク基調、アクセント=indigo–violet、数値=Sora(等幅)、本文=Noto Sans JP、shadcn/ui。用語は「アラート」に統一。

## UI アーキテクチャ (`frontend/alpha`)

- React 19 + Vite + TypeScript + Tailwind + shadcn/ui + SWR + React Router（basename: 本番`/alpha` / dev`/`）。
- 常駐ページは React 19 `<Activity>` の keep-alive（検索/マイリスト/通知/設定/period-growth/watch/labs）。詳細・掲載履歴・フォルダ統合グラフ・画像はURLルート駆動のオーバーレイ。
- モーダル/上に重ねる画面は全てブラウザバックで閉じる（`useBackDismiss`＋ルート駆動）。ドリルダウン到達ページ(period-growth/watch/labs)は固定タイトルバーに戻る矢印。
- 重ね順は `tailwind.config.js` の zIndex トークンが唯一の定義元（subheader/overlay/nav/header/sidebar/popover/modal。生 `z-[NN]` 禁止、dropdown/selectはpopover>header）。固定ヘッダ高さは ResizeObserver で実測し `--header-searchbar-h` に反映。入力は `text-base md:text-sm`（iOS拡大防止）。
- ビルド: `make build-frontend:alpha` → `public/js/alpha`（成果物コミット＝gitベースデプロイ）。
- 主要ディレクトリ: `src/{pages,components,api,hooks,lib,services,contexts,types}`。

## バックエンド アーキテクチャ (`app/`)

- 自作MVC MimimalCMS / PHP 8.5。α系は全て `MimimalCmsConfig::$urlRoot === ''`（ja）ガード。
- ルーティング: `app/Config/routing.php`（alpha-apiエンドポイント＋`alpha/*` SPAルート、ページ殻 `AlphaPageController`→`alpha_content.php`）。
- コントローラ: `app/Controllers/Api/AlphaApiController.php`。
- リポジトリ `app/Models/ApiRepositories/Alpha/`: OpenChat / Stats / PeriodGrowth / Insights / Alert / AccessRanking / QueryBuilder。
- サービス `app/Services/Alpha/`: InsightsService / AlertService / KeywordSearchClient(LINE公式検索) / GaClient(GA4/GSC)。
- データ源: MySQL `ocgraph_ocreview`(open_chat等) ＋ SQLite(statistics, statistics_ohlc, ranking_position_ohlc) ＋ `growth_ranking_*`。画像は obs CDN `https://obs.line-scdn.net/<hash>`。

### API エンドポイント

| メソッド | パス | 用途 |
|---|---|---|
| GET | `/alpha-api/search` | キーワード/カテゴリ/ソート検索 |
| GET | `/alpha-api/stats/{id}` | 基本情報（軽量） |
| GET | `/alpha-api/stats/{id}/graph` | グラフ時系列（人数/順位） |
| POST | `/alpha-api/batch-stats` | 複数IDの一括基本情報 |
| GET | `/alpha-api/ranking-history/{id}` | ランキング掲載履歴 |
| GET | `/alpha-api/insights/{id}` | 高次の考察 |
| GET | `/alpha-api/period-growth` | 任意N日増減 |
| GET/PUT | `/alpha-api/alerts/config` | アラート設定（キーワード/部屋/マイリスト） |
| GET | `/alpha-api/alerts` | 通知一覧（新着部屋＋増減） |
| GET | `/alpha-api/access-ranking` | Labs: アクセス数(GA4) |
| GET | `/alpha-api/search-ranking` | Labs: 検索流入(GSC) |

### 追加テーブル（加算のみ・`setup/schema/mysql/*.sql`＋`sync_mysql_schema.php`で反映）

- userlog: `alpha_keyword_watch` / `alpha_room_watch` / `alpha_mylist_threshold` / `alpha_keyword_seen` / `alpha_notification`
- ocreview: `alpha_room_access_daily`（GA4/GSC集計）

### バッチ / cron

- `batch/exec/alpha_hourly.php`: 毎時クロール後にアラート3種を算出・保存（ja限定、`cron_crawling.php`から）。
- `batch/exec/alpha_ga_sync.php`: 日次でGA4/GSCを部屋別集計（`SyncOpenChat::dailyTask()`末尾、設定済みのときのみ）。

### Labs の認証設定（GA4/GSC）

OAuth(installed) refresh_token方式（[`oc-pdca`] と同じ資格情報）。`local-secrets.php`(gitignore) に5値:
`$ga4PropertyId='373602810'` / `$gscSiteUrl='sc-domain:openchat-review.me'` / `$googleApiClientId` / `$googleApiClientSecret` / `$googleApiRefreshToken`。
本番反映: 同5値を本番local-secretsへ → スキーマ自動追加 → 日次cronで集計（初回 `php batch/exec/alpha_ga_sync.php --days=90` で遡及）。

## 重要な前提

- `DATA_PROTECTION=true`（本番データ環境）では mock/ci/自動cron/DB破壊操作を勝手にしない。スキーマ反映は加算のみで安全。
- 公開時に α 全体へ BASIC 認証ゲートを掛ける予定（現状は未適用）。
