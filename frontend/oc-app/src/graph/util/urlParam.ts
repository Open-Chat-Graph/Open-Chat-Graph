const categoryParam: urlParamsValue<'category'>[] = ['in', 'all']
const barParam: urlParamsValue<'bar'>[] = ['ranking', 'rising', 'none']
const limitParam: urlParamsValue<'limit'>[] = ['hour', 'week', 'month', 'all']
const chartParam: urlParamsValue<'chart'>[] = ['line', 'candlestick']

const graphStateParams = (url: URL): URLSearchParams => {
  if (!url.hash.startsWith('#graph')) return new URLSearchParams()
  const queryIndex = url.hash.indexOf('?')
  return new URLSearchParams(queryIndex === -1 ? '' : url.hash.slice(queryIndex + 1))
}

const validParam = <T extends urlParamsName>(
  definition: urlParamsValue<T>[],
  params: URLSearchParams,
  name: T
): urlParamsValue<T> | null => {
  const param = params.get(name) ?? ''
  return validParamString<T>(definition, param)
}

export const validParamString = <T extends urlParamsName>(
  definition: urlParamsValue<T>[],
  param: string
): urlParamsValue<T> | null => {
  return definition.includes(param as never) ? (param as urlParamsValue<T>) : null
}

const defaultBarLocalStorageName = 'chartDefaultBar'
const defaultCategoryLocalStorageName = 'chartDefaultCategory'
const defaultChartLocalStorageName = 'chartDefaultChart'
const fixedLimitLocalStorageName = 'chartFixedLimit'
const rankEmphasisLocalStorageName = 'chartRankEmphasis'

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
  return bar ? (validParamString<'bar'>(barParam, bar) ?? defaultBar) : defaultBar
}

function getStoregeCategorySetting(defaultCategory: urlParamsValue<'category'>) {
  const param = localStorage.getItem(defaultCategoryLocalStorageName)
  return param
    ? (validParamString<'category'>(categoryParam, param) ?? defaultCategory)
    : defaultCategory
}

function getStoregeChartSetting(): urlParamsValue<'chart'> {
  const v = localStorage.getItem(defaultChartLocalStorageName)
  return v ? (validParamString<'chart'>(chartParam, v) ?? 'line') : 'line'
}

export function getStoregeFixedLimitSetting(): urlParamsValue<'limit'> | null {
  const v = localStorage.getItem(fixedLimitLocalStorageName)
  return v ? validParamString<'limit'>(limitParam, v) : null
}

/** 順位バー「上位を強調」設定（非線形スケール）。未設定は既定でON */
export function setStoregeRankEmphasis(on: boolean) {
  localStorage.setItem(rankEmphasisLocalStorageName, on ? '1' : '0')
}

function getStoregeRankEmphasis(): boolean {
  return localStorage.getItem(rankEmphasisLocalStorageName) !== '0'
}

export const defaultCategory: urlParamsValue<'category'> = getStoregeCategorySetting('in')
export const defaultBar: urlParamsValue<'bar'> = getStoregeBarSetting('none')
export const defaultLimit: urlParamsValue<'limit'> = 'week'
export const defaultLimitNum: ChartLimit | 25 = 8
export const defaultChart: urlParamsValue<'chart'> = getStoregeChartSetting()
export const defaultRankEmphasis: boolean = getStoregeRankEmphasis()

export function getCurrentUrlParams(): urlParams {
  const url = new URL(window.location.href)
  const params = graphStateParams(url)
  return {
    category: validParam<'category'>(categoryParam, params, 'category') ?? defaultCategory,
    bar: validParam<'bar'>(barParam, params, 'bar') ?? defaultBar,
    limit: validParam<'limit'>(limitParam, params, 'limit') ?? defaultLimit,
    chart: validParam<'chart'>(chartParam, params, 'chart') ?? defaultChart,
  }
}

export function setUrlParams(params: urlParams) {
  const state = new URLSearchParams()
  const values = {
    bar: params.bar === defaultBar ? '' : params.bar,
    category: params.category === defaultCategory || params.bar === 'none' ? '' : params.category,
    limit: params.limit === defaultLimit ? '' : params.limit,
    chart: params.chart === defaultChart ? '' : params.chart,
  }
  Object.entries(values).forEach(([key, value]) => value && state.set(key, value))
  const url = new URL(window.location.href)
  url.hash = `graph${state.size ? `?${state.toString()}` : ''}`
  window.history.replaceState(null, '', url)
}
