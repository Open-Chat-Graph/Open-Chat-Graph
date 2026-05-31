import { memo, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import useSWRInfinite from 'swr/infinite'
import { FlaskConical } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import {
  LabsControls,
  LabsRankingCard,
  LabsQuerySection,
  type LabsTab,
  type LabsEntity,
} from '@/components/Labs'
import type { LabsMetric } from '@/components/Labs/LabsControls'
import { ListProgressRegion, ListProgressFooter } from '@/components/Common/ListProgress'
import { ListScreen } from '@/components/Layout'
import { useListProgress } from '@/hooks/useListProgress'
import { useInfiniteReveal } from '@/hooks/useInfiniteReveal'
import { alphaApi } from '@/api/alpha'
import { DEFAULT_PERIOD, periodKey, periodToParams, type PeriodValue } from '@/lib/period'
import type { EtaListType } from '@/types/api'
import type {
  AccessRankingResponse,
  PageRankingResponse,
  SearchQueryRankingResponse,
  LabsRankingRoom,
  SearchQueryRankingItem,
  OpenChat,
} from '@/types/api'

const PAGE_SIZE = 300

// 任意のタブのページ応答（無限スクロールの1ページ分）。
type LabsPageResponse = AccessRankingResponse | PageRankingResponse | SearchQueryRankingResponse

// "2024-05-30 12:00:00" → "2024/05/30 12:00"
const formatUpdatedAt = (raw?: string | null): string => {
  if (!raw) return ''
  const m = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/)
  if (m) return `${m[1]}/${m[2]}/${m[3]} ${m[4]}:${m[5]}`
  const d = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})/)
  return d ? `${d[1]}/${d[2]}/${d[3]}` : String(raw)
}

// 部屋ランキングの1行を DetailPage が期待する OpenChat 形へ写像（即時表示用）。
const toOpenChat = (room: LabsRankingRoom): OpenChat => ({
  id: room.id,
  name: room.name,
  desc: room.desc,
  member: room.member,
  img: room.img,
  emblem: room.emblem,
  category: room.category,
  categoryName: room.categoryName,
  join_method_type: room.join_method_type,
  increasedMember: 0,
  percentageIncrease: 0,
  diff24h: 0,
  percent24h: 0,
  diff1w: 0,
  percent1w: 0,
  createdAt: room.createdAt ?? null,
  registeredAt: room.registeredAt,
  isInRanking: false,
  url: room.url,
})

