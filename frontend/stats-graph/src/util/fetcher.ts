const cache: { url: string[], data: any[] } = {
  url: [],
  data: []
}

export default async function fetcher<T,>(url: string) {
  const cacheIndex = cache.url.indexOf(url)
  if (cacheIndex !== -1) {
    return cache.data[cacheIndex] as T
  }

  const response = await fetch(url)

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