import { useEffect, useRef, useState } from 'react'
import useSWRInfinite from 'swr/infinite'
import { useInView } from 'react-intersection-observer'
import { isSP } from '../utils/utils'
import { rankingArgDto } from '../config/config'

async function fetchApi<T,>(url: string) {
  const response = await fetch(url)
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
const ROOT_MARGIN = isSP() ? "100px" : "500px"

export default function useInfiniteFetchApi<T,>(query: string) {
  const getKey = (i: number) => `${rankingArgDto.baseUrl}/oclist?page=${i}&limit=${LIMIT_ITEMS}${query ? '&' + query : ''}`
  const { data, setSize, isValidating, error } = useSWRInfinite(
    getKey,
    fetchApi<T[]>,
    swrOptions
  )

  const [page, setPage] = useState(1)

  const isLastPage = !data || !data[page - 1]?.length || data[page - 1].length < LIMIT_ITEMS

  const { ref: useInViewRef, inView: isScrollEnd } = useInView({
    root: null,
    rootMargin: ROOT_MARGIN,
    threshold: 0.0
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