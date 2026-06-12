import { memo } from 'react'
import useSWR from 'swr'
import { Chip } from '@mui/material'
import { useAtomValue } from 'jotai'
import { useParams } from 'react-router-dom'
import { rankingArgDto } from '../config/config'
import { t } from '../config/translation'
import { isSP } from '../utils/utils'
import { listParamsState } from '../store/atom'
import { useDraggableScroll } from '../hooks/useDraggableScroll'
import { useIsRightScrollable } from '../hooks/ScrollableHooks'

// urlRoot: '' (ja) | '/tw' | '/th'。slug はサーバ側 urlencode 済みなので再エンコードしない。
const recommendHref = (slug: string) => `${rankingArgDto.urlRoot}/recommend/${slug}`

const fetcher = (url: string): Promise<ThemeTag[]> =>
  // X-Ocg-Client: サイト内JSからのfetchであることを示す（Cloudflare側で検証。直叩き収集対策）
  fetch(url, { headers: { Accept: 'application/json', 'X-Ocg-Client': '1' } }).then((r) =>
    r.ok ? r.json() : []
  )

/**
 * 回遊シェルフ（#1ページ /ranking → 高RPMの /recommend）。
 * いま表示中（カテゴリ・検索・list[時間軸]・sort・order）の上位ルームが持つ recommend タグを
 * /oclist-tags から取得してチップ表示する。ランキングの絞り込みが変わると中身も連動する。
 * - サブカテゴリ絞り込みchip(灰)と区別するため緑系（CSS .theme-link）。本物の <a href>（SEO/送客）。
 * - 横スクロール: PC(非タッチ)はドラッグ、SP(タッチ)は Swiper にジェスチャを奪われないよう
 *   swiper-no-swiping を付与しネイティブスクロール。右端フェードで可スクロールを明示。
 * - タグが無い文脈では何も出さない。並び替え中も keepPreviousData でちらつかせない。
 */
const RecommendThemeShelf = memo(function RecommendThemeShelf() {
  const params = useAtomValue(listParamsState)
  const { category } = useParams()
  const sp = isSP()

  // ランキング一覧と同じ絞り込みを送る（先頭ページの上位ルームを対象に集約）。
  const query = new URLSearchParams({
    category: category ? String(parseInt(category, 10) || 0) : '0',
    list: params.list,
    sort: params.sort,
    order: params.order,
    sub_category: params.sub_category,
    keyword: params.keyword,
    limit: '20',
    page: '0',
  }).toString()

  const { data } = useSWR(`${rankingArgDto.baseUrl}/oclist-tags?${query}`, fetcher, {
    keepPreviousData: true,
    revalidateOnFocus: false,
  })
  const themes = data ?? []

  const [isRightScrollable, scrollRef] = useIsRightScrollable(themes)
  const { events } = useDraggableScroll(scrollRef)

  if (themes.length === 0) return null

  return (
    <section
      aria-label={t('関連テーマ')}
      style={{ display: 'block', marginBottom: 10, fontFamily: 'var(--font-family, sans-serif)' }}
    >
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 6,
          fontSize: 13,
          fontWeight: 800,
          color: 'var(--c-green-shelf)',
          marginBottom: 8,
          letterSpacing: '0.02em',
        }}
      >
        <span
          aria-hidden="true"
          style={{ flex: '0 0 auto', width: 3, height: 14, background: 'var(--c-brand)', borderRadius: 2 }}
        />
        {t('関連テーマ')}
        <span aria-hidden="true" style={{ fontSize: 12 }}>✨</span>
      </div>
      <div style={{ position: 'relative' }}>
        <div
          ref={scrollRef}
          {...(sp ? {} : events)}
          className="hide-scrollbar-x swiper-no-swiping"
          style={{
            display: 'flex',
            gap: 8,
            overflowX: 'auto',
            paddingBottom: 2,
            cursor: sp ? 'auto' : 'grab',
            scrollbarWidth: 'none',
          }}
        >
          {themes.map((theme, i) => (
            <Chip
              key={`${theme.slug}-${i}`}
              component="a"
              href={recommendHref(theme.slug)}
              label={theme.name}
              clickable
              draggable={false}
              className="openchat-item-header-chip category theme-link"
              sx={{ flexShrink: 0 }}
            />
          ))}
        </div>
        {isRightScrollable && (
          <div
            aria-hidden="true"
            style={{
              position: 'absolute',
              top: 0,
              right: 0,
              height: '100%',
              width: 36,
              pointerEvents: 'none',
              background: 'linear-gradient(270deg, var(--c-bg) 35%, var(--c-bg-fade-end) 100%)',
            }}
          />
        )}
      </div>
    </section>
  )
})

export default RecommendThemeShelf
