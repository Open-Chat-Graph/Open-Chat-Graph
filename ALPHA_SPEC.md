# オプチャグラフα 横断ロジック仕様書（負債返済の基準）

粗削りで増築してきた横断システムの「あるべき契約（intended）」を確定し、現状コードの逸脱（負債）を洗い出して返済する基準にする。**ここに書いた契約が正。コードが違っていたら直す。** 機能仕様の網羅は [`ALPHA_README.md`](ALPHA_README.md)、これは“ロジックの設計契約”に絞る。

---

## 1. 画面ナビ＆オーバーレイ＆keep-alive

### 契約
- タブ（検索/マイリスト/分析/通知/設定）は keep-alive（React19 `<Activity>`）で常駐。表示切替で**状態（スクロール/入力/取得済データ）は保持**する。
- 詳細・フォルダ統合グラフ・画像は「上に被せるオーバーレイ」。**被せるだけで、下のタブは再読込しない**（ブラウザバックで閉じるとオーバーレイが外れるだけ）。
- タブ操作のインテントは1箇所（`lib/viewNavigation.ts` + `useViewNavigation`）で `enter`/`reclick`/`back` に正規化。`reclick`＝同タブ再押下のみ「ルートへ戻し再マウント＋スクロール先頭」。`enter`＝記憶状態を復元（再マウントしない）。`back`＝オーバーレイを閉じる。
- 再マウント（リセット）は `reclick` でのみ起きる。**タブ復帰・オーバーレイを閉じた復帰では一切リセット/再読込しない。**
- 画面骨格（固定サブヘッダ＋スクロール領域）は `ListScreen` 1つ。覗き込み禁止（ヘッダはスクロール外のflex兄弟）。

### 重要な前提（落とし穴）
- **`<Activity mode="hidden">` は state/ref を保持するが、エフェクトは破棄→visibleで再実行する（mount相当）。** よって「visible復帰でエフェクトが再走る」前提で全フックを書くこと。requestKey等が変わっていないのに再走る＝**新規イベントとして誤発火させてはいけない**。

### 現状の負債（D-1〜）
- **D-1（ユーザー報告・最優先）**: 検索→詳細→ブラウザバックで「一瞬 dim フィルタ」が出る。原因＝`useListProgress` の requestKey エフェクトが Activity 再表示で再走り、実フェッチが無いのに `setActive(true)`→最小表示で再取得バー(dim)が約450ms出る。**契約違反（被せ復帰で読み込み表示を出さない）。** 修正＝requestKey の“値の変化”でのみ起動（ref で前回値を覚え、再mountの同値では起動しない）。SWRも復帰時に不要な revalidate をしない（revalidateOnMount/IfStale 制御）。

---

## 2. リスト取得＆無限スクロール＆プログレス

### 契約
- 取得は useSWRInfinite。**ネットワークは1ページ300件、画面reveelは30件ずつ**（`useInfiniteReveal`：バッファから30件reveal、使い切って hasMore のときだけ次の300件をfetch）。
- 検索条件（filterKey）が変わったら先頭ページへ（setSize(1)＋visibleCount=30）。
- 読み込み表示は1種類のバー（`ListProgressBar`）に統一：①初回（結果0）＝上部バー＋キャプション ②再取得（結果あり・条件変更）＝淡いdim＋同じ上部バー ③追加読み込み＝末尾に同じバー。スピナーは使わない。
- ETA：`alpha_search_timing` にクエリ別 wall time を記録し次回見込みに。応答到着で100へ、到着前は90頭打ち。**最小表示時間は“実フェッチがある時のみ”**。
- **実フェッチが無い再描画（タブ復帰/オーバーレイ閉じ/Activity再mount）では progress を一切起動しない。**

### 現状の負債
- **D-1（再掲）**: useListProgress が Activity 再mountで誤起動。
- **D-2**: period-growth の候補が `CANDIDATE_LIMIT=3000` で頭打ち＋`fetchCandidates` が offset 非対応。頻出/全件で3000件超に無限スクロールで到達不能、`hasMore`/`totalMatched` も3000基準で誤る。→ 仕様として上限を明示しUIで止めるか、ソート確定後のページングの限界を是正。

