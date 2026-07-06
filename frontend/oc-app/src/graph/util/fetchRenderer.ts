import { graphStore } from '../state/store'
import {
  categoryAtom,
  chart,
  chartModeAtom,
  errorAtom,
  limitAtom,
  loadingAtom,
  rankingRisingAtom,
  updateCandleTabVisibility,
  updateTabVisibility,
} from '../state/chartState'
import fetcher from './fetcher'
import { t } from './translation'

export const chatArgDto: RankingPositionChartArgDto = JSON.parse(
  (document.getElementById('chart-arg') as HTMLScriptElement).textContent!
)

/**
 * ページHTMLに埋め込まれた可用性メタ(#chart-meta)を読む。
 * 事前計算済みなら ChartMeta、未生成(null/空)なら null（→初回ロードで meta=1 のライブ計算にフォールバック）。
 */
function readEmbeddedChartMeta(): ChartMeta | null {
  const text = document.getElementById('chart-meta')?.textContent?.trim()
  if (!text) return null
  try {
    const parsed = JSON.parse(text)
    return parsed && typeof parsed === 'object' ? (parsed as ChartMeta) : null
  } catch {
    return null
  }
}

// タブ・ボタン出し分け用の可用性メタデータ。ページ埋め込み(#chart-meta)があればそれを採用し、
// 無ければ初回ロードの meta=1 レスポンスで設定される
const embeddedChartMeta = readEmbeddedChartMeta()

/** 初回ロードで meta=1 を撃たずに済むか（埋め込みメタがあるか） */
export const hasEmbeddedChartMeta = embeddedChartMeta !== null

export let chartMeta: ChartMeta = embeddedChartMeta as ChartMeta

export const langCode = chatArgDto.urlRoot.replace(/^\/+/, '') as '' | 'tw' | 'th'

/** 現在のチャート状態が指す表示ビューの span を判定する（hour は最新24時間タブのみ） */
function getCurrentSpan(): 'hour' | 'day' {
  return graphStore.get(chartModeAtom) === 'line' && chart.getIsHour() ? 'hour' : 'day'
}

/** 現在のビューの順位集計範囲（in: カテゴリ内 / all: すべて）。category=all 選択時とその他/未掲載室は all。 */
function currentScope(): 'in' | 'all' {
  const sort = graphStore.get(rankingRisingAtom)
  return sort !== 'none' && graphStore.get(categoryAtom) !== 'all' ? 'in' : 'all'
}

/**
 * 現在のチャート状態が指す表示ビューをAPIクエリ文字列にする。
 * 同一ビューは同一URLになるため、fetcher のURLキャッシュがそのまま効く
 */
export function getChartViewQuery(withMeta = false): string {
  const mode = graphStore.get(chartModeAtom)
  const sort = graphStore.get(rankingRisingAtom)
  const span = getCurrentSpan()
  const scope = currentScope()

  const query = new URLSearchParams({
    span,
    sort,
    scope,
    category: (chatArgDto.categoryKey ?? 0).toString(),
    mode,
  })
  if (withMeta) query.set('meta', '1')

  return query.toString()
}

/* ────────────────────────────────────────────────────────────────────────
 * 層(series)別の差分フェッチ
 *
 * 表示ビューを「層」に分解し、層ごとに独立して「最新日(endDate)から、ある開始日まで」の
 * 日次系列を累積で持つ。view設定(sort|scope|mode)を変えても、共有できる層は再取得しない。
 * 例: 全期間で人数を見たあとに順位ONしても、member は再取得せず position 層だけ取りに行く。
 *
 * 層キー:
 *  - member                          … メンバー数（常に必要）
 *  - position|{sort}|{scope}         … 順位(折れ線)。sort=ranking/rising, scope=in/all
 *  - memberOhlc                      … メンバー数ローソク足
 *  - positionOhlc|{sort}|{scope}     … 順位ローソク足
 *  （category は部屋固定なので scope に内包される）
 *
 * 各層キャッシュ: { loadedFrom, byDate }。
 *  - loadedFrom: その層が保持している最古の日付(Y-m-d)
 *  - byDate: 日付 → その層の値 の Map（窓は常に endDate 終端固定なので、足りないのは古い側の連続1範囲だけ）
 *
 * 窓(windowFrom..endDate)を描画するとき、必要な各層について byDate に未取得の古い範囲があれば、
 * 同じ範囲を要する層をまとめて1リクエスト(?series=…&from&to)で取得し byDate にマージする。
 * 描画は窓の密な日付配列に各層を揃えて ChartResponse 相当を組み立てる。
 * ──────────────────────────────────────────────────────────────────────── */

