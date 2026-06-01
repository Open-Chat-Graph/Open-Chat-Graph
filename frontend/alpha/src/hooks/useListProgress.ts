import { useEffect, useRef, useState } from 'react'

/** ETA 取得に失敗／未指定のときの想定応答時間（ms）。これを基準に 0→90% を進める。 */
const FALLBACK_ETA_MS = 1500
/** 完了時に 100% を見せてから消えるまでの猶予（ms）。一瞬の達成感を出すため。 */
const FINISH_HOLD_MS = 320
/**
 * バーが視認できる最小表示時間（ms）。ローカル等で応答が一瞬だと 0→100 が速すぎて
 * 「出ていない」ように見えるため、応答が来てもこの時間までは 0→90 の進行を見せてから
 * 100 へスナップする（※応答前に 100 にはしない＝loading=false になってから働く）。
 */
const MIN_VISIBLE_MS = 450

export interface UseListProgress {
  /** 0..100。進行中は 0→約90、完了で 100。非表示時は 0 */
  progress: number
  /** バー/オーバーレイを表示すべきか（完了直後の 100% 表示中も true） */
  active: boolean
}

interface Options {
  /** 取得の識別キー（条件・再実行 nonce 等の合成）。変化で新しい取得とみなす */
  requestKey: string | null
  /** いま応答待ちか（SWR の isLoading || isValidating など。append は除外して渡すこと） */
  loading: boolean
  /**
   * ETA(ms) を取得する関数。requestKey 変化時に1回呼ぶ。
   * 失敗／null は無視してフォールバック値で進める。表示は待たずに動き出す。
   */
  fetchEta?: () => Promise<number | null | undefined>
}

/**
 * リスト取得（検索 / 期間増減 / Labs ランキング等）の応答待ちプログレスを管理する汎用フック。
 *
 * - `requestKey` が変化したら `fetchEta` で ETA(ms) を取り、その時間で 0→約90% へ
 *   requestAnimationFrame で滑らかに進める（90% で頭打ち。応答が遅くても張り付かせない）。
 * - 応答到着（loading=false）で一気に 100% にして少し見せてから非表示にする。
 *   見込みより早く来ても遅く来ても、到着時に必ず 100 まで詰める。
 * - 応答到着前に 100 にはしない（90% 頭打ち→到着で100）。
 * - ETA 取得は表示を遅らせないよう即座にフォールバック値で開始し、結果が来たら速度だけ補正。
 *
 * 所要時間の記録はサーバー側（各リスト処理）で行う前提。フロントは記録しない。
 * （旧 useSearchProgress を一般化したもの。検索もこれを使う）
 */
export function useListProgress({ requestKey, loading, fetchEta }: Options): UseListProgress {
  const [progress, setProgress] = useState(0)
  const [active, setActive] = useState(false)

  const rafRef = useRef<number | null>(null)
  const finishTimerRef = useRef<number | null>(null)
  const minVisibleTimerRef = useRef<number | null>(null)
  const startRef = useRef(0)
  const etaRef = useRef(FALLBACK_ETA_MS)
  // 完了演出が走っている間は進行 rAF を止めておく
  const finishingRef = useRef(false)
  // fetchEta は毎レンダーで参照が変わりうるので ref 経由で最新を読む（依存は requestKey に集約）
  const fetchEtaRef = useRef(fetchEta)
  fetchEtaRef.current = fetchEta
  // 直近で「取得開始」した requestKey。React19 <Activity> は visible 復帰でエフェクトを
  // 再実行する（state/ref は保持）ため、requestKey の“値”が変わっていない再mountでは
  // 新規取得ではない＝プログレスを起動しない（被せ復帰/タブ復帰の幽霊ローディング防止。仕様 D-1）。
  const lastKeyRef = useRef<string | null>(null)

  const clearRaf = () => {
    if (rafRef.current != null) cancelAnimationFrame(rafRef.current)
    rafRef.current = null
  }
  const clearFinishTimer = () => {
    if (finishTimerRef.current != null) window.clearTimeout(finishTimerRef.current)
    finishTimerRef.current = null
  }
  const clearMinVisibleTimer = () => {
    if (minVisibleTimerRef.current != null) window.clearTimeout(minVisibleTimerRef.current)
    minVisibleTimerRef.current = null
  }

  // requestKey 変化 = 新しい取得の開始。アニメをリセットして 0→90% を回し始める。
  useEffect(() => {
    if (!requestKey) {
      clearRaf()
      clearFinishTimer()
      clearMinVisibleTimer()
      finishingRef.current = false
      lastKeyRef.current = null
      setActive(false)
      setProgress(0)
      return
    }

    // 同じ requestKey でエフェクトが再実行された（Activity の visible 復帰・再mount 等）。
    // 新規取得ではないのでプログレスを起動しない（保持中の表示状態をそのまま維持）。
    if (lastKeyRef.current === requestKey) {
      return
    }
    lastKeyRef.current = requestKey

    finishingRef.current = false
    clearFinishTimer()
    clearMinVisibleTimer()
    startRef.current = performance.now()
    etaRef.current = FALLBACK_ETA_MS
    setActive(true)
    setProgress(0)

    // ETA を取得して進行速度を補正（取得を待たずに動き出している）
    let cancelled = false
    const fetcher = fetchEtaRef.current
    if (fetcher) {
      fetcher()
        .then((ms) => {
          if (cancelled) return
          if (ms != null && Number.isFinite(ms) && ms > 0) etaRef.current = ms
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
    // fetchEta は requestKey と連動するため依存に含めない（requestKey が代表）
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [requestKey])

  // 応答到着で 100% → 少し見せて非表示。
  // ただし最小表示時間に満たないうちに来た場合は、その時間まで 0→90 の進行を見せてから 100 へ。
  useEffect(() => {
    if (!active) return
    if (loading) return
    if (finishingRef.current) return
    if (minVisibleTimerRef.current != null) return // 既に最小表示待ちをスケジュール済み

    const snap = () => {
      finishingRef.current = true
      clearRaf()
      clearMinVisibleTimer()
      setProgress(100)
      finishTimerRef.current = window.setTimeout(() => {
        setActive(false)
        setProgress(0)
        finishingRef.current = false
      }, FINISH_HOLD_MS)
    }

    const elapsed = performance.now() - startRef.current
    const remain = MIN_VISIBLE_MS - elapsed
    if (remain <= 0) {
      snap()
    } else {
      // 最小表示時間まで rAF の進行（0→90）を継続させ、その後 100 へスナップ
      minVisibleTimerRef.current = window.setTimeout(snap, remain)
    }
  }, [active, loading])

  useEffect(
    () => () => {
      clearRaf()
      clearFinishTimer()
      clearMinVisibleTimer()
    },
    [],
  )

  return { progress, active }
}
