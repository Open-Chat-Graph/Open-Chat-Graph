# オプチャグラフα (ALPHA)

本家オプチャグラフ([openchat-review.me](https://openchat-review.me))の上に乗る、有料/限定向けの「メタ」ツール。`/alpha` で配信、日本語(ja)専用。広告・SEO・初見導線に縛られず、本家のリスト品質を下回らずに「通知/アラート」と分析機能で差別化する。

> このドキュメントはαの実装済み機能とアーキテクチャの正典。**αに機能を追加/変更したら必ずここを更新する**（[`CLAUDE.md`](CLAUDE.md) 参照）。

## 実装済み機能

- 検索: キーワード＋カテゴリ絞り込み、ソート軸（人数 / 1時間・24時間・1週間増減 / 作成日）＋昇降トグル、検索条件の保存（localStorage）、無限スクロール。読み込み表示は全状態で同一の細いプログレスバーに統一（初回＝上部バー＋キャプション／再検索＝前回結果を薄くdim＋同じ上部バー／追加読み込み＝リスト末尾に同じバー。スピナーは使わない）。初期/空状態のランディングに「最近の検索」（自動履歴・チップ・タップで再検索・個別/一括削除）と「保存した検索条件」（タップで再検索・削除）を表示。カードは本家風のコンパクト行で、ソート軸に対応する増減を表示。
- 詳細: 丸アイコンヘッダ＋画像モーダル（URL駆動）、人数＋3期間の増減、Preactグラフ（人数＋順位線。操作タブ列の高さを `--chart-controls-h` で予約し描画完了時のレイアウトシフト無し）、ランキング掲載履歴（遅延オーバーレイ・件数表示／0件はグレーアウト）、高次の考察ブロック、アクション（LINEで開く / マイリスト）、増減アラート（枠上部の開閉トグル。未有効時は「解除」を出さず「ONにする」のみ／有効時は人数・％しきい値の編集と「解除」）。
- 詳細のアクセス・検索指標ブロック: 純PV / UU / SEO流入 / 参加リンク押下（うちSEO=Organic起点を併記）/ 平均滞在 ＋ 流入キーワード窓（GSC・多い順）＋ 参照元窓（GA4 pageReferrer・多い順、本家内からの遷移は「SEO経由」バッジ＝SEO動線で間接流入した部屋が分かる）。本家が出さない数字を出すαの中核価値。
- 高次の考察: 一目で分からない高次シグナルだけを提示。種類: 急上昇ランキング順位 / 公式ランキング（全体, 上位300位以内のみ）の推移・最高位 / 同カテゴリのメンバー数順位・シェア・規模 / 単日最大増加 / ペース異常。データ不足・下位・ノイズは黙る。
- 任意のN日増減 (`/period-growth`): キーワード(＋カテゴリ)一致のうち「N日前と現在の両方に統計がある部屋」に絞り、その期間の増減で並べる。**キーワードは任意（空欄＝全部屋対象。member降順の候補プール上限内）**。期間は**既定30日** ＋ カレンダーで開始〜終了の任意範囲（`PeriodRangePicker`、`allowAll={false}` で「全期間」非表示）。コントロールは**2段**（1段目: キーワード＋検索 / 2段目: カテゴリ＋並び順＋期間ピッカー）。期間は `PeriodValue`（`lib/period.ts`）で管理し `periodToParams` で API の `days` か `start`/`end` に変換。range指定時の `days` は実日数（+1）で統一。作成日/登録日も表示。APIレスポンスに `poolLimited`/`candidateLimit` を含み、件数がプール上限（3,000件）に達した場合はUIで補足注記＋リスト末尾に明示。**無限スクロール（useSWRInfinite＋page/limit。読み込み表示は検索・Labsと同一のプログレスバー＝初回上部バー／再取得dim＋同バー／末尾の追加読み込みバー）**。本家ソートより長い任意期間＋「始点から継続」絞り込みが差別点。
- フォルダ統合グラフ (`/mylist/:folderId/chart`): マイリストのフォルダ配下の全部屋の人数推移を1グラフに重ねる(recharts)。期間 24時間/1週間/1ヶ月/全期間/任意。下のチェックで線の表示/非表示。
- マイリスト: フォルダ管理・並べ替え・一括操作・モバイル下部ナビ。フォルダ内に統合グラフ導線。
- アラート通知: 毎時クロール後のα処理(`alpha_hourly`)で3種検出 — ①アラートキーワードに一致する新しい部屋（LINE公式検索APIで未登録部屋も最速。「新しい」＝ウォッチ作成以降にDB収録/初観測された部屋のみで、古い部屋が検索順位の揺れで上位に入っても通知しない。同部屋×同ウォッチは一度だけ）②アラート対象部屋の±％ ③マイリスト全体の±％。通知タブは新着部屋＋増減を1本の時系列タイムラインに混在表示、未読バッジ・既読化。アラート設定(`/watch`)はキーワードとマイリスト全体の2つ。部屋ごとのアラートは詳細画面で設定/解除（解除は確認ダイアログ）。ユーザー識別はマイリストと同じCookie。
- Labs (`/labs`): 本家 `/oc/{id}` のアクセス数ランキング(GA4 PV)・検索流入ランキング(GSC クリック/表示/平均順位)。**部屋と「ページ全体」(トップ/おすすめ等)を1つの並びに統合し指標降順**（ページは汎用アイコンで本家ページへ）。検索流入時は上位検索クエリ一覧も。日次バッチ(`alpha_ga_sync`)で部屋別集計。「分析」タブ（独立）から到達。「その他ページ」の入室数は流入PV比で按分した近似値（UI上に注記）。外部検索エンジンのルートURL referrer（`https://www.google.com/` 等）はホスト検査で除外しトップページ入室への誤計上を防止。
- PWA: manifest＋Service Worker（scope `/alpha`）。
- αトーン「Quiet Signal」: ダーク基調、アクセント=indigo–violet、数値=Sora(等幅)、本文=Noto Sans JP、shadcn/ui。用語は「アラート」に統一。

## UI アーキテクチャ (`frontend/alpha`)

- React 19 + Vite + TypeScript + Tailwind + shadcn/ui + SWR + React Router（basename: 本番`/alpha` / dev`/`）。
- 常駐ページは React 19 `<Activity>` の keep-alive（検索/マイリスト/通知/設定/period-growth/watch/labs）。詳細・掲載履歴・フォルダ統合グラフ・画像はURLルート駆動のオーバーレイ。
- モーダル/上に重ねる画面は全てブラウザバックで閉じる（`useBackDismiss`＋ルート駆動）。ドリルダウン到達ページ(period-growth/watch/labs)は固定タイトルバーに戻る矢印。
- 重ね順は `tailwind.config.js` の zIndex トークンが唯一の定義元（subheader/overlay/nav/header/sidebar/popover/modal。生 `z-[NN]` 禁止、dropdown/selectはpopover>header）。固定ヘッダ高さは ResizeObserver で実測し `--header-searchbar-h` に反映。入力は `text-base md:text-sm`（iOS拡大防止）。
- 画面骨格（固定サブヘッダ＋スクロール領域）は `components/Layout/ListScreen.tsx` に集約。ヘッダをスクロール外の flex 兄弟に置きカードの覗き込みを物理的に防ぐ（旧 `sticky -mt -mx` 方式を廃止）。利用ページ(labs/watch)は `KEEP_ALIVE_PAGES` で `scrollable:false`。`scrollResetKey` prop 変化で内部スクロールコンテナを先頭へ戻す（Labs タブ切替などで使用）。
- 無限スクロールは「ネットワーク1000件取得・描画30件ずつ reveal」分離方式（`hooks/useInfiniteReveal.ts`）。バッファに未表示分があれば即時 +30、使い切ったとき・hasMore があれば次の1000件をネットワーク取得。検索・Labs・period-growth 共通。一覧3画面（検索/period-growth/Labs）の `useSWRInfinite` は `revalidateIfStale: false`（`<Activity>` 復帰時の同一キー再検証を抑止し幽霊ローディングを防ぐ）。
- リスト取得の応答待ち表示は `components/Common/ListProgress.tsx` に集約し、3状態すべて同一の `ListProgressBar`（細い primary バー・スピナー不使用）に統一: 初回＝`ListProgressRegion` の上部バー＋キャプション／再取得＝`ListRefetchBar`（前回結果を薄くdim＋同じ上部バー）／追加読み込み（無限スクロール次ページ）＝`ListProgressFooter`（リスト末尾に同じバー。次ページETAは取れないので約900msの軽いindeterminate ramp）。検索・Labs・period-growth が共用し同一描画（先頭ページ進行値は `useListProgress`）。旧 `InfiniteScrollLoader`（Loader2スピナー）は廃止。
- ビルド: `make build-frontend:alpha` → `public/js/alpha`（成果物コミット＝gitベースデプロイ）。
- 主要ディレクトリ: `src/{pages,components,api,hooks,lib,services,contexts,types}`。

## バックエンド アーキテクチャ (`app/`)

- 自作MVC MimimalCMS / PHP 8.5。α系は全て `MimimalCmsConfig::$urlRoot === ''`（ja）ガード。
- ルーティング: `app/Config/routing.php`（alpha-apiエンドポイント＋`alpha/*` SPAルート、ページ殻 `AlphaPageController`→`alpha_content.php`）。
- コントローラ: `app/Controllers/Api/AlphaApiController.php`。
- リポジトリ `app/Models/ApiRepositories/Alpha/`: OpenChat / Stats / PeriodGrowth / Insights / Alert / AccessRanking / QueryBuilder。
- サービス `app/Services/Alpha/`: InsightsService / AlertService / KeywordSearchClient(LINE公式検索) / GaClient(GA4/GSC HTTPクライアント・トークン管理) / GaDataAggregator(GAレスポンス→集計配列の純粋変換) / GaSyncService(GA日次同期・page_jump再構築の本体ロジック) / LineUrlFormatter(LINE URL変換) / ReferrerFormatter(リファラ解析) / AlphaPagePathNormalizer（referrer/pagePath→内部ページ正規化の唯一の定義元。テスト: `app/Services/Alpha/test/AlphaPagePathNormalizerTest.php`）。
- **レイヤリング原則（CLAUDE.md「開発パターン」・必守）**: SQLは必ずRepository、ロジックはService、`batch/exec/` はServiceを呼ぶ最小エントリのみ、コントローラは薄く。alpha_hourly.php / alpha_ga_sync.php / alpha_rebuild_page_jump.php がこの形の手本。
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
| GET | `/alpha-api/period-growth` | 任意N日増減（無限スクロール: `page`(1始) / `limit`、応答に `page`/`hasMore`。access-ranking と同形） |
| GET/PUT | `/alpha-api/alerts/config` | アラート設定（キーワード/部屋/マイリスト） |
| GET | `/alpha-api/alerts` | 通知一覧（新着部屋＋増減） |
| GET | `/alpha-api/access-ranking` | Labs rooms タブの唯一の入口。部屋集合は常に PV>0 で固定し `sort` で並びだけ切替（`pageviews`=アクセス数 / `seo_total`=SEO合計(直接+間接) / `jump_clicks`=入室数）。各部屋に全指標＋`keywords`(流入KW top8)。流入KW集計は `getRoomsTopKeywords` が SQL の `ROW_NUMBER()` で部屋ごと上位8件に絞りDBで完結（全件転送なし）。`scope=pages` で非部屋ページ（`jumpClicks`/`jumpClicksOrganic` 含む） |
| GET | `/alpha-api/search-ranking` | Labs: 検索流入(GSC)。pages タブ seo 用途のみ（rooms は access-ranking に一本化） |
| GET | `/alpha-api/search-query-ranking` | Labs: 上位検索クエリ(GSC) |
| GET | `/alpha-api/eta` | 汎用ETA（リスト系プログレスバー用。type=period-growth/access-ranking/search-ranking/search-query-ranking）。検索だけは従来の `/alpha-api/search-eta` |
| GET | `/alpha-api/room-metrics/{id}` | 詳細: 1部屋のGA/GSC指標（PV/UU/SEO/参加クリック/エンゲージ＋流入キーワード＋リファラ元＋SEO起点の参加クリック） |

### 追加テーブル（加算のみ・`setup/schema/mysql/*.sql`＋`sync_mysql_schema.php`で反映）

- **全αテーブルは userlog DB・テーブル名 `_ja` サフィックス**（2026-06-06 移設。言語はサフィックス方式＝多言語化時は `_tw` 等を増設。userlog は言語共有DBだがテーブル名で分離）。αテーブルへのクエリは `UserLogDB::`、`open_chat` 等 ocreview 側との跨ぎ JOIN は ocreview 側だけを実行時DB名（`AppConfig::$dbName['']`）で修飾（`DB::execute` 自動再接続対策）。マイリスト本体 `oc_list_user` はαのテーブルではないためサフィックス無し。
- ユーザー系(7): `alpha_keyword_watch_ja` / `alpha_room_watch_ja` / `alpha_mylist_threshold_ja` / `alpha_keyword_seen_ja` / `alpha_search_seen_room_ja` / `alpha_search_timing_ja` / `alpha_notification_ja`
- GA集計系(6): `alpha_room_access_daily_ja`（GA4/GSC集計。`jump_clicks_organic`=参加クリックのうちOrganic Searchセッション由来）／ `alpha_page_access_daily_ja`（非部屋ページ）／ `alpha_search_query_daily_ja`（上位検索クエリ）／ `alpha_room_search_query_daily_ja`（部屋別 流入検索クエリ）／ `alpha_room_referrer_daily_ja`（部屋別 リファラ元）／ `alpha_page_jump_daily_ja`（非部屋ページの入室数近似・日次事前集計。算出: 部屋の当日 jump_clicks を「その部屋の当日リファラ PV 中、該当ページ由来の割合」で按分した近似値。分母は外部・direct を含む全リファラ PV でページ合計≦部屋合計が保証される。`alpha_ga_sync.php` が各日書込み後に自動更新。初回投入・再集計は `batch/exec/alpha_rebuild_page_jump.php` でバックフィル）
- **本番デプロイ後の手動移行（必須・1回）**: ①schema-sync が userlog に `_ja` 13枚を自動作成（空） ②データコピー: 集計系5枚（page_jump除く）を `INSERT INTO userlog.alpha_xxx_ja SELECT * FROM ocreview.alpha_xxx`（旧行に NULL があり新テーブルは NOT NULL のため、失敗時は NOT NULL カラムを `COALESCE(col, 0)` で埋めて SELECT）、ユーザー系7枚を `INSERT INTO userlog.alpha_xxx_ja SELECT * FROM userlog.alpha_xxx`（DB名は本番の実名） ③`php batch/exec/alpha_rebuild_page_jump.php`（全期間・新按分ロジックで再構築） ④動作確認後、旧13テーブル（ocreview の集計6＋userlog の旧名7）を手動DROP。**これを忘れると Labs/通知が空データで動く**

### バッチ / cron

- `batch/exec/alpha_hourly.php`: 毎時クロール後にアラート3種を算出・保存（ja限定、`cron_crawling.php`から）。
- `batch/exec/alpha_ga_sync.php`: 日次でGA4/GSCを部屋別集計（`SyncOpenChat::dailyTask()`末尾、設定済みのときのみ）。各日の room/referrer 書込み後に `rebuildPageJumpDaily` を呼び `alpha_page_jump_daily` を自動更新。
- `batch/exec/alpha_rebuild_page_jump.php`: `alpha_page_jump_daily` の派生バックフィル CLI。`alpha_room_referrer_daily` に存在する全日付（または `--from`/`--to` 指定範囲）を再集計（GA再取得不要・DB内完結）。

### Labs の認証設定（GA4/GSC）

OAuth(installed) refresh_token方式（[`oc-pdca`] と同じ資格情報）。`local-secrets.php`(gitignore) に5値:
`$ga4PropertyId='373602810'` / `$gscSiteUrl='sc-domain:openchat-review.me'` / `$googleApiClientId` / `$googleApiClientSecret` / `$googleApiRefreshToken`。
本番反映: 同5値を本番local-secretsへ → スキーマ自動追加 → 日次cronで集計（初回 `php batch/exec/alpha_ga_sync.php --days=90` で遡及）。
access_tokenは `storage/ja/alpha_ga_token.json`（gitignore済み）にキャッシュし、有効期限60秒前まで再利用（cron毎のrefresh不要）。refresh応答にrefresh_tokenが含まれる場合（Google rotation）はストアを自動更新して永続化。書込不可環境ではSecretsConfigのrefresh_tokenで毎回refresh（従来動作）にフォールバック。**invalid_grant 時はストアを破棄し SecretsConfig 値で1回自動リトライ**（local-secrets 差し替え後の自動回復）。refresh は `alpha_ga_token.json.lock` への flock で排他＋double-checked 再読込（cron 並走時の競合防止）。

## 重要な前提

- `DATA_PROTECTION=true`（本番データ環境）では mock/ci/自動cron/DB破壊操作を勝手にしない。スキーマ反映は加算のみで安全。
- 公開時に α 全体へ BASIC 認証ゲートを掛ける予定（現状は未適用）。

---

# 作業再開メモ（最終更新: 2026-05-31）

別セッションでもここから再開できるよう、現状・未実装（指示済み）・この先のフェーズを集約する。**進めたら随時このセクションを更新する。**

## 開発の前提・コマンド

- フロントビルド: `cd frontend/alpha && npm run build`（`tsc -b` 込み）→ 成果物 `public/js/alpha` をコミット（gitベースデプロイ）。型だけなら `npx tsc --noEmit`。
- PHPStan（PHP変更時は適宜通す）: `docker compose exec app php vendor/bin/phpstan analyse --autoload-file=phpstan-bootstrap.php [<変更パス>]`。**`--autoload-file` 必須**（無いとアプリのエラーハンドラでクラッシュ。理由は `phpstan.neon` 冒頭）。既存5件のα無関係エラーは無視可・αは0件を維持。
- スキーマ加算反映: `docker compose exec app php batch/exec/sync_mysql_schema.php --dry-run` → 反映。GA集計の手動実行: `... php batch/exec/alpha_ga_sync.php --days=30`。アラート毎時の手動実行: `/admin/alphahourly`（admin認証）。
- ローカル動作確認: `curl -sk https://localhost:8443/alpha-api/...`。UIはヘッドレスChrome（CDP `:9222`）でスマホ390/PC1280を撮影・実測（例: グラフ枠高さの before/after）。
- UIは出す前に3名体制レビュー（操作撮影／frontend-designスキル／スクショ矛盾・動線）。生 `z-[NN]` 禁止＝tailwind zIndexトークン。

## 直近セッションで完了済み（コミット済み・実画面検証済み）

- **【#31 完了】画面表示状態カーネル** — `src/lib/viewNavigation.ts`（宣言的ビュー表＋enter/reclick/back 正規化）＋`hooks/useViewNavigation.ts` に集約。全タブがここを経由。reclick は LayoutContext の `resetNonces`(パネル別) を bump → keep-aliveパネルを `key` で強制再マウント＋scrollTop。enter は記憶（検索クエリ／フォルダ／分析サブ画面 `analysisLastSub`）を復元。検索→period-growth「みる」も `bumpReset('period-growth')` で同契約。旧 `useNavigationHandler`/`useScrollToTopOnReclick` 廃止。※ヘッドレス実機検証はMCP Chrome未接続のため未実施＝ユーザー目視待ち。
- **【#33/#34/#38-Labs 完了】Labs 4タブ再構築** — アクセス/検索流入/検索KW/その他ページ(非オプチャ)。各行に 合計＋うちSEO経由（アクセス数PV・うちSEO／SEO流入合計=直接+間接・内訳／入室数=参加リンク押下・うちSEO）。無限スクロール全件（useSWRInfinite＋limit+1のhasMore）。期間=既定30日＋カレンダー＋全期間（PeriodRangePicker再利用）。検索条件ヘッダ sticky(z-subheader)。連番は固定列廃止→名前行頭の小番号で横スペース節約。BE: access/search-ranking に per-row全指標＋resolveWindow＋ページング＋scope=pages、search-query-ranking も範囲＋ページング。間接SEO=`getRoomIndirectSeo`相当を fetchRanking の LEFT JOIN で。4タブ実画面確認済(390)。**残: #40d-d の中間ページ経由KWアトリビューション（要GA/GSC拡張）。**
- **【#38 詳細部分 完了】詳細メトリクスの期間指定** — 7/30トグル→`ui/period-range-picker.tsx`（プリセット7/30/90＋カレンダー＋全期間、Popover/z-popover、Labsでも再利用予定）。既定30日。バックエンドは `resolveWindow(start,end,days,all)`＋`getRoomMetrics/SearchQueries/Referrers` を fromDate/toDate 受けに変更、応答に `fromDate/toDate/days`。`lib/period.ts`(PeriodValue/periodToParams/periodKey/periodLabel)を共通定義に。**残: Labs(access/search ranking)側の期間ピッカー対応（#33/#34と一緒に）。**
- **【#40c 完了】参照元の文言網羅＋チップ** — `formatReferrer`/`internalReferrerLabel` を本家URLパターン別の人間可読ラベルに（トップ/ランキング/検索結果「語」/おすすめタグ「タグ」/他の部屋/このページ内(自己参照)/新着/コメント/ラボ/検索エンジン名）。`keyword=tag:◯◯`→おすすめ扱い。応答に `detail`(全文) 追加。フロントは省略表示＋`ui/info-chip.tsx`(Radix Popover/Portal/z-popover)でタップ・ホバーに全文＋元URL。**残: 「他の部屋(ID:◯)」「おすすめタグ(slug)」の name 解決（部屋名/タグ表示名のlookup）は未対応＝今は ID/slug 表示。**

- 詳細のSEO深掘り（流入キーワード窓・参照元窓「SEO経由」バッジ・参加リンクのOrganic起点内訳）＋バックエンド（新テーブル `alpha_room_search_query_daily`/`alpha_room_referrer_daily`、`alpha_room_access_daily.jump_clicks_organic`、`alpha_ga_sync` 拡張、`room-metrics` 応答拡張）。実データ検証済み（例: 参加1,146中1,099がSEO起点）。
- 詳細のアラート枠を開閉トグル化（未有効時は解除を出さない）。
- グラフ描画のレイアウトシフト解消（`--chart-controls-h` を `index.css` に一元定義、`#graph-box #app` に予約。実測 before=after）。
- Labs: 部屋とページ全体を1つの並びに統合（指標降順）。`LabsPagesSection` 廃止。
- 指定期間ランキング: キーワード空＝全件対象（route/controller 両方の `keyword` を `emptyAble` 化）。
- PHPStan を実行可能化＋運用ルール化（上記コマンド）。

## 2026-05-31 追加対応（コミット済み）

- Labs固定ヘッダを上に密着（-mt相殺）。期間プリセットは「30日＋全期間＋カレンダー」のみ（7/90日削除＝`PERIOD_DAY_PRESETS`）。
- 参照元チップはホバー廃止→クリックで開く・外側クリックで閉じる（`ui/info-chip.tsx`）。URL＋全文ラベル表示。
- 参照元から自己参照「このページ内」を除外（`getRoomReferrers` で self を NOT LIKE）。間接SEOも自己参照除外済。
- 「他の部屋（◯◯）」に部屋名解決（`getRoomNames`）。
- **【重要・要バックフィル】GA/GSC集計のtw/th混入を修正**（`AlphaGaClient`：`isOtherLocalePath`＋`normalizePageScopePath`でtw/th除外、`extractOpenChatId`が`/th/oc/{id}`をja部屋IDへ誤畳みしていたのを阻止、`fetchTopSearchQueries`にGSC page除外フィルタ）。**既存蓄積データは混在のまま→クリーンには alpha_search_query_daily / alpha_room_* / alpha_page_* を消して `alpha_ga_sync --days=N` で再集計が要る（ユーザー許可が要る破壊操作なので未実行）。**
- 30日と全期間が同値に見えるのはデータが約30日分(2026-05-01〜)しか無いため（バグでない。日数が貯まれば差が出る）。
- 【保留】検索KWのタイ語が「Thai部屋(ja上)」由来か「/th混入」かは、上記修正で/th混入を断つ。再集計後に残れば前者（正当）。

## GA/GSC トークン失効（2026-05-31・要ユーザー対応）

- 症状: cron/同期で `Token has been expired or revoked`（HTTP400）。`AlphaGaClient::getAccessToken` は refresh_token 交換あり＝ロジックは存在。**refresh_token 自体が失効**。
- 対応(ユーザー): refresh_token 再発行→local-secrets 更新／OAuth同意画面を本番(Production)化（テストだと7日で失効＝再発）。
- 直後にやる: `alpha_ga_sync --days=2` で直近補正 → truncate+`--days=30` で ja限定クリーン再集計（#33のtw/th除外修正が効く）。**トークンが通るまで truncate 厳禁**（取得失敗時に本番消失）。プローブはupsert故に無害。

## 未実装（追加・2026-05-31）

- **Labsの部屋ランキング(アクセス/検索流入)をキーワードでも絞り込み**（部屋名/キーワード）。access/search-ranking に keyword パラメータ＋WHERE。
- **PWAキャッシュで新ビルドが反映されない件**（ユーザーが何度も旧ビルドを見る）。SWは `registerType:autoUpdate`＋/js/alpha は StaleWhileRevalidate＋precache。ファイル名ハッシュ無し(index.js固定)。要: 反映を1リロードで確実にする（ファイル名ハッシュ化 or JSをNetworkFirst）。今はハードリフレッシュ回避で運用。

## 未実装（ユーザー指示済み・このあと実装）

優先度順。番号は会話中のタスク番号。

1. **【最重要・基盤】画面表示状態カーネル（#31）** — タブ状態保持／同タブ再押下で状態破棄→トップ／オーバーレイ／スクロール復元が各所にバラバラ。1ファイル `src/lib/viewNavigation.ts` に宣言的ルール表＋ `useViewNavigation()` を作り全タブが経由する。
   - 散在の現状: `App.tsx`(KEEP_ALIVE_PAGES＋`<Activity>`)／`hooks/useNavigationHandler.ts`(宛先ごと個別ルール)／`hooks/useScrollToTopOnReclick.ts`(`state.timestamp`時のみ・querySelector依存)／`MobileBottomNav`は `/`,`/mylist`,`/settings` だけ特別扱い。
   - 設計: ビュー宣言`{key,rootPath,lastSubRoute?,overlay?}`（分析は最後の `/period-growth`|`/labs` を記憶し復元）。インテントを `enter`/`reclick`/`back` に正規化しここだけで分岐。**reclick→ルートへ`replace`＋`state:{reset:nonce}`→keep-aliveパネルは nonce を `key` に混ぜ強制再マウント＋scrollTop**（全タブ統一）。`enter`→記憶サブルート復元。`back`→`history.idx>0`なら`navigate(-1)`。
   - これで直る既知バグ: マイリスト二度押しでトップに戻らない／分析タブが前回サブ画面を覚えてない／period-growth「みる」遷移でスクロールが残る／検索⇄他タブの状態喪失感。
   - 注意: ナビ中核なので全画面に影響。ヘッドレスで「タブ往復で状態保持」「二度押しでトップ＆再描画」まで実遷移検証。
2. **Labsカードに合計/間接の指標（#33）** — 各カードに「SEO流入（合計・間接）」「参加リンク押下（合計・SEO経由＝間接含む）」を表示。検索流入も同様で、検索流入リストは間接含む。**バックエンド要**: `access-ranking`/`search-ranking` 応答へ per-room/page の `jumpClicks`/`jumpClicksOrganic`/間接SEO（本家内リファラ起点）を載せる。`alpha_room_referrer_daily`＋`alpha_room_access_daily` から集計。「間接」の定義＝本家内(openchat-review.me)リファラ経由の流入＝SEOで来た人が回遊して到達。
   - **【2026-05-31 追加指示】アクセス流入ランキングでも「SEO経由合算表示」にし各項目に "うちSEO経由 X" を出す（詳細の合計／うちSEO経由と同じ表現）。指標に「入室数（参加リンク押下＝jump_clicks）」も加える。** つまり各ランキング行＝合計値＋"うちSEO経由"を、アクセス数・検索流入・入室数の各指標で表示。
   - **【完了 2026-06-01】検索流入(seo)ソートの是正＋カード文言＋流入KW＋pagesうちSEO経由**:
     - A. rooms タブを `access-ranking` に一本化。`sort='seo_total'` を追加し、部屋集合は常に PV>0（having `SUM(a.pageviews)>0`）で固定、`sort` で並びだけ切替（pageviews/seo_total/jump_clicks）。`seo_total`=SELECT `(SUM(search_clicks)+MAX(COALESCE(ref.indirect_seo,0)))` を ORDER。これで「検索流入」もアクセス数と同じ部屋集合で SEO合計降順になる。`getSearchRanking` は pages タブ seo 用途のみ温存。controller sort 許可・ETAキー(ar)に seo_total 追加、フロント `RankingParams.sort`/`EtaParams.sort` に `'seo_total'`、LabsPage は metric→sort 写像で全て access-ranking 経由。
     - C. カード文言: アクセス数 stat の「うちSEO …」sub を削除（SEO流入(合計)と重複）。入室数 stat の sub を「うちSEO経由（間接含む）X」に明示（rooms/pages とも）。
     - D. 各部屋カードに**流入キーワードをカンマ区切りで列挙**（`line-clamp-2`・無ければ非表示）。BE: `getRoomsTopKeywords`（部屋ID群を1クエリ・各部屋 clicks 降順 top8。N+1回避）で `LabsRankingRoom.keywords:string[]` を付与。pages は per-page KW データが無いので非表示。
     - B. pages（その他ページ）の入室数に `jumpClicksOrganic`（うちSEO経由・間接含む 近似）を追加。`RankingPageMetric.jumpClicksOrganic`。走査倍化を避けるため重い ORDER は jump_clicks 1本のまま、organic は表示分(≤limit・jump>0)だけ `getPagesJumpOrganic` で1クエリ補完。
     - E.（保留・要データ拡張）「トップの入室数をトップを直接 or 直接SEOで開いて最終的に入室した分に絞る」は landing(channel)＋conversion funnel をセッション単位で要するが、現集計テーブルに無い（page別の direct/organic landing も join funnel も未収集）。誤った近似は入れず**現 proxy（参照元=トップの部屋の jump 合計）を維持**。厳密化には GA4 のセッション/landingPagePath×sessionDefaultChannelGroup×参加イベントの funnel 収集（新テーブル）が必要。
3. **Labs 検索KWを別タブ化＋無限スクロール（#34）** ※決定: **4タブ＝アクセス/検索流入/検索KW/その他ページ(非オプチャ)**。主指標は**タブごとに変える**おまかせ（各行=主指標＋小さく「うちSEO経由」）。**ランキング行の左の連番(rank)が右に無駄な大マージンを作っているのを是正**（番号で幅を食わない詰めたレイアウトに）。 — 流入クエリ一覧を「アクセス/検索」とは別タブに。件数(limit)セレクタ廃止し**無限スクロールで全件**。検索条件ヘッダを通常検索と同様に上部固定。
   - **【2026-05-31 追加指示】部屋（openchat）以外のページのエンティティは別枠タブに分離して出す**（以前アクセス数・検索流入のリストに openchat 以外のページも混ぜたが、やはりオプチャ以外は別タブに、分かりやすい方法で）。タブ構成イメージ: 「アクセス」「検索流入」「検索キーワード」＋「その他ページ（非オプチャ）」。表示は**無限スクロールで全件**・期間は**既定30日**＋カレンダーで任意期間選択。
4. **【完了 2026-06-01】リスト全般にETAプログレスバー（#35）→ 全状態のバー統一＋period-growth無限スクロール（#39）** — 既存の検索ETA方式を汎用化。フックは `hooks/useListProgress.ts`（旧 `components/Search/useSearchProgress` を一般化し置換）、表示は `components/Common/ListProgress.tsx`。**#39 で読み込み表示を全状態同一の `ListProgressBar` に統一**: 初回＝`ListProgressRegion` 上部バー＋キャプション／再取得＝`ListRefetchBar`（前回結果を薄くdim＋同じ上部バー。旧 `ListRefetchOverlay` のスピナーを廃止）／追加読み込み＝`ListProgressFooter`（リスト末尾に同じバー。次ページETAは取れないので約900msのindeterminate ramp。旧 `InfiniteScrollLoader` のLoader2スピナーを廃止し削除）。**period-growth を useSWR→useSWRInfinite 化**し IntersectionObserver で次ページ取得（filterKey変化で `setSize(1)` リセット。Labs/Searchと同パターン）。BE: `periodGrowth`＋`AlphaPeriodGrowthRepository` に `page`/`offset`/`limit` ＋ `hasMore`（ソート済み全行を offset/limit で切り出し残件で判定）を追加、応答に `page`/`hasMore` を載せる（access-ranking と同形）。ETA時間で 0→90% 漸近・応答到着で必ず100・到着前は100にしない。バックエンドは `alpha_search_timing`（既存テーブル・スキーマ変更なし）を任意 query_key で再利用。各リスト処理が実 wall time を record し、汎用 `GET /alpha-api/eta?type=...` が同一規則のキーで見込みを返す。queryKey は「種別接頭辞（pg/ar/sr/sq）＋正規化条件を `|` 連結」、190超は安定ハッシュへ。フォールバック中央値は同種別（接頭辞 LIKE）に絞る。record と eta は `AlphaApiController::buildListEtaKey` が唯一の組み立て元。
5. **【完了 2026-06-01】アラート設定画面の拡充（#36）** — `/watch`(`WatchSettingsPage`) に①キーワードのアラートを画面内の入力＋カテゴリ選択で追加（一覧から削除も）②設定済みルーム一覧（部屋名は `batch-stats` で解決・しきい値表示・個別解除）③最終更新ヒント＋保存/キャンセルを置いた上部 sticky ヘッダーバー（`top-0 z-subheader`、生z-[NN]不使用）を実装。保存は従来どおり PUT 全置き換え（rooms も温存）。
6. **【完了 2026-06-01】マイリスト変動アラート3パターン（#37）** — `/watch` のマイリスト変動セクションで scope=全体/ルート直下のみ/特定フォルダ を選択。しきい値は部屋詳細と**同じ %/人数 プルダウン**（新 `components/Notifications/ThresholdInput.tsx` に共通化し `WatchRoomControl` と共用）。スキーマ加算: `alpha_mylist_threshold` に `up_member`/`down_member`/`scope`/`target_oc_ids`(JSON) を追加。フォルダ構造は localStorage のみでサーバに無いため、対象 open_chat_id 集合はフロント（`mylistScope.ts`）が解決し PUT で送り cron はそれを直接使う（scope='all'/旧行は従来どおり `oc_list_user` 全体にフォールバック）。**二重通知回避**: `AlphaAlertService::computeMylistMovements` が、同毎時に部屋単体(type='room')で通知済みの (user_id, open_chat_id) を `getRoomNotificationKeys($hourBucket)` で集め、マイリスト側はそれをスキップ（部屋単体を優先・どちらか一方のみ）。**デプロイ時に schema-sync で4列追加が要る（dry-run確認済み・+4 column）。**
7. **詳細/Labs 指標の期間プルダウン（#38）** — 詳細のアクセス・検索指標を**既定「過去30日」**にし、カレンダー（開始〜終了）で範囲指定。**カレンダーでは全期間まで選べる**こと＋**どこかに「全期間」ボタン**を置く。Labsも同様（既定30日＋カレンダー＋全期間ボタン）。期間に含まれるデータは全部集計。**バックエンド**: room-metrics/ranking に開始-終了日(date range)パラメータを追加（`*_daily` を範囲SUM。全期間＝下限なし）。
8. **【完了 2026-06-01】検索の履歴・保存クエリ（#30/#39）** — 検索の初期/空状態ランディング `components/Common/SearchLanding.tsx` に「最近の検索」（自動履歴）と「保存した検索条件」を表示。履歴は新サービス `services/searchHistory.ts`（localStorage `alpha_search_history`・上限12件・キーワード＋カテゴリで重複排除し先頭繰り上げ・空キーワードは残さない）、SearchPage が検索完了時に自動追記。保存クエリは既存 `services/savedSearches.ts`/`SavedSearchControls`（ヘッダの保存UI）をそのまま流用しランディングでも一覧/再適用/削除。どちらもタップで再検索（キーワード＋カテゴリ＋ソート復元）・個別削除（履歴は一括消去も）。重複実装なし。
10. **詳細 SEO 内訳 第3波（#40d・2026-05-31）** — (a)「このページ内」(自己参照)は **SEO経由バッジを出さない**（isInternal=false。再読込/グラフ操作でありSEO流入ではない）。(b) 参照元ラベルの**おすすめにタグ名**を出す＝「おすすめ（下ネタ）」（1行に。`/recommend/{tag}` も `keyword=tag:◯◯` も）。(c) **詳細「SEO流入」タイルに間接SEO**を出す：直接GSCクリックが0でも本家内SEOページ経由(間接)が多い場合があるので 合計＝直接+間接、sub「直接X・間接Y」。間接=`getRoomIndirectSeo`(本家内リファラPV、自己参照除外)。(d)【要設計・よしなに】**中間ページ(おすすめ等)経由の流入キーワードと、その語経由でroomに到達した件数**も知りたい＝room←おすすめ(tag)←Google検索語 のアトリビューション。per-pageの検索クエリ(中間ページのGSC query)が必要で現状データが足りない可能性＝GA/GSC取得拡張を検討。まずは出せる範囲で。
9. **詳細の SEO 内訳をさらに拡充（#40・2026-05-31 追加指示）** — 部屋詳細（例 `/alpha/openchat/209299`）で現在「参加リンク押下 626回・うちSEO経由 587回」「平均滞在時間 3秒」を表示中。**平均滞在時間も "うちSEO経由 X秒" を出す**（GAをsource=organicでセグメントした平均滞在/エンゲージ時間）。**入室数指標**も同様に合計／うちSEO経由で。**バックエンド**: `alpha_ga_sync` で organic セグメントの avg engagement time を取得し `alpha_room_access_daily` 等に列追加、`room-metrics` 応答へ。
   - **参照元（referrer）にページタイトル＋URLを出す（#40b）** — 現状の参照元窓に「ページタイトル」と「URL」が無い。タイトルを主表示にし、幅制限ではみ出す分は省略（truncate）、**タップ/ホバーでチップ(tooltip)に全貌**。`alpha_room_referrer_daily` の referrer 値を人間可読ラベル＋原URLに整形して返す。
   - **本家内リンク元のパターン文言を網羅設計する（#40c・2026-05-31）** — 「グラフ内ページ」「SEO経由」だけだと“どこ”か不明。本家(openchat-review.me)内のリンク元は限られるので各URLパターンを人間可読ラベルにマップ（**1行要約＋チップに詳細URL**）。想定パターン: トップ `/`→「トップページ」／別オープンチャット `/oc/{id}`(本家詳細)→「他の部屋『{name}』」／ランキング `/ranking?...`→「ランキング（{条件名}）」／検索結果 `/?...`/検索→「検索結果『{kw}』」／タグ/おすすめ→「おすすめ（{タグ名}）」／カテゴリ→「カテゴリ『{名}』」／外部→元URLそのまま／参照元なし(direct)→「直接/アプリ流入」／organic(google等)→「SEO（{エンジン}）」。各行は省略表示、チップで原URL＋正式ラベル全文。

## この先のフェーズ（推奨順）

- フェーズA（基盤）: #31 カーネル → 既存バグ一掃。続けて #35 プログレスバー共通化（カーネルの上に乗せると綺麗）。
- フェーズB（Labs深化・バックエンド）: #33 合計/間接指標 → #34 別タブ＋無限スクロール → #38 期間レンジ（詳細＆Labs）。
- フェーズC（アラート）: #36 設定画面拡充 → #37 マイリスト3パターン＋二重通知回避（スキーマ拡張）。
- フェーズD（検索体験）: #30/#39 履歴・保存クエリ。
- 公開前: BASIC認証ゲート（#10）。

## 既知の地雷（再開時に踏むな）

- `alpha_page_jump_daily` の集計ロジックを変更した後は **`docker compose exec app php batch/exec/alpha_rebuild_page_jump.php`（全期間）を本番で手動再実行**すること。未実行の場合、過去分が旧ロジック値のまま残り時系列に段差が出る。
- PHP 8.5: `curl_close()` を呼ばない（strictエラーハンドラが非推奨を例外化）。
- ルートの `matchStr`/`matchNum` は**コントローラの Validator とは別物**。空許可は両方に `emptyAble` が要る（period-growth空キーワードの404はこれが原因だった）。
- opcache は `validate_timestamps=On`/`revalidate_freq=2`。PHP変更は約2秒で反映（それでも怪しければ `apache2ctl -k graceful`）。
- 複数DB跨ぎはDDL/クエリを `` `db`.`table` `` 修飾（`DB::execute` 再接続が既定DBに戻る）。
