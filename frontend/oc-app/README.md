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
| ローソク・人数のみ | `member,memberOhlc` | `?span=day&sort=none&scope=all&category={cat}&mode=candlestick&series=member,memberOhlc&from={from}&to={to}` |
| ローソク・ランキング | `member,memberOhlc,positionOhlc` | `?span=day&sort=ranking&scope=in&category={cat}&mode=candlestick&series=member,memberOhlc,positionOhlc&from={from}&to={to}` |
| ローソク・急上昇 | `member,memberOhlc,positionOhlc` | `?span=day&sort=rising&scope=all&category={cat}&mode=candlestick&series=member,memberOhlc,positionOhlc&from={from}&to={to}` |
| 最新24時間（折れ線） | （series無し・全24h） | `?span=hour&sort=ranking&scope=in&category={cat}&mode=line` |
| 初回ロード（埋め込みメタ無し） | （series無し・全期間） | `?span=day&sort=ranking&scope=in&category={cat}&mode=line&meta=1` |

---

## レスポンスの型

```ts
interface ChartResponse {
  date: string[]                 // 全系列共通の日付（hour は時刻ラベル）軸
  member: (number | null)[]      // date と同順・同長のメンバー数

  // 順位（折れ線の重ね描き。sort≠none のときだけ付く）
  position?: (number | null)[]   // date と同順・同長（圏外は0、未取得日は null）
  totalCount?: (number | null)[] // 同上（その日の総件数）
  time?: (string | null)[]       // 急上昇(rising)のときだけ。順位を記録した時刻(HH:MM)

  // ローソク足（mode=candlestick のときだけ付く）
  ohlcDate?: string[]            // OHLC専用の日付軸（date の部分集合）。下2つはこれと index 整合
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
| `date` / `member` | 常に |
| `position` / `totalCount` | sort≠none（折れ線の順位） |
| `time` | sort=rising のときだけ |
| `ohlcDate` / `memberOhlc` | mode=candlestick |
| `positionOhlc` | mode=candlestick かつ sort≠none |
| `meta` | 初回ロード（meta=1）のときだけ |

---

## 約束ごと（壊さないこと）

- `date` は全系列で共通の1本の軸。`member`・`position`・`totalCount`・`time` はこれと index 整合。
- `time` は急上昇(rising)のときだけ返す。ランキング・人数のみ・24時間・ローソク足では付かない
  （時刻を持たない／x軸が時刻のため）。フロントは「`time` が無い＝時刻表示なし」として扱う。
- OHLC は `ohlcDate` という別の日付軸を1本だけ持ち、`memberOhlc` と `positionOhlc` はどちらも
  これと同順・同長で揃える（各要素に date を持たせて二重・三重にしない）。`positionOhlc` の `null`
  はその日が圏外（順位OHLCなし）で、描画では0（圏外）として埋める。
- `meta` は初回ロード時のみ。操作後の層別フェッチには含めない。
