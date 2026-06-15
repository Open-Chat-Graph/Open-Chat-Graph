import { useCallback, useRef, useState } from 'react'
import { useSetAtom } from 'jotai'
import { rankingArgDto } from '../config/config'
import { updateURLSearchParams } from '../utils/utils'
import { analysisParamsState } from '../store/atom'
import { fetchApi, LIMIT_ITEMS } from './InfiniteFetchApi'

const base = rankingArgDto.baseUrl

// 1回のジョブで取得する最大件数（全件計算済みの結果から大量を一括取得し、以降はメモリ内描画）。
// これを超えて読む場合のみ次バッチを取得する（その時だけ再待機）。
const BATCH = 3000

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms))

const SORTS_BY_METRIC: Record<AnalysisMetric, AnalysisSort[]> = {
  increase: ['count', 'rate'],
  steady: ['score', 'cagr', 'slope'],
}

const pick = <T extends string>(v: string | null, allowed: T[], def: T): T =>
  allowed.includes(v as T) ? (v as T) : def

const isDate = (v: string) => /^\d{4}-\d{2}-\d{2}$/.test(v)

/** URLSearchParams → 妥当な AnalysisParams */
export function getValidAnalysisParams(p: URLSearchParams): AnalysisParams {
  const metric = pick<AnalysisMetric>(p.get('metric'), ['increase', 'steady'], 'increase')
  const period = pick<AnalysisPeriod>(p.get('period'), ['month', 'year', 'custom'], 'year')
  const from = isDate(p.get('from') ?? '') ? (p.get('from') as string) : ''
  const to = isDate(p.get('to') ?? '') ? (p.get('to') as string) : ''
  const category = Number(p.get('category')) || 0
  const keyword = (p.get('keyword') ?? '').slice(0, 100)
  const defaultSort = metric === 'steady' ? 'score' : 'count'
  const sort = pick<AnalysisSort>(p.get('sort'), SORTS_BY_METRIC[metric], defaultSort)
  const order = pick<AnalysisOrder>(p.get('order'), ['asc', 'desc'], 'desc')
  return { metric, period, from, to, category, keyword, sort, order }
}

/** ツールバーの入力を atom と URL(replaceState) に反映する */
export function useSetAnalysisParams(): (next: (cur: AnalysisParams) => AnalysisParams) => void {
  const setParams = useSetAtom(analysisParamsState)
  return useCallback(
    (next) => {
      setParams((cur) => {
        const np = next(cur)
        // metric を変えたら、その metric で無効な sort を既定へ補正
        const sort = SORTS_BY_METRIC[np.metric].includes(np.sort)
          ? np.sort
          : np.metric === 'steady'
          ? 'score'
          : 'count'
        const fixed: AnalysisParams = { ...np, sort }
        const q: { [k: string]: string } = {
          metric: fixed.metric,
          sort: fixed.sort,
          order: fixed.order,
        }
        if (fixed.metric === 'increase') q.period = fixed.period
        if (fixed.metric === 'increase' && fixed.period === 'custom') {
          q.from = fixed.from
          q.to = fixed.to
        }
        if (fixed.category) q.category = String(fixed.category)
        if (fixed.keyword) q.keyword = fixed.keyword
        window.history.replaceState(null, '', updateURLSearchParams(q).toString())
        return fixed
      })
    },
    [setParams]
  )
}

function statusQuery(p: AnalysisParams): string {
  const q = new URLSearchParams({ metric: p.metric })
  if (p.metric === 'increase') {
    q.set('period', p.period)
    if (p.period === 'custom') {
      if (p.from) q.set('from', p.from)
      if (p.to) q.set('to', p.to)
    }
  }
  return q.toString()
}

function resultQuery(p: AnalysisParams, page: number): string {
  const q = new URLSearchParams(statusQuery(p))
  q.set('sort', p.sort)
  q.set('order', p.order)
  if (p.category) q.set('category', String(p.category))
  if (p.keyword) q.set('keyword', p.keyword)
  q.set('page', String(page))
  q.set('limit', String(BATCH))
  return q.toString()
}

export interface AnalysisJob {
  phase: AnalysisJobPhase
  percent: number
  computed: number
  error: string | null
  items: AnalysisItem[] // 描画対象（renderCount でスライス済み）
  totalCount: number
  isLastPage: boolean
  /** 結果として表示中の指標（検索実行時に確定。フォームの metric とは別） */
  resultMetric: AnalysisMetric
  search: (params: AnalysisParams) => void
  cancel: () => void
  loadMore: () => void
  reset: () => void
}

