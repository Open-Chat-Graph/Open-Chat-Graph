# オプチャグラフα 横断ロジック仕様書（設計契約）

横断システムの「あるべき契約（intended）」を確定する文書。**ここに書いた契約が正。コードが違っていたら直す。** 機能仕様の網羅は [`ALPHA_README.md`](ALPHA_README.md)、これは“ロジックの設計契約”に絞る。

運用: コードの逸脱（負債）を見つけたら該当セクションに「現状の負債」として追記し、返済したら消し込む。初版の負債 D-1〜D-9 は 2026-06-06 に全返済済み。

---

## 1. 画面ナビ＆オーバーレイ＆keep-alive

### 契約
- タブ（検索/マイリスト/分析/通知/設定）は keep-alive（React19 `<Activity>`）で常駐。表示切替で**状態（スクロール/入力/取得済データ）は保持**する。
- 詳細・フォルダ統合グラフ・画像は「上に被せるオーバーレイ」。**被せるだけで、下のタブは再読込しない**（ブラウザバックで閉じるとオーバーレイが外れるだけ）。
- タブ操作のインテントは1箇所（`lib/viewNavigation.ts` + `useViewNavigation`）で `enter`/`reclick`/`back` に正規化。`reclick`＝同タブ再押下のみ「ルートへ戻し再マウント＋スクロール先頭」。`enter`＝記憶状態を復元（再マウントしない）。`back`＝オーバーレイを閉じる。
- 再マウント（リセット）は `reclick` でのみ起きる。**タブ復帰・オーバーレイを閉じた復帰では一切リセット/再読込しない。**
- 画面骨格（固定サブヘッダ＋スクロール領域）は `ListScreen` 1つ。覗き込み禁止（ヘッダはスクロール外のflex兄弟）。

### 重要な前提（落とし穴）
- **`<Activity mode="hidden">` は state/ref を保持するが、エフェクトは破棄→visibleで再実行する（mount相当）。** よって「visible復帰でエフェクトが再走る」前提で全フックを書くこと。requestKey等が変わっていないのに再走る＝**新規イベントとして誤発火させてはいけない**。実装上の防御は2枚: `useListProgress` は requestKey の“値の変化”でのみ起動（ref で前回値を記憶）、SWR は `revalidateIfStale: false` で同一キーの復帰時再検証を抑止。

---

## 2. リスト取得＆無限スクロール＆プログレス

### 契約
- 取得は useSWRInfinite。**ネットワークは1ページ300件、画面reveelは30件ずつ**（`useInfiniteReveal`：バッファから30件reveal、使い切って hasMore のときだけ次の300件をfetch）。
- period-growth の集計対象は**メンバー数上位3000件のプール**（`CANDIDATE_LIMIT`）。仕様としての上限であり、頭打ち時は API が `poolLimited`/`candidateLimit` を返し、UI は件数補足とリスト末尾注記で「上位3,000件を対象に集計」と明示する。`hasMore`/`totalMatched` はプール内基準。
- 検索条件（filterKey）が変わったら先頭ページへ（setSize(1)＋visibleCount=30）。
- 読み込み表示は1種類のバー（`ListProgressBar`）に統一：①初回（結果0）＝上部バー＋キャプション ②再取得（結果あり・条件変更）＝淡いdim＋同じ上部バー ③追加読み込み＝末尾に同じバー。スピナーは使わない。
- ETA：`alpha_search_timing` にクエリ別 wall time を記録し次回見込みに。応答到着で100へ、到着前は90頭打ち。**最小表示時間は“実フェッチがある時のみ”**。
- **実フェッチが無い再描画（タブ復帰/オーバーレイ閉じ/Activity再mount）では progress も SWR 再検証も一切起動しない**（`revalidateIfStale: false`）。

---

## 3. ランキング＆指標セマンティクス（Labs / 詳細）

