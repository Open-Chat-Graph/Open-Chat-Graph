import { memo } from 'react'
import { Users, Clock, Calendar, Tag, AlertCircle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'

interface DetailStatsProps {
  currentMember: number
  joinMethodType?: number
  hourlyDiff: number | null
  hourlyPercentage: number | null
  diff24h: number | null
  percent24h: number | null
  diff1w: number | null
  percent1w: number | null
  createdAt?: number | null
  registeredAt?: string
  categoryName?: string
  isInRanking: boolean
}

// ランキングデータが有効かチェック（nullish、undefinedの場合は無効とみなす）
const isValidRankingData = (value: number | null | undefined): boolean => {
  return value !== null && value !== undefined
}

// 入室タイプのラベルを取得
const getJoinMethodLabel = (type: number) => {
  switch (type) {
    case 1:
      return '承認制'
    case 2:
      return '参加コード'
    default:
      return '全体公開'
  }
}

// タイムスタンプを日付文字列に変換
const formatTimestamp = (timestamp: string | number): string => {
  // 数値の場合
  if (typeof timestamp === 'number') {
    const ms = timestamp > 9999999999 ? timestamp / 1000 : timestamp * 1000
    return new Date(ms).toLocaleDateString('ja-JP')
  }
  // 文字列で数値のみの場合
  if (typeof timestamp === 'string' && timestamp.match(/^\d+$/)) {
    const ts = parseInt(timestamp)
    const ms = ts > 9999999999 ? ts / 1000 : ts * 1000
    return new Date(ms).toLocaleDateString('ja-JP')
  }
  // その他の場合はそのまま返す
  return String(timestamp)
}

export const DetailStats = memo(({
  currentMember,
  joinMethodType,
  hourlyDiff,
  hourlyPercentage,
  diff24h,
  percent24h,
  diff1w,
  percent1w,
  createdAt,
  registeredAt,
  categoryName,
  isInRanking,
}: DetailStatsProps) => {
  const hasHourlyData = isValidRankingData(hourlyDiff)
  const has24hData = isValidRankingData(diff24h)
  const has1wData = isValidRankingData(diff1w)
  const isNotInRanking = !isInRanking

  return (
    <div className="max-w-[var(--content-w)] mx-auto space-y-2">
      {/* メンバー・入室タイプ・カテゴリ */}
      <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
        <div className="flex items-center gap-1.5">
          <Users className="h-3.5 w-3.5 text-muted-foreground" />
          <span className="text-muted-foreground">メンバー:</span>
          <span className="font-semibold">{currentMember.toLocaleString()}人</span>
        </div>
        <div className="flex items-center gap-1.5">
          <div className="h-3.5 w-3.5 flex items-center justify-center">
            <div className="h-1.5 w-1.5 rounded-full bg-muted-foreground" />
          </div>
          <span className="text-muted-foreground">入室:</span>
          <span>{getJoinMethodLabel(joinMethodType ?? 0)}</span>
        </div>
        {categoryName && (
          <div className="flex items-center gap-1.5">
            <Tag className="h-3.5 w-3.5 text-muted-foreground" />
            <span className="text-muted-foreground">カテゴリ:</span>
            <span>{categoryName}</span>
          </div>
        )}
      </div>

      {/* 増減統計 */}
      <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
        {isNotInRanking ? (
          <>
            {/* ランキング非掲載時はバッジと1週間統計のみ表示 */}
            <Badge variant="secondary" className="flex items-center gap-1">
              <AlertCircle className="h-3 w-3" />
              ランキング非掲載
            </Badge>
            <div className="flex items-center gap-1.5">
              <Clock className="h-3.5 w-3.5 text-muted-foreground" />
              <span className="text-muted-foreground">1週間:</span>
              {has1wData ? (
                <span className={`font-semibold ${diff1w! > 0 ? 'text-green-600 dark:text-green-500' : diff1w! < 0 ? 'text-red-600 dark:text-red-500' : 'text-muted-foreground'}`}>
                  {diff1w! > 0 ? '+' : diff1w === 0 ? '±' : ''}{diff1w!.toLocaleString()}
                  {percent1w !== null && percent1w !== undefined && diff1w !== 0 && (
                    <span className="text-xs ml-1">({percent1w > 0 ? '+' : ''}{percent1w.toFixed(1)}%)</span>
                  )}
                </span>
              ) : (
                <span className="font-semibold text-muted-foreground">N/A</span>
              )}
            </div>
          </>
        ) : (
          <>
            {/* ランキング掲載時は全統計を表示 */}
            <div className="flex items-center gap-1.5">
              <Clock className="h-3.5 w-3.5 text-muted-foreground" />
              <span className="text-muted-foreground">1時間:</span>
              {hasHourlyData ? (
                <span className={`font-semibold ${hourlyDiff! > 0 ? 'text-green-600 dark:text-green-500' : hourlyDiff! < 0 ? 'text-red-600 dark:text-red-500' : 'text-muted-foreground'}`}>
                  {hourlyDiff! > 0 ? '+' : hourlyDiff === 0 ? '±' : ''}{hourlyDiff!.toLocaleString()}
                  {hourlyPercentage !== null && hourlyPercentage !== undefined && hourlyDiff !== 0 && (
                    <span className="text-xs ml-1">({hourlyPercentage > 0 ? '+' : ''}{hourlyPercentage.toFixed(1)}%)</span>
                  )}
                </span>
              ) : (
                <span className="font-semibold text-muted-foreground">N/A</span>
              )}
            </div>
            <div className="flex items-center gap-1.5">
              <span className="text-muted-foreground">24時間:</span>
              {has24hData ? (
                <span className={`font-semibold ${diff24h! > 0 ? 'text-green-600 dark:text-green-500' : diff24h! < 0 ? 'text-red-600 dark:text-red-500' : 'text-muted-foreground'}`}>
                  {diff24h! > 0 ? '+' : diff24h === 0 ? '±' : ''}{diff24h!.toLocaleString()}
                  {percent24h !== null && percent24h !== undefined && diff24h !== 0 && (
                    <span className="text-xs ml-1">({percent24h > 0 ? '+' : ''}{percent24h.toFixed(1)}%)</span>
                  )}
                </span>
              ) : (
                <span className="font-semibold text-muted-foreground">N/A</span>
              )}
            </div>
            <div className="flex items-center gap-1.5">
              <span className="text-muted-foreground">1週間:</span>
              {has1wData ? (
                <span className={`font-semibold ${diff1w! > 0 ? 'text-green-600 dark:text-green-500' : diff1w! < 0 ? 'text-red-600 dark:text-red-500' : 'text-muted-foreground'}`}>
                  {diff1w! > 0 ? '+' : diff1w === 0 ? '±' : ''}{diff1w!.toLocaleString()}
                  {percent1w !== null && percent1w !== undefined && diff1w !== 0 && (
                    <span className="text-xs ml-1">({percent1w > 0 ? '+' : ''}{percent1w.toFixed(1)}%)</span>
                  )}
                </span>
              ) : (
                <span className="font-semibold text-muted-foreground">N/A</span>
              )}
            </div>
          </>
        )}
      </div>

      {/* 日付 */}
      <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
        {registeredAt && (
          <div className="flex items-center gap-1.5">
            <Calendar className="h-3.5 w-3.5 text-muted-foreground" />
            <span className="text-muted-foreground">作成:</span>
            <span>{formatTimestamp(registeredAt)}</span>
          </div>
        )}
        {createdAt && (
          <div className="flex items-center gap-1.5">
            <span className="text-muted-foreground">登録:</span>
            <span>{new Date(createdAt * 1000).toLocaleDateString('ja-JP')}</span>
          </div>
        )}
      </div>
    </div>
  )
})

DetailStats.displayName = 'DetailStats'