type LayerName = 'member' | 'position' | 'memberOhlc' | 'positionOhlc'

/** position 系列の1日分（折れ線の重ね描き用） */
type PositionPoint = {
  time: string | null
  position: number | null
  totalCount: number | null
}

type LayerCache =
  | { loadedFrom: string; byDate: Map<string, number | null> } // member
  | { loadedFrom: string; byDate: Map<string, PositionPoint> } // position
  | { loadedFrom: string; byDate: Map<string, MemberOhlc> } // memberOhlc
  | { loadedFrom: string; byDate: Map<string, RankingPositionOhlc> } // positionOhlc

/** 層キャッシュ本体。キーは `${LayerName}` もしくは `${LayerName}|${sort}|${scope}` */
const layers = new Map<string, LayerCache>()

/** member 層のキャッシュキー（sort/scope に依らず1つ） */
const MEMBER_KEY = 'member'
/** memberOhlc 層のキャッシュキー（sort/scope に依らず1つ） */
const MEMBER_OHLC_KEY = 'memberOhlc'

/** position / positionOhlc 層のキャッシュキー（sort×scope ごとに別） */
function positionKey(name: 'position' | 'positionOhlc'): string {
  return `${name}|${graphStore.get(rankingRisingAtom)}|${currentScope()}`
}

/** 層名 → 現在ビューでのキャッシュキー */
function layerKey(name: LayerName): string {
  switch (name) {
    case 'member':
      return MEMBER_KEY
    case 'memberOhlc':
      return MEMBER_OHLC_KEY
    case 'position':
      return positionKey('position')
    case 'positionOhlc':
      return positionKey('positionOhlc')
  }
}

/**
 * 現在の表示ビューで必要な層を返す。
 *  - 折れ線: member（常に）＋ position（順位ON時）
 *  - ローソク足: memberOhlc（常に）＋ positionOhlc（順位ON時）。日次 member 折れ線は描かないので取得しない
 *    （member 層＝統計DBアクセスを発生させない。OHLC は ohlcDate 軸で独立して持つ）
 */
function requiredLayers(): LayerName[] {
  const mode = graphStore.get(chartModeAtom)
  const sort = graphStore.get(rankingRisingAtom)
  if (mode === 'candlestick') {
    const list: LayerName[] = ['memberOhlc']
    if (sort !== 'none') list.push('positionOhlc')
    return list
  }
  const list: LayerName[] = ['member']
  if (sort !== 'none') list.push('position')
  return list
}

/** Y-m-d に日数を加減算した Y-m-d を返す（UTCで計算しタイムゾーンの影響を排除） */
function addDays(date: string, days: number): string {
  const d = new Date(`${date}T00:00:00Z`)
  d.setUTCDate(d.getUTCDate() + days)
  return d.toISOString().slice(0, 10)
}

/**
 * 期間タブ(limit)が必要とする窓の開始日を返す。
 *
 * 窓は最新日(endDate)終端固定: 週=endDate-7日, 月=endDate-30日, 全=startDate。
 * いずれも startDate より古くはしない（部屋がタブ幅より若い場合のクランプ）。
 * 日付軸はバックエンドで毎日(欠損日もnull埋め)の密な配列のため、
 * 「最新から limit 件」= 「endDate から limit-1 日前まで」が一致する。
 */
function windowFromForLimit(limit: ChartLimit): string {
  const { startDate, endDate } = chartMeta
  let from: string
  switch (limit) {
    case 8:
      from = addDays(endDate, -7)
      break
    case 31:
      from = addDays(endDate, -30)
      break
    case 0:
      from = startDate
      break
  }
  return from < startDate ? startDate : from
}

/**
 * 現在ビューで「描画・取得に使う窓の開始日」を返す。
 *
 * ローソク足は全期間で取得する（＝開始日は startDate 固定）。タブ表示判定・本数スライスとも
 * OHLC全件が手元にある前提で行うため（窓だけ持つと長いタブの本数を過小カウントする）。
 * 折れ線は従来どおり limit の窓開始日（縮小はクライアントスライス、拡大は古い差分だけ取得）。
 */
function neededFromForView(limit: ChartLimit): string {
  return graphStore.get(chartModeAtom) === 'candlestick'
    ? chartMeta.startDate
    : windowFromForLimit(limit)
}

