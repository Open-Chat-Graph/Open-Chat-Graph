type RankingPositionChartArgDto = {
  id: number
  categoryKey: number | null
  categoryName: string
  baseUrl: string
  urlRoot: string
}

/** ランキング種別(ranking/rising)×カテゴリ(in/all)毎に順位データが存在するか */
type PositionAvailability = {
  ranking_in: boolean
  ranking_all: boolean
  rising_in: boolean
  rising_all: boolean
}

/** タブ・ボタン出し分け用の可用性メタデータ（初回ロードの meta=1 レスポンスに同梱） */
type ChartMeta = {
  startDate: string
  endDate: string
  /** 日次データの件数（期間タブの出し分けに使用） */
  dateCount: number
  /** 期間タブ毎にローソク足(OHLC)データが存在するか */
  ohlcAvailability: { week: boolean; month: boolean; all: boolean }
  /** 最新24時間タブに表示できる毎時メンバー数データが存在するか */
  hourAvailability: boolean
  /** 期間タブ毎の順位データ有無 */
  positionAvailability: {
    hour: PositionAvailability
    week: PositionAvailability
    month: PositionAvailability
    all: PositionAvailability
  }
}

/**
 * メンバー数OHLC（ローソク足1本）の値。日付は持たず、共通の ohlcDate 配列と index で整合する。
 * （各要素に date を持たせると ChartResponse.ohlcDate と重複するため）
 */
interface MemberOhlc {
  open_member: number
  high_member: number
  low_member: number
  close_member: number
}

interface ErrorResponse {
  error: {
    code: string
    message: string
  }
}

/**
 * /oc/{id}/chart のレスポンス。表示ビュー（span×sort×scope×mode）の描画に必要な系列だけを返す。
 *
 * - 折れ線(line): date（hourは時刻ラベル）＋ member。順位ON時は position/totalCount。
 *   time は急上昇(rising)のときだけ付く（ランキングは終日時刻を持たないため省略。無い＝時刻表示なし）。
 * - ローソク足(candlestick): ohlcDate ＋ memberOhlc（順位ON時は positionOhlc）だけを返す。
 *   日次の date / member 折れ線は使わないので付かない（ohlcDate が OHLC 専用の日付軸）。
 *   memberOhlc・positionOhlc は ohlcDate と同順・同長（positionOhlc の null はその日が圏外＝順位OHLCなし）。
 *
 * 各フィールドは要求した層のときだけ付く（layer別の差分フェッチと共通形）。
 */
interface ChartResponse {
  date?: string[]
  member?: (number | null)[]
  time?: (string | null)[]
  position?: (number | null)[]
  totalCount?: (number | null)[]
  ohlcDate?: string[]
  memberOhlc?: MemberOhlc[]
  positionOhlc?: (RankingPositionOhlc | null)[]
  meta?: ChartMeta
}

/**
 * 順位OHLC（ローソク足1本）の値。日付は持たず、共通の ohlcDate 配列と index で整合する。
 * low_position が null の日はその日に圏内記録が無い（圏外）。
 */
interface RankingPositionOhlc {
  open_position: number
  high_position: number
  low_position: number | null
  close_position: number
}

type ToggleChart = 'rising' | 'ranking' | 'none'

type urlParams = {
  category: 'in' | 'all'
  bar: ToggleChart
  limit: 'hour' | 'week' | 'month' | 'all'
  chart: 'line' | 'candlestick'
}

type urlParamsName = keyof urlParams
type urlParamsValue<T extends urlParamsName> = urlParams[T]
