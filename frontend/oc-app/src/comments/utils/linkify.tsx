import { ReactNode } from 'react'

const URL_REGEX = /(https?:\/\/[^\s]+)/g

// 自サイトの管理ページ（/admin…・/tw/admin…）はリンクにしない。管理者がコメント内のリンクを踏むと
// 同一サイトの遷移になり、SameSite=Strict の管理クッキー付きで管理操作の GET が実行されてしまうため。
// SameSite の「同じサイト」はサブドメインを含むので、ホスト名は末尾 2 ラベルで比べる
const siteOf = (hostname: string) => hostname.split('.').slice(-2).join('.')

function isOwnAdminUrl(url: string): boolean {
  let u: URL
  try {
    u = new URL(url)
  } catch {
    return false
  }
  if (siteOf(u.hostname) !== siteOf(window.location.hostname)) return false

  let path: string
  try {
    path = decodeURIComponent(u.pathname)
  } catch {
    return true
  }
  return /^\/+(?:(?:tw|th)\/+)?admin/i.test(path)
}

export function linkify(text: string): ReactNode[] {
  const parts = text.split(URL_REGEX)
  return parts.map((part, i) =>
    /^https?:\/\//.test(part) && !isOwnAdminUrl(part) ? (
      <a key={i} href={part} target="_blank" rel="noopener noreferrer" style={{ color: 'var(--c-text-1)' }}>
        {part}
      </a>
    ) : (
      part
    )
  )
}
