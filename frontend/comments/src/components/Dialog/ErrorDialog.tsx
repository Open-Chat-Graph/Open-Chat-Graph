import { useRecoilState } from 'recoil'
import { useCallback } from 'react'
import { errorDialogState } from '../../state/errorDialogState'
import ConfirmDialogUi, { DialogParagraph } from './ConfirmDialogUi'

export default function ErrorDialog() {
  const [state, setState] = useRecoilState(errorDialogState)

  const handleClose = useCallback(() => {
    setState({ open: false, message: '' })
  }, [setState])

  return (
    <ConfirmDialogUi
      open={state.open}
      title="エラー"
      cancelText="閉じる"
      handleClose={handleClose}
    >
      <DialogParagraph>{state.message}</DialogParagraph>
    </ConfirmDialogUi>
  )
}
