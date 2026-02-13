import { useRecoilState } from 'recoil'
import { reportDialogState } from '../../state/reportDialogState'
import { useCallback, useRef, useState } from 'react'
import { useGoogleReCaptcha } from 'react-google-recaptcha-v3'
import { fetchApi } from '../../utils/utils'
import ReportDialogUi from './ReportDialogUi'
import { appInitTagDto } from '../../config/appInitTagDto'

export default function ReportDialog() {
  const [state, setState] = useRecoilState(reportDialogState)
  const [isLoading, setIsLoading] = useState(false)
  const { executeRecaptcha } = useGoogleReCaptcha()
  const commentId = useRef<number | undefined>()

  commentId.current = state.commentId

  const handleOk = useCallback(async () => {
    if (!executeRecaptcha) {
      console.error('executeRecaptcha is undefined')
      return
    }

    setIsLoading(true)
    ;(async () => {
      try {
        const token = await executeRecaptcha('report')
        const res = await fetchApi<{ sucsess: boolean }>(
          `${appInitTagDto.baseUrl}/comment_report/${commentId.current}`,
          'POST',
          { token }
        )

        console.log(res)
        setState((p) => ({ ...p, result: 'done' }))
      } catch {
        setState((p) => ({ ...p, result: 'fail' }))
      } finally {
        setIsLoading(false)
      }
    })()
  }, [executeRecaptcha, setState])

  const handleClose = useCallback(() => {
    setState((p) => ({ ...p, open: false }))
  }, [setState])

  return (
    <ReportDialogUi {...{ open: state.open, isLoading, result: state.result, handleClose, handleOk, id: state.id }} />
  )
}
