import { FormEventHandler, useCallback, useRef, useState } from 'react'
import CommentFormUi from './CommentFormUi'
import { fetchApiFormData, ApiError } from '../utils/utils'
import useSetPostedItem from '../hooks/useSetPostedItem'
import { useGoogleReCaptcha } from 'react-google-recaptcha-v3'
import CommentFormDialogUi from './Dialog/CommentFormDialogUi'
import ErrorDialog from './Dialog/ErrorDialog'
import { useRecoilState, useSetRecoilState } from 'recoil'
import { inputTextState } from '../state/inputTextState'
import { inputNameState } from '../state/inputNameState'
import { appInitTagDto } from '../config/appInitTagDto'
import { errorDialogState } from '../state/errorDialogState'
import { imageFilesState } from '../state/imageFilesState'

export default function CommentForm() {
  const [dialogOpen, setDialogOpen] = useState(false)
  const [isSending, setIsSending] = useState(false)
  const setName = useSetRecoilState(inputNameState)
  const setText = useSetRecoilState(inputTextState)
  const [imageFiles, setImageFiles] = useRecoilState(imageFilesState)
  const formRef = useRef<FormData | undefined>()
  const setPostedItem = useSetPostedItem()
  const setErrorDialog = useSetRecoilState(errorDialogState)
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
    const currentImages = [...imageFiles]
    formRef.current = undefined
    ;(async () => {
      try {
        const token = await executeRecaptcha('comment')

        // Build FormData
        const formData = new FormData()
        formData.append('name', name)
        formData.append('text', text)
        formData.append('token', token)
        currentImages.forEach((blob, i) => {
          formData.append(`image${i}`, blob, `image${i}.jpg`)
        })

        const { commentId, userId, userIdHash, uaHash, ipHash, images } = await fetchApiFormData<{
          commentId: number
          userId: string
          userIdHash: string
          uaHash: string
          ipHash: string
          images: string[]
        }>(
          `${window.location.origin}/comment/${appInitTagDto.openChatId}`,
          formData
        )

        setPostedItem(commentId, name, text, userId, userIdHash, uaHash, ipHash, images.map(f => ({ id: 0, filename: f })))
        setName('')
        setText('')
        setImageFiles([])
      } catch (error) {
        console.error(error)
        let message: string
        let detail: string
        if (error instanceof ApiError) {
          message = error.message
          detail = `[サーバーエラー] HTTP ${error.status}\ncode: ${error.serverCode}\nurl: ${error.url}`
          if (error.responseBody) {
            detail += `\n\n--- レスポンス ---\n${error.responseBody}`
          }
        } else if (error instanceof Error) {
          message = error.message
          detail = `[${error.name}]\n${error.stack ?? ''}`
        } else {
          message = 'エラーが発生しました'
          detail = String(error)
        }
        setErrorDialog({ open: true, message, detail })
      } finally {
        setIsSending(false)
      }
    })()
  }, [executeRecaptcha, imageFiles, setErrorDialog, setImageFiles, setName, setPostedItem, setText])

  const hadleDialogClose = useCallback(() => {
    formRef.current = undefined
    setDialogOpen(false)
  }, [])

  return (
    <>
      <CommentFormUi onSubmit={onSubmit} isSending={isSending} />
      <CommentFormDialogUi open={dialogOpen} handleOk={handleOk} handleClose={hadleDialogClose} />
      <ErrorDialog />
    </>
  )
}
