// 詳細成長分析（/analysis）の型。グローバル宣言（ApiTypes.d.ts と同様）。

type AnalysisMetric = 'increase' | 'steady'
type AnalysisPeriod = 'month' | '3month' | '6month' | 'year' | 'all' | 'custom'
// increase: count(増加数) / rate(増加率) ・ steady: score(じわじわ度) / cagr(年率) / slope(勢い)
type AnalysisSort = 'count' | 'rate' | 'score' | 'cagr' | 'slope'
type AnalysisOrder = 'asc' | 'desc'

type AnalysisParams = {
  metric: AnalysisMetric
  period: AnalysisPeriod
  from: string
  to: string
  category: number
  keyword: string
  sort: AnalysisSort
  order: AnalysisOrder
}

// API(/analysis-result)が返す1部屋。OpenChat の表示フィールド＋指標の生数値。
interface AnalysisItem {
  id: number
  name: string
  desc: string
  member: number
  img: string
  emblem: 0 | 1 | 2
  joinMethodType: 0 | 1 | 2
  category: number
  // 期間増加 metric
  diff?: number
  pct?: number | null
  base?: number
  // じわじわ成長 metric
  score?: number
  cagr?: number | null
  r2?: number
  slope?: number
  historyDays?: number
  // 先頭要素のみ（page 0）
  totalCount?: number
}

type AnalysisJobPhase = 'idle' | 'running' | 'done' | 'error' | 'canceled'

interface AnalysisStatusResponse {
  done: boolean
  percent: number
  computed: number
  total: number | null
}
