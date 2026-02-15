import { Box, Chip, Tab, Tabs } from '@mui/material'
import CandlestickChartIcon from '@mui/icons-material/CandlestickChart'
import ShowChartIcon from '@mui/icons-material/ShowChart'
import { signal } from '@preact/signals'
import { chartModeSignal, handleChangeChartMode, handleChangeLimit, hasOhlcData, limitSignal } from '../signal/chartState'
import { t } from '../util/translation'

export const toggleDisplay24h = signal(true)
export const toggleDisplayMonth = signal(true)
export const toggleDisplayAll = signal(true)

function CandlestickToggle() {
  if (!hasOhlcData()) return null

  const isCandlestick = chartModeSignal.value === 'candlestick'
  const isHourMode = limitSignal.value === 25
  const disabled = isHourMode && !isCandlestick

  const handleToggle = () => {
    if (disabled) return
    handleChangeChartMode(isCandlestick ? 'line' : 'candlestick')
  }

  return (
    <Chip
      className={`openchat-item-header-chip graph chart-mode-toggle ${isCandlestick ? 'selected' : ''}`}
      icon={isCandlestick ? <ShowChartIcon /> : <CandlestickChartIcon />}
      label={isCandlestick ? t('折れ線グラフ') : t('ローソク足')}
      onClick={handleToggle}
      size='small'
      sx={{
        opacity: disabled ? 0.4 : 1,
        cursor: disabled ? 'default' : 'pointer',
        '& .MuiChip-icon': {
          color: 'inherit',
        },
      }}
      aria-label={isCandlestick ? t('折れ線グラフに切り替え') : t('ローソク足に切り替え')}
    />
  )
}

export default function ChartLimitBtns() {
  const handleChange = (e: MouseEvent, limit: ChartLimit | 25) => {
    e.preventDefault()
    handleChangeLimit(limit)
  }

  return (
    <>
      <Box
        sx={{ borderBottom: 1, borderColor: '#efefef', width: '100%' }}
        className='limit-btns category-tab'
      >
        <Tabs onChange={handleChange} variant='fullWidth' value={limitSignal.value}>
          {toggleDisplay24h.value && chartModeSignal.value !== 'candlestick' && <Tab value={25} label={t('最新24時間')} />}
          <Tab value={8} label={t('1週間')} />
          {toggleDisplayMonth.value && <Tab value={31} label={t('1ヶ月')} />}
          {toggleDisplayAll.value && <Tab value={0} label={t('全期間')} />}
        </Tabs>
      </Box>
      {hasOhlcData() && (
        <Box sx={{ display: 'flex', justifyContent: 'flex-end', pt: '1rem' }}>
          <CandlestickToggle />
        </Box>
      )}
    </>
  )
}
