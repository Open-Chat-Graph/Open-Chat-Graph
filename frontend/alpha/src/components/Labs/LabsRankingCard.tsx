import { memo } from 'react'
import { FileText } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { OfficialIcon, SpecialIcon } from '@/components/icons'
import { imgPreviewUrl } from '@/lib/imageUrl'
import { formatMemberCompact } from '@/lib/formatMember'
import type { AccessRankingRoom, SearchRankingRoom, RankingPageMetric } from '@/types/api'
import type { LabsMode } from './LabsControls'

type LabsRoom = AccessRankingRoom | SearchRankingRoom

// 部屋とページ全体（トップ/おすすめ等）を1枚のカード型に束ねる。
// 統合ランキングはこの union を rank 付きで通し順に並べる。
export type LabsEntity =
  | { kind: 'room'; room: LabsRoom }
  | { kind: 'page'; page: RankingPageMetric }

interface LabsRankingCardProps {
  entity: LabsEntity
  rank: number
  mode: LabsMode
  onRoomClick: (id: number) => void
}

const isAccessRoom = (room: LabsRoom): room is AccessRankingRoom => 'pageviews' in room

// 主指標の1枠。大きな数字（Sora・tabular-nums）の下に小さなラベルを添えて構造化する。
function PrimaryStat({ value, label }: { value: number; label: string }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="font-display text-xl font-bold tabular-nums leading-none text-primary">
        {value.toLocaleString()}
      </span>
      <span className="text-[11px] leading-none text-muted-foreground">{label}</span>
    </div>
  )
}

// access/search それぞれの数値を room/page どちらからも安全に取り出す。
// 型は union なので kind で分岐し、各形に存在するフィールドだけを参照する。
function readMetrics(entity: LabsEntity): {
  pageviews: number
  activeUsers: number
  searchClicks: number
  searchImpressions: number
  searchPosition: number | null
} {
  if (entity.kind === 'page') {
    const p = entity.page
    return {
      pageviews: p.pageviews,
      activeUsers: p.activeUsers,
      searchClicks: p.searchClicks,
      searchImpressions: p.searchImpressions,
      searchPosition: p.searchPosition,
    }
  }
  const room = entity.room
  if (isAccessRoom(room)) {
    return {
      pageviews: room.pageviews,
      activeUsers: room.activeUsers,
      searchClicks: 0,
      searchImpressions: 0,
      searchPosition: null,
    }
  }
  return {
    pageviews: 0,
    activeUsers: room.activeUsers,
    searchClicks: room.searchClicks,
    searchImpressions: room.searchImpressions,
    searchPosition: room.searchPosition,
  }
}

// 指標ブロック（room/page 共通）。
// アクセス: 純PV と ユニークユーザー(UU) を両方主役で並べる。
// 検索流入: クリック数を主役にしつつ UU も並置し、表示回数/平均順位を控えめに添える。
function MetricBlock({ entity, mode }: { entity: LabsEntity; mode: LabsMode }) {
  const m = readMetrics(entity)
  return (
    <div className="mt-2 rounded-md border bg-muted/40 px-2.5 py-2">
      {mode === 'access' ? (
        <div className="flex items-end gap-5">
          <PrimaryStat value={m.pageviews} label="純PV" />
          <PrimaryStat value={m.activeUsers} label="ユニークユーザー" />
        </div>
      ) : (
        <>
          <div className="flex items-end gap-5">
            <PrimaryStat value={m.searchClicks} label="検索クリック" />
            <PrimaryStat value={m.activeUsers} label="ユニークユーザー" />
          </div>
          <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] leading-none text-muted-foreground">
            <span>
              表示回数{' '}
              <span className="tabular-nums text-foreground">
                {m.searchImpressions.toLocaleString()}
              </span>
            </span>
            <span aria-hidden className="opacity-40">|</span>
            <span>
              平均順位{' '}
              <span className="tabular-nums text-foreground">
                {m.searchPosition != null ? m.searchPosition.toFixed(1) : '—'}
              </span>
            </span>
          </div>
        </>
      )}
    </div>
  )
}

export const LabsRankingCard = memo(({ entity, rank, mode, onRoomClick }: LabsRankingCardProps) => {
  // ページ全体（トップ/おすすめ等）。本家ページを新規タブで開く。
  if (entity.kind === 'page') {
    const { page } = entity
    return (
      <Card
        data-testid={`labs-page-${page.path}`}
        className="hover:shadow-md transition-shadow cursor-pointer overflow-hidden select-none"
        onClick={() => window.open('https://openchat-review.me' + page.path, '_blank', 'noopener')}
      >
        <CardContent className="flex items-start gap-3 p-3 md:p-4">
          {/* 順位 */}
          <span className="flex-shrink-0 w-6 pt-0.5 text-center font-display text-sm font-bold tabular-nums text-muted-foreground">
            {rank}
          </span>

          {/* 部屋アバターの代わりに汎用アイコン */}
          <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-muted">
            <FileText className="h-6 w-6 text-muted-foreground" />
          </div>

          <div className="flex-1 min-w-0">
            {/* タイトル */}
            <h3 className="text-[15px] md:text-base font-semibold leading-snug break-words line-clamp-2">
              {page.label}
            </h3>

            {/* パス＋「ページ」タグ */}
            <div className="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-1">
              <code className="text-[11px] tabular-nums text-muted-foreground/80 break-all">
                {page.path}
              </code>
              <span className="rounded bg-muted px-1.5 py-px text-[10px] leading-none text-muted-foreground">
                ページ
              </span>
            </div>

            <MetricBlock entity={entity} mode={mode} />
          </div>
        </CardContent>
      </Card>
    )
  }

  // 部屋（従来通り）。クリックで内部の詳細へ。
  const { room } = entity
  return (
    <Card
      data-testid={`labs-card-${room.id}`}
      className="hover:shadow-md transition-shadow cursor-pointer overflow-hidden select-none"
      onClick={() => onRoomClick(room.id)}
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

          <MetricBlock entity={entity} mode={mode} />
        </div>
      </CardContent>
    </Card>
  )
})

LabsRankingCard.displayName = 'LabsRankingCard'
