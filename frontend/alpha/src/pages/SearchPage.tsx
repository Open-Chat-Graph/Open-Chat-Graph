import { useState, useEffect, memo, useCallback, useMemo } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { Search, TrendingUp } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { FolderSelectDialog } from '@/components/ui/folder-select-dialog'
import { OpenChatCard } from '@/components/OpenChat'
import { WatchKeywordButton } from '@/components/Notifications'
import { ListProgressRegion, ListProgressFooter } from '@/components/Common/ListProgress'
import { SearchLanding } from '@/components/Common/SearchLanding'
import { useListProgress } from '@/hooks/useListProgress'
import { useInfiniteList } from '@/hooks/useInfiniteList'
import { useSparklines } from '@/hooks/useSparklines'
import { alphaApi } from '@/api/alpha'
import { addSearchHistory } from '@/services/searchHistory'
import { loadMyList, addItem, isInMyList } from '@/services/storage'
import type { SearchResponse, SearchEtaParams } from '@/types/api'
import type { SortType, SortOrder } from '@/lib/sort-options'
import { useLayout } from '@/contexts/layout-context'

const LIMIT = 300

const SearchPage = memo(() => {
  const navigate = useNavigate()
  const location = useLocation()
  const [routerParams] = useSearchParams()

  // 外部パラメータガード: keep-alive で隠れている間は URL が他ページのもの（詳細 /openchat/:id や
  // /analysis 等）になり ?q が消えるため、URL パラメータは「このページがルートを所有しているとき」
  // だけ取り込む。隠れている間は最後に所有していた値を保持し、SWR キー／listKey を安定させる
  // （変動すると useInfiniteList が「条件変更」と誤認し visibleCount が 30 に戻る）。
  // PeriodGrowthPage と同一パターン。
  const ownsRoute = location.pathname === '/'
  const [searchParams, setOwnedParams] = useState(() =>
    ownsRoute ? routerParams : new URLSearchParams(),
  )
  if (ownsRoute && searchParams !== routerParams) {
    // レンダー中の state 調整（React 公式パターン）。所有中のみ URL に追従する。
    setOwnedParams(routerParams)
  }

  const urlKeyword = searchParams.get('q') || ''
  const sort = (searchParams.get('sort') as SortType) || 'member'
  const order = (searchParams.get('order') as SortOrder) || 'desc'
  const category = Number(searchParams.get('category')) || 0
  const [myListData, setMyListData] = useState(() => loadMyList())
  const [folderSelectOpen, setFolderSelectOpen] = useState(false)
  const [selectedChatId, setSelectedChatId] = useState<number | null>(null)
  // 検索バー再実行シグナル（同じキーワードでも SWR キーを変えて再フェッチ）
  const { searchNonce, bumpReset } = useLayout()

  // SWR Infinite のキー（sort, order, category, searchNonce を含めて再実行に対応）
  const getKey = useCallback(
    (pageIndex: number, previousPageData: SearchResponse | null) => {
      // キーワードもカテゴリも無いときだけフェッチしない
      if (!urlKeyword && !category) return null

      // 前のページデータがあり、それが最後のページなら nullを返す
      if (previousPageData && previousPageData.data.length === 0) return null

      // pageIndexは0始まりなので、そのまま渡す
      return ['search', urlKeyword, sort, order, category, pageIndex, LIMIT, searchNonce]
    },
    [urlKeyword, sort, order, category, searchNonce]
  )

  // 検索条件の識別キー（getKey と同じ識別。pageIndex を除く）。
  // 変化したときだけ表示件数が先頭へ戻る（useInfiniteList が ref 比較で判定）。
  const searchKey = (urlKeyword || category) ? `${urlKeyword}|${sort}|${order}|${category}|${searchNonce}` : null

  // ページング＋reveal＋無限スクロールは共通コントローラに集約（検索/期間増減/Labs 同一）。
  const { pages, items: results, error, phase, hasMore, visibleCount, sentinelRef, mutate } =
    useInfiniteList<SearchResponse>({
      listKey: searchKey,
      getKey,
      fetcher: ([, keyword, sortType, sortOrder, cat, page, limit]: readonly [
        string, string, SortType, SortOrder, number, number, number, number,
      ]) =>
        alphaApi.search({ keyword, category: cat, page, limit, sort: sortType, order: sortOrder }),
      getHasMore: (loadedPages, loadedCount) => loadedCount < (loadedPages[0]?.totalCount ?? 0),
    })

  const totalCount = pages[0]?.totalCount || 0

  const etaParams = useMemo<SearchEtaParams | null>(
    () =>
      (urlKeyword || category)
        ? { keyword: urlKeyword, category: category || undefined, sort, order }
        : null,
    [urlKeyword, category, sort, order],
  )

  // 検索が完了したら（キーワードありで結果が返ったら）最近の検索履歴に自動追記する。
  // localStorage 駆動・上限件数で古いものを捨てる（searchHistory サービス側）。
  useEffect(() => {
    if (!urlKeyword.trim()) return
    if (phase === 'first') return
    if (pages.length === 0) return
    addSearchHistory({ q: urlKeyword, category, sort, order })
    // urlKeyword/カテゴリ/ソートの組と「結果が来た」ことをトリガに1回だけ追記
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchKey, pages, phase])

  // プログレスは実フェッチ（1ページ目の応答待ち）の有無だけから導出される。
  // キャッシュ即答・Activity 復帰では loading が立たないのでバーも ETA 取得も発生しない。
  const { progress, active: progressActive } = useListProgress({
    loading: phase === 'first',
    fetchEta: etaParams
      ? async () => (await alphaApi.getSearchEta(etaParams)).etaMs
      : undefined,
  })
  const hasResults = results.length > 0

  // 表示中（reveal済み）アイテムの ID に対してスパークラインを取得
  const visibleIds = useMemo(
    () => results.slice(0, visibleCount).map((c) => c.id),
    // visibleCount は増える一方なので毎回新配列だが useMemo で安定化
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [results, visibleCount],
  )
  const sparklines = useSparklines(visibleIds)

  const handleCardClick = useCallback((chatId: number) => {
    // リストから該当するデータを見つけてstateで渡す（DetailPageでのフェッチを避ける）
    const chatData = results.find(chat => chat.id === chatId)
    navigate(`/openchat/${chatId}`, {
      state: chatData ? { initialData: chatData } : undefined
    })
  }, [navigate, results])

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
      <ListProgressRegion
        progress={progress}
        active={progressActive}
        hasResults={hasResults}
        caption="検索中…"
      >
      {error && (
        <Card className="border-destructive">
          <CardContent className="pt-6 space-y-3">
            <p className="text-sm text-destructive">
              データの取得に失敗しました。自動的に再試行します…
            </p>
            <button
              type="button"
              onClick={() => mutate()}
              className="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
            >
              今すぐ再試行
            </button>
          </CardContent>
        </Card>
      )}

      {(urlKeyword || category) && phase !== 'first' && !progressActive && results.length === 0 && (
        <Card>
          <CardContent className="pt-6">
            <p className="text-sm text-muted-foreground text-center">
              検索結果がありません
            </p>
          </CardContent>
        </Card>
      )}

      {(urlKeyword || category) && results.length > 0 && (
        <div className="space-y-4">
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

          {/* ソート済みリストは1カラム（2カラムは視線がZ字に折り返し上から順の比較ができない）。
              広いシェルでは1行が長くなりすぎないよう読みやすい幅にキャップして中央寄せ。 */}
          <div className="grid gap-2 md:gap-4 lg:max-w-3xl lg:mx-auto lg:w-full">
            {results.slice(0, visibleCount).map((chat) => (
              <OpenChatCard
                key={chat.id}
                chat={chat}
                inMyList={isInMyList(myListData, chat.id)}
                onCardClick={handleCardClick}
                onAddToMyList={handleAddToMyList}
                currentSort={sort}
                searchKeyword={urlKeyword}
                sparklinePoints={sparklines[chat.id]}
              />
            ))}
          </div>

          {/* 追加読み込み（無限スクロール）。初回・再取得と同じ ListProgressBar に統一。 */}
          <ListProgressFooter
            isLoading={phase === 'more'}
            hasMore={hasMore}
            observerRef={sentinelRef}
          />
        </div>
      )}
      </ListProgressRegion>

      {!urlKeyword && !category && phase !== 'first' && (
        <>
          {/* 初期/空状態のランディング: 最近の検索＋保存した検索条件（あれば）。 */}
          <SearchLanding />
          <Card>
            <CardContent className="py-12 text-center">
              <Search className="mx-auto h-12 w-12 text-muted-foreground/50" />
              <p className="mt-4 text-sm text-muted-foreground">
                キーワードを入力するか、カテゴリを選んで検索してください
              </p>
            </CardContent>
          </Card>
        </>
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
