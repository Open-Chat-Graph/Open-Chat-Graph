import { memo } from 'react'
import { X } from 'lucide-react'
import { Dialog, DialogContent } from '@/components/ui/dialog'
import { imgPreviewUrl, imgUrl } from '@/lib/imageUrl'

interface DetailHeaderProps {
  thumbnail: string | undefined
  name: string
  imageModalOpen: boolean
  onImageModalOpenChange: (open: boolean) => void
}

// 画像URLを生成（空文字なら undefined にして未表示にする）
const getThumbnailUrl = (localImgUrl: string | undefined) => imgUrl(localImgUrl) || undefined
const getThumbnailPreviewUrl = (localImgUrl: string | undefined) => imgPreviewUrl(localImgUrl) || undefined

type ProgressiveImageProps = {
  src: string
  previewSrc?: string
  alt: string
  className?: string
}

const ProgressiveImage = memo(({ src, previewSrc, alt, className }: ProgressiveImageProps) => {
  return (
    <div className="relative w-full h-full">
      {previewSrc && (
        <img
          src={previewSrc}
          alt={alt}
          className={`${className ?? ''} absolute inset-0 w-full h-full`}
          aria-hidden="true"
        />
      )}
      <img
        src={src}
        alt={alt}
        className={`${className ?? ''} absolute inset-0 w-full h-full`}
        loading="eager"
        decoding="async"
      />
    </div>
  )
})

ProgressiveImage.displayName = 'ProgressiveImage'

export const DetailHeader = memo(({ thumbnail, name, imageModalOpen, onImageModalOpenChange }: DetailHeaderProps) => {
  const thumbnailUrl = getThumbnailUrl(thumbnail)
  const thumbnailPreviewUrl = getThumbnailPreviewUrl(thumbnail)

  return (
    <>
      {/* Header image - full width within content bounds */}
      <div
        className="-mx-3 -mt-3 md:-mx-6 md:-mt-6 cursor-pointer"
        style={{ aspectRatio: '16/9' }}
        onClick={() => thumbnailUrl && onImageModalOpenChange(true)}
      >
        <div className="w-full h-full bg-gradient-to-br from-gray-200 to-gray-300">
          {thumbnailUrl && (
            <ProgressiveImage
              src={thumbnailUrl}
              previewSrc={thumbnailPreviewUrl}
              alt={name}
              className="w-full h-full object-cover"
            />
          )}
        </div>
      </div>

      {/* Image Modal */}
      <Dialog open={imageModalOpen} onOpenChange={onImageModalOpenChange}>
        <DialogContent className="max-w-full w-full h-full p-0 bg-black/90 border-0">
          <button
            onClick={() => onImageModalOpenChange(false)}
            className="absolute top-4 right-4 z-50 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 transition-colors"
          >
            <X className="h-6 w-6" />
          </button>
          {thumbnailUrl && (
            <ProgressiveImage
              src={thumbnailUrl}
              previewSrc={thumbnailPreviewUrl}
              alt={name}
              className="w-full h-full object-contain"
            />
          )}
        </DialogContent>
      </Dialog>
    </>
  )
})

DetailHeader.displayName = 'DetailHeader'
