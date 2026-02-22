import { useCallback } from 'react'
import { useSetAtom, useAtomValue, createStore } from 'jotai'
import { keywordState, listParamsState } from '../store/atom'
import { scrollToTop, setTitle, updateURLSearchParams } from '../utils/utils'
import { useLocation } from 'react-router-dom'

const getValidParam = <T extends ListParamsKey>(
  value: string | null,
  defaultValue: ListParamsValue<T>,
  expectValues: ListParamsValue<T>[]
) => (expectValues.some((ex) => value === ex) ? (value as ListParamsValue<T>) : defaultValue)

type ValidListParams = { list: ListParams['list'][]; default: ListParams['list'] }

const listParamsRanking: ValidListParams = {
  list: ['hourly', 'daily', 'weekly', 'all'],
  default: 'all',
}

const listParamsOfficial: ValidListParams = {
  list: ['ranking', 'rising'],
  default: 'rising',
}

const paramsSortRanking: ListParams['sort'][] = ['rank', 'increase', 'rate']
const paramsSortAll: ListParams['sort'][] = ['member', 'created_at']
const paramsOrder: ListParams['order'][] = ['asc', 'desc']

export const getValidListParams = (params: URLSearchParams, location: any): ListParams => {
  const orderParam = (defaultValue: ListParams['order']) =>
    getValidParam<'order'>(params.get('order'), defaultValue, paramsOrder)
  const sortParam = (defaultValue: ListParams['sort'], paramsSort: ListParams['sort'][]) =>
    getValidParam<'sort'>(params.get('sort'), defaultValue, paramsSort)
  const paramsList =
    location.pathname.split('/')[1] === 'ranking' || location.pathname.split('/')[2] === 'ranking'
      ? listParamsRanking
      : listParamsOfficial

  const keyword = params.get('keyword') ?? ''
  const sub_category = params.get('sub_category') ?? ''

  const list = getValidParam<'list'>(params.get('list'), paramsList.default, paramsList.list)
  if (list === 'all') {
    return {
      sub_category,
      keyword,
      list,
      sort: sortParam('member', paramsSortAll),
      order: orderParam('desc'),
    }
  }

  const sort = sortParam('increase', paramsSortRanking)
  if (sort === 'increase') {
    return { sub_category, keyword, list, sort: sort, order: orderParam('desc') }
  }

  return { sub_category, keyword, list, sort: sort, order: orderParam('desc') }
}

export const useInitStoreFromURL = () => {
  const location = useLocation()

  return (store: ReturnType<typeof createStore>) => {
    const params = getValidListParams(new URLSearchParams(window.location.search), location)
    store.set(listParamsState, params)
    store.set(keywordState, params.keyword)
  }
}

export function useSetListParams(): SetListParamsValue {
  const setKeyword = useSetAtom(keywordState)
  const setListParams = useSetAtom(listParamsState)
  const location = useLocation()

  return useCallback(
    (getNewParams: (currentParams: ListParams) => ListParams) => {
      setListParams((currentParams) => {
        const newParams = getValidListParams(
          new URLSearchParams(getNewParams(currentParams)),
          location
        )

        window.history.replaceState(null, '', updateURLSearchParams(newParams))
        setTitle(newParams)
        setKeyword(newParams.keyword)
        scrollToTop()

        return newParams
      })
    },
    [location, setKeyword, setListParams]
  )
}