/**
 * 折れ線の組み立てに使う窓開始日。必要層すべてが揃っている範囲まで窓を古い側へ広げる。
 *
 * chart は保持データ(initData)の末尾スライスで期間タブを切り替える
 * (currentConfigCoversLimit → chart.update)ため、窓ぴったりで組み立てると
 * 「層キャッシュは全期間あるのに chart は現在の窓ぶんしか持っていない」状態になる。
 * 例: 全期間を表示した後に週タブへ戻して順位表示をOFFにすると、chart が週窓だけで
 * 作り直され、その後の全期間タブがスライスだけで済まされて1週間分しか表示されない。
 * 全必要層が共通して持つ最古日まで広げて渡すことで、層キャッシュと chart の保持範囲を
 * 常に一致させる（chart 側が limit で末尾スライスするため、窓より広くてもよい）。
 */
function coveredFromForView(limit: ChartLimit): string {
  const neededFrom = neededFromForView(limit)
  let from = chartMeta.startDate
  for (const name of requiredLayers()) {
    const entry = layers.get(layerKey(name))
    const loadedFrom = entry?.loadedFrom ?? SENTINEL_FROM
    if (loadedFrom > from) from = loadedFrom
  }
  return from > neededFrom ? neededFrom : from
}

/** windowFrom..endDate を1日刻みで埋めた密な日付配列（バックエンドの date 軸と一致する） */
function buildDateAxis(from: string): string[] {
  const dates: string[] = []
  let cur = from
  const end = chartMeta.endDate
  while (cur <= end) {
    dates.push(cur)
    cur = addDays(cur, 1)
  }
  return dates
}

/**
 * 現在ビューの必要層すべてが、指定 limit の窓を既に満たしているか（追加取得不要か）。
 * 1つでも未取得の古い範囲を持つ層があれば false。
 */
export function currentConfigCoversLimit(limit: ChartLimit): boolean {
  const neededFrom = neededFromForView(limit)
  return requiredLayers().every((name) => {
    const entry = layers.get(layerKey(name))
    return entry !== undefined && entry.loadedFrom <= neededFrom
  })
}

/** 層名 → series クエリ値 */
function seriesParam(name: LayerName): string {
  return name
}

/**
 * 指定範囲(from..to)で指定層をまとめて1リクエスト取得する。
 * 共通 date 軸で返るので、各層を date と zip して byDate にマージする。
 */
async function fetchLayers(names: LayerName[], from: string, to: string): Promise<void> {
  const series = names.map(seriesParam).join(',')
  const data = await fetcher<ChartResponse>(
    `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/chart?${getChartViewQuery()}&series=${series}&from=${from}&to=${to}`
  )
  mergeLayers(names, data, from)
}

/**
 * 取得レスポンスを、要求した各層の byDate にマージし loadedFrom を更新する。
 * 折れ線層(member/position)は date 軸、ローソク足層(OHLC)は ohlcDate 軸と zip する。
 * loadedFrom は「取得した窓の開始日(from)」で更新する（OHLC応答に date が無くても遡れた範囲を記録できる）。
 */
function mergeLayers(names: LayerName[], data: ChartResponse, from: string): void {
  const date = data.date ?? []
  const ohlcDate = data.ohlcDate ?? []

  for (const name of names) {
    const key = layerKey(name)
    switch (name) {
      case 'member': {
        const entry = ensureMemberEntry(key)
        date.forEach((d, i) => entry.byDate.set(d, data.member?.[i] ?? null))
        break
      }
      case 'position': {
        const entry = ensurePositionEntry(key)
        date.forEach((d, i) =>
          entry.byDate.set(d, {
            time: data.time?.[i] ?? null,
            position: data.position?.[i] ?? null,
            totalCount: data.totalCount?.[i] ?? null,
          })
        )
        break
      }
      case 'memberOhlc': {
        const entry = ensureMemberOhlcEntry(key)
        ohlcDate.forEach((d, i) => {
          const v = data.memberOhlc?.[i]
          if (v) entry.byDate.set(d, v)
        })
        break
      }
      case 'positionOhlc': {
        const entry = ensurePositionOhlcEntry(key)
        ohlcDate.forEach((d, i) => {
          const v = data.positionOhlc?.[i]
          if (v) entry.byDate.set(d, v) // null（圏外）は積まない＝assemble 時に欠落=圏外として復元
        })
        break
      }
    }
    const entry = layers.get(key)!
    if (from < entry.loadedFrom) entry.loadedFrom = from
  }
}