/**
 * 重いジョブを逐次ポーリングして本物の%進捗を出し、完了後に大量バッチを取得して
 * クライアント側で無限スクロール描画する。検索のたびにトークンを更新し、古いループを無効化。
 */
export function useAnalysisJob(): AnalysisJob {
  const [phase, setPhase] = useState<AnalysisJobPhase>('idle')
  const [percent, setPercent] = useState(0)
  const [computed, setComputed] = useState(0)
  const [error, setError] = useState<string | null>(null)
  const [fetched, setFetched] = useState<AnalysisItem[]>([])
  const [totalCount, setTotalCount] = useState(0)
  const [renderCount, setRenderCount] = useState(LIMIT_ITEMS)
  const [resultMetric, setResultMetric] = useState<AnalysisMetric>('increase')

  const tokenRef = useRef(0)
  const paramsRef = useRef<AnalysisParams | null>(null)
  const loadingMoreRef = useRef(false)

  const search = useCallback((params: AnalysisParams) => {
    const token = ++tokenRef.current
    paramsRef.current = params
    setResultMetric(params.metric)
    setPhase('running')
    setPercent(0)
    setComputed(0)
    setError(null)
    setFetched([])
    setTotalCount(0)
    setRenderCount(LIMIT_ITEMS)

    ;(async () => {
      try {
        // 1) 重い計算を1チャンクずつ進め、本物の%を表示
        // eslint-disable-next-line no-constant-condition
        while (true) {
          if (token !== tokenRef.current) return
          const st = await fetchApi<AnalysisStatusResponse>(
            `${base}/analysis-status?${statusQuery(params)}`
          )
          if (token !== tokenRef.current) return
          setPercent(st.percent)
          setComputed(st.computed)
          if (st.done) break
          await sleep(250)
        }
        // 2) 完成結果から先頭バッチを一括取得
        const batch = await fetchApi<AnalysisItem[]>(`${base}/analysis-result?${resultQuery(params, 0)}`)
        if (token !== tokenRef.current) return
        const tc = batch.length ? batch[0].totalCount ?? batch.length : 0
        setFetched(batch)
        setTotalCount(tc)
        setRenderCount(LIMIT_ITEMS)
        setPhase('done')
      } catch (e) {
        if (token !== tokenRef.current) return
        setError(e instanceof Error ? e.message : 'error')
        setPhase('error')
      }
    })()
  }, [])

  const cancel = useCallback(() => {
    tokenRef.current++ // 走行中ループを無効化
    if (paramsRef.current) {
      // サーバ側の中間ファイルを掃除（失敗は無視）
      fetch(`${base}/analysis-cancel?${statusQuery(paramsRef.current)}`, {
        headers: { 'X-Ocg-Client': '1' },
      }).catch(() => {})
    }
    setPhase('idle')
    setPercent(0)
    setComputed(0)
  }, [])

  const reset = useCallback(() => {
    tokenRef.current++
    setPhase('idle')
    setPercent(0)
    setComputed(0)
    setError(null)
    setFetched([])
    setTotalCount(0)
  }, [])

  const loadMore = useCallback(() => {
    if (phase !== 'done') return
    // 取得済みの範囲内ならメモリ内でレンダリング窓を広げるだけ（再fetchしない）
    if (renderCount < fetched.length) {
      setRenderCount((c) => Math.min(c + LIMIT_ITEMS, fetched.length))
      return
    }
    // バッチを使い切り、まだ続きがあるなら次バッチを取得（このときだけ待機）
    if (fetched.length < totalCount && paramsRef.current && !loadingMoreRef.current) {
      loadingMoreRef.current = true
      const token = tokenRef.current
      const page = Math.floor(fetched.length / BATCH)
      fetchApi<AnalysisItem[]>(`${base}/analysis-result?${resultQuery(paramsRef.current, page)}`)
        .then((next) => {
          if (token !== tokenRef.current) return
          setFetched((prev) => [...prev, ...next])
          setRenderCount((c) => c + LIMIT_ITEMS)
        })
        .catch(() => {})
        .finally(() => {
          loadingMoreRef.current = false
        })
    }
  }, [phase, renderCount, fetched.length, totalCount])

  const items = fetched.slice(0, renderCount)
  const isLastPage = renderCount >= fetched.length && fetched.length >= totalCount

  return {
    phase,
    percent,
    computed,
    error,
    items,
    totalCount,
    isLastPage,
    resultMetric,
    search,
    cancel,
    loadMore,
    reset,
  }
}
