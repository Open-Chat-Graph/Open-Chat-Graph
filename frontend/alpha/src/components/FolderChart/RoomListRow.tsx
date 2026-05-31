import { memo } from 'react'
import { imgPreviewUrl } from '@/lib/imageUrl'

export interface RoomListItem {
  id: number
  name: string
  member: number
  img: string
  color: string
}

interface RoomListRowProps {
  room: RoomListItem
  visible: boolean
  onToggle: (id: number) => void
}

/**
 * 統合グラフ下の部屋1行。クリックでその線の表示/非表示を切り替える。
 *
 * 表示状態は「色チップの点灯/消灯」で示す（目アイコンは使わない＝アプリ内で目＝
 * 「見張る(watch)」の予約語なので衝突を避ける）。
 *  - 表示中: チップが線色で点灯（リング付き）。
 *  - 非表示: チップが消灯（淡い枠だけ）＋部屋名に取り消し線＋行を淡色化。
 */
export const RoomListRow = memo(({ room, visible, onToggle }: RoomListRowProps) => {
  const thumb = imgPreviewUrl(room.img) || undefined

  return (
    <button
      type="button"
      onClick={() => onToggle(room.id)}
      role="switch"
      aria-checked={visible}
      aria-label={`タップで線を${visible ? '非表示' : '表示'}`}
      className={`flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition-colors hover:bg-accent ${
        visible ? '' : 'opacity-55'
      }`}
      data-testid={`room-toggle-${room.id}`}
    >
      {/* 色チップ＝表示トグルの状態表示（点灯=表示中 / 消灯=非表示） */}
      <span
        className={`h-3.5 w-3.5 flex-shrink-0 rounded-full ring-1 transition-colors ${
          visible ? 'ring-border' : 'ring-foreground/20'
        }`}
        style={{
          backgroundColor: visible ? room.color : 'transparent',
          boxShadow: visible ? `0 0 0 3px color-mix(in srgb, ${room.color} 22%, transparent)` : undefined,
        }}
        aria-hidden
      />

      {/* サムネ */}
      {thumb ? (
        <img
          src={thumb}
          alt=""
          className="h-9 w-9 flex-shrink-0 rounded-md object-cover"
          loading="lazy"
          decoding="async"
        />
      ) : (
        <span className="h-9 w-9 flex-shrink-0 rounded-md bg-muted" aria-hidden />
      )}

      {/* 名前 + 現在人数 */}
      <span className="min-w-0 flex-1">
        <span
          className={`block truncate text-sm font-medium ${
            visible ? '' : 'text-muted-foreground line-through decoration-foreground/30'
          }`}
        >
          {room.name}
        </span>
        <span className="block text-xs text-muted-foreground tabular-nums">
          {room.member.toLocaleString('ja-JP')}人
        </span>
      </span>

      {/* 表示状態のテキストバッジ（補助的に状態を明示） */}
      <span
        className={`flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium leading-none ${
          visible
            ? 'bg-primary/10 text-primary'
            : 'bg-muted text-muted-foreground'
        }`}
        aria-hidden
      >
        {visible ? '表示中' : '非表示'}
      </span>
    </button>
  )
})

RoomListRow.displayName = 'RoomListRow'
