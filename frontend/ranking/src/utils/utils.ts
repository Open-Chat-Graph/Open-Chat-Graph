import { OPEN_CHAT_CATEGORY } from '../config/config'
import { sprintfT, t } from '../config/translation'

export function localStorageProvider() {
  const map = new Map(JSON.parse(sessionStorage.getItem('app-cache') || '[]'))

  window.addEventListener('beforeunload', () => {
    const appCache = JSON.stringify(Array.from(map.entries()))
    sessionStorage.setItem('app-cache', appCache)
  })

  return map
}

export function samePageLinkNavi(e: React.MouseEvent<HTMLAnchorElement, MouseEvent>) {
  if (
    e.defaultPrevented ||
    e.button !== 0 || // ignore everything but left-click
    e.metaKey ||
    e.ctrlKey ||
    e.altKey ||
    e.shiftKey
  ) {
    return false
  }
  return true
}

export function scrollToTop(selector: string = 'html') {
  document.querySelector(selector)?.scrollTo(0, 0)
}

export function isSP(): boolean {
  return !!('ontouchstart' in window || navigator.maxTouchPoints)
}

export function updateURLSearchParams(params: { [key: string]: string }): URL {
  const url = new URL(window.location.href)
  url.search = ''

  for (let k in params) {
    params[k] && url.searchParams.set(k, params[k])
  }

  url.searchParams.sort()

  return url
}

export function setTitle(params: ListParams, cateIndex?: number) {
  const keyword = params.keyword
  const subCategory = params.sub_category

  if (cateIndex === undefined) {
    const pathName = window.location.pathname
    const pathSegments = pathName.split('/')
    const category = parseInt((pathSegments.pop() || pathSegments.pop()) ?? '0')
    cateIndex = !Number.isNaN(category)
      ? OPEN_CHAT_CATEGORY.findIndex((el) => el[1] === category)
      : 0
  }

  let title0 = ''
  switch (!!keyword) {
    case true:
      title0 = sprintfT('「%s」の検索結果', keyword) + '｜'
      break
    default:
      title0 = ''
  }

  let title1 = ''
  switch (!!cateIndex) {
    case true:
      title1 = OPEN_CHAT_CATEGORY[cateIndex][0] + '｜'
      break
    default:
      title1 = title0 ? '' : t('【最新】')
  }

  let title3 = ''
  switch (!!subCategory) {
    case true:
      title3 = subCategory + '｜'
      break
    default:
      title3 = ''
  }

  let title2 = ''
  switch (params.list) {
    case 'weekly':
      title2 = t('人数増加・1週間')
      break
    case 'daily':
      title2 = t('人数増加・24時間')
      break
    case 'hourly':
      title2 = t('人数増加・1時間')
      break
    case 'all':
      title2 = t('参加人数のランキング')
      break
    case 'ranking':
      title2 = '公式ランキング(1時間前)'
      break
    case 'rising':
      title2 = '公式急上昇(1時間前)'
  }

  document.title = title0 + title1 + title3 + title2 + '｜' + t('オプチャグラフ')
}

export function validateStringNotEmpty(str: string) {
  const normalizedStr = str.normalize('NFKC')
  const string = normalizedStr.replace(/[\u200B-\u200D\uFEFF]/g, '')
  return string.trim() !== ''
}
