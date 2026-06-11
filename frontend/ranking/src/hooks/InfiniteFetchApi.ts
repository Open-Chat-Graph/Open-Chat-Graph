import { useEffect, useRef, useState, useSyncExternalStore } from 'react'
import useSWRInfinite from 'swr/infinite'
import { useInView } from 'react-intersection-observer'
import { isSP } from '../utils/utils'
import { rankingArgDto } from '../config/config'

// 429(Cloudflareレートリミット)の再試行待機中フラグ。
// jotai storeはOCListPageごとに分かれるため、fetcher(React外)から共有できる極小ストアにする
let isRateLimitWaiting = false
const rateLimitListeners = new Set<() => void>()
const setRateLimitWaiting = (value: boolean) => {
  isRateLimitWaiting = value
  rateLimitListeners.forEach((listener) => listener())
}
export const useRateLimitWaiting = () =>
  useSyncExternalStore(
    (callback) => {
      rateLimitListeners.add(callback)
      return () => rateLimitListeners.delete(callback)
    },
    () => isRateLimitWaiting
  )

// 待機後の再試行でも429だった場合のエラー。表示側で通信エラーと文言を出し分ける
export class RateLimitError extends Error {
  name = 'RateLimitError'
}

export const RATE_LIMIT_RETRY_SECONDS = 10

async function fetchApi<T>(url: string) {
  // X-Ocg-Client: サイト内JSからのfetchであることを示す（無いとAPI側で404。直叩き収集対策）
  let response = await fetch(url, { headers: { 'X-Ocg-Client': '1' } })

  // 429: スケルトンを出したまま(SWRのisValidatingが続く)10秒待って1回だけ再試行
  if (response.status === 429) {
    setRateLimitWaiting(true)
    try {
      await new Promise((resolve) => setTimeout(resolve, RATE_LIMIT_RETRY_SECONDS * 1000))
      response = await fetch(url, { headers: { 'X-Ocg-Client': '1' } })
    } finally {
      setRateLimitWaiting(false)
    }
    // 再試行も429ならJSONでない(CloudflareのHTML)ためparseせずに専用エラーにする
    if (response.status === 429) {
      throw new RateLimitError()
    }
  }

  const data: T | ErrorResponse = await response.json()
  if (!response.ok) {
    const errorMessage = (data as ErrorResponse).error.message
    console.log(errorMessage)
    throw new Error(errorMessage)
  }
  return data as T
}

const swrOptions = {
  revalidateOnReconnect: false,
  revalidateIfStale: false,
  revalidateOnFocus: false,
  revalidateFirstPage: false,
}

export const LIMIT_ITEMS = isSP() ? 10 : 20
const ROOT_MARGIN = isSP() ? '100px' : '500px'

export default function useInfiniteFetchApi<T>(query: string) {
  const getKey = (i: number) =>
    `${rankingArgDto.baseUrl}/oclist?page=${i}&limit=${LIMIT_ITEMS}${query ? '&' + query : ''}`
  const { data, setSize, isValidating, error } = useSWRInfinite(getKey, fetchApi<T[]>, swrOptions)

  const [page, setPage] = useState(1)

  const isLastPage = !data || !data[page - 1]?.length || data[page - 1].length < LIMIT_ITEMS

  const { ref: useInViewRef, inView: isScrollEnd } = useInView({
    root: null,
    rootMargin: ROOT_MARGIN,
    threshold: 0.0,
  })

  useEffect(() => {
    if (isScrollEnd && !isValidating && !error && !isLastPage) {
      setSize(page + 1)
      setPage(page + 1)
    }
  }, [isScrollEnd])

  const dataRef = useRef<[number, string, T[] | undefined]>([0, '', undefined])
  const queryRef = useRef(query)

  useEffect(() => {
    if (queryRef.current !== query) {
      queryRef.current = query
      setPage(1)
    }
  }, [query])

  if (!dataRef.current[2] || dataRef.current[0] !== page || dataRef.current[1] !== query) {
    if (data?.[page - 1]) {
      dataRef.current[0] = page
      dataRef.current[1] = query
    }

    dataRef.current[2] = data?.slice(0, queryRef.current !== query ? 1 : page).flat()
  }

  return { data: dataRef.current[2], useInViewRef, isValidating, isLastPage, error }
}
