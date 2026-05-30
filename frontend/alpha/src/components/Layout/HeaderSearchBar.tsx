import { useState, useEffect, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Search, ArrowDownWideNarrow, ArrowUpWideNarrow, Check, X as XIcon, ChevronDown, Eye, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAlertsConfig } from '@/components/Notifications'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { SORT_METRICS, sortMetricLabel } from '@/lib/sort-options'
import { CATEGORIES } from '@/lib/categories'
import { useLayout } from '@/contexts/layout-context'
import { SavedSearchControls } from './SavedSearchControls'

const SEARCH_PLACEHOLDER = 'キーワードを入力...'

/**
 * 検索ページのヘッダー検索バー（キーワード＋カテゴリ＋ソート）。
 * ソートは「軸（メニュー）」と「昇順/降順（トグルボタン）」を分離。
 */
export function HeaderSearchBar() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { triggerSearch } = useLayout()
  const { addKeyword } = useAlertsConfig()
  const [value, setValue] = useState('')
  const [isFocused, setIsFocused] = useState(false)
  // このキーワードを見張る導線の状態: idle / saving / done（追加済み or 既に存在）
  const [watchState, setWatchState] = useState<'idle' | 'saving' | 'done'>('idle')

  // URL の q と入力値を同期
  useEffect(() => {
    setValue(searchParams.get('q') || '')
  }, [searchParams])

  const executeSearch = useCallback(() => {
    const q = value.trim()
    const next = new URLSearchParams(searchParams)
    if (q) next.set('q', q)
    else next.delete('q')
    setSearchParams(next)
    triggerSearch() // 同じキーワードでも再フェッチさせる
  }, [value, searchParams, setSearchParams, triggerSearch])

  // iOS Safari: 入力中（IME確定）のタップを優先し、他要素のクリックを一時的に抑止
  useEffect(() => {
    if (!isFocused) return

    const handleClick = (e: MouseEvent) => {
      const target = e.target as HTMLElement
      const searchInput = document.querySelector(`input[placeholder="${SEARCH_PLACEHOLDER}"]`)
      if (searchInput?.contains(target) || target.closest('button[aria-label="クリア"]')) return
      e.preventDefault()
      e.stopPropagation()
    }

    document.addEventListener('click', handleClick, true)
    return () => document.removeEventListener('click', handleClick, true)
  }, [isFocused])

  const currentSort = searchParams.get('sort') || 'member'
  const currentOrder = searchParams.get('order') || 'desc'
  const currentCategory = searchParams.get('category') || '0'

  // 検索中のキーワード（URL の q）。これを見張り対象にする。
  const appliedKeyword = (searchParams.get('q') || '').trim()

  // 見張り対象のキーワード/カテゴリが変わったらボタン状態をリセット
  useEffect(() => {
    setWatchState('idle')
  }, [appliedKeyword, currentCategory])

  const handleWatchKeyword = useCallback(async () => {
    if (!appliedKeyword || watchState !== 'idle') return
    setWatchState('saving')
    try {
      const cat = currentCategory === '0' ? null : Number(currentCategory)
      await addKeyword({ keyword: appliedKeyword, category: cat })
      setWatchState('done')
    } catch {
      setWatchState('idle')
    }
  }, [appliedKeyword, currentCategory, watchState, addKeyword])

  const updateParam = useCallback(
    (mutate: (p: URLSearchParams) => void) => {
      const next = new URLSearchParams(searchParams)
      mutate(next)
      setSearchParams(next)
    },
    [searchParams, setSearchParams],
  )

  return (
    <div className="border-t md:border-t-0 md:border-b bg-background px-3 py-2 md:px-4 space-y-2">
      {/* 1行目: キーワード入力（常に全幅・潰さない） */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <input
          type="text"
          placeholder={SEARCH_PLACEHOLDER}
          className="w-full h-10 pl-10 pr-12 rounded-md border border-input bg-background text-base"
          value={value}
          onChange={(e) => setValue(e.target.value)}
          onFocus={() => setIsFocused(true)}
          onBlur={() => {
            setTimeout(() => setIsFocused(false), 100)
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              executeSearch()
              e.currentTarget.blur()
            }
          }}
        />
        {value && (
          <button
            type="button"
            onClick={() => setValue('')}
            className="absolute right-1 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors p-2 rounded-md hover:bg-accent w-8 h-8 flex items-center justify-center"
            aria-label="クリア"
          >
            <XIcon className="h-5 w-5" />
          </button>
        )}
      </div>

      {/* 2行目: 並び順・ソート軸・カテゴリ・見張り・保存 */}
      <div className="flex items-center gap-2">
        {/* 昇順/降順トグル */}
        <Button
          variant="outline"
          size="icon"
          className="h-10 w-10 flex-shrink-0"
          aria-label={currentOrder === 'desc' ? '降順（タップで昇順）' : '昇順（タップで降順）'}
          title={currentOrder === 'desc' ? '降順' : '昇順'}
          onClick={() => updateParam((p) => p.set('order', currentOrder === 'desc' ? 'asc' : 'desc'))}
          data-testid="toolbar-order-toggle"
        >
          {currentOrder === 'desc' ? <ArrowDownWideNarrow className="h-4 w-4" /> : <ArrowUpWideNarrow className="h-4 w-4" />}
        </Button>

        {/* ソート軸メニュー（昇降は含めない。作成日順は最下部） */}
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" className="h-10 gap-1 px-3 flex-shrink-0" data-testid="toolbar-sort-dropdown-trigger">
              <span className="text-sm">{sortMetricLabel(currentSort)}</span>
              <ChevronDown className="h-4 w-4 opacity-60" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" sideOffset={6} className="w-44" data-testid="toolbar-sort-dropdown-content">
            {SORT_METRICS.map((m) => (
              <DropdownMenuItem
                key={m.value}
                onClick={() => updateParam((p) => p.set('sort', m.value))}
                data-testid={`toolbar-sort-option-${m.value}`}
              >
                <div className="flex items-center gap-2 w-full">
                  <Check className={`h-4 w-4 ${m.value === currentSort ? 'opacity-100' : 'opacity-0'}`} />
                  <span>{m.label}</span>
                </div>
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>

        {/* カテゴリ絞り込み（残り幅を埋める。デスクトップは控えめに） */}
        <Select
          value={currentCategory}
          onValueChange={(v) => updateParam((p) => (v === '0' ? p.delete('category') : p.set('category', v)))}
        >
          <SelectTrigger className="h-10 flex-1 min-w-0 md:max-w-[220px]" data-testid="toolbar-category-select">
            <SelectValue placeholder="カテゴリ" />
          </SelectTrigger>
          <SelectContent>
            {CATEGORIES.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>
                {c.id === 0 ? 'すべてのカテゴリ' : c.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        {/* このキーワードを見張る（検索中のキーワードがあるときだけ） */}
        {appliedKeyword && (
          <Button
            variant={watchState === 'done' ? 'secondary' : 'outline'}
            size="icon"
            className="h-10 w-10 flex-shrink-0"
            onClick={handleWatchKeyword}
            disabled={watchState !== 'idle'}
            aria-label={
              watchState === 'done' ? 'このキーワードを見張り中' : 'このキーワードを見張る'
            }
            title={
              watchState === 'done'
                ? '見張りに追加しました'
                : `「${appliedKeyword}」を見張る`
            }
            data-testid="watch-current-keyword"
          >
            {watchState === 'saving' ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : watchState === 'done' ? (
              <Check className="h-4 w-4" />
            ) : (
              <Eye className="h-4 w-4" />
            )}
          </Button>
        )}

        {/* 検索条件の保存・呼び出し */}
        <SavedSearchControls />
      </div>
    </div>
  )
}
