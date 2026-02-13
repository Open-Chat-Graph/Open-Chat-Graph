type SubCategories = {
  [K: string]: string[]
}

type SubCategoryKey = keyof SubCategories

type RankingArgDto = {
  baseUrl: string
  rankingUpdatedAt: string
  modifiedUpdatedAtDate: string
  hourlyUpdatedAt: string
  subCategories: SubCategories
  openChatCategory: [string, number][]
  urlRoot: '' | '/tw' | '/th'
}

interface ErrorResponse {
  error: {
    code: string
    message: string
  }
}

interface OpenChat {
  id: number
  name: string
  desc: string
  member: number
  img: string
  emblem: 0 | 1 | 2
  joinMethodType: 0 | 1 | 2
  category: number
  symbolIncrease?: 'positive' | 'negative'
  increasedMember?: string
  percentageIncrease?: string
  createdAt?: string
  totalCount?: number
}

type ListParams = {
  sub_category: string
  keyword: string
  order: 'asc' | 'desc' | ''
  sort: 'rank' | 'member' | 'increase' | 'rate' | 'created_at' | ''
  list: 'hourly' | 'daily' | 'weekly' | 'all' | 'ranking' | 'rising'
}

type ListParamsKey = keyof ListParams
type ListParamsValue<T extends ListParamsKey> = ListParams[T]
type SetListParamsValue = (getNewParams: (currentParams: ListParams) => ListParams) => void
