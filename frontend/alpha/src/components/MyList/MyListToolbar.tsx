import { memo } from 'react'
import { ArrowLeft, CheckSquare, FolderPlus, ArrowUpDown, Check } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import type { Folder } from '@/types/storage'
import type { MyListSortType, SortOrder } from '@/services/storage'

// 統合ソートオプション（タイプ×順序の全組み合わせ）
const UNIFIED_SORT_OPTIONS = [
  { value: 'member', order: 'desc' as SortOrder, label: '人数降順' },
  { value: 'member', order: 'asc' as SortOrder, label: '人数昇順' },
  { value: 'created_at', order: 'desc' as SortOrder, label: '作成日順降順' },
  { value: 'created_at', order: 'asc' as SortOrder, label: '作成日順昇順' },
  { value: 'hourly_diff', order: 'desc' as SortOrder, label: '1時間増減降順' },
  { value: 'hourly_diff', order: 'asc' as SortOrder, label: '1時間増減昇順' },
  { value: 'diff_24h', order: 'desc' as SortOrder, label: '24時間増減降順' },
  { value: 'diff_24h', order: 'asc' as SortOrder, label: '24時間増減昇順' },
  { value: 'diff_1w', order: 'desc' as SortOrder, label: '1週間増減降順' },
  { value: 'diff_1w', order: 'asc' as SortOrder, label: '1週間増減昇順' },
] as const

interface MyListToolbarProps {
  currentFolderId: string | null
  folders: Folder[]
  onNavigate: (folderId: string | null) => void
  selectionMode: boolean
  onToggleSelectionMode: () => void
  onCreateFolder: () => void
  sortType: MyListSortType
  sortOrder: SortOrder
  onSortChange: (sortType: MyListSortType, order: SortOrder) => void
}

export const MyListToolbar = memo((props: MyListToolbarProps) => {
  const {
    currentFolderId,
    folders,
    onNavigate,
    selectionMode,
    onToggleSelectionMode,
    onCreateFolder,
    sortType,
    sortOrder,
    onSortChange,
  } = props

  const handleGoUp = () => {
    if (!currentFolderId) return

    const currentFolder = folders.find(f => f.id === currentFolderId)
    if (currentFolder) {
      onNavigate(currentFolder.parentId)
    }
  }

  const currentSortLabel = UNIFIED_SORT_OPTIONS.find(
    opt => opt.value === sortType && opt.order === sortOrder
  )?.label ?? '人数降順'

  return (
    <div className="max-w-4xl mx-auto px-4">
      <div className="flex items-center gap-2">
        {/* 戻るボタン（フォルダ内のみ表示） */}
        {currentFolderId && (
          <Button
            variant="ghost"
            size="icon"
            onClick={handleGoUp}
            data-testid="go-up-button"
            title="上の階層へ"
          >
            <ArrowLeft className="h-4 w-4" />
          </Button>
        )}

        {/* 右寄せグループ */}
        <div className="flex items-center gap-2 ml-auto">
          {/* 複数選択 */}
          <Button
            variant={selectionMode ? 'default' : 'outline'}
            size="icon"
            onClick={onToggleSelectionMode}
            data-testid="selection-mode-button"
            title={selectionMode ? '選択モード終了' : '複数選択'}
          >
            <CheckSquare className="h-4 w-4" />
          </Button>

          {/* フォルダ新規 */}
          <Button
            variant="outline"
            size="icon"
            onClick={onCreateFolder}
            data-testid="create-folder-button"
            title="フォルダ作成"
          >
            <FolderPlus className="h-4 w-4" />
          </Button>

          {/* 並び替え */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="outline"
                size="icon"
                data-testid="sort-dropdown-trigger"
                title={`並び替え: ${currentSortLabel}`}
              >
                <ArrowUpDown className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent data-testid="sort-dropdown-content">
              {UNIFIED_SORT_OPTIONS.map((option) => {
                const isSelected = option.value === sortType && option.order === sortOrder
                return (
                  <DropdownMenuItem
                    key={`${option.value}-${option.order}`}
                    onClick={() => onSortChange(option.value as MyListSortType, option.order)}
                    data-testid={`sort-option-${option.value}-${option.order}`}
                  >
                    <div className="flex items-center gap-2 w-full">
                      <Check className={`h-4 w-4 ${isSelected ? 'opacity-100' : 'opacity-0'}`} />
                      <span>{option.label}</span>
                    </div>
                  </DropdownMenuItem>
                )
              })}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </div>
  )
})

MyListToolbar.displayName = 'MyListToolbar'
