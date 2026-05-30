import { memo } from 'react'
import { FolderOpen } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'

export const EmptyState = memo(() => {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">マイリスト</h1>
        <p className="text-muted-foreground">
          お気に入りのオープンチャットを管理
        </p>
      </div>

      <Card>
        <CardContent className="py-12 text-center">
          <FolderOpen className="mx-auto h-12 w-12 text-muted-foreground/50" />
          <p className="mt-4 text-sm text-muted-foreground">
            マイリストは空です
          </p>
          <p className="mt-2 text-xs text-muted-foreground">
            検索からオープンチャットを追加してください
          </p>
        </CardContent>
      </Card>
    </div>
  )
})

EmptyState.displayName = 'EmptyState'
