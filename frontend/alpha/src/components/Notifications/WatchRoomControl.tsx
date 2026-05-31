import { useState, useEffect, useCallback } from 'react'
import { Eye, Bell, Loader2, BellOff } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ConfirmDialog } from '@/components/ui/confirm-dialog'
import { useConfirmDialog } from '@/hooks/useConfirmDialog'
import { useAlertsConfig } from './useAlertsConfig'

/**
 * 部屋詳細画面の「アラート」セクション。部屋ごとのしきい値をこの画面で完結させる。
 *
 * - 未設定: outline ボタン「この部屋の増減をアラート」。押すと ±10% 既定でアラート開始。
 * - 設定中: 枠付きカード。単一％入力で「±N% を超えたら通知」を設定（up=down 対称）。
 *   下部の ghost ボタンでアラートを解除。
 */
export function WatchRoomControl({ openChatId }: { openChatId: number }) {
  const { config, addRoom, removeRoom, setRoomPercent } = useAlertsConfig()
  const confirm = useConfirmDialog()
  const [adding, setAdding] = useState(false)
  const [removing, setRemoving] = useState(false)

  const room = config?.rooms.find((r) => r.open_chat_id === openChatId)
  const watched = !!room
  // 入力値はサーバ値（up_percent ?? down_percent）由来。ローカルで編集し blur/Enter で保存する。
  const serverPercent = room ? (room.up_percent ?? room.down_percent) : null
  const [percent, setPercent] = useState<string>(serverPercent == null ? '' : String(serverPercent))

  // サーバ値が変わったら（保存反映・別タブ等）入力へ追従させる。
  useEffect(() => {
    setPercent(serverPercent == null ? '' : String(serverPercent))
  }, [serverPercent])

  const onStart = useCallback(async () => {
    if (watched || adding) return
    setAdding(true)
    try {
      await addRoom(openChatId)
    } finally {
      setAdding(false)
    }
  }, [watched, adding, addRoom, openChatId])

  const onRemove = useCallback(async () => {
    if (!watched || removing) return
    const ok = await confirm.confirm({
      title: 'アラートを解除',
      description: 'この部屋の増減アラートを解除しますか？解除すると通知が届かなくなります。',
      confirmText: '解除する',
      cancelText: 'やめる',
      variant: 'destructive',
    })
    if (!ok) return
    setRemoving(true)
    try {
      await removeRoom(openChatId)
    } finally {
      setRemoving(false)
    }
  }, [watched, removing, removeRoom, openChatId, confirm])

  // 確定（blur / Enter）で保存。空・非数・同値なら何もしない。
  const commitPercent = useCallback(() => {
    const trimmed = percent.trim()
    if (trimmed === '' || Number(trimmed) === serverPercent) return
    void setRoomPercent(openChatId, trimmed)
  }, [percent, serverPercent, setRoomPercent, openChatId])

  if (!watched) {
    return (
      <div className="max-w-[var(--content-w)] mx-auto">
        <Button
          variant="outline"
          size="default"
          className="w-full gap-2"
          onClick={onStart}
          disabled={adding}
          data-testid="watch-room-start"
        >
          {adding ? <Loader2 className="h-4 w-4 animate-spin" /> : <Eye className="h-4 w-4" />}
          この部屋の増減をアラート
        </Button>
      </div>
    )
  }

  return (
    <div className="max-w-[var(--content-w)] mx-auto rounded-lg border bg-card p-4" data-testid="watch-room-control">
      <div className="flex items-center gap-2 text-primary">
        <Bell className="h-4 w-4" />
        <span className="text-sm font-semibold">アラートON</span>
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
        <span>増減が ±</span>
        <Input
          type="number"
          min={1}
          inputMode="numeric"
          value={percent}
          onChange={(e) => setPercent(e.target.value)}
          onBlur={commitPercent}
          onKeyDown={(e) => {
            if (e.key === 'Enter') e.currentTarget.blur()
          }}
          className="h-10 w-20"
          aria-label="通知する増減の割合（％）"
          data-testid="watch-room-percent"
        />
        <span>% を超えたら通知</span>
      </div>

      <Button
        type="button"
        variant="ghost"
        size="sm"
        className="mt-3 gap-1.5 text-muted-foreground hover:text-destructive"
        onClick={onRemove}
        disabled={removing}
        data-testid="watch-room-remove"
      >
        {removing ? <Loader2 className="h-4 w-4 animate-spin" /> : <BellOff className="h-4 w-4" />}
        アラートを解除
      </Button>

      <ConfirmDialog
        open={confirm.isOpen}
        onOpenChange={confirm.handleOpenChange}
        title={confirm.options?.title ?? ''}
        description={confirm.options?.description ?? ''}
        confirmText={confirm.options?.confirmText}
        cancelText={confirm.options?.cancelText}
        variant={confirm.options?.variant}
        onConfirm={confirm.handleConfirm}
        onCancel={confirm.handleCancel}
      />
    </div>
  )
}
