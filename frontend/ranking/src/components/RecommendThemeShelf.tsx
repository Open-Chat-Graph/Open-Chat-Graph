import { memo } from 'react'
import useSWR from 'swr'
import { Chip, Skeleton } from '@mui/material'
import { useAtomValue } from 'jotai'
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

// チップ取得待ちのプレースホルダ（pill 形・高さ32でチップと同寸）。見出しは静的なので
// スケルトンにせず実テキストを出し、API から来るチップ部分だけをこれで埋めて高さを確保する。
const SKELETON_CHIP_WIDTHS = [76, 56, 92, 64, 84]

/**
 * 回遊シェルフ（#1ページ /ranking → 高RPMの /recommend）。
 * いま表示中（カテゴリ・検索・list[時間軸]・sort・order）の上位ルームが持つ recommend タグを
 * /oclist-tags から取得してチップ表示する。ランキングの絞り込みが変わると中身も連動する。
 * - 見出し「関連テーマ」は静的テキスト。チップ（タグ）だけが API 取得なので、取得待ちは
 *   チップ部分のみスケルトンにする（見出しは最初から実テキストで出す）。
 * - サブカテゴリ絞り込みchip(灰)と区別するため緑系（CSS .theme-link）。本物の <a href>（SEO/送客）。
 * - 横スクロール: PC(非タッチ)はドラッグ、SP(タッチ)は Swiper にジェスチャを奪われないよう
 *   swiper-no-swiping を付与しネイティブスクロール。右端フェードで可スクロールを明示。
 * - category / subCategory は描画中スライドの値を受け取る（隣接スライドにも先出しできるよう props 化）。
 *   リストと同じく描画開始時から取得を始め、取得前はチップをスケルトンで埋めて高さを確保する
 *   （スワイプ後にチップが遅れて入って下のリストが押し下げられる「ガタッ」を防ぐ）。
 * - 取得後にタグが無い文脈では何も出さない。並び替え中も keepPreviousData でちらつかせない。
 */
const RecommendThemeShelf = memo(function RecommendThemeShelf({
  category,
  subCategory,
}: {
  category: number
  subCategory: string
}) {
  const params = useAtomValue(listParamsState)
  const sp = isSP()

  // ランキング一覧と同じ絞り込みを送る（先頭ページの上位ルームを対象に集約）。
  const query = new URLSearchParams({
    category: String(category),
    list: params.list,
    sort: params.sort,
    order: params.order,
    sub_category: subCategory,
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

  // 取得前はチップをスケルトンで埋める。取得後にタグが無ければ何も出さない（見出しごと畳む）。
  const loading = data === undefined
  if (!loading && themes.length === 0) return null

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
            overflowX: loading ? 'hidden' : 'auto',
            paddingBottom: 2,
            cursor: sp ? 'auto' : 'grab',
            scrollbarWidth: 'none',
          }}
        >
          {loading
            ? SKELETON_CHIP_WIDTHS.map((w, i) => (
                <Skeleton
                  key={i}
                  variant="rounded"
                  width={w}
                  height={32}
                  aria-hidden="true"
                  sx={{ flex: '0 0 auto', borderRadius: '99px' }}
                />
              ))
            : themes.map((theme, i) => (
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
