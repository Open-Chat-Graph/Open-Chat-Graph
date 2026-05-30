export interface OpenChat {
  id: number
  name: string
  desc: string
  member: number
  img: string
  emblem: 0 | 1 | 2
  category: number
  categoryName: string  // カテゴリ名
  join_method_type: number  // 0: 全体公開, 1: 承認制, 2: 参加コード入力制
  increasedMember: number  // 1時間の差分
  percentageIncrease: number  // 1時間の増加率
  diff24h: number  // 24時間の差分
  percent24h: number  // 24時間の増加率
  diff1w: number  // 1週間の差分
  percent1w: number  // 1週間の増加率
  createdAt?: number | null  // 作成日
  registeredAt?: string  // 登録日
  isInRanking: boolean  // ランキング掲載されているか
  url?: string  // LINE OpenChat URL
}

export interface SearchResponse {
  data: OpenChat[]
  totalCount: number
}

export interface SearchParams {
  keyword?: string
  category?: number
  page?: number
  limit?: number
  sort?: 'member' | 'created_at' | 'hourly_diff' | 'diff_24h' | 'diff_1w'
  order?: 'asc' | 'desc'
}

// 基本情報のみ（軽量）- /alpha-api/stats/{id}
export interface BasicInfoResponse {
  id: number
  name: string
  currentMember: number
  category: number
  categoryName: string
  description: string
  thumbnail: string
  emblem: 0 | 1 | 2
  hourlyDiff: number | null
  hourlyPercentage: number | null
  diff24h: number | null
  percent24h: number | null
  diff1w: number | null
  percent1w: number | null
  createdAt: number | null
  registeredAt: string
  joinMethodType: number
  url: string
  isInRanking: boolean
}

// グラフデータのみ（重い処理）- /alpha-api/stats/{id}/graph
export interface GraphDataResponse {
  dates: string[]
  members: number[]
  rankings: (number | null)[]
}

// 後方互換性のため残す（BasicInfo + GraphData）
export interface StatsResponse extends BasicInfoResponse {
  dates: string[]
  members: number[]
  rankings: (number | null)[]
}

export interface BatchStatsResponse {
  data: OpenChat[]
}

export interface RankingHistoryItem {
  datetime: string  // 開始日時
  endDatetime: string | null  // 終了日時（nullの場合は現在も継続中）
  status: string  // '未掲載' または '再掲載済み'
  hasContentChange: boolean  // ルーム内容変更有無
  updateItems: string[]  // 変更項目の配列
  member: number  // その時点のメンバー数
  currentMember: number  // 現在のメンバー数
  memberDiff: number  // メンバー数の差分
  percentage: number  // ランキング位置パーセンテージ（フォールバック用）
  position: number | null  // 同一カテゴリ内の順位（N位）。古い履歴は null
  totalCount: number | null  // 同一カテゴリのランキング総数（M位）。古い履歴は null
}

export interface RankingHistoryResponse {
  data: RankingHistoryItem[]
}

// 高次の考察 - /alpha-api/insights/{id}
// type ごとに数値フィールドは異なるが、UI は text を主役に type でアイコン分けする。
export interface InsightItem {
  type:
    | 'growth_rank'
    | 'position_trend'
    | 'best_rank'
    | 'category_rank'
    | 'category_share'
    | 'category_scale'
    | 'record_single_day'
    | 'pace_anomaly'
    | string
  text: string
  [key: string]: unknown
}

export interface InsightsResponse {
  insights: InsightItem[]
  generatedAt: string
}

// 任意のN日増減 - /alpha-api/period-growth
export interface PeriodGrowthItem {
  id: number
  name: string
  desc: string
  member: number  // 現在のメンバー数
  img: string
  emblem: 0 | 1 | 2
  category: number
  categoryName: string
  join_method_type: number
  diff: number  // N日間のメンバー増減
  percent: number  // N日間の増減率
  pastMember: number  // N日前のメンバー数
  pastDate: string  // 比較に用いた実際のN日前日付
  baseDate: string  // 比較に用いた実際の基準日付
  createdAt: number | null
  registeredAt: string
  url: string
}

export interface PeriodGrowthResponse {
  data: PeriodGrowthItem[]
  days: number
  totalMatched: number
  baseDate: string
  targetPastDate: string
}

export interface PeriodGrowthParams {
  keyword: string
  category?: number
  days?: number
  order?: 'asc' | 'desc'
  limit?: number
}
