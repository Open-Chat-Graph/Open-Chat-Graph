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

/**
 * 横スクロール領域の「右端フェード幅」を残スクロール量に追従させる。
 * CSS変数 `--shelf-right-fade` を ref 経由で直接書き換えるだけで、React state を使わない
 * （= 再描画もちらつきも起きない）。残量が fadePx 未満になるとフェードが縮み、右端に到達すると
 * 0 になって自然に消える。あふれていない時も残量0で最初から消える。
 *
 * 静的な mask（常に右36pxをフェード）だと「右端までスクロールしても最後のチップが暗いまま」に
 * なるため、残量に連動させてこれを解消する。state を使う useIsRightScrollable はスライド切替の
 * 再マウントで初期 false→true を経由してちらつくので、棚ではこちらの imperative 版を使う。
 *
 * @param trigger 中身（チップ数など）が変わったら再計測するためのトリガ値
 * @param fadePx  フェード最大幅(px)。CSS側のデフォルト値と一致させること
 */
export function useRightFadeMask(
  trigger: unknown,
  fadePx = 36
): React.RefObject<HTMLDivElement | null> {
  const ref = useRef<HTMLDivElement>(null)

  // useLayoutEffect: ペイント前に残量を計測してフェード幅を確定（チップ取得直後も先に反映）。
  useLayoutEffect(() => {
    const el = ref.current
    if (!el) return

    const update = () => {
      const remaining = el.scrollWidth - el.clientWidth - el.scrollLeft
      const w = Math.max(0, Math.min(fadePx, remaining))
      el.style.setProperty('--shelf-right-fade', `${w}px`)
    }

    update()
    el.addEventListener('scroll', update, { passive: true })
    // コンテナ幅の変化（リサイズ・スライド幅変動）でも測り直す。
    const ro = new ResizeObserver(update)
    ro.observe(el)

    return () => {
      el.removeEventListener('scroll', update)
      ro.disconnect()
    }
  }, [trigger, fadePx])

  return ref
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
