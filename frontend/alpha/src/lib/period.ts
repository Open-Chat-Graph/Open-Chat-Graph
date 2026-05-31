/**
 * アクセス・検索指標の「期間指定」を表す共通の値（詳細・Labs で使い回す唯一の定義）。
 *
 * - days : 直近 N 日（既定 30）
 * - range: カレンダーで選んだ開始〜終了（含まれる日付の集計を全部見る）
 * - all  : 全期間（最古データ〜最新）
 */
export type PeriodValue =
  | { mode: 'days'; days: number }
  | { mode: 'range'; start: string; end: string } // Y-m-d
  | { mode: 'all' }

/** 既定は過去30日。 */
export const DEFAULT_PERIOD: PeriodValue = { mode: 'days', days: 30 }

/** プルダウンの日数プリセット。 */
export const PERIOD_DAY_PRESETS: { days: number; label: string }[] = [
  { days: 7, label: '7日' },
  { days: 30, label: '30日' },
  { days: 90, label: '90日' },
]

/** API クエリパラメータへ変換。 */
export function periodToParams(p: PeriodValue): Record<string, string> {
  if (p.mode === 'all') return { all: '1' }
  if (p.mode === 'range') return { start: p.start, end: p.end }
  return { days: String(p.days) }
}

/** SWR キーなどに使う安定した識別文字列。 */
export function periodKey(p: PeriodValue): string {
  if (p.mode === 'all') return 'all'
  if (p.mode === 'range') return `range:${p.start}:${p.end}`
  return `days:${p.days}`
}

// "2026-05-07" → "5/7"
const shortYmd = (s: string): string => {
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  return m ? `${Number(m[2])}/${Number(m[3])}` : s
}

/** トリガーや見出しに出す短いラベル。 */
export function periodLabel(p: PeriodValue): string {
  if (p.mode === 'all') return '全期間'
  if (p.mode === 'range') return `${shortYmd(p.start)}〜${shortYmd(p.end)}`
  return `${p.days}日`
}
