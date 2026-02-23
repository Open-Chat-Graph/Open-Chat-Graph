import { memo, useCallback } from 'react'
import { Button } from '@mui/material'
import { useSetAtom } from 'jotai'
import { imageReportDialogState } from '../../state/imageReportDialogState'

export default memo(function ImageReportButton({ imageId, commentNo }: { imageId: number; commentNo: number }) {
  const setDialog = useSetAtom(imageReportDialogState)

  const onClick = useCallback(() => {
    setDialog({ imageId, commentNo, open: true, result: 'unsent' })
  }, [imageId, commentNo, setDialog])

  return (
    <Button
      variant="text"
      onClick={onClick}
      sx={{
        color: 'rgba(255,255,255,0.7)',
        fontSize: '14px',
        fontWeight: 500,
        padding: '6px 12px',
        borderRadius: '4px',
        backgroundColor: 'rgba(255,255,255,0.15)',
        '&:hover': { backgroundColor: 'rgba(255,255,255,0.25)', color: '#fff' },
        minWidth: 0,
      }}
    >
      通報
    </Button>
  )
})
