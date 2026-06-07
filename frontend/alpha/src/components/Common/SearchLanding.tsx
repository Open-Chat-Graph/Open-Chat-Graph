import { useCallback, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { History, Bookmark, X, Trash2, Search } from 'lucide-react'
import { CATEGORIES, categoryName } from '@/lib/categories'
import { sortMetricLabel } from '@/lib/sort-options'
import { useLayout } from '@/contexts/layout-context'
import {
  loadSearchHistory,
  removeSearchHistory,
  clearSearchHistory,
  type SearchHistoryItem,
} from '@/services/searchHistory'
import {
  loadSavedSearches,
  removeSavedSearch,
  type SavedSearch,
} from '@/services/savedSearches'
import { EmptyState } from './EmptyState'

/** 条件の短い要約（カテゴリ・ソート軸）。キーワードはチップ本体に出すのでここでは出さない。 */
function conditionSummary(category: number, sort: string, order: string): string {
  const parts: string[] = []
  if (category !== 0) parts.push(categoryName(category))
  parts.push(`${sortMetricLabel(sort)}${order === 'asc' ? '↑' : '↓'}`)
  return parts.join(' ・ ')
}

/** 初回ランディング向けの人気カテゴリ（id=0 除く上位8件） */
const FEATURED_CATEGORIES = CATEGORIES.filter((c) => c.id !== 0).slice(0, 8)

/**
 * 検索の初期/空状態ランディング。
 * 「最近の検索キーワード」（自動履歴）と「保存した検索条件」を出し、
 * タップで再検索（キーワード＋カテゴリ＋ソートを復元）する。localStorage 駆動。
 *
 * 履歴・保存条件が一切無い初回アクセスは EmptyState（カテゴリチップ）を出す。
 * 履歴がある場合の表示は現状維持。
 *
 * 履歴の自動追記は SearchPage 側（検索完了時）が行う。ここは表示・再適用・削除のみ。
 */
export function SearchLanding() {
  const [, setSearchParams] = useSearchParams()
  const { triggerSearch } = useLayout()

  const [history, setHistory] = useState<SearchHistoryItem[]>(() => loadSearchHistory())
  const [saved, setSaved] = useState<SavedSearch[]>(() => loadSavedSearches())

  const apply = useCallback(
    (q: string, category: number, sort: string, order: string) => {
      const next = new URLSearchParams()
      if (q.trim()) next.set('q', q.trim())
      if (category !== 0) next.set('category', String(category))
      next.set('sort', sort)
      next.set('order', order)
      setSearchParams(next)
      triggerSearch() // 同じ条件でも再フェッチ
    },
    [setSearchParams, triggerSearch],
  )

  const handleRemoveHistory = useCallback((q: string, category: number) => {
    setHistory(removeSearchHistory(q, category))
  }, [])

  const handleClearHistory = useCallback(() => {
    setHistory(clearSearchHistory())
  }, [])

  const handleRemoveSaved = useCallback((id: string) => {
    setSaved(removeSavedSearch(id))
  }, [])

  const hasHistory = history.length > 0
  const hasSaved = saved.length > 0

  // 履歴も保存条件も無い初回: カテゴリチップで誘導
  if (!hasHistory && !hasSaved) {
    return (
      <div data-testid="search-landing-first" className="space-y-5">
        <EmptyState
          icon={<Search />}
          title="キーワードでオプチャを探す"
          description="上の検索バーにキーワードを入力するか、カテゴリから探してみてください。"
        />
        <section className="px-1">
          <h2 className="mb-2.5 text-xs font-medium text-muted-foreground">カテゴリから探す</h2>
          <div className="flex flex-wrap gap-2">
            {FEATURED_CATEGORIES.map((cat) => (
              <button
                key={cat.id}
                type="button"
                onClick={() => apply('', cat.id, 'member', 'desc')}
                className="rounded-full border bg-card px-3 py-1.5 text-sm font-medium transition-colors hover:bg-accent hover:border-primary/40"
              >
                {cat.name}
              </button>
            ))}
          </div>
        </section>
      </div>
    )
  }

  return (
    <div className="space-y-6" data-testid="search-landing">
      {/* 最近の検索キーワード（自動履歴・チップ） */}
      {hasHistory && (
        <section>
          <div className="mb-2 flex items-center justify-between">
            <h2 className="flex items-center gap-1.5 text-sm font-medium text-foreground">
              <History className="h-4 w-4 text-muted-foreground" />
              最近の検索
            </h2>
            <button
              type="button"
              onClick={handleClearHistory}
              className="text-xs text-muted-foreground hover:text-foreground"
              data-testid="search-history-clear"
            >
              すべて消去
            </button>
          </div>
          <div className="flex flex-wrap gap-2">
            {history.map((h) => (
              <span
                key={`${h.q.toLowerCase()}|${h.category}`}
                className="group inline-flex items-center gap-1 rounded-full border bg-card py-1 pl-3 pr-1 text-sm transition-colors hover:bg-accent"
                data-testid="search-history-item"
              >
                <button
                  type="button"
                  onClick={() => apply(h.q, h.category, h.sort, h.order)}
                  className="flex min-w-0 items-center gap-1.5 text-left"
                >
                  <span className="truncate max-w-[12rem]">{h.q}</span>
                  {h.category !== 0 && (
                    <span className="text-xs text-muted-foreground">{categoryName(h.category)}</span>
                  )}
                </button>
                <button
                  type="button"
                  aria-label={`「${h.q}」を履歴から削除`}
                  title="履歴から削除"
                  onClick={() => handleRemoveHistory(h.q, h.category)}
                  className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-background hover:text-destructive"
                  data-testid="search-history-remove"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              </span>
            ))}
          </div>
        </section>
      )}

      {/* 保存した検索条件（明示保存・リスト行） */}
      {hasSaved && (
        <section>
          <h2 className="mb-2 flex items-center gap-1.5 text-sm font-medium text-foreground">
            <Bookmark className="h-4 w-4 text-muted-foreground" />
            保存した検索条件
          </h2>
          <div className="grid gap-2">
            {saved.map((s) => (
              <div
                key={s.id}
                className="flex items-center gap-2 rounded-lg border bg-card transition-colors hover:bg-accent"
                data-testid="saved-search-landing-item"
              >
                <button
                  type="button"
                  onClick={() => apply(s.q, s.category, s.sort, s.order)}
                  className="flex min-w-0 flex-1 flex-col items-start gap-0.5 px-3 py-2.5 text-left"
                >
                  <span className="truncate text-sm font-medium">{s.name}</span>
                  <span className="truncate text-xs text-muted-foreground">
                    {conditionSummary(s.category, s.sort, s.order)}
                  </span>
                </button>
                <button
                  type="button"
                  aria-label={`「${s.name}」を削除`}
                  title="削除"
                  onClick={() => handleRemoveSaved(s.id)}
                  className="mr-1 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-background hover:text-destructive"
                  data-testid="saved-search-landing-remove"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ))}
          </div>
        </section>
      )}
    </div>
  )
}
