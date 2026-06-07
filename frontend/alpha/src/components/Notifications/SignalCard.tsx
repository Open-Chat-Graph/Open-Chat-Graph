import { memo } from 'react'
import { useNavigate } from 'react-router-dom'
import { Pencil, TrendingUp, Zap } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { timeAgo, formatDateTime } from './timeAgo'
import { categoryName } from '@/lib/categories'
import { diffColorClass } from '@/lib/colors'
import type { Signal, RoomChangeSignal, RankJumpSignal, PaceSignal } from '@/types/api'

interface SignalCardProps {
  signal: Signal
  onOpen: (signal: Signal) => void
}

/**
 * 機微シグナル（room_change / rank_jump / pace）の共通カード。
 * KeywordHitCard / MovementCard のトーン・構造に合わせる。
 */
export const SignalCard = memo(({ signal, onOpen }: SignalCardProps) => {
  const navigate = useNavigate()

  const openChatId =
    signal.type === 'room_change'
      ? signal.payload.openChatId
      : signal.type === 'rank_jump'
        ? signal.payload.openChatId
        : signal.payload.openChatId

  const handleClick = () => {
    onOpen(signal)
    navigate(`/openchat/${openChatId}`)
  }

  return (
    <Card
      data-testid={`signal-${signal.type}-${signal.id}`}
      onClick={handleClick}
      className={`cursor-pointer overflow-hidden transition-shadow hover:shadow-md select-none ${
        signal.isRead ? '' : 'border-l-2 border-l-primary bg-primary/[0.03]'
      }`}
    >
      <CardContent className="flex items-start gap-3 p-3 md:p-4">
        {/* アイコン領域（サムネ画像なし → アイコンプレースホルダー） */}
        <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-muted md:h-14 md:w-14">
          {signal.type === 'room_change' && <Pencil className="h-5 w-5 text-primary" />}
          {signal.type === 'rank_jump' && <TrendingUp className="h-5 w-5 text-primary" />}
          {signal.type === 'pace' && <Zap className="h-5 w-5 text-primary" />}
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1.5">
            {!signal.isRead && (
              <span className="h-2 w-2 flex-shrink-0 rounded-full bg-primary" aria-label="未読" />
            )}
            <Badge variant="secondary" className="h-5 gap-1 px-1.5 text-[11px] font-normal">
              {signal.type === 'room_change' && <Pencil className="h-3 w-3" />}
              {signal.type === 'rank_jump' && <TrendingUp className="h-3 w-3" />}
              {signal.type === 'pace' && <Zap className="h-3 w-3" />}
              {signal.type === 'room_change' && '部屋情報の変更'}
              {signal.type === 'rank_jump' && 'ランキング動向'}
              {signal.type === 'pace' && '増加ペース'}
            </Badge>
            <span className="ml-auto flex-shrink-0 text-[11px] text-muted-foreground">
              {timeAgo(signal.createdAt)}
            </span>
          </div>

          {signal.type === 'room_change' && <RoomChangeBody signal={signal} />}
          {signal.type === 'rank_jump' && <RankJumpBody signal={signal} />}
          {signal.type === 'pace' && <PaceBody signal={signal} />}

          <p className="mt-1 text-[11px] text-muted-foreground/80 tabular-nums">
            {formatDateTime(signal.createdAt)} に算出
          </p>
        </div>
      </CardContent>
    </Card>
  )
})

SignalCard.displayName = 'SignalCard'

// ---------- 各 type 固有の本文 ----------

function RoomChangeBody({ signal }: { signal: RoomChangeSignal }) {
  const { name, changes } = signal.payload
  return (
    <>
      <h3 className="mt-1 break-words text-[15px] font-semibold leading-snug line-clamp-2 md:text-base">
        {name}
      </h3>
      <p className="mt-0.5 text-xs text-muted-foreground">部屋情報が変わりました</p>
      <ul className="mt-1.5 space-y-0.5 text-xs">
        {changes.map((c, i) => (
          <li key={i} className="break-words leading-snug text-muted-foreground">
            <span className="font-medium text-foreground">
              {c.field === 'name' ? '名前' : c.field === 'description' ? '説明' : 'カテゴリ'}:{' '}
            </span>
            {c.field === 'description' ? (
              // 説明文は2行に省略（長くなるため）
              <>
                <span className="line-clamp-1">{c.old}</span>
                <span className="mx-1 text-muted-foreground/60">→</span>
                <span className="line-clamp-1">{c.new}</span>
              </>
            ) : c.field === 'category' ? (
              <>
                {categoryName(Number(c.old))}
                <span className="mx-1 text-muted-foreground/60">→</span>
                {categoryName(Number(c.new))}
              </>
            ) : (
              <>
                {c.old}
                <span className="mx-1 text-muted-foreground/60">→</span>
                {c.new}
              </>
            )}
          </li>
        ))}
      </ul>
    </>
  )
}

function RankJumpBody({ signal }: { signal: RankJumpSignal }) {
  const { name, position, prevPosition, kind } = signal.payload
  return (
    <>
      <h3 className="mt-1 break-words text-[15px] font-semibold leading-snug line-clamp-2 md:text-base">
        {name}
      </h3>
      <p className="mt-0.5 text-xs text-muted-foreground">
        {kind === 'enter' ? (
          <>
            公式ランキングに掲載されました（
            <span className={`font-semibold ${diffColorClass(1)}`}>{position}位</span>）
          </>
        ) : (
          <>
            ランキング順位が急上昇（
            {prevPosition != null && (
              <span className="text-muted-foreground">{prevPosition}位 → </span>
            )}
            <span className={`font-semibold ${diffColorClass(1)}`}>{position}位</span>）
          </>
        )}
      </p>
    </>
  )
}

function PaceBody({ signal }: { signal: PaceSignal }) {
  const { name, diff7, recentPace, basePace } = signal.payload
  const ratio = basePace > 0 ? recentPace / basePace : 0
  const ratioText = ratio.toFixed(1)
  const sign = diff7 >= 0 ? '+' : ''
  return (
    <>
      <h3 className="mt-1 break-words text-[15px] font-semibold leading-snug line-clamp-2 md:text-base">
        {name}
      </h3>
      <p className="mt-0.5 text-xs text-muted-foreground">
        増加ペースがいつもの
        <span className={`font-semibold ${diffColorClass(1)}`}>{ratioText}倍</span>（7日で
        <span className={`font-semibold ${diffColorClass(diff7)}`}>
          {sign}{diff7.toLocaleString()}人
        </span>）
      </p>
    </>
  )
}
