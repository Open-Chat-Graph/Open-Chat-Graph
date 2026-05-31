import { memo, useState, useEffect } from 'react'
import { Search, ArrowDownWideNarrow, ArrowUpWideNarrow } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { PeriodRangePicker } from '@/components/ui/period-range-picker'
import { CATEGORIES } from '@/lib/categories'
import type { PeriodValue } from '@/lib/period'

export type PeriodOrder = 'desc' | 'asc'

export interface PeriodGrowthQuery {
  keyword: string
  category: number
  period: PeriodValue
  order: PeriodOrder
}

interface PeriodGrowthControlsProps extends PeriodGrowthQuery {
  onSubmit: (next: PeriodGrowthQuery) => void
}

export const PeriodGrowthControls = memo(
  ({ keyword, category, period, order, onSubmit }: PeriodGrowthControlsProps) => {
    const [keywordInput, setKeywordInput] = useState(keyword)
    const [categoryInput, setCategoryInput] = useState(category)
    const [periodInput, setPeriodInput] = useState<PeriodValue>(period)
    const [orderInput, setOrderInput] = useState<PeriodOrder>(order)

    // URL（親）側の変更に追随
    useEffect(() => setKeywordInput(keyword), [keyword])
    useEffect(() => setCategoryInput(category), [category])
    useEffect(() => setPeriodInput(period), [period])
    useEffect(() => setOrderInput(order), [order])

    const commit = (overrides?: Partial<PeriodGrowthQuery>) => {
      onSubmit({
        keyword: (overrides?.keyword ?? keywordInput).trim(),
        category: overrides?.category ?? categoryInput,
        period: overrides?.period ?? periodInput,
        order: overrides?.order ?? orderInput,
      })
    }

    return (
      <div className="space-y-2 rounded-lg border bg-card p-3 md:p-4">
        {/* 1段目: キーワード ＋ 検索 */}
        <form
          className="flex gap-2"
          onSubmit={(e) => {
            e.preventDefault()
            commit()
          }}
        >
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={keywordInput}
              onChange={(e) => setKeywordInput(e.target.value)}
              placeholder="キーワード（空欄で全件）"
              className="pl-9 text-base md:text-sm"
              data-testid="period-growth-keyword"
            />
          </div>
          <Button type="submit" data-testid="period-growth-search">
            検索
          </Button>
        </form>

        {/* 2段目: カテゴリ ＋ 並び順 ＋ 期間ピッカー */}
        <div className="flex flex-wrap items-center gap-2">
          <Select
            value={String(categoryInput)}
            onValueChange={(v) => {
              const c = Number(v)
              setCategoryInput(c)
              commit({ category: c })
            }}
          >
            <SelectTrigger className="h-8 min-w-0 flex-1 text-xs" data-testid="period-growth-category">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {CATEGORIES.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>
                  {c.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Button
            type="button"
            variant="outline"
            size="sm"
            className="h-8 flex-shrink-0 gap-1 px-3 text-xs"
            onClick={() => {
              const next: PeriodOrder = orderInput === 'desc' ? 'asc' : 'desc'
              setOrderInput(next)
              commit({ order: next })
            }}
            data-testid="period-growth-order"
          >
            {orderInput === 'desc' ? (
              <>
                <ArrowDownWideNarrow className="h-3.5 w-3.5" />
                増加順
              </>
            ) : (
              <>
                <ArrowUpWideNarrow className="h-3.5 w-3.5" />
                減少順
              </>
            )}
          </Button>

          <PeriodRangePicker
            value={periodInput}
            allowAll={false}
            onChange={(next) => {
              setPeriodInput(next)
              commit({ period: next })
            }}
            data-testid="period-growth-period"
          />
        </div>
      </div>
    )
  }
)

PeriodGrowthControls.displayName = 'PeriodGrowthControls'