### 契約
- rooms ランキングは**1つの母集合（`HAVING SUM(pageviews)>0`）**。指標プルダウン＝**並び替え軸の切替のみ**：`pv`=pageviews / `seo`=seo_total(直接searchClicks+間接seoIndirect) / `jump`=jump_clicks。母集合は変えない（同じ部屋が並び順だけ変わる）。
- 間接SEO＝本家内SEOページ経由PV（自己参照除外）。日付fan-outに対し `MAX(COALESCE(ref.indirect_seo,0))` で1:1値を保持（SUMで水増ししない）＝**これは正しい実装**。
- ページ入室数（その他ページ）＝部屋の当日 jump を**内部ページ流入PV比で按分**した近似。分母は全referrer PV（外部・direct含む）のため外部由来分は内部ページに帰属させない（ページ合計≦部屋合計）。**日次事前集計 `alpha_page_jump_daily`** に持つ（リクエスト毎LIKE禁止）。UIにも「按分した近似値」と明示。
- 期間は `resolveWindow`：days(既定30)/range(start,end)/all(最古〜最新)。`days` は実日数（+1）。

---

## 4. アラート（毎時処理）

### 契約
- 検出3種：①KW一致の新部屋 ②部屋しきい値(±%/人数) ③マイリスト変動（全体/ルート/フォルダの3スコープ、±%/人数）。
- ①の「新着」定義（両経路で対称）: 登録済み部屋＝`open_chat.created_at`（DB収録日時）`>= watch.created_at` のみ通知（古い部屋が検索順位の揺れで top20 入りしても通知しない。skip 時も seen 記録）／未登録部屋＝`alpha_search_seen_room.first_seen_at >= watch.created_at`。kw系 dedup_key は (watch,emid) で hourBucket 非含有＝**同部屋×同ウォッチは生涯一度だけ**。未登録→後日DB収録も seen 共有で二重通知しない。
- **二重通知回避**：部屋単体としきい値が、マイリスト該当スコープにも含まれる場合、どちらか一方（部屋優先）。room→mylist の実行順で同毎時の (user,oc) を除外。**dedup は方向(up/down)を問わない＝同部屋・同毎時は1通のみ**（同毎時に up/down が両立するのは再実行やデータ更新レースに限られ、その場合も通知を増やさないのが意図的仕様）。

---

## 5. GA/GSC トークン

### 契約
- access_token はストアにキャッシュ（プロセス跨ぎ）し期限内は再取得しない。refresh_token rotation は自動永続化。書込不可なら SecretsConfig 読取にフォールバック。
- refresh は `.lock` ファイルへの flock(LOCK_EX) で排他（本体JSONはrename差し替えのため不可）。ロック取得後に double-checked 再読込し、他プロセスが refresh 済みなら HTTP を叩かない。fopen/flock 失敗時はロックなしで劣化続行。
- ストア由来の refresh_token（SecretsConfig 値と異なる）が invalid_grant → ストアを破棄し SecretsConfig 値で1回だけ再試行（local-secrets 差し替え後の自動回復）。それでも invalid_grant＝OAuth再同意のみ（Google仕様）。
- ja限定：GSC/GA4 はドメイン全体なので `/tw`,`/th` を全フェッチで除外（`isOtherLocalePath`/page正規化）。
- referrer/pagePath→内部ページ正規化は `AlphaPagePathNormalizer::normalize()` が**唯一の定義元**（GaClient と AccessRankingRepository が共用。再重複定義禁止）。空文字入力は null（GA行の欠損をトップに混入させない）。完全URLは自サイトホスト（`AppConfig::$siteDomain`、www許容）のみ受理し、外部ホストは null（Google/Yahoo等のルートURL referrer を「トップページ経由」に誤計上しない）。

---

## 返済済みの負債（記録）
- **D-1〜D-9**（初版 2026-06-01 で列挙）: 2026-06-06 に全返済。内訳＝幽霊ローディング(D-1)／3000件上限の仕様化(D-2)／流入KWのSQL上位N化(D-3)／ページ入室数のPV比按分(D-4)／range日数+1(D-5)／通知dedupの契約明文化(D-6)／GAトークン自動回復(D-7)／flock排他(D-8)／正規化の共通クラス統合(D-9)。返済中に発覚した「外部検索エンジンのルートURL referrer がトップページ流入に誤計上」も同時修正。
