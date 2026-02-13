import { atom } from 'recoil'

export const listParamsState = atom<ListParams>({
  key: 'listParams',
  default: {
    sub_category: '',
    keyword: '',
    order: 'asc',
    sort: 'rank',
    list: 'daily',
  },
})

export const keywordState = atom<string>({
  key: 'keyword',
  default: '',
})

export const subCategoryChipsStackScrollLeft = atom<number>({
  key: 'ubCategoryChipsStackScroll',
  default: 0,
})
