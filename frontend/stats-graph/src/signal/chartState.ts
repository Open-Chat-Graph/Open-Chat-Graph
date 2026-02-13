import { signal } from "@preact/signals"
import { chatArgDto, fetchChart, statsDto } from "../util/fetchRenderer"
import OpenChatChart from "../classes/OpenChatChart"
import { getCurrentUrlParams, setUrlParams } from "../util/urlParam"
import { toggleDisplay24h, toggleDisplayAll, toggleDisplayMonth } from "../components/ChartLimitBtns"
import { setRenderPositionBtns } from "../app"

export const chart = new OpenChatChart
export const loading = signal(false)
export const toggleShowCategorySignal = signal(true)
export const rankingRisingSignal = signal<ToggleChart>('none')
export const categorySignal = signal<urlParamsValue<'category'>>('in')
export const limitSignal = signal<ChartLimit | 25>(8)
export const zoomEnableSignal = signal(false)

export function setChartStatesFromUrlParams() {
  const params = getCurrentUrlParams()
  rankingRisingSignal.value = params.bar
  categorySignal.value = params.category

  switch (params.limit) {
    case "hour":
      limitSignal.value = 25
      chart.setIsHour(true)
      break
    case "week":
      limitSignal.value = 8
      break
    case "month":
      limitSignal.value = 31
      break
    case "all":
      limitSignal.value = 0
      break
  }
}

export function setUrlParamsFromChartStates() {
  let limit: urlParamsValue<'limit'> = 'hour'
  switch (limitSignal.value) {
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

  setUrlParams({ bar: rankingRisingSignal.value, category: categorySignal.value, limit })
}

export function initDisplay() {
  // カテゴリがその他の場合
  if (chatArgDto.categoryKey === 0) {
    toggleShowCategorySignal.value = false
    categorySignal.value = 'all'
    rankingRisingSignal.value !== 'rising' && (rankingRisingSignal.value = 'none')
  }

  // 最新１週間のデータがない場合
  if (statsDto.date.length <= 8) {
    toggleDisplayMonth.value = false
  }

  // 最新1ヶ月のデータがない場合
  if (statsDto.date.length <= 31) {
    toggleDisplayAll.value = false
  }

  // ランキング未掲載の場合
  if (chatArgDto.categoryKey === null) {
    setRenderPositionBtns(false)
    chart.setIsHour(false)
    toggleDisplay24h.value = false

    categorySignal.value = 'in'
    rankingRisingSignal.value = 'none'
    limitSignal.value === 25 && (limitSignal.value = 8)

    return false
  }

  return true
}

export function handleChangeLimit(limit: ChartLimit | 25) {
  limitSignal.value = limit

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
  categorySignal.value = alignment
  fetchChart(false)
  setUrlParamsFromChartStates()
}

export function handleChangeRankingRising(alignment: ToggleChart) {
  rankingRisingSignal.value = alignment
  fetchChart(false)
  setUrlParamsFromChartStates()
}

export function handleChangeEnableZoom(value: boolean) {
  zoomEnableSignal.value = value
  chart.updateEnableZoom(value)
}