import { memo } from 'react'
import { Eye, EyeOff } from 'lucide-react'
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
 * 統合グラフ下のルール1行。クリックでその線の表示/非表示を切り替える。
 * 非表示時は色チップ・文字を淡くし「消えている」ことを明示する。
 */
export const RoomListRow = memo(({ room, visible, onToggle }: RoomListRowProps) => {
  const thumb = imgPreviewUrl(room.img) || undefined

  return (
    <button
      type="button"
      onClick={() => onToggle(room.id)}
      aria-pressed={visible}
      className={`flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition-colors hover:bg-accent ${
        visible ? '' : 'opacity-45'
      }`}
      data-testid={`room-toggle-${room.id}`}
    >
      {/* 色チップ（線と一致） */}
      <span
        className="h-3 w-3 flex-shrink-0 rounded-full ring-1 ring-black/10"
        style={{ backgroundColor: room.color }}
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
        <span className="block truncate text-sm font-medium">{room.name}</span>
        <span className="block text-xs text-muted-foreground tabular-nums">
          {room.member.toLocaleString('ja-JP')}人
        </span>
      </span>

      {/* 表示トグル表示（目アイコン） */}
      <span className="flex-shrink-0 text-muted-foreground">
        {visible ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}
      </span>
    </button>
  )
})

RoomListRow.displayName = 'RoomListRow'
