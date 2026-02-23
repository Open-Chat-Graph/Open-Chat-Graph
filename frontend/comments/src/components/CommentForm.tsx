import { FormEventHandler, useCallback, useRef, useState } from 'react'
import CommentFormUi from './CommentFormUi'
import { fetchApiFormData } from '../utils/utils'
import useSetPostedItem from '../hooks/useSetPostedItem'
import { useGoogleReCaptcha } from 'react-google-recaptcha-v3'
import CommentFormDialogUi from './Dialog/CommentFormDialogUi'
import { useRecoilState, useSetRecoilState } from 'recoil'
import { inputTextState } from '../state/inputTextState'
import { inputNameState } from '../state/inputNameState'
import { appInitTagDto } from '../config/appInitTagDto'
import { reportDialogState } from '../state/reportDialogState'
import { imageFilesState } from '../state/imageFilesState'
import imageCompression from 'browser-image-compression'

const MAX_SERVER_SIZE = 8 * 1024 * 1024 // 8MB

async function compressImage(file: File): Promise<File> {
  const compressed = await imageCompression(file, {
    maxSizeMB: 5,
    maxWidthOrHeight: 2000,
    useWebWorker: true,
    initialQuality: 0.85,
  })

  if (compressed.size > MAX_SERVER_SIZE) {
    throw new Error('画像サイズを小さくしてください（8MB以下）')
  }

  return compressed
}

export default function CommentForm() {
  const [dialogOpen, setDialogOpen] = useState(false)
  const [isSending, setIsSending] = useState(false)
  const setName = useSetRecoilState(inputNameState)
  const setText = useSetRecoilState(inputTextState)
  const [imageFiles, setImageFiles] = useRecoilState(imageFilesState)
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
    const currentImages = [...imageFiles]
    formRef.current = undefined
    ;(async () => {
      try {
        const token = await executeRecaptcha('comment')

        // Compress images
        const compressedImages: File[] = []
        for (const file of currentImages) {
          compressedImages.push(await compressImage(file))
        }

        // Build FormData
        const formData = new FormData()
        formData.append('name', name)
        formData.append('text', text)
        formData.append('token', token)
        compressedImages.forEach((file, i) => {
          formData.append(`image${i}`, file)
        })

        const { commentId, userId, userIdHash, uaHash, ipHash, images, imageError } = await fetchApiFormData<{
          commentId: number
          userId: string
          userIdHash: string
          uaHash: string
          ipHash: string
          images: string[]
          imageError?: boolean
        }>(
          `${window.location.origin}/comment/${appInitTagDto.openChatId}`,
          formData
        )

        setPostedItem(commentId, name, text, userId, userIdHash, uaHash, ipHash, images.map(f => ({ id: 0, filename: f })))
        setName('')
        setText('')
        setImageFiles([])

        if (imageError) {
          alert('コメントは投稿されましたが、画像の処理に失敗しました。')
        }
      } catch (error) {
        console.error(error)
        setFailDialog((p) => ({ ...p, open: true, result: 'fail' }))
      } finally {
        setIsSending(false)
      }
    })()
  }, [executeRecaptcha, imageFiles, setFailDialog, setImageFiles, setName, setPostedItem, setText])

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
