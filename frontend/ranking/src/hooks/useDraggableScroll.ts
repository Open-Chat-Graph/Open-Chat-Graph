import { useCallback, useRef } from 'react'

export function useDraggableScroll(ref: React.RefObject<HTMLElement | null>) {
  const isDown = useRef(false)
  const startX = useRef(0)
  const scrollLeft = useRef(0)

  const onPointerDown = useCallback(
    (e: React.PointerEvent) => {
      if (!ref.current) return
      isDown.current = true
      startX.current = e.pageX - ref.current.offsetLeft
      scrollLeft.current = ref.current.scrollLeft
      ref.current.style.cursor = 'grabbing'
      ref.current.style.userSelect = 'none'
    },
    [ref]
  )

  const onPointerMove = useCallback(
    (e: React.PointerEvent) => {
      if (!isDown.current || !ref.current) return
      e.preventDefault()
      const x = e.pageX - ref.current.offsetLeft
      const walk = (x - startX.current) * 1.5
      ref.current.scrollLeft = scrollLeft.current - walk
    },
    [ref]
  )

  const onPointerUp = useCallback(() => {
    isDown.current = false
    if (ref.current) {
      ref.current.style.cursor = 'grab'
      ref.current.style.removeProperty('user-select')
    }
  }, [ref])

  return {
    events: {
      onPointerDown,
      onPointerMove,
      onPointerUp,
      onPointerLeave: onPointerUp,
    },
  }
}
