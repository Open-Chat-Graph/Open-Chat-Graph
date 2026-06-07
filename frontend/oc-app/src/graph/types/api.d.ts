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

type StatisticsChartDto = {
  date: string[]
  member: (number | null)[]
  startDate: string
  endDate: string
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

interface RankingPositionChart {
  date: string[]
  member: (number | null)[]
  time: (string | null)[]
  position: (number | null)[]
  totalCount: (number | null)[]
}

interface RankingPositionOhlc {
  date: string
  open_position: number
  high_position: number
  low_position: number | null
  close_position: number
}

type ChartApiParam = 'ranking' | 'ranking_all' | 'rising' | 'rising_all'
type ToggleChart = 'rising' | 'ranking' | 'none'
type PotisionPath = 'position_hour' | 'position'

type urlParams = {
  category: 'in' | 'all'
  bar: ToggleChart
  limit: 'hour' | 'week' | 'month' | 'all'
  chart: 'line' | 'candlestick'
}

type urlParamsName = keyof urlParams
type urlParamsValue<T extends urlParamsName> = urlParams[T]
