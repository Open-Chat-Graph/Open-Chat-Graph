import { memo, useEffect, useLayoutEffect, useRef, useState } from 'react'
import { basePath, OPEN_CHAT_CATEGORY } from '../config/config'
import { samePageLinkNavi } from '../utils/utils'
import { t } from '../config/translation'

const INDICATOR_TRANSITION =
  'left 260ms cubic-bezier(0.4, 0, 0.2, 1), width 260ms cubic-bezier(0.4, 0, 0.2, 1)'

// MUI の Tab と同じフォント（テーマで typography.fontFamily を上書きしていないので MUI 既定値）。
// 指定しないと素の <a> が body 継承に失敗して Safari で明朝体になる／MUI と字面がズレる。
const TAB_FONT_FAMILY = '"Roboto", "Helvetica", "Arial", sans-serif'

/**
 * カテゴリタブバー（自前実装）。MUI の Tabs を使わず、横スクロールと下線インジケータを自前で持つ。
 * これで「アクティブタブを中央寄せ」をスムーズかつ確実に実現する（MUI Tabs の scrollSelectedIntoView は
 * 選択タブを端ギリギリまでしか寄せず、かつ自前のスムーズ中央寄せと競合して幅広タブが右に張り付いたり
 * 下線がズレたりする。自前なら競合しない）。
 * - タブは本物の <a href>（SEO / 送客）。同一ページ遷移はクリックを奪って Swiper を slideTo する。
 * - cateIndex が変わるたび: 下線を選択タブへスライド（CSS transition）＋コンテナを rAF でスムーズに
 *   中央へスクロール。下線はスクロール内容と一緒に動くので選択タブの真下に追従する。
 * - 見た目は従来の MUI 版に合わせる（太字・選択は --c-text-1 / 非選択は --c-text-4・下線 2px）。
 */
const CategoryTabsBar = memo(function CategoryTabsBar({
  cateIndex,
  onSelect,
}: {
  cateIndex: number
  onSelect: (index: number) => void
}) {
  const scrollerRef = useRef<HTMLDivElement>(null)
  const tabRefs = useRef<(HTMLAnchorElement | null)[]>([])
  const mountedRef = useRef(false)
  const rafRef = useRef(0)
  const [indicator, setIndicator] = useState({ left: 0, width: 0, animate: false })

  useLayoutEffect(() => {
    const scroller = scrollerRef.current
    const tab = tabRefs.current[cateIndex]
    if (!scroller || !tab) return

    // 下線を選択タブへ（content 座標）。初回はアニメさせない。
    setIndicator({ left: tab.offsetLeft, width: tab.offsetWidth, animate: mountedRef.current })

    // 選択タブが中央に来る scrollLeft（端は越えないよう clamp）。
    const max = scroller.scrollWidth - scroller.clientWidth
    const target = Math.max(
      0,
      Math.min(max, tab.offsetLeft + tab.offsetWidth / 2 - scroller.clientWidth / 2)
    )

    // 初回（ページ読み込み時）はアニメ無しで即中央へ。
    if (!mountedRef.current) {
      mountedRef.current = true
      scroller.scrollLeft = target
      return
    }

    cancelAnimationFrame(rafRef.current)
    const from = scroller.scrollLeft
    if (Math.abs(target - from) < 1) return

    const duration = 300
    const easeOutCubic = (p: number) => 1 - Math.pow(1 - p, 3)
    let start = 0
    const step = (ts: number) => {
      if (!start) start = ts
      const p = Math.min(1, (ts - start) / duration)
      scroller.scrollLeft = from + (target - from) * easeOutCubic(p)
      if (p < 1) rafRef.current = requestAnimationFrame(step)
    }
    rafRef.current = requestAnimationFrame(step)
  }, [cateIndex])

  useEffect(() => () => cancelAnimationFrame(rafRef.current), [])

  return (
    <div
      ref={scrollerRef}
      className="hide-scrollbar-x category-tab"
      role="tablist"
      aria-label={t('オープンチャットのカテゴリータブ')}
      style={{
        position: 'relative',
        display: 'flex',
        alignItems: 'stretch',
        minHeight: 39,
        borderBottom: '1px solid var(--c-border)',
        WebkitOverflowScrolling: 'touch',
      }}
    >
      {OPEN_CHAT_CATEGORY.map((el, i) => {
        const selected = i === cateIndex
        return (
          <a
            key={i}
            ref={(node) => {
              tabRefs.current[i] = node
            }}
            role="tab"
            aria-selected={selected}
            href={`/${basePath}${el[1] ? '/' + el[1] : ''}`}
            onClick={(e) => {
              if (samePageLinkNavi(e)) {
                e.preventDefault()
                onSelect(i)
              }
            }}
            style={{
              flex: '0 0 auto',
              display: 'flex',
              alignItems: 'center',
              padding: '0 16px',
              fontFamily: TAB_FONT_FAMILY,
              fontSize: '0.875rem',
              fontWeight: 700,
              whiteSpace: 'nowrap',
              textDecoration: 'none',
              userSelect: 'none',
              color: selected ? 'var(--c-text-1)' : 'var(--c-text-4)',
            }}
          >
            {el[0]}
          </a>
        )
      })}
      <span
        aria-hidden="true"
        style={{
          position: 'absolute',
          bottom: 0,
          height: 2,
          left: indicator.left,
          width: indicator.width,
          background: 'var(--c-text-1)',
          transition: indicator.animate ? INDICATOR_TRANSITION : 'none',
        }}
      />
    </div>
  )
})

export default CategoryTabsBar
