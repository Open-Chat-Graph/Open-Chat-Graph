import { atom } from 'jotai'
import { graphStore } from './store'
import { chatArgDto, fetchChart, statsDto } from '../util/fetchRenderer'
import OpenChatChart from '../classes/OpenChatChart'
import { getCurrentUrlParams, getStoregeFixedLimitSetting, setUrlParams } from '../util/urlParam'

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

  // ローソク足モードの復元（表示中の期間タブにOHLCデータが存在する場合のみ）
  if (params.chart === 'candlestick' && hasOhlcDataForLimit(graphStore.get(limitAtom))) {
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

export function initDisplay() {
  // カテゴリがその他の場合
  if (chatArgDto.categoryKey === 0) {
    graphStore.set(toggleShowCategoryAtom, false)
    graphStore.set(categoryAtom, 'all')
    graphStore.get(rankingRisingAtom) !== 'rising' && graphStore.set(rankingRisingAtom, 'none')
  }

  // データ数に基づいてタブ表示を設定
  updateTabVisibility(statsDto.date.length)

  // ランキング未掲載の場合
  if (chatArgDto.categoryKey === null) {
    graphStore.set(renderPositionBtnsAtom, false)
    chart.setIsHour(false)
    graphStore.set(toggleDisplay24hAtom, false)

    graphStore.set(categoryAtom, 'in')
    graphStore.set(rankingRisingAtom, 'none')
    graphStore.get(limitAtom) === 25 && graphStore.set(limitAtom, 8)

    return false
  }

  return true
}

export function handleChangeLimit(limit: ChartLimit | 25) {
  graphStore.set(limitAtom, limit)

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
  } else if (fallbackToLine) {
    // モードが変わるため折れ線グラフ用のデータで再取得する
    fetchChart(true)
  } else {
    chart.update(limit)
  }

  setUrlParamsFromChartStates()
}

export function handleChangeCategory(alignment: urlParamsValue<'category'> | null) {
  if (!alignment) return
  graphStore.set(categoryAtom, alignment)
  fetchChart(false)
  setUrlParamsFromChartStates()
}

export function handleChangeRankingRising(alignment: ToggleChart) {
  graphStore.set(rankingRisingAtom, alignment)
  fetchChart(false)
  setUrlParamsFromChartStates()
}

export function handleChangeEnableZoom(value: boolean) {
  graphStore.set(zoomEnableAtom, value)
  chart.updateEnableZoom(value)
}

export function hasOhlcData(): boolean {
  return statsDto.ohlcAvailability?.all ?? false
}

/** 指定の期間タブにローソク足(OHLC)データが存在するか（24時間タブは常にfalse） */
export function hasOhlcDataForLimit(limit: ChartLimit | 25): boolean {
  const availability = statsDto.ohlcAvailability
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
  graphStore.set(toggleDisplayMonthAtom, dataLength > 8)
  graphStore.set(toggleDisplayAllAtom, dataLength > 31)
  fallbackHiddenLimit()
}

/**
 * ローソク足モード: 期間タブ毎のウィンドウ内ローソク足本数に基づいてタブ表示を設定する
 *
 * OHLCデータは記録期間が限られるため（例: 過去のみに存在する部屋）、
 * 件数ではなく各タブのウィンドウに実際に入る本数で判定する
 */
export function updateCandleTabVisibility(ohlcData: MemberOhlc[]) {
  const dates = statsDto.date
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

  // より短いタブで全て表示しきれないローソク足がある場合のみ表示
  graphStore.set(toggleDisplayMonthAtom, monthCount > weekCount)
  graphStore.set(toggleDisplayAllAtom, allCount > monthCount)
  fallbackHiddenLimit()
}

/** 非表示になったタブが選択中の場合、表示中のタブにフォールバックする */
function fallbackHiddenLimit() {
  if (graphStore.get(limitAtom) === 0 && !graphStore.get(toggleDisplayAllAtom)) {
    graphStore.set(limitAtom, graphStore.get(toggleDisplayMonthAtom) ? 31 : 8)
  }
  if (graphStore.get(limitAtom) === 31 && !graphStore.get(toggleDisplayMonthAtom)) {
    graphStore.set(limitAtom, 8)
  }
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
}
