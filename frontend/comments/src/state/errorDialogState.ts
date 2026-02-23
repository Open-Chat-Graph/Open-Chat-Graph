import { atom } from 'recoil'

export const errorDialogState = atom<{
  open: boolean
  message: string
}>({
  key: 'errorDialog',
  default: {
    open: false,
    message: '',
  },
})
