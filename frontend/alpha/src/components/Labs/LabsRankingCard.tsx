import { memo } from 'react'
import { FileText } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { OfficialIcon, SpecialIcon } from '@/components/icons'
import { imgPreviewUrl } from '@/lib/imageUrl'
import { formatMemberCompact } from '@/lib/formatMember'
import { cn } from '@/lib/utils'
import type { LabsRankingRoom, RankingPageMetric } from '@/types/api'

// 部屋とページ全体（トップ/おすすめ等）を1枚のカード型に束ねる。
export type LabsEntity =
  | { kind: 'room'; room: LabsRankingRoom }
  | { kind: 'page'; page: RankingPageMetric }

// どの指標を主役（大きく・primary色）にするか。指標プルダウンに対応。jump＝入室数。
export type LabsPrimary = 'pv' | 'seo' | 'jump'

interface LabsRankingCardProps {
  entity: LabsEntity
  rank: number
  primary: LabsPrimary
  onRoomClick: (id: number) => void
}

// 1指標。主役は大きく primary 色、それ以外は控えめ。sub に「うちSEO経由/直接・間接」を添える。
function Stat({
  label,
  value,
  unit,
  sub,
  emphasize,
}: {
  label: string
  value: number
  unit?: string
  sub?: string
  emphasize?: boolean
}) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-[10px] leading-none text-muted-foreground">{label}</span>
      <span
        className={cn(
          'font-display font-bold tabular-nums leading-none',
          emphasize ? 'text-lg text-primary' : 'text-sm text-foreground',
        )}
      >
        {value.toLocaleString()}
        {unit && <span className="ml-0.5 text-[10px] font-normal text-muted-foreground">{unit}</span>}
      </span>
      {sub && <span className="text-[10px] leading-none text-muted-foreground/80">{sub}</span>}
    </div>
  )
}

// 部屋: アクセス数 / SEO流入(合計=直接+間接) / 入室数(うちSEO経由(間接含む)) の3指標。
function RoomMetrics({ room, primary }: { room: LabsRankingRoom; primary: LabsPrimary }) {
  const seoTotal = room.searchClicks + room.seoIndirect
  return (
    <div className="mt-2 flex flex-wrap items-end gap-x-5 gap-y-2 rounded-md border bg-muted/40 px-2.5 py-2">
      <Stat label="アクセス数" value={room.pageviews} unit="PV" emphasize={primary === 'pv'} />
      <Stat
        label="SEO流入(合計)"
        value={seoTotal}
        sub={`直接${room.searchClicks.toLocaleString()}・間接${room.seoIndirect.toLocaleString()}`}
        emphasize={primary === 'seo'}
      />
      <Stat
        label="入室数"
        value={room.jumpClicks}
        sub={`うちSEO経由（間接含む）${room.jumpClicksOrganic.toLocaleString()}`}
        emphasize={primary === 'jump'}
      />
    </div>
  )
}

// 非オプチャページ: アクセス数 / SEO流入(直接のみ・GSC) / 入室数(近似) / ユニークユーザー。
// 入室数はこのページを参照元として到達した部屋の参加リンク押下合計（近似）。
function PageMetrics({ page, primary }: { page: RankingPageMetric; primary: LabsPrimary }) {
  return (
    <div className="mt-2 flex flex-wrap items-end gap-x-5 gap-y-2 rounded-md border bg-muted/40 px-2.5 py-2">
      <Stat label="アクセス数" value={page.pageviews} unit="PV" emphasize={primary === 'pv'} />
      <Stat
        label="SEO流入"
        value={page.searchClicks}
        sub={`平均 ${page.searchPosition != null ? page.searchPosition.toFixed(1) : '—'}位`}
        emphasize={primary === 'seo'}
      />
      <Stat
        label="入室数"
        value={page.jumpClicks}
        sub={`うちSEO経由（間接含む）${page.jumpClicksOrganic.toLocaleString()}`}
        emphasize={primary === 'jump'}
      />
      <Stat label="ユニークユーザー" value={page.activeUsers} unit="人" />
    </div>
  )
}

// 連番。左に幅を取る固定列ではなく、名前行の頭に小さく出して横スペースを無駄にしない。
function RankBadge({ rank }: { rank: number }) {
  return (
    <span className="mr-1.5 align-middle text-xs font-bold tabular-nums text-muted-foreground">
      {rank}
    </span>
  )
}

export const LabsRankingCard = memo(({ entity, rank, primary, onRoomClick }: LabsRankingCardProps) => {
  // 非オプチャページ。本家ページを新規タブで開く。
  if (entity.kind === 'page') {
    const { page } = entity
    return (
      <Card
        data-testid={`labs-page-${page.path}`}
        className="cursor-pointer select-none overflow-hidden transition-shadow hover:shadow-md"
        onClick={() => window.open('https://openchat-review.me' + page.path, '_blank', 'noopener')}
      >
        <CardContent className="flex items-start gap-3 p-3 md:p-4">
          <div className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg bg-muted">
            <FileText className="h-5 w-5 text-muted-foreground" />
          </div>
          <div className="min-w-0 flex-1">
            <h3 className="break-words text-[15px] font-semibold leading-snug line-clamp-2 md:text-base">
              <RankBadge rank={rank} />
              {page.label}
            </h3>
            <div className="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-1">
              <code className="break-all text-[11px] tabular-nums text-muted-foreground/80">
                {page.path}
              </code>
              <span className="rounded bg-muted px-1.5 py-px text-[10px] leading-none text-muted-foreground">
                ページ
              </span>
            </div>
            <PageMetrics page={page} primary={primary} />
          </div>
        </CardContent>
      </Card>
    )
  }

  // 部屋。クリックで内部の詳細へ。
  const { room } = entity
  return (
    <Card
      data-testid={`labs-card-${room.id}`}
      className="cursor-pointer select-none overflow-hidden transition-shadow hover:shadow-md"
      onClick={() => onRoomClick(room.id)}
    >
      <CardContent className="flex items-start gap-3 p-3 md:p-4">
        {room.img && (
          <img
            src={imgPreviewUrl(room.img)}
            alt={room.name}
            className="h-12 w-12 flex-shrink-0 rounded-full object-cover md:h-14 md:w-14"
          />
        )}
        <div className="min-w-0 flex-1">
          <h3 className="break-words text-[15px] font-semibold leading-snug line-clamp-2 md:text-base">
            <RankBadge rank={rank} />
            {room.emblem === 2 && (
              <OfficialIcon className="mr-1 inline-block h-[18px] w-[18px] align-text-bottom" />
            )}
            {room.emblem === 1 && (
              <SpecialIcon className="mr-1 inline-block h-[18px] w-[19px] align-text-bottom" />
            )}
            {room.name}
          </h3>
          {room.desc && (
            <p className="mt-0.5 break-words text-xs leading-snug text-muted-foreground line-clamp-1">
              {room.desc}
            </p>
          )}
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
          <RoomMetrics room={room} primary={primary} />
          {room.keywords && room.keywords.length > 0 && (
            <p className="mt-1.5 break-words text-[11px] leading-snug text-muted-foreground/80 line-clamp-2">
              <span className="text-muted-foreground/60">流入KW: </span>
              {room.keywords.join('、')}
            </p>
          )}
        </div>
      </CardContent>
    </Card>
  )
})

LabsRankingCard.displayName = 'LabsRankingCard'
