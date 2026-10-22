import { describe, it, expect, vi, beforeEach } from 'vitest'
import { validateStringNotEmpty, fetchApi, fetchApiFormData } from '../utils'

describe('validateStringNotEmpty', () => {
  it('returns false for empty string', () => {
    expect(validateStringNotEmpty('')).toBe(false)
  })

  it('returns false for whitespace only', () => {
    expect(validateStringNotEmpty('   ')).toBe(false)
  })

  it('returns false for zero-width characters only', () => {
    expect(validateStringNotEmpty('\u200B\u200C\u200D\uFEFF')).toBe(false)
  })

  it('returns true for normal text', () => {
    expect(validateStringNotEmpty('hello')).toBe(true)
  })

  it('returns true for Japanese text', () => {
    expect(validateStringNotEmpty('テスト')).toBe(true)
  })

  it('normalizes full-width to half-width and validates', () => {
    // NFKC converts full-width spaces to regular spaces
    expect(validateStringNotEmpty('\u3000')).toBe(false)
  })
})

describe('fetchApi', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    document.cookie = 'CSRF-Token=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
  })

  const mockOk = () =>
    vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({}),
    } as Response)

  it('sends the CSRF-Token cookie as X-CSRF-Token header on POST', async () => {
    document.cookie = 'CSRF-Token=abc%2B123'
    mockOk()

    await fetchApi('/api/test', 'POST', { a: 1 })

    const headers = vi.mocked(fetch).mock.calls[0][1]?.headers as Record<string, string>
    expect(headers['X-CSRF-Token']).toBe('abc+123')
  })

  it('does not send X-CSRF-Token on GET', async () => {
    document.cookie = 'CSRF-Token=abc123'
    mockOk()

    await fetchApi('/api/test', 'GET', null)

    const headers = vi.mocked(fetch).mock.calls[0][1]?.headers as Record<string, string>
    expect(headers['X-CSRF-Token']).toBeUndefined()
  })
})

describe('fetchApiFormData', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('sends FormData via POST and returns parsed JSON', async () => {
    const mockResponse = { commentId: 1, images: [] }
    vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: true,
      status: 200,
      text: async () => JSON.stringify(mockResponse),
    } as Response)

    const formData = new FormData()
    formData.append('text', 'test')

    const result = await fetchApiFormData<typeof mockResponse>('/api/test', formData)

    expect(result).toEqual(mockResponse)
    expect(fetch).toHaveBeenCalledWith('/api/test', {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      body: formData,
    })
  })

  it('throws on error response', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: false,
      status: 400,
      text: async () => JSON.stringify({ error: { code: '400', message: 'Bad Request' } }),
    } as Response)

    const formData = new FormData()
    await expect(fetchApiFormData('/api/test', formData)).rejects.toThrow('Bad Request')
  })
})
