import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import useSWRInfinite from 'swr/infinite'

/** 「ネットワーク大きく取得・描画は小出し」の1回の表示増分。 */
const REVEAL_STEP = 30

/**
 * 一覧3画面（検索 / 期間増減 / Labs）共通の SWR Infinite オプション。
 * revalidateIfStale:false 等は <Activity> 再表示（タブ復帰/オーバーレイ閉じ）の
 * 同一キー再検証を抑止するため（実フェッチの無い再描画で読み込み挙動を起こさない）。
 * errorRetryCount/errorRetryInterval: SW/ネットワーク起因の一時的失敗から素早く復帰させる。
 */
const SWR_INFINITE_OPTIONS = {
  revalidateFirstPage: false,
  revalidateIfStale: false,
  revalidateOnFocus: false,
  revalidateOnReconnect: false,
  dedupingInterval: 60000,
  errorRetryCount: 2,
  errorRetryInterval: 2000,
} as const

/**
 * 読み込みフェーズの分類（3画面で重複していた導出を一本化）。
 * - 'first': 1ページ目（初回/条件変更/再実行）の応答待ち → 上部バー or dim＋バー
 * - 'more' : 追加ページ（無限スクロール append）の応答待ち → 末尾バー
 * - 'idle' : 応答待ちなし
 */
export type ListLoadPhase = 'idle' | 'first' | 'more'

interface UseInfiniteListOptions<P extends { data: readonly unknown[] }> {
  /**
   * リスト条件の識別キー（キーワード/ソート/期間/nonce 等の合成）。null は未検索。
   * 「値が実際に変化した」ときだけ表示件数を先頭へ戻す（ref 比較）。
   * <Activity> の hidden→visible 復帰はエフェクトを作り直すが ref は保持されるため、
   * 同一キーでの復帰では何もしない＝スクロール位置・表示件数を失わない。
   */
  listKey: string | null
  /** useSWRInfinite の getKey（ページごとの SWR キー。null でフェッチ停止）。 */
  getKey: (pageIndex: number, previousPageData: P | null) => readonly unknown[] | null
  /** ページ取得関数（getKey が返したキーを受ける）。 */
  fetcher: (key: never) => Promise<P>
  /** ページ配列から「次ページがあるか」を導く（API ごとに hasMore/totalCount と形が違うため注入）。 */
  getHasMore: (pages: P[], loadedCount: number) => boolean
}

interface UseInfiniteListResult<P extends { data: readonly unknown[] }> {
  /** 取得済みページの配列（未取得は []）。サマリ（totalCount/updatedAt 等）はここから読む。 */
  pages: P[]
  /** 全ページの data を flat にした取得済みバッファ。`slice(0, visibleCount)` で描画する。 */
  items: P['data'][number][]
  error: Error | undefined
  /** 読み込みフェーズ。'first' を useListProgress の loading に渡す。'more' は末尾バー。 */
  phase: ListLoadPhase
  /** 次ページがあるか（番兵の表示判定）。 */
  hasMore: boolean
  /** 現在 DOM に描画すべき件数。 */
  visibleCount: number
  /** 無限スクロール番兵 <div> に渡す callback ref（ListProgressFooter の observerRef へ）。 */
  sentinelRef: (el: HTMLDivElement | null) => void
  /** エラー時の手動再試行（キャッシュを破棄して再フェッチ）。 */
  mutate: () => void
}

/**
 * 一覧3画面（検索 / 期間増減 / Labs）共通の無限リストコントローラ。
 * 各ページに重複していた useSWRInfinite 設定・読み込み分類・reveal・IntersectionObserver を1本化する。
 *
 * 設計の要点:
 * - ネットワークは LIMIT 件単位で取得し、描画は REVEAL_STEP(30) 件ずつ reveal する分離方式
 *   （旧 useInfiniteReveal を吸収）。
 * - 表示件数のリセットは「listKey の値が実際に変わったときだけ」（ref 比較）。
 *   <Activity> 復帰のエフェクト再実行では何もしない（リスト切り詰め・スクロール喪失の防止）。
 * - 条件変更時に setSize(1) は呼ばない。swr/infinite はページ数(_l)をキーごとにキャッシュしており
 *   新キーは自動的に size=1 から始まる。逆に setSize は内部で mutate（dedupe を迂回）に到達するため、
 *   マウント直後やキー変更直後に呼ぶと SWR 自身のキー変更再検証と重なって同じ重いクエリが
 *   2本並走する（実測2ms差の二重フェッチ）。
 * - IntersectionObserver は1つだけ生成し、揮発する状態（visibleCount/hasMore/取得中フラグ）は
 *   ref 経由でコールバック内から読む（読み込みフリップごとに observer を作り直さない）。
 *   reveal/取得完了後にまだ番兵が見えている場合に連鎖発火させるため、状態変化時は
 *   observe を張り替えて再評価だけさせる（unobserve→observe）。
 * - 二重発火ガードは「ネットワーク取得中は発火しない」の1本に統一
 *   （SWR v2 では isLoading ⊆ isValidating。旧 Search の !isValidating && !isLoading と
 *    旧 Labs/period-growth の !isValidating は実質同値だった）。
 */
