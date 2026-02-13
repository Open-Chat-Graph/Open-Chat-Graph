import { useSetRecoilState } from 'recoil'
import { postedItemState } from '../state/postedItemState'
import { getDatetimeString } from '../utils/utils'
import { useCallback } from 'react'

export default function useSetPostedItem() {
  const setPostedItem = useSetRecoilState(postedItemState)

  return useCallback((commentId: number, name: string, text: string, userId: string) => setPostedItem((p) => [{
    comment: {
      id: 0,
      commentId,
      name,
      time: getDatetimeString(),
      text,
      userId
    },
    like: {
      empathyCount: 0,
      insightsCount: 0,
      negativeCount: 0,
      voted: ''
    }
  }, ...p]), [setPostedItem])
}
