import { cn } from '@/lib/utils'

interface SearchProgressBarProps {
  /** 0..100 */
  progress: number
  /** 表示するか。false の間は高さ0で畳む */
  active: boolean
  className?: string
}

/**
 * 検索の応答待ちを表す細い上部プログレスバー（αトーンの primary）。
 * 初回ロード時に結果リストの上へ置く。進行値は useSearchProgress が制御する。
 */
export function SearchProgressBar({ progress, active, className }: SearchProgressBarProps) {
  return (
    <div
      className={cn(
        'h-0.5 w-full overflow-hidden rounded-full bg-primary/10 transition-opacity duration-200',
        active ? 'opacity-100' : 'opacity-0',
        className,
      )}
      role="progressbar"
      aria-label="検索中"
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
