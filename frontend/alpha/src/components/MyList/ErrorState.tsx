import { memo } from 'react'
import { RotateCw } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'

export const ErrorState = memo(({ onRetry }: { onRetry?: () => void }) => {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">マイリスト</h1>
        <p className="text-muted-foreground">
          お気に入りのオープンチャットを管理
        </p>
      </div>
      <Card className="border-destructive">
        <CardContent className="space-y-3 pt-6">
          <p className="text-sm text-destructive">データの取得に失敗しました</p>
          {onRetry && (
            <Button variant="outline" size="sm" onClick={onRetry} data-testid="mylist-retry">
              <RotateCw className="mr-2 h-4 w-4" />
              再読み込み
            </Button>
          )}
        </CardContent>
      </Card>
    </div>
  )
})

ErrorState.displayName = 'ErrorState'