// 層キャッシュの取得/生成（型ごとに分けて byDate の値型を保つ）。
// loadedFrom は生成時に endDate より後の番兵にしておき、最初のマージで実データの from に縮む。
const SENTINEL_FROM = '9999-12-31'

function ensureMemberEntry(key: string): { loadedFrom: string; byDate: Map<string, number | null> } {
  let entry = layers.get(key) as { loadedFrom: string; byDate: Map<string, number | null> } | undefined
  if (!entry) {
    entry = { loadedFrom: SENTINEL_FROM, byDate: new Map() }
    layers.set(key, entry)
  }
  return entry
}

function ensurePositionEntry(key: string): { loadedFrom: string; byDate: Map<string, PositionPoint> } {
  let entry = layers.get(key) as { loadedFrom: string; byDate: Map<string, PositionPoint> } | undefined
  if (!entry) {
    entry = { loadedFrom: SENTINEL_FROM, byDate: new Map() }
    layers.set(key, entry)
  }
  return entry
}

function ensureMemberOhlcEntry(key: string): { loadedFrom: string; byDate: Map<string, MemberOhlc> } {
  let entry = layers.get(key) as { loadedFrom: string; byDate: Map<string, MemberOhlc> } | undefined
  if (!entry) {
    entry = { loadedFrom: SENTINEL_FROM, byDate: new Map() }
    layers.set(key, entry)
  }
  return entry
}

function ensurePositionOhlcEntry(key: string): { loadedFrom: string; byDate: Map<string, RankingPositionOhlc> } {
  let entry = layers.get(key) as { loadedFrom: string; byDate: Map<string, RankingPositionOhlc> } | undefined
  if (!entry) {
    entry = { loadedFrom: SENTINEL_FROM, byDate: new Map() }
    layers.set(key, entry)
  }
  return entry
}

/**
 * 必要な各層について「窓に足りない古い範囲」を求め、同じ範囲を要する層をまとめて取得する。
 * 窓は endDate 終端固定なので、各層に足りないのは常に [neededFrom .. loadedFrom-1] の連続1範囲。
 * 同一 from の層は1リクエストにまとめる（fetcher のURLキャッシュも効く）。
 */
async function ensureLayersForWindow(neededFrom: string): Promise<void> {
  // 層ごとに「不足範囲(from..to)」を求め、同一範囲の層を1リクエストにまとめる。
  //  - 未取得層: 窓全体 [neededFrom .. endDate]
  //  - 既存層: 既に持つ範囲(loadedFrom..endDate)は再取得せず、不足分 [neededFrom .. loadedFrom-1] だけ
  // 全期間 member 取得済み→順位ON は position 層だけが未取得なので position だけ取りに行く（member 非再取得）。
  const requests = new Map<string, LayerName[]>() // key: `${from}|${to}`
  for (const name of requiredLayers()) {
    const entry = layers.get(layerKey(name))
    if (entry && entry.loadedFrom <= neededFrom) continue // 既に窓を満たす層はスキップ
    const reqTo = entry ? addDays(entry.loadedFrom, -1) : chartMeta.endDate
    const k = `${neededFrom}|${reqTo}`
    const list = requests.get(k) ?? []
    list.push(name)
    requests.set(k, list)
  }

  await Promise.all(
    [...requests.entries()].map(([k, names]) => {
      const [from, reqTo] = k.split('|')
      return fetchLayers(names, from, reqTo)
    })
  )
}

/**
 * 現在ビューの必要層から、窓(neededFrom..endDate)に揃えた ChartResponse を組み立てる。
 * 折れ線は limit の窓、ローソク足は全期間（neededFromForView 参照）。
 * chart 側が limit で末尾スライスするため、ここでは窓全体（蓄積が広くてもよい）を渡す。
 */
