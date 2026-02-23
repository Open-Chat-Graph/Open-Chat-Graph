import { useRecoilState } from 'recoil'
import { useCallback } from 'react'
import { errorDialogState } from '../../state/errorDialogState'
import ConfirmDialogUi, { DialogParagraph } from './ConfirmDialogUi'
import { Box } from '@mui/material'

export default function ErrorDialog() {
  const [state, setState] = useRecoilState(errorDialogState)

  const handleClose = useCallback(() => {
    setState({ open: false, message: '', detail: '' })
  }, [setState])

  return (
    <ConfirmDialogUi
      open={state.open}
      title="エラー"
      cancelText="閉じる"
      handleClose={handleClose}
    >
      <DialogParagraph>{state.message}</DialogParagraph>
      {state.detail && (
        <Box
          component="pre"
          sx={{
            mt: 1,
            p: 1,
            bgcolor: 'grey.100',
            borderRadius: 1,
            fontSize: '11px',
            color: 'text.secondary',
            whiteSpace: 'pre-wrap',
            wordBreak: 'break-all',
            maxHeight: '60vh',
            overflow: 'auto',
            fontFamily: 'monospace',
          }}
        >
          {state.detail}
        </Box>
      )}
    </ConfirmDialogUi>
  )
}
