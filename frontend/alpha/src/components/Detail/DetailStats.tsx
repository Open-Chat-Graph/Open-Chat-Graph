import { memo } from 'react'
import { AlertCircle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { diffColorClass } from '@/lib/colors'

interface DetailStatsProps {
  currentMember: number
  joinMethodType?: number
  hourlyDiff: number | null
  hourlyPercentage: number | null
  diff24h: number | null
  percent24h: number | null
  diff1w: number | null
  percent1w: number | null
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

// タイムスタンプ → Date（秒・ミリ秒・数値文字列を吸収）
const toDate = (timestamp: string | number | null | undefined): Date | null => {
  if (timestamp === null || timestamp === undefined || timestamp === '') return null
  let ts: number
  if (typeof timestamp === 'number') {
    ts = timestamp
  } else if (/^\d+$/.test(timestamp)) {
    ts = parseInt(timestamp)
  } else {
    const d = new Date(timestamp)
    return isNaN(d.getTime()) ? null : d
  }
  const ms = ts > 9999999999 ? ts : ts * 1000
  const d = new Date(ms)
  return isNaN(d.getTime()) ? null : d
}

const formatTimestamp = (timestamp: string | number): string =>
  toDate(timestamp)?.toLocaleDateString('ja-JP') ?? String(timestamp)

// 増減の色 → lib/colors.ts の diffColorClass へ移動
const diffColor = diffColorClass

// 「+1,234」形式
const fmtDiff = (diff: number): string =>
  `${diff > 0 ? '+' : diff === 0 ? '±' : ''}${diff.toLocaleString()}`

export const DetailStats = memo(({
  currentMember,
  joinMethodType,
  hourlyDiff,
  hourlyPercentage,
  diff24h,
  percent24h,
  diff1w,
  percent1w,
  registeredAt,
  categoryName,
  isInRanking,
}: DetailStatsProps) => {
  const hasHourlyData = isValidRankingData(hourlyDiff)
  const has24hData = isValidRankingData(diff24h)
  const has1wData = isValidRankingData(diff1w)
  const isNotInRanking = !isInRanking

  // 1指標分の表示（ラベル＋符号付き増減＋%）。本家詳細同様、3指標を横並びで見せる。
  const renderMetric = (label: string, has: boolean, diff: number | null, percent: number | null) => (
    <div key={label} className="flex flex-col">
      <span className="text-[11px] tracking-wide text-muted-foreground">{label}</span>
      {has && diff !== null ? (
        <span className={`text-base font-semibold tabular-nums ${diffColor(diff)}`}>
          {fmtDiff(diff)}
          {percent !== null && percent !== undefined && diff !== 0 && (
            <span className="ml-0.5 text-xs font-normal">({percent > 0 ? '+' : ''}{percent.toFixed(1)}%)</span>
          )}
        </span>
      ) : (
        <span className="text-base font-semibold text-muted-foreground">N/A</span>
      )}
    </div>
  )

  return (
    <div className="max-w-[var(--content-w)] mx-auto space-y-3">
      {/* メンバー数（主役）＋ 3指標: surface-highlight で主役の面に昇格 */}
      <div className="surface-highlight flex flex-wrap items-end gap-x-6 gap-y-3 px-4 py-3">
        <div className="flex flex-col">
          <span className="text-[11px] tracking-wide text-muted-foreground">メンバー</span>
          <span className="text-2xl font-bold leading-none tabular-nums">
            {currentMember.toLocaleString()}
            <span className="ml-0.5 text-base font-medium text-muted-foreground">人</span>
          </span>
        </div>

        {isNotInRanking
          ? renderMetric('1週間', has1wData, diff1w, percent1w)
          : [
              renderMetric('1時間', hasHourlyData, hourlyDiff, hourlyPercentage),
              renderMetric('24時間', has24hData, diff24h, percent24h),
              renderMetric('1週間', has1wData, diff1w, percent1w),
            ]}
      </div>

      {/* メタ：カテゴリ ・ 入室方式 ・ 開設日 ＋ 非掲載バッジ。
          以前は text-xs で小さすぎ読みづらかったため text-sm に引き上げ、
          値（カテゴリ名・入室タイプ・開設日）は foreground でコントラストを確保。
          ラベル相当の語（「入室」「開設」）と区切り「・」だけを muted で控えめにする。 */}
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-foreground">
        {categoryName && (
          <>
            <span className="truncate font-medium">{categoryName}</span>
            <span aria-hidden className="text-muted-foreground/60">・</span>
          </>
        )}
        <span>
          <span className="text-muted-foreground">入室</span>{' '}
          <span className="font-medium">{getJoinMethodLabel(joinMethodType ?? 0)}</span>
        </span>
        {registeredAt && (
          <>
            <span aria-hidden className="text-muted-foreground/60">・</span>
            <span>
              <span className="font-medium tabular-nums">{formatTimestamp(registeredAt)}</span>{' '}
              <span className="text-muted-foreground">開設</span>
            </span>
          </>
        )}
        {isNotInRanking && (
          <Badge variant="secondary" className="ml-1 flex items-center gap-1 h-5 px-1.5 text-[11px] font-normal">
            <AlertCircle className="h-3 w-3" />
            ランキング非掲載
          </Badge>
        )}
      </div>
    </div>
  )
})

DetailStats.displayName = 'DetailStats'
