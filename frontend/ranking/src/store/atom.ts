import { atom } from 'jotai'

export const listParamsState = atom<ListParams>({
  sub_category: '',
  keyword: '',
  order: 'asc',
  sort: 'rank',
  list: 'daily',
})

export const keywordState = atom<string>('')

export const subCategoryChipsStackScrollLeft = atom<number>(0)

// 詳細成長分析（/analysis）のツールバー入力状態（検索条件のフォーム値）
export const analysisParamsState = atom<AnalysisParams>({
  metric: 'increase',
  period: 'year',
  from: '',
  to: '',
  category: 0,
  keyword: '',
  sort: 'count',
  order: 'desc',
})
