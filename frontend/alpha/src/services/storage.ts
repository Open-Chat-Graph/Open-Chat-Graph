import type { MyListData, Folder, ChatItem } from '@/types/storage'
import { v4 as uuidv4 } from 'uuid'
import { STORAGE_KEYS } from '@/lib/storage-keys'
import type { SortType, SortOrder } from '@/lib/sort-options'

const STORAGE_KEY = STORAGE_KEYS.myList
const SORT_SETTINGS_KEY = STORAGE_KEYS.myListSort
const CURRENT_VERSION = 1

export type MyListSortType = SortType
export type { SortOrder }

export interface MyListSortSettings {
  sortType: MyListSortType
  order: SortOrder
}

const DEFAULT_SORT_SETTINGS: MyListSortSettings = {
  sortType: 'member',
  order: 'desc'
}

function createInitialData(): MyListData {
  return {
    version: CURRENT_VERSION,
    folders: [],
    items: [],
    lastModified: new Date().toISOString(),
  }
}

export function loadMyList(): MyListData {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (!stored) return createInitialData()

    const data: MyListData = JSON.parse(stored)

    // バージョンチェック（将来のマイグレーション用）
    if (data.version !== CURRENT_VERSION) {
      // TODO: マイグレーション処理
      console.warn('MyList data version mismatch, creating new data')
      return createInitialData()
    }

    return data
  } catch (error) {
    console.error('Failed to load mylist:', error)
    return createInitialData()
  }
}

export function saveMyList(data: MyListData): void {
  try {
    data.lastModified = new Date().toISOString()
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data))
  } catch (error) {
    console.error('Failed to save mylist:', error)
    throw new Error('マイリストの保存に失敗しました')
  }
}

// Folder operations

export function addFolder(data: MyListData, name: string, parentId: string | null = null): MyListData {
  const newFolder: Folder = {
    id: uuidv4(),
    name,
    parentId,
    order: data.folders.filter(f => f.parentId === parentId).length,
    expanded: true,
  }

  const updated = {
    ...data,
    folders: [...data.folders, newFolder],
  }

  saveMyList(updated)
  return updated
}

export function updateFolder(data: MyListData, folderId: string, updates: Partial<Folder>): MyListData {
  const updated = {
    ...data,
    folders: data.folders.map(f => (f.id === folderId ? { ...f, ...updates } : f)),
  }

  saveMyList(updated)
  return updated
}

export function deleteFolder(data: MyListData, folderId: string): MyListData {
  // フォルダ内のアイテムを親フォルダに移動
  const folder = data.folders.find(f => f.id === folderId)
  if (!folder) return data

  // 子フォルダも削除
  const foldersToDelete = new Set([folderId])
  const collectChildFolders = (parentId: string) => {
    data.folders.forEach(f => {
      if (f.parentId === parentId) {
        foldersToDelete.add(f.id)
        collectChildFolders(f.id)
      }
    })
  }
  collectChildFolders(folderId)

  const updated = {
    ...data,
    folders: data.folders.filter(f => !foldersToDelete.has(f.id)),
    items: data.items.filter(item =>
      !(item.folderId && foldersToDelete.has(item.folderId))
    ),
  }

  saveMyList(updated)
  return updated
}

// ChatItem operations

export function addItem(data: MyListData, chatId: number, folderId: string | null = null): MyListData {
  // 既に存在する場合は追加しない
  if (data.items.some(item => item.id === chatId)) {
    return data
  }

  const newItem: ChatItem = {
    id: chatId,
    folderId,
    order: data.items.filter(item => item.folderId === folderId).length,
    addedAt: new Date().toISOString(),
  }

  const updated = {
    ...data,
    items: [...data.items, newItem],
  }

  saveMyList(updated)
  return updated
}

export function removeItem(data: MyListData, chatId: number): MyListData {
  const updated = {
    ...data,
    items: data.items.filter(item => item.id !== chatId),
  }

  saveMyList(updated)
  return updated
}

export function moveItem(
  data: MyListData,
  chatId: number,
  targetFolderId: string | null,
  newOrder: number
): MyListData {
  const item = data.items.find(i => i.id === chatId)
  if (!item) return data

  // 同じフォルダ内の他のアイテムの順序を調整
  const updatedItems = data.items.map(i => {
    if (i.id === chatId) {
      return { ...i, folderId: targetFolderId, order: newOrder }
    }

    // 元のフォルダ内のアイテムの順序を詰める
    if (i.folderId === item.folderId && i.order > item.order) {
      return { ...i, order: i.order - 1 }
    }

    // 移動先フォルダ内のアイテムの順序をずらす
    if (i.folderId === targetFolderId && i.order >= newOrder) {
      return { ...i, order: i.order + 1 }
    }

    return i
  })

  const updated = {
    ...data,
    items: updatedItems,
  }

  saveMyList(updated)
  return updated
}

