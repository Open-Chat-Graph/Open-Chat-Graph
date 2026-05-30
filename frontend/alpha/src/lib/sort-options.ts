export type SortType = 'member' | 'created_at' | 'hourly_diff' | 'diff_24h' | 'diff_1w'
export type SortOrder = 'asc' | 'desc'

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
