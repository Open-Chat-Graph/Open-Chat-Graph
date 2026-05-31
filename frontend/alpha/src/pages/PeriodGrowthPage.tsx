import { memo, useCallback, useEffect, useMemo, useRef } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import useSWRInfinite from 'swr/infinite'
import { CalendarRange, Info } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { PeriodGrowthCard, PeriodGrowthControls, type PeriodOrder, type PeriodGrowthQuery } from '@/components/PeriodGrowth'
import { ListProgressRegion, ListProgressFooter } from '@/components/Common/ListProgress'
import { useListProgress } from '@/hooks/useListProgress'
import { alphaApi } from '@/api/alpha'
import { categoryName } from '@/lib/categories'
import { periodKey, periodToParams, type PeriodValue } from '@/lib/period'
import type { PeriodGrowthItem, PeriodGrowthResponse, OpenChat } from '@/types/api'

const LIMIT = 30
const DEFAULT_DAYS = 30

// "2024-05-30 12:00:00" → "2024/05/30"
const formatDate = (raw?: string | null): string => {
  if (!raw) return ''
  const m = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})/)
  return m ? `${m[1]}/${m[2]}/${m[3]}` : String(raw)
}

// PeriodGrowthItem を DetailPage が期待する OpenChat 形へ写像（即時表示用の初期データ）。
// 期間差分(diff/percent)は OpenChat に対応フィールドが無いため持ち越さない（詳細側で再取得される）。
const toOpenChat = (item: PeriodGrowthItem): OpenChat => ({
  id: item.id,
  name: item.name,
  desc: item.desc,
  member: item.member,
  img: item.img,
  emblem: item.emblem,
  category: item.category,
  categoryName: item.categoryName,
  join_method_type: item.join_method_type,
  increasedMember: 0,
  percentageIncrease: 0,
  diff24h: 0,
  percent24h: 0,
  diff1w: 0,
  percent1w: 0,
  createdAt: item.createdAt ?? null,
  registeredAt: item.registeredAt,
  isInRanking: false,
  url: item.url,
})

/** URLSearchParams から PeriodValue を復元する。既定は30日。 */
function parsePeriodFromParams(params: URLSearchParams): PeriodValue {
  const start = params.get('start') || ''
  const end = params.get('end') || ''
  if (start && end) return { mode: 'range', start, end }
  const days = Number(params.get('days')) || DEFAULT_DAYS
  return { mode: 'days', days }
}

