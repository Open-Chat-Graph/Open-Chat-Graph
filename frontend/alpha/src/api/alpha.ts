import type { SearchParams, SearchResponse, BasicInfoResponse, BatchStatsResponse, GraphEmbedResponse, RankingHistoryResponse, InsightsResponse, PeriodGrowthParams, PeriodGrowthResponse, AlertsConfigResponse, AlertsConfigRequest, AlertsResponse, RankingParams, AccessRankingResponse, SearchRankingResponse, SearchEtaParams, SearchEtaResponse, RoomMetricsResponse, SearchQueryRankingParams, SearchQueryRankingResponse, EtaParams, EtaResponse, FolderSettingsResponse, FolderSettingsRequest, FolderSettingsSaveResponse } from '../types/api'
import { periodToParams, type PeriodValue } from '@/lib/period'

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

  // グラフ埋め込み用 DTO ＋ ハッシュ付きスクリプトパス（oc-app グラフを SPA にマウントする）
  async getGraphEmbed(openChatId: number): Promise<GraphEmbedResponse> {
    const res = await fetch(`${API_BASE}/oc/${openChatId}/graph-embed`)
    if (!res.ok) throw new Error('Graph embed API failed')

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
    if (params.page) query.set('page', params.page.toString())
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

  // 汎用 ETA（リスト系プログレスバー用）。type＋取得条件を渡すと予測 ms を返す。
  // 検索は専用の getSearchEta を使う。
  async getEta(params: EtaParams): Promise<EtaResponse> {
    const query = new URLSearchParams()
    query.set('type', params.type)
    if (params.keyword) query.set('keyword', params.keyword)
    if (params.category) query.set('category', params.category.toString())
    if (params.order) query.set('order', params.order)
    if (params.scope) query.set('scope', params.scope)
    if (params.sort) query.set('sort', params.sort)
    if (params.days !== undefined) query.set('days', params.days.toString())
    if (params.start) query.set('start', params.start)
    if (params.end) query.set('end', params.end)
    if (params.all) query.set('all', '1')
    if (params.startDate) query.set('startDate', params.startDate)
    if (params.endDate) query.set('endDate', params.endDate)

    const res = await fetch(`${API_BASE}/eta?${query}`)
    if (!res.ok) throw new Error('ETA API failed')

    return res.json()
  },

  // 部屋ごとのアクセス・検索メトリクス（詳細ページ向け）。period で集計期間（日数/範囲/全期間）を指定。
  async getRoomMetrics(openChatId: number, period?: PeriodValue): Promise<RoomMetricsResponse> {
    const query = new URLSearchParams(period ? periodToParams(period) : {})

    const res = await fetch(`${API_BASE}/room-metrics/${openChatId}?${query}`)
    if (!res.ok) throw new Error('Room metrics API failed')

    return res.json()
  },

  // サイト全体の検索クエリ別流入ランキング（GSC 由来）。無限スクロール（page）対応。
  async getSearchQueryRanking(params: SearchQueryRankingParams = {}): Promise<SearchQueryRankingResponse> {
    const query = new URLSearchParams(params.period ? periodToParams(params.period) : {})
    if (params.page) query.set('page', params.page.toString())
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

  // ===== フォルダ設定（スマートフォルダ＋フォルダ単位アラート） =====

  // フォルダ設定を取得（rule: 自動追加ルール、threshold: 増減アラートしきい値）
  async getFolderSettings(folderId: string): Promise<FolderSettingsResponse> {
    const res = await fetch(`${API_BASE}/folder-settings/${encodeURIComponent(folderId)}`, {
      credentials: 'include',
    })
    if (!res.ok) throw new Error('Folder settings GET failed: ' + res.status)
    return res.json()
  },

  // フォルダ設定を保存。ruleを設定/変更すると即時に一致部屋上位50件を追加し autoAdded を返す。
  async putFolderSettings(folderId: string, body: FolderSettingsRequest): Promise<FolderSettingsSaveResponse> {
    const res = await fetch(`${API_BASE}/folder-settings/${encodeURIComponent(folderId)}`, {
      method: 'PUT',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    })
    if (!res.ok) throw new Error('Folder settings PUT failed: ' + res.status)
    return res.json()
  },

  // ===== Labs: アクセス数 / 検索流入ランキング =====
  // どちらも GA4/GSC 由来の日次集計。creds 未投入時は data が空配列で返る（集計待ち）。

  // ランキング共通のクエリ組み立て（期間/カテゴリ/並び/ページ/件数/スコープ/キーワード）。
  _rankingQuery(params: RankingParams): URLSearchParams {
    const query = new URLSearchParams(params.period ? periodToParams(params.period) : {})
    if (params.category) query.set('category', params.category.toString())
    if (params.order) query.set('order', params.order)
    if (params.page) query.set('page', params.page.toString())
    if (params.limit) query.set('limit', params.limit.toString())
    if (params.scope) query.set('scope', params.scope)
    if (params.keyword) query.set('keyword', params.keyword)
    if (params.sort) query.set('sort', params.sort)
    return query
  },

  // アクセス数（ページビュー）ランキング。scope=pages で「その他ページ（非オプチャ）」。
  async getAccessRanking(params: RankingParams = {}): Promise<AccessRankingResponse> {
    const res = await fetch(`${API_BASE}/access-ranking?${this._rankingQuery(params)}`)
    if (!res.ok) throw new Error('Access ranking API failed')
    return res.json()
  },

  // 検索流入（直接/間接SEO・入室数）ランキング。scope=pages で非オプチャページ。
  async getSearchRanking(params: RankingParams = {}): Promise<SearchRankingResponse> {
    const res = await fetch(`${API_BASE}/search-ranking?${this._rankingQuery(params)}`)
    if (!res.ok) throw new Error('Search ranking API failed')
    return res.json()
  },
}
