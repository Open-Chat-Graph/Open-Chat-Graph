import { atom } from 'recoil'

export const imageReportDialogState = atom<{
  open: boolean
  result: FetchResultType
  imageId: number
  commentNo: number
}>({
  key: 'imageReportDialog',
  default: {
    open: false,
    result: 'unsent',
    imageId: 0,
    commentNo: 0,
  },
})
