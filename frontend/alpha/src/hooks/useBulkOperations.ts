import { useState, useCallback } from 'react'
import { bulkRemoveItems, bulkMoveItems, saveMyList } from '@/services/storage'
import type { MyListData } from '@/types/storage'

interface UseBulkOperationsProps {
  myListData: MyListData
  setMyListData: (data: MyListData) => void
  onMutate?: () => void
  onConfirm: (options: {
    title: string
    description: string
    confirmText?: string
    cancelText?: string
    variant?: 'default' | 'destructive'
  }) => Promise<boolean>
}

export function useBulkOperations({
  myListData,
  setMyListData,
  onMutate,
  onConfirm,
}: UseBulkOperationsProps) {
  const [bulkFolderSelectOpen, setBulkFolderSelectOpen] = useState(false)

  const handleBulkDelete = useCallback(
    async (selectedIds: Set<number>, onComplete: () => void) => {
      const confirmed = await onConfirm({
        title: '一括削除の確認',
        description: `${selectedIds.size}件のアイテムを削除しますか？この操作は取り消せません。`,
        confirmText: '削除',
        cancelText: 'キャンセル',
        variant: 'destructive',
      })

      if (confirmed) {
        const updated = bulkRemoveItems(myListData, Array.from(selectedIds))
        setMyListData(updated)
        saveMyList(updated)
        onComplete()
        onMutate?.()
      }
    },
    [myListData, setMyListData, onConfirm, onMutate]
  )

  const handleBulkMove = useCallback(() => {
    setBulkFolderSelectOpen(true)
  }, [])

  const handleBulkFolderSelect = useCallback(
    (folderId: string | null, selectedIds: Set<number>, onComplete: () => void) => {
      const updated = bulkMoveItems(myListData, Array.from(selectedIds), folderId)
      setMyListData(updated)
      saveMyList(updated)
      setBulkFolderSelectOpen(false)
      onComplete()
    },
    [myListData, setMyListData]
  )

  return {
    bulkFolderSelectOpen,
    setBulkFolderSelectOpen,
    handleBulkDelete,
    handleBulkMove,
    handleBulkFolderSelect,
  }
}
