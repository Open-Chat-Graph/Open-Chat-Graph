import { useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { Bell, Settings2, Sparkles, Activity, CheckCheck, Radio, FolderOpen } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAlerts } from '@/hooks/useAlerts'
import {
  KeywordHitCard,
  MovementCard,
  SignalCard,
  FolderAddCard,
  FolderMovementCard,
  formatComputedAt,
} from '@/components/Notifications'
import type { KeywordHit, Movement, Signal, FolderAdd, FolderMovement } from '@/types/api'

// 統合タイムラインの1アイテム。新着部屋・増減アラート・機微シグナル・フォルダ系を混在させる。
type FeedItem =
  | { kind: 'keyword'; createdAt: number; data: KeywordHit }
  | { kind: 'movement'; createdAt: number; data: Movement }
  | { kind: 'signal'; createdAt: number; data: Signal }
  | { kind: 'folder_add'; createdAt: number; data: FolderAdd }
  | { kind: 'folder_movement'; createdAt: number; data: FolderMovement }

/**
 * 通知ページ。新着部屋（キーワードアラートヒット）と増減アラート（部屋／マイリストの増減）を
 * 1本のタイムラインに時系列（createdAt 降順）で混在表示する。
 * 各アイテムは未読を強調し、開いた時点でそのアイテムを既読化する。
 */
export default function NotificationsPage() {
  const navigate = useNavigate()
  const { data, isLoading, markRead, markAllRead } = useAlerts()
  const openWatchSettings = () => navigate('/watch')

  // keywordHits / movements / signals / folderAdds / folderMovements を1配列に統合し createdAt 降順で並べる。
  const feed = useMemo<FeedItem[]>(() => {
    const items: FeedItem[] = [
      ...(data?.keywordHits ?? []).map(
        (data): FeedItem => ({ kind: 'keyword', createdAt: data.createdAt, data }),
      ),
      ...(data?.movements ?? []).map(
        (data): FeedItem => ({ kind: 'movement', createdAt: data.createdAt, data }),
      ),
      ...(data?.signals ?? []).map(
        (data): FeedItem => ({ kind: 'signal', createdAt: data.createdAt, data }),
      ),
      ...(data?.folderAdds ?? []).map(
        (data): FeedItem => ({ kind: 'folder_add', createdAt: data.createdAt, data }),
      ),
      ...(data?.folderMovements ?? []).map(
        (data): FeedItem => ({ kind: 'folder_movement', createdAt: data.createdAt, data }),
      ),
    ]
    items.sort((a, b) => b.createdAt - a.createdAt)
    return items
  }, [data])

  const unreadCount = feed.filter((item) => !item.data.isRead).length

  const handleOpenKeyword = (hit: KeywordHit) => {
    if (!hit.isRead) markRead([hit.id])
  }
  const handleOpenMovement = (m: Movement) => {
    if (!m.isRead) markRead([m.id])
  }
  const handleOpenSignal = (s: Signal) => {
    if (!s.isRead) markRead([s.id])
  }
  const handleOpenFolderAdd = (item: FolderAdd) => {
    if (!item.isRead) markRead([item.id])
  }
  const handleOpenFolderMovement = (item: FolderMovement) => {
    if (!item.isRead) markRead([item.id])
  }

  const computedLabel = data?.computedAt ? formatComputedAt(data.computedAt) : null

  return (
    <div className="space-y-4">
      {/* ヘッダー行: 最終算出時刻 ＋ 操作 */}
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-xs text-muted-foreground">
          {computedLabel ? (
            <>最終更新 {computedLabel}（毎時のデータ更新後に反映）</>
          ) : (
            <>通知は毎時のデータ更新後に反映されます</>
          )}
        </p>
        <div className="ml-auto flex items-center gap-2">
          {unreadCount > 0 && (
            <Button
              variant="ghost"
              size="sm"
              className="h-8 gap-1.5 text-xs"
              onClick={() => markAllRead()}
              data-testid="mark-all-read"
            >
              <CheckCheck className="h-4 w-4" />
              すべて既読
            </Button>
          )}
          {/* フィードの設定は控えめな歯車で（主たる入口は設定タブ）。慣習的な「通知の設定」位置。 */}
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8"
            onClick={openWatchSettings}
            aria-label="アラート設定"
            title="アラート設定"
            data-testid="open-watch-settings"
          >
            <Settings2 className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {isLoading && !data ? (
        <div className="flex justify-center py-12">
          <div className="h-8 w-8 animate-spin rounded-full border-b-2 border-primary" />
        </div>
      ) : feed.length > 0 ? (
        <div className="space-y-2.5">
          {feed.map((item) =>
            item.kind === 'keyword' ? (
              <FeedRow key={`k-${item.data.id}`} type="keyword">
                <KeywordHitCard hit={item.data} onOpen={handleOpenKeyword} />
              </FeedRow>
            ) : item.kind === 'movement' ? (
              <FeedRow key={`m-${item.data.id}`} type="movement">
                <MovementCard movement={item.data} onOpen={handleOpenMovement} />
              </FeedRow>
            ) : item.kind === 'folder_add' ? (
              <FeedRow key={`fa-${item.data.id}`} type="folder_add">
                <FolderAddCard item={item.data} onOpen={handleOpenFolderAdd} />
              </FeedRow>
            ) : item.kind === 'folder_movement' ? (
              <FeedRow key={`fm-${item.data.id}`} type="folder_movement">
                <FolderMovementCard movement={item.data} onOpen={handleOpenFolderMovement} />
              </FeedRow>
            ) : (
              <FeedRow key={`s-${item.data.id}`} type="signal">
                <SignalCard signal={item.data} onOpen={handleOpenSignal} />
              </FeedRow>
            ),
          )}
        </div>
      ) : (
        <EmptyMessage onSettings={openWatchSettings} />
      )}
    </div>
  )
}

