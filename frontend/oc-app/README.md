# oc-app（ルーム個別ページのグラフ）

ルーム個別ページ `/oc/{id}` に表示する統計グラフ（メンバー数・順位の履歴。折れ線／ローソク足）の
フロントエンド。このドキュメントは「どの表示状態のときに、グラフAPIへどんなリクエストを送り、
どんな形のデータが返ってくるか」をまとめた早見表。

実装の対応箇所:
- リクエスト組み立て・層キャッシュ・描画呼び出し … `src/graph/util/fetchRenderer.ts`
- レスポンスの型 … `src/graph/types/api.d.ts`
- 描画クラス … `src/graph/classes/OpenChatChart.ts`

エンドポイントはすべて `GET /oc/{id}/chart`。バックエンドは `App\Services\Chart\OpenChatChartApiService`。

---

## 表示状態を決める要素

| 要素 | 値 | 説明 |
| --- | --- | --- |
| `mode`（chart） | `line` / `candlestick` | 折れ線 / ローソク足 |
| `sort`（bar） | `none` / `ranking` / `rising` | 順位の重ね描き（none=メンバー数のみ） |
| `scope` | `in` / `all` | 順位の集計範囲（in=カテゴリ内 / all=すべて） |
| `span` | `day` / `hour` | 日次 / 最新24時間（hour は折れ線の「24時間」タブのみ） |
| `limit` | `week` / `month` / `all` / `hour` | 期間タブ（描画範囲。hour は span=hour） |
| `category` | 数値 | 部屋のカテゴリID（未掲載・その他は0） |

`scope` は「sort≠none かつ カテゴリ選択が all 以外」のときだけ `in`、それ以外は `all`。
`span` は「折れ線かつ24時間タブ」のときだけ `hour`、それ以外は `day`（ローソク足は常に day）。

---

## 2つの取得経路

### 1. 初回ロード
- ページHTMLに可用性メタ `#chart-meta` が埋め込まれていれば、それを使い `meta=1` を撃たずに
  下記「層別フェッチ」で必要な系列だけ取得する（4DBへのCOUNTを表示経路から外す）。
- 埋め込みが無い部屋（未生成）だけ、従来どおり `meta=1` で全期間＋可用性メタをまとめて取得する。

### 2. 操作後（タブ・順位ON/OFF・モード切替）
表示状態を「層」に分解し、層ごとに `?series=…&from=…&to=…` で足りない期間だけ取りに行く。
共有できる層は再取得しない（例: 人数を見たあと順位ONしても member は取り直さず position だけ取得）。
最新24時間（span=hour）だけは層キャッシュ対象外で、毎回 series 無しで全24時間を取得する。

層キャッシュのキー:
- `member`
- `position|{sort}|{scope}`
- `memberOhlc`
- `positionOhlc|{sort}|{scope}`

---

## 表示状態ごとのリクエストと層

`{cat}` は category、`{from}`/`{to}` は `Y-m-d`。共通パラメータは `span,sort,scope,category,mode`。

| 表示状態 | 送る series（操作後） | 送るクエリ例 |
| --- | --- | --- |
| 折れ線・人数のみ | `member` | `?span=day&sort=none&scope=all&category={cat}&mode=line&series=member&from={from}&to={to}` |
| 折れ線・ランキング | `member,position` | `?span=day&sort=ranking&scope=in&category={cat}&mode=line&series=member,position&from={from}&to={to}` |
| 折れ線・急上昇 | `member,position` | `?span=day&sort=rising&scope=all&category={cat}&mode=line&series=member,position&from={from}&to={to}` |
| ローソク・人数のみ | `memberOhlc` | `?span=day&sort=none&scope=all&category={cat}&mode=candlestick&series=memberOhlc&from={from}&to={to}` |
| ローソク・ランキング | `memberOhlc,positionOhlc` | `?span=day&sort=ranking&scope=in&category={cat}&mode=candlestick&series=memberOhlc,positionOhlc&from={from}&to={to}` |
| ローソク・急上昇 | `memberOhlc,positionOhlc` | `?span=day&sort=rising&scope=all&category={cat}&mode=candlestick&series=memberOhlc,positionOhlc&from={from}&to={to}` |
| 最新24時間（折れ線） | （series無し・全24h） | `?span=hour&sort=ranking&scope=in&category={cat}&mode=line` |
| 初回ロード（埋め込みメタ無し） | （series無し・全期間） | `?span=day&sort=ranking&scope=in&category={cat}&mode=line&meta=1` |

