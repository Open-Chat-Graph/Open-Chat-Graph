import React, { memo } from 'react'
import { Chip, Stack } from '@mui/material'
import { useSetListParams } from '../hooks/ListParamsHooks'
import { t } from '../config/translation'

type ToggleButtons = [ListParams['list'], string][]

export const toggleButtons: ToggleButtons = [
  ['hourly', t('1時間')],
  ['daily', t('24時間')],
  ['weekly', t('1週間')],
  ['all', t('人数')],
]

export const officialButtons: ToggleButtons = [
  ['rising', '急上昇'],
  ['ranking', 'ランキング'],
]

const ListToggleChips = memo(function ListToggleButton({
  list,
  toggleButtons,
}: {
  list: ListParams['list']
  toggleButtons: ToggleButtons
}) {
  const setParams = useSetListParams()
  const handleChange = (newList: ListParams['list']) => {
    newList && setParams((params) => ({ ...params, list: newList, order: '', sort: '' }))
  }

  return (
    <Stack direction='row' spacing={1}>
      {toggleButtons.map((el, i) =>
        list === el[0] ? (
          <Chip
            key={i}
            label={el[1]}
            className='openchat-item-header-chip selected'
            sx={{ cursor: 'default' }}
          />
        ) : (
          <Chip
            key={i}
            label={el[1]}
            onClick={() => handleChange(el[0])}
            className='openchat-item-header-chip'
          />
        )
      )}
    </Stack>
  )
})

export default ListToggleChips
