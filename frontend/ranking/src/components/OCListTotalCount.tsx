import React from 'react'
import { Typography } from '@mui/material'
import { OPEN_CHAT_CATEGORY } from '../config/config'
import ChevronRightIcon from '@mui/icons-material/ChevronRight'
import { sprintfT } from '../config/translation'

export default function OCListTotalCount({
  totalCount,
  cateIndex,
  subCategory,
  keyword,
}: {
  totalCount: number | undefined
  cateIndex: number
  subCategory: string
  keyword: string
}) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', marginBottom: '-7px' }}>
      <Typography sx={{ fontSize: 13 }}>{OPEN_CHAT_CATEGORY[cateIndex][0]}</Typography>

      {subCategory && (
        <>
          <ChevronRightIcon sx={{ fontSize: '17px' }} />
          <Typography sx={{ fontSize: 13, display: 'flex', alignItems: 'center' }}>
            {subCategory}
          </Typography>
        </>
      )}
      {keyword && (
        <>
          <ChevronRightIcon sx={{ fontSize: '17px' }} />
          <Typography sx={{ fontSize: 13, display: 'flex', alignItems: 'center' }}>
            {sprintfT('「%s」の検索結果', keyword)}
          </Typography>
        </>
      )}
      {totalCount !== undefined && (
        <Typography sx={{ fontSize: 13, display: 'flex', alignItems: 'center' }}>
          <ChevronRightIcon sx={{ fontSize: '17px' }} />
          {sprintfT('%s 件', totalCount.toLocaleString())}
        </Typography>
      )}
    </div>
  )
}
