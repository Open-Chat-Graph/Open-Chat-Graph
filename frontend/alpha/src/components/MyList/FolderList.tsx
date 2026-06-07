import { ChevronRight, Folder, Edit } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { OpenChatCard } from '@/components/OpenChat'
import type { Folder as FolderType, MyListData, ChatItem } from '@/types/storage'
import type { OpenChat } from '@/types/api'
import {
  getFolderChildren,
  sortChatItems,
  sortFoldersByName,
  type MyListSortType,
  type SortOrder,
} from '@/services/storage'

// 現在のフォルダの内容を取得
function getVisibleContent(
  myListData: MyListData,
  currentFolderId: string | null
): { folders: FolderType[]; items: ChatItem[] } {
  return getFolderChildren(myListData, currentFolderId)
}

interface FolderListProps {
  currentFolderId: string | null
  myListData: MyListData
  statsData: OpenChat[]
  onFolderClick: (folderId: string) => void
  onItemClick: (chatId: number) => void
  onItemRemove: (chatId: number) => void
  onFolderEdit: (folder: FolderType) => void
  onUpdateData: (data: MyListData) => void
  selectionMode?: boolean
  selectedIds?: Set<number>
  onToggleSelection?: (chatId: number) => void
  onRangeSelection?: (chatId: number, allItemIds: number[]) => void
  onEnterSelectionMode?: () => void
  sortType?: MyListSortType
  sortOrder?: SortOrder
  sparklines?: Record<number, number[] | undefined>
}

interface FolderItemProps {
  folder: FolderType
  onFolderClick: () => void
  onFolderEdit: () => void
  myListData: MyListData
}

function FolderItem({ folder, onFolderClick, onFolderEdit, myListData }: FolderItemProps) {
  return (
    <div
      className="flex items-center gap-2 px-3 py-2 min-h-[44px] hover:bg-accent rounded-md cursor-pointer select-none"
      onClick={onFolderClick}
      data-testid={`folder-item-${folder.id}`}
    >
      <ChevronRight className="h-5 w-5 flex-shrink-0" />
      <Folder className="h-5 w-5 text-primary flex-shrink-0" />
      <span className="flex-1 text-base font-medium">{folder.name}</span>
      <span className="text-sm text-muted-foreground flex-shrink-0">
        {myListData.items.filter((item) => item.folderId === folder.id).length}
      </span>
      <Button
        variant="ghost"
        size="icon"
        className="h-8 w-8 flex-shrink-0"
        onClick={(e) => {
          e.stopPropagation()
          onFolderEdit()
        }}
        data-testid={`folder-edit-${folder.id}`}
      >
        <Edit className="h-4 w-4" />
      </Button>
    </div>
  )
}

export function FolderList({
  currentFolderId,
  myListData,
  statsData,
  onFolderClick,
  onItemClick,
  onItemRemove,
  onFolderEdit,
  selectionMode = false,
  selectedIds = new Set(),
  onToggleSelection,
  onRangeSelection,
  onEnterSelectionMode,
  sortType = 'member',
  sortOrder = 'desc',
  sparklines = {},
}: FolderListProps) {
  // 表示する内容を取得
  const { folders: visibleFolders, items: visibleItems } = getVisibleContent(
    myListData,
    currentFolderId
  )

  // フォルダを名前順にソート
  const sortedFolders = sortFoldersByName(visibleFolders)

  // アイテムをソート
  const sortedItems = sortChatItems(visibleItems, statsData, sortType, sortOrder)

  // ソート済みアイテムIDのリスト
  const sortedItemIds = sortedItems.map(item => item.id)

  return (
    <div className="space-y-3" data-testid="folder-list">
      {/* フォルダ表示 */}
      {sortedFolders.map((folder) => (
        <FolderItem
          key={folder.id}
          folder={folder}
          onFolderClick={() => onFolderClick(folder.id)}
          onFolderEdit={() => onFolderEdit(folder)}
          myListData={myListData}
        />
      ))}

      {/* チャットアイテム表示 - OpenChatCardを使用 */}
      {sortedItems.map((item) => {
        const chatData = statsData.find((s) => s.id === item.id)
        if (!chatData) return null

        return (
          <OpenChatCard
            key={item.id}
            chat={chatData}
            inMyList={true}
            onCardClick={onItemClick}
            onRemove={onItemRemove}
            selectionMode={selectionMode}
            isSelected={selectedIds.has(item.id)}
            onToggleSelection={onToggleSelection}
            onRangeSelection={onRangeSelection}
            allItemIds={sortedItemIds}
            onEnterSelectionMode={onEnterSelectionMode}
            currentSort={sortType}
            sparklinePoints={sparklines[item.id]}
          />
        )
      })}
    </div>
  )
}
