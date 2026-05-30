import { useState, useEffect, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Search, ArrowUpDown, Check, X as XIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { UNIFIED_SORT_OPTIONS } from '@/lib/sort-options'
import { useLayout } from '@/contexts/layout-context'

const SEARCH_PLACEHOLDER = 'キーワードを入力...'

/**
 * 検索ページのヘッダー検索バー（キーワード入力＋ソート）。
 * 旧 DashboardLayout に直書きされていたものを切り出した。
 */
export function HeaderSearchBar() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { triggerSearch } = useLayout()
  const [value, setValue] = useState('')
  const [isFocused, setIsFocused] = useState(false)

  // URL の q と入力値を同期
  useEffect(() => {
    setValue(searchParams.get('q') || '')
  }, [searchParams])

  const executeSearch = useCallback(() => {
    const q = value.trim()
    if (q) {
      setSearchParams({ q })
      triggerSearch() // 同じキーワードでも再フェッチさせる
    } else {
      setSearchParams({})
    }
  }, [value, setSearchParams, triggerSearch])

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

  return (
    <div className="border-t md:border-t-0 md:border-b bg-background px-3 py-2 md:px-4">
      <div className="flex gap-2">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            type="text"
            placeholder={SEARCH_PLACEHOLDER}
            className="w-full h-10 pl-10 pr-12 rounded-md border border-input bg-background text-base"
            value={value}
            onChange={(e) => setValue(e.target.value)}
            onFocus={() => setIsFocused(true)}
            onBlur={() => {
              // IME確定後のタップを正しく処理するため少し遅延
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
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" className="h-10 px-3 flex-shrink-0" data-testid="toolbar-sort-dropdown-trigger">
              <ArrowUpDown className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-56" data-testid="toolbar-sort-dropdown-content">
            {UNIFIED_SORT_OPTIONS.map((option) => {
              const isSelected = option.value === currentSort && option.order === currentOrder
              return (
                <DropdownMenuItem
                  key={`${option.value}-${option.order}`}
                  onClick={() => {
                    const newParams = new URLSearchParams(searchParams)
                    newParams.set('sort', option.value)
                    newParams.set('order', option.order)
                    setSearchParams(newParams)
                  }}
                  data-testid={`toolbar-sort-option-${option.value}-${option.order}`}
                >
                  <div className="flex items-center gap-2 w-full">
                    <Check className={`h-4 w-4 ${isSelected ? 'opacity-100' : 'opacity-0'}`} />
                    <span>{option.label}</span>
                  </div>
                </DropdownMenuItem>
              )
            })}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>
  )
}
