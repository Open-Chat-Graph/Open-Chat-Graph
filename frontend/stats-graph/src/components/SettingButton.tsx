import {
  Divider,
  IconButton,
  ListItemIcon,
  ListItemText,
  Menu,
  MenuItem,
  MenuList,
} from '@mui/material'
import SettingsIcon from '@mui/icons-material/Settings'
import { Check } from '@mui/icons-material'
import React from 'preact/compat'
import { defaultBar, setStoregeBarSetting } from '../util/urlParam'
import { useState } from 'react'
import { t } from '../util/translation'

const menuListToggleChart: [string, ToggleChart][] = [
  [t('順位表示なし'), 'none'],
  [t('ランキング'), 'ranking'],
  [t('急上昇'), 'rising'],
]

function DenseMenu({
  handleSelect,
  bar,
}: {
  handleSelect: (bar: ToggleChart) => void
  bar: ToggleChart
}) {
  return (
    <MenuList>
      <MenuItem disabled>{t('順位グラフの初期表示')}</MenuItem>
      <Divider />
      {menuListToggleChart.map((el) => (
        <MenuItem onClick={() => handleSelect(el[1])}>
          {bar !== el[1] && <ListItemText inset>{el[0]}</ListItemText>}
          {bar === el[1] && (
            <>
              <ListItemIcon>
                <Check />
              </ListItemIcon>
              {el[0]}
            </>
          )}
        </MenuItem>
      ))}
    </MenuList>
  )
}

export default function SettingButton() {
  const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null)
  const [bar, setBar] = useState(defaultBar)

  const open = Boolean(anchorEl)
  const handleClick = (event: MouseEvent) => {
    setAnchorEl(event.target as HTMLElement)
  }

  const handleClose = () => {
    setAnchorEl(null)
  }

  const handleSelect = (bar: ToggleChart) => {
    setStoregeBarSetting(bar)
    setBar(bar)
    handleClose()
  }

  return (
    <div>
      <IconButton
        id='basic-button'
        aria-controls={open ? 'basic-menu' : undefined}
        aria-haspopup='true'
        aria-expanded={open ? 'true' : undefined}
        onClick={handleClick}
        ariat-label={t('設定')}
      >
        <SettingsIcon />
      </IconButton>
      <Menu
        id='basic-menu'
        anchorEl={anchorEl}
        open={open}
        onClose={handleClose}
        MenuListProps={{
          'aria-labelledby': 'basic-button',
        }}
      >
        <DenseMenu bar={bar} handleSelect={handleSelect} />
      </Menu>
    </div>
  )
}
