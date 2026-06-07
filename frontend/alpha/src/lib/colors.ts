/**
 * 増減色のセマンティックヘルパ。
 * `--up` / `--down` トークン（tailwind.config.js で定義）を唯一の参照元にする。
 */

/** テキスト色クラス: 正=up, 負=down, 0=muted */
export function diffColorClass(diff: number): string {
  if (diff > 0) return 'text-up'
  if (diff < 0) return 'text-down'
  return 'text-muted-foreground'
}

/** 背景+テキスト色クラス: 正=up/10, 負=down/10, 0=muted */
export function diffBgClass(diff: number): string {
  if (diff > 0) return 'bg-up/10 text-up'
  if (diff < 0) return 'bg-down/10 text-down'
  return 'bg-muted text-muted-foreground'
}
