import { useState, useEffect, useRef, memo, useCallback, useMemo } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import useSWRInfinite from 'swr/infinite'
import { Search, TrendingUp } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { FolderSelectDialog } from '@/components/ui/folder-select-dialog'
import { OpenChatCard, InfiniteScrollLoader } from '@/components/OpenChat'
import { WatchKeywordButton } from '@/components/Notifications'
import { alphaApi } from '@/api/alpha'
import { loadMyList, addItem, isInMyList } from '@/services/storage'
import type { SearchResponse } from '@/types/api'
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
  const { searchNonce } = useLayout()

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
  const isLoadingMore = isValidating && size > 1

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
  const handleViewPeriodGrowth = useCallback(() => {
    const params = new URLSearchParams()
    params.set('q', urlKeyword)
    if (category) params.set('category', String(category))
    navigate(`/period-growth?${params.toString()}`)
  }, [navigate, urlKeyword, category])

  return (
    <div className="space-y-6">
      {isLoading && size === 1 && results.length === 0 && (
        <div className="flex justify-center py-10" role="status" aria-label="読み込み中">
          <div className="h-8 w-8 animate-spin rounded-full border-2 border-muted border-t-primary" />
        </div>
      )}

      {error && (
        <Card className="border-destructive">
          <CardContent className="pt-6">
            <p className="text-sm text-destructive">データの取得に失敗しました</p>
          </CardContent>
        </Card>
      )}

      {urlKeyword && !isLoading && results.length === 0 && (
        <Card>
          <CardContent className="pt-6">
            <p className="text-sm text-muted-foreground text-center">
              検索結果がありません
            </p>
          </CardContent>
        </Card>
      )}

      {urlKeyword && results.length > 0 && (
        <div className="space-y-4">
          <div className="mt-2 flex items-center justify-between gap-2">
            <p className="text-sm text-muted-foreground">
              <span className="font-medium text-foreground tabular-nums">{totalCount.toLocaleString()}</span>件
            </p>
            {/* 見張る＝新しい部屋が出たら通知（保存条件の「再検索」とは別機能）。差を title で明示。 */}
            <span title="この条件に合う新しい部屋が出たら通知" className="flex-shrink-0">
              <WatchKeywordButton keyword={urlKeyword} category={category} />
            </span>
          </div>

          {/* 検索→期間分析の橋渡し。「今の人数」ではなく「期間の伸び」で並べ直す分析へ。 */}
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
              <span className="block truncate text-sm font-medium">この条件で期間の伸びを見る</span>
              <span className="block truncate text-xs text-muted-foreground">
                今の人数ではなく「期間の伸び」で並べる分析
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
