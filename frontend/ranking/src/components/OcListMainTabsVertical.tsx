import { basePath, OPEN_CHAT_CATEGORY } from '../config/config'
import React from 'react'
import { useNavigate } from 'react-router-dom'
import { Box, Tabs, Tab } from '@mui/material'
import FetchOpenChatRankingList from './FetchOpenChatRankingList'
import { samePageLinkNavi, scrollToTop, setTitle, updateURLSearchParams } from '../utils/utils'
import { listParamsState } from '../store/atom'
import { useRecoilState } from 'recoil'
import { CategoryListAppBar } from './CategoryListAppBar'
import SiteHeaderVertical from './SiteHeaderVertical'
import SiteHeaderVerticalSearch from './SiteHeaderVerticalSearch'
import { t } from '../config/translation'

function TabPanel({ children, value, index }: TabPanelProps) {
  return <div hidden={value !== index}>{value === index && children}</div>
}

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

export default function OcListMainTabsVertical({ cateIndex }: { cateIndex: number }) {
  const navigate = useNavigate()
  const [params, setParams] = useRecoilState(listParamsState)

  const handleChange = (e: React.SyntheticEvent, newValue: number) => {
    if (e.type === 'click' && !samePageLinkNavi(e as LinkEvent)) return

    const category = OPEN_CHAT_CATEGORY[newValue][1]
    const url = updateURLSearchParams({ ...params, sub_category: '' })
    const q = url.searchParams.toString()

    setParams({ ...params, sub_category: '' })
    navigate(
      `${'/' + basePath}${category ? '/' + category : ''}${
        q ? '?' + q : ''
      }`,
      { replace: true }
    )
    setTitle({ ...params, sub_category: '' }, newValue)
    scrollToTop()
    scrollToTop('.hide-scrollbar-x')
  }

  const query = new URLSearchParams({
    category: OPEN_CHAT_CATEGORY[cateIndex][1].toString(),
    ...params,
  }).toString()

  const tabsWidth = 170
  const maxWidth = 812
  const siteHeaderHeight = '72px'

  return (
    <Box
      sx={{
        maxWidth,
        display: 'flex',
        justifyContent: 'center',
        margin: '0 auto',
        overflowX: 'clip',
      }}
    >
      <Box sx={{ minWidth: tabsWidth }}>
        <Box sx={{ position: 'sticky', top: 0 }}>
          <SiteHeaderVertical height={siteHeaderHeight} />
        </Box>
        <Tabs
          className='category-tab-pc'
          value={cateIndex}
          onChange={handleChange}
          variant='scrollable'
          scrollButtons='auto'
          allowScrollButtonsMobile={true}
          aria-label={t('オープンチャットのカテゴリータブ')}
          orientation='vertical'
          sx={{
            minWidth: tabsWidth,
            height: `calc(100% - ${siteHeaderHeight} - 2rem)`,
            position: 'fixed',
            zIndex: 2,
          }}
        >
          {OPEN_CHAT_CATEGORY.map((el, i) => (
            <LinkTab
              label={el[0]}
              href={`/${basePath}${el[1] ? '/' + el[1] : ''}`}
              key={i}
            />
          ))}
        </Tabs>
      </Box>
      <Box
        sx={{
          width: `calc(100% - ${tabsWidth}px)`,
          borderLeft: 1,
          borderRight: 1,
          borderColor: '#efefef',
          minHeight: '100vh',
        }}
      >
        <Box
          sx={{
            position: 'sticky',
            top: 0,
            width: '100%',
            zIndex: 1150,
          }}
        >
          <CategoryListAppBar />
          <SiteHeaderVerticalSearch />
        </Box>
        <Box sx={{ p: '1.5rem', pt: '1rem' }}>
          {OPEN_CHAT_CATEGORY.map((el, i) => (
            <TabPanel value={cateIndex} index={i} key={i}>
              <FetchOpenChatRankingList query={query} cateIndex={cateIndex} />
            </TabPanel>
          ))}
        </Box>
      </Box>
    </Box>
  )
}
