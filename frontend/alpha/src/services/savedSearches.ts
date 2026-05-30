/**
 * 検索条件の保存（端末ローカル）。
 *
 * 認証はまだ無いフェーズなので、サーバーに持たず localStorage で完結させる。
 * 「検索条件」＝ URLパラメータの q / category / sort / order。
 * 名前付きで保存・一覧・ワンタップ再適用・削除できる。
 */
import { v4 as uuidv4 } from 'uuid'
import { STORAGE_KEYS } from '@/lib/storage-keys'

const SAVED_SEARCHES_KEY = STORAGE_KEYS.savedSearches

export interface SavedSearch {
  id: string
  name: string
  q: string
  category: number
  sort: string
  order: string
  createdAt: string
}

/** 保存できる条件（id/createdAt はストア側で付与）。 */
export type SavedSearchInput = Omit<SavedSearch, 'id' | 'createdAt'>

export function loadSavedSearches(): SavedSearch[] {
  try {
    const raw = localStorage.getItem(SAVED_SEARCHES_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? (parsed as SavedSearch[]) : []
  } catch {
    return []
  }
}

export function saveSavedSearches(list: SavedSearch[]): void {
  try {
    localStorage.setItem(SAVED_SEARCHES_KEY, JSON.stringify(list))
  } catch (error) {
    console.error('Failed to save searches:', error)
  }
}

/** 条件を追加して新しい一覧を返す（新しいものが先頭）。 */
export function addSavedSearch(input: SavedSearchInput): SavedSearch[] {
  const item: SavedSearch = {
    ...input,
    name: input.name.trim(),
    q: input.q.trim(),
    id: uuidv4(),
    createdAt: new Date().toISOString(),
  }
  const next = [item, ...loadSavedSearches()]
  saveSavedSearches(next)
  return next
}

/** 指定 id を削除して新しい一覧を返す。 */
export function removeSavedSearch(id: string): SavedSearch[] {
  const next = loadSavedSearches().filter((s) => s.id !== id)
  saveSavedSearches(next)
  return next
}
