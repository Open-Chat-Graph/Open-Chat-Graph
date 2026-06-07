import { memo } from 'react'
import { FolderOpen, Search } from 'lucide-react'
import { EmptyState as CommonEmptyState } from '@/components/Common/EmptyState'
import { useViewNavigation } from '@/hooks/useViewNavigation'

/** マイリスト空状態のゴーストプレビュー（ダミーカード輪郭 × 2） */
function GhostCards() {
  return (
    <div className="w-full space-y-2 px-1">
      {[0, 1].map((i) => (
        <div
          key={i}
          className="flex items-center gap-3 rounded-lg border bg-card px-3 py-2.5"
        >
          {/* アバター placeholder */}
          <div className="h-10 w-10 flex-shrink-0 rounded-md bg-muted" />
          <div className="flex-1 min-w-0 space-y-1.5">
            <div className="h-3 w-3/4 rounded bg-muted" />
            <div className="h-2.5 w-1/2 rounded bg-muted" />
          </div>
          {/* スパークライン SVG ダミー */}
          <svg
            width={64}
            height={22}
            viewBox="0 0 64 22"
            className="flex-shrink-0 text-primary"
            aria-hidden="true"
          >
            <polyline
              points={i === 0 ? '2,18 12,14 24,10 36,8 48,5 62,3' : '2,5 12,9 24,14 36,12 48,16 62,19'}
              fill="none"
              stroke="currentColor"
              strokeWidth={1.5}
              strokeLinecap="round"
              strokeLinejoin="round"
              opacity={0.6}
            />
          </svg>
        </div>
      ))}
    </div>
  )
}

export const EmptyState = memo(() => {
  const { goToView } = useViewNavigation()

  return (
    <CommonEmptyState
      icon={<FolderOpen />}
      title="マイリストに部屋を追加しよう"
      description="部屋をフォルダにまとめると、成長を1グラフで比較・増減をまとめてアラートできます。"
      action={{
        label: '部屋を探す',
        onClick: () => goToView('search'),
        icon: <Search />,
      }}
      hint="フォルダにキーワードを設定すると新着部屋が自動で入ります"
    >
      <GhostCards />
    </CommonEmptyState>
  )
})

EmptyState.displayName = 'EmptyState'
