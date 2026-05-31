import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Input } from '@/components/ui/input'

export type ThresholdUnit = 'member' | 'percent'

export const thresholdUnitLabel = (u: ThresholdUnit) => (u === 'percent' ? '％' : '人')

/**
 * 増減アラートのしきい値を「増減が ± [数値] [人/％] を超えたら通知」の文で入力する共通部品。
 *
 * 部屋詳細の増減アラート（WatchRoomControl）と、マイリスト変動アラート（WatchSettingsPage）で
 * 同一の %/人数プルダウンを使い回す。値・単位は親が state で持ち、確定(blur/Enter)で onCommit。
 *
 * Select は popover(z-popover) で header(60) より上に出る（生 z-[NN] は使わない）。
 * 入力は text-base md:text-sm 相当（Input 既定）で iOS の自動ズームを避ける。
 */
export function ThresholdInput({
  value,
  unit,
  disabled,
  onValueChange,
  onUnitChange,
  onCommit,
  ariaPrefix = '',
}: {
  value: string
  unit: ThresholdUnit
  disabled?: boolean
  onValueChange: (v: string) => void
  onUnitChange: (u: ThresholdUnit) => void
  onCommit?: () => void
  /** aria-label の接頭辞（複数置く場合の識別用） */
  ariaPrefix?: string
}) {
  return (
    <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
      <span>増減が ±</span>
      <Input
        type="number"
        min={1}
        inputMode="numeric"
        value={value}
        disabled={disabled}
        onChange={(e) => onValueChange(e.target.value)}
        onBlur={onCommit}
        onKeyDown={(e) => {
          if (e.key === 'Enter') e.currentTarget.blur()
        }}
        className="h-10 w-20"
        aria-label={`${ariaPrefix}通知する増減のしきい値`}
        data-testid="threshold-value"
      />
      <Select
        value={unit}
        disabled={disabled}
        onValueChange={(v) => onUnitChange(v === 'percent' ? 'percent' : 'member')}
      >
        <SelectTrigger
          className="h-10 w-20"
          aria-label={`${ariaPrefix}しきい値の単位`}
          data-testid="threshold-unit"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="member">人</SelectItem>
          <SelectItem value="percent">％</SelectItem>
        </SelectContent>
      </Select>
      <span>を超えたら通知</span>
    </div>
  )
}
