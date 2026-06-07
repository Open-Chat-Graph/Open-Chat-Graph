/**
 * プッシュ通知トグル（設定ページの「通知」カード内）
 *
 * 状態ごとの表示:
 * - unsupported: トグル無効化 + 非対応の説明
 * - denied:      トグル無効化 + ブラウザ設定の案内
 * - loading:     スケルトン表示
 * - server-disabled (enabled:false): トグル無効化 + 「準備中」
 * - subscribed:  ON トグル（クリックで解除）
 * - unsubscribed: OFF トグル（クリックで購読）
 * iOS 向け注記を常に小さく表示する。
 */

import { useCallback, useEffect, useRef, useState } from 'react'
import { Bell, BellOff, Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import {
  getPushStatus,
  subscribe,
  unsubscribe,
  type PushStatus,
} from '@/services/pushSubscription'

/** サーバーから公開鍵設定の enabled フラグを取得 */
async function fetchPushEnabled(): Promise<boolean> {
  try {
    const res = await fetch('/alpha-api/push/config', { credentials: 'include' })
    if (!res.ok) return false
    const data = (await res.json()) as { enabled: boolean }
    return data.enabled === true
  } catch {
    return false
  }
}

type UiState =
  | { kind: 'loading' }
  | { kind: 'unsupported' }
  | { kind: 'denied' }
  | { kind: 'server-disabled' }
  | { kind: 'idle'; status: 'subscribed' | 'unsubscribed' }
  | { kind: 'toggling' }
  | { kind: 'error'; message: string; prevStatus: 'subscribed' | 'unsubscribed' }

export function PushNotificationToggle() {
  const [ui, setUi] = useState<UiState>({ kind: 'loading' })
  const mountedRef = useRef(true)

  const load = useCallback(async () => {
    setUi({ kind: 'loading' })
    const status = await getPushStatus()
    if (!mountedRef.current) return

    if (status === 'unsupported') {
      setUi({ kind: 'unsupported' })
      return
    }
    if (status === 'denied') {
      setUi({ kind: 'denied' })
      return
    }

    // サーバー側の enabled フラグを確認（subscribed の場合はスキップしても良いが一貫性のため確認）
    const enabled = await fetchPushEnabled()
    if (!mountedRef.current) return

    if (!enabled && status === 'unsubscribed') {
      setUi({ kind: 'server-disabled' })
      return
    }

    setUi({ kind: 'idle', status: status as 'subscribed' | 'unsubscribed' })
  }, [])

  useEffect(() => {
    mountedRef.current = true
    void load()
    return () => {
      mountedRef.current = false
    }
  }, [load])

  const handleToggle = useCallback(async () => {
    const currentStatus =
      ui.kind === 'idle' ? ui.status
      : ui.kind === 'error' ? ui.prevStatus
      : null
    if (!currentStatus) return

    setUi({ kind: 'toggling' })
    try {
      if (currentStatus === 'unsubscribed') {
        await subscribe()
        if (mountedRef.current) setUi({ kind: 'idle', status: 'subscribed' })
      } else {
        await unsubscribe()
        if (mountedRef.current) setUi({ kind: 'idle', status: 'unsubscribed' })
      }
    } catch (err) {
      if (!mountedRef.current) return
      const message =
        err instanceof Error ? err.message : '操作に失敗しました'
      setUi({ kind: 'error', message, prevStatus: currentStatus })
    }
  }, [ui])

  const isOn =
    (ui.kind === 'idle' && ui.status === 'subscribed') ||
    (ui.kind === 'error' && ui.prevStatus === 'subscribed')

  const isDisabled =
    ui.kind === 'loading' ||
    ui.kind === 'toggling' ||
    ui.kind === 'unsupported' ||
    ui.kind === 'denied' ||
    ui.kind === 'server-disabled'

  const isLoading = ui.kind === 'loading' || ui.kind === 'toggling'

  return (
    <div className="space-y-2">
      <button
        type="button"
        role="switch"
        aria-checked={isOn}
        disabled={isDisabled}
        onClick={() => void handleToggle()}
        className={cn(
          'flex w-full items-center gap-3 rounded-md border px-3 py-3 text-sm transition-colors',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
          isDisabled
            ? 'cursor-not-allowed opacity-50 bg-muted/30'
            : 'bg-background hover:bg-accent hover:text-accent-foreground cursor-pointer',
        )}
      >
        {isLoading ? (
          <Loader2 className="h-5 w-5 flex-shrink-0 animate-spin text-muted-foreground" />
        ) : isOn ? (
          <Bell className="h-5 w-5 flex-shrink-0 text-primary" />
        ) : (
          <BellOff className="h-5 w-5 flex-shrink-0 text-muted-foreground" />
        )}

        <span className="min-w-0 flex-1 text-left">
          <span className="block font-medium">
            {ui.kind === 'server-disabled' ? 'プッシュ通知（準備中）' : 'プッシュ通知'}
          </span>
          <span className="block text-xs font-normal text-muted-foreground">
            {ui.kind === 'unsupported' && 'このブラウザはプッシュ通知に対応していません'}
            {ui.kind === 'denied' && 'ブラウザ設定で通知をオンにしてください'}
            {ui.kind === 'server-disabled' && '現在設定中です。しばらくお待ちください'}
            {ui.kind === 'loading' && '読み込み中...'}
            {ui.kind === 'toggling' && (isOn ? '購読解除中...' : '購読中...')}
            {ui.kind === 'idle' && (isOn ? '有効 — 新着アラートをプッシュ通知で受け取る' : '無効 — タップしてオンにする')}
            {ui.kind === 'error' && ui.message}
          </span>
        </span>

        {/* トグルスイッチ（見た目のみ） */}
        {!isLoading && (
          <span
            aria-hidden="true"
            className={cn(
              'flex-shrink-0 relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
              isOn && !isDisabled ? 'bg-primary' : 'bg-input',
            )}
          >
            <span
              className={cn(
                'absolute h-4 w-4 rounded-full bg-white shadow transition-transform',
                isOn ? 'translate-x-[18px]' : 'translate-x-[2px]',
              )}
            />
          </span>
        )}
      </button>

      {/* iOS 向け注記 */}
      <p className="text-xs text-muted-foreground px-1">
        iPhone / iPad は共有メニューから「ホーム画面に追加」したアプリ内でのみ通知を受け取れます。
      </p>
    </div>
  )
}
