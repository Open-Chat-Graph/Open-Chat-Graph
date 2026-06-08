import type { PeriodValue } from '@/lib/period'

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

// グラフ埋め込み（oc-app グラフ）- /alpha-api/oc/{id}/graph-embed
// DTO の中身は oc-app 側（window.mountOcGraph）の契約なのでここでは不透明に扱う
export interface GraphEmbedResponse {
  /** ハッシュ付きバンドルのパス（サーバー側で glob 解決済み。例: js/oc-app/graph-XXXX.js） */
  scriptPath: string
  chartArgDto: Record<string, unknown>
  statsDto: Record<string, unknown>
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
    | 'momentum'           // 勢い（直近7日の純増＋平常ペース比＋成長ランキング順位）
    | 'rank_position'      // 公式ランキングでの位置（現在順位＋30日推移＋自己最高位）
    | 'category_position'  // カテゴリ内での位置（カテゴリ内順位＋シェア）
    | string               // 未知 type は fallback 描画（旧通知キャッシュ等の保険）
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
  page: number  // 取得したページ番号（1始まり）
  hasMore: boolean  // 次ページがあるか（無限スクロール）
  baseDate: string
  targetPastDate: string
  poolLimited: boolean  // 候補プールが candidateLimit 件に達した（規模上位 N 件打ち切り）
  candidateLimit: number  // 集計対象の上限件数（規模上位 N 件）
}

export interface PeriodGrowthParams {
  keyword: string
  category?: number
  days?: number
  order?: 'asc' | 'desc'
  limit?: number
  page?: number  // 無限スクロールのページ番号（1始まり）
  startDate?: string  // 比較の起点日（Y-m-d）。days より優先される明示指定
  endDate?: string  // 比較の終点日（Y-m-d）。days より優先される明示指定
}

// ===== 検索の所要時間予測（GET /alpha-api/search-eta） =====
// 同条件の過去の検索応答時間からサーバーが見積もる。プログレスバーの進行速度に使う。
export interface SearchEtaParams {
  keyword: string
  category?: number
  sort?: SearchParams['sort']
  order?: 'asc' | 'desc'
}

export interface SearchEtaResponse {
  etaMs: number  // 予測応答時間（ミリ秒）
}

// ===== 汎用 ETA（GET /alpha-api/eta） =====
// リスト系（期間増減 / Labs ランキング）のプログレスバー用。検索は search-eta を使う。
export type EtaListType = 'period-growth' | 'access-ranking' | 'search-ranking' | 'search-query-ranking'

export interface EtaParams {
  type: EtaListType
  // type ごとに使う条件（その取得 API へ渡すのと同じ値）。サーバーが record と同じ規則でキー化する。
  keyword?: string
  category?: number
  order?: 'asc' | 'desc'
  scope?: 'rooms' | 'pages'
  // access-ranking の並び替え軸（seo_total=SEO合計 / jump=入室数）。record 側のキーに含まれるので一致させる。
  sort?: 'pageviews' | 'seo_total' | 'jump_clicks'
  // 期間（Labs ランキング系）: days か start・end か all のいずれか。
  days?: number
  start?: string
  end?: string
  all?: boolean
  // period-growth の期間（startDate・endDate か days）。
  startDate?: string
  endDate?: string
}

export type EtaResponse = SearchEtaResponse

// ===== 通知・アラート条件（/alpha-api/alerts*） =====
// すべて ja のみ。通知は毎時クロール後に算出（即時ではない）。

// --- アラート条件の取得（GET /alpha-api/alerts/config） ---
export interface AlertsConfigKeyword {
  id: number
  keyword: string
  category: number | null
  created_at: string
}

export interface AlertsConfigRoom {
  id: number
  open_chat_id: number
  up_member: number | null
  up_percent: number | null
  down_member: number | null
  down_percent: number | null
}

// マイリスト変動アラートのスコープ。
//   all    … マイリスト全体
//   root   … ルート直下（フォルダ未分類）のみ
//   folder … 特定フォルダ配下のみ
export type MylistAlertScope = 'all' | 'root' | 'folder'

export interface AlertsConfigMylistThreshold {
  up_percent: number | null
  down_percent: number | null
  up_member: number | null
  down_member: number | null
  scope: MylistAlertScope
  // scope!=='all' のとき、フロントが解決した対象 open_chat_id 集合。'all'/未保存は null。
  target_oc_ids: number[] | null
  enabled: boolean
}

