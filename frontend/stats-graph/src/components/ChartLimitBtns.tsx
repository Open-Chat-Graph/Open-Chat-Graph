import { Box, Tab, Tabs } from '@mui/material'
import { signal } from '@preact/signals'
import { handleChangeLimit, limitSignal } from '../signal/chartState'
import { t } from '../util/translation'

export const toggleDisplay24h = signal(true)
export const toggleDisplayMonth = signal(true)
export const toggleDisplayAll = signal(true)

export default function ChartLimitBtns() {
  const handleChange = (e: MouseEvent, limit: ChartLimit | 25) => {
    e.preventDefault()
    handleChangeLimit(limit)
  }

  return (
    <Box
      sx={{ borderBottom: 1, borderColor: '#efefef', width: '100%' }}
      className='limit-btns category-tab'
    >
      <Tabs onChange={handleChange} variant='fullWidth' value={limitSignal.value}>
        {toggleDisplay24h.value && <Tab value={25} label={t('最新24時間')} />}
        <Tab value={8} label={t('1週間')} />
        {toggleDisplayMonth.value && <Tab value={31} label={t('1ヶ月')} />}
        {toggleDisplayAll.value && <Tab value={0} label={t('全期間')} />}
      </Tabs>
    </Box>
  )
}
