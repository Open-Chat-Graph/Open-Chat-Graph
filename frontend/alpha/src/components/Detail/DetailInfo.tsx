import { memo, useState, useRef, useEffect } from 'react'
import { OfficialIcon, SpecialIcon } from '@/components/icons'

interface DetailInfoProps {
  name: string
  emblem: 0 | 1 | 2
  description?: string
}

export const DetailInfo = memo(({ name, emblem, description }: DetailInfoProps) => {
  const [descriptionExpanded, setDescriptionExpanded] = useState(false)
  const [isTruncated, setIsTruncated] = useState(false)
  const descriptionRef = useRef<HTMLParagraphElement>(null)

  useEffect(() => {
    if (descriptionRef.current && description) {
      // Check if text is actually truncated
      const element = descriptionRef.current
      setIsTruncated(element.scrollHeight > element.clientHeight)
    }
  }, [description])

  return (
    <div className="max-w-[var(--content-w)] mx-auto space-y-2">
      {/* Title and icons */}
      <div className="flex items-center gap-2">
        {emblem === 2 && (
          <OfficialIcon className="w-5 h-5 flex-shrink-0" />
        )}
        {emblem === 1 && (
          <SpecialIcon className="w-[21px] h-5 flex-shrink-0" />
        )}
        <h1 className="text-lg sm:text-xl font-bold break-words leading-snug">
          {name}
        </h1>
      </div>

      {/* Description（本家相当：小さめ・行間詰め） */}
      {description && (
        <div>
          <p
            ref={descriptionRef}
            className={`text-sm text-muted-foreground leading-normal break-words whitespace-pre-line ${!descriptionExpanded ? 'line-clamp-3' : ''}`}
          >
            {description}
          </p>
          {isTruncated && (
            <div className="text-right mt-1.5 -mb-[2px]">
              <button
                onClick={() => setDescriptionExpanded(!descriptionExpanded)}
                className="inline-flex items-center justify-center w-[100px] py-1 text-[14px] text-primary border border-primary rounded-full hover:bg-primary hover:text-primary-foreground transition-colors select-none"
              >
                {descriptionExpanded ? '閉じる' : '続きを読む'}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  )
})

DetailInfo.displayName = 'DetailInfo'
