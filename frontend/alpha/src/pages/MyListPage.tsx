import { useState, useEffect, useCallback, memo } from 'react'
import { useNavigate, useLocation, useParams } from 'react-router-dom'
import useSWR from 'swr'
import { ArrowLeft, CheckSquare, FolderPlus, ArrowUpDown, Check, LineChart } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  removeItem,
  saveMyList,
  loadMyList,
  loadSortSettings,
  saveSortSettings,
  type MyListSortType,
  type SortOrder,
} from '@/services/storage'
import { alphaApi } from '@/api/alpha'
import {
  FolderList,
  FolderDialog,
  BulkActionBar,
  EmptyState,
  ErrorState,
} from '@/components/MyList'
import { BulkActionBarMobile } from '@/components/MyList/BulkActionBar'
import { FolderSelectDialog } from '@/components/ui/folder-select-dialog'
import { ConfirmDialog } from '@/components/ui/confirm-dialog'
import { useConfirmDialog } from '@/hooks/useConfirmDialog'
import { useMyListSelection } from '@/hooks/useMyListSelection'
import { useBulkOperations } from '@/hooks/useBulkOperations'
import { useFolderManagement } from '@/hooks/useFolderManagement'
import { useFolderNavigation } from '@/hooks/useFolderNavigation'
import { useScrollDirection } from '@/hooks/useScrollDirection'
import { useScrollToTopOnReclick } from '@/hooks/useScrollToTopOnReclick'
import type { MyListData } from '@/types/storage'
import type { BatchStatsResponse } from '@/types/api'
import { UNIFIED_SORT_OPTIONS } from '@/lib/sort-options'
import { STORAGE_KEYS } from '@/lib/storage-keys'

