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

  // ローソク足モードの復元（OHLCデータが存在する場合のみ、24時間モードでない場合）
  if (params.chart === 'candlestick' && hasOhlcData() && graphStore.get(limitAtom) !== 25) {
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

  if (graphStore.get(chartModeAtom) === 'candlestick' && limit === 25) {
    graphStore.set(chartModeAtom, 'line')
    chart.setMode('line')
  }

  if (limit === 25) {
    chart.setIsHour(true)
    fetchChart(true)
  } else if (chart.getIsHour()) {
    chart.setIsHour(false)
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
  return statsDto.date.length > 1
}

export function updateTabVisibility(dataLength: number) {
  graphStore.set(toggleDisplayMonthAtom, dataLength > 8)
  graphStore.set(toggleDisplayAllAtom, dataLength > 31)

  // 非表示になったタブが選択中の場合、表示中のタブにフォールバック
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
