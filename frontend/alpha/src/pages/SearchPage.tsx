import { useState, useEffect, useRef, memo, useCallback, useMemo } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import useSWRInfinite from 'swr/infinite'
import { Search, TrendingUp } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { FolderSelectDialog } from '@/components/ui/folder-select-dialog'
import { OpenChatCard, InfiniteScrollLoader } from '@/components/OpenChat'
import { WatchKeywordButton } from '@/components/Notifications'
import { SearchProgressBar, SearchRefetchOverlay, useSearchProgress } from '@/components/Search'
import { alphaApi } from '@/api/alpha'
import { loadMyList, addItem, isInMyList } from '@/services/storage'
import type { SearchResponse, SearchEtaParams } from '@/types/api'
import type { SortType, SortOrder } from '@/lib/sort-options'
import { STORAGE_KEYS } from '@/lib/storage-keys'
import { useLayout } from '@/contexts/layout-context'

const LIMIT = 20

const SearchPage = memo(() => {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const observerTarget = useRef<HTMLDivElement>(null!)

  const urlKeyword = searchParams.get('q') || ''
  const sort = (searchParams.get('sort') as SortType) || 'member'
  const order = (searchParams.get('order') as SortOrder) || 'desc'
  const category = Number(searchParams.get('category')) || 0
  const [myListData, setMyListData] = useState(() => loadMyList())
  const [folderSelectOpen, setFolderSelectOpen] = useState(false)
  const [selectedChatId, setSelectedChatId] = useState<number | null>(null)
  // 検索バー再実行シグナル（同じキーワードでも SWR キーを変えて再フェッチ）
  const { searchNonce, bumpReset } = useLayout()

  // useSWRInfinite でページングを管理
  const getKey = useCallback(
    (pageIndex: number, previousPageData: SearchResponse | null) => {
      // キーワードがない場合はnullを返してフェッチしない
      if (!urlKeyword) return null

      // 前のページデータがあり、それが最後のページなら nullを返す
      if (previousPageData && previousPageData.data.length === 0) return null

      // SWRのキーを返す（sort, order, category, searchNonce を含めて再実行に対応）
      // pageIndexは0始まりなので、そのまま渡す
      return ['search', urlKeyword, sort, order, category, pageIndex, LIMIT, searchNonce]
    },
    [urlKeyword, sort, order, category, searchNonce]
  )

  const {
    data,
    error,
    size,
    setSize,
    isLoading,
    isValidating,
  } = useSWRInfinite<SearchResponse>(
    getKey,
    ([, keyword, sortType, sortOrder, cat, page, limit]) =>
      alphaApi.search({ keyword, category: cat as number, page: page as number, limit: limit as number, sort: sortType as SortType, order: sortOrder as SortOrder }),
    {
      revalidateFirstPage: false,
      revalidateOnFocus: false,
      revalidateOnReconnect: false,
      dedupingInterval: 60000,
    }
  )

  // 全ページのデータを結合
  const results = useMemo(() => {
    return data ? data.flatMap(page => page.data) : []
  }, [data])

  const totalCount = data?.[0]?.totalCount || 0
  const hasMore = results.length < totalCount

  // 検索プログレス: SWR の getKey と同じ識別（pageIndex を除く）を1キーにまとめる。
  // これが変わるたびに ETA を取り直し、0→90% のアニメを仕切り直す。
  const searchKey = urlKeyword ? `${urlKeyword}|${sort}|${order}|${category}|${searchNonce}` : null
  const etaParams = useMemo<SearchEtaParams | null>(
    () =>
      urlKeyword
        ? { keyword: urlKeyword, category: category || undefined, sort, order }
        : null,
    [urlKeyword, category, sort, order],
  )

  // 検索キー（キーワード/ソート/カテゴリ/再実行）が変わったらページングを先頭へ戻す。
  // これで「再検索＝size===1 の validation」「追加読み込み＝size>1 の validation」と素直に分けられ、
  // 再検索のたびにスクロール位置・ページ数を持ち越さない（UX的にも先頭から見せたい）。
  useEffect(() => {
    setSize(1)
  }, [searchKey, setSize])

  // size===1 の validation は検索そのもの（初回/再検索）、size>1 は追加読み込み（append）。
  const isLoadingMore = isValidating && size > 1
  // 1ページ目（=検索そのもの）の応答待ちか。append は除外する。
  // 初回ロードは上部バー、結果が見えている再検索はオーバーレイに振り分ける。
  const firstPageLoading = (isLoading || isValidating) && !isLoadingMore
  const { progress, active: progressActive } = useSearchProgress({
    searchKey,
    loading: firstPageLoading,
    etaParams,
  })
  const hasResults = results.length > 0
  // 初回（リスト未表示）の応答待ち → 上部プログレスバー
  const showTopBar = progressActive && !hasResults
  // 既存リスト表示中の再検索 → 薄いレイヤー＋スピナー
  const showRefetchOverlay = progressActive && hasResults

  // Intersection Observer for infinite scroll
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        const first = entries[0]
        if (first.isIntersecting && hasMore && !isLoadingMore && !isLoading) {
          setSize(prev => prev + 1)
        }
      },
      { threshold: 0.1 }
    )

    const currentTarget = observerTarget.current
    if (currentTarget) {
      observer.observe(currentTarget)
    }

    return () => {
      if (currentTarget) {
        observer.unobserve(currentTarget)
      }
    }
  }, [hasMore, isLoadingMore, isLoading, setSize])

  const handleCardClick = useCallback((chatId: number) => {
    // 検索クエリ（キーワード + ソート設定）をsessionStorageに保存してから詳細ページに遷移
    if (urlKeyword) {
      sessionStorage.setItem(STORAGE_KEYS.searchQuery, searchParams.toString())
    }

    // リストから該当するデータを見つけてstateで渡す（DetailPageでのフェッチを避ける）
    const chatData = results.find(chat => chat.id === chatId)
    navigate(`/openchat/${chatId}`, {
      state: chatData ? { initialData: chatData } : undefined
    })
  }, [navigate, urlKeyword, searchParams, results])

  const handleAddToMyList = useCallback((chatId: number, event: React.MouseEvent) => {
    event.stopPropagation()
    setSelectedChatId(chatId)
    setFolderSelectOpen(true)
  }, [])

  const handleFolderSelect = useCallback((folderId: string | null) => {
    if (selectedChatId) {
      const updated = addItem(myListData, selectedChatId, folderId)
      setMyListData(updated)
    }
    setFolderSelectOpen(false)
    setSelectedChatId(null)
  }, [selectedChatId, myListData])

  // 検索→期間分析の橋渡し。今のキーワード（＋カテゴリ）を引き継いで期間の伸びで並べ直す。
  // 画面表示状態カーネルの reset 契約で period-growth パネルを再マウント＋トップへ
  // （前回の結果・スクロール位置を持ち越さない）。
  const handleViewPeriodGrowth = useCallback(() => {
    const params = new URLSearchParams()
    params.set('q', urlKeyword)
    if (category) params.set('category', String(category))
    bumpReset('period-growth')
    navigate(`/period-growth?${params.toString()}`)
  }, [navigate, urlKeyword, category, bumpReset])

  return (
    <div className="space-y-6">
      {/* 初回ロード時の上部プログレスバー（ETA時間で 0→約90%、応答到着で 100%）。
          結果が出たら畳まれ、以降の再検索はオーバーレイ側に切り替わる。 */}
      {showTopBar && (
        <div className="pt-1">
          <SearchProgressBar progress={progress} active={showTopBar} />
          <p className="mt-2 text-center text-xs text-muted-foreground">検索中…</p>
        </div>
      )}

      {error && (
        <Card className="border-destructive">
          <CardContent className="pt-6">
            <p className="text-sm text-destructive">データの取得に失敗しました</p>
          </CardContent>
        </Card>
      )}

      {urlKeyword && !isLoading && !progressActive && results.length === 0 && (
        <Card>
          <CardContent className="pt-6">
            <p className="text-sm text-muted-foreground text-center">
              検索結果がありません
            </p>
          </CardContent>
        </Card>
      )}

      {urlKeyword && results.length > 0 && (
        <div className="relative space-y-4">
          {/* 既存リスト表示中の再検索は薄いレイヤー＋スピナーで応答待ちを明示 */}
          <SearchRefetchOverlay active={showRefetchOverlay} />
          <div className="mt-2 flex items-center justify-between gap-2">
            <p className="text-sm text-muted-foreground">
              <span className="font-medium text-foreground tabular-nums">{totalCount.toLocaleString()}</span>件
            </p>
            {/* アラート＝新しい部屋が出たら通知（保存条件の「再検索」とは別機能）。差を title で明示。 */}
            <span title="この条件に合う新しい部屋が出たらアラート" className="flex-shrink-0">
              <WatchKeywordButton keyword={urlKeyword} category={category} />
            </span>
          </div>

          {/* 検索→期間分析の橋渡し。ソートの1時間/24時間/1週間より長い任意の期間で、
              かつ期間の始点から在る部屋だけに絞る、という点が検索ソートとの違い。 */}
          <button
            type="button"
            onClick={handleViewPeriodGrowth}
            className="flex w-full items-center gap-3 rounded-lg border bg-card px-3 py-2.5 text-left transition-colors hover:bg-accent"
            data-testid="search-to-period-growth"
          >
            <span className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
              <TrendingUp className="h-5 w-5" />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-medium">指定期間の増減ランキングで見る</span>
              <span className="block text-xs text-muted-foreground">
                1週間より長い任意の期間で、期間の始点から続く部屋だけを増減順に
              </span>
            </span>
          </button>

          <div className="grid gap-2 md:gap-4">
            {results.map((chat) => (
              <OpenChatCard
                key={chat.id}
                chat={chat}
                inMyList={isInMyList(myListData, chat.id)}
                onCardClick={handleCardClick}
                onAddToMyList={handleAddToMyList}
                currentSort={sort}
                searchKeyword={urlKeyword}
              />
            ))}
          </div>

          {/* Infinite scroll loading indicator */}
          <InfiniteScrollLoader
            isLoading={isLoadingMore}
            hasMore={hasMore}
            observerRef={observerTarget}
          />
        </div>
      )}

      {!urlKeyword && !isLoading && (
        <Card>
          <CardContent className="py-12 text-center">
            <Search className="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p className="mt-4 text-sm text-muted-foreground">
              キーワードを入力して検索してください
            </p>
          </CardContent>
        </Card>
      )}

      <FolderSelectDialog
        open={folderSelectOpen}
        onOpenChange={setFolderSelectOpen}
        folders={myListData.folders}
        onSelect={handleFolderSelect}
        title="マイリストに追加"
      />
    </div>
  )
})

SearchPage.displayName = 'SearchPage'

export default SearchPage
