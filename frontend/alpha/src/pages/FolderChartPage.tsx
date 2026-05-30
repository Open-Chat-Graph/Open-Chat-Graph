import { memo, useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import useSWR from 'swr'
import { ArrowLeft, Layers, TrendingUp, Trophy } from 'lucide-react'
import { alphaApi } from '@/api/alpha'
import { loadMyList, getFolderItems } from '@/services/storage'
import { buildColorMap } from '@/components/FolderChart/colors'
import {
  FolderOverlayChart,
  RoomListRow,
  useFolderGraphData,
  MAX_ROOMS,
  type Metric,
  type RoomMeta,
  type RoomListItem,
} from '@/components/FolderChart'
import type { BatchStatsResponse } from '@/types/api'

/**
 * フォルダ統合グラフ。マイリストのあるフォルダ配下の全ルームの時系列を1つのグラフに重ねる。
 * 本家に無いαの標準メタ機能。`/mylist/:folderId/chart` で DetailOverlay 内に表示される。
 *
 * 構成: ヘッダ(フォルダ名・対象数・人数/順位トグル) → 重ね線グラフ → チェック式ルームリスト。
 * リスト行クリックでその線の表示/非表示を切替（visibleIds state）。
 */
const FolderChartPage = memo(() => {
  const navigate = useNavigate()
  const { folderId } = useParams<{ folderId?: string }>()

  // マイリスト（localStorage）からフォルダ名と配下アイテムを得る
  const myList = useMemo(() => loadMyList(), [])
  const folder = useMemo(
    () => myList.folders.find((f) => f.id === folderId) ?? null,
    [myList.folders, folderId],
  )
  const folderName = folder?.name ?? 'フォルダ'

  // 配下ルームの openChatId（追加順）。上限を超えた分は対象外。
  const allItemIds = useMemo(
    () => getFolderItems(myList, folderId ?? null).map((i) => i.id),
    [myList, folderId],
  )
  const targetIds = useMemo(() => allItemIds.slice(0, MAX_ROOMS), [allItemIds])
  const truncated = allItemIds.length > MAX_ROOMS

  // id→色（並び順で安定割当。凡例/線/チェックリストで共通）
  const colorMap = useMemo(() => buildColorMap(targetIds), [targetIds])

  // 表示メトリクス（人数 / ランキング順位）
  const [metric, setMetric] = useState<Metric>('members')

  // チェックON（線を描く）ルームの集合。初期は全ON。
  const [visibleIds, setVisibleIds] = useState<Set<number>>(() => new Set(targetIds))
  useEffect(() => {
    setVisibleIds(new Set(targetIds))
  }, [targetIds])

  const toggleVisible = (id: number) => {
    setVisibleIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  // 名前/画像/現在人数（バッチ取得）
  const { data: statsData } = useSWR<BatchStatsResponse>(
    targetIds.length > 0 ? ['folder-chart-batch', targetIds.join(',')] : null,
    () => alphaApi.batchStats(targetIds),
    { revalidateOnFocus: false, revalidateOnReconnect: false },
  )

  // 時系列（並列フェッチ＋日付マージ）
  const { rows, loadedIds, isLoading, error } = useFolderGraphData(targetIds)

  // ルームのメタ情報（名前/人数/画像 + 色）。グラフで取得できたルームのみ。
  const roomItems: RoomListItem[] = useMemo(() => {
    const byId = new Map(statsData?.data.map((s) => [s.id, s]) ?? [])
    return loadedIds.map((id) => {
      const s = byId.get(id)
      return {
        id,
        name: s?.name ?? `#${id}`,
        member: s?.member ?? 0,
        img: s?.img ?? '',
        color: colorMap.get(id) ?? '#888',
      }
    })
  }, [loadedIds, statsData?.data, colorMap])

  // グラフに描くルーム（チェックON）
  const visibleRooms: RoomMeta[] = useMemo(
    () =>
      roomItems
        .filter((r) => visibleIds.has(r.id))
        .map((r) => ({ id: r.id, name: r.name, color: r.color })),
    [roomItems, visibleIds],
  )

  const handleClose = () => navigate(folderId ? `/mylist/${folderId}` : '/mylist')

  return (
    <div className="-m-3 md:-m-6">
      {/* ヘッダ */}
      <header className="sticky top-0 z-subheader flex items-center gap-2 border-b bg-background/90 px-2 py-2 backdrop-blur-sm">
        <button
          type="button"
          onClick={handleClose}
          aria-label="戻る"
          className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        <div className="flex min-w-0 items-center gap-2">
          <Layers className="h-4 w-4 flex-shrink-0 text-primary" />
          <div className="min-w-0">
            <h1 className="truncate text-base font-semibold leading-tight">{folderName}</h1>
            <p className="text-xs leading-tight text-muted-foreground">
              統合グラフ・{targetIds.length}件{truncated ? `（全${allItemIds.length}件中）` : ''}
            </p>
          </div>
        </div>
      </header>

      <div className="mx-auto max-w-[700px] p-3 md:p-6">
        {/* 空フォルダ */}
        {targetIds.length === 0 ? (
          <div className="flex flex-col items-center gap-2 py-20 text-center">
            <Layers className="h-8 w-8 text-muted-foreground/60" />
            <p className="text-sm text-muted-foreground">
              このフォルダにはルームがありません。
              <br />
              ルームを追加すると成長の重ね線グラフが見られます。
            </p>
          </div>
        ) : (
          <>
            {/* メトリクス切替（人数 / 順位） */}
            <div className="mb-3 inline-flex rounded-lg border bg-card p-0.5 text-sm">
              <button
                type="button"
                onClick={() => setMetric('members')}
                className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-colors ${
                  metric === 'members'
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
                data-testid="metric-members"
              >
                <TrendingUp className="h-3.5 w-3.5" />
                人数
              </button>
              <button
                type="button"
                onClick={() => setMetric('rankings')}
                className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-colors ${
                  metric === 'rankings'
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
                data-testid="metric-rankings"
              >
                <Trophy className="h-3.5 w-3.5" />
                ランキング順位
              </button>
            </div>

            {truncated && (
              <p className="mb-3 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                ルームが多いため、先頭の{MAX_ROOMS}件を表示しています。
              </p>
            )}

            {/* グラフ本体 */}
            <div className="rounded-lg border bg-card p-3">
              <div className="h-[320px] w-full md:h-[380px]">
                {isLoading ? (
                  <div className="flex h-full items-center justify-center">
                    <div className="h-8 w-8 animate-spin rounded-full border-b-2 border-primary" />
                  </div>
                ) : error ? (
                  <div className="flex h-full items-center justify-center">
                    <p className="text-sm text-destructive">グラフの取得に失敗しました</p>
                  </div>
                ) : visibleRooms.length === 0 ? (
                  <div className="flex h-full items-center justify-center">
                    <p className="text-sm text-muted-foreground">
                      下のリストでルームを選ぶと線が表示されます
                    </p>
                  </div>
                ) : (
                  <FolderOverlayChart rows={rows} rooms={visibleRooms} metric={metric} />
                )}
              </div>
            </div>

            {/* ルームリスト（チェック式表示トグル） */}
            <div className="mt-4">
              <div className="mb-1 flex items-center justify-between px-2">
                <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  ルーム（{roomItems.length}）
                </h2>
                <span className="text-xs text-muted-foreground">タップで線を表示/非表示</span>
              </div>
              <div className="rounded-lg border bg-card p-1">
                {roomItems.length === 0 && !isLoading ? (
                  <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                    表示できるルームのデータがありません
                  </p>
                ) : (
                  roomItems.map((room) => (
                    <RoomListRow
                      key={room.id}
                      room={room}
                      visible={visibleIds.has(room.id)}
                      onToggle={toggleVisible}
                    />
                  ))
                )}
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  )
})

FolderChartPage.displayName = 'FolderChartPage'

export default FolderChartPage
