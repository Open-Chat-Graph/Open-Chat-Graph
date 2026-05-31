import type { ReactNode } from 'react'
import { Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'

interface ListProgressBarProps {
  /** 0..100 */
  progress: number
  /** 表示するか。false の間は高さ0で畳む */
  active: boolean
  className?: string
}

/**
 * リスト取得の応答待ちを表す細い上部プログレスバー（αトーンの primary）。
 * 初回ロード時に結果リストの上へ置く。進行値は useListProgress が制御する。
 */
export function ListProgressBar({ progress, active, className }: ListProgressBarProps) {
  return (
    <div
      className={cn(
        'h-0.5 w-full overflow-hidden rounded-full bg-primary/10 transition-opacity duration-200',
        active ? 'opacity-100' : 'opacity-0',
        className,
      )}
      role="progressbar"
      aria-label="読み込み中"
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={Math.round(progress)}
      aria-hidden={!active}
    >
      <div
        className="h-full rounded-full bg-primary transition-[width] duration-150 ease-out"
        style={{ width: `${active ? progress : 0}%` }}
      />
    </div>
  )
}

interface ListRefetchOverlayProps {
  /** 表示するか（既存リスト表示中の再取得の応答待ち） */
  active: boolean
  /** 中央に出す文言（既定「更新中…」） */
  label?: string
}

/**
 * 既にリストが見えている状態での再取得（条件変更／再実行）中に、
 * 既存リストの上へ薄いレイヤー＋スピナーを被せて応答待ちを明示する。
 * フリーズに見せないための演出。親は relative にしておくこと。
 * z は overlay トークン（生 z-[NN] 不使用）。
 */
export function ListRefetchOverlay({ active, label = '更新中…' }: ListRefetchOverlayProps) {
  if (!active) return null
  return (
    <div
      className="pointer-events-none absolute inset-0 z-overlay flex items-start justify-center rounded-lg bg-background/55 backdrop-blur-[1px]"
      role="status"
      aria-label={label}
    >
      <div className="mt-8 flex items-center gap-2 rounded-full border bg-card/90 px-3 py-1.5 text-xs text-muted-foreground shadow-sm">
        <Loader2 className="h-3.5 w-3.5 animate-spin text-primary" />
        {label}
      </div>
    </div>
  )
}

interface ListProgressRegionProps {
  /** 0..100。useListProgress の progress をそのまま渡す。 */
  progress: number
  /** バー/オーバーレイを表示すべきか。useListProgress の active。 */
  active: boolean
  /** すでにリスト結果が見えているか（再取得＝オーバーレイ／初回＝上部バー の振り分け）。 */
  hasResults: boolean
  /** 応答待ち中に出す文言（既定「読み込み中…」。検索は「検索中…」等）。 */
  caption?: string
  /** リスト本体 */
  children: ReactNode
}

/**
 * リスト取得の応答待ち表示を1箇所に集約した領域コンポーネント。
 * 検索・Labs・period-growth はこれを使って完全に同一の見た目／挙動になる。
 *
 *  - `active && !hasResults`（初回ロード）→ 上部に細いプログレスバー＋中央キャプション。
 *  - `active && hasResults`（再取得）     → children を relative で包み、薄いぼかし＋スピナーを被せる。
 *  - それ以外                              → children をそのまま描画。
 *
 * 進行値・最小表示時間等のロジックは useListProgress が持つ（このコンポーネントは描画のみ）。
 */
export function ListProgressRegion({
  progress,
  active,
  hasResults,
  caption = '読み込み中…',
  children,
}: ListProgressRegionProps) {
  const showTopBar = active && !hasResults
  const showRefetchOverlay = active && hasResults

  return (
    <>
      {showTopBar && (
        <div className="pt-1">
          <ListProgressBar progress={progress} active={showTopBar} />
          <p className="mt-2 text-center text-xs text-muted-foreground">{caption}</p>
        </div>
      )}
      <div className="relative">
        <ListRefetchOverlay active={showRefetchOverlay} label={caption} />
        {children}
      </div>
    </>
  )
}
