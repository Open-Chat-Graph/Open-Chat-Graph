import { memo } from 'react'
import { Loader2 } from 'lucide-react'

interface InfiniteScrollLoaderProps {
  isLoading: boolean
  hasMore: boolean
  observerRef: React.RefObject<HTMLDivElement>
}

export const InfiniteScrollLoader = memo(({ isLoading, hasMore, observerRef }: InfiniteScrollLoaderProps) => {
  return (
    <>
      {isLoading && (
        <div className="flex justify-center py-8">
          <Loader2 className="h-6 w-6 animate-spin text-primary" />
        </div>
      )}
      {hasMore && <div ref={observerRef} className="h-4" />}
    </>
  )
})

InfiniteScrollLoader.displayName = 'InfiniteScrollLoader'
