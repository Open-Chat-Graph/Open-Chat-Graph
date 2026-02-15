import { updateURLSearchParams } from "./util"

const categoryParam: urlParamsValue<'category'>[] = ['in', 'all']
const barParam: urlParamsValue<'bar'>[] = ['ranking', 'rising', 'none']
const limitParam: urlParamsValue<'limit'>[] = ['hour', 'week', 'month', 'all']
const chartParam: urlParamsValue<'chart'>[] = ['line', 'candlestick']

const validParam = <T extends urlParamsName>(definition: urlParamsValue<T>[], url: URL, name: T)
  : urlParamsValue<T> | null => {
  const param = url.searchParams.get(name) ?? ''
  return validParamString<T>(definition, param)
}

export const validParamString = <T extends urlParamsName>(definition: urlParamsValue<T>[], param: string)
  : urlParamsValue<T> | null => {
  return definition.includes(param as never) ? param as urlParamsValue<T> : null
}

const defaultBarLocalStorageName = 'chartDefaultBar'
const defaultCategoryLocalStorageName = 'chartDefaultCategory'
const defaultChartLocalStorageName = 'chartDefaultChart'
const fixedLimitLocalStorageName = 'chartFixedLimit'

export function setStoregeBarSetting(bar: ToggleChart) {
  localStorage.setItem(defaultBarLocalStorageName, bar)
}

export function setStoregeCategorySetting(category: urlParamsValue<'category'>) {
  localStorage.setItem(defaultCategoryLocalStorageName, category)
}

export function setStoregeChartSetting(chart: urlParamsValue<'chart'>) {
  localStorage.setItem(defaultChartLocalStorageName, chart)
}

export function setStoregeFixedLimitSetting(limit: urlParamsValue<'limit'> | '') {
  if (limit) localStorage.setItem(fixedLimitLocalStorageName, limit)
  else localStorage.removeItem(fixedLimitLocalStorageName)
}

function getStoregeBarSetting(defaultBar: ToggleChart) {
  const bar = localStorage.getItem(defaultBarLocalStorageName)
  return bar ? validParamString<'bar'>(barParam, bar) ?? defaultBar : defaultBar
}

function getStoregeCategorySetting(defaultCategory: urlParamsValue<'category'>) {
  const param = localStorage.getItem(defaultCategoryLocalStorageName)
  return param ? validParamString<'category'>(categoryParam, param) ?? defaultCategory : defaultCategory
}

function getStoregeChartSetting(): urlParamsValue<'chart'> {
  const v = localStorage.getItem(defaultChartLocalStorageName)
  return v ? validParamString<'chart'>(chartParam, v) ?? 'line' : 'line'
}

export function getStoregeFixedLimitSetting(): urlParamsValue<'limit'> | null {
  const v = localStorage.getItem(fixedLimitLocalStorageName)
  return v ? validParamString<'limit'>(limitParam, v) : null
}

export const defaultCategory: urlParamsValue<'category'> = getStoregeCategorySetting('in')
export const defaultBar: urlParamsValue<'bar'> = getStoregeBarSetting('none')
export const defaultLimit: urlParamsValue<'limit'> = 'week'
export const defaultLimitNum: ChartLimit | 25 = 8
export const defaultChart: urlParamsValue<'chart'> = getStoregeChartSetting()

export function getCurrentUrlParams(): urlParams {
  const url = new URL(window.location.href);
  return {
    category: validParam<'category'>(categoryParam, url, 'category') ?? defaultCategory,
    bar: validParam<'bar'>(barParam, url, 'bar') ?? defaultBar,
    limit: validParam<'limit'>(limitParam, url, 'limit') ?? defaultLimit,
    chart: validParam<'chart'>(chartParam, url, 'chart') ?? defaultChart,
  }
}

export function setUrlParams(params: urlParams) {
  window.history.replaceState(null, '', updateURLSearchParams(
    {
      bar: params.bar === defaultBar ? '' : params.bar,
      category: params.category === defaultCategory || params.bar === 'none' ? '' : params.category,
      limit: params.limit === defaultLimit ? '' : params.limit,
      chart: params.chart === defaultChart ? '' : params.chart,
    }
  ))
}

