import { memo, useCallback } from 'react'
import ReportButtonUi from './ReportButtonUi'
import { useSetAtom } from 'jotai'
import { reportDialogState } from '../../state/reportDialogState'

export default memo(function ReportButton({ id, commentId }: { id: number; commentId: number }) {
  const setDialog = useSetAtom(reportDialogState)

  const onClick = useCallback(() => {
    setDialog({ id, commentId, open: true, result: 'unsent' })
  }, [commentId, id, setDialog])

  return <ReportButtonUi onClick={onClick} />
})
