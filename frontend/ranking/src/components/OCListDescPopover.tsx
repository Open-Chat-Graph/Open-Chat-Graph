import * as React from 'react'
import Popover from '@mui/material/Popover'
import HelpOutlineIcon from '@mui/icons-material/HelpOutline'
import { Box, IconButton, useMediaQuery } from '@mui/material'

export function HelpIcon() {
  const matches = useMediaQuery('(min-width:600px)') // 599px以下で false
  return <HelpOutlineIcon sx={{ color: 'rgba(0, 0, 0, 0.12)', fontSize: matches ? '24px' : '22px' }} />
}

export default function OCListDescPopover({ children }: { children: React.ReactNode }) {
  const [anchorEl, setAnchorEl] = React.useState<HTMLElement | null>(null)

  const handlePopoverOpen = (event: React.MouseEvent<HTMLElement>) => {
    setAnchorEl(event.currentTarget)
  }

  const handlePopoverClose = () => {
    setAnchorEl(null)
  }

  const open = Boolean(anchorEl)

  return (
    <Box sx={{ width: 'fit-content' }}>
      <IconButton
        aria-owns={open ? 'mouse-over-popover' : undefined}
        aria-haspopup="true"
        onClick={handlePopoverOpen}
        sx={{ p: 0, minWidth: 0 }}
      >
        <HelpIcon />
      </IconButton>
      <Popover
        id="mouse-over-popover"
        open={open}
        anchorEl={anchorEl}
        anchorOrigin={{
          vertical: 'bottom',
          horizontal: 'left',
        }}
        transformOrigin={{
          vertical: 'top',
          horizontal: 'center',
        }}
        onClose={handlePopoverClose}
      >
        <Box sx={{ display: 'flex', flexDirection: 'column', p: '1.25rem' }}>{children}</Box>
      </Popover>
    </Box>
  )
}
