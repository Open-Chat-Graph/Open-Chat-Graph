import { memo, useCallback } from 'react'
import ReportButtonUi from './ReportButtonUi'
import { useSetRecoilState } from 'recoil'
import { reportDialogState } from '../../state/reportDialogState'

export default memo(function ReportButton({ id, commentId }: { id: number; commentId: number }) {
  const setDialog = useSetRecoilState(reportDialogState)

  const onClick = useCallback(() => {
    setDialog({ id, commentId, open: true, result: 'unsent' })
  }, [commentId, id, setDialog])

  return <ReportButtonUi onClick={onClick} />
})
