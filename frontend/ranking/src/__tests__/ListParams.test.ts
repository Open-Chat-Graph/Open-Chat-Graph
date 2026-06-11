import { describe, it, expect } from 'vitest'
import { getValidListParams } from '../hooks/ListParamsHooks'

describe('getValidListParams', () => {
  it('returns defaults for empty params', () => {
    const params = getValidListParams(new URLSearchParams())
    expect(params.list).toBe('all')
    expect(params.sort).toBe('member')
    expect(params.order).toBe('desc')
    expect(params.keyword).toBe('')
    expect(params.sub_category).toBe('')
  })

  it('parses daily list with ranking sort options', () => {
    const params = getValidListParams(
      new URLSearchParams({ list: 'daily', sort: 'increase', order: 'asc' })
    )
    expect(params.list).toBe('daily')
    expect(params.sort).toBe('increase')
    expect(params.order).toBe('asc')
  })

  it('parses weekly list', () => {
    const params = getValidListParams(new URLSearchParams({ list: 'weekly' }))
    expect(params.list).toBe('weekly')
  })

  it('parses hourly list', () => {
    const params = getValidListParams(new URLSearchParams({ list: 'hourly' }))
    expect(params.list).toBe('hourly')
  })

  it('parses all list with member sort', () => {
    const params = getValidListParams(
      new URLSearchParams({ list: 'all', sort: 'member', order: 'asc' })
    )
    expect(params.list).toBe('all')
    expect(params.sort).toBe('member')
    expect(params.order).toBe('asc')
  })

  it('falls back to defaults for invalid list', () => {
    const params = getValidListParams(new URLSearchParams({ list: 'invalid' }))
    expect(params.list).toBe('all')
  })

  it('falls back to defaults for invalid sort', () => {
    const params = getValidListParams(new URLSearchParams({ list: 'daily', sort: 'invalid' }))
    expect(params.sort).toBe('increase')
  })

  it('falls back to defaults for invalid order', () => {
    const params = getValidListParams(new URLSearchParams({ list: 'daily', order: 'invalid' }))
    expect(params.order).toBe('desc')
  })

  it('preserves keyword and sub_category', () => {
    const params = getValidListParams(
      new URLSearchParams({ keyword: 'テスト', sub_category: 'ファッション' })
    )
    expect(params.keyword).toBe('テスト')
    expect(params.sub_category).toBe('ファッション')
  })
})
