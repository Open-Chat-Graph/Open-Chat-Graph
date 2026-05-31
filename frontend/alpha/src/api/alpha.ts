import type { SearchParams, SearchResponse, BasicInfoResponse, BatchStatsResponse, RankingHistoryResponse, InsightsResponse, PeriodGrowthParams, PeriodGrowthResponse, AlertsConfigResponse, AlertsConfigRequest, AlertsResponse, RankingParams, AccessRankingResponse, SearchRankingResponse, SearchEtaParams, SearchEtaResponse, RoomMetricsResponse, SearchQueryRankingParams, SearchQueryRankingResponse } from '../types/api'

const API_BASE = '/alpha-api'

export const alphaApi = {
  async search(params: SearchParams): Promise<SearchResponse> {
    const query = new URLSearchParams()

    if (params.keyword) query.set('keyword', params.keyword)
    if (params.category) query.set('category', params.category.toString())
    if (params.page !== undefined) query.set('page', params.page.toString())
    if (params.limit) query.set('limit', params.limit.toString())
    if (params.sort) query.set('sort', params.sort)
    if (params.order) query.set('order', params.order)

    const res = await fetch(`${API_BASE}/search?${query}`)
    if (!res.ok) throw new Error('Search API failed')

    return res.json()
  },

  // 基本情報のみ取得（軽量）
  async getBasicInfo(openChatId: number): Promise<BasicInfoResponse> {
    const res = await fetch(`${API_BASE}/stats/${openChatId}`)
    if (!res.ok) throw new Error('Basic info API failed')

    return res.json()
  },

  async batchStats(ids: number[]): Promise<BatchStatsResponse> {
    const res = await fetch(`${API_BASE}/batch-stats`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ids }),
    })
    if (!res.ok) throw new Error('Batch stats API failed')

    return res.json()
  },

  async getRankingHistory(openChatId: number): Promise<RankingHistoryResponse> {
    const res = await fetch(`${API_BASE}/ranking-history/${openChatId}`)
    if (!res.ok) throw new Error('Ranking history API failed')

    return res.json()
  },

  // 高次の考察（一目で分からない洞察だけ）。insights が空配列のこともある。
  async getInsights(openChatId: number): Promise<InsightsResponse> {
    const res = await fetch(`${API_BASE}/insights/${openChatId}`)
    if (!res.ok) throw new Error('Insights API failed')

    return res.json()
  },

  // 任意のN日増減検索（N日前と現在の両方に統計があるルームに絞り増減順）。
  async getPeriodGrowth(params: PeriodGrowthParams): Promise<PeriodGrowthResponse> {
    const query = new URLSearchParams()
    query.set('keyword', params.keyword)
    if (params.category) query.set('category', params.category.toString())
    if (params.days !== undefined) query.set('days', params.days.toString())
    if (params.order) query.set('order', params.order)
    if (params.limit) query.set('limit', params.limit.toString())
    if (params.startDate) query.set('startDate', params.startDate)
    if (params.endDate) query.set('endDate', params.endDate)

    const res = await fetch(`${API_BASE}/period-growth?${query}`)
    if (!res.ok) throw new Error('Period growth API failed')

    return res.json()
  },

  // 検索の所要時間予測。プログレスバーの進行速度に使う（実応答は別途 search で取得）。
  async getSearchEta(params: SearchEtaParams): Promise<SearchEtaResponse> {
    const query = new URLSearchParams()
    if (params.keyword) query.set('keyword', params.keyword)
    if (params.category) query.set('category', params.category.toString())
    if (params.sort) query.set('sort', params.sort)
    if (params.order) query.set('order', params.order)

    const res = await fetch(`${API_BASE}/search-eta?${query}`)
    if (!res.ok) throw new Error('Search ETA API failed')

    return res.json()
  },

  // 部屋ごとのアクセス・検索メトリクス（詳細ページ向け）。days で集計期間を指定。
  async getRoomMetrics(openChatId: number, days?: number): Promise<RoomMetricsResponse> {
    const query = new URLSearchParams()
    if (days !== undefined) query.set('days', days.toString())

    const res = await fetch(`${API_BASE}/room-metrics/${openChatId}?${query}`)
    if (!res.ok) throw new Error('Room metrics API failed')

    return res.json()
  },

  // サイト全体の検索クエリ別流入ランキング（GSC 由来）。
  async getSearchQueryRanking(params: SearchQueryRankingParams = {}): Promise<SearchQueryRankingResponse> {
    const query = new URLSearchParams()
    if (params.days !== undefined) query.set('days', params.days.toString())
    if (params.limit) query.set('limit', params.limit.toString())

    const res = await fetch(`${API_BASE}/search-query-ranking?${query}`)
    if (!res.ok) throw new Error('Search query ranking API failed')

    return res.json()
  },

  // ===== 通知・アラート条件 =====

  // アラート条件を取得（キーワードのアラート・部屋しきい値・マイリスト全体しきい値）
  async getAlertsConfig(): Promise<AlertsConfigResponse> {
    const res = await fetch(`${API_BASE}/alerts/config`)
    if (!res.ok) throw new Error('Alerts config API failed')

    return res.json()
  },

  // アラート条件を全置き換えで保存。レスポンスは保存後の最新（GET と同形）。
  async putAlertsConfig(body: AlertsConfigRequest): Promise<AlertsConfigResponse> {
    const res = await fetch(`${API_BASE}/alerts/config`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    })
    if (!res.ok) throw new Error('Alerts config save failed')

    return res.json()
  },

  // 通知一覧を取得。markRead に 'all' か対象 id の CSV を渡すと既読化する。
  async getAlerts(markRead?: 'all' | string): Promise<AlertsResponse> {
    const query = markRead ? `?markRead=${encodeURIComponent(markRead)}` : ''
    const res = await fetch(`${API_BASE}/alerts${query}`)
    if (!res.ok) throw new Error('Alerts API failed')

    return res.json()
  },

  // ===== Labs: アクセス数 / 検索流入ランキング =====
  // どちらも GA4/GSC 由来の日次集計。creds 未投入時は data が空配列で返る（集計待ち）。

  // アクセス数（ページビュー）ランキング
  async getAccessRanking(params: RankingParams = {}): Promise<AccessRankingResponse> {
    const query = new URLSearchParams()
    if (params.category) query.set('category', params.category.toString())
    if (params.days !== undefined) query.set('days', params.days.toString())
    if (params.order) query.set('order', params.order)
    if (params.limit) query.set('limit', params.limit.toString())

    const res = await fetch(`${API_BASE}/access-ranking?${query}`)
    if (!res.ok) throw new Error('Access ranking API failed')

    return res.json()
  },

  // 検索流入（クリック数・表示回数・平均順位）ランキング
  async getSearchRanking(params: RankingParams = {}): Promise<SearchRankingResponse> {
    const query = new URLSearchParams()
    if (params.category) query.set('category', params.category.toString())
    if (params.days !== undefined) query.set('days', params.days.toString())
    if (params.order) query.set('order', params.order)
    if (params.limit) query.set('limit', params.limit.toString())

    const res = await fetch(`${API_BASE}/search-ranking?${query}`)
    if (!res.ok) throw new Error('Search ranking API failed')

    return res.json()
  },
}
