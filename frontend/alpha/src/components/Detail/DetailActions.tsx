import { memo } from 'react'
import { ExternalLink, Check, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { WatchRoomButton } from '@/components/Notifications'

interface DetailActionsProps {
  openChatId: number
  url?: string
  isInList: boolean
  onAddToMyList: () => void
  onRemoveFromMyList: () => void
}

export const DetailActions = memo(({ openChatId, url, isInList, onAddToMyList, onRemoveFromMyList }: DetailActionsProps) => {
  return (
    <div className="max-w-[var(--content-w)] mx-auto flex justify-center gap-3 flex-wrap pb-4">
      {url && (
        <Button
          variant="default"
          size="default"
          onClick={() => window.open(url, '_blank')}
          className="gap-2"
          data-testid="line-open-button"
        >
          <ExternalLink className="h-4 w-4" />
          LINEで開く
        </Button>
      )}
      {isInList ? (
        <Button
          variant="outline"
          size="default"
          onClick={onRemoveFromMyList}
          className="gap-2"
          data-testid="mylist-added-button"
        >
          <Check className="h-4 w-4" />
          マイリスト登録済み
        </Button>
      ) : (
        <Button
          variant="outline"
          size="default"
          onClick={onAddToMyList}
          className="gap-2"
          data-testid="mylist-add-button"
        >
          <Plus className="h-4 w-4" />
          マイリストに追加
        </Button>
      )}
      {/* この部屋を見張る（通知のウォッチ条件に追加。詳細＝部屋ウォッチの追加導線） */}
      <WatchRoomButton openChatId={openChatId} />
    </div>
  )
})

DetailActions.displayName = 'DetailActions'
