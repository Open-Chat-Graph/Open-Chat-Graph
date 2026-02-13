import { useEffect, useRef, useState } from "react"

export function useIsRightScrollable(useEffectTrigerValue: any): [boolean, React.MutableRefObject<HTMLInputElement>] {
  const [isRightScrollable, setIsRightScrollable] = useState(false)
  const ref = useRef<HTMLDivElement>() as React.MutableRefObject<HTMLInputElement>

  const checkScrollButtons = () => {
    if (ref.current) {
      const { scrollWidth, clientWidth, scrollLeft } = ref.current
      const correction = 10
      setIsRightScrollable(scrollWidth !== clientWidth && scrollLeft < (scrollWidth - correction) - clientWidth)
    }
  }

  useEffect(() => {
    checkScrollButtons()
    const currentRef = ref.current
    currentRef?.addEventListener('scroll', checkScrollButtons)

    return () => {
      currentRef?.removeEventListener('scroll', checkScrollButtons)
    }
  }, [useEffectTrigerValue])

  return [isRightScrollable, ref]
}

export function useIsLeftRightScrollable(useEffectTrigerValue: any): [boolean, boolean, React.MutableRefObject<HTMLInputElement>] {
  const ref = useRef<HTMLDivElement>() as React.MutableRefObject<HTMLInputElement>
  const [isLeftScrollable, setIsLeftScrollable] = useState(false)
  const [isRightScrollable, setIsRightScrollable] = useState(false)

  const checkScrollButtons = () => {
    if (ref.current) {
      const { scrollWidth, clientWidth, scrollLeft } = ref.current
      const correction = 10
      setIsLeftScrollable(scrollLeft > 10)
      setIsRightScrollable(scrollWidth !== clientWidth && scrollLeft < (scrollWidth - correction) - clientWidth)
    }
  }

  useEffect(() => {
    checkScrollButtons()
    const currentRef = ref.current
    currentRef?.addEventListener('scroll', checkScrollButtons)

    return () => {
      currentRef?.removeEventListener('scroll', checkScrollButtons)
    }
  }, [useEffectTrigerValue])

  return [isLeftScrollable, isRightScrollable, ref]
}