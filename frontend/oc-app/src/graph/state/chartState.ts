import { atom } from 'jotai'
import { graphStore } from './store'
import { chartMeta, chatArgDto, fetchChart } from '../util/fetchRenderer'
import OpenChatChart from '../classes/OpenChatChart'
import { getCurrentUrlParams, getStoregeFixedLimitSetting, setUrlParams } from '../util/urlParam'
import { trackEvent } from '../../util/track'

export const chart = new OpenChatChart()
export const loadingAtom = atom(false)
export const toggleShowCategoryAtom = atom(true)
export const rankingRisingAtom = atom<ToggleChart>('none')
export const categoryAtom = atom<urlParamsValue<'category'>>('in')
export const limitAtom = atom<ChartLimit | 25>(8)
export const zoomEnableAtom = atom(false)
export const chartModeAtom = atom<ChartMode>('line')

// Atoms moved from components to resolve circular dependencies
export const renderTabAtom = atom(false)
export const renderPositionBtnsAtom = atom(false)
export const toggleDisplay24hAtom = atom(true)
export const toggleDisplayWeekAtom = atom(true)
export const toggleDisplayMonthAtom = atom(true)
export const toggleDisplayAllAtom = atom(true)

let isInitialLoad = true

export function setChartStatesFromUrlParams() {
  const params = getCurrentUrlParams()
  graphStore.set(rankingRisingAtom, params.bar)
  graphStore.set(categoryAtom, params.category)

  switch (params.limit) {
    case 'hour':
      graphStore.set(limitAtom, 25)
      chart.setIsHour(true)
      break
    case 'week':
      graphStore.set(limitAtom, 8)
      break
    case 'month':
      graphStore.set(limitAtom, 31)
      break
    case 'all':
      graphStore.set(limitAtom, 0)
      break
  }

  // 初回読込時のみ: 期間固定オプションでlimitを上書き
  if (isInitialLoad) {
    const fixedLimit = getStoregeFixedLimitSetting()
    if (fixedLimit) {
      switch (fixedLimit) {
        case 'hour':
          graphStore.set(limitAtom, 25)
          chart.setIsHour(true)
          break
        case 'week':
          graphStore.set(limitAtom, 8)
          chart.setIsHour(false)
          break
        case 'month':
          graphStore.set(limitAtom, 31)
          chart.setIsHour(false)
          break
        case 'all':
          graphStore.set(limitAtom, 0)
          chart.setIsHour(false)
          break
      }
    }
  }

  // ローソク足モードの復元（24時間タブにローソク足は無い。OHLCデータ有無はメタ取得後に判定）
  if (params.chart === 'candlestick' && graphStore.get(limitAtom) !== 25) {
    graphStore.set(chartModeAtom, 'candlestick')
    chart.setMode('candlestick')
  }
}

export function markInitialLoadComplete() {
  isInitialLoad = false
}

export function setUrlParamsFromChartStates() {
  let limit: urlParamsValue<'limit'> = 'hour'
  switch (graphStore.get(limitAtom)) {
    case 8:
      limit = 'week'
      break
    case 31:
      limit = 'month'
      break
    case 0:
      limit = 'all'
      break
  }

  setUrlParams({
    bar: graphStore.get(rankingRisingAtom),
    category: graphStore.get(categoryAtom),
    limit,
    chart: graphStore.get(chartModeAtom),
  })
}

/** カテゴリがその他・未掲載の部屋の初期状態を設定する（メタデータ不要、初回フェッチ前に実行） */
export function applyUncategorizedDefaults() {
  if (chatArgDto.categoryKey === 0) {
    graphStore.set(toggleShowCategoryAtom, false)
    graphStore.set(categoryAtom, 'all')
    graphStore.get(rankingRisingAtom) !== 'rising' && graphStore.set(rankingRisingAtom, 'none')
  }
}

/**
 * 初回ロードで取得した可用性メタデータに基づき、データが無いビューからフォールバックする。
 * ここで表示ビューが変わった場合のみ補正フェッチが必要になる
 */
