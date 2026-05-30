import { memo } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Clock, TrendingDown, TrendingUp, Users, BarChart3, FileText } from 'lucide-react'
import type { RankingHistoryItem } from '@/types/api'

interface RankingHistoryProps {
  data: RankingHistoryItem[]
}

export const RankingHistory = memo(({ data }: RankingHistoryProps) => {
  if (data.length === 0) {
    return null
  }

  const formatDateTime = (datetime: string) => {
    const date = new Date(datetime)
    return date.toLocaleString('ja-JP', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    })
  }

  const formatMember = (num: number) => {
    return num.toLocaleString('ja-JP')
  }

  const calculateTimeDifference = (start: string, end: string) => {
    const startDate = new Date(start)
    const endDate = new Date(end)
    const diff = Math.abs(endDate.getTime() - startDate.getTime())
    const hours = Math.floor(diff / (1000 * 60 * 60))

    // 72時間以下は時間表記
    if (hours <= 72) {
      if (hours === 0) {
        const minutes = Math.floor(diff / (1000 * 60))
        return `${minutes}分`
      }
      return `${hours}時間`
    }

    // 72時間超えたら日数表記（1日=24時間、時間部分は表示しない）
    const days = Math.floor(hours / 24)
    return `${days}日`
  }

  const calculateDaysAgo = (datetime: string) => {
    const date = new Date(datetime)
    const now = new Date()
    const diff = now.getTime() - date.getTime()
    const hours = Math.floor(diff / (1000 * 60 * 60))

    // 72時間以下は時間表記
    if (hours <= 72) {
      if (hours === 0) {
        const minutes = Math.floor(diff / (1000 * 60))
        return minutes === 0 ? 'たった今' : `${minutes}分`
      }
      return `${hours}時間`
    }

    // 72時間超えたら日数表記（1日=24時間）
    const days = Math.floor(hours / 24)
    return `${days}日`
  }

  const calculatePositionPercentage = (percentage: number) => {
    return `上位${percentage}%`
  }

  const translateUpdateItem = (item: string) => {
    const translations: { [key: string]: string } = {
      'name': 'ルーム名',
      'description': '説明文',
      'img_url': '画像',
      'join_method_type': '公開設定',
      'category': 'カテゴリー',
      'emblem': 'バッジ'
    }
    return translations[item] || item
  }

  const signedNum = (num: number) => {
    if (num > 0) return `+${num}`
    if (num < 0) return `${num}`
    return '±0'
  }

  return (
    <Card>
      <CardHeader className="pb-3">
        <CardTitle className="text-base flex items-center gap-2">
          <Clock className="h-4 w-4" />
          ランキング掲載履歴
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {data.map((item, index) => (
            <div key={index}>
              {/* 掲載状況バッジ */}
              <div className="flex items-center gap-2 mb-2">
                {item.status === '未掲載' ? (
                  <>
                    <Badge variant="destructive" className="text-sm">
                      現在未掲載
                    </Badge>
                    <span className="text-sm text-muted-foreground">
                      現在未掲載中: {calculateDaysAgo(item.datetime)}
                    </span>
                  </>
                ) : (
                  <>
                    <Badge variant="secondary" className="text-sm">
                      再掲載済み
                    </Badge>
                    <span className="text-sm text-muted-foreground">
                      非掲載だった時間: {calculateTimeDifference(item.datetime, item.endDatetime!)}
                    </span>
                  </>
                )}
              </div>

              {/* 期間表示 */}
              <div className="flex items-center gap-2 text-sm text-muted-foreground mb-2">
                <Clock className="h-4 w-4" />
                <span>{formatDateTime(item.datetime)}</span>
                {item.endDatetime && (
                  <>
                    <span>→</span>
                    <span>{formatDateTime(item.endDatetime)}</span>
                  </>
                )}
              </div>

              {/* 非掲載時点の状況 */}
              <Alert className="mb-2">
                <AlertDescription>
                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2 text-sm font-medium">
                      <BarChart3 className="h-4 w-4" />
                      <span>非掲載時点の状況</span>
                    </div>
                    <Separator className="my-1.5" />
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      <div className="flex items-start gap-1.5">
                        <Users className="h-4 w-4 mt-0.5 text-blue-600 dark:text-blue-400" />
                        <div className="flex-1">
                          <div className="text-xs text-muted-foreground">メンバー数</div>
                          <div className="text-sm font-semibold">
                            {formatMember(item.member)}人
                            <span className="ml-2 text-xs text-muted-foreground">
                              (現在: {formatMember(item.currentMember)}人
                              <span className={`ml-1 ${item.memberDiff > 0 ? 'text-green-600 dark:text-green-400' : item.memberDiff < 0 ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'}`}>
                                {signedNum(item.memberDiff)}
                              </span>)
                            </span>
                          </div>
                        </div>
                      </div>
                      <div className="flex items-start gap-1.5">
                        {item.memberDiff >= 0 ? (
                          <TrendingUp className="h-4 w-4 mt-0.5 text-green-600 dark:text-green-400" />
                        ) : (
                          <TrendingDown className="h-4 w-4 mt-0.5 text-red-600 dark:text-red-400" />
                        )}
                        <div className="flex-1">
                          <div className="text-xs text-muted-foreground">ランキング順位（同一カテゴリ内）</div>
                          <div className="text-sm font-semibold">
                            {calculatePositionPercentage(item.percentage)}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </AlertDescription>
              </Alert>

              {/* 変更内容 */}
              <div className="flex items-start gap-1.5 text-sm">
                <FileText className="h-4 w-4 mt-0.5 text-muted-foreground" />
                <div className="flex-1">
                  <span className="text-muted-foreground">変更内容: </span>
                  {item.updateItems.length > 0 ? (
                    <span className="font-medium">
                      {item.updateItems.map((updateItem, idx) => (
                        <span key={idx}>
                          {translateUpdateItem(updateItem)}
                          {idx < item.updateItems.length - 1 && '、'}
                        </span>
                      ))}
                    </span>
                  ) : (
                    <span className="text-muted-foreground">なし</span>
                  )}
                </div>
              </div>

              {index < data.length - 1 && <Separator className="mt-4" />}
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
})

RankingHistory.displayName = 'RankingHistory'
