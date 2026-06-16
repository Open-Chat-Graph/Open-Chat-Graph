import { useCallback, useEffect, useRef, useState } from 'react'
import { useSetAtom } from 'jotai'
import { rankingArgDto } from '../config/config'
import { updateURLSearchParams } from '../utils/utils'
import { analysisParamsState } from '../store/atom'
import { LIMIT_ITEMS } from './InfiniteFetchApi'

const base = rankingArgDto.baseUrl

// 1リクエストで取得する件数。サーバは保存しないので、これを超える分は再取得（再計算）になる。
const BATCH = 3000

const SORTS_BY_METRIC: Record<AnalysisMetric, AnalysisSort[]> = {
  increase: ['count', 'rate'],
  steady: ['score'],
}
const DEFAULT_SORT: Record<AnalysisMetric, AnalysisSort> = { increase: 'count', steady: 'score' }

// 期間タブは両指標で共通（同じ窓で比較）。既定だけ指標ごとに変える。
export const PERIODS: AnalysisPeriod[] = ['3month', '6month', 'year', 'all', 'custom']
const DEFAULT_PERIOD: Record<AnalysisMetric, AnalysisPeriod> = { increase: 'year', steady: '3month' }

const pick = <T extends string>(v: string | null, allowed: T[], def: T): T =>
  allowed.includes(v as T) ? (v as T) : def

const isDate = (v: string) => /^\d{4}-\d{2}-\d{2}$/.test(v)

/** URLSearchParams → 妥当な AnalysisParams */
export function getValidAnalysisParams(p: URLSearchParams): AnalysisParams {
  const metric = pick<AnalysisMetric>(p.get('metric'), ['increase', 'steady'], 'increase')
  const period = pick<AnalysisPeriod>(p.get('period'), PERIODS, DEFAULT_PERIOD[metric])
  const from = isDate(p.get('from') ?? '') ? (p.get('from') as string) : ''
  const to = isDate(p.get('to') ?? '') ? (p.get('to') as string) : ''
  const category = Number(p.get('category')) || 0
  const keyword = (p.get('keyword') ?? '').slice(0, 100)
  const sort = pick<AnalysisSort>(p.get('sort'), SORTS_BY_METRIC[metric], DEFAULT_SORT[metric])
  // じわじわ成長はスコア降順のみ
  const order: AnalysisOrder = metric === 'steady' ? 'desc' : pick<AnalysisOrder>(p.get('order'), ['asc', 'desc'], 'desc')
  return { metric, period, from, to, category, keyword, sort, order }
}

