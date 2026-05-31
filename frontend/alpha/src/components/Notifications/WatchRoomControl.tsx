import { useState, useEffect, useCallback } from 'react'
import { Bell, ChevronDown, Loader2, BellOff } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { ConfirmDialog } from '@/components/ui/confirm-dialog'
import { useConfirmDialog } from '@/hooks/useConfirmDialog'
import { useAlertsConfig } from './useAlertsConfig'
import { ThresholdInput, thresholdUnitLabel, type ThresholdUnit } from './ThresholdInput'

/**
 * 部屋詳細画面の「増減アラート」セクション。1枚のカードで完結する開閉トグル式。
 *
 * - カード上部のヘッダ自体が開閉ボタン（タブ）。クリックで本文を開閉する。
 *   ヘッダには現在の状態（±N単位で通知中／オフ）を出す。
 * - 本文（展開時のみ）にしきい値エディタを置く。
 *   - 未有効: 「このしきい値でアラートON」ボタンのみ（解除ボタンは出さない）。
 *   - 有効中: しきい値は live 保存。下に「アラートを解除」ボタン＋確認ダイアログ。
 */
export function WatchRoomControl({ openChatId }: { openChatId: number }) {
  const { config, addRoom, removeRoom, setRoomThreshold } = useAlertsConfig()
  const confirm = useConfirmDialog()
  const [expanded, setExpanded] = useState(false)
  const [saving, setSaving] = useState(false)
  const [removing, setRemoving] = useState(false)

  const room = config?.rooms.find((r) => r.open_chat_id === openChatId)
  const active = !!room
  // サーバ値から単位/値を判定する。up_member があれば人、なければ up_percent があれば％、
  // どちらも無ければ人（既定）。値は up（= down 対称）由来。
  const serverUnit: ThresholdUnit =
    room && room.up_member == null && room.up_percent != null ? 'percent' : 'member'
  const serverValue = room
    ? serverUnit === 'percent'
      ? room.up_percent
      : room.up_member
    : null

  // 入力値・単位はローカルで編集する。非active の既定は値100・単位member。
  const [unit, setUnit] = useState<ThresholdUnit>('member')
  const [value, setValue] = useState<string>('100')

  // active のときだけサーバ値へ追従（保存反映・別タブ等）。非active は手元の入力を保つ。
  useEffect(() => {
    if (!active) return
    setUnit(serverUnit)
    setValue(serverValue == null ? '' : String(serverValue))
  }, [active, serverUnit, serverValue])

  // 入力値を正の有限数として取り出す（不正なら null）。
  const parsedValue = useCallback((): number | null => {
    const trimmed = value.trim()
    if (trimmed === '') return null
    const n = Number(trimmed)
    if (!Number.isFinite(n) || n <= 0) return null
    return n
  }, [value])

  // 非active: 入力中の値/単位でアラートON。±100人 既定で追加→入力値で上書き保存。
  const onActivate = useCallback(async () => {
    if (active || saving) return
    const n = parsedValue()
    setSaving(true)
    try {
      await addRoom(openChatId)
      if (n != null) await setRoomThreshold(openChatId, n, unit)
    } finally {
      setSaving(false)
    }
  }, [active, saving, parsedValue, unit, addRoom, setRoomThreshold, openChatId])

  // active: 数値の確定（blur / Enter）で live 保存。
  // 空・非数・0以下や、現状と同値（値も単位も）なら呼ばない。
  const commitValue = useCallback(() => {
    if (!active) return
    const n = parsedValue()
    if (n == null) return
    if (n === serverValue && unit === serverUnit) return
    void setRoomThreshold(openChatId, n, unit)
  }, [active, parsedValue, unit, serverValue, serverUnit, setRoomThreshold, openChatId])

  // 単位変更。active なら（数値が有効なら）即 live 保存。非active はローカル反映のみ。
  const onUnitChange = useCallback(
    (next: string) => {
      const u: ThresholdUnit = next === 'percent' ? 'percent' : 'member'
      setUnit(u)
      if (!active) return
      const n = parsedValue()
      if (n == null) return
      if (n === serverValue && u === serverUnit) return
      void setRoomThreshold(openChatId, n, u)
    },
    [active, parsedValue, serverValue, serverUnit, setRoomThreshold, openChatId],
  )

  const onRemove = useCallback(async () => {
    if (!active || removing) return
    const ok = await confirm.confirm({
      title: 'アラートを解除',
      description:
        'この部屋の増減アラートを解除しますか？解除すると通知が届かなくなります。',
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
  }, [active, removing, removeRoom, openChatId, confirm])

  // ヘッダ右の状態テキスト。active なら通知中、非active はオフ。
  const statusText = active ? (
    <span className="text-primary tabular-nums">
      ±{serverValue}
      {thresholdUnitLabel(serverUnit)}で通知中
    </span>
  ) : (
    <span className="text-muted-foreground">オフ</span>
  )

  return (
    <div
      className="max-w-[var(--content-w)] mx-auto rounded-lg border bg-card"
      data-testid="watch-room-control"
    >
      {/* ヘッダ＝開閉トグル（カード幅いっぱいのボタン） */}
      <button
        type="button"
        onClick={() => setExpanded((v) => !v)}
        className="flex w-full items-center gap-2 px-4 py-3 text-left"
        aria-expanded={expanded}
        data-testid="watch-toggle"
      >
        <Bell
          className={`h-4 w-4 shrink-0 ${active ? 'text-primary' : 'text-muted-foreground'}`}
        />
        <span className="text-sm font-semibold">増減アラート</span>
        <span className="ml-1 text-sm">{statusText}</span>
        <ChevronDown
          className={`ml-auto h-4 w-4 shrink-0 text-muted-foreground transition-transform ${
            expanded ? 'rotate-180' : ''
          }`}
        />
      </button>

      {/* 本文（展開時のみ） */}
      {expanded && (
        <div className="border-t p-4">
          <ThresholdInput
            value={value}
            unit={unit}
            onValueChange={setValue}
            onUnitChange={onUnitChange}
            onCommit={commitValue}
          />

          {active ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="mt-3 gap-1.5 text-muted-foreground hover:text-destructive"
              onClick={onRemove}
              disabled={removing}
              data-testid="watch-room-remove"
            >
              {removing ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <BellOff className="h-4 w-4" />
              )}
              アラートを解除
            </Button>
          ) : (
            <Button
              type="button"
              size="default"
              className="mt-3 w-full gap-2"
              onClick={onActivate}
              disabled={saving}
              data-testid="watch-room-activate"
            >
              {saving ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Bell className="h-4 w-4" />
              )}
              このしきい値でアラートON
            </Button>
          )}
        </div>
      )}

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
