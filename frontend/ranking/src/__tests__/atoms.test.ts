import { describe, it, expect } from 'vitest'
import { createStore } from 'jotai'
import { listParamsState, keywordState, subCategoryChipsStackScrollLeft } from '../store/atom'

describe('Jotai atoms', () => {
  it('listParamsState has correct default values', () => {
    const store = createStore()
    const params = store.get(listParamsState)
    expect(params).toEqual({
      sub_category: '',
      keyword: '',
      order: 'asc',
      sort: 'rank',
      list: 'daily',
    })
  })

  it('keywordState defaults to empty string', () => {
    const store = createStore()
    expect(store.get(keywordState)).toBe('')
  })

  it('subCategoryChipsStackScrollLeft defaults to 0', () => {
    const store = createStore()
    expect(store.get(subCategoryChipsStackScrollLeft)).toBe(0)
  })

  it('can set and read listParamsState', () => {
    const store = createStore()
    const newParams: ListParams = {
      sub_category: 'テスト',
      keyword: 'search',
      order: 'desc',
      sort: 'member',
      list: 'all',
    }
    store.set(listParamsState, newParams)
    expect(store.get(listParamsState)).toEqual(newParams)
  })

  it('can set and read keywordState', () => {
    const store = createStore()
    store.set(keywordState, 'テスト検索')
    expect(store.get(keywordState)).toBe('テスト検索')
  })

  it('stores are independent', () => {
    const store1 = createStore()
    const store2 = createStore()
    store1.set(keywordState, 'store1')
    store2.set(keywordState, 'store2')
    expect(store1.get(keywordState)).toBe('store1')
    expect(store2.get(keywordState)).toBe('store2')
  })
})
