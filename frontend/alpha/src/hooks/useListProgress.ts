import { useCallback, useEffect, useRef, useState } from 'react'

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
  /** 1ページ目の取得が応答待ちか（append は除外して渡すこと）。これの立ち上がり/立ち下がりが全て */
  loading: boolean
  /**
   * ETA(ms) を取得する関数。取得開始（loading の false→true）ごとに1回呼ぶ。
   * 失敗／null は無視してフォールバック値で進める。表示は待たずに動き出す。
   * 実フェッチが無ければ（キャッシュ即答・再表示）一切呼ばれない。
   */
  fetchEta?: () => Promise<number | null | undefined>
}

/**
 * リスト取得（検索 / 期間増減 / Labs ランキング等）の応答待ちプログレスを管理する汎用フック。
 *
 * すべてを `loading` のエッジから導出する（requestKey 等の並行シミュレーションは持たない）:
 * - false→true（取得開始）: バーを表示し、`fetchEta` の ETA(ms) を基準に 0→約90% へ
 *   requestAnimationFrame で滑らかに進める（90% で頭打ち。応答が遅くても張り付かせない）。
 *   ETA 取得は表示を遅らせないよう即フォールバック値で開始し、結果が来たら速度だけ補正。
 * - true→false（応答到着）: 100% にスナップして FINISH_HOLD_MS 見せてから非表示。
 *   最小表示時間（MIN_VISIBLE_MS）に満たないうちに来た場合は、その時間まで進行を見せてから 100 へ。
 * - loading=true のままエフェクトが再実行された場合（React19 <Activity> の hidden→visible 復帰は
 *   依存が同じでもエフェクトを作り直す）: 開始時刻 ref を保持しているので、経過時間ぶん進んだ
 *   位置から rAF を再アームして続きを描く（凍結させない）。
 * - 実フェッチが無い再表示・キャッシュ即答では loading が一度も true にならないため、
 *   バーも ETA リクエストも構造的に発生しない（幽霊ローディング防止）。
 *
 * 所要時間の記録はサーバー側（各リスト処理）で行う前提。フロントは記録しない。
 */
export function useListProgress({ loading, fetchEta }: Options): UseListProgress {
  const [progress, setProgress] = useState(0)
  const [active, setActive] = useState(false)

  const rafRef = useRef<number | null>(null)
  const finishTimerRef = useRef<number | null>(null)
  const minVisibleTimerRef = useRef<number | null>(null)
  const startRef = useRef(0)
  const etaRef = useRef(FALLBACK_ETA_MS)
  // 完了演出が走っている間は進行 rAF を止めておく
  const finishingRef = useRef(false)
  // いま「取得エピソード」中か。loading の立ち上がりで true、完了処理の開始で false。
  // <Activity> の hide/show でエフェクトが再実行されても ref は保持されるため、
  // 「同一エピソードの再開」と「新規取得の開始」を区別できる。
  const episodeRef = useRef(false)
  // fetchEta は毎レンダーで参照が変わりうるので ref 経由で最新を読む
  //（毎レンダー後に同期。下の loading エフェクトより先に宣言してあるので先に実行される）
  const fetchEtaRef = useRef(fetchEta)
  useEffect(() => {
    fetchEtaRef.current = fetchEta
  })

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

  // 0→90% の進行アニメを（再）アームする。開始時刻は startRef を使うので、
  // エフェクト再実行からの再開でも経過時間ぶん進んだ位置から続きを描ける。
  const startRafLoop = useCallback(() => {
    clearRaf()
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
  }, [])

  // loading の立ち上がり＝取得開始（または Activity 復帰での再開）。
  useEffect(() => {
    if (!loading) return

    // 完了演出の途中で次の取得が始まった場合は演出を打ち切って仕切り直す
    finishingRef.current = false
    clearFinishTimer()
    clearMinVisibleTimer()

    if (!episodeRef.current) {
      // 新規エピソード: 実フェッチが始まったときだけバーを出し ETA を取る。
      // バーは rAF/タイマー駆動の演出（外部システム同期）なので effect 内での state 更新が本質。
      episodeRef.current = true
      startRef.current = performance.now()
      etaRef.current = FALLBACK_ETA_MS
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setActive(true)
      setProgress(0)

      const fetcher = fetchEtaRef.current
      if (fetcher) {
        const startedAt = startRef.current
        fetcher()
          .then((ms) => {
            // 既にエピソードが終わっている／次のエピソードに切り替わっていたら捨てる
            if (!episodeRef.current || startRef.current !== startedAt) return
            if (ms != null && Number.isFinite(ms) && ms > 0) etaRef.current = ms
          })
          .catch(() => {
            /* ETA は補助。失敗してもフォールバックで進める */
          })
      }
    } else {
      // 同一エピソードの再実行（Activity 復帰等）: 開始時刻・ETA を保持したまま再アームのみ
      setActive(true)
    }

    startRafLoop()
    return () => clearRaf()
  }, [loading, startRafLoop])

  // 応答到着（loading=false）で 100% → 少し見せて非表示。
  // ただし最小表示時間に満たないうちに来た場合は、その時間まで 0→90 の進行を見せてから 100 へ。
  // エピソードが無かった（loading が一度も true にならなかった）場合は active=false のままで何もしない。
  useEffect(() => {
    if (!active) return
    if (loading) return
    if (finishingRef.current) return
    if (minVisibleTimerRef.current != null) return // 既に最小表示待ちをスケジュール済み

    // ここでエピソード終了。次の loading 立ち上がりは新規取得として扱う。
    episodeRef.current = false

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
      // （loading=false で上のエフェクトの rAF はクリーンアップ済みなのでここで再アーム）
      startRafLoop()
      minVisibleTimerRef.current = window.setTimeout(snap, remain)
    }
  }, [active, loading, startRafLoop])

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
