import React, { useCallback, useEffect, useRef, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { scrollToTop, validateStringNotEmpty } from '../utils/utils'
import { useSetListParams } from './ListParamsHooks'
import { useRecoilValue } from 'recoil'
import { keywordState } from '../store/atom'
import { langCode } from '../config/config'

export default function useSiteHeaderSearch(siperSlideTo?: ((index: number) => void) | undefined) {
  const [open, setOpen] = useState(false)
  const setParams = useSetListParams()
  const keyword = useRecoilValue(keywordState)
  const inputRef = useRef<HTMLInputElement | null>(null)
  const hiddenRef = useRef<HTMLInputElement | null>(null)
  const buttonRef = useRef<HTMLButtonElement | null>(null)
  const isCompositionStart = useRef<boolean>(false)
  const [inputEmpty, setInputEmpty] = useState(true)
  const navigate = useNavigate()
  const location = useLocation()

  const handleCompositionStart = useCallback(() => {
    setInputEmpty(true)
    isCompositionStart.current = true
  }, [])

  const handleCompositionEnd = useCallback(() => {
    setInputEmpty(false)
    isCompositionStart.current = false
  }, [])

  const openSearch = useCallback(() => {
    if (keyword) {
      inputRef.current!.value = keyword
      setInputEmpty(false)
    }

    setOpen(true)
  }, [keyword])

  const closeSearch = useCallback(() => {
    inputRef.current && (inputRef.current.value = '')
    inputRef.current?.blur()
    setInputEmpty(true)
    setOpen(false)
  }, [])

  const onKeyDown: React.KeyboardEventHandler<HTMLInputElement> = useCallback((e) => {
    if (e.key === 'Escape' || e.key === 'Tab') {
      closeSearch()
      buttonRef.current?.focus()
    }
  }, [])

  const onChange = useCallback((e: React.FormEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    hiddenRef.current && (hiddenRef.current.value = e.currentTarget.value)
    !isCompositionStart.current && setInputEmpty(!e.currentTarget.value)
  }, [])

  const deleteInput = useCallback(() => {
    inputRef.current && (inputRef.current.value = '')
    inputRef.current?.focus()
    setInputEmpty(true)
  }, [])

  const onSubmit = useCallback(
    (e: React.FormEvent<HTMLFormElement>) => {
      e.preventDefault()

      let keyword = inputRef.current!.value
      if (!validateStringNotEmpty(keyword)) {
        return
      }

      closeSearch()

      const maxLength = 1000
      // 文字数がmaxLength以内ならそのまま、超えていれば切り詰める
      if (keyword.length > maxLength) {
        keyword = keyword.substring(0, maxLength)
      }

      if (siperSlideTo) {
        setParams((params) => {
          return {
            ...params,
            keyword,
            sub_category: '',
            list: 'all',
            sort: 'member',
            order: 'desc',
          }
        })
        siperSlideTo(0)
      } else {
        setParams((params) => {
          const p: ListParams = {
            ...params,
            keyword,
            sub_category: '',
            list: 'all',
            sort: 'member',
            order: 'desc',
          }
          navigate( '/' +(langCode === location.pathname.split('/')[1]
                ? location.pathname.split('/')[1] + '/' + location.pathname.split('/')[2]
                : location.pathname.split('/')[1]
              ) + `/?${new URLSearchParams(p).toString()}`,
            { replace: true }
          )
          return p
        })
        scrollToTop()
        scrollToTop('.hide-scrollbar-x')
      }
    },
    [siperSlideTo]
  )

  useEffect(() => {
    if (open) {
      inputRef.current?.focus()
    }
  }, [open])

  return {
    openSearch,
    closeSearch,
    onKeyDown,
    onChange,
    onSubmit,
    deleteInput,
    inputEmpty,
    inputRef,
    hiddenRef,
    buttonRef,
    open,
    handleCompositionStart,
    handleCompositionEnd,
  }
}
