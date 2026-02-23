import { atom } from 'jotai'

export const reportDialogState = atom<{
  open: boolean
  result: FetchResultType
  commentId: number
  id: number
}>({
  open: false,
  result: 'unsent',
  commentId: 0,
  id: 0,
})
