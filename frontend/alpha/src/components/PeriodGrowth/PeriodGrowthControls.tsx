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

export interface PeriodGrowthQuery {
  keyword: string
  category: number
  start: string // 開始日（Y-m-d）。空なら未指定
  end: string // 終了日（Y-m-d）。空なら未指定
  order: PeriodOrder
}

interface PeriodGrowthControlsProps extends PeriodGrowthQuery {
  onSubmit: (next: PeriodGrowthQuery) => void
}

// ローカル日付を Y-m-d へ（タイムゾーンずれを避けるため UTC ではなくローカル基準）。
const toYmd = (d: Date): string =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

// 「終了日=今日、開始日=今日-N日」のクイック選択プリセット。
const DAY_PRESETS: { days: number; label: string }[] = [
  { days: 7, label: '7日' },
  { days: 30, label: '30日' },
  { days: 90, label: '90日' },
  { days: 180, label: '半年' },
  { days: 365, label: '1年' },
]

// 開始/終了が「終了日=今日 かつ 開始日=今日-N日」に一致するプリセット日数を返す（一致しなければ null）。
const matchedPresetDays = (start: string, end: string): number | null => {
  if (!start || !end) return null
  const today = toYmd(new Date())
  if (end !== today) return null
  for (const p of DAY_PRESETS) {
    const d = new Date()
    d.setDate(d.getDate() - p.days)
    if (toYmd(d) === start) return p.days
  }
  return null
}

export const PeriodGrowthControls = memo(
  ({ keyword, category, start, end, order, onSubmit }: PeriodGrowthControlsProps) => {
    // 入力はローカルで持ち、「検索」または各操作で確定する
    const [keywordInput, setKeywordInput] = useState(keyword)
    const [categoryInput, setCategoryInput] = useState(category)
    const [startInput, setStartInput] = useState(start)
    const [endInput, setEndInput] = useState(end)
    const [orderInput, setOrderInput] = useState<PeriodOrder>(order)

    // URL（親）側の変更に追随
    useEffect(() => setKeywordInput(keyword), [keyword])
    useEffect(() => setCategoryInput(category), [category])
    useEffect(() => setStartInput(start), [start])
    useEffect(() => setEndInput(end), [end])
    useEffect(() => setOrderInput(order), [order])

    const commit = (overrides?: Partial<PeriodGrowthQuery>) => {
      onSubmit({
        keyword: (overrides?.keyword ?? keywordInput).trim(),
        category: overrides?.category ?? categoryInput,
        start: overrides?.start ?? startInput,
        end: overrides?.end ?? endInput,
        order: overrides?.order ?? orderInput,
      })
    }

    const activePreset = matchedPresetDays(startInput, endInput)

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
              placeholder="キーワード（空欄で全件）"
              className="pl-9"
              data-testid="period-growth-keyword"
            />
          </div>
          <Button type="submit" data-testid="period-growth-search">
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

        {/* クイック選択プリセット（終了日=今日、開始日=今日-N日） */}
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="mr-1 text-xs text-muted-foreground">期間</span>
          {DAY_PRESETS.map((p) => {
            const today = new Date()
            const from = new Date()
            from.setDate(from.getDate() - p.days)
            return (
              <Button
                key={p.days}
                type="button"
                size="sm"
                variant={activePreset === p.days ? 'default' : 'outline'}
                className="h-8 px-3"
                onClick={() => {
                  const s = toYmd(from)
                  const e = toYmd(today)
                  setStartInput(s)
                  setEndInput(e)
                  commit({ start: s, end: e })
                }}
                data-testid={`period-growth-days-${p.days}`}
              >
                {p.label}
              </Button>
            )
          })}
        </div>

        {/* 任意の期間（開始日／終了日のカレンダー指定） */}
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1.5">
          <span className="mr-1 text-xs text-muted-foreground">任意の期間</span>
          <Input
            type="date"
            value={startInput}
            max={endInput || undefined}
            aria-label="開始日"
            onChange={(e) => {
              setStartInput(e.target.value)
              commit({ start: e.target.value })
            }}
            className="h-8 w-[9.5rem] flex-shrink-0"
            data-testid="period-growth-start"
          />
          <span aria-hidden className="text-xs text-muted-foreground">
            〜
          </span>
          <Input
            type="date"
            value={endInput}
            min={startInput || undefined}
            aria-label="終了日"
            onChange={(e) => {
              setEndInput(e.target.value)
              commit({ end: e.target.value })
            }}
            className="h-8 w-[9.5rem] flex-shrink-0"
            data-testid="period-growth-end"
          />
        </div>
      </div>
    )
  }
)

PeriodGrowthControls.displayName = 'PeriodGrowthControls'
