type RankingPositionChartArgDto = {
  id: number
  categoryKey: number | null
  categoryName: string
  baseUrl: string
  urlRoot: string
}

type StatisticsChartDto = {
  date: string[]
  member: (number | null)[]
  startDate: string
  endDate: string
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

type ChartApiParam = 'ranking' | 'ranking_all' | 'rising' | 'rising_all'
type ToggleChart = 'rising' | 'ranking' | 'none'
type PotisionPath = 'position_hour' | 'position'

type urlParams = {
  category: 'in' | 'all'
  bar: ToggleChart
  limit: 'hour' | 'week' | 'month' | 'all'
}

type urlParamsName = keyof urlParams
type urlParamsValue<T extends urlParamsName> = urlParams[T]
