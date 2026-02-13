import React, { memo } from 'react'
import { Chip, Toolbar } from '@mui/material'
import { useSetListParams } from '../hooks/ListParamsHooks'
import SearchIcon from '@mui/icons-material/Search'

function Chips({ keyword }: KeywordChipProps) {
  const setParams = useSetListParams()

  const handleDelete = () => {
    setParams((params) => ({ ...params, keyword: '' }))
  }

  return (
    <Chip
      icon={<SearchIcon sx={{ fontSize: '18px' }} />}
      color="primary"
      className="openchat-item-header-chip category selected"
      label={keyword}
      onDelete={handleDelete}
    />
  )
}

const KeywordChip = memo(function KeywordChip(props: KeywordChipProps) {
  return (
    <div style={{ width: '100%' }}>
      <Toolbar style={{ minHeight: 48 }}>
        <Chips {...props} />
      </Toolbar>
    </div>
  )
})

export default KeywordChip