function assembleResponse(limit: ChartLimit): ChartResponse {
  const needs = new Set(requiredLayers())

  // ローソク足: OHLC 専用軸(ohlcDate)だけで組み立てる（日次 date / member は使わない）。
  // ローソク足は全期間取得なので、キャッシュ済み OHLC の日付を昇順に並べたものが ohlcDate になる。
  if (needs.has('memberOhlc')) {
    const entry = layers.get(MEMBER_OHLC_KEY) as
      | { loadedFrom: string; byDate: Map<string, MemberOhlc> }
      | undefined
    const ohlcDate = entry ? [...entry.byDate.keys()].sort() : []
    const response: ChartResponse = {
      ohlcDate,
      memberOhlc: ohlcDate.map((d) => entry!.byDate.get(d)!),
    }
    // positionOhlc（順位ON時のみ）も同じ ohlcDate に整合（順位OHLCの無い日は null＝圏外）
    if (needs.has('positionOhlc')) {
      const pentry = layers.get(positionKey('positionOhlc')) as
        | { loadedFrom: string; byDate: Map<string, RankingPositionOhlc> }
        | undefined
      response.positionOhlc = ohlcDate.map((d) => pentry?.byDate.get(d) ?? null)
    }
    return response
  }

  // 折れ線: 日次 date 軸で組み立てる（必要層が揃っている範囲まで窓を広げて chart に持たせる）
  const date = buildDateAxis(coveredFromForView(limit))
  const response: ChartResponse = { date, member: [], position: [], totalCount: [] }

  // member（常に必要）
  const memberEntry = layers.get(MEMBER_KEY) as
    | { loadedFrom: string; byDate: Map<string, number | null> }
    | undefined
  response.member = date.map((d) => memberEntry?.byDate.get(d) ?? null)

  // position（順位ON時のみ。それ以外は空配列で従来同様）
  if (needs.has('position')) {
    const entry = layers.get(positionKey('position')) as
      | { loadedFrom: string; byDate: Map<string, PositionPoint> }
      | undefined
    response.position = date.map((d) => entry?.byDate.get(d)?.position ?? null)
    response.totalCount = date.map((d) => entry?.byDate.get(d)?.totalCount ?? null)
    // time は急上昇(rising)のみ。ランキングは時刻を持たないので time キー自体を付けない。
    if (graphStore.get(rankingRisingAtom) === 'rising') {
      response.time = date.map((d) => entry?.byDate.get(d)?.time ?? null)
    }
  }

  return response
}

/**
 * 表示中の期間タブの窓を描画するためのデータを用意する。必要な層のうち足りない古い差分だけ取得する。
 *
 * - span=hour（最新24時間）: 範囲化・層キャッシュ対象外。従来どおり series 無しで全24hを取得する。
 * - 埋め込みメタ無し(withMeta=true)の新規室: 窓計算ができないため series 無しで全期間を取得し、
 *   取得後 chartMeta を埋めてから全層を byDate へ取り込む（以後は全期間が手元にあり追加取得不要）。
 * - それ以外(day, 埋め込みメタ有): 現在 limit の窓に必要な層の不足分だけ取得し、窓に揃えて組み立てる。
 *
 * 返り値は「現在ビューの窓ぶんの ChartResponse（= chart.render に渡す initData）」。
 * chart 側が limit で末尾スライスするため、窓どおりに渡せばよい。
 */
export async function fetchChartData(withMeta = false): Promise<ChartResponse> {
  // 最新24時間ビューは範囲化しない（毎時データはMariaDB・全24h固定。層キャッシュにも入れない）
  if (getCurrentSpan() === 'hour') {
    const data = await fetcher<ChartResponse>(
      `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/chart?${getChartViewQuery(withMeta)}`
    )
    if (data.meta) chartMeta = data.meta
    return data
  }

  // 埋め込みメタが無い新規室: 窓を計算できないので全期間フォールバック（series/from/to 無し）。
  // 取得後 chartMeta が埋まる。全期間ぶんを各層へ取り込み、以後は層キャッシュ経路に合流できる。
  if (withMeta) {
    const data = await fetcher<ChartResponse>(
      `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/chart?${getChartViewQuery(withMeta)}`
    )
    if (data.meta) chartMeta = data.meta
    importFullResponseToLayers(data)
    return data
  }

  const limit = graphStore.get(limitAtom) as ChartLimit
  await ensureLayersForWindow(neededFromForView(limit))
  return assembleResponse(limit)
}

/**
 * series 無し全期間レスポンス（hour 以外のフォールバック経路）を層キャッシュに取り込む。
 * これにより埋め込みメタ無し室でも、以降の view 切替で層キャッシュ経路に合流できる。
 * （取り込む層は現在ビューが返した系列ぶん。order は date 昇順前提）
 */
