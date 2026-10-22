/**
 * @vitest-environment-options {"url": "https://openchat-review.me/oc/1"}
 */
import { describe, it, expect } from 'vitest'
import { isValidElement } from 'react'
import { linkify } from '../linkify'

const links = (text: string) =>
  linkify(text).filter(isValidElement).map((el) => (el.props as { href: string }).href)

describe('linkify', () => {
  it('links external URLs', () => {
    expect(links('see https://example.com/a and http://line.me/x')).toEqual([
      'https://example.com/a',
      'http://line.me/x',
    ])
  })

  it('links own-site non-admin pages', () => {
    const own = `${window.location.origin}/oc/123`
    expect(links(`room ${own}`)).toEqual([own])
  })

  it('does not link own-site admin pages', () => {
    const o = window.location.origin
    expect(
      links(`${o}/admin-api/deletecomment?id=1 ${o}/admin/cookie ${o}/tw/admin-api/x ${o}/th/ADMIN/y`)
    ).toEqual([])
  })

  it('does not link admin pages on same-site hosts or with path variants', () => {
    const { protocol, host } = window.location
    expect(
      links(
        `${protocol}//stg.${host}/admin/deleteoc ${protocol}//${host}//admin/x ${protocol}//${host}/%61dmin/x ${protocol}//${host}/tw//admin`
      )
    ).toEqual([])
  })

  it('keeps the admin URL as plain text', () => {
    const url = `${window.location.origin}/admin-api/deleteuser?id=1`
    expect(linkify(`x ${url}`).join('')).toBe(`x ${url}`)
  })
})
