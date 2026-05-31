import { memo } from 'react'
import { Eye, Search, Tag, FileText, type LucideIcon } from 'lucide-react'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { PeriodRangePicker } from '@/components/ui/period-range-picker'
import { CATEGORIES } from '@/lib/categories'
import { cn } from '@/lib/utils'
import type { PeriodValue } from '@/lib/period'

// Labs の4タブ。アクセス/検索流入/検索KW＋その他ページ(非オプチャ)。
export type LabsTab = 'access' | 'search' | 'keywords' | 'pages'

export const LABS_TABS: { value: LabsTab; label: string; icon: LucideIcon }[] = [
  { value: 'access', label: 'アクセス', icon: Eye },
  { value: 'search', label: '検索流入', icon: Search },
  { value: 'keywords', label: '検索KW', icon: Tag },
  { value: 'pages', label: 'その他ページ', icon: FileText },
]

// カテゴリ指定は部屋ランキング（access/search）のみ。KW/ページはサイト全体。
const TAB_HAS_CATEGORY: Record<LabsTab, boolean> = {
  access: true,
  search: true,
  keywords: false,
  pages: false,
}

interface LabsControlsProps {
  tab: LabsTab
  period: PeriodValue
  category: number
  onTabChange: (tab: LabsTab) => void
  onPeriodChange: (period: PeriodValue) => void
  onCategoryChange: (category: number) => void
}

/**
 * Labs の検索条件ヘッダ（タブ＋期間＋カテゴリ）。通常検索と同様に上部固定（sticky）。
 * 重ね順は z-subheader。期間は既定30日＋カレンダー＋全期間（PeriodRangePicker）。
 */
export const LabsControls = memo(
  ({ tab, period, category, onTabChange, onPeriodChange, onCategoryChange }: LabsControlsProps) => {
    return (
      <div className="sticky top-0 z-subheader -mx-3 -mt-3 border-b bg-background/95 px-3 py-2 backdrop-blur md:-mx-6 md:-mt-6 md:px-6">
        {/* タブ（横スクロール可。4つ） */}
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

        {/* 期間＋カテゴリ */}
        <div className="mt-2 flex items-center gap-2">
          <PeriodRangePicker value={period} onChange={onPeriodChange} />
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
      </div>
    )
  },
)

LabsControls.displayName = 'LabsControls'
