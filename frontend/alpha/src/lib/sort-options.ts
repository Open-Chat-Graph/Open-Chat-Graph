export type SortType = 'member' | 'created_at' | 'hourly_diff' | 'diff_24h' | 'diff_1w'
export type SortOrder = 'asc' | 'desc'

// 検索メニュー用のソート軸（昇順/降順はメニューでなくトグルボタンで切替）。
// 「作成日順」は一番下に置く。
export const SORT_METRICS: { value: SortType; label: string }[] = [
  { value: 'member', label: '人数' },
  { value: 'hourly_diff', label: '1時間増減' },
  { value: 'diff_24h', label: '24時間増減' },
  { value: 'diff_1w', label: '1週間増減' },
  { value: 'created_at', label: '作成日順' },
]

export const sortMetricLabel = (value: string): string =>
  SORT_METRICS.find((m) => m.value === value)?.label ?? '人数'

// 統合ソートオプション
export const UNIFIED_SORT_OPTIONS = [
  { value: 'member', order: 'desc', label: '人数降順' },
  { value: 'member', order: 'asc', label: '人数昇順' },
  { value: 'created_at', order: 'desc', label: '作成日順降順' },
  { value: 'created_at', order: 'asc', label: '作成日順昇順' },
  { value: 'hourly_diff', order: 'desc', label: '1時間増減降順' },
  { value: 'hourly_diff', order: 'asc', label: '1時間増減昇順' },
  { value: 'diff_24h', order: 'desc', label: '24時間増減降順' },
  { value: 'diff_24h', order: 'asc', label: '24時間増減昇順' },
  { value: 'diff_1w', order: 'desc', label: '1週間増減降順' },
  { value: 'diff_1w', order: 'asc', label: '1週間増減昇順' },
] as const