function importFullResponseToLayers(data: ChartResponse): void {
  // meta=1 は全期間取得なので、窓開始日＝startDate（ローソク足は date を持たないため chartMeta から取る）。
  const from = chartMeta?.startDate
  if (!from) return

  const sort = graphStore.get(rankingRisingAtom)
  const mode = graphStore.get(chartModeAtom)
  if (mode === 'candlestick') {
    mergeLayers(['memberOhlc'], data, from)
    if (sort !== 'none') mergeLayers(['positionOhlc'], data, from)
  } else {
    mergeLayers(['member'], data, from)
    if (sort !== 'none') mergeLayers(['position'], data, from)
  }
}

const renderPositionChart = (data: ChartResponse, animation: boolean, limit: ChartLimit) => {
  const isRising = graphStore.get(rankingRisingAtom) === 'rising'

  chart.render(
    {
      date: data.date ?? [],
      graph1: data.member ?? [],
      graph2: data.position ?? [],
      // time は rising のみ。ランキング等で無い場合は空配列として扱う（時刻tooltip非表示）
      time: data.time ?? [],
      totalCount: data.totalCount ?? [],
    },
    {
      label1: t('メンバー数'),
      label2: isRising ? t('公式急上昇の順位') : t('公式ランキングの順位'),
      category: graphStore.get(categoryAtom) === 'all' ? t('すべて') : chatArgDto.categoryName,
      isRising,
    },
    animation,
    limit
  )
}

const renderMemberChart = (data: ChartResponse, animation: boolean, limit: ChartLimit) => {
  chart.render(
    {
      date: data.date ?? [],
      graph1: data.member ?? [],
      graph2: [],
      time: [],
      totalCount: [],
    },
    {
      label1: t('メンバー数'),
      label2: '',
      category: chatArgDto.categoryName,
    },
    animation,
    limit
  )
}

const renderCandlestickChart = (animation: boolean, limit: ChartLimit) => {
  const sort = graphStore.get(rankingRisingAtom)
  const isRising = sort === 'rising'

  // ローソク足は OHLC をチャートのプロパティ(ohlcDate/memberOhlcApiData/positionOhlcApiData)で持つので、
  // 折れ線用の ChartArgs(date/graph1/...) は空でよい。
  chart.render(
    { date: [], graph1: [], graph2: [], time: [], totalCount: [] },
    {
      label1: t('メンバー数'),
      label2: sort === 'none' ? '' : isRising ? t('急上昇') : t('ランキング'),
      category: graphStore.get(categoryAtom) === 'all' ? t('すべて') : chatArgDto.categoryName,
      isRising,
    },
    animation,
    limit
  )
}

/** 取得済みのレスポンスを現在のチャート状態に従って描画する */
export function renderChartData(data: ChartResponse, animation: boolean) {
  graphStore.set(loadingAtom, false)
  // 取得に成功して描画できたので、もしエラー表示中だったらクリアする（エラー後の再操作で復帰）
  graphStore.set(errorAtom, false)

  const currentLimit = graphStore.get(limitAtom)
  const limit: ChartLimit = currentLimit === 25 ? 31 : currentLimit
  const sort = graphStore.get(rankingRisingAtom)

  if (graphStore.get(chartModeAtom) === 'candlestick') {
    // OHLC は ohlcDate（OHLC専用の日付軸）＋ index 整合の値配列で受け取る（日次 date/member は持たない）
    chart.ohlcDate = data.ohlcDate ?? []
    chart.memberOhlcApiData = data.memberOhlc ?? []
    chart.positionOhlcApiData = data.positionOhlc ?? []

    // 期間タブ表示は OHLC 本数で判定（順位ON/OFFどちらも renderCandlestickChart が描く）
    updateCandleTabVisibility(chart.ohlcDate)
    renderCandlestickChart(animation, limit)
    return
  }

  // 折れ線グラフモード: 日次データ数に基づいてタブ表示を復元
  updateTabVisibility(chartMeta.dateCount)

  if (sort === 'none') {
    renderMemberChart(data, animation, limit)
  } else {
    renderPositionChart(data, animation, limit)
  }
}

export async function fetchChart(animation: boolean) {
  graphStore.set(loadingAtom, true)
  try {
    renderChartData(await fetchChartData(), animation)
  } catch (e) {
    // 5xxをリトライしても取れない・403等で最終的に失敗。壊れた/空のグラフを出さず
    // エラー表示（再読み込み案内）に切り替える。
    console.error(e)
    // 作り直しに至らなかったので、切替前に保存した拡大窓を破棄する（後続の無関係な
    // 再構築が古い窓を誤って復元しないように）。
    chart.clearPendingZoomWindow()
    graphStore.set(loadingAtom, false)
    graphStore.set(errorAtom, true)
  }
}