export interface AlertsConfigResponse {
  keywords: AlertsConfigKeyword[]
  rooms: AlertsConfigRoom[]
  mylistThreshold: AlertsConfigMylistThreshold
}

// --- アラート条件の保存（PUT /alpha-api/alerts/config、全置き換え） ---
export interface AlertsConfigRequestKeyword {
  keyword: string // 必須・最大190字
  category?: number | null
}

export interface AlertsConfigRequestRoom {
  openChatId: number
  upMember?: number | null
  upPercent?: number | null
  downMember?: number | null
  downPercent?: number | null
}

export interface AlertsConfigRequestMylistThreshold {
  upPercent?: number | null
  downPercent?: number | null
  upMember?: number | null
  downMember?: number | null
  scope?: MylistAlertScope
  // scope!=='all' のとき送る。対象 open_chat_id 集合（サーバ側で最大1000件）。
  targetOcIds?: number[]
  enabled: boolean
}

export interface AlertsConfigRequest {
  keywords?: AlertsConfigRequestKeyword[]
  rooms?: AlertsConfigRequestRoom[]
  mylistThreshold?: AlertsConfigRequestMylistThreshold
}

// --- 通知一覧（GET /alpha-api/alerts?markRead=all|<csv ids>） ---
export interface AlertBase {
  id: number
  type: 'keyword' | 'room' | 'mylist' | 'room_change' | 'rank_jump' | 'pace' | 'folder_add' | 'folder'
  isRead: boolean
  createdAt: number // unix秒
}

// 機微シグナル: 部屋情報変更
export interface RoomChangeField {
  field: 'name' | 'description' | 'category'
  old: string
  new: string
}

export interface RoomChangePayload {
  openChatId: number
  name: string
  changes: RoomChangeField[]
}

export interface RoomChangeSignal extends AlertBase {
  type: 'room_change'
  payload: RoomChangePayload
}

// 機微シグナル: ランキング急上昇
export interface RankJumpPayload {
  openChatId: number
  name: string
  category: number
  position: number
  prevPosition: number | null
  kind: 'enter' | 'jump'
}

export interface RankJumpSignal extends AlertBase {
  type: 'rank_jump'
  payload: RankJumpPayload
}

// 機微シグナル: 増加ペース
export interface PacePayload {
  openChatId: number
  name: string
  diff7: number
  recentPace: number
  basePace: number
}

export interface PaceSignal extends AlertBase {
  type: 'pace'
  payload: PacePayload
}

export type Signal = RoomChangeSignal | RankJumpSignal | PaceSignal

// 新しい部屋（キーワードアラートヒット）
export interface KeywordHit extends AlertBase {
  type: 'keyword'
  keyword: string
  category: number | null
  emid: string
  openChatId: number | null // 未登録の真の新規部屋は null
  name: string
  desc: string
  img: string // obsハッシュ → imgPreviewUrl で前置
  joinMethodType: number
  isRegistered: boolean
  member: number | null  // 検出時点のメンバー数（取得できなければ null）
  detectedAt: number  // 検索で発見した時刻（unix秒）。明示的な検出時刻表示に使う
}

// 増減アラート（部屋／マイリストの増減）
export interface Movement extends AlertBase {
  type: 'room' | 'mylist'
  kind: 'room' | 'mylist'
  openChatId: number
  name: string
  img: string // open_chat.img_url（保存パス）
  category: number | null
  currentMember: number | null
  diff: number
  percent: number
  direction: 'up' | 'down'
}

export interface AlertsResponse {
  keywordHits: KeywordHit[]
  movements: Movement[]
  signals: Signal[]
  folderAdds: FolderAdd[]
  folderMovements: FolderMovement[]
  unreadCount: number
  computedAt: string | null
}

// フォルダへの自動追加通知（type: 'folder_add'）
// count が存在する場合は初回フィルのサマリ通知（複数部屋をまとめた1通）。
// count が存在しない場合は毎時の新着個別通知（openChatId / name / member が必ず存在する）。
export interface FolderAddPayload {
  folderId: string
  folderName: string
  // サマリ通知のとき存在する
  count?: number
  sampleNames?: string[]
  // 個別通知のとき存在する（サマリ通知では undefined）
  openChatId?: number
  name?: string
  member?: number
}

