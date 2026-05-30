import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'

/**
 * 同じタブを再クリックしたとき（navigate(..., { state: { timestamp } })）に、
 * 現在表示中のベースページパネルを先頭へスクロールする。
 *
 * keep-alive（保持パターン）では各ページが絶対配置パネルとして常駐し、
 * 表示中のものだけ display:block になる。その可視パネルを先頭に戻す。
 * 旧コードは SettingsPage / MyListPage が同じ querySelector を重複実装していた。
 *
 * @param isActive このページが現在アクティブなルートか
 */
export function useScrollToTopOnReclick(isActive: boolean): void {
  const location = useLocation()

  useEffect(() => {
    const timestamp = (location.state as { timestamp?: number } | null)?.timestamp
    if (!timestamp || !isActive) return

    const panels = document.querySelectorAll<HTMLElement>('main > div[style*="position: absolute"]')
    const visible = Array.from(panels).find((el) => el.style.display === 'block')
    visible?.scrollTo(0, 0)
  }, [location.state, isActive])
}
