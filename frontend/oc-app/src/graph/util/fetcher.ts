// eslint-disable-next-line @typescript-eslint/no-explicit-any
const cache: { url: string[]; data: any[] } = {
  url: [],
  data: [],
}

// 5xx（DBロック競合などの一過性エラー）やネットワーク失敗は、少し待てば通ることが多い。
// そのため最大もう1回だけ、短い間隔（ジッタ付きで同時リトライの集中を避ける）を空けて再試行する。
// 4xx は再試行しない（恒久的なエラー）。
const MAX_ATTEMPTS = 2
const RETRY_BASE_DELAY_MS = 500
const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms))
const retryDelay = () => RETRY_BASE_DELAY_MS + Math.floor(Math.random() * 400)

export default async function fetcher<T>(url: string) {
  const cacheIndex = cache.url.indexOf(url)
  if (cacheIndex !== -1) {
    return cache.data[cacheIndex] as T
  }

  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    let response: Response
    try {
      // X-Ocg-Client: サイト内JSからのfetchであることを示す（Cloudflare側で検証。直叩き収集対策）
      response = await fetch(url, { headers: { 'X-Ocg-Client': '1' } })
    } catch (networkError) {
      // ネットワーク失敗。最終試行でなければ少し待って再試行
      if (attempt < MAX_ATTEMPTS) {
        await sleep(retryDelay())
        continue
      }
      throw networkError
    }

    // 5xx（DBロック競合等の一過性エラー）は最終試行でなければ少し待って再試行
    if (response.status >= 500 && attempt < MAX_ATTEMPTS) {
      await sleep(retryDelay())
      continue
    }

    let data: T | ErrorResponse
    try {
      data = await response.json()
    } catch {
      // 5xx で JSON 以外（HTMLエラーページ等）が返るケース
      throw new Error(`HTTP ${response.status}`)
    }

    if (!response.ok) {
      const errorMessage = (data as ErrorResponse).error.message
      console.error(errorMessage)
      throw new Error(errorMessage)
    }

    cache.url.push(url)
    cache.data.push(data)
    return data as T
  }

  // ループは必ず return か throw で抜ける（型のためのフォールバック）
  throw new Error('fetch failed')
}
