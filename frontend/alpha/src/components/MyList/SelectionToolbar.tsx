import { memo } from 'react'
import { Button } from '@/components/ui/button'

interface SelectionToolbarProps {
  selectedCount: number
  onSelectAll: () => void
  onClearSelection: () => void
}

export const SelectionToolbar = memo(({
  selectedCount,
  onSelectAll,
  onClearSelection,
}: SelectionToolbarProps) => {
  return (
    <div className="flex items-center gap-2 mt-6" data-testid="selection-toolbar">
      <Button variant="outline" size="sm" onClick={onSelectAll} data-testid="select-all-button">
        すべて選択
      </Button>
      <Button variant="outline" size="sm" onClick={onClearSelection}>
        選択解除
      </Button>
      <span className="text-sm text-muted-foreground">
        {selectedCount}件選択中
      </span>
    </div>
  )
})

SelectionToolbar.displayName = 'SelectionToolbar'