export interface FolderAdd extends AlertBase {
  type: 'folder_add'
  payload: FolderAddPayload
}

// フォルダ単位の増減アラート（type: 'folder'）
export interface FolderMovement extends AlertBase {
  type: 'folder'
  kind: 'folder'
  folderId: string
  folderName: string
  openChatId: number
  name: string
  img: string
  category: number | null
  currentMember: number | null
  diff: number
  percent: number
  direction: 'up' | 'down'
}

// フォルダ設定（GET/PUT /alpha-api/folder-settings/{folderId}）
export interface FolderRule {
  keyword: string
  category: number | null
  enabled: boolean
}

export interface FolderThreshold {
  upPercent: number | null
  downPercent: number | null
  upMember: number | null
  downMember: number | null
  enabled: boolean
}

export interface FolderSettingsResponse {
  rule: FolderRule | null
  threshold: FolderThreshold | null
}

export interface FolderSettingsRequest {
  rule: FolderRule | null
  threshold: FolderThreshold | null
}

export interface FolderSettingsSaveResponse {
  ok: boolean
  autoAdded: number
}

// ===== Labs: アクセス数 / 検索流入ランキング（/alpha-api/access-ranking, /search-ranking） =====
// 本家ページ（SEO/初見向け）の指標。データは GA4/GSC 由来でバックエンドが日次集計する。
// GA連携の creds 投入前は data が空配列（200）で返る → 画面側で「集計待ち」を出す。
// ja のみ。img は OBS ハッシュ（imgPreviewUrl で前置）。

// ランキング全体に対する各ページ（本家のページ単位）の指標。
// access/search ランキングのレスポンスに任意で付く（GA4/GSC 由来・未集計なら省略）。
export interface RankingPageMetric {
  path: string  // ページのパス
  label: string  // 表示用ラベル
  pageviews: number  // ページビュー数
  activeUsers: number  // アクティブユーザー数
  searchClicks: number  // 検索からのクリック数
  searchImpressions: number  // 検索結果の表示回数
  searchPosition: number | null  // 平均掲載順位（未集計なら null）
  // 入室数（近似）＝このページを参照元として到達した部屋の jump_clicks 合計。
  // ページ単体の参加リンク押下は計測していないため referrer ベースで近似（過大/重複しうる）。
  jumpClicks: number
  // うちSEO経由（間接含む・近似）＝同じ参照元部屋群の jump_clicks_organic 合計。
  jumpClicksOrganic: number
}

// ランキングの1部屋。アクセス/検索流入どちらのタブでも全指標を持つ
// （合計・直接/間接SEO・入室数=参加リンク押下・うちSEO経由）。主指標はタブ側で選ぶ。
export interface LabsRankingRoom {
  id: number
  name: string
  desc: string
  member: number
  img: string
  emblem: 0 | 1 | 2
  category: number
  categoryName: string
  join_method_type: number
  createdAt: number | null
  registeredAt: string
  url: string
  pageviews: number  // アクセス数（PV）
  activeUsers: number  // ユニークユーザー
  searchClicks: number  // 直接SEO流入（Google→このページのクリック）
  searchImpressions: number  // 検索結果の表示回数
  searchPosition: number | null  // 平均掲載順位（未集計なら null）
  seoIndirect: number  // 間接SEO流入（本家内SEOページ経由で到達したPV）
  jumpClicks: number  // 入室数＝参加リンク押下（LINEへの送客）
  jumpClicksOrganic: number  // うちSEO経由（Organic Search セッション起点）
  keywords: string[]  // 流入検索キーワード（clicks 多い順 上位8語。無ければ空配列）
}

// 後方互換のためのエイリアス（旧名 import を温存）
export type AccessRankingRoom = LabsRankingRoom
export type SearchRankingRoom = LabsRankingRoom

// ランキング応答の共通封筒（無限スクロール対応）。T は room / page / query。
export interface LabsRankingResponse<T> {
  data: T[]
  page: number  // 取得したページ番号（1始まり）
  hasMore: boolean  // 次ページがあるか
  days: number  // 集計対象の実日数
  fromDate?: string  // 集計開始日
  toDate?: string  // 集計終了日
  baseDate: string | null  // 集計の基準日（未集計なら null）
  updatedAt: string | null  // 集計の最終更新日時（未集計なら null）
}

