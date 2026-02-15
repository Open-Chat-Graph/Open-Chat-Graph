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
import {
  defaultBar,
  defaultChart,
  getStoregeFixedLimitSetting,
  setStoregeBarSetting,
  setStoregeChartSetting,
  setStoregeFixedLimitSetting,
} from '../util/urlParam'
import { useState } from 'react'
import { t } from '../util/translation'

const menuListToggleChart: [string, ToggleChart][] = [
  [t('順位表示なし'), 'none'],
  [t('ランキング'), 'ranking'],
  [t('急上昇'), 'rising'],
]

const menuListChartMode: [string, urlParamsValue<'chart'>][] = [
  [t('折れ線グラフ'), 'line'],
  [t('ローソク足'), 'candlestick'],
]

const menuListFixedLimit: [string, urlParamsValue<'limit'> | ''][] = [
  [t('固定しない'), ''],
  [t('24時間'), 'hour'],
  [t('1週間'), 'week'],
  [t('1ヶ月'), 'month'],
  [t('全期間'), 'all'],
]

function CheckableMenuItem<T extends string>({
  label,
  value,
  selected,
  onSelect,
  disabled,
}: {
  label: string
  value: T
  selected: boolean
  onSelect: (value: T) => void
  disabled?: boolean
}) {
  return (
    <MenuItem onClick={() => onSelect(value)} disabled={disabled}>
      {!selected && <ListItemText inset>{label}</ListItemText>}
      {selected && (
        <>
          <ListItemIcon>
            <Check />
          </ListItemIcon>
          {label}
        </>
      )}
    </MenuItem>
  )
}

function DenseMenu({
  handleSelectBar,
  bar,
  chartMode,
  handleSelectChartMode,
  fixedLimit,
  handleSelectFixedLimit,
}: {
  handleSelectBar: (bar: ToggleChart) => void
  bar: ToggleChart
  chartMode: urlParamsValue<'chart'>
  handleSelectChartMode: (mode: urlParamsValue<'chart'>) => void
  fixedLimit: urlParamsValue<'limit'> | ''
  handleSelectFixedLimit: (limit: urlParamsValue<'limit'> | '') => void
}) {
  return (
    <MenuList>
      <MenuItem disabled>{t('順位グラフの初期表示')}</MenuItem>
      <Divider />
      {menuListToggleChart.map((el) => (
        <CheckableMenuItem
          label={el[0]}
          value={el[1]}
          selected={bar === el[1]}
          onSelect={handleSelectBar}
        />
      ))}
      <Divider />
      <MenuItem disabled>{t('チャートの種類')}</MenuItem>
      <Divider />
      {menuListChartMode.map((el) => (
        <CheckableMenuItem
          label={el[0]}
          value={el[1]}
          selected={chartMode === el[1]}
          onSelect={handleSelectChartMode}
        />
      ))}
      <Divider />
      <MenuItem disabled>{t('期間の固定')}</MenuItem>
      <Divider />
      {menuListFixedLimit.map((el) => (
        <CheckableMenuItem
          label={el[0]}
          value={el[1]}
          selected={fixedLimit === el[1]}
          onSelect={handleSelectFixedLimit}
          disabled={el[1] === 'hour' && chartMode === 'candlestick'}
        />
      ))}
    </MenuList>
  )
}

export default function SettingButton() {
  const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null)
  const [bar, setBar] = useState(defaultBar)
  const [chartMode, setChartMode] = useState(defaultChart)
  const [fixedLimit, setFixedLimit] = useState<urlParamsValue<'limit'> | ''>(getStoregeFixedLimitSetting() ?? '')

  const open = Boolean(anchorEl)
  const handleClick = (event: MouseEvent) => {
    setAnchorEl(event.target as HTMLElement)
  }

  const handleClose = () => {
    setAnchorEl(null)
  }

  const handleSelectBar = (bar: ToggleChart) => {
    setStoregeBarSetting(bar)
    setBar(bar)
    handleClose()
  }

  const handleSelectChartMode = (mode: urlParamsValue<'chart'>) => {
    setStoregeChartSetting(mode)
    setChartMode(mode)
    if (mode === 'candlestick' && fixedLimit === 'hour') {
      setStoregeFixedLimitSetting('')
      setFixedLimit('')
    }
    handleClose()
  }

  const handleSelectFixedLimit = (limit: urlParamsValue<'limit'> | '') => {
    setStoregeFixedLimitSetting(limit)
    setFixedLimit(limit)
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
        aria-label={t('設定')}
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
        <DenseMenu
          bar={bar}
          handleSelectBar={handleSelectBar}
          chartMode={chartMode}
          handleSelectChartMode={handleSelectChartMode}
          fixedLimit={fixedLimit}
          handleSelectFixedLimit={handleSelectFixedLimit}
        />
      </Menu>
    </div>
  )
}
