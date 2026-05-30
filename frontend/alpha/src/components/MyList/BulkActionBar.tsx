import { memo } from 'react'
import { Trash2, Folder, X, CheckSquare } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface BulkActionBarProps {
  selectedCount: number
  onSelectAll: () => void
  onBulkDelete: () => void
  onBulkMove: () => void
  onExitSelectionMode?: () => void
}

export const BulkActionBar = memo(({
  selectedCount,
  onSelectAll,
  onBulkDelete,
  onBulkMove,
  onExitSelectionMode,
}: BulkActionBarProps) => {
  return (
    /* PC・タブレット：メインツールバーと同じ行に表示 */
    <div className="hidden md:flex items-center gap-2" data-testid="bulk-action-bar-desktop">
      <Button
        variant="outline"
        size="sm"
        onClick={onSelectAll}
        className="gap-1 text-xs select-none"
        title="全選択"
        data-testid="select-all-button"
      >
        <CheckSquare className="h-3 w-3" />
        全選択
      </Button>
      {selectedCount > 0 && (
        <>
          <Button
            variant="destructive"
            size="sm"
            onClick={onBulkDelete}
            className="gap-1 text-xs select-none"
          >
            <Trash2 className="h-3 w-3" />
            {selectedCount}件削除
          </Button>
          <Button
            variant="default"
            size="sm"
            onClick={onBulkMove}
            className="gap-1 text-xs select-none"
            data-testid="bulk-move-button"
          >
            <Folder className="h-3 w-3" />
            移動
          </Button>
        </>
      )}
      {onExitSelectionMode && (
        <Button
          variant="outline"
          size="sm"
          onClick={onExitSelectionMode}
          className="gap-1 text-xs ml-auto select-none"
          title="キャンセル"
          data-testid="exit-selection-mode-button"
        >
          <X className="h-3 w-3" />
          キャンセル
        </Button>
      )}
    </div>
  )
})

// モバイル用の下部固定バー（ドキュメントルートレベルでレンダリング）
export const BulkActionBarMobile = memo(({
  selectedCount,
  onSelectAll,
  onBulkDelete,
  onBulkMove,
  onExitSelectionMode,
}: BulkActionBarProps) => {
  return (
    <div className="md:hidden fixed bottom-20 left-1/2 transform -translate-x-1/2 bg-background border rounded-lg shadow-lg p-3 flex gap-3 z-nav max-w-[90vw]" data-testid="bulk-action-bar">
      <Button
        variant="outline"
        size="sm"
        onClick={onSelectAll}
        className="gap-1 text-sm select-none min-h-[40px]"
        data-testid="select-all-button-mobile"
      >
        全選択
      </Button>
      {selectedCount > 0 && (
        <>
          <Button
            variant="destructive"
            size="sm"
            onClick={onBulkDelete}
            className="gap-1 text-sm select-none min-h-[40px]"
          >
            <Trash2 className="h-3 w-3" />
            {selectedCount}件
          </Button>
          <Button
            variant="default"
            size="sm"
            onClick={onBulkMove}
            className="gap-1 text-sm select-none min-h-[40px]"
            data-testid="bulk-move-button-mobile"
          >
            移動
          </Button>
        </>
      )}
      {onExitSelectionMode && (
        <Button
          variant="outline"
          size="sm"
          onClick={onExitSelectionMode}
          className="gap-1 text-sm select-none min-h-[40px]"
          data-testid="exit-selection-mode-button-mobile"
        >
          <X className="h-3 w-3" />
        </Button>
      )}
    </div>
  )
})

BulkActionBar.displayName = 'BulkActionBar'
BulkActionBarMobile.displayName = 'BulkActionBarMobile'
