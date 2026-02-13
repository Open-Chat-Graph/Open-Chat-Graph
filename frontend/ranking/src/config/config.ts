const LINE_IMG_URL = 'https://obs.line-scdn.net/';

export const rankingArgDto: RankingArgDto = JSON.parse(
  document.getElementById('arg-dto')!.textContent!
)

export const OPEN_CHAT_CATEGORY: [string, number][] = rankingArgDto.openChatCategory

export const OPEN_CHAT_CATEGORY_OBJ = ((obj) => {
  OPEN_CHAT_CATEGORY.map((el) => (obj[el[1]] = el[0]))
  return obj
})({} as { [K: string]: string })

export const basePath = `${
  rankingArgDto.urlRoot ? rankingArgDto.urlRoot.replace(/^\/+/, '') + '/' : ''
}ranking`

const parsedUrl = new URL(rankingArgDto.baseUrl, window.location.origin)
export const siteUrl = `${parsedUrl.protocol}//${parsedUrl.hostname}${
  parsedUrl.port ? ':' + parsedUrl.port : ''
}`

export const langCode = rankingArgDto.urlRoot.replace(/^\/+/, '')
export const imageBaseUrl = LINE_IMG_URL