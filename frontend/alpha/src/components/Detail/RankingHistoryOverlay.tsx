import { useEffect } from 'react'
import { createPortal } from 'react-dom'
import useSWR from 'swr'
import { ArrowLeft } from 'lucide-react'
import { alphaApi } from '@/api/alpha'
import type { RankingHistoryResponse } from '@/types/api'
import { RankingHistoryList } from './RankingHistoryList'

interface RankingHistoryOverlayProps {
  openChatId: number
  onClose: () => void
}

/**
 * ランキング掲載履歴の個別画面。詳細ページの上に重ねる全画面オーバーレイ。
 * このコンポーネントがマウントされた時に初めて履歴を fetch する（遅延読み込み）。
 */
export function RankingHistoryOverlay({ openChatId, onClose }: RankingHistoryOverlayProps) {
  const { data, error, isLoading } = useSWR<RankingHistoryResponse>(
    ['ranking-history', openChatId],
    () => alphaApi.getRankingHistory(openChatId),
    { revalidateOnFocus: false, revalidateOnReconnect: false },
  )

  // 表示中は背面のスクロールを止める
  useEffect(() => {
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = ''
    }
  }, [])

  const items = data?.data ?? []

  // ルート(body)へポータル。詳細オーバーレイ(z-50)やサイドバー(z-70)より前面に出すため、
  // ネスト先の stacking context に閉じ込めない。
  return createPortal(
    <div className="fixed inset-0 z-[80] flex flex-col bg-background">
      {/* ヘッダー */}
      <header className="flex h-12 flex-shrink-0 items-center gap-2 border-b bg-card px-2 select-none">
        <button
          type="button"
          onClick={onClose}
          aria-label="戻る"
          className="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        <span className="text-base font-semibold">ランキング掲載履歴</span>
      </header>

      {/* 本文 */}
      <div className="flex-1 overflow-y-auto overflow-x-hidden" style={{ scrollbarGutter: 'stable' }}>
        <div className="mx-auto max-w-[700px] p-3 md:p-6">
          {isLoading ? (
            <div className="flex justify-center py-16">
              <div className="h-8 w-8 animate-spin rounded-full border-b-2 border-primary" />
            </div>
          ) : error ? (
            <p className="py-16 text-center text-sm text-destructive">履歴の取得に失敗しました</p>
          ) : items.length === 0 ? (
            <p className="py-16 text-center text-sm text-muted-foreground">
              ランキング掲載履歴はありません
            </p>
          ) : (
            <RankingHistoryList items={items} />
          )}
        </div>
      </div>
    </div>,
    document.body,
  )
}
