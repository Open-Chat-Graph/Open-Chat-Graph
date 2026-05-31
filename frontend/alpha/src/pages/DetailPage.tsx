import { useParams, useLocation, useNavigate } from 'react-router-dom'
import { useEffect, useState, memo, useCallback, useMemo } from 'react'
import useSWR from 'swr'
import { History, ChevronRight } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { FolderSelectDialog } from '@/components/ui/folder-select-dialog'
import { DetailHeader, DetailInfo, DetailStats, DetailActions, PreactChart, InsightsBlock, RankingHistoryOverlay } from '@/components/Detail'
import { alphaApi } from '@/api/alpha'
import { loadMyList, addItem, removeItem, isInMyList } from '@/services/storage'
import { useTheme } from '@/providers/theme-provider'
import { useLayout } from '@/contexts/layout-context'
import type { BasicInfoResponse, OpenChat, RankingHistoryResponse } from '@/types/api'

const DetailPage = memo(() => {
  const { id } = useParams<{ id: string }>()
  const location = useLocation()
  const navigate = useNavigate()
  const { resolvedTheme } = useTheme()
  const { setDetailTitle } = useLayout()

  const [myListData, setMyListData] = useState(() => loadMyList())
  const [folderSelectOpen, setFolderSelectOpen] = useState(false)
  const isInList = id ? isInMyList(myListData, parseInt(id)) : false

  // 詳細の上に重ねるサブ画面は URL で制御（ブラウザバックで閉じる）
  const isImageRoute = location.pathname.endsWith('/image')
  const isHistoryRoute = location.pathname.endsWith('/ranking-history')

  // location.stateから初期データを取得（検索/マイリストから遷移した場合）
  const initialData = location.state?.initialData as OpenChat | undefined

  // OpenChatからBasicInfoResponseへの変換
  // これにより、リストから遷移した場合は即座に基本情報を表示できる
  // ただし、initialDataのIDと現在のIDが一致する場合のみfallbackDataを使用
  const fallbackData = useMemo(() => {
    if (!initialData || !id) return undefined
    if (initialData.id !== parseInt(id)) return undefined // IDが一致しない場合はfallbackDataを使わない

    return {
      id: initialData.id,
      name: initialData.name,
      currentMember: initialData.member,
      category: initialData.category,
      categoryName: initialData.categoryName,
      description: initialData.desc,
      thumbnail: initialData.img,
      emblem: initialData.emblem,
      hourlyDiff: initialData.increasedMember,
      hourlyPercentage: initialData.percentageIncrease,
      diff24h: initialData.diff24h,
      percent24h: initialData.percent24h,
      diff1w: initialData.diff1w,
      percent1w: initialData.percent1w,
      createdAt: initialData.createdAt ?? null,
      registeredAt: initialData.registeredAt ?? '',
      joinMethodType: initialData.join_method_type,
      isInRanking: initialData.isInRanking,
      url: initialData.url ?? '', // urlフィールドがあればそれを使用
    } as BasicInfoResponse
  }, [initialData, id])

  // 基本情報取得（軽量）
  // fallbackDataで初期表示を高速化しつつ、IDが変わったら必ず最新データを取得
  const { data: basicInfo, error, isLoading } = useSWR<BasicInfoResponse>(
    id ? ['basic-info', id] : null,
    () => alphaApi.getBasicInfo(parseInt(id!)),
    {
      fallbackData, // 初期データを設定（同じIDのリストから遷移した場合のみ）
      revalidateOnFocus: false,
      revalidateOnReconnect: false,
      keepPreviousData: false // IDが変わったら前のデータを保持しない
    }
  )

  // ランキング掲載履歴の件数を先読み（オーバーレイと同一SWRキー＝開いた時はキャッシュ即ヒット）。
  // 件数をボタンに出し、0件はグレーアウトするために必要。非ブロッキングに背後で取得。
  const { data: historyData } = useSWR<RankingHistoryResponse>(
    basicInfo?.id ? ['ranking-history', basicInfo.id] : null,
    () => alphaApi.getRankingHistory(basicInfo!.id),
    { revalidateOnFocus: false, revalidateOnReconnect: false, dedupingInterval: 60000 }
  )
  const historyCount = historyData?.data.length // 取得中は undefined

  const handleAddToMyList = useCallback(() => {
    setFolderSelectOpen(true)
  }, [])

  const handleRemoveFromMyList = useCallback(() => {
    if (id) {
      const updated = removeItem(myListData, parseInt(id))
      setMyListData(updated)
    }
  }, [id, myListData])

  const handleFolderSelect = useCallback((folderId: string | null) => {
    if (id) {
      const updated = addItem(myListData, parseInt(id), folderId)
      setMyListData(updated)
    }
    setFolderSelectOpen(false)
  }, [id, myListData])

  // ヘッダーのタイトルを共有Context経由で渡す（離脱時にクリア）
  useEffect(() => {
    if (basicInfo?.name && basicInfo?.currentMember !== undefined) {
      setDetailTitle({ name: basicInfo.name, member: basicInfo.currentMember })
    }
    return () => setDetailTitle(null)
  }, [basicInfo?.name, basicInfo?.currentMember, setDetailTitle])

  if (error || (!isLoading && !basicInfo)) {
    return (
      <Card className="border-destructive">
        <CardContent className="pt-6">
          <p className="text-sm text-destructive">データの取得に失敗しました</p>
        </CardContent>
      </Card>
    )
  }

  if (!basicInfo) {
    return (
      <div className="flex justify-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    )
  }


  return (
    <div className="space-y-4">
      {/* 画像はマージンなし */}
      <DetailHeader
        thumbnail={basicInfo.thumbnail}
        name={basicInfo.name}
        imageModalOpen={isImageRoute}
        onImageModalOpenChange={(open) => {
          // 開く＝URLを進める / 閉じる＝ブラウザバック相当
          if (open) navigate(`/openchat/${basicInfo.id}/image`)
          else navigate(-1)
        }}
      />

      {/* コンテンツ */}
      <div className="space-y-4 pb-20 md:pb-0">
        <DetailInfo
          name={basicInfo.name}
          emblem={basicInfo.emblem}
          description={basicInfo.description}
        />

        <DetailStats
          currentMember={basicInfo.currentMember}
          joinMethodType={basicInfo.joinMethodType}
          hourlyDiff={basicInfo.hourlyDiff}
          hourlyPercentage={basicInfo.hourlyPercentage}
          diff24h={basicInfo.diff24h}
          percent24h={basicInfo.percent24h}
          diff1w={basicInfo.diff1w}
          percent1w={basicInfo.percent1w}
          registeredAt={basicInfo.registeredAt}
          categoryName={basicInfo.categoryName}
          isInRanking={basicInfo.isInRanking}
        />

        {/* Graph（外部Preactバンドルをコンポーネント化。idが変わったらkeyで再マウント） */}
        <PreactChart
          key={basicInfo.id}
          chatId={basicInfo.id}
          categoryKey={basicInfo.category}
          theme={resolvedTheme}
        />

        <DetailActions
          openChatId={basicInfo.id}
          url={basicInfo.url}
          isInList={isInList}
          onAddToMyList={handleAddToMyList}
          onRemoveFromMyList={handleRemoveFromMyList}
        />

        {/* 高次の考察: グラフだけでは見えない傾向。洞察が在るときだけ静かに現れる補助ブロック */}
        <InsightsBlock openChatId={basicInfo.id} />

        {/* ランキング掲載履歴: 件数を先読み表示。0件はグレーアウトして開けない。
            1件以上なら「N件」を出し、押すと上に重ねる個別画面（キャッシュ即ヒット）を開く。 */}
        {historyCount === 0 ? (
          // 0件は「壊れている」ではなく「まだ無い」と読めるよう、全体を薄くする opacity ではなく
          // 破線ボーダー＋muted背景で“意図的に空”と示す。文字は muted-foreground を維持しコントラストを確保。
          <div
            className="flex w-full items-center gap-3 rounded-lg border border-dashed bg-muted/30 px-4 py-3 text-left"
            aria-disabled="true"
            title="ランキングに掲載されると履歴がたまります"
            data-testid="ranking-history-empty"
          >
            <History className="h-5 w-5 flex-shrink-0 text-muted-foreground/70" />
            <span className="flex-1 text-sm font-medium text-muted-foreground">ランキング掲載履歴</span>
            <span className="text-xs tabular-nums text-muted-foreground">0件</span>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => navigate(`/openchat/${basicInfo.id}/ranking-history`)}
            className="flex w-full items-center gap-3 rounded-lg border bg-card px-4 py-3 text-left transition-colors hover:bg-accent"
          >
            <History className="h-5 w-5 flex-shrink-0 text-muted-foreground" />
            <span className="flex-1 text-sm font-medium">ランキング掲載履歴</span>
            {historyCount !== undefined && (
              <span className="text-xs tabular-nums text-muted-foreground">{historyCount}件</span>
            )}
            <ChevronRight className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
          </button>
        )}
      </div>

      <FolderSelectDialog
        open={folderSelectOpen}
        onOpenChange={setFolderSelectOpen}
        folders={myListData.folders}
        onSelect={handleFolderSelect}
        title="マイリストに追加"
      />

      {/* ランキング掲載履歴の個別画面（開いている時だけマウント＝この時だけfetch） */}
      {isHistoryRoute && (
        <RankingHistoryOverlay
          openChatId={basicInfo.id}
          onClose={() => navigate(-1)}
        />
      )}
    </div>
  )
})

DetailPage.displayName = 'DetailPage'

export default DetailPage