ローソク足は OHLC 層だけを取る（`member` 折れ線は引かない＝統計DBアクセスを発生させない）。
ローソク足は常に全期間取得し、期間タブの本数はクライアント側でスライスする。

---

## レスポンスの型

```ts
interface ChartResponse {
  // 折れ線(line)用。ローソク足では付かない
  date?: string[]                // 日次の日付（hour は時刻ラベル）軸
  member?: (number | null)[]     // date と同順・同長のメンバー数

  // 順位（折れ線の重ね描き。sort≠none のときだけ付く）
  position?: (number | null)[]   // date と同順・同長（圏外は0、未取得日は null）
  totalCount?: (number | null)[] // 同上（その日の総件数）
  time?: (string | null)[]       // 急上昇(rising)のときだけ。順位を記録した時刻(HH:MM)

  // ローソク足(candlestick)用。ohlcDate が OHLC 専用の日付軸（折れ線の date とは別系列）
  ohlcDate?: string[]            // OHLC のある日付（昇順）。下2つはこれと index 整合
  memberOhlc?: MemberOhlc[]      // ohlcDate と同順・同長
  positionOhlc?: (RankingPositionOhlc | null)[] // ohlcDate と同順・同長。null=その日は圏外

  meta?: ChartMeta               // 初回ロード（meta=1）のときだけ
}

// OHLC の値（日付は持たない。共通の ohlcDate と index で対応づける）
interface MemberOhlc {
  open_member: number
  high_member: number
  low_member: number
  close_member: number
}
interface RankingPositionOhlc {
  open_position: number
  high_position: number
  low_position: number | null    // null=その日に圏内記録なし（圏外）
  close_position: number
}
```

`ChartMeta`（タブ・ボタンの出し分け用。`#chart-meta` 埋め込み or `meta=1`）の定義は
`src/graph/types/api.d.ts` を参照。

### どのフィールドがどの状態で付くか

| フィールド | 付く条件 |
| --- | --- |
| `date` / `member` | 折れ線(line)のみ（ローソク足では付かない） |
| `position` / `totalCount` | 折れ線 かつ sort≠none |
| `time` | 折れ線 かつ sort=rising のときだけ |
| `ohlcDate` / `memberOhlc` | ローソク足(candlestick) |
| `positionOhlc` | ローソク足 かつ sort≠none |
| `meta` | 初回ロード（meta=1）のときだけ |

---

## 約束ごと（壊さないこと）

- 折れ線は `date` が共通軸で、`member`・`position`・`totalCount`・`time` はこれと index 整合。
- ローソク足は `ohlcDate` が唯一の軸で、`memberOhlc`・`positionOhlc` はこれと同順・同長
  （各要素に date を持たせない＝二重・三重にしない）。`positionOhlc` の `null` はその日が圏外
  （順位OHLCなし）で、描画では0（圏外）として埋める。
- ローソク足は折れ線の `date` / `member` を返さない・取得しない（OHLC は別系列で、日次 member 折れ線は
  描かないため。統計DBへの無駄なアクセスと date/ohlcDate の二重を避ける）。
- `time` は急上昇(rising)のときだけ返す。ランキング・人数のみ・24時間・ローソク足では付かない
  （時刻を持たない／x軸が時刻のため）。フロントは「`time` が無い＝時刻表示なし」として扱う。
- `meta` は初回ロード時のみ。操作後の層別フェッチには含めない。
