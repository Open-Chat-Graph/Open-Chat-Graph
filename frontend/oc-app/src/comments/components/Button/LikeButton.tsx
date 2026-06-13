import { useCallback, useRef, useState } from 'react'
import LikeButtonUi from './LikeButtonUi'
import { fetchApi } from '../../utils/utils'
import { trackEvent } from '../../../util/track'

export type LikeBtnState = LikeBtnApi & { commentId: number }

export type LikeBtnHandler = (type: LikeBtnType) => void

export default function LikeButton(props: LikeBtnState) {
  const sendingRef = useRef(false)
  const [state, setState] = useState<LikeBtnState>(props)

  const handler: LikeBtnHandler = useCallback(
    async (type) => {
      if (sendingRef.current) return
      sendingRef.current = true

      // 未投票なら付与(POST)、投票済みなら取り消し(DELETE)
      const isAdding = state.voted === ''

      try {
        const res = await fetchApi<LikeBtnApi>(
          `${window.location.origin}/comment_reaction/${state.commentId}`,
          isAdding ? 'POST' : 'DELETE',
          { type }
        )
        setState({ ...res, commentId: state.commentId })

        // 付与時のみ計測。empathy=いいね！/negative=うーん…
        if (isAdding) {
          trackEvent('comment_vote', { vote_type: type === 'negative' ? 'dislike' : 'like' })
        }
      } finally {
        sendingRef.current = false
      }
    },
    [state.commentId, state.voted]
  )

  return <LikeButtonUi {...{ ...state, handler }} />
}
