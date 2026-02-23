import { describe, it, expect, vi, beforeEach } from 'vitest'
import { validateStringNotEmpty, fetchApiFormData } from '../utils'

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

describe('fetchApiFormData', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('sends FormData via POST and returns parsed JSON', async () => {
    const mockResponse = { commentId: 1, images: [] }
    vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: true,
      json: async () => mockResponse,
    } as Response)

    const formData = new FormData()
    formData.append('text', 'test')

    const result = await fetchApiFormData<typeof mockResponse>('/api/test', formData)

    expect(result).toEqual(mockResponse)
    expect(fetch).toHaveBeenCalledWith('/api/test', {
      method: 'POST',
      body: formData,
    })
  })

  it('throws on error response', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue({
      ok: false,
      json: async () => ({ error: { code: '400', message: 'Bad Request' } }),
    } as Response)

    const formData = new FormData()
    await expect(fetchApiFormData('/api/test', formData)).rejects.toThrow('Bad Request')
  })
})