const MyListPage = memo(() => {
  const navigate = useNavigate()
  const location = useLocation()
  const { folderId } = useParams<{ folderId?: string }>()
  const [myListData, setMyListData] = useState<MyListData>(() => loadMyList())
  const confirmDialog = useConfirmDialog()

  // ソート設定
  const [sortSettings, setSortSettings] = useState(() => loadSortSettings())
  const { sortType, order } = sortSettings

  // ドロップダウンの開閉状態
  const [sortDropdownOpen, setSortDropdownOpen] = useState(false)

  const handleUnifiedSortChange = useCallback((newSortType: MyListSortType, newOrder: SortOrder) => {
    const newSettings = { sortType: newSortType, order: newOrder }
    setSortSettings(newSettings)
    saveSortSettings(newSettings)
  }, [])

  // Custom hooks
  const selection = useMyListSelection()
  const folderMgmt = useFolderManagement({ myListData, setMyListData, onConfirm: confirmDialog.confirm })
  const folderNav = useFolderNavigation(folderId)
  const scrollDirection = useScrollDirection()
  useScrollToTopOnReclick(location.pathname === '/mylist' || location.pathname.startsWith('/mylist/'))

  // マイリストのアイテムIDを取得
  const itemIds = myListData.items.map(item => item.id)

  // 一括統計データ取得
  const { data: statsData, error, mutate } = useSWR<BatchStatsResponse>(
    itemIds.length > 0 ? ['batch-stats', JSON.stringify(itemIds)] : null,
    () => alphaApi.batchStats(itemIds)
  )

  const bulkOps = useBulkOperations({
    myListData,
    setMyListData,
    onMutate: mutate,
    onConfirm: confirmDialog.confirm,
  })

  // LocalStorageの変更を監視 + ページ表示時にデータをリロード
  useEffect(() => {
    const handleStorageChange = () => {
      setMyListData(loadMyList())
      mutate()
    }

    window.addEventListener('storage', handleStorageChange)
    return () => window.removeEventListener('storage', handleStorageChange)
  }, [mutate])

  // ページが表示されるたびにデータをリロード
  useEffect(() => {
    if (location.pathname === '/mylist' || location.pathname.startsWith('/mylist/')) {
      setMyListData(loadMyList())
      mutate()
    }

    // マイリストルートに来た場合、sessionStorageの最後のフォルダIDをクリア
    if (location.pathname === '/mylist' && !folderId) {
      sessionStorage.removeItem(STORAGE_KEYS.myListCurrentFolder)
    }
  }, [location.pathname, folderId, mutate])

  // location変更時にドロップダウンを閉じる
  useEffect(() => {
    setSortDropdownOpen(false)
  }, [location.pathname])

  // ブラウザバック時にドロップダウンを閉じる
  useEffect(() => {
    const handlePopstate = () => {
      setSortDropdownOpen(false)
    }

    window.addEventListener('popstate', handlePopstate)
    return () => window.removeEventListener('popstate', handlePopstate)
  }, [])

  // 同じページでボタン再クリック時のデータリロード（スクロールは useScrollToTopOnReclick）
  useEffect(() => {
    const timestamp = (location.state as { timestamp?: number } | null)?.timestamp
    if (timestamp && (location.pathname === '/mylist' || location.pathname.startsWith('/mylist/'))) {
      setMyListData(loadMyList())
      mutate()
    }
  }, [location.state, location.pathname, mutate])

  const handleUpdateData = useCallback((data: MyListData) => {
    setMyListData(data)
    saveMyList(data)
  }, [])

  const handleRemoveItem = useCallback(
    async (chatId: number) => {
      const chatData = statsData?.data.find(s => s.id === chatId)
      const chatName = chatData?.name ?? 'このアイテム'

      const confirmed = await confirmDialog.confirm({
        title: '削除の確認',
        description: `「${chatName}」をマイリストから削除しますか？`,
        confirmText: '削除',
        cancelText: 'キャンセル',
        variant: 'destructive',
      })

      if (confirmed) {
        const updated = removeItem(myListData, chatId)
        setMyListData(updated)
        mutate()
      }
    },
    [confirmDialog, myListData, mutate, statsData?.data]
  )

  const handleCardClick = useCallback((chatId: number) => {
    // マイリストから該当するデータを見つけてstateで渡す（DetailPageでのフェッチを避ける）
    const chatData = statsData?.data.find(chat => chat.id === chatId)
    navigate(`/openchat/${chatId}`, {
      state: chatData ? { initialData: chatData } : undefined
    })
  }, [navigate, statsData?.data])

  const handleFolderClick = useCallback(
    (folderId: string) => {
      // フォルダ遷移時に選択モードを解除
      if (selection.selectionMode) {
        selection.exitSelectionMode()
      }
      folderNav.navigateToFolder(folderId)
    },
    [folderNav, selection]
  )

  const handleBulkDelete = useCallback(() => {
    bulkOps.handleBulkDelete(selection.selectedIds, selection.exitSelectionMode)
  }, [bulkOps, selection.selectedIds, selection.exitSelectionMode])

  const handleBulkFolderSelect = useCallback(
    (folderId: string | null) => {
      bulkOps.handleBulkFolderSelect(folderId, selection.selectedIds, selection.exitSelectionMode)
    },
    [bulkOps, selection.selectedIds, selection.exitSelectionMode]
  )

  const handleSelectAll = useCallback(() => {
    // 現在表示中のディレクトリ内のアイテムのみ選択
    const visibleItemIds = myListData.items
      .filter(item => item.folderId === folderNav.currentFolderId)
      .map(item => item.id)
    selection.selectAll(visibleItemIds)
  }, [selection, myListData.items, folderNav.currentFolderId])

  // Early returns for error and empty states
  if (error) {
    return (
      <div className="p-3 md:p-6">
        <ErrorState />
      </div>
    )
  }
  if (myListData.items.length === 0) {
    return (
      <div className="p-3 md:p-6">
        <EmptyState />
      </div>
    )
  }

  return (
    <>
      {/* ツールバー - fixedで全幅表示 */}
      <div
        className={`fixed top-12 left-0 right-0 z-10 bg-background border-b transition-transform duration-300 select-none md:left-[var(--main-offset-md)] lg:left-[var(--main-offset-lg)] md:w-[var(--content-w)] md:border-r ${
          scrollDirection === 'down' ? '-translate-y-full' : 'translate-y-0'
        }`}
      >
        <div className="py-3">
          <div className="max-w-4xl mx-auto px-4">
            <div className="flex items-center gap-2">
              {/* モバイル：戻るボタン（左側、フォルダ内のみ） */}
              {folderNav.currentFolderId && (
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={() => {
                    const currentFolder = myListData.folders.find(f => f.id === folderNav.currentFolderId)
                    if (currentFolder) {
                      folderNav.navigateToFolder(currentFolder.parentId)
                    }
                  }}
                  data-testid="go-up-button"
                  title="上の階層へ"
                  className="md:hidden"
                >
                  <ArrowLeft className="h-4 w-4" />
                </Button>
              )}

              {/* PC・タブレット：戻るボタン（左端、フォルダ内のみ） */}
              {folderNav.currentFolderId && (
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={() => {
                    const currentFolder = myListData.folders.find(f => f.id === folderNav.currentFolderId)
                    if (currentFolder) {
                      folderNav.navigateToFolder(currentFolder.parentId)
                    }
                  }}
                  data-testid="go-up-button"
                  title="上の階層へ"
                  className="hidden md:flex"
                >
                  <ArrowLeft className="h-4 w-4" />
                </Button>
              )}

              {/* 選択モード時：BulkActionBar（PC・タブレットのみ、左側に表示） */}
              {selection.selectionMode && (
                <BulkActionBar
                  selectedCount={selection.selectedIds.size}
                  onSelectAll={handleSelectAll}
                  onBulkDelete={handleBulkDelete}
                  onBulkMove={bulkOps.handleBulkMove}
                  onExitSelectionMode={selection.exitSelectionMode}
                />
              )}

              {/* 通常のツールバー（常に表示、右側） */}
              <div className="flex items-center gap-2 ml-auto">
                {/* 統合グラフ（フォルダ内かつ非選択モードのとき）。配下ルームの成長を1つに重ねる */}
                {folderNav.currentFolderId && !selection.selectionMode &&
                  myListData.items.some(item => item.folderId === folderNav.currentFolderId) && (
                    <Button
                      variant="outline"
                      className="h-10 w-10 md:w-auto md:px-3"
                      onClick={() => navigate(`/mylist/${folderNav.currentFolderId}/chart`)}
                      data-testid="folder-chart-button"
                      title="統合グラフ"
                    >
                      <LineChart className="h-4 w-4 md:mr-2" />
                      <span className="hidden md:inline">統合グラフ</span>
                    </Button>
                  )}

                <Button
                  variant={selection.selectionMode ? 'default' : 'outline'}
                  className="h-10 w-10 md:w-auto md:px-3"
                  onClick={selection.toggleSelectionMode}
                  data-testid="selection-mode-button"
                  title={selection.selectionMode ? '選択モード終了' : '複数選択'}
                >
                  <CheckSquare className="h-4 w-4 md:mr-2" />
                  <span className="hidden md:inline">
                    {selection.selectionMode ? '選択終了' : '複数選択'}
                  </span>
                </Button>

                <Button
                  variant="outline"
                  className="h-10 w-10 md:w-auto md:px-3"
                  onClick={folderMgmt.handleCreateFolder}
                  data-testid="create-folder-button"
                  title="フォルダ作成"
                >
                  <FolderPlus className="h-4 w-4 md:mr-2" />
                  <span className="hidden md:inline">フォルダ作成</span>
                </Button>

                <DropdownMenu open={sortDropdownOpen} onOpenChange={setSortDropdownOpen}>
                  <DropdownMenuTrigger asChild>
                    <Button
                      variant="outline"
                      className="h-10"
                      data-testid="sort-dropdown-trigger"
                      title={`並び替え: ${
                        UNIFIED_SORT_OPTIONS.find(
                          opt => opt.value === sortType && opt.order === order
                        )?.label ?? '人数降順'
                      }`}
                    >
                      <ArrowUpDown className="h-4 w-4 mr-2 flex-shrink-0" />
                      <span className="truncate">
                        {UNIFIED_SORT_OPTIONS.find(
                          opt => opt.value === sortType && opt.order === order
                        )?.label ?? '人数降順'}
                      </span>
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-56" data-testid="sort-dropdown-content">
                    {UNIFIED_SORT_OPTIONS.map((option) => {
                      const isSelected = option.value === sortType && option.order === order
                      return (
                        <DropdownMenuItem
                          key={`${option.value}-${option.order}`}
                          onClick={() => handleUnifiedSortChange(option.value as MyListSortType, option.order)}
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
        </div>
      </div>

      {/* スクロール可能なコンテンツエリア */}
      <div className="absolute top-[65px] left-0 right-0 bottom-0 overflow-y-auto overflow-x-hidden">
        <div className="max-w-4xl mx-auto p-3 md:p-6">
          <div className="space-y-6">
        {!statsData && (
          <div className="flex justify-center py-8">
            <div className="text-muted-foreground">読み込み中...</div>
          </div>
        )}

        {statsData && (
          <FolderList
            currentFolderId={folderNav.currentFolderId}
            myListData={myListData}
            statsData={statsData.data}
            onFolderClick={handleFolderClick}
            onItemClick={handleCardClick}
            onItemRemove={handleRemoveItem}
            onFolderEdit={folderMgmt.handleEditFolder}
            onUpdateData={handleUpdateData}
            selectionMode={selection.selectionMode}
            selectedIds={selection.selectedIds}
            onToggleSelection={selection.toggleSelection}
            onRangeSelection={selection.selectRange}
            onEnterSelectionMode={selection.enterSelectionMode}
            sortType={sortType}
            sortOrder={order}
          />
        )}
          </div>
        </div>
      </div>

      <FolderDialog
        open={folderMgmt.dialogOpen}
        onOpenChange={folderMgmt.setDialogOpen}
        folder={folderMgmt.selectedFolder}
        folders={myListData.folders}
        onSave={folderMgmt.handleSaveFolder}
        onDelete={folderMgmt.dialogMode === 'edit' ? folderMgmt.handleDeleteFolder : undefined}
        mode={folderMgmt.dialogMode}
      />

      <FolderSelectDialog
        open={bulkOps.bulkFolderSelectOpen}
        onOpenChange={bulkOps.setBulkFolderSelectOpen}
        folders={myListData.folders}
        onSelect={handleBulkFolderSelect}
        title="移動先フォルダを選択"
      />

      <ConfirmDialog
        open={confirmDialog.isOpen}
        onOpenChange={confirmDialog.handleOpenChange}
        title={confirmDialog.options?.title ?? ''}
        description={confirmDialog.options?.description ?? ''}
        confirmText={confirmDialog.options?.confirmText}
        cancelText={confirmDialog.options?.cancelText}
        variant={confirmDialog.options?.variant}
        onConfirm={confirmDialog.handleConfirm}
        onCancel={confirmDialog.handleCancel}
      />

      {/* モバイル：選択モード時の下部固定バー */}
      {selection.selectionMode && (
        <BulkActionBarMobile
          selectedCount={selection.selectedIds.size}
          onSelectAll={handleSelectAll}
          onBulkDelete={handleBulkDelete}
          onBulkMove={bulkOps.handleBulkMove}
          onExitSelectionMode={selection.exitSelectionMode}
        />
      )}
    </>
  )
})

MyListPage.displayName = 'MyListPage'

export default MyListPage
