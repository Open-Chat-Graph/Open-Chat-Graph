import { describe, it, expect } from 'vitest'
import { getValidListParams } from '../hooks/ListParamsHooks'

const makeLocation = (pathname: string) => ({ pathname })

describe('getValidListParams', () => {
  describe('ranking path (daily/hourly/weekly/all)', () => {
    const location = makeLocation('/ranking')

    it('returns defaults for empty params', () => {
      const params = getValidListParams(new URLSearchParams(), location)
      expect(params.list).toBe('all')
      expect(params.sort).toBe('member')
      expect(params.order).toBe('desc')
      expect(params.keyword).toBe('')
      expect(params.sub_category).toBe('')
    })

    it('parses daily list with ranking sort options', () => {
      const params = getValidListParams(
        new URLSearchParams({ list: 'daily', sort: 'increase', order: 'asc' }),
        location
      )
      expect(params.list).toBe('daily')
      expect(params.sort).toBe('increase')
      expect(params.order).toBe('asc')
    })

    it('parses weekly list', () => {
      const params = getValidListParams(new URLSearchParams({ list: 'weekly' }), location)
      expect(params.list).toBe('weekly')
    })

    it('parses hourly list', () => {
      const params = getValidListParams(new URLSearchParams({ list: 'hourly' }), location)
      expect(params.list).toBe('hourly')
    })

    it('parses all list with member sort', () => {
      const params = getValidListParams(
        new URLSearchParams({ list: 'all', sort: 'member', order: 'asc' }),
        location
      )
      expect(params.list).toBe('all')
      expect(params.sort).toBe('member')
      expect(params.order).toBe('asc')
    })

    it('falls back to defaults for invalid list', () => {
      const params = getValidListParams(new URLSearchParams({ list: 'invalid' }), location)
      expect(params.list).toBe('all')
    })

    it('falls back to defaults for invalid sort', () => {
      const params = getValidListParams(
        new URLSearchParams({ list: 'daily', sort: 'invalid' }),
        location
      )
      expect(params.sort).toBe('increase')
    })

    it('falls back to defaults for invalid order', () => {
      const params = getValidListParams(
        new URLSearchParams({ list: 'daily', order: 'invalid' }),
        location
      )
      expect(params.order).toBe('desc')
    })

    it('preserves keyword and sub_category', () => {
      const params = getValidListParams(
        new URLSearchParams({ keyword: 'テスト', sub_category: 'ファッション' }),
        location
      )
      expect(params.keyword).toBe('テスト')
      expect(params.sub_category).toBe('ファッション')
    })
  })

  describe('official path (ranking/rising)', () => {
    const location = makeLocation('/official-ranking')

    it('defaults to rising for official path', () => {
      const params = getValidListParams(new URLSearchParams(), location)
      expect(params.list).toBe('rising')
    })

    it('parses ranking list type', () => {
      const params = getValidListParams(new URLSearchParams({ list: 'ranking' }), location)
      expect(params.list).toBe('ranking')
    })

    it('falls back to rising for invalid list on official path', () => {
      const params = getValidListParams(new URLSearchParams({ list: 'daily' }), location)
      expect(params.list).toBe('rising')
    })
  })

  describe('localized ranking path', () => {
    it('detects ranking in second path segment (e.g. /tw/ranking)', () => {
      const location = makeLocation('/tw/ranking')
      const params = getValidListParams(new URLSearchParams({ list: 'daily' }), location)
      expect(params.list).toBe('daily')
    })

    it('detects ranking in first path segment', () => {
      const location = makeLocation('/ranking/20')
      const params = getValidListParams(new URLSearchParams({ list: 'hourly' }), location)
      expect(params.list).toBe('hourly')
    })
  })
})