const PeriodGrowthPage = memo(() => {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const observerTarget = useRef<HTMLDivElement>(null!)

  const keyword = searchParams.get('q') || ''
  const category = Number(searchParams.get('category')) || 0
  const order = (searchParams.get('order') as PeriodOrder) || 'desc'
  const period = parsePeriodFromParams(searchParams)

  // 検索を実行したか。キーワードは任意（空欄＝全件）なので、キーワード有無では判定できない。
  // 検索画面からの遷移(q付き)か、このページで「検索」を押した(go=1)ときに結果を出す。
  const searched = keyword !== '' || searchParams.has('go')

  // 条件（キーワード/カテゴリ/期間/並び）をまとめた識別キー。変化で先頭ページへ戻す。
  const filterKey = searched
    ? `${keyword}|${category}|${periodKey(period)}|${order}`
    : null

  // useSWRInfinite でページングを管理（Labs/Search と同パターン）。
  const getKey = useCallback(
    (pageIndex: number, prev: PeriodGrowthResponse | null) => {
      if (!searched) return null
      if (prev && !prev.hasMore) return null
      return ['period-growth', filterKey, pageIndex] as const
    },
    [searched, filterKey],
  )

  const fetcher = useCallback(
    async ([, , pageIndex]: readonly [string, string | null, number]): Promise<PeriodGrowthResponse> => {
      const page = pageIndex + 1
      const periodParams = periodToParams(period)
      if (period.mode === 'range') {
        return alphaApi.getPeriodGrowth({
          keyword,
          category: category || undefined,
          startDate: periodParams.start,
          endDate: periodParams.end,
          order,
          limit: LIMIT,
          page,
        })
      }
      return alphaApi.getPeriodGrowth({
        keyword,
        category: category || undefined,
        days: Number(periodParams.days),
        order,
        limit: LIMIT,
        page,
      })
    },
    [period, keyword, category, order],
  )

  const { data, error, isLoading, isValidating, size, setSize } = useSWRInfinite<PeriodGrowthResponse>(
    getKey,
    fetcher,
    {
      revalidateFirstPage: false,
      revalidateOnFocus: false,
      revalidateOnReconnect: false,
      dedupingInterval: 60000,
    },
  )

  // 条件が変わったら先頭ページへ戻す。
  useEffect(() => {
    setSize(1)
  }, [filterKey, setSize])

  const pages = useMemo(() => data ?? [], [data])
  const items = useMemo(() => pages.flatMap((p) => p.data), [pages])
  const lastPage = pages[pages.length - 1]
  const hasMore = lastPage?.hasMore ?? false
  const firstPage = pages[0]
  const isLoadingMore = isValidating && size > 1
  const hasResults = items.length > 0

  // ETAプログレス: 条件が変わるたびに ETA を取り直し 0→90%、応答到着で 100%。
  // append（size>1）は除外。初回＝上部バー／結果表示中の再取得＝重ねバー。
  const firstPageLoading = searched && (isLoading || isValidating) && !isLoadingMore
  const { progress, active: progressActive } = useListProgress({
    requestKey: filterKey,
    loading: firstPageLoading,
    fetchEta: searched
      ? async () => {
          const periodParams = periodToParams(period)
          const etaParams =
            period.mode === 'range'
              ? { type: 'period-growth' as const, keyword, category: category || undefined, startDate: periodParams.start, endDate: periodParams.end, order }
              : { type: 'period-growth' as const, keyword, category: category || undefined, days: Number(periodParams.days), order }
          return (await alphaApi.getEta(etaParams)).etaMs
        }
      : undefined,
  })

  // 無限スクロール
  useEffect(() => {
    const el = observerTarget.current
    if (!el) return
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && hasMore && !isValidating) {
          setSize((s) => s + 1)
        }
      },
      { threshold: 0.1 },
    )
    observer.observe(el)
    return () => observer.unobserve(el)
  }, [hasMore, isValidating, setSize])

  const handleSubmit = useCallback(
    (next: PeriodGrowthQuery) => {
      const params = new URLSearchParams()
      if (next.keyword) params.set('q', next.keyword)
      if (next.category) params.set('category', String(next.category))
      if (next.order) params.set('order', next.order)
      // 期間を URL パラメータに変換
      const periodParams = periodToParams(next.period)
      if (next.period.mode === 'range') {
        params.set('start', periodParams.start)
        params.set('end', periodParams.end)
      } else {
        params.set('days', periodParams.days)
      }
      // キーワード空でも検索実行を表す。これがあると結果を表示する。
      params.set('go', '1')
      setSearchParams(params)
    },
    [setSearchParams],
  )

  const handleCardClick = useCallback(
    (id: number) => {
      const item = items.find((i) => i.id === id)
      navigate(`/openchat/${id}`, {
        state: item ? { initialData: toOpenChat(item) } : undefined,
      })
    },
    [navigate, items]
  )

  return (
    <div className="space-y-4">
      {/* 見出し＋戻るは固定タイトルバー（DashboardLayout）が担うので、ここでは説明のみ。 */}
      <p className="text-sm text-muted-foreground">
        指定した期間の前後どちらにもデータがある部屋だけを並べます。
      </p>

      <PeriodGrowthControls
        keyword={keyword}
        category={category}
        period={period}
        order={order}
        onSubmit={handleSubmit}
      />

      {/* 未検索時の案内（キーワードは任意・空欄で全件） */}
      {!searched && (
        <Card>
          <CardContent className="py-12 text-center">
            <CalendarRange className="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p className="mt-4 text-sm text-muted-foreground">
              期間とカテゴリを選んで「検索」を押してください
            </p>
            <p className="mt-1 text-xs text-muted-foreground/80">
              キーワードは任意。空欄なら全部屋から、その期間に伸びた部屋を一覧します
            </p>
          </CardContent>
        </Card>
      )}

      {searched && error && (
        <Card className="border-destructive">
          <CardContent className="pt-6">
            <p className="text-sm text-destructive">データの取得に失敗しました</p>
          </CardContent>
        </Card>
      )}

      {/* プログレス（初回＝上部バー／再取得＝重ねバー／追加読み込み＝末尾バー）。検索・Labs と同一実装。 */}
      <ListProgressRegion progress={progress} active={progressActive} hasResults={hasResults}>
      {searched && firstPage && (
        <div className="space-y-4">
          {/* サマリ（ラベル付き・数値は tabular-nums で軽く構造化） */}
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
            <span>
              <span className="text-xs text-muted-foreground/80">対象</span>{' '}
              <span className="font-semibold text-foreground">{keyword || '全部屋'}</span>
              {category > 0 && (
                <span className="text-foreground">（{categoryName(category)}）</span>
              )}
            </span>
            <span aria-hidden className="opacity-40">|</span>
            <span>
              <span className="text-xs text-muted-foreground/80">期間</span>{' '}
              <span className="tabular-nums text-foreground">
                {formatDate(firstPage.targetPastDate)} 〜 {formatDate(firstPage.baseDate)}
              </span>
              <span className="tabular-nums">（{firstPage.days.toLocaleString()}日）</span>
            </span>
            <span aria-hidden className="opacity-40">|</span>
            <span>
              <span className="text-xs text-muted-foreground/80">該当</span>{' '}
              <span className="tabular-nums text-foreground">{firstPage.totalMatched.toLocaleString()}</span>件
            </span>
          </div>

          {items.length === 0 ? (
            <Card>
              <CardContent className="py-10 text-center">
                <Info className="mx-auto h-8 w-8 text-muted-foreground/50" />
                <p className="mt-3 text-sm text-muted-foreground">
                  条件に合う部屋がありません
                </p>
                <p className="mt-1 text-xs text-muted-foreground/80">
                  指定した期間の開始時点にデータがある部屋が見つかりませんでした。期間を短くするとヒットしやすくなります。
                </p>
              </CardContent>
            </Card>
          ) : (
            <>
              <div className="grid gap-2 md:gap-3">
                {items.map((item, index) => (
                  <PeriodGrowthCard
                    key={item.id}
                    item={item}
                    rank={index + 1}
                    onCardClick={handleCardClick}
                  />
                ))}
              </div>

              {/* 追加読み込み（無限スクロール）。初回・再取得と同じ ListProgressBar に統一。 */}
              <ListProgressFooter isLoading={isLoadingMore} hasMore={hasMore} observerRef={observerTarget} />
            </>
          )}
        </div>
      )}
      </ListProgressRegion>
    </div>
  )
})

PeriodGrowthPage.displayName = 'PeriodGrowthPage'

export default PeriodGrowthPage
