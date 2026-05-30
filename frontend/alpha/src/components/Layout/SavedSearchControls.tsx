import { useState, useEffect, useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Bookmark, BookmarkCheck, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  loadSavedSearches,
  addSavedSearch,
  removeSavedSearch,
  type SavedSearch,
} from '@/services/savedSearches'
import { categoryName } from '@/lib/categories'
import { sortMetricLabel } from '@/lib/sort-options'
import { useLayout } from '@/contexts/layout-context'

/** 自動命名: 「キーワード（カテゴリ名）」。キーワードが空ならカテゴリ名のみ。 */
function buildDefaultName(q: string, category: number): string {
  const cat = category === 0 ? '' : categoryName(category)
  const kw = q.trim()
  if (kw && cat) return `${kw}（${cat}）`
  if (kw) return kw
  if (cat) return cat
  return '検索条件'
}

/** 条件の要約（一覧の二行目に出す）。 */
function summarize(s: SavedSearch): string {
  const parts: string[] = []
  if (s.category !== 0) parts.push(categoryName(s.category))
  parts.push(`${sortMetricLabel(s.sort)}${s.order === 'asc' ? '↑' : '↓'}`)
  return parts.join(' ・ ')
}

/**
 * 検索条件を保存・一覧・再適用・削除する最小UI。
 * ツールバーの構造は壊さず、アイコンボタン2つだけ足す想定。
 * 現在条件は useSearchParams から読む。
 */
export function SavedSearchControls() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { triggerSearch } = useLayout()

  const [saved, setSaved] = useState<SavedSearch[]>(() => loadSavedSearches())
  const [dialogOpen, setDialogOpen] = useState(false)
  const [name, setName] = useState('')

  const q = searchParams.get('q') || ''
  const category = Number(searchParams.get('category')) || 0
  const sort = searchParams.get('sort') || 'member'
  const order = searchParams.get('order') || 'desc'

  // 保存する条件があるか（キーワードまたはカテゴリ指定）。空っぽなら保存させない。
  const hasConditions = q.trim() !== '' || category !== 0

  const openSaveDialog = useCallback(() => {
    setName(buildDefaultName(q, category))
    setDialogOpen(true)
  }, [q, category])

  // ダイアログを開くたびに既定名を入れ直す（条件が変わっている可能性があるため）
  useEffect(() => {
    if (dialogOpen) setName(buildDefaultName(q, category))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dialogOpen])

  const handleSave = useCallback(() => {
    const trimmed = name.trim()
    if (!trimmed) return
    const next = addSavedSearch({ name: trimmed, q, category, sort, order })
    setSaved(next)
    setDialogOpen(false)
  }, [name, q, category, sort, order])

  const handleApply = useCallback(
    (s: SavedSearch) => {
      const next = new URLSearchParams()
      if (s.q.trim()) next.set('q', s.q.trim())
      if (s.category !== 0) next.set('category', String(s.category))
      next.set('sort', s.sort)
      next.set('order', s.order)
      setSearchParams(next)
      triggerSearch() // 同じ条件でも再フェッチ
    },
    [setSearchParams, triggerSearch],
  )

  const handleRemove = useCallback((id: string) => {
    setSaved(removeSavedSearch(id))
  }, [])

  const hasSaved = saved.length > 0
  const dialogDescription = useMemo(() => {
    const parts: string[] = []
    if (q.trim()) parts.push(`キーワード「${q.trim()}」`)
    if (category !== 0) parts.push(categoryName(category))
    parts.push(`${sortMetricLabel(sort)}${order === 'asc' ? '昇順' : '降順'}`)
    return parts.join(' ・ ')
  }, [q, category, sort, order])

  return (
    <>
      {/* 現在の条件を保存 */}
      <Button
        variant="outline"
        size="icon"
        className="h-10 w-10 flex-shrink-0"
        aria-label="現在の検索条件を保存"
        title="現在の検索条件を保存"
        disabled={!hasConditions}
        onClick={openSaveDialog}
        data-testid="saved-search-save"
      >
        <Bookmark className="h-4 w-4" />
      </Button>

      {/* 保存済み検索の一覧 */}
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="outline"
            size="icon"
            className="h-10 w-10 flex-shrink-0 relative"
            aria-label="保存した検索条件"
            title="保存した検索条件"
            data-testid="saved-search-list-trigger"
          >
            <BookmarkCheck className="h-4 w-4" />
            {hasSaved && (
              <span className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-medium leading-none text-primary-foreground">
                {saved.length}
              </span>
            )}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-72" data-testid="saved-search-list-content">
          <DropdownMenuLabel>保存した検索条件</DropdownMenuLabel>
          <DropdownMenuSeparator />
          {!hasSaved && (
            <div className="px-3 py-4 text-sm text-muted-foreground">
              まだ保存された条件はありません。
            </div>
          )}
          {saved.map((s) => (
            <DropdownMenuItem
              key={s.id}
              onSelect={() => handleApply(s)}
              className="flex items-start gap-2"
              data-testid="saved-search-item"
            >
              <div className="min-w-0 flex-1">
                <div className="truncate font-medium">{s.name}</div>
                <div className="truncate text-xs text-muted-foreground">{summarize(s)}</div>
              </div>
              <button
                type="button"
                aria-label={`「${s.name}」を削除`}
                title="削除"
                onClick={(e) => {
                  e.stopPropagation()
                  handleRemove(s.id)
                }}
                className="-mr-1 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-destructive"
                data-testid="saved-search-remove"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>

      {/* 名前を付けて保存 */}
      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>検索条件を保存</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="space-y-1.5">
              <Label htmlFor="saved-search-name">名前</Label>
              <Input
                id="saved-search-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault()
                    handleSave()
                  }
                }}
                autoFocus
                maxLength={60}
                data-testid="saved-search-name-input"
              />
            </div>
            <p className="text-xs text-muted-foreground">{dialogDescription}</p>
          </div>
          <DialogFooter className="flex flex-col-reverse gap-2 sm:flex-row sm:gap-3">
            <Button variant="outline" onClick={() => setDialogOpen(false)} className="flex-1">
              キャンセル
            </Button>
            <Button onClick={handleSave} disabled={!name.trim()} className="flex-1" data-testid="saved-search-confirm">
              保存
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