export function updateItemsOrder(data: MyListData, items: ChatItem[]): MyListData {
  const updated = {
    ...data,
    items,
  }

  saveMyList(updated)
  return updated
}

// Utility functions

export function isInMyList(data: MyListData, chatId: number): boolean {
  return data.items.some(item => item.id === chatId)
}

export function toggleFolder(data: MyListData, folderId: string): MyListData {
  return updateFolder(data, folderId, {
    expanded: !data.folders.find(f => f.id === folderId)?.expanded,
  })
}

export function getFolderChildren(data: MyListData, folderId: string | null): { folders: Folder[]; items: ChatItem[] } {
  return {
    folders: data.folders.filter(f => f.parentId === folderId).sort((a, b) => a.order - b.order),
    items: data.items.filter(item => item.folderId === folderId).sort((a, b) => a.order - b.order),
  }
}

export function getFolderItems(data: MyListData, folderId: string | null): ChatItem[] {
  return data.items.filter(item => item.folderId === folderId).sort((a, b) => a.order - b.order)
}

// Bulk operations
export function bulkRemoveItems(data: MyListData, chatIds: number[]): MyListData {
  return {
    ...data,
    items: data.items.filter((item) => !chatIds.includes(item.id)),
  }
}

export function bulkMoveItems(
  data: MyListData,
  chatIds: number[],
  targetFolderId: string | null
): MyListData {
  return {
    ...data,
    items: data.items.map((item) =>
      chatIds.includes(item.id) ? { ...item, folderId: targetFolderId } : item
    ),
  }
}

// Sort settings
export function loadSortSettings(): MyListSortSettings {
  try {
    const stored = localStorage.getItem(SORT_SETTINGS_KEY)
    if (!stored) return DEFAULT_SORT_SETTINGS
    return JSON.parse(stored)
  } catch {
    return DEFAULT_SORT_SETTINGS
  }
}

export function saveSortSettings(settings: MyListSortSettings): void {
  try {
    localStorage.setItem(SORT_SETTINGS_KEY, JSON.stringify(settings))
  } catch (error) {
    console.error('Failed to save sort settings:', error)
  }
}

// Sort logic
export function sortChatItems(
  items: ChatItem[],
  statsData: Array<{ id: number; member: number; createdAt?: number | null; increasedMember: number; diff24h: number; diff1w: number; isInRanking: boolean }>,
  sortType: MyListSortType,
  order: SortOrder
): ChatItem[] {
  const getStatValue = (chatId: number, key: string) => {
    const stat = statsData.find(s => s.id === chatId)
    if (!stat) return 0

    switch (key) {
      case 'member':
        return stat.member || 0
      case 'created_at':
        return stat.createdAt || 0
      case 'hourly_diff':
        return stat.increasedMember || 0
      case 'diff_24h':
        return stat.diff24h || 0
      case 'diff_1w':
        return stat.diff1w || 0
      default:
        return 0
    }
  }

  const getIsInRanking = (chatId: number): boolean => {
    const stat = statsData.find(s => s.id === chatId)
    return stat?.isInRanking ?? false
  }

  const getMemberCount = (chatId: number): number => {
    const stat = statsData.find(s => s.id === chatId)
    return stat?.member || 0
  }

  // 1・24時間ソートの場合のみ is_in_ranking を優先（ランキング非掲載を最下位に）
  const useRankingSeparation = sortType === 'hourly_diff' || sortType === 'diff_24h'

  const sorted = [...items].sort((a, b) => {
    // 1・24時間ソートの場合、is_in_rankingで先に分ける
    if (useRankingSeparation) {
      const aIsInRanking = getIsInRanking(a.id) ? 1 : 0
      const bIsInRanking = getIsInRanking(b.id) ? 1 : 0

      if (aIsInRanking !== bIsInRanking) {
        return bIsInRanking - aIsInRanking // is_in_ranking=1 が先
      }
    }

    // 主要なソート値で比較
    const aVal = getStatValue(a.id, sortType)
    const bVal = getStatValue(b.id, sortType)

    if (aVal !== bVal) {
      return order === 'asc' ? aVal - bVal : bVal - aVal
    }

    // 同じ値の場合、メンバー数で降順ソート（タイブレーカー）
    const aMember = getMemberCount(a.id)
    const bMember = getMemberCount(b.id)
    return bMember - aMember
  })

  return sorted
}

// Sort folders alphabetically by name
export function sortFoldersByName(folders: Folder[]): Folder[] {
  return [...folders].sort((a, b) => a.name.localeCompare(b.name, 'ja'))
}
