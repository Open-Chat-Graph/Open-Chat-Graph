import { useState, useEffect, useCallback } from 'react'

const REVEAL_STEP = 30

interface UseInfiniteRevealOptions {
  /** 取得済みバッファの件数（全ページ flatMap 後の length）。 */
  loadedCount: number
  /** API の hasMore（次の1000件があるか）。 */
  hasMore: boolean
  /** useSWRInfinite の setSize。次の1000件をネットワーク取得するときに呼ぶ。 */
  setSize: (updater: (s: number) => number) => void
  /**
   * filterKey 等。変化するたびに visibleCount を REVEAL_STEP に戻す。
   * null を渡すと空文字扱い（未検索状態等）。
   */
  resetKey: string | null
}

interface UseInfiniteRevealResult {
  /** 現在 DOM に描画すべき件数。 */
  visibleCount: number
  /**
   * IntersectionObserver の発火時に呼ぶ。
   * - バッファに未表示分があれば即時 +30（ネットワーク無し）。
   * - バッファを使い切って hasMore なら次の1000件をネットワーク取得。
   */
  onReachEnd: () => void
}

/**
 * 「ネットワーク大きく取得・描画は小出し」の状態管理フック。
 *
 * useSWRInfinite で得た全取得済みアイテム数（loadedCount）と
 * hasMore / setSize / size を受け取り、visibleCount（表示件数）を管理する。
 *
 * 呼び出し側は:
 *   1. アイテム配列を `slice(0, visibleCount)` して描画する。
 *   2. 末尾の IntersectionObserver が発火したら `onReachEnd()` を呼ぶ。
 *   3. ネットワーク取得中（isValidating）はガードして二重発火を防ぐ（呼び出し側の責務）。
 */
export function useInfiniteReveal({
  loadedCount,
  hasMore,
  setSize,
  resetKey,
}: UseInfiniteRevealOptions): UseInfiniteRevealResult {
  const [visibleCount, setVisibleCount] = useState(REVEAL_STEP)

  // resetKey が変化したら表示件数を先頭へ戻す。
  useEffect(() => {
    setVisibleCount(REVEAL_STEP)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [resetKey])

  const onReachEnd = useCallback(() => {
    if (visibleCount < loadedCount) {
      // バッファに未表示分あり → 即時 reveal（ネットワーク無し）
      setVisibleCount((prev) => prev + REVEAL_STEP)
    } else if (hasMore) {
      // バッファを使い切った & 次の1000件あり → ネットワーク取得
      setSize((s) => s + 1)
    }
    // 全件表示済み & hasMore=false → 何もしない
  }, [visibleCount, loadedCount, hasMore, setSize])

  return { visibleCount, onReachEnd }
}
