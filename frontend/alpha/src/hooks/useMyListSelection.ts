import { useState, useCallback, useRef } from 'react'

export function useMyListSelection() {
  const [selectionMode, setSelectionMode] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const lastSelectedIdRef = useRef<number | null>(null)

  const toggleSelectionMode = useCallback(() => {
    setSelectionMode(prev => !prev)
    setSelectedIds(new Set())
    lastSelectedIdRef.current = null
  }, [])

  const toggleSelection = useCallback((chatId: number) => {
    setSelectedIds(prev => {
      const newSet = new Set(prev)
      if (newSet.has(chatId)) {
        newSet.delete(chatId)
      } else {
        newSet.add(chatId)
        lastSelectedIdRef.current = chatId
      }
      return newSet
    })
  }, [])

  const selectRange = useCallback((chatId: number, allItemIds: number[]) => {
    const lastId = lastSelectedIdRef.current
    if (lastId === null || allItemIds.length === 0) {
      // 前回選択がない場合は通常の選択
      toggleSelection(chatId)
      return
    }

    const startIndex = allItemIds.indexOf(lastId)
    const endIndex = allItemIds.indexOf(chatId)

    if (startIndex === -1 || endIndex === -1) {
      // インデックスが見つからない場合は通常の選択
      toggleSelection(chatId)
      return
    }

    const [minIndex, maxIndex] = startIndex <= endIndex
      ? [startIndex, endIndex]
      : [endIndex, startIndex]

    const rangeIds = allItemIds.slice(minIndex, maxIndex + 1)

    setSelectedIds(prev => {
      const newSet = new Set(prev)
      rangeIds.forEach(id => newSet.add(id))
      return newSet
    })

    lastSelectedIdRef.current = chatId
  }, [toggleSelection])

  const selectAll = useCallback((itemIds: number[]) => {
    setSelectedIds(new Set(itemIds))
    lastSelectedIdRef.current = null
  }, [])

  const clearSelection = useCallback(() => {
    setSelectedIds(new Set())
    lastSelectedIdRef.current = null
  }, [])

  const enterSelectionMode = useCallback(() => {
    setSelectionMode(true)
  }, [])

  const exitSelectionMode = useCallback(() => {
    setSelectionMode(false)
    setSelectedIds(new Set())
    lastSelectedIdRef.current = null
  }, [])

  return {
    selectionMode,
    selectedIds,
    toggleSelectionMode,
    toggleSelection,
    selectRange,
    selectAll,
    clearSelection,
    enterSelectionMode,
    exitSelectionMode,
  }
}
