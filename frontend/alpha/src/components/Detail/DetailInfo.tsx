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
    <div className="max-w-[700px] mx-auto space-y-4">
      {/* Title and icons */}
      <div className="flex items-center gap-2">
        {emblem === 2 && (
          <OfficialIcon className="w-5 h-5 flex-shrink-0" />
        )}
        {emblem === 1 && (
          <SpecialIcon className="w-[21px] h-5 flex-shrink-0" />
        )}
        <h1 className="text-xl sm:text-2xl font-bold break-words">
          {name}
        </h1>
      </div>

      {/* Description */}
      {description && (
        <div>
          <p
            ref={descriptionRef}
            className={`text-base text-muted-foreground leading-relaxed break-words whitespace-pre-line ${!descriptionExpanded ? 'line-clamp-4' : ''}`}
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
