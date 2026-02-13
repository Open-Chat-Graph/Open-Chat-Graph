import { useMediaQuery, Button, Menu, MenuItem, Box } from '@mui/material'
import React, { memo } from 'react'
import SortIcon from '@mui/icons-material/Sort'
import { isSP } from '../utils/utils'
import { useSetListParams } from '../hooks/ListParamsHooks'
import { t } from '../config/translation'

export const rankingOptions2: SortOptions = [
  /* [['ランキング順', t('並び順')], 'asc', 'rank'], */
  [[t('増加数が多い順'), t('並び順')], 'desc', 'increase'],
  [[t('増加数が少ない順'), t('並び順')], 'asc', 'increase'],
  [[t('増加率が高い順'), t('並び順')], 'desc', 'rate'],
  [[t('増加率が低い順'), t('並び順')], 'asc', 'rate'],
]

export const allOptions2: SortOptions = [
  [[t('メンバー数が多い順'), t('並び順')], 'desc', 'member'],
  [[t('メンバー数が少ない順'), t('並び順')], 'asc', 'member'],
  /*  [['作成日が新しい順', t('並び順')], 'desc', 'created_at'], */
  /* [['作成日が古い順', t('並び順')], 'asc', 'created_at'], */
]

export const OCListSortMenu = memo(function OCListSortMenu({
  options,
  order,
  sort,
}: {
  options: SortOptions
  order: ListParams['order']
  sort: ListParams['sort']
}) {
  const setParams = useSetListParams()
  const under359 = useMediaQuery('(max-width:359px)')
  const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null)
  const open = Boolean(anchorEl)

  let selectedIndex = options.findIndex((el) => order === el[1] && sort === el[2])
  if (selectedIndex === -1) {
    selectedIndex = 0
  }

  const handleClickListItem = (e: ClickEvent) => {
    setAnchorEl(e.currentTarget)
  }

  const handleMenuItemClick = (e: ClickEvent, i: number) => {
    setParams((params) => ({ ...params, order: options[i][1], sort: options[i][2] }))
    setAnchorEl(null)
  }

  const handleClose = () => {
    setAnchorEl(null)
  }

  return (
    <Box sx={{ pl: under359 ? undefined : '8px', whiteSpace: 'nowrap' }}>
      <Button
        id='sort-button'
        aria-controls={open ? 'sort-menu' : undefined}
        aria-haspopup='true'
        aria-expanded={open ? 'true' : undefined}
        onClick={handleClickListItem}
        sx={{ fontSize: under359 ? '12.5px' : '14px', color: '#000', minWidth: 36, minHeight: 36 }}
        color='success'
      >
        {!under359 && <SortIcon sx={{ marginRight: '4px', fontSize: '20px', color: '#000' }} />}
        {isSP() || under359 ? `${options[selectedIndex][0][1]}` : options[selectedIndex][0][0]}
      </Button>
      <Menu
        id='sort-menu'
        anchorEl={anchorEl}
        open={open}
        onClose={handleClose}
        MenuListProps={{
          'aria-labelledby': 'sort-button',
        }}
      >
        {options.map((el, i) => (
          <MenuItem
            key={i}
            selected={i === selectedIndex}
            onClick={(event) => handleMenuItemClick(event, i)}
          >
            {el[0][0]}
          </MenuItem>
        ))}
      </Menu>
    </Box>
  )
})