export function applyAvailabilityFallbacks() {
  // 日次データ数に基づいてタブ表示を設定
  updateTabVisibility(chartMeta.dateCount)

  // 最新24時間タブ: 毎時メンバー数データが無い場合は非表示
  if (!chartMeta.hourAvailability) {
    chart.setIsHour(false)
    graphStore.set(toggleDisplay24hAtom, false)
    graphStore.get(limitAtom) === 25 && graphStore.set(limitAtom, 8)
  }

  // ローソク足モード: 表示中の期間タブにOHLCデータが無い場合は折れ線に戻す
  if (graphStore.get(chartModeAtom) === 'candlestick' && !hasOhlcDataForLimit(graphStore.get(limitAtom))) {
    graphStore.set(chartModeAtom, 'line')
    chart.setMode('line')
  }

  // ランキング順位データが全期間・全組み合わせで0件の場合は「ランキングの順位を表示」を丸ごと非表示
  if (!hasAnyPositionData()) {
    graphStore.set(renderPositionBtnsAtom, false)
    graphStore.set(categoryAtom, 'in')
    graphStore.set(rankingRisingAtom, 'none')
    return
  }

  graphStore.set(renderPositionBtnsAtom, true)
  sanitizePositionSelection()
}

const emptyPositionAvailability: PositionAvailability = {
  ranking_in: false,
  ranking_all: false,
  rising_in: false,
  rising_all: false,
}

/** 指定の期間タブの順位データ有無を取得する */
export function getPositionAvailabilityForLimit(limit: ChartLimit | 25): PositionAvailability {
  const availability = chartMeta.positionAvailability
  if (!availability) return emptyPositionAvailability

  switch (limit) {
    case 25:
      return availability.hour ?? emptyPositionAvailability
    case 8:
      return availability.week ?? emptyPositionAvailability
    case 31:
      return availability.month ?? emptyPositionAvailability
    case 0:
      return availability.all ?? emptyPositionAvailability
  }
}

/** いずれかの期間・組み合わせで順位データが存在するか */
export function hasAnyPositionData(): boolean {
  const availability = chartMeta.positionAvailability
  if (!availability) return false

  return Object.values(availability).some(
    (p) => p && (p.ranking_in || p.ranking_all || p.rising_in || p.rising_all)
  )
}

/**
 * 現在の期間タブで選択中の順位表示(種別×カテゴリ)にデータが無い場合、
 * データのある組み合わせへフォールバックする
 *
 * @returns 選択を変更した場合 true
 */
function sanitizePositionSelection(): boolean {
  const sort = graphStore.get(rankingRisingAtom)
  if (sort === 'none') return false

  const avail = getPositionAvailabilityForLimit(graphStore.get(limitAtom))

  // 種別自体にデータが無い場合は順位表示を解除する
  if (!avail[`${sort}_in`] && !avail[`${sort}_all`]) {
    graphStore.set(rankingRisingAtom, 'none')
    return true
  }

  // 選択中のカテゴリにデータが無い場合はもう一方へ切り替える
  const categoryKey = graphStore.get(categoryAtom) === 'all' ? 'all' : 'in'
  if (!avail[`${sort}_${categoryKey}`]) {
    graphStore.set(categoryAtom, categoryKey === 'all' ? 'in' : 'all')
    return true
  }

  return false
}

export function handleChangeLimit(limit: ChartLimit | 25) {
  graphStore.set(limitAtom, limit)

  // 移動先の期間タブでデータが無い順位表示(種別×カテゴリ)を解除する
  const selectionChanged = sanitizePositionSelection()

  // 移動先の期間タブにOHLCデータが無い場合は折れ線グラフに戻す
  const fallbackToLine =
    graphStore.get(chartModeAtom) === 'candlestick' && !hasOhlcDataForLimit(limit)
  if (fallbackToLine) {
    graphStore.set(chartModeAtom, 'line')
    chart.setMode('line')
  }

  if (limit === 25) {
    chart.setIsHour(true)
    fetchChart(true)
  } else if (chart.getIsHour()) {
    chart.setIsHour(false)
    fetchChart(true)
  } else if (fallbackToLine || selectionChanged) {
    // モードまたは順位表示の選択が変わるため、表示中のデータのままでは再描画できない
    fetchChart(true)
  } else {
    chart.update(limit)
  }

  setUrlParamsFromChartStates()
  trackChartChange('period')
}

// グラフ操作はどれでも chart_change を送り、period/mode/rank_type/category の全状態と、
// 今回変えた項目(changed)を毎回含める。これで「ローソク足×1ヶ月」等の組み合わせを集計できる。
// (URLはreplaceStateで書くだけでGA4は遷移後を拾えないため、状態はイベントに載せる)
function trackChartChange(changed: 'period' | 'mode' | 'rank' | 'category') {
  const limit = graphStore.get(limitAtom)
  const period = limit === 25 ? '24h' : limit === 8 ? '1week' : limit === 31 ? '1month' : 'all'
  trackEvent('chart_change', {
    changed,
    period,
    mode: graphStore.get(chartModeAtom),
    rank_type: graphStore.get(rankingRisingAtom),
    category: graphStore.get(categoryAtom),
  })
}

