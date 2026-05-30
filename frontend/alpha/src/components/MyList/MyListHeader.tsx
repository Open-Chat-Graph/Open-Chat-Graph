import { memo } from 'react'
import { FolderPlus, CheckSquare } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface MyListHeaderProps {
  selectionMode: boolean
  onToggleSelectionMode: () => void
  onCreateFolder: () => void
}

export const MyListHeader = memo(({
  selectionMode,
  onToggleSelectionMode,
  onCreateFolder,
}: MyListHeaderProps) => {
  return (
    <div className="flex items-center gap-2 py-2">
      <Button
        variant={selectionMode ? 'default' : 'outline'}
        size="sm"
        onClick={onToggleSelectionMode}
        data-testid="selection-mode-button"
      >
        <CheckSquare className="h-4 w-4 mr-2" />
        {selectionMode ? '選択モード終了' : '複数選択'}
      </Button>
      <Button variant="outline" size="sm" onClick={onCreateFolder}>
        <FolderPlus className="h-4 w-4 mr-2" />
        フォルダ作成
      </Button>
    </div>
  )
})

MyListHeader.displayName = 'MyListHeader'