export function useInfiniteList<P extends { data: readonly unknown[] }>({
  listKey,
  getKey,
  fetcher,
  getHasMore,
}: UseInfiniteListOptions<P>): UseInfiniteListResult<P> {
  const { data, error, isLoading, isValidating, size, setSize, mutate } = useSWRInfinite<P>(
    getKey,
    fetcher as (key: unknown) => Promise<P>,
    SWR_INFINITE_OPTIONS,
  )

  const pages = useMemo(() => data ?? [], [data])
  const items = useMemo(() => pages.flatMap((p) => p.data), [pages])
  const hasMore = pages.length > 0 ? getHasMore(pages, items.length) : false

  // size===1 の validation は1ページ目そのもの（初回/条件変更/再実行）、size>1 は append。
  const isLoadingMore = isValidating && size > 1
  const firstPageLoading = (isLoading || isValidating) && !isLoadingMore
  const phase: ListLoadPhase = firstPageLoading ? 'first' : isLoadingMore ? 'more' : 'idle'

  const [visibleCount, setVisibleCount] = useState(REVEAL_STEP)

  // listKey の「値の変化」だけで表示件数を先頭へ戻す（レンダー中の state 調整＝React 公式パターン。
  // キーが変わったその描画から新しい表示件数が反映される）。初回マウント時は現在値で初期化される
  // ため何もしない（マウント直後の余計なリセット＝二重フェッチの引き金を作らない）。
  // state は <Activity> の hide/show でも保持されるため、同一キーでの復帰では何も起きない。
  const [lastListKey, setLastListKey] = useState(listKey)
  if (lastListKey !== listKey) {
    setLastListKey(listKey)
    setVisibleCount(REVEAL_STEP)
  }

  // IntersectionObserver のコールバックから読む揮発値（observer は作り直さない）。
  // 毎レンダー後に最新値へ同期する（コールバックは非同期に発火するため十分間に合う）。
  const volatileRef = useRef({ visibleCount, loadedCount: 0, hasMore, fetching: false })
  const setSizeRef = useRef(setSize)
  useEffect(() => {
    volatileRef.current = {
      visibleCount,
      loadedCount: items.length,
      hasMore,
      // ネットワーク取得中は reveal も次ページ取得も発火させない（二重発火防止の単一ポリシー）
      fetching: isValidating || isLoading,
    }
    setSizeRef.current = setSize
  })

  const observerRef = useRef<IntersectionObserver | null>(null)
  const sentinelElRef = useRef<HTMLDivElement | null>(null)

  // 番兵到達時の処理: バッファに未表示分があれば即時 +30（ネットワーク無し）、
  // 使い切っていて hasMore なら次ページをネットワーク取得。全件表示済みなら何もしない。
  const handleReachEnd = useCallback(() => {
    const v = volatileRef.current
    if (v.fetching) return
    if (v.visibleCount < v.loadedCount) {
      setVisibleCount((prev) => prev + REVEAL_STEP)
    } else if (v.hasMore) {
      setSizeRef.current((s) => s + 1)
    }
  }, [])

  // observer の生成/破棄。<Activity> の hidden はエフェクトをクリーンアップするので、
  // visible 復帰時にここが再実行されて observer が復活する（番兵 ref は DOM 保持で生きている）。
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting) handleReachEnd()
      },
      { threshold: 0.1 },
    )
    observerRef.current = observer
    if (sentinelElRef.current) observer.observe(sentinelElRef.current)
    return () => {
      observer.disconnect()
      observerRef.current = null
    }
  }, [handleReachEnd])

  // 番兵の callback ref。要素の出現/消滅（hasMore 切替や条件付き描画）に追従する。
  const sentinelRef = useCallback((el: HTMLDivElement | null) => {
    if (sentinelElRef.current && observerRef.current) {
      observerRef.current.unobserve(sentinelElRef.current)
    }
    sentinelElRef.current = el
    if (el && observerRef.current) observerRef.current.observe(el)
  }, [])

  // reveal・ページ到着・取得状態の変化後にまだ番兵が見えていれば連鎖発火させる。
  // IntersectionObserver は交差状態が変わらないと再発火しないため、observe を張り替えて
  // 現在の交差状態をもう一度評価させる（viewport が埋まるまで自動で続く）。
  useEffect(() => {
    const el = sentinelElRef.current
    const observer = observerRef.current
    if (!el || !observer) return
    observer.unobserve(el)
    observer.observe(el)
  }, [visibleCount, items.length, hasMore, isValidating, isLoading])

  // mutate でキャッシュを破棄して再フェッチ（エラー時の手動再試行ボタンから呼ぶ）
  const retry = useCallback(() => { mutate() }, [mutate])

  return { pages, items, error, phase, hasMore, visibleCount, sentinelRef, mutate: retry }
}
