/**
 * 最近の検索キーワード履歴（端末ローカル）。
 *
 * 保存クエリ（savedSearches）が「明示的に名前を付けて取っておく」ものなのに対し、
 * こちらは検索実行のたびに自動追記される「直近に何を調べたか」の足あと。
 * タップで再検索でき、カテゴリ・ソートも復元する。上限件数で古いものを捨てる。
 */
import { STORAGE_KEYS } from '@/lib/storage-keys'

const SEARCH_HISTORY_KEY = STORAGE_KEYS.searchHistory
/** 履歴の最大保持件数（古いものから捨てる）。 */
const MAX_HISTORY = 12

export interface SearchHistoryItem {
  q: string
  category: number
  sort: string
  order: string
  /** 最終検索時刻（ISO）。同じキーワードを再検索したら更新して先頭へ。 */
  searchedAt: string
}

/** 追記する条件（searchedAt はストア側で付与）。 */
export type SearchHistoryInput = Omit<SearchHistoryItem, 'searchedAt'>

export function loadSearchHistory(): SearchHistoryItem[] {
  try {
    const raw = localStorage.getItem(SEARCH_HISTORY_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? (parsed as SearchHistoryItem[]) : []
  } catch {
    return []
  }
}

function saveSearchHistory(list: SearchHistoryItem[]): void {
  try {
    localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(list))
  } catch (error) {
    console.error('Failed to save search history:', error)
  }
}

/**
 * 検索実行を履歴に追記して新しい一覧を返す（新しいものが先頭）。
 * 同じ「キーワード（小文字トリム）＋カテゴリ」は重複させず先頭へ繰り上げる。
 * キーワードが空のものは履歴に残さない（空検索＝足あとにする意味が薄い）。
 */
export function addSearchHistory(input: SearchHistoryInput): SearchHistoryItem[] {
  const q = input.q.trim()
  if (!q) return loadSearchHistory()

  const item: SearchHistoryItem = {
    q,
    category: input.category,
    sort: input.sort,
    order: input.order,
    searchedAt: new Date().toISOString(),
  }
  const key = (s: { q: string; category: number }) => `${s.q.toLowerCase()}|${s.category}`
  const dedupKey = key(item)
  const rest = loadSearchHistory().filter((s) => key(s) !== dedupKey)
  const next = [item, ...rest].slice(0, MAX_HISTORY)
  saveSearchHistory(next)
  return next
}

/** 指定の「キーワード＋カテゴリ」を履歴から削除して新しい一覧を返す。 */
export function removeSearchHistory(q: string, category: number): SearchHistoryItem[] {
  const target = `${q.trim().toLowerCase()}|${category}`
  const next = loadSearchHistory().filter((s) => `${s.q.toLowerCase()}|${s.category}` !== target)
  saveSearchHistory(next)
  return next
}

/** 履歴を全消去して空配列を返す。 */
export function clearSearchHistory(): SearchHistoryItem[] {
  saveSearchHistory([])
  return []
}
