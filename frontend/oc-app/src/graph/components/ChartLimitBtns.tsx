import { Box, Chip, Tab, Tabs } from '@mui/material'
import CandlestickChartIcon from '@mui/icons-material/CandlestickChart'
import ShowChartIcon from '@mui/icons-material/ShowChart'
import { useAtomValue } from 'jotai'
import {
  chartModeAtom,
  handleChangeChartMode,
  handleChangeLimit,
  hasOhlcData,
  hasOhlcDataForLimit,
  limitAtom,
  toggleDisplay24hAtom,
  toggleDisplayAllAtom,
  toggleDisplayMonthAtom,
  toggleDisplayWeekAtom,
} from '../state/chartState'
import { t } from '../util/translation'

function CandlestickToggle() {
  const chartMode = useAtomValue(chartModeAtom)
  const limit = useAtomValue(limitAtom)
  if (!hasOhlcData()) return null
  const isCandlestick = chartMode === 'candlestick'
  // 表示中の期間タブにOHLCデータが無い場合はグレーアウト
  const disabled = !isCandlestick && !hasOhlcDataForLimit(limit)

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
      size="small"
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
  const limit = useAtomValue(limitAtom)
  const chartMode = useAtomValue(chartModeAtom)
  const display24h = useAtomValue(toggleDisplay24hAtom)
  const displayWeek = useAtomValue(toggleDisplayWeekAtom)
  const displayMonth = useAtomValue(toggleDisplayMonthAtom)
  const displayAll = useAtomValue(toggleDisplayAllAtom)

  const handleChange = (_e: React.SyntheticEvent, newLimit: ChartLimit | 25) => {
    handleChangeLimit(newLimit)
  }

  return (
    <>
      <Box
        sx={{ borderBottom: 1, borderColor: 'var(--c-border)', width: '100%' }}
        className="limit-btns category-tab"
      >
        <Tabs onChange={handleChange} variant="fullWidth" value={limit}>
          {display24h && chartMode !== 'candlestick' && (
            <Tab value={25} label={t('最新24時間')} />
          )}
          {displayWeek && <Tab value={8} label={t('1週間')} />}
          {displayMonth && <Tab value={31} label={t('1ヶ月')} />}
          {displayAll && <Tab value={0} label={t('全期間')} />}
        </Tabs>
      </Box>
      {/* 最新24時間タブ(limit=25)はローソク足非対応なので切替ボタンごと非表示 */}
      {hasOhlcData() && limit !== 25 && (
        <Box sx={{ display: 'flex', justifyContent: 'flex-end', pt: '1rem' }}>
          <CandlestickToggle />
        </Box>
      )}
    </>
  )
}
