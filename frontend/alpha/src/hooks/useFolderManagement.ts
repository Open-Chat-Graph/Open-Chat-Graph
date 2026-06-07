import { useState, useCallback } from 'react'
import { addFolder, updateFolder, deleteFolder } from '@/services/storage'
import type { MyListData, Folder } from '@/types/storage'
import type { ConfirmOptions } from '@/hooks/useConfirmDialog'

interface UseFolderManagementProps {
  myListData: MyListData
  setMyListData: (data: MyListData) => void
  onConfirm: (options: ConfirmOptions) => Promise<boolean>
  /** フォルダ新規作成直後に呼ばれる（作成→フォルダ設定を1動線にする導線用） */
  onFolderCreated?: (folder: Folder) => void
}

export function useFolderManagement({ myListData, setMyListData, onConfirm, onFolderCreated }: UseFolderManagementProps) {
  const [dialogOpen, setDialogOpen] = useState(false)
  const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create')
  const [selectedFolder, setSelectedFolder] = useState<Folder | undefined>()

  const handleCreateFolder = useCallback(() => {
    setDialogMode('create')
    setSelectedFolder(undefined)
    setDialogOpen(true)
  }, [])

  const handleEditFolder = useCallback((folder: Folder) => {
    setDialogMode('edit')
    setSelectedFolder(folder)
    setDialogOpen(true)
  }, [])

  const handleSaveFolder = useCallback(
    (name: string, parentId: string | null) => {
      if (dialogMode === 'create') {
        const updated = addFolder(myListData, name, parentId)
        setMyListData(updated)
        // addFolder は末尾に追加するため、最後の要素が作成されたフォルダ
        const created = updated.folders[updated.folders.length - 1]
        if (created) onFolderCreated?.(created)
      } else if (selectedFolder) {
        const updated = updateFolder(myListData, selectedFolder.id, { name, parentId })
        setMyListData(updated)
      }
    },
    [dialogMode, myListData, selectedFolder, setMyListData, onFolderCreated]
  )

  const handleDeleteFolder = useCallback(async () => {
    if (selectedFolder) {
      const confirmed = await onConfirm({
        title: '削除の確認',
        description: `「${selectedFolder.name}」を削除しますか？フォルダ内のアイテムもすべて削除されます。`,
        confirmText: '削除',
        cancelText: 'キャンセル',
        variant: 'destructive',
      })

      if (confirmed) {
        const updated = deleteFolder(myListData, selectedFolder.id)
        setMyListData(updated)
      }
    }
  }, [myListData, selectedFolder, setMyListData, onConfirm])

  return {
    dialogOpen,
    setDialogOpen,
    dialogMode,
    selectedFolder,
    handleCreateFolder,
    handleEditFolder,
    handleSaveFolder,
    handleDeleteFolder,
  }
}
