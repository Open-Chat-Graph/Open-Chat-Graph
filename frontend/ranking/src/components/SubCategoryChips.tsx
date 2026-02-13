import React, { memo, useEffect, useRef } from 'react'
import { Box, Button, Chip, Stack, Toolbar } from '@mui/material'
import { useParams } from 'react-router-dom'
import { useDraggable } from 'react-use-draggable-scroll'
import { isSP } from '../utils/utils'
import { ChevronLeft, ChevronRight } from '@mui/icons-material'
import { useIsLeftRightScrollable, useIsRightScrollable } from '../hooks/ScrollableHooks'
import { useSetListParams } from '../hooks/ListParamsHooks'
import { rankingArgDto } from '../config/config'
import { useRecoilState } from 'recoil'
import { subCategoryChipsStackScrollLeft } from '../store/atom'

const Chips = memo(function Chips({
  sub_category,
  stackParentRef,
}: SubCategoryChipsProps & { stackParentRef: React.RefObject<HTMLDivElement> }) {
  const selectedRef = useRef<null | HTMLDivElement>(null)
  const [stackScrollLeft, setStackScrollLeft] = useRecoilState(subCategoryChipsStackScrollLeft)
  const { category } = useParams()
  const setParams = useSetListParams()
  const existsProp = category && Object.hasOwn(rankingArgDto.subCategories, category)

  const handleChange = (newValue: ListParams['sub_category']) => {
    setStackScrollLeft(stackParentRef.current?.scrollLeft || 0)
    setParams((params) => ({ ...params, sub_category: newValue }))
  }

  useEffect(() => {
    if (selectedRef.current && stackParentRef.current && stackScrollLeft > 0) {
      stackParentRef.current.scrollLeft = stackScrollLeft
    } else if (selectedRef.current && stackParentRef.current) {
      selectedRef.current.scrollIntoView({ inline: 'center' })
    }
  }, [])

  return (
    <Stack direction='row' spacing={1}>
      {existsProp &&
        rankingArgDto.subCategories[category as SubCategoryKey].map((el, i) =>
          sub_category === el ? (
            <Chip
              key={i}
              label={el}
              className='openchat-item-header-chip category selected'
              onClick={() => handleChange('')}
              ref={selectedRef}
            />
          ) : (
            <Chip
              key={i}
              label={el}
              className='openchat-item-header-chip category'
              onClick={() => handleChange(el)}
            />
          )
        )}
    </Stack>
  )
})

const chevronBradientBoxSx = (gradientDeg: number) => ({
  position: 'absolute',
  zIndex: 1155,
  top: 0,
  background: `linear-gradient(${gradientDeg}deg, rgba(255,255,255,1) 55%, rgba(0,0,0,0) 100%)`,
})

const buttonSx = { minWidth: 40, minHeight: 48 }
const chevronSx = { fontSize: '1.25rem' }

function SubCategoryChipsPC(props: SubCategoryChipsProps) {
  const { category } = useParams()
  const [isLeftScrollable, isRightScrollable, ref] = useIsLeftRightScrollable(category)

  const { events } = useDraggable(ref, {
    applyRubberBandEffect: true,
    decayRate: 0.95,
  })

  const onClickArrow = (value: number) => () => {
    ref.current?.scrollTo({
      left: ref.current.scrollLeft - value,
      behavior: 'smooth',
    })
  }

  return (
    <div style={{ position: 'relative', width: '100%' }}>
      {isLeftScrollable && (
        <Box sx={{ ...chevronBradientBoxSx(90) }}>
          <Button
            color='inherit'
            aria-label='left scroll'
            onClick={onClickArrow(250)}
            sx={buttonSx}
          >
            <ChevronLeft sx={chevronSx} />
          </Button>
        </Box>
      )}
      <Toolbar
        className='hide-scrollbar-x'
        {...events}
        ref={ref}
        style={{ minHeight: 48, cursor: 'grab' }}
      >
        <Chips {...{ stackParentRef: ref, ...props }} />
      </Toolbar>
      {isRightScrollable && (
        <Box sx={{ ...chevronBradientBoxSx(270), right: 0 }}>
          <Button
            color='inherit'
            aria-label='right scroll'
            onClick={onClickArrow(-250)}
            sx={buttonSx}
          >
            <ChevronRight sx={chevronSx} />
          </Button>
        </Box>
      )}
    </div>
  )
}

const gradientBoxSx = {
  position: 'absolute',
  zIndex: 1155,
  top: 0,
  minWidth: 52,
  minHeight: 43,
  right: 0,
  background: 'linear-gradient(270deg, rgba(255,255,255,0.93) 50%, rgba(0,0,0,0) 100%)',
}

function SubCategoryChipsSP2(props: SubCategoryChipsProps) {
  const { category } = useParams()
  const [isRightVisible, ref] = useIsRightScrollable(category)

  return (
    <div style={{ position: 'relative', width: '100%' }}>
      <Toolbar
        className='hide-scrollbar-x'
        ref={ref}
        style={{
          minHeight: 0,
          height: 44,
          paddingRight: 0,
          paddingBottom: '8px',
          marginTop: '8px',
        }}
      >
        <Chips {...{ stackParentRef: ref, ...props }} />
      </Toolbar>
      {isRightVisible && <Box sx={{ ...gradientBoxSx }} />}
    </div>
  )
}

const SubCategoryChips = memo(function SubCategoryChips(props: SubCategoryChipsProps) {
  const { category } = useParams()

  if (!rankingArgDto.subCategories[category as SubCategoryKey]?.length) return <></>

  return isSP() ? <SubCategoryChipsSP2 {...props} /> : <SubCategoryChipsPC {...props} />
})

export default SubCategoryChips
