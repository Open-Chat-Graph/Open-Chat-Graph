import { useState, useEffect, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Search, ArrowDownWideNarrow, ArrowUpWideNarrow, Check, X as XIcon, ChevronDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
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
import { CATEGORIES, categoryName } from '@/lib/categories'
import { useApplySearchParams } from '@/hooks/useApplySearchParams'
import { SavedSearchControls } from './SavedSearchControls'

const SEARCH_PLACEHOLDER = 'キーワードを入力...'

/**
 * 検索ページのヘッダー検索バー（キーワード＋カテゴリ＋ソート）。
 * ソートは「軸（メニュー）」と「昇順/降順（トグルボタン）」を分離。
 */
export function HeaderSearchBar() {
  const [searchParams, setSearchParams] = useSearchParams()
  const applySearchParams = useApplySearchParams()
  const [value, setValue] = useState('')
  const [isFocused, setIsFocused] = useState(false)

  // URL の q と入力値を同期
  useEffect(() => {
    setValue(searchParams.get('q') || '')
  }, [searchParams])

  const executeSearch = useCallback(() => {
    const q = value.trim()
    const next = new URLSearchParams(searchParams)
    if (q) next.set('q', q)
    else next.delete('q')
    // 条件が変わるなら URL 遷移（検索済みならキャッシュ即表示）、
    // 同じキーワードのままなら nonce で強制再フェッチ（従来挙動）。
    applySearchParams(next)
  }, [value, searchParams, applySearchParams])

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

      {/* 2行目: 並び順・ソート軸・カテゴリ・アラート・保存 */}
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

        {/* カテゴリ絞り込み。残り幅を埋めず内容幅（max-w-fit）にして「すべてのカ…」の
            切り詰めを防ぐ。トリガー表示は短い「全カテゴリ」（WatchSettings 等と同じ語）で、
            390px でも他コントロールと同一行に収まる。リスト項目は説明的な
            「すべてのカテゴリ」のまま。min-w は行が詰まった際の潰れ防止の下限。 */}
        <Select
          value={currentCategory}
          onValueChange={(v) => {
            const next = new URLSearchParams(searchParams)
            if (v === '0') next.delete('category')
            else next.set('category', v)
            applySearchParams(next)
          }}
        >
          <SelectTrigger className="h-10 max-w-fit min-w-[6rem]" data-testid="toolbar-category-select">
            <SelectValue placeholder="カテゴリ">
              {currentCategory === '0' ? '全カテゴリ' : categoryName(Number(currentCategory))}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            {CATEGORIES.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>
                {c.id === 0 ? 'すべてのカテゴリ' : c.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        {/* 検索条件の保存・呼び出し（「このキーワードをアラート」は検索結果ヘッダへ移設） */}
        <SavedSearchControls />
      </div>
    </div>
  )
}
