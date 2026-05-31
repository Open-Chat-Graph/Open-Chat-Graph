import { memo } from 'react'
import { useNavigate } from 'react-router-dom'
import { FolderOpen, Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'

export const EmptyState = memo(() => {
  const navigate = useNavigate()

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">マイリスト</h1>
        <p className="text-muted-foreground">
          お気に入りのオープンチャットを管理
        </p>
      </div>

      <Card>
        <CardContent className="flex flex-col items-center py-12 text-center">
          <FolderOpen className="h-12 w-12 text-muted-foreground/50" />
          <p className="mt-4 text-sm font-medium text-foreground">
            マイリストは空です
          </p>
          {/* 価値訴求: なぜマイリスト（フォルダ）に入れると嬉しいのか */}
          <p className="mt-2 max-w-xs text-xs leading-relaxed text-muted-foreground">
            複数の部屋をフォルダにまとめると、成長を重ねたグラフで比較でき、増減をまとめてアラートできます。
          </p>
          <Button
            className="mt-5 gap-1.5"
            onClick={() => navigate('/')}
            data-testid="empty-open-search"
          >
            <Search className="h-4 w-4" />
            検索を開く
          </Button>
        </CardContent>
      </Card>
    </div>
  )
})

EmptyState.displayName = 'EmptyState'
