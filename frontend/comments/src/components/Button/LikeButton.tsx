import { useCallback, useRef, useState } from 'react'
import LikeButtonUi from './LikeButtonUi'
import { fetchApi } from '../../utils/utils'
import { appInitTagDto } from '../../config/appInitTagDto'

export type LikeBtnState = LikeBtnApi & { commentId: number }

export type LikeBtnHandler = (type: LikeBtnType) => void

export default function LikeButton(props: LikeBtnState) {
  const sendingRef = useRef(false)
  const [state, setState] = useState<LikeBtnState>(props)

  const handler: LikeBtnHandler = useCallback(
    async (type) => {
      if (sendingRef.current) return
      sendingRef.current = true

      try {
        const res = await fetchApi<LikeBtnApi>(
          `${appInitTagDto.baseUrl}/comment_reaction/${state.commentId}`,
          state.voted === '' ? 'POST' : 'DELETE',
          { type }
        )
        setState({ ...res, commentId: state.commentId })
      } finally {
        sendingRef.current = false
      }
    },
    [state.commentId, state.voted]
  )

  return <LikeButtonUi {...{ ...state, handler }} />
}
