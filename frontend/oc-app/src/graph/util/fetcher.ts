// eslint-disable-next-line @typescript-eslint/no-explicit-any
const cache: { url: string[]; data: any[] } = {
  url: [],
  data: [],
}

export default async function fetcher<T>(url: string) {
  const cacheIndex = cache.url.indexOf(url)
  if (cacheIndex !== -1) {
    return cache.data[cacheIndex] as T
  }

  // X-Ocg-Client: サイト内JSからのfetchであることを示す（無いとAPI側で404。直叩き収集対策）
  const response = await fetch(url, { headers: { 'X-Ocg-Client': '1' } })

  const data: T | ErrorResponse = await response.json()
  if (!response.ok) {
    const errorMessage = (data as ErrorResponse).error.message
    console.error(errorMessage)
    throw new Error(errorMessage)
  }

  cache.url.push(url)
  cache.data.push(data)

  return data as T
}