/**
 * タイムライン上の1行。控えめな種別ラベルを添えカード本体をそのまま描画する。
 */
function FeedRow({
  type,
  children,
}: {
  type: 'keyword' | 'movement' | 'signal' | 'folder_add' | 'folder_movement'
  children: React.ReactNode
}) {
  const icon =
    type === 'keyword' ? (
      <Sparkles className="h-3 w-3 text-primary" />
    ) : type === 'movement' ? (
      <Activity className="h-3 w-3 text-primary" />
    ) : type === 'folder_add' ? (
      <FolderOpen className="h-3 w-3 text-primary" />
    ) : type === 'folder_movement' ? (
      <FolderOpen className="h-3 w-3 text-primary" />
    ) : (
      <Radio className="h-3 w-3 text-primary" />
    )
  const label =
    type === 'keyword'
      ? '新着部屋'
      : type === 'movement'
        ? '増減アラート'
        : type === 'folder_add'
          ? 'フォルダ自動追加'
          : type === 'folder_movement'
            ? 'フォルダ増減アラート'
            : '機微シグナル'
  return (
    <div>
      <div className="mb-1 flex items-center gap-1 px-0.5 text-[11px] font-medium text-muted-foreground">
        {icon}
        <span>{label}</span>
      </div>
      {children}
    </div>
  )
}

function EmptyMessage({ onSettings }: { onSettings: () => void }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
      <Bell className="h-10 w-10 text-muted-foreground/50" />
      <p className="font-medium">アラートの動きや新着部屋がここに出ます。</p>
      <p className="max-w-xs text-sm text-muted-foreground">
        キーワードや部屋・マイリスト全体にアラートを設定すると、ここに時系列で出ます。
      </p>
      <Button variant="outline" size="sm" className="mt-1 gap-1.5" onClick={onSettings}>
        <Settings2 className="h-4 w-4" />
        アラート設定を開く
      </Button>
    </div>
  )
}
