import { memo } from 'react'
import { Eye, Search } from 'lucide-react'
import { Button } from '@/components/ui/button'

// Labs の2つのランキング。アクセス=PV、検索流入=GSC由来。
export type LabsMode = 'access' | 'search'

interface LabsControlsProps {
  mode: LabsMode
  days: number
  onModeChange: (mode: LabsMode) => void
  onDaysChange: (days: number) => void
}

// 期間プリセット（日数）。本家データの日次集計に合わせ 7/30/90 日のみ。
const DAY_PRESETS: { value: number; label: string }[] = [
  { value: 7, label: '7日' },
  { value: 30, label: '30日' },
  { value: 90, label: '90日' },
]

const MODES: { value: LabsMode; label: string; icon: typeof Eye }[] = [
  { value: 'access', label: 'アクセス数', icon: Eye },
  { value: 'search', label: '検索流入', icon: Search },
]

export const LabsControls = memo(
  ({ mode, days, onModeChange, onDaysChange }: LabsControlsProps) => {
    return (
      <div className="space-y-3 rounded-lg border bg-card p-3 md:p-4">
        {/* モード切替（PeriodGrowthControls のチップ作法に倣う） */}
        <div className="flex gap-1.5">
          {MODES.map((m) => {
            const Icon = m.icon
            const active = mode === m.value
            return (
              <Button
                key={m.value}
                type="button"
                size="sm"
                variant={active ? 'default' : 'outline'}
                className="h-9 flex-1 gap-1.5"
                onClick={() => onModeChange(m.value)}
                aria-pressed={active}
                data-testid={`labs-mode-${m.value}`}
              >
                <Icon className="h-4 w-4" />
                {m.label}
              </Button>
            )
          })}
        </div>

        {/* 期間プリセット */}
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="mr-1 text-xs text-muted-foreground">期間</span>
          {DAY_PRESETS.map((p) => (
            <Button
              key={p.value}
              type="button"
              size="sm"
              variant={days === p.value ? 'default' : 'outline'}
              className="h-8 px-3"
              onClick={() => onDaysChange(p.value)}
              data-testid={`labs-days-${p.value}`}
            >
              {p.label}
            </Button>
          ))}
        </div>
      </div>
    )
  }
)

LabsControls.displayName = 'LabsControls'
