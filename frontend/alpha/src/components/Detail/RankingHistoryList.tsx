import type { RankingHistoryItem } from '@/types/api'

/** 非掲載だった期間を「N時間」「N日」等の読みやすい文字列にする */
function formatDuration(start: string, end: string | null): string | null {
  if (!end) return null
  const ms = new Date(end.replace(' ', 'T')).getTime() - new Date(start.replace(' ', 'T')).getTime()
  if (!isFinite(ms) || ms <= 0) return null
  const hours = Math.round(ms / 3_600_000)
  if (hours < 24) return `${hours}時間`
  const days = Math.round(hours / 24)
  return `${days}日`
}

function formatDateTime(s: string): string {
  // "2026-03-02 22:30:00" → "2026/03/02 22:30"
  const [date, time = ''] = s.split(' ')
  return `${date.replace(/-/g, '/')} ${time.slice(0, 5)}`.trim()
}

function rankLabel(item: RankingHistoryItem): string {
  if (item.position != null && item.totalCount != null) {
    return `${item.position.toLocaleString()}位 / ${item.totalCount.toLocaleString()}位中`
  }
  return `上位${item.percentage}%`
}

export function RankingHistoryList({ items }: { items: RankingHistoryItem[] }) {
  return (
    <ul className="space-y-3">
      {items.map((item, i) => {
        const ongoing = item.endDatetime === null
        const duration = formatDuration(item.datetime, item.endDatetime)
        return (
          <li key={i} className="rounded-lg border bg-card p-4 text-sm">
            <div className="font-semibold">
              {ongoing ? '掲載なしを検出（継続中）' : '掲載なしを検出 → 再掲載'}
            </div>
            <div className="mt-1 text-muted-foreground">
              {formatDateTime(item.datetime)}
              {item.endDatetime && <> → {formatDateTime(item.endDatetime)}</>}
              {duration && <>（{duration}）</>}
            </div>
            <div className="mt-2">
              メンバー数 {item.member.toLocaleString()}人
              （現在 {item.currentMember.toLocaleString()}人
              {item.memberDiff !== 0 && <> {item.memberDiff > 0 ? '+' : ''}{item.memberDiff.toLocaleString()}</>}）
            </div>
            <div className="mt-1">順位（同カテゴリ） {rankLabel(item)}</div>
          </li>
        )
      })}
    </ul>
  )
}
