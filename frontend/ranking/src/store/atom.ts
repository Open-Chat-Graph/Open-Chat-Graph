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
