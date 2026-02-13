import { updateURLSearchParams } from "./util"

const categoryParam: urlParamsValue<'category'>[] = ['in', 'all']
const barParam: urlParamsValue<'bar'>[] = ['ranking', 'rising', 'none']
const limitParam: urlParamsValue<'limit'>[] = ['hour', 'week', 'month', 'all']

const validParam = <T extends urlParamsName>(definition: urlParamsValue<T>[], url: URL, name: T)
  : urlParamsValue<T> | null => {
  const param = url.searchParams.get(name) ?? ''
  return validParamString<T>(definition, param)
}

const validParamString = <T extends urlParamsName>(definition: urlParamsValue<T>[], param: string)
  : urlParamsValue<T> | null => {
  return definition.includes(param as never) ? param as urlParamsValue<T> : null
}

const defaultBarLocalStorageName = 'chartDefaultBar'
const defaultCategoryLocalStorageName = 'chartDefaultCategory'

export function setStoregeBarSetting(bar: ToggleChart) {
  localStorage.setItem(defaultBarLocalStorageName, bar)
}

export function setStoregeCategorySetting(category: urlParamsValue<'category'>) {
  localStorage.setItem(defaultCategoryLocalStorageName, category)
}

function getStoregeBarSetting(defaultBar: ToggleChart) {
  const bar = localStorage.getItem(defaultBarLocalStorageName)
  return bar ? validParamString<'bar'>(barParam, bar) ?? defaultBar : defaultBar
}

function getStoregeCategorySetting(defaultCategory: urlParamsValue<'category'>) {
  const param = localStorage.getItem(defaultCategoryLocalStorageName)
  return param ? validParamString<'category'>(categoryParam, param) ?? defaultCategory : defaultCategory
}

export const defaultCategory: urlParamsValue<'category'> = getStoregeCategorySetting('in')
export const defaultBar: urlParamsValue<'bar'> = getStoregeBarSetting('none')
export const defaultLimit: urlParamsValue<'limit'> = 'week'
export const defaultLimitNum: ChartLimit | 25 = 8

export function getCurrentUrlParams(): urlParams {
  const url = new URL(window.location.href);
  return {
    category: validParam<'category'>(categoryParam, url, 'category') ?? defaultCategory,
    bar: validParam<'bar'>(barParam, url, 'bar') ?? defaultBar,
    limit: validParam<'limit'>(limitParam, url, 'limit') ?? defaultLimit,
  }
}

export function setUrlParams(params: urlParams) {
  window.history.replaceState(null, '', updateURLSearchParams(
    {
      bar: params.bar === defaultBar ? '' : params.bar,
      category: params.category === defaultCategory || params.bar === 'none' ? '' : params.category,
      limit: params.limit === defaultLimit ? '' : params.limit
    }
  ))
}

