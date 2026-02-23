import { useCallback, useRef, DragEvent, useState, useMemo, useEffect } from 'react'
import { useRecoilState, useSetRecoilState } from 'recoil'
import { imageFilesState } from '../state/imageFilesState'
import { errorDialogState } from '../state/errorDialogState'
import { Box, Button, Badge, CircularProgress } from '@mui/material'
import AddPhotoAlternateIcon from '@mui/icons-material/AddPhotoAlternate'
import CloseIcon from '@mui/icons-material/Close'

const MAX_IMAGES = 3
const MAX_FILE_SIZE = 20 * 1024 * 1024 // 20MB
const MAX_DIMENSION = 2000
const WEBP_QUALITY = 0.7

type PendingImage = { id: number; previewUrl: string }

let nextId = 0

function compressToWebp(file: File): Promise<Blob> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      URL.revokeObjectURL(url)
      let { width, height } = img
      if (width > MAX_DIMENSION || height > MAX_DIMENSION) {
        if (width > height) {
          height = Math.round((height * MAX_DIMENSION) / width)
          width = MAX_DIMENSION
        } else {
          width = Math.round((width * MAX_DIMENSION) / height)
          height = MAX_DIMENSION
        }
      }
      const canvas = document.createElement('canvas')
      canvas.width = width
      canvas.height = height
      const ctx = canvas.getContext('2d')!
      ctx.drawImage(img, 0, 0, width, height)
      canvas.toBlob(
        (blob) => (blob ? resolve(blob) : reject(new Error('画像の変換に失敗しました'))),
        'image/webp',
        WEBP_QUALITY
      )
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('画像の読み込みに失敗しました'))
    }
    img.src = url
  })
}

