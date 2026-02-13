import React, { useCallback, useRef } from 'react'
import { RecoilState, useRecoilState } from 'recoil'

export default function useValidateInput(textLen: number, recoil: RecoilState<string>) {
  const [value, setValue] = useRecoilState(recoil)
  const isCompositionStart = useRef<boolean>(false)

  const commitStr = useCallback(() => {
    setValue((prevText: string): string => prevText.substring(0, textLen))
  }, [setValue, textLen])

  const onCompositionStart = useCallback((): void => {
    isCompositionStart.current = true
  }, [])

  const onCompositionEnd = useCallback((): void => {
    isCompositionStart.current = false
    commitStr()
  }, [commitStr])

  const onChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>): void => {
      setValue(e.target.value)
      if (!isCompositionStart.current) {
        commitStr()
      }
    },
    [commitStr, setValue]
  )

  return {
    onCompositionStart,
    onCompositionEnd,
    onChange,
    value,
  }
}
