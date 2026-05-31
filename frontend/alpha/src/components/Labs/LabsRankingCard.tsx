import { memo } from 'react'
import { Card, CardContent } from '@/components/ui/card'
import { OfficialIcon, SpecialIcon } from '@/components/icons'
import { imgPreviewUrl } from '@/lib/imageUrl'
import { formatMemberCompact } from '@/lib/formatMember'
import type { AccessRankingRoom, SearchRankingRoom } from '@/types/api'
import type { LabsMode } from './LabsControls'

type LabsRoom = AccessRankingRoom | SearchRankingRoom

interface LabsRankingCardProps {
  room: LabsRoom
  rank: number
  mode: LabsMode
  onCardClick: (id: number) => void
}

const isAccess = (room: LabsRoom): room is AccessRankingRoom =>
  'pageviews' in room

// 主指標の小単位（PV / クリック）。数値は Sora の tabular-nums で構造化する。
function PrimaryMetric({ room, mode }: { room: LabsRoom; mode: LabsMode }) {
  if (mode === 'access' && isAccess(room)) {
    return (
      <div className="flex items-baseline gap-1">
        <span className="font-display text-xl font-bold tabular-nums leading-none text-primary">
          {room.pageviews.toLocaleString()}
        </span>
        <span className="text-xs text-muted-foreground">PV</span>
      </div>
    )
  }
  if (!isAccess(room)) {
    return (
      <div className="flex items-baseline gap-1">
        <span className="font-display text-xl font-bold tabular-nums leading-none text-primary">
          {room.searchClicks.toLocaleString()}
        </span>
        <span className="text-xs text-muted-foreground">クリック</span>
      </div>
    )
  }
  return null
}

export const LabsRankingCard = memo(({ room, rank, mode, onCardClick }: LabsRankingCardProps) => {
  return (
    <Card
      data-testid={`labs-card-${room.id}`}
      className="hover:shadow-md transition-shadow cursor-pointer overflow-hidden select-none"
      onClick={() => onCardClick(room.id)}
    >
      <CardContent className="flex items-start gap-3 p-3 md:p-4">
        {/* 順位 */}
        <span className="flex-shrink-0 w-6 pt-0.5 text-center font-display text-sm font-bold tabular-nums text-muted-foreground">
          {rank}
        </span>

        {room.img && (
          <img
            src={imgPreviewUrl(room.img)}
            alt={room.name}
            className="w-12 h-12 md:w-14 md:h-14 rounded-full object-cover flex-shrink-0"
          />
        )}

        <div className="flex-1 min-w-0">
          {/* 名前 */}
          <h3 className="text-[15px] md:text-base font-semibold leading-snug break-words line-clamp-2">
            {room.emblem === 2 && (
              <OfficialIcon className="w-[18px] h-[18px] inline-block align-text-bottom mr-1" />
            )}
            {room.emblem === 1 && (
              <SpecialIcon className="w-[19px] h-[18px] inline-block align-text-bottom mr-1" />
            )}
            {room.name}
          </h3>

          {/* 説明 */}
          {room.desc && (
            <p className="mt-0.5 text-xs leading-snug text-muted-foreground break-words line-clamp-1">
              {room.desc}
            </p>
          )}

          {/* メタ行：現在メンバー ・ カテゴリ */}
          <div className="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground">
            <span className="font-semibold text-foreground">
              メンバー {formatMemberCompact(room.member)}
            </span>
            {room.categoryName && (
              <>
                <span aria-hidden className="opacity-50">・</span>
                <span className="truncate">{room.categoryName}</span>
              </>
            )}
          </div>

          {/* 指標ブロック：主指標を主役に、検索流入は表示回数/平均順位を控えめに添える */}
          <div className="mt-2 rounded-md border bg-muted/40 px-2.5 py-2">
            <PrimaryMetric room={room} mode={mode} />

            {mode === 'search' && !isAccess(room) && (
              <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] leading-none text-muted-foreground">
                <span>
                  表示回数{' '}
                  <span className="tabular-nums text-foreground">
                    {room.searchImpressions.toLocaleString()}
                  </span>
                </span>
                <span aria-hidden className="opacity-40">|</span>
                <span>
                  平均順位{' '}
                  <span className="tabular-nums text-foreground">
                    {room.searchPosition != null ? room.searchPosition.toFixed(1) : '—'}
                  </span>
                </span>
              </div>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  )
})

LabsRankingCard.displayName = 'LabsRankingCard'
