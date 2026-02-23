import { atom } from 'recoil'

export const errorDialogState = atom<{
  open: boolean
  message: string
  detail: string
}>({
  key: 'errorDialog',
  default: {
    open: false,
    message: '',
    detail: '',
  },
})
