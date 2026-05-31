import { useState, useEffect, useCallback } from 'react'
import { Eye, Bell, Loader2, BellOff } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { ConfirmDialog } from '@/components/ui/confirm-dialog'
import { useConfirmDialog } from '@/hooks/useConfirmDialog'
import { useAlertsConfig } from './useAlertsConfig'

type ThresholdUnit = 'member' | 'percent'

/**
 * 部屋詳細画面の「アラート」セクション。部屋ごとのしきい値をこの画面で完結させる。
 *
 * - 未設定: outline ボタン「この部屋の増減をアラート」。押すと ±100人 既定でアラート開始。
 * - 設定中: 枠付きカード。数値＋単位プルダウン（人／％）で「±N 単位 を超えたら通知」を
 *   設定（up=down 対称）。下部の ghost ボタンでアラートを解除。
 */
export function WatchRoomControl({ openChatId }: { openChatId: number }) {
  const { config, addRoom, removeRoom, setRoomThreshold } = useAlertsConfig()
  const confirm = useConfirmDialog()
  const [adding, setAdding] = useState(false)
  const [removing, setRemoving] = useState(false)

  const room = config?.rooms.find((r) => r.open_chat_id === openChatId)
  const watched = !!room
  // サーバ値から単位/値を判定する。up_member があれば人、なければ up_percent があれば％、
  // どちらも無ければ人（既定）。値は up（= down 対称）由来。
  const serverUnit: ThresholdUnit = room && room.up_member == null && room.up_percent != null
    ? 'percent'
    : 'member'
  const serverValue = room
    ? (serverUnit === 'percent' ? room.up_percent : room.up_member)
    : null

  // 入力値・単位はサーバ値由来。ローカルで編集し blur/Enter/単位変更で保存する。
  const [unit, setUnit] = useState<ThresholdUnit>(serverUnit)
  const [value, setValue] = useState<string>(serverValue == null ? '' : String(serverValue))

  // サーバ値が変わったら（保存反映・別タブ等）入力へ追従させる。
  useEffect(() => {
    setUnit(serverUnit)
    setValue(serverValue == null ? '' : String(serverValue))
  }, [serverUnit, serverValue])

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

  // 数値の確定（blur / Enter）で保存。空・非数・正でない・現状と同値（値も単位も）なら何もしない。
  const commitValue = useCallback(() => {
    const trimmed = value.trim()
    if (trimmed === '') return
    const n = Number(trimmed)
    if (!Number.isFinite(n) || n <= 0) return
    if (n === serverValue && unit === serverUnit) return
    void setRoomThreshold(openChatId, n, unit)
  }, [value, unit, serverValue, serverUnit, setRoomThreshold, openChatId])

  // 単位変更で即保存。数値が空・不正なら（保存はせず）単位だけローカルに反映する。
  const onUnitChange = useCallback(
    (next: string) => {
      const u = next === 'percent' ? 'percent' : 'member'
      setUnit(u)
      const n = Number(value.trim())
      if (value.trim() === '' || !Number.isFinite(n) || n <= 0) return
      void setRoomThreshold(openChatId, n, u)
    },
    [value, setRoomThreshold, openChatId],
  )

  // 未設定: ワンタップで ±100人（既定）からアラート開始。単位/値はONカードで調整。
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
          value={value}
          onChange={(e) => setValue(e.target.value)}
          onBlur={commitValue}
          onKeyDown={(e) => {
            if (e.key === 'Enter') e.currentTarget.blur()
          }}
          className="h-10 w-20 text-base"
          aria-label="通知する増減のしきい値"
          data-testid="watch-room-value"
        />
        <Select value={unit} onValueChange={onUnitChange}>
          <SelectTrigger
            className="h-10 w-20"
            aria-label="しきい値の単位"
            data-testid="watch-room-unit"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="member">人</SelectItem>
            <SelectItem value="percent">％</SelectItem>
          </SelectContent>
        </Select>
        <span>を超えたら通知</span>
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
