import { memo } from 'react'
import { useNavigate } from 'react-router-dom'
import { ExternalLink, Sparkles } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { imgPreviewUrl } from '@/lib/imageUrl'
import { lineOpenUrl } from '@/lib/lineUrl'
import { formatMemberCompact } from '@/lib/formatMember'
import { categoryName } from '@/lib/categories'
import { timeAgo } from './timeAgo'
import type { KeywordHit } from '@/types/api'

interface KeywordHitCardProps {
  hit: KeywordHit
  /** カードを開いたとき（既読化用） */
  onOpen: (hit: KeywordHit) => void
}

/**
 * 「新しい部屋」カード。キーワードアラートにヒットした新規部屋を表示する。
 * - openChatId があれば詳細ページへ遷移
 * - 未登録（openChatId=null）は詳細が無いので emid から「LINEで開く」導線だけ
 */
export const KeywordHitCard = memo(({ hit, onOpen }: KeywordHitCardProps) => {
  const navigate = useNavigate()
  const lineUrl = lineOpenUrl(hit.emid)

  const handleClick = () => {
    onOpen(hit)
    if (hit.openChatId != null) {
      navigate(`/openchat/${hit.openChatId}`)
    } else if (lineUrl) {
      window.open(lineUrl, '_blank', 'noopener')
    }
  }

  return (
    <Card
      data-testid={`keyword-hit-${hit.id}`}
      onClick={handleClick}
      className={`cursor-pointer overflow-hidden transition-shadow hover:shadow-md select-none ${
        hit.isRead ? '' : 'border-l-2 border-l-primary bg-primary/[0.03]'
      }`}
    >
      <CardContent className="flex items-start gap-3 p-3 md:p-4">
        {hit.img ? (
          <img
            src={imgPreviewUrl(hit.img)}
            alt={hit.name}
            className="h-12 w-12 flex-shrink-0 rounded-full object-cover md:h-14 md:w-14"
          />
        ) : (
          <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-muted md:h-14 md:w-14">
            <Sparkles className="h-5 w-5 text-muted-foreground" />
          </div>
        )}

        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1.5">
            {!hit.isRead && (
              <span className="h-2 w-2 flex-shrink-0 rounded-full bg-primary" aria-label="未読" />
            )}
            <Badge variant="secondary" className="h-5 gap-1 px-1.5 text-[11px] font-normal">
              <Sparkles className="h-3 w-3" />
              「{hit.keyword}」の新着
            </Badge>
            <span className="ml-auto flex-shrink-0 text-[11px] text-muted-foreground">
              {timeAgo(hit.createdAt)}
            </span>
          </div>

          <h3 className="mt-1 break-words text-[15px] font-semibold leading-snug line-clamp-2 md:text-base">
            {hit.name}
          </h3>

          {hit.desc && (
            <p className="mt-0.5 break-words text-xs leading-snug text-muted-foreground line-clamp-2">
              {hit.desc}
            </p>
          )}

          <div className="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground">
            {hit.member != null && (
              <span className="font-semibold text-foreground">
                メンバー {formatMemberCompact(hit.member)}
              </span>
            )}
            {hit.category != null && (
              <>
                {hit.member != null && <span aria-hidden className="opacity-50">・</span>}
                <span className="truncate">{categoryName(hit.category)}</span>
              </>
            )}
            {!hit.isRegistered && (
              <Badge variant="outline" className="ml-auto h-5 gap-1 px-1.5 text-[11px] font-normal">
                <ExternalLink className="h-3 w-3" />
                LINEで開く
              </Badge>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  )
})

KeywordHitCard.displayName = 'KeywordHitCard'