---

## 3. ランキング＆指標セマンティクス（Labs / 詳細）

### 契約
- rooms ランキングは**1つの母集合（`HAVING SUM(pageviews)>0`）**。指標プルダウン＝**並び替え軸の切替のみ**：`pv`=pageviews / `seo`=seo_total(直接searchClicks+間接seoIndirect) / `jump`=jump_clicks。母集合は変えない（同じ部屋が並び順だけ変わる）。
- 間接SEO＝本家内SEOページ経由PV（自己参照除外）。日付fan-outに対し `MAX(COALESCE(ref.indirect_seo,0))` で1:1値を保持（SUMで水増ししない）＝**これは正しい実装**。
- ページ入室数（その他ページ）＝「そのページを参照元として到達した部屋の jump 合計」の近似。**日次事前集計 `alpha_page_jump_daily`** に持つ（リクエスト毎LIKE禁止）。
- 期間は `resolveWindow`：days(既定30)/range(start,end)/all(最古〜最新)。`days` は実日数（+1）。

### 現状の負債
- **D-4**: ページ入室数が、部屋が複数ページから流入していると各ページに同じ部屋の当日jumpを**全量二重計上**（`rebuildPageJumpDaily`）。構造的水増し。→ 流入元ページ数で按分 or 「ページ別入室」を別計測。最低でも近似である旨をUI明示（現状「うちSEO経由（間接含む）」は付けたが二重計上の注記は無し）。

---

## 4. アラート（毎時処理）

### 契約
- 検出3種：①KW一致の新部屋 ②部屋しきい値(±%/人数) ③マイリスト変動（全体/ルート/フォルダの3スコープ、±%/人数）。
- **二重通知回避**：部屋単体としきい値が、マイリスト該当スコープにも含まれる場合、どちらか一方（部屋優先）。room→mylist の実行順で同毎時の (user,oc) を除外。

### 現状の負債（中〜低）
- **D-6**: 二重通知 dedup の `getRoomNotificationKeys` が direction を問わず (user,oc) を集めるため、同毎時に部屋が up 通知済みなら mylist の down 条件も抑止。実害は低い（同毎時に同部屋がup/down両立は稀）が、契約「どちらか一方」とは整合。要確認のみ。

---

## 5. GA/GSC トークン

### 契約
- access_token はストアにキャッシュ（プロセス跨ぎ）し期限内は再取得しない。refresh_token rotation は自動永続化。書込不可なら SecretsConfig 読取にフォールバック。
- refresh は `.lock` ファイルへの flock(LOCK_EX) で排他（本体JSONはrename差し替えのため不可）。ロック取得後に double-checked 再読込し、他プロセスが refresh 済みなら HTTP を叩かない。fopen/flock 失敗時はロックなしで劣化続行。
- ストア由来の refresh_token（SecretsConfig 値と異なる）が invalid_grant → ストアを破棄し SecretsConfig 値で1回だけ再試行（local-secrets 差し替え後の自動回復）。それでも invalid_grant＝OAuth再同意のみ（Google仕様）。
- ja限定：GSC/GA4 はドメイン全体なので `/tw`,`/th` を全フェッチで除外（`isOtherLocalePath`/page正規化）。

### 現状の負債（中）
- **D-9**: referrer→page 正規化が `AlphaGaClient::normalizePageScopePath` と `AlphaAccessRankingRepository::normalizeReferrerToPagePath` で**二重定義**。乖離リスク＝共通化。

---

## 返済の優先順（提案）
1. **D-1** バック/タブ復帰の phantom ローディング（ユーザー報告・契約の根幹）
2. **D-4** ページ入室数の二重計上（数字の正しさ）
3. **D-2** period-growth 3000件頭打ち＋offset非対応（無限スクロールの正しさ）
4. **D-7** トークン invalid_grant 自動回復
5. **D-5/D-3/D-9/D-8/D-6** 一貫性・性能・重複定義・競合

各返済はこの仕様の契約に照らして直し、直したら該当の負債項目を消し込む。
