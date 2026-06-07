import { memo } from 'react'
import { ArrowRight, TrendingUp, TrendingDown } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { OfficialIcon, SpecialIcon } from '@/components/icons'
import { imgPreviewUrl } from '@/lib/imageUrl'
import { formatMemberCompact } from '@/lib/formatMember'
import { diffColorClass } from '@/lib/colors'
import type { PeriodGrowthItem } from '@/types/api'

interface PeriodGrowthCardProps {
  item: PeriodGrowthItem
  rank: number
  onCardClick: (id: number) => void
}

// "2024-05-30" / "2024-05-30 12:00:00" → "2024/05/30"
const formatDate = (raw?: string | null): string => {
  if (!raw) return ''
  const m = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})/)
  return m ? `${m[1]}/${m[2]}/${m[3]}` : String(raw)
}

// 作成日(createdAt: unix秒) / 登録日(registeredAt: unix秒の文字列) を YYYY/MM/DD に整形。
// unix秒(number/数値文字列)・日時文字列のいずれも吸収する（OpenChatCard と同作法）。
const formatUnixDate = (raw?: string | number | null): string | null => {
  if (raw === undefined || raw === null || raw === '') return null
  let d: Date
  if (typeof raw === 'number' || /^\d+$/.test(String(raw))) {
    const n = Number(raw)
    d = new Date(n > 9999999999 ? n : n * 1000) // 10桁以下は秒とみなしてミリ秒へ
  } else {
    const s = String(raw)
    d = new Date(s.includes('T') ? s : s.replace(' ', 'T'))
  }
  if (!isNaN(d.getTime())) {
    return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`
  }
  const m = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})/)
  return m ? `${m[1]}/${m[2]}/${m[3]}` : null
}

// 増減値の色クラス → lib/colors.ts へ移動

const formatDiff = (diff: number): string =>
  `${diff > 0 ? '+' : diff < 0 ? '' : '±'}${diff.toLocaleString()}`

export const PeriodGrowthCard = memo(({ item, rank, onCardClick }: PeriodGrowthCardProps) => {
  const isUp = item.diff > 0
  const isDown = item.diff < 0
  const createdAtText = formatUnixDate(item.createdAt)
  const registeredAtText = formatUnixDate(item.registeredAt)

  return (
    <Card
      data-testid={`period-growth-card-${item.id}`}
      className="hover:shadow-md transition-shadow cursor-pointer overflow-hidden select-none"
      onClick={() => onCardClick(item.id)}
    >
      <CardContent className="flex items-start gap-3 p-3 md:p-4">
        {/* 順位 */}
        <span className="flex-shrink-0 w-6 pt-0.5 text-center text-sm font-bold tabular-nums text-muted-foreground">
          {rank}
        </span>

        {item.img && (
          <img
            src={imgPreviewUrl(item.img)}
            alt={item.name}
            className="w-12 h-12 md:w-14 md:h-14 rounded-full object-cover flex-shrink-0"
          />
        )}

        <div className="flex-1 min-w-0">
          {/* 名前 */}
          <h3 className="text-[15px] md:text-base font-semibold leading-snug break-words line-clamp-2">
            {item.emblem === 2 && (
              <OfficialIcon className="w-[18px] h-[18px] inline-block align-text-bottom mr-1" />
            )}
            {item.emblem === 1 && (
              <SpecialIcon className="w-[19px] h-[18px] inline-block align-text-bottom mr-1" />
            )}
            {item.name}
          </h3>

          {/* 説明 */}
          {item.desc && (
            <p className="mt-0.5 text-xs leading-snug text-muted-foreground break-words line-clamp-1">
              {item.desc}
            </p>
          )}

          {/* メタ行：現在メンバー ・ カテゴリ */}
          <div className="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground">
            <span className="font-semibold text-foreground">
              メンバー {formatMemberCompact(item.member)}
            </span>
            {item.categoryName && (
              <>
                <span aria-hidden className="opacity-50">・</span>
                <span className="truncate">{item.categoryName}</span>
              </>
            )}
          </div>

          {/* 作成日・登録日（小さめの muted メタ行） */}
          {(createdAtText || registeredAtText) && (
            <div className="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11px] leading-none text-muted-foreground/80 tabular-nums">
              {createdAtText && <span>作成 {createdAtText}</span>}
              {createdAtText && registeredAtText && (
                <span aria-hidden className="opacity-50">・</span>
              )}
              {registeredAtText && <span>登録 {registeredAtText}</span>}
            </div>
          )}

          {/* 期間増減：pastMember → current の遷移 ＋ 実日付 */}
          <div className="mt-2 rounded-md border bg-muted/40 px-2.5 py-1.5">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-1.5 text-xs tabular-nums text-muted-foreground min-w-0">
                <span className="whitespace-nowrap">{formatMemberCompact(item.pastMember)}</span>
                <ArrowRight className="h-3 w-3 flex-shrink-0 opacity-60" aria-hidden />
                <span className="whitespace-nowrap font-medium text-foreground">{formatMemberCompact(item.member)}</span>
              </div>
              <div className={`flex items-center gap-1 whitespace-nowrap text-sm font-bold tabular-nums ${diffColorClass(item.diff)}`}>
                {isUp && <TrendingUp className="h-3.5 w-3.5" aria-hidden />}
                {isDown && <TrendingDown className="h-3.5 w-3.5" aria-hidden />}
                <span>{formatDiff(item.diff)}</span>
                {item.diff !== 0 && (
                  <span className="font-normal text-[11px]">
                    ({item.percent > 0 ? '+' : ''}{item.percent.toFixed(1)}%)
                  </span>
                )}
              </div>
            </div>
            <div className="mt-1 text-[11px] leading-none text-muted-foreground/80 tabular-nums">
              {formatDate(item.pastDate)} 〜 {formatDate(item.baseDate)}
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
})

PeriodGrowthCard.displayName = 'PeriodGrowthCard'
