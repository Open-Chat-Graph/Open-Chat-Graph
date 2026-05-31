import { useEffect, useRef, useState } from 'react'
import { alphaApi } from '@/api/alpha'
import type { SearchEtaParams } from '@/types/api'

/** ETA 取得に失敗／未指定のときの想定応答時間（ms）。これを基準に 0→90% を進める。 */
const FALLBACK_ETA_MS = 1500
/** 完了時に 100% を見せてから消えるまでの猶予（ms）。一瞬の達成感を出すため。 */
const FINISH_HOLD_MS = 280

export interface UseSearchProgress {
  /** 0..100。進行中は 0→約90、完了で 100。非表示時は 0 */
  progress: number
  /** バー/オーバーレイを表示すべきか（完了直後の 100% 表示中も true） */
  active: boolean
}

interface Options {
  /** 検索キー（キーワード・ソート・カテゴリ・再実行 nonce 等の合成）。変化で新しい検索とみなす */
  searchKey: string | null
  /** いま応答待ちか（SWR の isLoading || isValidating など） */
  loading: boolean
  /** ETA 取得に渡す検索条件。searchKey 変化時にこの条件で getSearchEta する */
  etaParams: SearchEtaParams | null
}

/**
 * 検索プログレスの進行を管理するフック。
 *
 * - `searchKey` が変化したら getSearchEta で ETA(ms) を取り、その時間で 0→約90% へ
 *   requestAnimationFrame で滑らかに進める（90% で頭打ち。応答が遅くても張り付かせない）。
 * - 応答到着（loading=false）で一気に 100% にして少し見せてから非表示にする。
 * - ETA 取得は表示を遅らせないよう即座にフォールバック値で開始し、結果が来たら速度だけ補正。
 *
 * 所要時間の記録はサーバー側（search）で行う前提。フロントは記録しない。
 */
export function useSearchProgress({ searchKey, loading, etaParams }: Options): UseSearchProgress {
  const [progress, setProgress] = useState(0)
  const [active, setActive] = useState(false)

  const rafRef = useRef<number | null>(null)
  const finishTimerRef = useRef<number | null>(null)
  const startRef = useRef(0)
  const etaRef = useRef(FALLBACK_ETA_MS)
  // 完了演出が走っている間は進行 rAF を止めておく
  const finishingRef = useRef(false)

  const clearRaf = () => {
    if (rafRef.current != null) cancelAnimationFrame(rafRef.current)
    rafRef.current = null
  }
  const clearFinishTimer = () => {
    if (finishTimerRef.current != null) window.clearTimeout(finishTimerRef.current)
    finishTimerRef.current = null
  }

  // searchKey 変化 = 新しい検索の開始。アニメをリセットして 0→90% を回し始める。
  useEffect(() => {
    if (!searchKey) {
      clearRaf()
      clearFinishTimer()
      finishingRef.current = false
      setActive(false)
      setProgress(0)
      return
    }

    finishingRef.current = false
    clearFinishTimer()
    startRef.current = performance.now()
    etaRef.current = FALLBACK_ETA_MS
    setActive(true)
    setProgress(0)

    // ETA を取得して進行速度を補正（取得を待たずに動き出している）
    let cancelled = false
    if (etaParams) {
      alphaApi
        .getSearchEta(etaParams)
        .then((res) => {
          if (cancelled) return
          if (Number.isFinite(res.etaMs) && res.etaMs > 0) etaRef.current = res.etaMs
        })
        .catch(() => {
          /* ETA は補助。失敗してもフォールバックで進める */
        })
    }

    const tick = (now: number) => {
      if (finishingRef.current) return
      const elapsed = now - startRef.current
      // 経過/ETA を 0..1 に正規化し、緩やかな飽和（1 - e^-x 風）で 90% へ漸近させる
      const ratio = elapsed / etaRef.current
      const eased = 1 - Math.exp(-1.6 * ratio)
      setProgress(Math.min(90, eased * 90))
      rafRef.current = requestAnimationFrame(tick)
    }
    rafRef.current = requestAnimationFrame(tick)

    return () => {
      cancelled = true
      clearRaf()
    }
    // etaParams はキー変化と連動するため依存に含めない（searchKey が代表）
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchKey])

  // 応答到着で 100% → 少し見せて非表示
  useEffect(() => {
    if (!active) return
    if (loading) return
    if (finishingRef.current) return

    finishingRef.current = true
    clearRaf()
    setProgress(100)
    finishTimerRef.current = window.setTimeout(() => {
      setActive(false)
      setProgress(0)
      finishingRef.current = false
    }, FINISH_HOLD_MS)
  }, [active, loading])

  useEffect(
    () => () => {
      clearRaf()
      clearFinishTimer()
    },
    [],
  )

  return { progress, active }
}
