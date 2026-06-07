import { useParams, useLocation, useNavigate } from 'react-router-dom'
import { useEffect, useState, memo, useCallback, useMemo } from 'react'
import useSWR from 'swr'
import { History, ChevronRight } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { FolderSelectDialog } from '@/components/ui/folder-select-dialog'
import { DetailHeader, DetailInfo, DetailStats, DetailActions, OcGraph, InsightsBlock, RoomMetricsBlock, RankingHistoryOverlay } from '@/components/Detail'
import { WatchRoomControl } from '@/components/Notifications'
import { alphaApi } from '@/api/alpha'
import { loadMyList, addItem, removeItem, isInMyList } from '@/services/storage'
import { useLayout } from '@/contexts/layout-context'
import type { BasicInfoResponse, OpenChat, RankingHistoryResponse } from '@/types/api'

const DetailPage = memo(() => {
  const { id } = useParams<{ id: string }>()
  const location = useLocation()
  const navigate = useNavigate()
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


  // ランキング掲載履歴: 件数を先読み表示。0件はグレーアウトして開けない。
  // 1件以上なら「N件」を出し、押すと上に重ねる個別画面（キャッシュ即ヒット）を開く。
  // モバイルは従来通り最下部（右カラム側の末尾）、lg では左カラム（グラフの下）に出すため
  // 要素を1つ定義して2箇所（hidden lg:block / lg:hidden）に置く。
  const rankingHistoryNode =
    historyCount === 0 ? (
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
    )

  return (
    <div className="space-y-4">
      {/* コンテンツ。lg+ は「左=ヒーロー＋グラフ＋掲載履歴 / 右=アクション＋アラート＋指標＋考察」
          の2カラム。モバイル(〜lg未満)は従来の縦積み順を維持（DOM順そのまま、掲載履歴のみ
          2箇所マウントの表示切替で末尾に残す）。右カラムは固定320pxで、コンテナが広がっても
          補助ブロックが間延びしないようにする。 */}
      <div className="space-y-4 pb-20 md:pb-0 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(280px,320px)] xl:grid-cols-[minmax(0,1fr)_minmax(340px,400px)] lg:gap-6 lg:items-start lg:space-y-0">
        {/* 左カラム: ヒーロー（画像・名前・説明・統計）＋グラフ＋掲載履歴(lgのみ) */}
        <div className="space-y-4 min-w-0">
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

          {/* Graph（本家と同じoc-appグラフバンドルを埋め込み。idが変わったらkeyで再マウント。
              テーマはdata-theme＋octhemechangeでグラフ側が追従するためpropsに含めない） */}
          <OcGraph key={basicInfo.id} chatId={basicInfo.id} />

          <div className="hidden lg:block">{rankingHistoryNode}</div>
        </div>

        {/* 右カラム: アクション＋増減アラート＋アクセス指標＋考察（＋モバイルのみ掲載履歴） */}
        <div className="space-y-4 min-w-0">
          <DetailActions
            url={basicInfo.url}
            isInList={isInList}
            onAddToMyList={handleAddToMyList}
            onRemoveFromMyList={handleRemoveFromMyList}
          />

          {/* 部屋のアラート: 未設定なら開始ボタン、設定中ならしきい値（±%）設定カード。部屋ごとに詳細画面で完結 */}
          <WatchRoomControl openChatId={basicInfo.id} />

          {/* アクセス・検索の指標(GA/GSC): 純PV/UU/SEO流入/参加リンク押下/平均滞在。
              遅延取得・creds前は無表示。考察ブロックの近くに置き“見られ方”の文脈を補う */}
          <RoomMetricsBlock openChatId={basicInfo.id} />

          {/* 高次の考察: グラフだけでは見えない傾向。洞察が在るときだけ静かに現れる補助ブロック */}
          <InsightsBlock openChatId={basicInfo.id} />

          <div className="lg:hidden">{rankingHistoryNode}</div>
        </div>
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
