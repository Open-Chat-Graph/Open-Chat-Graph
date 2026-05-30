import { useState, useEffect } from 'react'

export function useScrollDirection() {
  const [scrollDirection, setScrollDirection] = useState<'up' | 'down'>('up')
  const [prevScrollY, setPrevScrollY] = useState(0)

  useEffect(() => {
    // Find the main scrollable container - the one that's currently visible
    const mainContainers = document.querySelectorAll('main > div')
    const mainContainer = Array.from(mainContainers).find(
      (el) => (el as HTMLElement).style.display === 'block'
    ) as HTMLElement

    if (!mainContainer) return

    const handleScroll = () => {
      const currentScrollY = mainContainer.scrollTop

      if (currentScrollY > prevScrollY && currentScrollY > 50) {
        // 下スクロール & 50px以上スクロールしている
        setScrollDirection('down')
      } else if (currentScrollY < prevScrollY) {
        // 上スクロール
        setScrollDirection('up')
      }

      setPrevScrollY(currentScrollY)
    }

    mainContainer.addEventListener('scroll', handleScroll, { passive: true })

    return () => {
      mainContainer.removeEventListener('scroll', handleScroll)
    }
  }, [prevScrollY])

  return scrollDirection
}
