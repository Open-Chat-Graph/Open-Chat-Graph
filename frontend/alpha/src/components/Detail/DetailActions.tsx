import { memo } from 'react'
import { ExternalLink, Check, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface DetailActionsProps {
  url?: string
  isInList: boolean
  onAddToMyList: () => void
  onRemoveFromMyList: () => void
}

export const DetailActions = memo(({ url, isInList, onAddToMyList, onRemoveFromMyList }: DetailActionsProps) => {
  return (
    <div className="max-w-[var(--content-w)] mx-auto flex justify-center gap-3 flex-wrap pb-4">
      {/* 外部リンク（離脱導線）は塗りつぶしにせず outline＋primaryテキストに降格。
          並びの主従は「LINE=outline ＞ マイリスト=ghost」。 */}
      {url && (
        <Button
          variant="outline"
          size="default"
          onClick={() => window.open(url, '_blank')}
          className="gap-2 border-primary/40 text-primary hover:bg-primary/10 hover:text-primary"
          data-testid="line-open-button"
        >
          <ExternalLink className="h-4 w-4" />
          LINEで開く
        </Button>
      )}
      {isInList ? (
        <Button
          variant="ghost"
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
          variant="ghost"
          size="default"
          onClick={onAddToMyList}
          className="gap-2"
          data-testid="mylist-add-button"
        >
          <Plus className="h-4 w-4" />
          マイリストに追加
        </Button>
      )}
    </div>
  )
})

DetailActions.displayName = 'DetailActions'
