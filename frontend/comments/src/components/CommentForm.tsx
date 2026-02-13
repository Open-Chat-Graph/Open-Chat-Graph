import { FormEventHandler, useCallback, useRef, useState } from 'react'
import CommentFormUi from './CommentFormUi'
import { fetchApi } from '../utils/utils'
import useSetPostedItem from '../hooks/useSetPostedItem'
import { useGoogleReCaptcha } from 'react-google-recaptcha-v3'
import CommentFormDialogUi from './Dialog/CommentFormDialogUi'
import { useSetRecoilState } from 'recoil'
import { inputTextState } from '../state/inputTextState'
import { inputNameState } from '../state/inputNameState'
import { appInitTagDto } from '../config/appInitTagDto'
import { reportDialogState } from '../state/reportDialogState'

export default function CommentForm() {
  const [dialogOpen, setDialogOpen] = useState(false)
  const [isSending, setIsSending] = useState(false)
  const setName = useSetRecoilState(inputNameState)
  const setText = useSetRecoilState(inputTextState)
  const formRef = useRef<FormData | undefined>()
  const setPostedItem = useSetPostedItem()
  const setFailDialog = useSetRecoilState(reportDialogState)
  const { executeRecaptcha } = useGoogleReCaptcha()

  const onSubmit: FormEventHandler<HTMLFormElement> = useCallback((e) => {
    e.preventDefault()
    formRef.current = new FormData(e.currentTarget)
    setDialogOpen(true)
  }, [])

  const handleOk = useCallback(() => {
    if (!executeRecaptcha || !formRef.current) {
      console.error('args is undefined')
      return
    }

    setDialogOpen(false)
    setIsSending(true)
    const name = formRef.current.get('name') as string
    const text = formRef.current.get('text') as string
    formRef.current = undefined
    ;(async () => {
      try {
        const token = await executeRecaptcha('comment')
        const { commentId, userId } = await fetchApi<{ commentId: number; userId: string }>(
          `${appInitTagDto.baseUrl}/comment/${appInitTagDto.openChatId}`,
          'POST',
          {
            name,
            text,
            token,
          }
        )

        setPostedItem(commentId, name, text, userId)
        setName('')
        setText('')
      } catch {
        setFailDialog((p) => ({ ...p, open: true, result: 'fail' }))
      } finally {
        setIsSending(false)
      }
    })()
  }, [executeRecaptcha, setFailDialog, setName, setPostedItem, setText])

  const hadleDialogClose = useCallback(() => {
    formRef.current = undefined
    setDialogOpen(false)
  }, [])

  return (
    <>
      <CommentFormUi onSubmit={onSubmit} isSending={isSending} />
      <CommentFormDialogUi open={dialogOpen} handleOk={handleOk} handleClose={hadleDialogClose} />
    </>
  )
}
