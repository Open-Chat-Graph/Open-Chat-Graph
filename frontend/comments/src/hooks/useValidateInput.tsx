import React, { useCallback, useRef } from 'react'
import { useAtom } from 'jotai'
import type { PrimitiveAtom } from 'jotai'

export default function useValidateInput(textLen: number, atomState: PrimitiveAtom<string>) {
  const [value, setValue] = useAtom(atomState)
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
