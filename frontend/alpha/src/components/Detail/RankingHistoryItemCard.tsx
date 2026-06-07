import type { RankingHistoryItem } from '@/types/api'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { ArrowRight, Clock3, EyeOff, Users, Trophy, PencilLine } from 'lucide-react'

/** 非掲載だった期間を「N時間」「N日」等の読みやすい文字列にする */
function formatDuration(start: string, end: string | null): string | null {
  const base = end ?? toLocalNowString()
  const ms = new Date(base.replace(' ', 'T')).getTime() - new Date(start.replace(' ', 'T')).getTime()
  if (!isFinite(ms) || ms <= 0) return null
  const hours = Math.round(ms / 3_600_000)
  if (hours < 24) return `${hours}時間`
  const days = Math.round(hours / 24)
  return `${days}日`
}

/** 継続中の経過時間を出すための「現在時刻」を datetime と同じ書式で返す */
function toLocalNowString(): string {
  const d = new Date()
  const p = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:00`
}

/** "2026-03-02 22:30:00" → "2026/03/02 22:30"（秒は出さない） */
function formatDateTime(s: string): string {
  const [date, time = ''] = s.split(' ')
  return `${date.replace(/-/g, '/')} ${time.slice(0, 5)}`.trim()
}

/** 変更項目キーを日本語ラベルに */
const UPDATE_ITEM_LABELS: Record<string, string> = {
  name: 'ルーム名',
  description: '説明文',
  image: '画像',
  img_url: '画像',
}

function updateItemLabels(items: string[]): string[] {
  const seen = new Set<string>()
  const out: string[] = []
  for (const key of items) {
    const label = UPDATE_ITEM_LABELS[key] ?? key
    if (!seen.has(label)) {
      seen.add(label)
      out.push(label)
    }
  }
  return out
}

export function RankingHistoryItemCard({ item }: { item: RankingHistoryItem }) {
  const ongoing = item.endDatetime === null
  const duration = formatDuration(item.datetime, item.endDatetime)

  const hasRank = item.position != null && item.totalCount != null
  // 順位が母数のどの辺りか（0=トップ, 1=最下位）。バー表示用。
  const rankRatio = hasRank
    ? Math.min(1, Math.max(0, (item.position! - 1) / Math.max(1, item.totalCount! - 1)))
    : Math.min(1, Math.max(0, item.percentage / 100))

  const diff = item.memberDiff
  const diffPositive = diff > 0
  const diffNegative = diff < 0
  const changeLabels = item.hasContentChange ? updateItemLabels(item.updateItems) : []

  return (
    <li className="overflow-hidden rounded-xl border bg-card shadow-sm">
      {/* ステータス帯：状態が一目で分かる色付きヘッダー */}
      <div
        className={cn(
          'flex items-center justify-between gap-2 border-b px-4 py-2.5',
          ongoing
            ? 'border-amber-500/20 bg-amber-500/10'
            : 'border-emerald-500/20 bg-emerald-500/[0.07]',
        )}
      >
        <div className="flex min-w-0 items-center gap-2">
          <span
            className={cn(
              'flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full',
              ongoing
                ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
            )}
          >
            <EyeOff className="h-3.5 w-3.5" />
          </span>
          <span className="truncate text-sm font-bold">
            {ongoing ? 'ランキング掲載なし' : '一時的に掲載なし → 復帰'}
          </span>
        </div>
        <Badge
          className={cn(
            'flex-shrink-0 border-transparent',
            ongoing
              ? 'bg-amber-500/20 text-amber-700 dark:bg-amber-500/25 dark:text-amber-300'
              : 'bg-emerald-500/20 text-emerald-700 dark:bg-emerald-500/25 dark:text-emerald-300',
          )}
        >
          {ongoing ? '継続中' : '復帰済み'}
        </Badge>
      </div>

      <div className="space-y-3.5 p-4">
        {/* ヒーロー：非掲載だった時間 */}
        <div className="flex items-baseline gap-2">
          <Clock3 className="h-4 w-4 flex-shrink-0 translate-y-0.5 text-muted-foreground" />
          <div className="flex items-baseline gap-1.5">
            <span className="text-xs text-muted-foreground">
              {ongoing ? '掲載なしが続いて' : '掲載されていなかった期間'}
            </span>
            {duration ? (
              <span className="text-2xl font-extrabold leading-none tracking-tight tabular-nums">
                {duration}
                {ongoing && <span className="ml-0.5 text-xs font-bold text-muted-foreground">経過</span>}
              </span>
            ) : (
              <span className="text-base font-bold">{ongoing ? '継続中' : '—'}</span>
            )}
          </div>
        </div>

        {/* 日時レンジ */}
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
          <time className="tabular-nums">{formatDateTime(item.datetime)}</time>
          <ArrowRight className="h-3 w-3 flex-shrink-0" />
          {ongoing ? (
            <span className="font-medium text-amber-600 dark:text-amber-400">現在も掲載なし</span>
          ) : (
            <time className="tabular-nums">{formatDateTime(item.endDatetime!)}</time>
          )}
        </div>

        {/* 非掲載時点の状況 */}
        <div className="grid grid-cols-1 gap-2.5 rounded-lg bg-muted/40 p-3 sm:grid-cols-2">
          {/* メンバー数 */}
          <div className="space-y-1">
            <div className="flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
              <Users className="h-3.5 w-3.5" />
              掲載なし検出時のメンバー
            </div>
            <div className="flex flex-wrap items-baseline gap-x-1.5">
              <span className="text-lg font-bold tabular-nums">
                {item.member.toLocaleString()}
                <span className="ml-0.5 text-xs font-normal text-muted-foreground">人</span>
              </span>
            </div>
            <div className="text-[11px] text-muted-foreground">
              現在 <span className="font-medium tabular-nums text-foreground">{item.currentMember.toLocaleString()}人</span>
              {diff !== 0 && (
                <span
                  className={cn(
                    'ml-1 font-semibold tabular-nums',
                    diffPositive && 'text-up',
                    diffNegative && 'text-down',
                  )}
                >
                  {diffPositive ? '+' : ''}
                  {diff.toLocaleString()}
                </span>
              )}
            </div>
          </div>

          {/* ランキング順位 */}
          <div className="space-y-1 sm:border-l sm:border-border sm:pl-3">
            <div className="flex items-center gap-1.5 text-[11px] font-medium text-muted-foreground">
              <Trophy className="h-3.5 w-3.5" />
              当時の順位（同カテゴリ）
            </div>
            {hasRank ? (
              <div className="flex flex-wrap items-baseline gap-x-1">
                <span className="text-lg font-bold tabular-nums text-primary">{item.position!.toLocaleString()}</span>
                <span className="text-xs font-medium text-muted-foreground">位</span>
                <span className="text-[11px] text-muted-foreground">
                  / {item.totalCount!.toLocaleString()}件中
                </span>
              </div>
            ) : (
              <div className="flex items-baseline gap-1">
                <span className="text-lg font-bold tabular-nums text-primary">上位{item.percentage}</span>
                <span className="text-xs font-medium text-muted-foreground">%</span>
              </div>
            )}
            {/* 母数のどの辺りかを示すバー（左=上位） */}
            <div
              className="relative mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-muted"
              role="presentation"
            >
              <div
                className="absolute top-1/2 h-3 w-1 -translate-y-1/2 rounded-full bg-primary shadow"
                style={{ left: `calc(${rankRatio * 100}% - 2px)` }}
              />
            </div>
            <div className="flex justify-between text-[10px] text-muted-foreground/70">
              <span>上位</span>
              <span>下位</span>
            </div>
          </div>
        </div>

        {/* 期間中のルーム内容変更（あった時だけ控えめに） */}
        {changeLabels.length > 0 && (
          <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
            <PencilLine className="h-3.5 w-3.5 flex-shrink-0" />
            <span>この間にルーム内容を変更:</span>
            {changeLabels.map((label) => (
              <Badge key={label} variant="outline" className="border-border px-1.5 py-0 text-[10px] font-normal">
                {label}
              </Badge>
            ))}
          </div>
        )}
      </div>
    </li>
  )
}
