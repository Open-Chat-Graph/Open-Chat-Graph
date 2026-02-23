import { useCallback, useRef, DragEvent, useState, useMemo, useEffect } from 'react'
import { useRecoilState } from 'recoil'
import { imageFilesState } from '../state/imageFilesState'
import { Box, Button, Badge } from '@mui/material'
import AddPhotoAlternateIcon from '@mui/icons-material/AddPhotoAlternate'
import CloseIcon from '@mui/icons-material/Close'

const MAX_IMAGES = 3
const MAX_FILE_SIZE = 15 * 1024 * 1024 // 15MB client-side limit

export default function ImageAttachmentInput() {
  const [files, setFiles] = useRecoilState(imageFilesState)
  const inputRef = useRef<HTMLInputElement>(null)
  const [dragOver, setDragOver] = useState(false)

  const previewUrls = useMemo(() => files.map((f) => URL.createObjectURL(f)), [files])

  useEffect(() => {
    return () => {
      previewUrls.forEach((url) => URL.revokeObjectURL(url))
    }
  }, [previewUrls])

  const addFiles = useCallback(
    (newFiles: FileList | File[]) => {
      const arr = Array.from(newFiles).filter((f) => {
        if (!f.type.startsWith('image/')) return false
        if (f.size > MAX_FILE_SIZE) return false
        return true
      })
      setFiles((prev) => [...prev, ...arr].slice(0, MAX_IMAGES))
    },
    [setFiles]
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
          p: files.length > 0 ? 1 : 0,
          border: dragOver ? '2px dashed #1976d2' : files.length > 0 ? '1px solid #e0e0e0' : 'none',
          borderRadius: 1,
          transition: 'border 0.2s',
        }}
      >
        {files.map((_, index) => (
          <Badge
            key={index}
            badgeContent={
              <CloseIcon
                sx={{ fontSize: 14, cursor: 'pointer' }}
                onClick={() => handleRemove(index)}
              />
            }
            color="default"
            overlap="circular"
            sx={{
              '& .MuiBadge-badge': {
                bgcolor: 'rgba(0,0,0,0.6)',
                color: '#fff',
                width: 20,
                height: 20,
                minWidth: 20,
                borderRadius: '50%',
                cursor: 'pointer',
              },
            }}
          >
            <Box
              component="img"
              src={previewUrls[index]}
              alt={`preview-${index}`}
              sx={{
                width: 64,
                height: 64,
                objectFit: 'cover',
                borderRadius: 1,
              }}
            />
          </Badge>
        ))}

        {files.length < MAX_IMAGES && (
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
              画像を添付{files.length === 0 && '（最大3枚）'}
            </Button>
          </>
        )}
      </Box>
    </Box>
  )
}
