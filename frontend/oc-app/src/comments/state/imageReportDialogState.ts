import { atom } from 'jotai'

export const imageReportDialogState = atom<{
  open: boolean
  result: FetchResultType
  imageId: number
  commentNo: number
}>({
  open: false,
  result: 'unsent',
  imageId: 0,
  commentNo: 0,
})
