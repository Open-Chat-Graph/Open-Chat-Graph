import { memo, useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import useSWR from 'swr'
import { ArrowLeft, Layers } from 'lucide-react'
import { alphaApi } from '@/api/alpha'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { loadMyList, getFolderItems } from '@/services/storage'
import { buildColorMap } from '@/components/FolderChart/colors'
import {
  FolderOverlayChart,
  RoomListRow,
  useFolderGraphData,
  MAX_ROOMS,
  type MergedRow,
  type RoomMeta,
  type RoomListItem,
} from '@/components/FolderChart'
import type { BatchStatsResponse } from '@/types/api'

/** 期間プリセット（日数）。任意入力も可能。0 は「全期間」。初期は 30日（1ヶ月）。 */
const PERIOD_PRESETS: { value: number; label: string }[] = [
  { value: 1, label: '24時間' },
  { value: 7, label: '1週間' },
  { value: 30, label: '1ヶ月' },
  { value: 0, label: '全期間' },
]
const DEFAULT_DAYS = 30

// "YYYY-MM-DD..." の先頭10桁を取り、Date(UTC正午) にして比較に使う。想定外は null。
function parseDate(value: string): number | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
  if (!m) return null
  return Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3]), 12)
}

/**
 * 基準日（rows 末尾＝最新）から days 日前までに rows を絞る。
 * days<=0 は全期間（絞らない）。日付パース不能な行は安全側で残す。
 */
function filterRowsByDays(rows: MergedRow[], days: number): MergedRow[] {
  if (days <= 0 || rows.length === 0) return rows
  const latest = parseDate(rows[rows.length - 1].date)
  if (latest == null) return rows
  const from = latest - (days - 1) * 86_400_000
  return rows.filter((r) => {
    const t = parseDate(r.date)
    return t == null || t >= from
  })
}

/**
 * フォルダ統合グラフ。マイリストのあるフォルダ配下の全ルームのメンバー数推移を1つのグラフに重ねる。
 * 本家に無いαの標準メタ機能。`/mylist/:folderId/chart` で DetailOverlay 内に表示される。
 *
 * 構成: ヘッダ(フォルダ名・対象数) → 期間チップ → 重ね線グラフ → チェック式ルームリスト。
 * リスト行クリックでその線の表示/非表示を切替（visibleIds state）。
 * 期間チップで最新からN日前までにクライアント側で絞る（初期=1ヶ月）。
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

  // 表示期間（日数）。0 は全期間。初期は 1ヶ月。
  const [days, setDays] = useState<number>(DEFAULT_DAYS)
  const isPreset = PERIOD_PRESETS.some((p) => p.value === days)

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

  // 選択期間で最新からN日前までに絞る
  const visibleRows = useMemo(() => filterRowsByDays(rows, days), [rows, days])

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
            {/* 期間プリセット ＋ 任意入力 */}
            <div className="mb-3 flex flex-wrap items-center gap-1.5">
              <span className="mr-1 text-xs text-muted-foreground">期間</span>
              {PERIOD_PRESETS.map((p) => (
                <Button
                  key={p.value}
                  type="button"
                  size="sm"
                  variant={days === p.value ? 'default' : 'outline'}
                  className="h-8 px-3"
                  onClick={() => setDays(p.value)}
                  data-testid={`folder-chart-days-${p.value}`}
                >
                  {p.label}
                </Button>
              ))}
              <div className="flex items-center gap-1">
                <Input
                  type="number"
                  min={1}
                  inputMode="numeric"
                  value={isPreset ? '' : String(days)}
                  placeholder="任意"
                  onChange={(e) => {
                    const n = Number(e.target.value)
                    if (Number.isFinite(n) && n > 0) setDays(Math.floor(n))
                  }}
                  className="h-8 w-20"
                  data-testid="folder-chart-days-custom"
                />
                <span className="text-xs text-muted-foreground">日</span>
              </div>
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
                  <FolderOverlayChart rows={visibleRows} rooms={visibleRooms} />
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
