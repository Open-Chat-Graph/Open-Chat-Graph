import { useLayoutEffect, useRef, useState } from 'react'

export function useIsRightScrollable(
  useEffectTrigerValue: unknown
): [boolean, React.RefObject<HTMLDivElement | null>] {
  const [isRightScrollable, setIsRightScrollable] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  const checkScrollButtons = () => {
    if (ref.current) {
      const { scrollWidth, clientWidth, scrollLeft } = ref.current
      const correction = 10
      setIsRightScrollable(
        scrollWidth !== clientWidth && scrollLeft < scrollWidth - correction - clientWidth
      )
    }
  }

  // useLayoutEffect: 初回描画(ペイント)前に計測して右端フェードの有無を確定させる。
  // useEffect だとペイント後に false→true へ更新されるため、スライド切替で棚が再マウント
  // するたびフェードが一瞬消えて戻る「ちらつき」が出る。
  useLayoutEffect(() => {
    checkScrollButtons()
    const currentRef = ref.current
    currentRef?.addEventListener('scroll', checkScrollButtons)

    return () => {
      currentRef?.removeEventListener('scroll', checkScrollButtons)
    }
  }, [useEffectTrigerValue])

  return [isRightScrollable, ref]
}

export function useIsLeftRightScrollable(
  useEffectTrigerValue: unknown
): [boolean, boolean, React.RefObject<HTMLDivElement | null>] {
  const ref = useRef<HTMLDivElement>(null)
  const [isLeftScrollable, setIsLeftScrollable] = useState(false)
  const [isRightScrollable, setIsRightScrollable] = useState(false)

  const checkScrollButtons = () => {
    if (ref.current) {
      const { scrollWidth, clientWidth, scrollLeft } = ref.current
      const correction = 10
      setIsLeftScrollable(scrollLeft > 10)
      setIsRightScrollable(
        scrollWidth !== clientWidth && scrollLeft < scrollWidth - correction - clientWidth
      )
    }
  }

  // useLayoutEffect: 初回描画(ペイント)前に計測して右端フェードの有無を確定させる。
  // useEffect だとペイント後に false→true へ更新されるため、スライド切替で棚が再マウント
  // するたびフェードが一瞬消えて戻る「ちらつき」が出る。
  useLayoutEffect(() => {
    checkScrollButtons()
    const currentRef = ref.current
    currentRef?.addEventListener('scroll', checkScrollButtons)

    return () => {
      currentRef?.removeEventListener('scroll', checkScrollButtons)
    }
  }, [useEffectTrigerValue])

  return [isLeftScrollable, isRightScrollable, ref]
}
