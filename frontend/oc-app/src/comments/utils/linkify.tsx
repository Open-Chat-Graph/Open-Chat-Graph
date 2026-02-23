import { ReactNode } from 'react'

const URL_REGEX = /(https?:\/\/[^\s]+)/g

export function linkify(text: string): ReactNode[] {
  const parts = text.split(URL_REGEX)
  return parts.map((part, i) =>
    URL_REGEX.test(part) ? (
      <a key={i} href={part} target="_blank" rel="noopener noreferrer" style={{ color: '#111' }}>
        {part}
      </a>
    ) : (
      part
    )
  )
}
