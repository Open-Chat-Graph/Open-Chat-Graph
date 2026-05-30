import { memo } from 'react'
import { Card, CardContent } from '@/components/ui/card'

export const ErrorState = memo(() => {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">マイリスト</h1>
        <p className="text-muted-foreground">
          お気に入りのオープンチャットを管理
        </p>
      </div>
      <Card className="border-destructive">
        <CardContent className="pt-6">
          <p className="text-sm text-destructive">データの取得に失敗しました</p>
        </CardContent>
      </Card>
    </div>
  )
})

ErrorState.displayName = 'ErrorState'
