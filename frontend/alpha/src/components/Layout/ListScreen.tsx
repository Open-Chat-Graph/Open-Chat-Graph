import { useEffect, useRef, type ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface ListScreenProps {
  /** 固定サブヘッダ（タブ／期間／指標／キーワード等）。スクロール領域の外に置かれる。 */
  header?: ReactNode
  /** スクロールするコンテンツ本体 */
  children: ReactNode
  /** スクロール領域(子)へ付与する追加クラス */
  className?: string
  /**
   * この値が変わったらスクロールコンテナを先頭へ戻す。
   * タブ切替など「内容が丸ごと変わる」タイミングに渡す（未指定なら従来通り）。
   */
  scrollResetKey?: string | number
}

/**
 * 画面骨格の唯一の定義元（リスト系ページ用）。
 *
 * これまで Labs / 検索 / period-growth / watch が、固定サブヘッダ＋スクロール領域を
 * それぞれ `sticky top-0 -mt -mx ...` で手組みしていた。sticky 方式は keep-alive パネルの
 * padding 内にヘッダが浮くため、スクロール時にカード内容がバーの上へ覗き込む不具合があった。
 *
 * ここでは「ヘッダはスクロールの外の flex 兄弟」にして覗きを物理的に不可能にする：
 *   - 外枠は親パネル（KeepAlivePanel, scrollable:false）の absolute 領域いっぱいの flex 縦並び。
 *   - header は shrink-0・スクロール領域の外（z-subheader / bg-background / border-b）。
 *   - children は flex-1 の overflow-y-auto。padding はこの層が持つ（パネルは付けない）。
 *
 * reset(nonce) 時の先頭スクロールは、KeepAlivePanel 側の `key={nonce}` 再マウントで
 * このスクロール領域ごと作り直されて 0 に戻る（MyList と同方式）。
 *
 * タブ切替等で scrollResetKey を渡すと、値が変化するたびスクロールコンテナを先頭へ戻す。
 *
 * 利用ページは App.tsx の KEEP_ALIVE_PAGES で `scrollable: false` にすること
 * （パネルは padding/space-y/overflow を付けず、ListScreen が自前で absolute レイアウトと
 *   内部スクロールを担う）。
 */
export function ListScreen({ header, children, className, scrollResetKey }: ListScreenProps) {
  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (scrollResetKey === undefined) return
    scrollRef.current?.scrollTo({ top: 0 })
  }, [scrollResetKey])

  return (
    <div className="absolute inset-0 flex flex-col">
      {header != null && (
        <div className="shrink-0 z-subheader border-b bg-background">{header}</div>
      )}
      <div ref={scrollRef} className={cn('flex-1 overflow-y-auto overflow-x-hidden p-3 md:p-6', className)}>
        {children}
      </div>
    </div>
  )
}