export type AccessRankingResponse = LabsRankingResponse<LabsRankingRoom>
export type SearchRankingResponse = LabsRankingResponse<LabsRankingRoom>
export type PageRankingResponse = LabsRankingResponse<RankingPageMetric>

// ランキング共通の指定（並び/期間/カテゴリ/ページング/対象スコープ）
export interface RankingParams {
  category?: number
  order?: 'asc' | 'desc'
  period?: PeriodValue  // 期間（日数/範囲/全期間）。未指定なら既定30日
  page?: number  // 1始まり。無限スクロールでインクリメント
  limit?: number  // 1ページの件数
  scope?: 'rooms' | 'pages'  // pages＝その他ページ（非オプチャ）タブ
  keyword?: string  // 部屋名キーワード絞り込み（rooms スコープのみ。空文字は全件）
  // 並び替え軸（access-ranking のみ）。部屋集合は常に PV>0 で固定し並びだけ切替える。
  // pageviews＝アクセス数降順（既定）/ seo_total＝SEO合計（直接＋間接）降順 / jump_clicks＝入室数降順。
  sort?: 'pageviews' | 'seo_total' | 'jump_clicks'
}

// ===== 部屋ごとのアクセス・検索メトリクス（GET /alpha-api/room-metrics/{id}） =====
// 詳細ページ向け。GA4/GSC 由来の日次集計（creds 未投入時はゼロ／未集計で返る）。ja のみ。

// このルームのページに流入した検索クエリ（多い順・GSC由来）。
export interface RoomSearchQuery {
  query: string  // 検索クエリ文字列
  clicks: number  // クリック数
  impressions: number  // 表示回数
  position: number | null  // 平均掲載順位（未集計なら null）
}

// このルームのページの参照元（多い順・GA4 pageReferrer 由来）。
export interface RoomReferrer {
  referrer: string  // 生の参照元（ホスト/URL。(direct) は直接流入）
  label: string  // 一覧の1行に出す短ラベル（トップページ/おすすめ/検索結果/他の部屋 など）
  detail: string  // タップ/ホバーのチップに出す全文（どこから来たかを明示）
  pageviews: number  // この参照元からのページビュー数
  isInternal: boolean  // 本家(openchat-review.me)内からの遷移＝SEO経由で間接流入
}

export interface RoomMetricsResponse {
  days: number  // 集計対象の実日数（範囲・全期間でも実日数）
  fromDate?: string  // 集計開始日（Y-m-d）
  toDate?: string  // 集計終了日（Y-m-d）
  updatedAt: string | null  // 集計の最終更新日時（未集計なら null）
  pageviews: number  // ページビュー数
  activeUsers: number  // アクティブユーザー数
  searchClicks: number  // 直接SEO流入＝Googleからこのページへのクリック数（GSC）
  searchImpressions: number  // 検索結果の表示回数
  searchPosition: number | null  // 平均掲載順位（未集計なら null）
  seoIndirect: number  // 間接SEO流入＝本家内SEOページ経由で到達したPV（自己参照除く）
  jumpClicks: number  // 「LINEで開く」等の外部遷移クリック数
  jumpClicksOrganic: number  // うち SEO（Organic Search セッション）起点の参加リンク押下数
  avgEngagementSeconds: number  // 平均滞在（エンゲージメント）秒数
  searchQueries: RoomSearchQuery[]  // 流入キーワード（多い順）
  referrers: RoomReferrer[]  // 参照元（多い順）
}

// ===== 検索クエリランキング（GET /alpha-api/search-query-ranking） =====
// サイト全体の検索クエリ別の流入（GSC 由来）。ja のみ。
export interface SearchQueryRankingItem {
  query: string  // 検索クエリ文字列
  clicks: number  // クリック数
  impressions: number  // 表示回数
  position: number  // 平均掲載順位
}

export interface SearchQueryRankingParams {
  period?: PeriodValue
  page?: number
  limit?: number
}

export interface SearchQueryRankingResponse {
  data: SearchQueryRankingItem[]
  page: number
  hasMore: boolean
  days: number
  fromDate?: string
  toDate?: string
  updatedAt: string | null  // 集計の最終更新日時（未集計なら null）
}
