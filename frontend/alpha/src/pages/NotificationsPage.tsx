import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Bell } from 'lucide-react'
import { OpenChatCard } from '@/components/OpenChat'
import { useGrowthNotifications } from '@/hooks/useGrowthNotifications'

/**
 * 人数増加通知ページ。
 * マイリストのルームのうち、直近24時間で人数が増えたものを増加幅順に表示する。
 * 開いた時点で「確認済み」にしてバッジをクリアする。
 */
export default function NotificationsPage() {
  const navigate = useNavigate()
  const { gainers, rooms, markSeen, isLoading, hasMyList } = useGrowthNotifications()

  // データが揃ったら既読化（バッジをクリア）
  useEffect(() => {
    if (!isLoading && rooms.length > 0) markSeen()
  }, [isLoading, rooms.length, markSeen])

  const handleCardClick = (chatId: number) => {
    const chatData = rooms.find((r) => r.id === chatId)
    navigate(`/openchat/${chatId}`, { state: chatData ? { initialData: chatData } : undefined })
  }

  if (!hasMyList) {
    return (
      <EmptyMessage
        title="通知はまだありません"
        body="マイリストにルームを追加すると、人数が増えたルームをここでお知らせします。"
      />
    )
  }

  if (isLoading) {
    return (
      <div className="flex justify-center py-10">
        <div className="h-8 w-8 animate-spin rounded-full border-b-2 border-primary" />
      </div>
    )
  }

  if (gainers.length === 0) {
    return (
      <EmptyMessage
        title="新しい増加はありません"
        body="マイリストのルームで、直近24時間に人数が増えたものはありませんでした。"
      />
    )
  }

  return (
    <div className="space-y-3">
      <p className="px-1 text-sm text-muted-foreground">
        マイリストで直近24時間に人数が増えたルーム（{gainers.length}件）
      </p>
      {gainers.map((room) => (
        <OpenChatCard key={room.id} chat={room} onCardClick={handleCardClick} />
      ))}
    </div>
  )
}

function EmptyMessage({ title, body }: { title: string; body: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
      <Bell className="h-10 w-10 text-muted-foreground/50" />
      <p className="font-medium">{title}</p>
      <p className="max-w-xs text-sm text-muted-foreground">{body}</p>
    </div>
  )
}
