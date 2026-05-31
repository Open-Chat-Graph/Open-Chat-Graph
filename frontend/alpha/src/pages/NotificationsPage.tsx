import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Bell, Settings2, Sparkles, Activity, CheckCheck } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAlerts } from '@/hooks/useAlerts'
import {
  KeywordHitCard,
  MovementCard,
  formatComputedAt,
} from '@/components/Notifications'
import { cn } from '@/lib/utils'
import type { KeywordHit, Movement } from '@/types/api'

type Tab = 'rooms' | 'movements'

/**
 * 通知ページ。サーバー算出の通知を2区分で表示する。
 * (a) 新しい部屋 = キーワードの見張りヒット (b) 見張りの動き = 部屋/マイリストの増減。
 * 各アイテムは未読を左帯で強調し、開いた時点でそのアイテムを既読化する。
 */
export default function NotificationsPage() {
  const navigate = useNavigate()
  const { data, isLoading, markRead, markAllRead } = useAlerts()
  const [tab, setTab] = useState<Tab>('rooms')
  const openWatchSettings = () => navigate('/watch')

  const keywordHits = useMemo(() => data?.keywordHits ?? [], [data])
  const movements = useMemo(() => data?.movements ?? [], [data])

  const unreadKeyword = keywordHits.filter((h) => !h.isRead).length
  const unreadMovement = movements.filter((m) => !m.isRead).length

  const handleOpenKeyword = (hit: KeywordHit) => {
    if (!hit.isRead) markRead([hit.id])
  }
  const handleOpenMovement = (m: Movement) => {
    if (!m.isRead) markRead([m.id])
  }

  const computedLabel = data?.computedAt ? formatComputedAt(data.computedAt) : null

  return (
    <div className="space-y-4">
      {/* ヘッダー行: 最終算出時刻 ＋ 操作 */}
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-xs text-muted-foreground">
          {computedLabel ? (
            <>最終更新 {computedLabel}（毎時のデータ更新後に反映）</>
          ) : (
            <>通知は毎時のデータ更新後に反映されます</>
          )}
        </p>
        <div className="ml-auto flex items-center gap-2">
          {(unreadKeyword > 0 || unreadMovement > 0) && (
            <Button
              variant="ghost"
              size="sm"
              className="h-8 gap-1.5 text-xs"
              onClick={() => markAllRead()}
              data-testid="mark-all-read"
            >
              <CheckCheck className="h-4 w-4" />
              すべて既読
            </Button>
          )}
          <Button
            variant="outline"
            size="sm"
            className="h-8 gap-1.5 text-xs"
            onClick={openWatchSettings}
            data-testid="open-watch-settings"
          >
            <Settings2 className="h-4 w-4" />
            見張り設定
          </Button>
        </div>
      </div>

      {/* 区分タブ（セグメント） */}
      <div className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1">
        <TabButton
          active={tab === 'rooms'}
          onClick={() => setTab('rooms')}
          icon={<Sparkles className="h-4 w-4" />}
          label="新しい部屋"
          count={unreadKeyword}
        />
        <TabButton
          active={tab === 'movements'}
          onClick={() => setTab('movements')}
          icon={<Activity className="h-4 w-4" />}
          label="見張りの動き"
          count={unreadMovement}
        />
      </div>

      {isLoading && !data ? (
        <div className="flex justify-center py-12">
          <div className="h-8 w-8 animate-spin rounded-full border-b-2 border-primary" />
        </div>
      ) : tab === 'rooms' ? (
        keywordHits.length > 0 ? (
          <div className="space-y-2.5">
            {keywordHits.map((hit) => (
              <KeywordHitCard key={hit.id} hit={hit} onOpen={handleOpenKeyword} />
            ))}
          </div>
        ) : (
          <EmptyMessage
            title="新しい部屋はまだありません"
            body="「見張り設定」でキーワードを見張ると、条件に合う新しい部屋をここでお知らせします。"
            onSettings={openWatchSettings}
          />
        )
      ) : movements.length > 0 ? (
        <div className="space-y-2.5">
          {movements.map((m) => (
            <MovementCard key={m.id} movement={m} onOpen={handleOpenMovement} />
          ))}
        </div>
      ) : (
        <EmptyMessage
          title="見張りの動きはまだありません"
          body="部屋やマイリスト全体のしきい値を設定すると、大きな増減をここでお知らせします。"
          onSettings={openWatchSettings}
        />
      )}
    </div>
  )
}

function TabButton({
  active,
  onClick,
  icon,
  label,
  count,
}: {
  active: boolean
  onClick: () => void
  icon: React.ReactNode
  label: string
  count: number
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'flex items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors select-none',
        active
          ? 'bg-background text-foreground shadow-sm'
          : 'text-muted-foreground hover:text-foreground',
      )}
    >
      {icon}
      <span>{label}</span>
      {count > 0 && (
        <span className="flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold leading-none text-primary-foreground">
          {count > 99 ? '99+' : count}
        </span>
      )}
    </button>
  )
}

function EmptyMessage({
  title,
  body,
  onSettings,
}: {
  title: string
  body: string
  onSettings: () => void
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
      <Bell className="h-10 w-10 text-muted-foreground/50" />
      <p className="font-medium">{title}</p>
      <p className="max-w-xs text-sm text-muted-foreground">{body}</p>
      <Button variant="outline" size="sm" className="mt-1 gap-1.5" onClick={onSettings}>
        <Settings2 className="h-4 w-4" />
        見張り設定を開く
      </Button>
    </div>
  )
}
