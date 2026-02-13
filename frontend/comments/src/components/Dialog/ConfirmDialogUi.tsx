import * as React from 'react'
import Button from '@mui/material/Button'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import { Box, Typography } from '@mui/material'
import GradientCircularProgress from '../GradientCircularProgress'

export function DialogParagraph({ children, fontSize = '12.5px' }: { children: React.ReactNode; fontSize?: string }) {
  return (
    <Typography color="text.secondary" variant="body2" sx={{ fontSize }}>
      {children}
    </Typography>
  )
}

export function DialogSubTitle({ children }: { children: React.ReactNode }) {
  return (
    <Typography color="error" variant="subtitle2" component="span" display="block" sx={{ fontSize: '13px' }}>
      {children}
    </Typography>
  )
}

export default function ConfirmDialogUi({
  title,
  cancelText,
  okText,
  open,
  isLoading = false,
  handleClose,
  handleOk,
  children,
}: {
  title: string
  cancelText?: string
  okText?: string
  open: boolean
  isLoading?: boolean
  handleClose?: () => void
  handleOk?: () => void
  children: React.ReactNode
}) {
  const descriptionElementRef = React.useRef<HTMLElement>(null)

  React.useEffect(() => {
    if (open) {
      const { current: descriptionElement } = descriptionElementRef
      if (descriptionElement !== null) {
        descriptionElement.focus()
      }
    }
  }, [open])

  return (
    <>
      <Dialog
        open={open}
        onClose={handleClose}
        scroll={'paper'}
        aria-labelledby="scroll-dialog-title"
        aria-describedby="scroll-dialog-description"
      >
        <DialogTitle id="scroll-dialog-title">{title}</DialogTitle>
        <DialogContent dividers>
          <Box
            id="scroll-dialog-description"
            ref={descriptionElementRef}
            tabIndex={-1}
            display="flex"
            flexDirection="column"
            gap="0.5rem"
          >
            {children}
          </Box>
        </DialogContent>
        <DialogActions sx={{ overflow: 'hidden' }}>
          {cancelText && <Button onClick={handleClose}>{cancelText}</Button>}
          {okText && <Button onClick={handleOk}>{okText}</Button>}
          {isLoading && <GradientCircularProgress margin="0" />}
        </DialogActions>
      </Dialog>
    </>
  )
}
