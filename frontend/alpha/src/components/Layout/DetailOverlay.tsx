import { useEffect, useRef } from 'react'
import type { ReactNode } from 'react'

/**
 * 詳細ページをベースページの上に被せるオーバーレイ。
 *
 * - 表示中は body スクロールを止め、開くたびに先頭から表示する
 * - 中央カラム幅・サイドバー分のオフセットはベースページと揃える（CSS変数）
 */
export function DetailOverlay({ children }: { children: ReactNode }) {
  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    document.body.style.overflow = 'hidden'
    scrollRef.current?.scrollTo(0, 0)
    return () => {
      document.body.style.overflow = ''
    }
  }, [])

  return (
    <div className="fixed inset-0 z-50 bg-background pt-12">
      <div
        ref={scrollRef}
        className="p-3 md:p-6 md:ml-[var(--main-offset-md)] lg:ml-[var(--main-offset-lg)] max-w-full md:max-w-[var(--content-w)] h-full overflow-y-auto overflow-x-hidden md:border-r"
        style={{ scrollbarGutter: 'stable' }}
      >
        {children}
      </div>
    </div>
  )
}
