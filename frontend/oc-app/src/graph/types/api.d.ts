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

interface MemberOhlc {
  date: string
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
 * /oc/{id}/chart のレスポンス。表示ビュー（span×sort×scope×mode）の描画に必要な系列を一括返却する
 *
 * - date/member: hour は時刻ラベル+毎時メンバー数、day は日付+日次メンバー数
 * - time/position/totalCount: sort が none 以外のときの順位系列（無い場合は空配列）
 * - memberOhlc/positionOhlc: mode=candlestick のときのみ
 */
interface ChartResponse {
  date: string[]
  member: (number | null)[]
  time: (string | null)[]
  position: (number | null)[]
  totalCount: (number | null)[]
  memberOhlc?: MemberOhlc[]
  positionOhlc?: RankingPositionOhlc[]
  meta?: ChartMeta
}

interface RankingPositionOhlc {
  date: string
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
