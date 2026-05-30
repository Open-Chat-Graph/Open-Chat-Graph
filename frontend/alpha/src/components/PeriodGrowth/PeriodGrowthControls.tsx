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
import { CATEGORIES } from '@/lib/categories'

export type PeriodOrder = 'desc' | 'asc'

interface PeriodGrowthControlsProps {
  keyword: string
  category: number
  days: number
  order: PeriodOrder
  onSubmit: (next: { keyword: string; category: number; days: number; order: PeriodOrder }) => void
}

// よく使うN日プリセット。任意入力も可能。
const DAY_PRESETS: { value: number; label: string }[] = [
  { value: 7, label: '7日' },
  { value: 30, label: '30日' },
  { value: 90, label: '90日' },
  { value: 180, label: '半年' },
  { value: 365, label: '1年' },
]

export const PeriodGrowthControls = memo(
  ({ keyword, category, days, order, onSubmit }: PeriodGrowthControlsProps) => {
    // 入力はローカルで持ち、「検索」または各操作で確定する
    const [keywordInput, setKeywordInput] = useState(keyword)
    const [categoryInput, setCategoryInput] = useState(category)
    const [daysInput, setDaysInput] = useState(days)
    const [orderInput, setOrderInput] = useState<PeriodOrder>(order)

    // URL（親）側の変更に追随
    useEffect(() => setKeywordInput(keyword), [keyword])
    useEffect(() => setCategoryInput(category), [category])
    useEffect(() => setDaysInput(days), [days])
    useEffect(() => setOrderInput(order), [order])

    const commit = (overrides?: Partial<{ keyword: string; category: number; days: number; order: PeriodOrder }>) => {
      onSubmit({
        keyword: (overrides?.keyword ?? keywordInput).trim(),
        category: overrides?.category ?? categoryInput,
        days: overrides?.days ?? daysInput,
        order: overrides?.order ?? orderInput,
      })
    }

    const isPreset = DAY_PRESETS.some((p) => p.value === daysInput)

    return (
      <div className="space-y-3 rounded-lg border bg-card p-3 md:p-4">
        {/* キーワード ＋ 検索 */}
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
              placeholder="キーワード（必須）"
              className="pl-9"
              data-testid="period-growth-keyword"
            />
          </div>
          <Button type="submit" data-testid="period-growth-search" disabled={!keywordInput.trim()}>
            検索
          </Button>
        </form>

        {/* カテゴリ ＋ 並び順 */}
        <div className="flex gap-2">
          <Select
            value={String(categoryInput)}
            onValueChange={(v) => {
              const c = Number(v)
              setCategoryInput(c)
              commit({ category: c })
            }}
          >
            <SelectTrigger className="flex-1" data-testid="period-growth-category">
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
            className="flex-shrink-0 gap-1.5"
            onClick={() => {
              const next: PeriodOrder = orderInput === 'desc' ? 'asc' : 'desc'
              setOrderInput(next)
              commit({ order: next })
            }}
            data-testid="period-growth-order"
          >
            {orderInput === 'desc' ? (
              <>
                <ArrowDownWideNarrow className="h-4 w-4" />
                増加順
              </>
            ) : (
              <>
                <ArrowUpWideNarrow className="h-4 w-4" />
                減少順
              </>
            )}
          </Button>
        </div>

        {/* N日プリセット ＋ 任意入力 */}
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="mr-1 text-xs text-muted-foreground">期間</span>
          {DAY_PRESETS.map((p) => (
            <Button
              key={p.value}
              type="button"
              size="sm"
              variant={daysInput === p.value ? 'default' : 'outline'}
              className="h-8 px-3"
              onClick={() => {
                setDaysInput(p.value)
                commit({ days: p.value })
              }}
              data-testid={`period-growth-days-${p.value}`}
            >
              {p.label}
            </Button>
          ))}
          <div className="flex items-center gap-1">
            <Input
              type="number"
              min={1}
              inputMode="numeric"
              value={isPreset ? '' : String(daysInput)}
              placeholder="任意"
              onChange={(e) => {
                const n = Number(e.target.value)
                if (Number.isFinite(n) && n > 0) setDaysInput(Math.floor(n))
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault()
                  commit()
                }
              }}
              className="h-8 w-20"
              data-testid="period-growth-days-custom"
            />
            <span className="text-xs text-muted-foreground">日</span>
          </div>
        </div>
      </div>
    )
  }
)

PeriodGrowthControls.displayName = 'PeriodGrowthControls'
