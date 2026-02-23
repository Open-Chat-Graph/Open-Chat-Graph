import { useSetAtom } from 'jotai'
import { postedItemState } from '../state/postedItemState'
import { getDatetimeString } from '../utils/utils'
import { useCallback } from 'react'

export default function useSetPostedItem() {
  const setPostedItem = useSetAtom(postedItemState)

  return useCallback((commentId: number, name: string, text: string, userIdHash: string, uaHash: string, ipHash: string, images: CommentImage[] = []) => setPostedItem((p) => [{
    comment: {
      id: 0,
      commentId,
      name,
      time: getDatetimeString(),
      text,
      status: '',
      userIdHash,
      uaHash,
      ipHash,
    },
    like: {
      empathyCount: 0,
      insightsCount: 0,
      negativeCount: 0,
      voted: ''
    },
    images,
  }, ...p]), [setPostedItem])
}
