import { memo, useState, useEffect, useRef } from 'react'
import { BarChart2, Tag, FileText, type LucideIcon } from 'lucide-react'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { PeriodRangePicker } from '@/components/ui/period-range-picker'
import { CATEGORIES } from '@/lib/categories'
import { cn } from '@/lib/utils'
import type { PeriodValue } from '@/lib/period'

// Labs の3タブ。ランキング(rooms) / その他ページ(pages) / 検索KW(keywords)。
export type LabsTab = 'rooms' | 'pages' | 'keywords'

// 指標の選択肢。rooms/pages タブで使う。
export type LabsMetric = 'pv' | 'seo'

export const LABS_TABS: { value: LabsTab; label: string; icon: LucideIcon }[] = [
  { value: 'rooms', label: 'ランキング', icon: BarChart2 },
  { value: 'pages', label: 'その他ページ', icon: FileText },
  { value: 'keywords', label: '検索KW', icon: Tag },
]

// カテゴリ指定は rooms タブのみ。KW/ページはサイト全体。
const TAB_HAS_CATEGORY: Record<LabsTab, boolean> = {
  rooms: true,
  pages: false,
  keywords: false,
}

interface LabsControlsProps {
  tab: LabsTab
  period: PeriodValue
  category: number
  metric: LabsMetric
  keyword: string
  onTabChange: (tab: LabsTab) => void
  onPeriodChange: (period: PeriodValue) => void
  onCategoryChange: (category: number) => void
  onMetricChange: (metric: LabsMetric) => void
  onKeywordChange: (keyword: string) => void
}

/**
 * Labs の検索条件ヘッダ（タブ＋期間＋指標＋カテゴリ＋キーワード）。
 * 画面骨格（固定／背景／border／重ね順）は ListScreen.header が持つので、ここでは中身だけを描く。
 * 期間は既定30日＋カレンダー＋全期間（PeriodRangePicker）。
 * キーワード入力は onChange を 300ms デバウンスして親に渡す（rooms タブのみ表示）。
 * 指標プルダウン（アクセス数/検索流入）は rooms/pages タブのみ表示。
 */
export const LabsControls = memo(
  ({
    tab,
    period,
    category,
    metric,
    keyword,
    onTabChange,
    onPeriodChange,
    onCategoryChange,
    onMetricChange,
    onKeywordChange,
  }: LabsControlsProps) => {
    // キーワード入力の内部 state（デバウンス用）
    const [inputValue, setInputValue] = useState(keyword)
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    // 外部から keyword がリセットされたとき（タブ切替等）に内部 state も同期する。
    useEffect(() => {
      setInputValue(keyword)
    }, [keyword])

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      const v = e.target.value
      setInputValue(v)
      if (debounceRef.current) clearTimeout(debounceRef.current)
      debounceRef.current = setTimeout(() => {
        onKeywordChange(v)
      }, 300)
    }

    return (
      <div className="px-3 py-2 md:px-6">
        {/* タブ（横スクロール可。3つ） */}
        <div className="flex gap-1 overflow-x-auto pb-0.5">
          {LABS_TABS.map((t) => {
            const Icon = t.icon
            const active = tab === t.value
            return (
              <button
                key={t.value}
                type="button"
                onClick={() => onTabChange(t.value)}
                aria-pressed={active}
                data-testid={`labs-tab-${t.value}`}
                className={cn(
                  'flex flex-shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                  active
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                )}
              >
                <Icon className="h-3.5 w-3.5" />
                {t.label}
              </button>
            )
          })}
        </div>

        {/* 期間 ＋ 指標（rooms/pages のみ）＋ カテゴリ（rooms のみ） */}
        <div className="mt-2 flex items-center gap-2">
          <PeriodRangePicker value={period} onChange={onPeriodChange} />
          {tab !== 'keywords' && (
            <Select value={metric} onValueChange={(v) => onMetricChange(v as LabsMetric)}>
              <SelectTrigger className="h-7 !w-28 flex-shrink-0 text-xs" data-testid="labs-metric">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="pv">アクセス数</SelectItem>
                <SelectItem value="seo">検索流入</SelectItem>
              </SelectContent>
            </Select>
          )}
          {TAB_HAS_CATEGORY[tab] && (
            <Select value={String(category)} onValueChange={(v) => onCategoryChange(Number(v))}>
              <SelectTrigger className="h-7 flex-1 text-xs" data-testid="labs-category">
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
          )}
        </div>

        {/* キーワード絞り込み（rooms タブのみ） */}
        {tab === 'rooms' && (
          <div className="mt-2">
            <Input
              type="search"
              placeholder="部屋名で絞り込み"
              value={inputValue}
              onChange={handleInputChange}
              className="h-8 text-base md:text-sm"
              data-testid="labs-keyword"
            />
          </div>
        )}
      </div>
    )
  },
)

LabsControls.displayName = 'LabsControls'
