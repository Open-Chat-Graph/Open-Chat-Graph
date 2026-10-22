import { ReactNode } from 'react'

const URL_REGEX = /(https?:\/\/[^\s]+)/g

// 自サイトの管理ページ（/admin…・/tw/admin…）はリンクにしない。管理者がコメント内のリンクを踏むと
// 同一サイトの遷移になり、SameSite=Strict の管理クッキー付きで管理操作の GET が実行されてしまうため
function isOwnAdminUrl(url: string): boolean {
  try {
    const u = new URL(url)
    return u.hostname === window.location.hostname && /^\/(?:(?:tw|th)\/)?admin/i.test(u.pathname)
  } catch {
    return false
  }
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
