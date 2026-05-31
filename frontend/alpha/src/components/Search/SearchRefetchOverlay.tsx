import { Loader2 } from 'lucide-react'

interface SearchRefetchOverlayProps {
  /** 表示するか（既存リスト表示中の再検索・再実行の応答待ち） */
  active: boolean
}

/**
 * 既にリストが見えている状態での再検索（新クエリ／再実行）中に、
 * 既存リストの上へ薄いレイヤー＋スピナーを被せて応答待ちを明示する。
 * フリーズに見せないための演出。親は relative にしておくこと。
 */
export function SearchRefetchOverlay({ active }: SearchRefetchOverlayProps) {
  if (!active) return null
  return (
    <div
      className="pointer-events-none absolute inset-0 z-10 flex items-start justify-center rounded-lg bg-background/55 backdrop-blur-[1px]"
      role="status"
      aria-label="再検索中"
    >
      <div className="mt-8 flex items-center gap-2 rounded-full border bg-card/90 px-3 py-1.5 text-xs text-muted-foreground shadow-sm">
        <Loader2 className="h-3.5 w-3.5 animate-spin text-primary" />
        再検索中…
      </div>
    </div>
  )
}
