import { memo } from 'react'
import { BarChart3, AlertCircle } from 'lucide-react'
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

// 増減の色
const diffColor = (diff: number): string =>
  diff > 0
    ? 'text-green-600 dark:text-green-500'
    : diff < 0
      ? 'text-red-600 dark:text-red-500'
      : 'text-muted-foreground'

// 「+1,234」形式
const fmtDiff = (diff: number): string =>
  `${diff > 0 ? '+' : diff === 0 ? '±' : ''}${diff.toLocaleString()}`

// 本家「オプチャグラフの分析」相当のナラティブを手元データから組み立てる。
// 新APIは増やさず、メンバー数・3指標・開設日・カテゴリ・掲載有無だけで文脈化する。
const buildNarrative = (p: {
  currentMember: number
  diff24h: number | null
  percent24h: number | null
  diff1w: number | null
  percent1w: number | null
  categoryName?: string
  registeredAt?: string
  isInRanking: boolean
}): string => {
  const parts: string[] = []
  const opened = toDate(p.registeredAt)
  const has24h = p.diff24h !== null && p.diff24h !== undefined && p.diff24h !== 0
  const has1w = p.diff1w !== null && p.diff1w !== undefined && p.diff1w !== 0

  // 勢いの一言（24時間→1週間の順で優先）。名詞止めで「〜のルーム。」に繋ぐ。
  let usedWeekInMomentum = false
  const momentum = (() => {
    if (has24h) {
      return `直近24時間で${fmtDiff(p.diff24h!)}人と${p.diff24h! > 0 ? '増加中' : '減少中'}`
    }
    if (has1w) {
      usedWeekInMomentum = true
      return `直近1週間で${fmtDiff(p.diff1w!)}人と${p.diff1w! > 0 ? '増加傾向' : '減少傾向'}`
    }
    return p.isInRanking ? '人数は横ばい' : '現在ランキングには非掲載'
  })()
  parts.push(`${momentum}のルーム。`)

  parts.push(`現在 ${p.currentMember.toLocaleString()}人。`)

  if (opened) {
    const years = Math.floor((Date.now() - opened.getTime()) / (365.25 * 24 * 3600 * 1000))
    const span = years >= 1 ? `運営${years}年` : '運営1年未満'
    parts.push(`${opened.getFullYear()}年${opened.getMonth() + 1}月 開設、${span}。`)
  }

  if (p.categoryName) {
    parts.push(`${p.categoryName} カテゴリのオープンチャット。`)
  }

  // 勢いで1週間を使っていなければ、補足として1週間の増減を出す（重複回避）
  if (!usedWeekInMomentum && has1w && p.percent1w !== null && p.percent1w !== undefined) {
    parts.push(`過去1週間で ${fmtDiff(p.diff1w!)}人 (${p.percent1w > 0 ? '+' : ''}${p.percent1w.toFixed(1)}%)。`)
  }

  return parts.join('')
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
      <span className="text-xs text-muted-foreground">{label}</span>
      {has && diff !== null ? (
        <span className={`text-sm font-semibold tabular-nums ${diffColor(diff)}`}>
          {fmtDiff(diff)}
          {percent !== null && percent !== undefined && diff !== 0 && (
            <span className="ml-0.5 text-xs font-normal">({percent > 0 ? '+' : ''}{percent.toFixed(1)}%)</span>
          )}
        </span>
      ) : (
        <span className="text-sm font-semibold text-muted-foreground">N/A</span>
      )}
    </div>
  )

  const narrative = buildNarrative({
    currentMember,
    diff24h,
    percent24h,
    diff1w,
    percent1w,
    categoryName,
    registeredAt,
    isInRanking,
  })

  return (
    <div className="max-w-[var(--content-w)] mx-auto space-y-3">
      {/* メンバー数（主役）＋ 3指標 */}
      <div className="flex flex-wrap items-end gap-x-6 gap-y-3">
        <div className="flex flex-col">
          <span className="text-xs text-muted-foreground">メンバー</span>
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

      {/* メタ：カテゴリ ・ 入室方式 ・ 開設日 ＋ 非掲載バッジ */}
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
        {categoryName && (
          <>
            <span className="truncate">{categoryName}</span>
            <span aria-hidden className="opacity-50">・</span>
          </>
        )}
        <span>入室 {getJoinMethodLabel(joinMethodType ?? 0)}</span>
        {registeredAt && (
          <>
            <span aria-hidden className="opacity-50">・</span>
            <span>{formatTimestamp(registeredAt)} 開設</span>
          </>
        )}
        {isNotInRanking && (
          <Badge variant="secondary" className="ml-1 flex items-center gap-1 h-5 px-1.5 text-[11px] font-normal">
            <AlertCircle className="h-3 w-3" />
            ランキング非掲載
          </Badge>
        )}
      </div>

      {/* オプチャグラフの分析（本家相当のナラティブ） */}
      <div className="rounded-lg border bg-muted/30 px-3 py-2.5">
        <div className="mb-1 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
          <BarChart3 className="h-3.5 w-3.5" />
          オプチャグラフの分析
        </div>
        <p className="text-sm leading-relaxed break-words">{narrative}</p>
      </div>
    </div>
  )
})

DetailStats.displayName = 'DetailStats'
