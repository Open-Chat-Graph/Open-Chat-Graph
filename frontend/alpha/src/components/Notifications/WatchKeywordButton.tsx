import { useState, useEffect, useCallback } from 'react'
import { Eye, Check, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAlertsConfig } from './useAlertsConfig'

/**
 * 「このキーワードを見張る」ボタン（ラベル付き）。
 * 検索結果ヘッダに置く。現在のキーワード（＋カテゴリ）を見張り条件に追加し、
 * 一致する新しい部屋が追加されたら通知タブに出るようにする。
 * idle → saving(スピナー) → done(チェック「見張り中」)。対象が変われば idle に戻る。
 */
export function WatchKeywordButton({ keyword, category }: { keyword: string; category: number }) {
  const { addKeyword } = useAlertsConfig()
  const [state, setState] = useState<'idle' | 'saving' | 'done'>('idle')

  useEffect(() => setState('idle'), [keyword, category])

  const onClick = useCallback(async () => {
    if (!keyword || state !== 'idle') return
    setState('saving')
    try {
      await addKeyword({ keyword, category: category || null })
      setState('done')
    } catch {
      setState('idle')
    }
  }, [keyword, category, state, addKeyword])

  if (!keyword) return null

  return (
    <Button
      variant={state === 'done' ? 'secondary' : 'outline'}
      size="sm"
      className="flex-shrink-0 gap-1.5"
      onClick={onClick}
      disabled={state !== 'idle'}
      data-testid="watch-current-keyword"
    >
      {state === 'saving' ? (
        <Loader2 className="h-4 w-4 animate-spin" />
      ) : state === 'done' ? (
        <Check className="h-4 w-4" />
      ) : (
        <Eye className="h-4 w-4" />
      )}
      {state === 'done' ? '見張り中' : 'このキーワードを見張る'}
    </Button>
  )
}
