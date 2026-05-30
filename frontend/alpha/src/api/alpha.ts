import type { SearchParams, SearchResponse, BasicInfoResponse, BatchStatsResponse, RankingHistoryResponse } from '../types/api'

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

  // グラフ用API（Viteプロキシ対応）
  async getRankingPosition(openChatId: number, category?: number, sort?: string, startDate?: string): Promise<any> {
    const query = new URLSearchParams()
    if (category !== undefined) query.set('category', category.toString())
    if (sort) query.set('sort', sort)
    if (startDate) query.set('start_date', startDate)

    const queryString = query.toString() ? `?${query}` : ''
    const res = await fetch(`/oc/${openChatId}/position${queryString}`)
    if (!res.ok) throw new Error('Ranking position API failed')

    return res.json()
  },

  async getRankingPositionHour(openChatId: number, category?: number, sort?: string): Promise<any> {
    const query = new URLSearchParams()
    if (category !== undefined) query.set('category', category.toString())
    if (sort) query.set('sort', sort)

    const queryString = query.toString() ? `?${query}` : ''
    const res = await fetch(`/oc/${openChatId}/position_hour${queryString}`)
    if (!res.ok) throw new Error('Ranking position hour API failed')

    return res.json()
  },
}
