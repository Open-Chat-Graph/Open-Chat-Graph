import { type ReactNode, useEffect, useRef, useState } from 'react'
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
 * 検索・Labs・period-growth の「初回／再取得／追加読み込み」全状態でこの1本に統一する。
 * 進行値は useListProgress（先頭ページ）／末尾用は ListProgressFooter が制御する。
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

interface ListRefetchBarProps {
  /** 表示するか（既存リスト表示中の再取得の応答待ち） */
  active: boolean
  /** 0..100。useListProgress の progress をそのまま渡す。 */
  progress: number
  /** 上に出す文言（既定「更新中…」） */
  label?: string
}

/**
 * 既にリストが見えている状態での再取得（条件変更／再実行）中に、
 * 既存リストの上へ淡い dim を被せ、上部に初回と同じプログレスバー＋小さな文言を出す。
 * スピナーは使わない（初回・追加読み込みと完全に同じ「バー」に統一する）。
 * 親は relative にしておくこと。z は overlay トークン（生 z-[NN] 不使用）。
 */
export function ListRefetchBar({ active, progress, label = '更新中…' }: ListRefetchBarProps) {
  if (!active) return null
  return (
    <div
      className="pointer-events-none absolute inset-0 z-overlay rounded-lg bg-background/55 backdrop-blur-[1px]"
      role="status"
      aria-label={label}
    >
      <div className="px-1 pt-1">
        <ListProgressBar progress={progress} active={active} />
        <p className="mt-2 text-center text-xs text-muted-foreground">{label}</p>
      </div>
    </div>
  )
}

interface ListProgressFooterProps {
  /** 次ページの応答待ちか（無限スクロールの append） */
  isLoading: boolean
  /** さらに次ページがあるか（番兵を出すか） */
  hasMore: boolean
  /** IntersectionObserver の番兵 ref（useInfiniteList の sentinelRef ＝ callback ref も可） */
  observerRef: React.Ref<HTMLDivElement>
}

/**
 * リスト末尾の「追加読み込み」インジケータ。
 * 初回・再取得と同じ ListProgressBar を末尾に出す（スピナーは使わない＝全状態同一の見た目）。
 * 次ページの ETA は取りにくいので、軽い indeterminate（0→90% へ緩く ramp して張り付き）で表現する。
 */
export function ListProgressFooter({ isLoading, hasMore, observerRef }: ListProgressFooterProps) {
  const [progress, setProgress] = useState(0)
  const rafRef = useRef<number | null>(null)
  const startRef = useRef(0)

  useEffect(() => {
    const clear = () => {
      if (rafRef.current != null) cancelAnimationFrame(rafRef.current)
      rafRef.current = null
    }
    if (!isLoading) {
      clear()
      setProgress(0)
      return
    }
    // 末尾バーは ETA を持たないので固定 ramp（約900msで90%へ漸近）で indeterminate を表現。
    startRef.current = performance.now()
    const RAMP_MS = 900
    const tick = (now: number) => {
      const ratio = (now - startRef.current) / RAMP_MS
      const eased = 1 - Math.exp(-1.6 * ratio)
      setProgress(Math.min(90, eased * 90))
      rafRef.current = requestAnimationFrame(tick)
    }
    rafRef.current = requestAnimationFrame(tick)
    return clear
  }, [isLoading])

  return (
    <>
      {isLoading && (
        <div className="px-1 py-6">
          <ListProgressBar progress={progress} active={isLoading} />
        </div>
      )}
      {hasMore && <div ref={observerRef} className="h-4" />}
    </>
  )
}

interface ListProgressRegionProps {
  /** 0..100。useListProgress の progress をそのまま渡す。 */
  progress: number
  /** バーを表示すべきか。useListProgress の active。 */
  active: boolean
  /** すでにリスト結果が見えているか（再取得＝重ねバー／初回＝上部バー の振り分け）。 */
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
 *  - `active && hasResults`（再取得）     → children を relative で包み、淡い dim ＋ 同じ上部バー（スピナー無し）。
 *  - それ以外                              → children をそのまま描画。
 *
 * 追加読み込み（無限スクロールの次ページ）はリスト末尾の ListProgressFooter が同じバーで担う。
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
  const showRefetchBar = active && hasResults

  return (
    <>
      {showTopBar && (
        <div className="pt-1">
          <ListProgressBar progress={progress} active={showTopBar} />
          <p className="mt-2 text-center text-xs text-muted-foreground">{caption}</p>
        </div>
      )}
      <div className="relative">
        <ListRefetchBar active={showRefetchBar} progress={progress} label={caption} />
        {children}
      </div>
    </>
  )
}