/** ツールバーの入力を atom と URL(replaceState) に反映する */
export function useSetAnalysisParams(): (next: (cur: AnalysisParams) => AnalysisParams) => void {
  const setParams = useSetAtom(analysisParamsState)
  return useCallback(
    (next) => {
      setParams((cur) => {
        const np = next(cur)
        // metric を変えたら sort をその指標の既定へ補正（period は共通なので維持）
        const sort =
          np.metric !== cur.metric || !SORTS_BY_METRIC[np.metric].includes(np.sort)
            ? DEFAULT_SORT[np.metric]
            : np.sort
        const order: AnalysisOrder = np.metric === 'steady' ? 'desc' : np.order
        const fixed: AnalysisParams = { ...np, sort, order }
        const q: { [k: string]: string } = {
          metric: fixed.metric,
          period: fixed.period,
          sort: fixed.sort,
          order: fixed.order,
        }
        if (fixed.period === 'custom') {
          if (fixed.from) q.from = fixed.from
          if (fixed.to) q.to = fixed.to
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

function resultUrl(p: AnalysisParams, page: number): string {
  const q = new URLSearchParams({ metric: p.metric, period: p.period, sort: p.sort, order: p.order })
  if (p.period === 'custom') {
    if (p.from) q.set('from', p.from)
    if (p.to) q.set('to', p.to)
  }
  if (p.category) q.set('category', String(p.category))
  if (p.keyword) q.set('keyword', p.keyword)
  q.set('page', String(page))
  q.set('limit', String(BATCH))
  return `${base}/analysis-result?${q.toString()}`
}

function statusUrl(p: AnalysisParams): string {
  const q = new URLSearchParams({ metric: p.metric, period: p.period })
  if (p.period === 'custom') {
    if (p.from) q.set('from', p.from)
    if (p.to) q.set('to', p.to)
  }
  return `${base}/analysis-status?${q.toString()}`
}

async function fetchJson<T>(url: string, signal: AbortSignal): Promise<T> {
  const res = await fetch(url, { headers: { 'X-Ocg-Client': '1' }, signal })
  if (!res.ok) {
    throw new Error('http ' + res.status)
  }
  return res.json()
}

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms))

interface StatusResponse {
  done: boolean
  percent: number
  computed: number
}

export interface AnalysisJob {
  phase: AnalysisJobPhase
  percent: number
  computed: number
  elapsed: number
  items: AnalysisItem[] // 描画対象（renderCount でスライス済み）
  totalCount: number
  isLastPage: boolean
  resultMetric: AnalysisMetric
  /** 検索時に指定したカテゴリ（0=全カテゴリ）。各行のカテゴリ表示の出し分けに使う */
  resultCategory: number
  search: (params: AnalysisParams) => void
  cancel: () => void
  loadMore: () => void
}

/**
 * シンプルなポーリングで重い集計の進捗(%)を出しつつ、完了後に結果バッチを取得して
 * クライアント側で無限スクロール描画する。キャンセルは AbortController で中断。
 */
export function useAnalysisJob(): AnalysisJob {
  const [phase, setPhase] = useState<AnalysisJobPhase>('idle')
  const [percent, setPercent] = useState(0)
  const [computed, setComputed] = useState(0)
  const [elapsed, setElapsed] = useState(0)
  const [fetched, setFetched] = useState<AnalysisItem[]>([])
  const [totalCount, setTotalCount] = useState(0)
  const [renderCount, setRenderCount] = useState(LIMIT_ITEMS)
  const [resultMetric, setResultMetric] = useState<AnalysisMetric>('increase')
  const [resultCategory, setResultCategory] = useState<number>(0)

  const abortRef = useRef<AbortController | null>(null)
  const paramsRef = useRef<AnalysisParams | null>(null)
  const loadingMoreRef = useRef(false)

  // 計算中の経過秒
  useEffect(() => {
    if (phase !== 'loading') {
      return
    }
    const tmr = setInterval(() => setElapsed((s) => s + 1), 1000)
    return () => clearInterval(tmr)
  }, [phase])

  const search = useCallback((params: AnalysisParams) => {
    abortRef.current?.abort()
    const ac = new AbortController()
    abortRef.current = ac
    paramsRef.current = params
    setResultMetric(params.metric)
    setResultCategory(params.category)
    setPhase('loading')
    setPercent(0)
    setComputed(0)
    setElapsed(0)
    setFetched([])
    setTotalCount(0)
    setRenderCount(LIMIT_ITEMS)

    ;(async () => {
      try {
        // 1) 進捗ポーリング（1回1チャンク）。待ち時間の目安を % で表示
        // eslint-disable-next-line no-constant-condition
        while (true) {
          if (ac.signal.aborted) return
          const st = await fetchJson<StatusResponse>(statusUrl(params), ac.signal)
          if (ac.signal.aborted) return
          setPercent(st.percent)
          setComputed(st.computed)
          if (st.done) break
          await sleep(300)
        }
        // 2) 完成結果から先頭バッチを取得
        const batch = await fetchJson<AnalysisItem[]>(resultUrl(params, 0), ac.signal)
        if (ac.signal.aborted) return
        setFetched(batch)
        setTotalCount(batch.length ? batch[0].totalCount ?? batch.length : 0)
        setRenderCount(LIMIT_ITEMS)
        setPhase('done')
      } catch {
        if (ac.signal.aborted) return
        setPhase('error')
      }
    })()
  }, [])

  const cancel = useCallback(() => {
    abortRef.current?.abort()
    setPhase('idle')
  }, [])

  const loadMore = useCallback(() => {
    if (phase !== 'done') return
    // 取得済みの範囲内ならメモリ内でレンダリング窓を広げるだけ
    if (renderCount < fetched.length) {
      setRenderCount((c) => Math.min(c + LIMIT_ITEMS, fetched.length))
      return
    }
    // バッチを使い切り、まだ続きがあるなら次バッチを取得（その場再計算）
    if (fetched.length < totalCount && paramsRef.current && !loadingMoreRef.current) {
      loadingMoreRef.current = true
      const ac = new AbortController()
      const page = Math.floor(fetched.length / BATCH)
      fetchJson<AnalysisItem[]>(resultUrl(paramsRef.current, page), ac.signal)
        .then((next) => {
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

  return { phase, percent, computed, elapsed, items, totalCount, isLastPage, resultMetric, resultCategory, search, cancel, loadMore }
}
