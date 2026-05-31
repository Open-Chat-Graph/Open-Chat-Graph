import { useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { Bell, Settings2, Sparkles, Activity, CheckCheck } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAlerts } from '@/hooks/useAlerts'
import {
  KeywordHitCard,
  MovementCard,
  formatComputedAt,
} from '@/components/Notifications'
import type { KeywordHit, Movement } from '@/types/api'

// 統合タイムラインの1アイテム。新着部屋（KeywordHit）と見張りの動き（Movement）を混在させる。
type FeedItem =
  | { kind: 'keyword'; createdAt: number; data: KeywordHit }
  | { kind: 'movement'; createdAt: number; data: Movement }

/**
 * 通知ページ。新着部屋（キーワード見張りヒット）と見張りの動き（部屋／マイリストの増減）を
 * 1本のタイムラインに時系列（createdAt 降順）で混在表示する。
 * 各アイテムは未読を強調し、開いた時点でそのアイテムを既読化する。
 */
export default function NotificationsPage() {
  const navigate = useNavigate()
  const { data, isLoading, markRead, markAllRead } = useAlerts()
  const openWatchSettings = () => navigate('/watch')

  // keywordHits と movements を1配列に統合し createdAt 降順で並べる。
  const feed = useMemo<FeedItem[]>(() => {
    const items: FeedItem[] = [
      ...(data?.keywordHits ?? []).map(
        (data): FeedItem => ({ kind: 'keyword', createdAt: data.createdAt, data }),
      ),
      ...(data?.movements ?? []).map(
        (data): FeedItem => ({ kind: 'movement', createdAt: data.createdAt, data }),
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
            aria-label="見張り設定"
            title="見張り設定"
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
            ) : (
              <FeedRow key={`m-${item.data.id}`} type="movement">
                <MovementCard movement={item.data} onOpen={handleOpenMovement} />
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
 * タイムライン上の1行。控えめな種別バッジ（新着部屋＝Sparkles／動き＝Activity）を添え、
 * カード本体はそのまま描画する。
 */
function FeedRow({
  type,
  children,
}: {
  type: 'keyword' | 'movement'
  children: React.ReactNode
}) {
  const isKeyword = type === 'keyword'
  return (
    <div>
      <div className="mb-1 flex items-center gap-1 px-0.5 text-[11px] font-medium text-muted-foreground">
        {isKeyword ? (
          <Sparkles className="h-3 w-3 text-primary" />
        ) : (
          <Activity className="h-3 w-3 text-primary" />
        )}
        <span>{isKeyword ? '新着部屋' : '見張りの動き'}</span>
      </div>
      {children}
    </div>
  )
}

function EmptyMessage({ onSettings }: { onSettings: () => void }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
      <Bell className="h-10 w-10 text-muted-foreground/50" />
      <p className="font-medium">見張り中の動きや新着部屋がここに出ます。</p>
      <p className="max-w-xs text-sm text-muted-foreground">
        キーワードや部屋・マイリスト全体を見張ると、ここに時系列で出ます。
      </p>
      <Button variant="outline" size="sm" className="mt-1 gap-1.5" onClick={onSettings}>
        <Settings2 className="h-4 w-4" />
        見張り設定を開く
      </Button>
    </div>
  )
}
