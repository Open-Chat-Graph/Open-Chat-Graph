type CommentItemApi = {
  id: number
  commentId: number
  name: string
  time: string
  text: string
  userId: string
  userIdHash: string
  uaHash: string | null
  ipHash: string | null
}

type LikeBtnType = 'empathy' | 'insights' | 'negative'

type LikeBtnApi = {
  empathyCount: number
  insightsCount: number
  negativeCount: number
  voted: LikeBtnType | ''
}

type CommentImage = {
  id: number
  filename: string
}

type CommentItem = {
  comment: CommentItemApi
  like: LikeBtnApi
  images: CommentImage[]
}

interface ErrorResponse {
  error: {
    code: string
    message: string
  }
}
