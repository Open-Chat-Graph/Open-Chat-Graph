type ChartLimit = 0 | 8 | 31

type ChartMode = 'line' | 'candlestick'

type ChartArgs = {
  date: string[]
  graph1: (number | null)[]
  graph2: (number | null)[]
  time: (string | null)[]
  totalCount: (number | null)[]
  rankingOhlc?: RankingPositionOhlc[]
}

type ChartData = {
  date: (string | string[])[]
  graph1: (number | null)[]
  graph2: (number | null)[]
  time: (string | null)[]
  totalCount: (number | null)[]
}

type OpenChatChartOption = {
  label1: string
  label2: string
  category: string
  isRising?: boolean
}

type labelRangeLine = {
  dataMax: number;
  dataMin: number;
  stepSize: number|undefined;
}