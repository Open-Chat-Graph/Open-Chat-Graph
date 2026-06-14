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

/**
 * 現在のチャート状態が指す表示ビューをAPIクエリ文字列にする。
 * 同一ビューは同一URLになるため、fetcher のURLキャッシュがそのまま効く
 */
export function getChartViewQuery(withMeta = false): string {
  const mode = graphStore.get(chartModeAtom)
  const sort = graphStore.get(rankingRisingAtom)
  const span = getCurrentSpan()
  const scope = sort !== 'none' && graphStore.get(categoryAtom) !== 'all' ? 'in' : 'all'

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

/**
 * 差分フェッチの蓄積。
 *
 * 表示ビュー(span=day)毎に「ある開始日から最新日(endDate)まで」の日次系列を累積で持つ。
 * 期間タブを広げると足りない古い側だけ取得して prepend マージし、loadedFrom を更新する。
 * 窓は最新日終端固定なので、足りないのは常に「古い側の連続1範囲」だけになる（窓⊂窓）。
 *
 * - キー: `${sort}|${scope}|${mode}`（span=day のみ。category は部屋固定なので scope に内包される）
 * - loadedFrom: 蓄積が保持している最古の日付(Y-m-d)
 * - data: loadedFrom〜endDate の累積レスポンス（chart.render にそのまま渡す initData）
 */
const accumulation = new Map<string, { loadedFrom: string; data: ChartResponse }>()

/** 現在の表示ビュー(span=day)の蓄積キー */
function currentConfigKey(): string {
  const mode = graphStore.get(chartModeAtom)
  const sort = graphStore.get(rankingRisingAtom)
  const scope = sort !== 'none' && graphStore.get(categoryAtom) !== 'all' ? 'in' : 'all'
  return `${sort}|${scope}|${mode}`
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

/** 現在の表示ビューの蓄積が、指定 limit の窓を既に満たしているか（追加取得不要か） */
export function currentConfigCoversLimit(limit: ChartLimit): boolean {
  const entry = accumulation.get(currentConfigKey())
  if (!entry) return false
  return entry.loadedFrom <= windowFromForLimit(limit)
}

/** 範囲(from/to)付きで現在ビューの系列を取得する。fetcher のURLキャッシュは範囲ごとに効く */
async function fetchRange(from: string, to: string): Promise<ChartResponse> {
  return fetcher<ChartResponse>(
    `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/chart?${getChartViewQuery()}&from=${from}&to=${to}`
  )
}

/** 古い差分(older)を既存(existing)の先頭に prepend マージする。窓は重複しないので連結のみ。 */
function prependMerge(older: ChartResponse, existing: ChartResponse): ChartResponse {
  const cat = <T>(a: T[] | undefined, b: T[] | undefined): T[] =>
    (a ?? []).concat(b ?? [])
  return {
    date: cat(older.date, existing.date),
    member: cat(older.member, existing.member),
    time: cat(older.time, existing.time),
    position: cat(older.position, existing.position),
    totalCount: cat(older.totalCount, existing.totalCount),
    memberOhlc:
      older.memberOhlc || existing.memberOhlc
        ? cat(older.memberOhlc, existing.memberOhlc)
        : undefined,
    positionOhlc:
      older.positionOhlc || existing.positionOhlc
        ? cat(older.positionOhlc, existing.positionOhlc)
        : undefined,
  }
}

/**
 * 表示中の期間タブの窓だけを取得し、拡大時は足りない古い差分だけ取得して蓄積する。
 *
 * - span=hour（最新24時間）: 範囲化対象外。従来どおり全24hを取得し蓄積に入れない。
 * - 埋め込みメタ無し(withMeta=true)の新規室: 窓計算ができないため全期間を取得し、
 *   その data.date[0] を loadedFrom として蓄積する（以後は全期間が手元にあり追加取得不要）。
 * - mode=candlestick: 全期間で取得する。ローソク足のタブ表示判定 updateCandleTabVisibility は
 *   「長いタブが短いタブより本数が多いか」を表示中 data の日付配列で数えて決めるため、
 *   窓だけ持つと（例: 週タブ表示中は week 窓ぶんしか日付が無く）月/全タブの本数を
 *   過小カウントしてタブが誤って消える。可用性メタ(ohlcAvailability)は閾値booleanのみで
 *   この本数比較を再現できないため、ローソク足は全期間取得で従来の判定を厳密に維持する。
 * - それ以外(day, line, 埋め込みメタ有): 現在 limit の窓を取得/差分マージし、蓄積 data を返す。
 *
 * 返り値は「現在ビューの蓄積 data（= chart.render に渡す initData）」。
 * chart 側が limit で末尾スライスするため、窓より広い蓄積でも正しく表示される。
 */
export async function fetchChartData(withMeta = false): Promise<ChartResponse> {
  // 最新24時間ビューは範囲化しない（毎時データはMariaDB・全24h固定）
  if (getCurrentSpan() === 'hour') {
    const data = await fetcher<ChartResponse>(
      `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/chart?${getChartViewQuery(withMeta)}`
    )
    if (data.meta) chartMeta = data.meta
    return data
  }

  // 埋め込みメタが無い新規室: 窓を計算できないので全期間フォールバック（from/to 無し）。
  // 取得後 chartMeta が埋まり、蓄積は全期間(loadedFrom=最古日)として記録する。
  // ローソク足モードも全期間で取得する（後述）。
  if (withMeta || graphStore.get(chartModeAtom) === 'candlestick') {
    const data = await fetcher<ChartResponse>(
      `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/chart?${getChartViewQuery(withMeta)}`
    )
    if (data.meta) chartMeta = data.meta
    accumulation.set(currentConfigKey(), {
      loadedFrom: data.date[0] ?? chartMeta.startDate,
      data,
    })
    return data
  }

  const key = currentConfigKey()
  const neededFrom = windowFromForLimit(graphStore.get(limitAtom) as ChartLimit)
  const entry = accumulation.get(key)

  // 蓄積なし: 現在 limit の窓だけ取得して蓄積を作る
  if (!entry) {
    const data = await fetchRange(neededFrom, chartMeta.endDate)
    accumulation.set(key, { loadedFrom: neededFrom, data })
    return data
  }

  // 既に足りる: 取得せず蓄積を使う（chart 側が limit でスライス）
  if (entry.loadedFrom <= neededFrom) {
    return entry.data
  }

  // 拡大: 足りない古い側(neededFrom 〜 loadedFromの前日)だけ取得して prepend マージ
  const older = await fetchRange(neededFrom, addDays(entry.loadedFrom, -1))
  const merged = prependMerge(older, entry.data)
  accumulation.set(key, { loadedFrom: neededFrom, data: merged })
  return merged
}

const renderPositionChart = (data: ChartResponse, animation: boolean, limit: ChartLimit) => {
  const isRising = graphStore.get(rankingRisingAtom) === 'rising'

  chart.render(
    {
      date: data.date,
      graph1: data.member,
      graph2: data.position,
      time: data.time,
      totalCount: data.totalCount,
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
      date: data.date,
      graph1: data.member,
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

const renderCandlestickChart = (data: ChartResponse, animation: boolean, limit: ChartLimit) => {
  const isRising = graphStore.get(rankingRisingAtom) === 'rising'

  chart.render(
    {
      date: data.date,
      graph1: data.member,
      graph2: [],
      time: [],
      totalCount: [],
      rankingOhlc: data.positionOhlc,
    },
    {
      label1: t('メンバー数'),
      label2: isRising ? t('急上昇') : t('ランキング'),
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
    chart.memberOhlcApiData = data.memberOhlc ?? []

    // 期間タブ毎のローソク足本数に基づいてタブ表示を更新
    updateCandleTabVisibility(chart.memberOhlcApiData, data.date)

    if (sort === 'none') {
      renderMemberChart(data, animation, limit)
    } else {
      renderCandlestickChart(data, animation, limit)
    }
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
    graphStore.set(loadingAtom, false)
    graphStore.set(errorAtom, true)
  }
}