const LabsPage = memo(() => {
  const navigate = useNavigate()

  // 状態は keep-alive パネルが保持するので、URL ではなくローカルで持つ。
  const [tab, setTab] = useState<LabsTab>('rooms')
  const [period, setPeriod] = useState<PeriodValue>(DEFAULT_PERIOD)
  const [category, setCategory] = useState(0)
  // 指標（アクセス数 pv / 検索流入 seo）。rooms/pages タブで共通。
  const [metric, setMetric] = useState<LabsMetric>('pv')
  // キーワード絞り込み（rooms タブのみ）。
  const [keyword, setKeyword] = useState('')
  const observerTarget = useRef<HTMLDivElement>(null!)

  // タブが変わったらキーワードをリセット。
  const handleTabChange = useCallback((t: LabsTab) => {
    setTab(t)
    setKeyword('')
  }, [])

  // タブ/期間/カテゴリ/metric/keyword が変わるたびページングを先頭へ戻して取り直す。
  const filterKey = `${tab}|${periodKey(period)}|${tab === 'rooms' ? category : 0}|${metric}|${tab === 'rooms' ? keyword : ''}`

  const getKey = useCallback(
    (pageIndex: number, prev: LabsPageResponse | null) => {
      if (prev && !prev.hasMore) return null
      return ['labs', filterKey, pageIndex] as const
    },
    [filterKey],
  )

  const fetcher = useCallback(
    async ([, , pageIndex]: readonly [string, string, number]): Promise<LabsPageResponse> => {
      const page = pageIndex + 1
      if (tab === 'keywords') {
        return alphaApi.getSearchQueryRanking({ period, page, limit: PAGE_SIZE })
      }
      if (tab === 'pages') {
        // pages タブは metric で叩くエンドポイントを切り替える。
        if (metric === 'seo') {
          return alphaApi.getSearchRanking({ period, page, limit: PAGE_SIZE, scope: 'pages', order: 'desc' })
        }
        // jump＝入室数（近似）も pages では access-ranking を sort で切り替えて取得。
        if (metric === 'jump') {
          return alphaApi.getAccessRanking({ period, page, limit: PAGE_SIZE, scope: 'pages', order: 'desc', sort: 'jump_clicks' })
        }
        return alphaApi.getAccessRanking({ period, page, limit: PAGE_SIZE, scope: 'pages', order: 'desc' })
      }
      // rooms タブは metric で叩くエンドポイントを切り替え、keyword も渡す。
      // メソッドを変数に取り出すと this が外れる（_rankingQuery 参照で失敗）ので必ず alphaApi 経由で呼ぶ。
      if (metric === 'seo') {
        return alphaApi.getSearchRanking({ period, category, page, limit: PAGE_SIZE, order: 'desc', keyword: keyword || undefined })
      }
      // jump＝入室数（参加リンク押下）降順は access-ranking を sort で切り替えて取得。
      if (metric === 'jump') {
        return alphaApi.getAccessRanking({ period, category, page, limit: PAGE_SIZE, order: 'desc', keyword: keyword || undefined, sort: 'jump_clicks' })
      }
      return alphaApi.getAccessRanking({ period, category, page, limit: PAGE_SIZE, order: 'desc', keyword: keyword || undefined })
    },
    [tab, period, category, metric, keyword],
  )

  const { data, error, isLoading, isValidating, size, setSize } = useSWRInfinite<LabsPageResponse>(
    getKey,
    fetcher,
    { revalidateFirstPage: false, revalidateOnFocus: false, revalidateOnReconnect: false, dedupingInterval: 60000 },
  )

  // フィルタが変わったら先頭ページへ。
  useEffect(() => {
    setSize(1)
  }, [filterKey, setSize])

  const pages = useMemo(() => data ?? [], [data])
  const lastPage = pages[pages.length - 1]
  const hasMore = lastPage?.hasMore ?? false
  const updatedAt = pages[0]?.updatedAt ?? null
  const isLoadingMore = isValidating && size > 1
  const isEmpty = !isLoading && pages.length > 0 && (pages[0]?.data.length ?? 0) === 0

  // ETAプログレス: filterKey（タブ/期間/カテゴリ/指標/キーワード）が変わるたびに ETA を取り直す。
  // 初回ロードは上部バー、結果表示中のフィルタ変更はオーバーレイへ振り分ける（append は除外）。
  const firstPageLoading = (isLoading || isValidating) && !isLoadingMore
  const { progress, active: progressActive } = useListProgress({
    requestKey: filterKey,
    loading: firstPageLoading,
    fetchEta: async () => {
      // タブ＋指標で叩く ETA の種別を決める（fetcher と同じ対応）。
      let type: EtaListType
      if (tab === 'keywords') type = 'search-query-ranking'
      else type = metric === 'seo' ? 'search-ranking' : 'access-ranking'
      const periodParams = periodToParams(period)
      const scope = tab === 'pages' ? 'pages' : 'rooms'
      return (
        await alphaApi.getEta({
          type,
          order: 'desc',
          scope,
          // jump＝入室数は access-ranking を sort=jump_clicks で叩く。record 側キーと一致させる。
          sort: metric === 'jump' ? 'jump_clicks' : undefined,
          category: tab === 'rooms' ? category || undefined : undefined,
          keyword: tab === 'rooms' ? keyword || undefined : undefined,
          days: periodParams.days ? Number(periodParams.days) : undefined,
          start: periodParams.start,
          end: periodParams.end,
          all: periodParams.all === '1',
        })
      ).etaMs
    },
  })
  const hasResults = pages.length > 0 && (pages[0]?.data.length ?? 0) > 0

  // 全ページのデータを結合（タブごとに型が違うので必要箇所でキャストして読む）。
  const rooms = useMemo<LabsRankingRoom[]>(() => {
    if (tab !== 'rooms') return []
    return pages.flatMap((p) => (p as AccessRankingResponse).data)
  }, [pages, tab])

  const pageRows = useMemo(() => {
    if (tab !== 'pages') return []
    return pages.flatMap((p) => (p as PageRankingResponse).data)
  }, [pages, tab])

  const queries = useMemo<SearchQueryRankingItem[]>(() => {
    if (tab !== 'keywords') return []
    return pages.flatMap((p) => (p as SearchQueryRankingResponse).data)
  }, [pages, tab])

  // 統合エンティティ（カード描画用）。部屋／ページを LabsEntity に束ねる。
  // ※ useInfiniteReveal の loadedCount 計算に必要なため observer より先に定義する。
  const primary = metric === 'seo' ? 'seo' : metric === 'jump' ? 'jump' : 'pv'

  const entities = useMemo<LabsEntity[]>(() => {
    if (tab === 'pages') return pageRows.map((page) => ({ kind: 'page', page }))
    return rooms.map((room) => ({ kind: 'room', room }))
  }, [tab, rooms, pageRows])

  // 取得1000件・表示30件ずつのウィンドウィング。
  const { visibleCount, onReachEnd } = useInfiniteReveal({
    loadedCount: tab === 'keywords' ? queries.length : entities.length,
    hasMore,
    setSize,
    resetKey: filterKey,
  })

  // 無限スクロール（バッファ即時 reveal or 次の1000件取得）
  useEffect(() => {
    const el = observerTarget.current
    if (!el) return
    const observer = new IntersectionObserver(
      (entries) => {
        // isValidating 中（ネットワーク取得中）は二重発火を防ぐ
        if (entries[0].isIntersecting && !isValidating) {
          onReachEnd()
        }
      },
      { threshold: 0.1 },
    )
    observer.observe(el)
    return () => observer.unobserve(el)
  }, [onReachEnd, isValidating])

  const handleCardClick = useCallback(
    (id: number) => {
      const room = rooms.find((r) => r.id === id)
      navigate(`/openchat/${id}`, { state: room ? { initialData: toOpenChat(room) } : undefined })
    },
    [navigate, rooms],
  )

  return (
    <ListScreen
      scrollResetKey={tab}
      header={
        /* 検索条件ヘッダ（タブ＋期間＋指標＋カテゴリ＋キーワード）。骨格は ListScreen が固定する。 */
        <LabsControls
          tab={tab}
          period={period}
          category={category}
          metric={metric}
          keyword={keyword}
          onTabChange={handleTabChange}
          onPeriodChange={setPeriod}
          onCategoryChange={setCategory}
          onMetricChange={setMetric}
          onKeywordChange={setKeyword}
        />
      }
    >
      <div className="space-y-4">
        {error && (
          <Card className="border-destructive">
            <CardContent className="pt-6">
              <p className="text-sm text-destructive">データの取得に失敗しました</p>
            </CardContent>
          </Card>
        )}

        {!error && isEmpty && (
          <Card>
            <CardContent className="py-12 text-center">
              <FlaskConical className="mx-auto h-12 w-12 text-muted-foreground/40" />
              <p className="mt-4 text-sm text-muted-foreground">
                該当データがありません（集計待ち or 条件に一致なし）
              </p>
              <p className="mt-1 text-xs text-muted-foreground/80">
                GA連携後に日次で集計されます。期間やカテゴリを広げてみてください。
              </p>
            </CardContent>
          </Card>
        )}

        {!error && !isEmpty && (
          <ListProgressRegion progress={progress} active={progressActive} hasResults={hasResults}>
            {tab === 'keywords' ? (
              <LabsQuerySection queries={queries.slice(0, visibleCount)} />
            ) : (
              <div className="grid gap-2 md:gap-3">
                {entities.slice(0, visibleCount).map((entity, index) => (
                  <LabsRankingCard
                    key={entity.kind === 'room' ? `r${entity.room.id}` : `p${entity.page.path}`}
                    entity={entity}
                    rank={index + 1}
                    primary={primary}
                    onRoomClick={handleCardClick}
                  />
                ))}
              </div>
            )}

            {/* 無限スクロールの番兵＋ローディング（初回・再取得と同じ ListProgressBar に統一） */}
            <ListProgressFooter isLoading={isLoadingMore} hasMore={hasMore} observerRef={observerTarget} />

            {updatedAt && (
              <p className="pt-1 text-center text-[11px] tabular-nums text-muted-foreground/70">
                最終更新 {formatUpdatedAt(updatedAt)}
              </p>
            )}
          </ListProgressRegion>
        )}
      </div>
    </ListScreen>
  )
})

LabsPage.displayName = 'LabsPage'

export default LabsPage
