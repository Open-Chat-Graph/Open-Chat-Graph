import { useState } from 'react'
import * as PopoverPrimitive from '@radix-ui/react-popover'
import { CalendarRange, ChevronDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import {
  PERIOD_DAY_PRESETS,
  periodLabel,
  type PeriodValue,
} from '@/lib/period'

interface PeriodRangePickerProps {
  value: PeriodValue
  onChange: (next: PeriodValue) => void
  className?: string
}

// ローカル日付を Y-m-d へ（タイムゾーンずれ回避）。
const toYmd = (d: Date): string =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

/**
 * 期間プルダウン（プリセット＋カレンダー＋全期間）。詳細・Labs で使い回す。
 *
 * コンパクトなトリガー（現在の期間ラベル）をタップすると、プリセット（7/30/90日・全期間）と
 * カレンダー（開始〜終了）を出す。プリセット選択は即確定して閉じる。カレンダーは両端が
 * 揃った時点で range として確定する。重ね順は z-popover。
 */
export function PeriodRangePicker({ value, onChange, className }: PeriodRangePickerProps) {
  const [open, setOpen] = useState(false)
  // カレンダーの編集中の値（確定前）。range のときは現在値を初期表示。
  const [start, setStart] = useState(value.mode === 'range' ? value.start : '')
  const [end, setEnd] = useState(value.mode === 'range' ? value.end : '')
  const today = toYmd(new Date())

  const pickPreset = (next: PeriodValue) => {
    onChange(next)
    setOpen(false)
  }

  // 開始・終了が両方揃ったら range 確定（前後関係は親リポジトリ側でも補正される）。
  const commitRange = (s: string, e: string) => {
    if (s && e) onChange({ mode: 'range', start: s, end: e })
  }

  return (
    <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
      <PopoverPrimitive.Trigger asChild>
        <Button
          type="button"
          size="sm"
          variant="outline"
          className={cn('h-7 gap-1 px-2.5 text-xs', className)}
          data-testid="period-picker-trigger"
        >
          <CalendarRange className="h-3.5 w-3.5" />
          {periodLabel(value)}
          <ChevronDown className="h-3 w-3 opacity-60" />
        </Button>
      </PopoverPrimitive.Trigger>
      <PopoverPrimitive.Portal>
        <PopoverPrimitive.Content
          align="end"
          sideOffset={6}
          collisionPadding={8}
          className={cn(
            'z-popover w-[min(18rem,calc(100vw-1rem))] rounded-md border bg-popover p-3 text-popover-foreground shadow-md',
            'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
          )}
        >
          {/* プリセット＋全期間 */}
          <div className="flex flex-wrap gap-1.5">
            {PERIOD_DAY_PRESETS.map((p) => (
              <Button
                key={p.days}
                type="button"
                size="sm"
                variant={value.mode === 'days' && value.days === p.days ? 'default' : 'outline'}
                className="h-8 px-3 text-xs"
                onClick={() => pickPreset({ mode: 'days', days: p.days })}
                data-testid={`period-preset-${p.days}`}
              >
                {p.label}
              </Button>
            ))}
            <Button
              type="button"
              size="sm"
              variant={value.mode === 'all' ? 'default' : 'outline'}
              className="h-8 px-3 text-xs"
              onClick={() => pickPreset({ mode: 'all' })}
              data-testid="period-preset-all"
            >
              全期間
            </Button>
          </div>

          {/* カレンダー（開始〜終了） */}
          <div className="mt-3 border-t pt-3">
            <p className="mb-1.5 text-[11px] text-muted-foreground">カレンダーで指定</p>
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1.5">
              <Input
                type="date"
                value={start}
                max={end || today}
                aria-label="開始日"
                onChange={(e) => {
                  setStart(e.target.value)
                  commitRange(e.target.value, end)
                }}
                className="h-8 w-[8.5rem] flex-shrink-0"
                data-testid="period-start"
              />
              <span aria-hidden className="text-xs text-muted-foreground">〜</span>
              <Input
                type="date"
                value={end}
                min={start || undefined}
                max={today}
                aria-label="終了日"
                onChange={(e) => {
                  setEnd(e.target.value)
                  commitRange(start, e.target.value)
                }}
                className="h-8 w-[8.5rem] flex-shrink-0"
                data-testid="period-end"
              />
            </div>
            <p className="mt-1.5 text-[10px] leading-tight text-muted-foreground/70">
              全期間ボタンで最古データから現在まで。指定した期間に含まれるデータを全部集計します。
            </p>
          </div>
        </PopoverPrimitive.Content>
      </PopoverPrimitive.Portal>
    </PopoverPrimitive.Root>
  )
}
