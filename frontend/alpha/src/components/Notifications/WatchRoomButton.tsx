import { useState, useCallback } from 'react'
import { Eye, Check, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAlertsConfig } from './useAlertsConfig'

/**
 * 「この部屋を見張る」ボタン。部屋詳細画面のアクション行に置く。
 * 当該 openChatId を見張り対象（±10%で通知の既定しきい値）に追加する。
 * 既に見張り中なら「見張り中」表示。細かいしきい値は通知タブの見張り設定で調整。
 * idle → saving(スピナー) → done(チェック)。設定済みの部屋は最初から done。
 */
export function WatchRoomButton({ openChatId }: { openChatId: number }) {
  const { config, addRoom } = useAlertsConfig()
  const [pending, setPending] = useState(false)

  const alreadyWatched = !!config?.rooms.some((r) => r.open_chat_id === openChatId)

  const onClick = useCallback(async () => {
    if (alreadyWatched || pending) return
    setPending(true)
    try {
      await addRoom(openChatId)
    } finally {
      setPending(false)
    }
  }, [alreadyWatched, pending, addRoom, openChatId])

  const done = alreadyWatched

  return (
    <Button
      variant={done ? 'secondary' : 'outline'}
      size="default"
      className="gap-2"
      onClick={onClick}
      disabled={done || pending}
      data-testid="watch-room-button"
    >
      {pending ? (
        <Loader2 className="h-4 w-4 animate-spin" />
      ) : done ? (
        <Check className="h-4 w-4" />
      ) : (
        <Eye className="h-4 w-4" />
      )}
      {done ? '見張り中' : 'この部屋を見張る'}
    </Button>
  )
}
