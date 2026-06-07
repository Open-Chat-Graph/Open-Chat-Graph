import { memo } from 'react'
import { useNavigate } from 'react-router-dom'
import { TrendingUp, TrendingDown, FolderOpen } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { imgPreviewUrl } from '@/lib/imageUrl'
import { formatMemberCompact } from '@/lib/formatMember'
import { categoryName } from '@/lib/categories'
import { timeAgo, formatDateTime } from './timeAgo'
import { diffColorClass } from '@/lib/colors'
import type { FolderMovement } from '@/types/api'

interface FolderMovementCardProps {
  movement: FolderMovement
  onOpen: (movement: FolderMovement) => void
}

/**
 * フォルダ単位の増減アラートカード（type: 'folder'）。
 * MovementCard と同じトーン・構造。フォルダ名バッジを追加する。
 */
export const FolderMovementCard = memo(({ movement, onOpen }: FolderMovementCardProps) => {
  const navigate = useNavigate()
  const isUp = movement.direction === 'up'
  const diffColor = diffColorClass(isUp ? 1 : -1)

  const handleClick = () => {
    onOpen(movement)
    navigate(`/openchat/${movement.openChatId}`)
  }

  const sign = movement.diff > 0 ? '+' : ''
  const percentSign = movement.percent > 0 ? '+' : ''

  return (
    <Card
      data-testid={`folder-movement-${movement.id}`}
      onClick={handleClick}
      className={`cursor-pointer overflow-hidden transition-shadow hover:shadow-md select-none ${
        movement.isRead ? '' : 'border-l-2 border-l-primary bg-primary/[0.03]'
      }`}
    >
      <CardContent className="flex items-start gap-3 p-3 md:p-4">
        {movement.img && (
          <img
            src={imgPreviewUrl(movement.img)}
            alt={movement.name}
            className="h-12 w-12 flex-shrink-0 rounded-full object-cover md:h-14 md:w-14"
          />
        )}

        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1.5 flex-wrap">
            {!movement.isRead && (
              <span className="h-2 w-2 flex-shrink-0 rounded-full bg-primary" aria-label="未読" />
            )}
            <Badge variant="secondary" className="h-5 gap-1 px-1.5 text-[11px] font-normal">
              <FolderOpen className="h-3 w-3" />
              {movement.folderName}
            </Badge>
            <span className="ml-auto flex-shrink-0 text-[11px] text-muted-foreground">
              {timeAgo(movement.createdAt)}
            </span>
          </div>

          <h3 className="mt-1 break-words text-[15px] font-semibold leading-snug line-clamp-2 md:text-base">
            {movement.name}
          </h3>

          <div className="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground">
            {movement.currentMember != null && (
              <span className="font-semibold text-foreground">
                メンバー {formatMemberCompact(movement.currentMember)}
              </span>
            )}
            {movement.category != null && (
              <>
                {movement.currentMember != null && (
                  <span aria-hidden className="opacity-50">・</span>
                )}
                <span className="truncate">{categoryName(movement.category)}</span>
              </>
            )}
            <span className={`ml-auto inline-flex items-center gap-0.5 whitespace-nowrap font-semibold ${diffColor}`}>
              {isUp ? <TrendingUp className="h-3.5 w-3.5" /> : <TrendingDown className="h-3.5 w-3.5" />}
              {sign}
              {movement.diff.toLocaleString()}
              <span className="ml-0.5 font-normal">
                ({percentSign}
                {movement.percent.toFixed(1)}%)
              </span>
            </span>
          </div>

          <p className="mt-1 text-[11px] text-muted-foreground/80 tabular-nums">
            {formatDateTime(movement.createdAt)} に算出
          </p>
        </div>
      </CardContent>
    </Card>
  )
})

FolderMovementCard.displayName = 'FolderMovementCard'
