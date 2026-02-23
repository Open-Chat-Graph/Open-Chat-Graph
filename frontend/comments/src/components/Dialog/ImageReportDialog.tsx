import { useAtom } from 'jotai'
import { imageReportDialogState } from '../../state/imageReportDialogState'
import { useCallback, useRef, useState } from 'react'
import { useGoogleReCaptcha } from 'react-google-recaptcha-v3'
import { fetchApi } from '../../utils/utils'
import ConfirmDialogUi, { DialogParagraph } from './ConfirmDialogUi'

export default function ImageReportDialog() {
  const [state, setState] = useAtom(imageReportDialogState)
  const [isLoading, setIsLoading] = useState(false)
  const { executeRecaptcha } = useGoogleReCaptcha()
  const imageId = useRef(0)

  imageId.current = state.imageId

  const handleOk = useCallback(async () => {
    if (!executeRecaptcha) return

    setIsLoading(true)
    try {
      const token = await executeRecaptcha('image_report')
      await fetchApi(
        `${window.location.origin}/comment_image_report/${imageId.current}`,
        'POST',
        { token }
      )
      setState((p) => ({ ...p, result: 'done' }))
    } catch {
      setState((p) => ({ ...p, result: 'fail' }))
    } finally {
      setIsLoading(false)
    }
  }, [executeRecaptcha, setState])

  const handleClose = useCallback(() => {
    setState((p) => ({ ...p, open: false }))
  }, [setState])

  const title =
    state.result !== 'fail'
      ? `コメントNo.${state.commentNo} の画像を通報`
      : 'エラー'

  return (
    <ConfirmDialogUi
      open={state.open}
      handleOk={handleOk}
      title={title}
      cancelText={isLoading ? undefined : state.result !== 'unsent' ? '閉じる' : 'キャンセル'}
      okText={isLoading || state.result !== 'unsent' ? undefined : '通報する'}
      isLoading={isLoading}
      handleClose={isLoading ? undefined : handleClose}
      sx={{ zIndex: 10000 }}
    >
      {state.result === 'unsent' && (
        <DialogParagraph>削除すべき不適切な画像として通報しますか？</DialogParagraph>
      )}
      {state.result === 'done' && <DialogParagraph>通報しました</DialogParagraph>}
      {state.result === 'fail' && (
        <DialogParagraph>
          サーバーとの通信に失敗しました。連続アクセスを防止しているため、しばらく待ってから再度お試しください。
        </DialogParagraph>
      )}
    </ConfirmDialogUi>
  )
}
