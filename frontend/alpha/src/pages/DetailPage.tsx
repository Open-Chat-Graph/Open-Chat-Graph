import { useParams, useLocation } from 'react-router-dom'
import { useEffect, useState, memo, useCallback, useMemo } from 'react'
import useSWR from 'swr'
import { Card, CardContent } from '@/components/ui/card'
import { FolderSelectDialog } from '@/components/ui/folder-select-dialog'
import { DetailHeader, DetailInfo, DetailStats, DetailActions, RankingHistory, PreactChart } from '@/components/Detail'
import { alphaApi } from '@/api/alpha'
import { loadMyList, addItem, removeItem, isInMyList } from '@/services/storage'
import { useTheme } from '@/providers/theme-provider'
import type { BasicInfoResponse, RankingHistoryResponse, OpenChat } from '@/types/api'

const DetailPage = memo(() => {
  const { id } = useParams<{ id: string }>()
  const location = useLocation()
  const { resolvedTheme } = useTheme()

  const [myListData, setMyListData] = useState(() => loadMyList())
  const [folderSelectOpen, setFolderSelectOpen] = useState(false)
  const [imageModalOpen, setImageModalOpen] = useState(false)
  const isInList = id ? isInMyList(myListData, parseInt(id)) : false

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

  const { data: historyData } = useSWR<RankingHistoryResponse>(
    id ? ['ranking-history', id] : null,
    () => alphaApi.getRankingHistory(parseInt(id!)),
    {
      revalidateOnFocus: false,
      revalidateOnReconnect: false
    }
  )

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

  // タイトル情報をsessionStorageに保存（DashboardLayoutのタイトルバー用）
  useEffect(() => {
    if (basicInfo?.name && basicInfo?.currentMember !== undefined) {
      sessionStorage.setItem('detailPageTitle', JSON.stringify({
        name: basicInfo.name,
        member: basicInfo.currentMember
      }))
    }
    return () => {
      sessionStorage.removeItem('detailPageTitle')
    }
  }, [basicInfo?.name, basicInfo?.currentMember])

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
        id={basicInfo.id}
        thumbnail={basicInfo.thumbnail}
        name={basicInfo.name}
        imageModalOpen={imageModalOpen}
        onImageModalOpenChange={setImageModalOpen}
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
          createdAt={basicInfo.createdAt}
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
          url={basicInfo.url}
          isInList={isInList}
          onAddToMyList={handleAddToMyList}
          onRemoveFromMyList={handleRemoveFromMyList}
        />

        {/* ランキング掲載履歴 */}
        {historyData?.data && historyData.data.length > 0 && (
          <div className="-mt-2 md:mt-0">
            <RankingHistory data={historyData.data} />
          </div>
        )}
      </div>

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

DetailPage.displayName = 'DetailPage'

export default DetailPage
