import type { SearchParams, SearchResponse, BasicInfoResponse, BatchStatsResponse, RankingHistoryResponse, InsightsResponse, PeriodGrowthParams, PeriodGrowthResponse, AlertsConfigResponse, AlertsConfigRequest, AlertsResponse } from '../types/api'

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

    const res = await fetch(`${API_BASE}/period-growth?${query}`)
    if (!res.ok) throw new Error('Period growth API failed')

    return res.json()
  },

  // ===== 通知・ウォッチ条件 =====

  // ウォッチ条件を取得（キーワード見張り・部屋しきい値・マイリスト全体しきい値）
  async getAlertsConfig(): Promise<AlertsConfigResponse> {
    const res = await fetch(`${API_BASE}/alerts/config`)
    if (!res.ok) throw new Error('Alerts config API failed')

    return res.json()
  },

  // ウォッチ条件を全置き換えで保存。レスポンスは保存後の最新（GET と同形）。
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
}
