import { ChevronRight, Folder, Edit, Settings2, Sparkles } from 'lucide-react'
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
  onFolderSettings?: (folder: FolderType) => void
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
  onFolderSettings?: () => void
  myListData: MyListData
}

function FolderItem({ folder, onFolderClick, onFolderEdit, onFolderSettings, myListData }: FolderItemProps) {
  return (
    <div
      className="flex items-center gap-2 px-3 py-2 min-h-[44px] hover:bg-accent rounded-md cursor-pointer select-none"
      onClick={onFolderClick}
      data-testid={`folder-item-${folder.id}`}
    >
      <ChevronRight className="h-5 w-5 flex-shrink-0" />
      <Folder className="h-5 w-5 text-primary flex-shrink-0" />
      {/* スマートフォルダアイコン（rule が有効なとき） */}
      {folder.hasRule && (
        <Sparkles className="h-3.5 w-3.5 text-primary flex-shrink-0" aria-label="自動追加ルールあり" />
      )}
      <span className="flex-1 text-base font-medium">{folder.name}</span>
      <span className="text-sm text-muted-foreground flex-shrink-0">
        {myListData.items.filter((item) => item.folderId === folder.id).length}
      </span>
      {/* フォルダ設定ボタン */}
      {onFolderSettings && (
        <Button
          variant="ghost"
          size="icon"
          className="h-8 w-8 flex-shrink-0"
          onClick={(e) => {
            e.stopPropagation()
            onFolderSettings()
          }}
          aria-label="フォルダ設定"
          data-testid={`folder-settings-${folder.id}`}
        >
          <Settings2 className="h-4 w-4" />
        </Button>
      )}
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
  onFolderSettings,
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

  // ChatItem の source を id で引くマップ
  const itemSourceMap = new Map<number, 'manual' | 'auto'>(
    myListData.items.map(i => [i.id, i.source ?? 'manual'])
  )

  return (
    <div className="space-y-3" data-testid="folder-list">
      {/* フォルダ表示 */}
      {sortedFolders.map((folder) => (
        <FolderItem
          key={folder.id}
          folder={folder}
          onFolderClick={() => onFolderClick(folder.id)}
          onFolderEdit={() => onFolderEdit(folder)}
          onFolderSettings={onFolderSettings ? () => onFolderSettings(folder) : undefined}
          myListData={myListData}
        />
      ))}

      {/* チャットアイテム表示 - OpenChatCardを使用 */}
      {sortedItems.map((item) => {
        const chatData = statsData.find((s) => s.id === item.id)
        if (!chatData) return null
        const isAuto = itemSourceMap.get(item.id) === 'auto'

        return (
          <div key={item.id} className="relative">
            <OpenChatCard
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
            {/* 自動追加バッジ */}
            {isAuto && (
              <span
                className="absolute bottom-2 left-3 inline-flex items-center rounded-full bg-primary/10 px-1.5 py-0 text-[10px] font-medium text-primary leading-5 pointer-events-none"
                aria-label="自動追加"
                data-testid={`auto-badge-${item.id}`}
              >
                自動
              </span>
            )}
          </div>
        )
      })}
    </div>
  )
}