export default function ImageAttachmentInput() {
  const [files, setFiles] = useRecoilState(imageFilesState)
  const setErrorDialog = useSetRecoilState(errorDialogState)
  const inputRef = useRef<HTMLInputElement>(null)
  const [dragOver, setDragOver] = useState(false)
  const [pending, setPending] = useState<PendingImage[]>([])
  const pendingRef = useRef(pending)
  pendingRef.current = pending

  const previewUrls = useMemo(() => files.map((f) => URL.createObjectURL(f)), [files])

  useEffect(() => {
    return () => {
      previewUrls.forEach((url) => URL.revokeObjectURL(url))
    }
  }, [previewUrls])

  const totalCount = files.length + pending.length

  const startCompression = useCallback(
    (filesToCompress: File[]) => {
      for (const file of filesToCompress) {
        const id = nextId++
        const previewUrl = URL.createObjectURL(file)
        setPending((p) => [...p, { id, previewUrl }])
        ;(async () => {
          try {
            const blob = await compressToWebp(file)
            // Check if cancelled during compression
            if (!pendingRef.current.some((item) => item.id === id)) return
            URL.revokeObjectURL(previewUrl)
            setPending((p) => p.filter((item) => item.id !== id))
            setFiles((prev) => {
              if (prev.length >= MAX_IMAGES) return prev
              return [...prev, new File([blob], file.name.replace(/\.[^.]+$/, '.webp'), { type: 'image/webp' })]
            })
          } catch (e) {
            console.error(e)
            URL.revokeObjectURL(previewUrl)
            setPending((p) => p.filter((item) => item.id !== id))
            setErrorDialog({
              open: true,
              message: e instanceof Error ? e.message : '画像の圧縮に失敗しました',
            })
          }
        })()
      }
    },
    [setFiles, setErrorDialog]
  )

  const addFiles = useCallback(
    (newFiles: FileList | File[]) => {
      let hasOversized = false
      let hasInvalidType = false
      const arr = Array.from(newFiles).filter((f) => {
        if (!f.type.startsWith('image/')) {
          hasInvalidType = true
          return false
        }
        if (f.size > MAX_FILE_SIZE) {
          hasOversized = true
          return false
        }
        return true
      })
      if (hasOversized) {
        setErrorDialog({
          open: true,
          message: '画像のファイルサイズが大きすぎます（20MB以下にしてください）',
        })
      } else if (hasInvalidType) {
        setErrorDialog({
          open: true,
          message: '画像ファイルのみ添付できます',
        })
      }
      if (arr.length === 0) return

      const available = MAX_IMAGES - files.length - pendingRef.current.length
      const toAdd = arr.slice(0, Math.max(0, available))
      if (toAdd.length > 0) {
        startCompression(toAdd)
      }
    },
    [files.length, setErrorDialog, startCompression]
  )

  const handleFileChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      if (e.target.files) {
        addFiles(e.target.files)
      }
      e.target.value = ''
    },
    [addFiles]
  )

  const handleRemove = useCallback(
    (index: number) => {
      setFiles((prev) => prev.filter((_, i) => i !== index))
    },
    [setFiles]
  )

  const handleRemovePending = useCallback((id: number) => {
    setPending((prev) => {
      const item = prev.find((p) => p.id === id)
      if (item) URL.revokeObjectURL(item.previewUrl)
      return prev.filter((p) => p.id !== id)
    })
  }, [])

  const handleDrop = useCallback(
    (e: DragEvent) => {
      e.preventDefault()
      setDragOver(false)
      if (e.dataTransfer.files) {
        addFiles(e.dataTransfer.files)
      }
    },
    [addFiles]
  )

  const handleDragOver = useCallback((e: DragEvent) => {
    e.preventDefault()
    setDragOver(true)
  }, [])

  const handleDragLeave = useCallback(() => {
    setDragOver(false)
  }, [])

  const badgeSx = {
    '& .MuiBadge-badge': {
      bgcolor: 'rgba(0,0,0,0.6)',
      color: '#fff',
      width: 20,
      height: 20,
      minWidth: 20,
      borderRadius: '50%',
      cursor: 'pointer',
    },
  }

  return (
    <Box>
      <Box
        onDrop={handleDrop}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        sx={{
          display: 'flex',
          alignItems: 'center',
          gap: 1,
          flexWrap: 'wrap',
          p: totalCount > 0 ? 1 : 0,
          border: dragOver ? '2px dashed #1976d2' : totalCount > 0 ? '1px solid #e0e0e0' : 'none',
          borderRadius: 1,
          transition: 'border 0.2s',
        }}
      >
        {files.map((_, index) => (
          <Badge
            key={`file-${index}`}
            badgeContent={
              <CloseIcon sx={{ fontSize: 14, cursor: 'pointer' }} onClick={() => handleRemove(index)} />
            }
            color="default"
            overlap="circular"
            sx={badgeSx}
          >
            <Box
              component="img"
              src={previewUrls[index]}
              alt={`preview-${index}`}
              sx={{ width: 64, height: 64, objectFit: 'cover', borderRadius: 1 }}
            />
          </Badge>
        ))}

        {pending.map((item) => (
          <Badge
            key={`pending-${item.id}`}
            badgeContent={
              <CloseIcon sx={{ fontSize: 14, cursor: 'pointer' }} onClick={() => handleRemovePending(item.id)} />
            }
            color="default"
            overlap="circular"
            sx={badgeSx}
          >
            <Box sx={{ position: 'relative', width: 64, height: 64 }}>
              <Box
                component="img"
                src={item.previewUrl}
                alt="compressing"
                sx={{ width: 64, height: 64, objectFit: 'cover', borderRadius: 1, opacity: 0.4 }}
              />
              <CircularProgress
                size={24}
                sx={{
                  position: 'absolute',
                  top: '50%',
                  left: '50%',
                  marginTop: '-12px',
                  marginLeft: '-12px',
                }}
              />
            </Box>
          </Badge>
        ))}

        {totalCount < MAX_IMAGES && (
          <>
            <input
              ref={inputRef}
              type="file"
              accept="image/*"
              multiple
              onChange={handleFileChange}
              style={{ display: 'none' }}
            />
            <Button
              variant="outlined"
              size="small"
              onClick={() => inputRef.current?.click()}
              startIcon={<AddPhotoAlternateIcon />}
              sx={{
                color: 'text.secondary',
                borderColor: 'divider',
                textTransform: 'none',
                '&:hover': { borderColor: 'action.disabled', bgcolor: 'action.hover' },
                '&:focus-visible': { borderColor: 'action.disabled' },
                '&:active': { borderColor: 'action.disabled' },
              }}
            >
              画像を添付{totalCount === 0 && '（最大3枚）'}
            </Button>
          </>
        )}
      </Box>
    </Box>
  )
}
