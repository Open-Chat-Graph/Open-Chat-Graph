import { basePath, OPEN_CHAT_CATEGORY } from '../config/config'
import React, { memo, useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Box } from '@mui/material'
import { type Swiper as SwiperCore } from 'swiper'
import { Swiper, SwiperSlide } from 'swiper/react'
import 'swiper/css'
import FetchOpenChatRankingList, { DummyOpenChatRankingList } from './FetchOpenChatRankingList'
import { scrollToTop, setTitle, updateURLSearchParams } from '../utils/utils'
import { CategoryListAppBar } from './CategoryListAppBar'
import { listParamsState } from '../store/atom'
import { useAtom } from 'jotai'
import SiteHeader from './SiteHeader'
import { useInView } from 'react-intersection-observer'
import RecommendThemeShelf from './RecommendThemeShelf'
import CategoryTabsBar from './CategoryTabsBar'
import { trackEvent } from '../utils/track'

const OpenChatRankingList = memo(FetchOpenChatRankingList)

const getQuery = (i: number, cateIndex: number, params: ListParams) =>
  new URLSearchParams({
    ...params,
    sub_category: i === cateIndex ? params.sub_category : '',
    category: OPEN_CHAT_CATEGORY[i][1].toString(),
  }).toString()

function OcListSwiper({
  cateIndex,
  swiperRef,
}: {
  cateIndex: number
  swiperRef: React.RefObject<SwiperCore | null>
}) {
  const navigate = useNavigate()
  const [params, setParams] = useAtom(listParamsState)
  const initialIndex = useRef(cateIndex)
  const currentIndex = useRef(cateIndex)
  const scrollY = useRef(0)
  const [tIndex, setTIndex] = useState<[number, string] | null>(null)
  const { ref: prevRef, inView: prevInView } = useInView()
  const { ref: nextRef, inView: nextInView } = useInView()

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const onSwiper = useCallback((swiper: SwiperCore) => (swiperRef.current = swiper), [])

  currentIndex.current = cateIndex

  useEffect(() => {
    const swiper = swiperRef.current
    if (swiper && swiper.activeIndex !== cateIndex) {
      swiper.slideTo(cateIndex, 0)
    }
  }, [cateIndex, swiperRef])

  const onSlideChange = useCallback((swiper: SwiperCore) => {
    const newValue = swiper.activeIndex
    if (currentIndex.current === newValue) return

    trackEvent('ranking_action', { action: `category:${OPEN_CHAT_CATEGORY[newValue][1]}` })

    setParams((params) => {
      const category = OPEN_CHAT_CATEGORY[newValue][1]
      const url = updateURLSearchParams({ ...params, sub_category: '' })
      const q = url.searchParams.toString()
      navigate(`${'/' + basePath}${category ? '/' + category : ''}${q ? '?' + q : ''}`, {
        replace: true,
      })
      setTitle({ ...params, sub_category: '' }, newValue)
      return { ...params, sub_category: '' }
    })

    scrollToTop()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  if (tIndex && !scrollY.current) {
    scrollY.current = window.scrollY
  }

  if (!tIndex && scrollY.current) {
    scrollY.current = 0
  }

  useLayoutEffect(() => {
    if (tIndex && scrollY.current) {
      scrollToTop()
    }
  }, [tIndex])

  return (
    <Swiper
      initialSlide={initialIndex.current}
      simulateTouch={true}
      onSlideChange={onSlideChange}
      onSlideChangeTransitionStart={() =>
        setTIndex([cateIndex, getQuery(cateIndex, cateIndex, params)])
      }
      onSlideChangeTransitionEnd={() => setTIndex(null)}
      onSwiper={onSwiper}
      speed={260}
    >
      {OPEN_CHAT_CATEGORY.map((_el, i) => (
        <SwiperSlide key={i}>
          <div
            style={{
              padding: '1rem 1rem 1rem 1rem',
              marginTop: '94px',
              minHeight: 'calc(100svh - 191px)',
              width: '100%',
              position: 'relative',
              ...(tIndex && i === tIndex[0]
                ? { position: 'absolute', top: `${-(scrollY.current + (i ? 0 : 48))}px` }
                : {}),
            }}
          >
            {(() => {
              const isActive = i === cateIndex
              const isTransition = !!tIndex && i === tIndex[0]

              // リストは inView の隣接スライドだけ描画（全カテゴリ同時描画は避ける）。
              // ここで tIndex(遷移中)をゲートに入れない: 入れると、スワイプ開始(transitionStart)で
              // tIndex がセットされた瞬間に「入ってくる側スライド」のダミーが消える一方、まだ cateIndex は
              // 更新されておらず active にもならないため、可視スライドが1フレーム“リスト無し”になって
              // チラつく。遷移中もダミーを保持して空フレームを無くす。
              const isPrev = prevInView && i === cateIndex - 1
              const isNext = nextInView && i === cateIndex + 1
              const showList = isActive || isTransition || isPrev || isNext
              if (!showList) return null

              // 関連テーマ棚はリストと一緒に出す。
              // - active / 遷移中: 通常フロー（リストの上）。リストごと縦スクロールする。
              // - ダミー（隣接スライド）: ダミーリストは絶対配置(top:scrollY)なので、棚を通常フローに
              //   置くと棚の高さ分だけダミーリストを押し下げてマージン過多になり、棚自身もスクロールで
              //   画面外へ消える（スクロール中スワイプの不具合）。棚をダミーの絶対配置コンテナ内に入れ、
              //   リストと一緒に正しい位置へ置く。
              const shelf = (
                <RecommendThemeShelf
                  category={OPEN_CHAT_CATEGORY[i][1]}
                  subCategory={isActive ? params.sub_category : ''}
                />
              )

              if (isActive) {
                return (
                  <>
                    {shelf}
                    <OpenChatRankingList query={getQuery(i, cateIndex, params)} cateIndex={i} />
                  </>
                )
              }
              if (isTransition && tIndex) {
                return (
                  <>
                    {shelf}
                    <OpenChatRankingList query={tIndex[1]} cateIndex={i} />
                  </>
                )
              }
              return (
                <DummyOpenChatRankingList
                  query={getQuery(i, cateIndex, params)}
                  cateIndex={i}
                  shelf={shelf}
                />
              )
            })()}
            {i === cateIndex && (
              <div
                style={{ position: 'absolute', top: 0, left: '-2px', width: '1px', height: '100%' }}
                ref={prevRef}
              />
            )}
            {i === cateIndex && (
              <div
                style={{
                  position: 'absolute',
                  top: 0,
                  right: '-2px',
                  width: '1px',
                  height: '100%',
                }}
                ref={nextRef}
              />
            )}
          </div>
        </SwiperSlide>
      ))}
    </Swiper>
  )
}

export default function OcListMainTabs({ cateIndex }: { cateIndex: number }) {
  const swiperRef = useRef<SwiperCore | null>(null)

  const handleSelectCategory = useCallback((index: number) => {
    swiperRef.current?.slideTo(index)
  }, [])

  const siperSlideTo = (index: number): void => {
    swiperRef.current?.slideTo(index)
  }

  return (
    <Box>
      <SiteHeader siperSlideTo={siperSlideTo} height="78px">
        <CategoryTabsBar cateIndex={cateIndex} onSelect={handleSelectCategory} />
      </SiteHeader>
      <CategoryListAppBar />
      <OcListSwiper cateIndex={cateIndex} swiperRef={swiperRef} />
    </Box>
  )
}
