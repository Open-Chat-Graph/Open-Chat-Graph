import { basePath, OPEN_CHAT_CATEGORY } from '../config/config'
import React, { memo, useCallback, useLayoutEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Box, Tabs, Tab } from '@mui/material'
import { type Swiper as SwiperCore } from 'swiper'
import { Swiper, SwiperSlide } from 'swiper/react'
import 'swiper/css'
import FetchOpenChatRankingList, { DummyOpenChatRankingList } from './FetchOpenChatRankingList'
import {
  isSP,
  samePageLinkNavi,
  scrollToTop,
  setTitle,
  updateURLSearchParams,
} from '../utils/utils'
import { CategoryListAppBar } from './CategoryListAppBar'
import { listParamsState } from '../store/atom'
import { useRecoilState } from 'recoil'
import SiteHeader from './SiteHeader'
import { useInView } from 'react-intersection-observer'
import { t } from '../config/translation'

function LinkTab(props: { label?: string; href?: string }) {
  return (
    <Tab
      component='a'
      onClick={(e: LinkEvent) => {
        if (samePageLinkNavi(e)) {
          e.preventDefault()
        }
      }}
      {...props}
    />
  )
}

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
  swiperRef: React.MutableRefObject<SwiperCore | null>
}) {
  const navigate = useNavigate()
  const [params, setParams] = useRecoilState(listParamsState)
  const initialIndex = useRef(cateIndex)
  const currentIndex = useRef(cateIndex)
  const scrollY = useRef(0)
  const [tIndex, setTIndex] = useState<[number, string] | null>(null)
  const { ref: prevRef, inView: prevInView } = useInView()
  const { ref: nextRef, inView: nextInView } = useInView()

  // eslint-disable-next-line react-hooks/exhaustive-deps
  const onSwiper = useCallback((swiper: SwiperCore) => (swiperRef.current = swiper), [])

  currentIndex.current = cateIndex

  const onSlideChange = useCallback((swiper: SwiperCore) => {
    const newValue = swiper.activeIndex
    if (currentIndex.current === newValue) return

    setParams((params) => {
      const category = OPEN_CHAT_CATEGORY[newValue][1]
      const url = updateURLSearchParams({ ...params, sub_category: '' })
      const q = url.searchParams.toString()
      navigate(
        `${'/' + basePath}${category ? '/' + category : ''}${
          q ? '?' + q : ''
        }`,
        { replace: true }
      )
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
      {OPEN_CHAT_CATEGORY.map((el, i) => (
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
              if (i === cateIndex) {
                return <OpenChatRankingList query={getQuery(i, cateIndex, params)} cateIndex={i} />
              } else if (tIndex && i === tIndex[0]) {
                return <OpenChatRankingList query={tIndex[1]} cateIndex={i} />
              } else if (!tIndex && prevInView && i === cateIndex - 1) {
                return (
                  <DummyOpenChatRankingList query={getQuery(i, cateIndex, params)} cateIndex={i} />
                )
              } else if (!tIndex && nextInView && i === cateIndex + 1) {
                return (
                  <DummyOpenChatRankingList query={getQuery(i, cateIndex, params)} cateIndex={i} />
                )
              }
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

  const handleChange = useCallback((e: React.SyntheticEvent, newValue: number) => {
    if (e.type === 'click' && !samePageLinkNavi(e as LinkEvent)) return
    swiperRef.current?.slideTo(newValue)
  }, [])

  const siperSlideTo = (index: number): void => {
    swiperRef.current?.slideTo(index)
  }

  return (
    <Box>
      <SiteHeader siperSlideTo={siperSlideTo} height='78px'>
        <Tabs
          className='fix-min-width category-tab'
          value={cateIndex}
          onChange={handleChange}
          variant='scrollable'
          scrollButtons={true}
          allowScrollButtonsMobile={!isSP()}
          aria-label={t('オープンチャットのカテゴリータブ')}
          sx={{ borderBottom: 1, borderColor: 'divider' }}
        >
          {OPEN_CHAT_CATEGORY.map((el, i) => (
            <LinkTab
              label={el[0]}
              href={`/${basePath}${el[1] ? '/' + el[1] : ''}`}
              key={i}
            />
          ))}
        </Tabs>
      </SiteHeader>
      <CategoryListAppBar />
      <OcListSwiper cateIndex={cateIndex} swiperRef={swiperRef} />
    </Box>
  )
}
