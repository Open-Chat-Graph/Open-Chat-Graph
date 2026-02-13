import { atom } from "recoil";

export const reportDialogState = atom<{
  open: boolean
  result: FetchResultType
  commentId: number
  id: number
}>({
  key: 'reportDialog',
  default: {
    open: false,
    result: 'unsent',
    commentId: 0,
    id: 0
  }
});