export function handleChangeCategory(alignment: urlParamsValue<'category'> | null) {
  if (!alignment) return
  graphStore.set(categoryAtom, alignment)
  fetchChart(false)
  setUrlParamsFromChartStates()
  trackChartChange('category')
}

export function handleChangeRankingRising(alignment: ToggleChart) {
  graphStore.set(rankingRisingAtom, alignment)

  // 選択した種別で現在のカテゴリにデータが無い場合、データのあるカテゴリへ切り替える
  if (alignment !== 'none') {
    const avail = getPositionAvailabilityForLimit(graphStore.get(limitAtom))
    const categoryKey = graphStore.get(categoryAtom) === 'all' ? 'all' : 'in'
    if (!avail[`${alignment}_${categoryKey}`]) {
      graphStore.set(categoryAtom, categoryKey === 'all' ? 'in' : 'all')
    }
  }

  fetchChart(false)
  setUrlParamsFromChartStates()
  trackChartChange('rank')
}

export function handleChangeEnableZoom(value: boolean) {
  graphStore.set(zoomEnableAtom, value)
  chart.updateEnableZoom(value)
}

export function hasOhlcData(): boolean {
  return chartMeta.ohlcAvailability?.all ?? false
}

/** 指定の期間タブにローソク足(OHLC)データが存在するか（24時間タブは常にfalse） */
export function hasOhlcDataForLimit(limit: ChartLimit | 25): boolean {
  const availability = chartMeta.ohlcAvailability
  if (!availability) return false

  switch (limit) {
    case 8:
      return availability.week
    case 31:
      return availability.month
    case 0:
      return availability.all
    default:
      return false
  }
}

export function updateTabVisibility(dataLength: number) {
  graphStore.set(toggleDisplayWeekAtom, true)
  graphStore.set(toggleDisplayMonthAtom, dataLength > 8)
  graphStore.set(toggleDisplayAllAtom, dataLength > 31)
  fallbackHiddenLimit()
}

/**
 * ローソク足モード: 期間タブ毎のウィンドウ内ローソク足本数に基づいてタブ表示を設定する
 *
 * - ローソク足を利用できない期間（折れ線モードで切替ボタンがグレーアウトになる期間）の
 *   タブは非表示にする
 * - OHLCデータは記録期間が限られるため（例: 過去のみに存在する部屋）、
 *   より短いタブで全て表示しきれる冗長な長いタブも非表示にする
 */
export function updateCandleTabVisibility(ohlcData: MemberOhlc[], dates: string[]) {
  const ohlcDateSet = new Set(ohlcData.map((r) => r.date))
  const countInWindow = (limit: number) => {
    let count = 0
    for (let i = limit ? Math.max(0, dates.length - limit) : 0; i < dates.length; i++) {
      if (ohlcDateSet.has(dates[i])) count++
    }
    return count
  }

  const weekCount = countInWindow(8)
  const monthCount = countInWindow(31)
  const allCount = countInWindow(0)

  const showWeek = hasOhlcDataForLimit(8)
  const showMonth = hasOhlcDataForLimit(31) && monthCount > (showWeek ? weekCount : 0)
  const showAll = allCount > (showMonth ? monthCount : showWeek ? weekCount : 0)

  graphStore.set(toggleDisplayWeekAtom, showWeek)
  graphStore.set(toggleDisplayMonthAtom, showMonth)
  graphStore.set(toggleDisplayAllAtom, showAll)
  fallbackHiddenLimit()
}

/** 非表示になったタブが選択中の場合、表示中のタブにフォールバックする */
function fallbackHiddenLimit() {
  const display: Record<ChartLimit, boolean> = {
    8: graphStore.get(toggleDisplayWeekAtom),
    31: graphStore.get(toggleDisplayMonthAtom),
    0: graphStore.get(toggleDisplayAllAtom),
  }

  const limit = graphStore.get(limitAtom)
  if (limit === 25 || display[limit]) return

  const fallback = ([31, 8, 0] as ChartLimit[]).find((l) => l !== limit && display[l])
  fallback !== undefined && graphStore.set(limitAtom, fallback)
}

export function handleChangeChartMode(mode: ChartMode) {
  graphStore.set(chartModeAtom, mode)
  chart.setMode(mode)

  if (mode === 'candlestick') {
    if (graphStore.get(limitAtom) === 25) {
      graphStore.set(limitAtom, 8)
      chart.setIsHour(false)
    }
  }

  fetchChart(true)
  setUrlParamsFromChartStates()
  trackChartChange('mode')
}
