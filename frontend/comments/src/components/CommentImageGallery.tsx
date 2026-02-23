import { useState, useCallback, useEffect, useRef, useMemo } from 'react'
import { Box } from '@mui/material'
import Lightbox from 'yet-another-react-lightbox'
import Zoom from 'yet-another-react-lightbox/plugins/zoom'
import 'yet-another-react-lightbox/styles.css'
import './lightbox-overrides.css'
import { appInitTagDto } from '../config/appInitTagDto'
import ImageReportButton from './Button/ImageReportButton'

function getSubDir(filename: string) {
  return filename.substring(0, 2)
}

function getThumbUrl(filename: string) {
  return `${window.location.origin}/comment-img/thumb/${filename}`
}

function getFullUrl(filename: string) {
  return `${window.location.origin}/comment-img/${getSubDir(filename)}/${filename}`
}

function buildAlt(posterName: string, commentNo: number, index: number, total: number) {
  const room = appInitTagDto.openChatName ?? ''
  const num = total > 1 ? `(${index + 1}/${total})` : ''
  return `${room} コメントNo.${commentNo}の画像${num} - ${posterName}`
}

export default function CommentImageGallery({ images, posterName, commentNo }: { images: CommentImage[]; posterName: string; commentNo: number }) {
  const [lightboxIndex, setLightboxIndex] = useState(-1)
  const [viewIndex, setViewIndex] = useState(-1)
  const isOpenRef = useRef(false)
  const closingByPopstate = useRef(false)
  const pushedState = useRef(false)
  const imagesRef = useRef(images)
  imagesRef.current = images
  const isPC = useMemo(() => window.matchMedia('(hover: hover) and (pointer: fine)').matches, [])

  const filenames = useMemo(() => images.map(img => img.filename), [images])

  // URL直アクセス: マウント時にハッシュから画像を開く
  useEffect(() => {
    const match = location.hash.match(/^#comment-img=(.+)$/)
    if (match) {
      const idx = filenames.indexOf(match[1])
      if (idx >= 0) {
        isOpenRef.current = true
        setLightboxIndex(idx)
      }
    }
  }, [filenames])

  // popstateリスナー: ブラウザバック/フォワードでLightboxを開閉する
  useEffect(() => {
    const onPopState = () => {
      const match = location.hash.match(/^#comment-img=(.+)$/)
      if (match) {
        // フォワード: ハッシュが復帰 → 該当画像のLightboxを開く
        const fns = imagesRef.current.map(img => img.filename)
        const idx = fns.indexOf(match[1])
        if (idx >= 0) {
          closingByPopstate.current = false
          isOpenRef.current = true
          pushedState.current = true
          setLightboxIndex(idx)
        }
      } else if (isOpenRef.current) {
        // バック: ハッシュが消えた → Lightboxを閉じる
        closingByPopstate.current = true
        isOpenRef.current = false
        setLightboxIndex(-1)
      }
    }
    window.addEventListener('popstate', onPopState)
    return () => window.removeEventListener('popstate', onPopState)
  }, [])

  const handleOpen = useCallback((index: number) => {
    closingByPopstate.current = false
    isOpenRef.current = true
    pushedState.current = true
    setLightboxIndex(index)
    setViewIndex(index)
    history.pushState(null, '', `#comment-img=${filenames[index]}`)
  }, [filenames])

  const handleClose = useCallback(() => {
    isOpenRef.current = false
    setLightboxIndex(-1)
    setViewIndex(-1)
    if (closingByPopstate.current) {
      closingByPopstate.current = false
    } else if (pushedState.current) {
      pushedState.current = false
      history.back()
    } else {
      history.replaceState(null, '', location.pathname + location.search)
    }
  }, [])

  const handleView = useCallback(({ index }: { index: number }) => {
    setViewIndex(index)
  }, [])

  if (!images.length) return null

  const currentImageId = viewIndex >= 0 ? images[viewIndex]?.id ?? 0 : 0

  return (
    <>
      <Box sx={{ display: 'flex', gap: 0.5, flexWrap: 'wrap', mb: '1rem' }}>
        {images.map((img, index) => (
          <Box
            key={img.filename}
            component="img"
            src={getThumbUrl(img.filename)}
            alt={buildAlt(posterName, commentNo, index, images.length)}
            loading="lazy"
            onClick={() => handleOpen(index)}
            sx={{
              width: { xs: 72, sm: 96 },
              height: { xs: 72, sm: 96 },
              objectFit: 'cover',
              borderRadius: 1,
              cursor: 'pointer',
              border: '1px solid #e0e0e0',
              '&:hover': { opacity: 0.85 },
            }}
          />
        ))}
      </Box>
      <Lightbox
        open={lightboxIndex >= 0}
        close={handleClose}
        index={lightboxIndex}
        slides={images.map((img, i) => ({ src: getFullUrl(img.filename), alt: buildAlt(posterName, commentNo, i, images.length) }))}
        on={{ view: handleView }}
        carousel={{ finite: true }}
        plugins={[Zoom]}
        zoom={{
          maxZoomPixelRatio: 3,
          scrollToZoom: true,
        }}
        styles={{
          toolbar: { left: 0, right: 'auto' },
        }}
        toolbar={{
          buttons: ['close'],
        }}
        render={{
          buttonZoom: () => null,
          ...(images.length <= 1 && {
            buttonPrev: () => null,
            buttonNext: () => null,
          }),
          slideFooter: () =>
            currentImageId > 0 ? (
              <div style={{ position: 'absolute', bottom: 16, left: 16, zIndex: 1 }}>
                <ImageReportButton imageId={currentImageId} commentNo={commentNo} />
              </div>
            ) : null,
        }}
        controller={{
          closeOnBackdropClick: isPC,
        }}
      />
    </>
  )
}
