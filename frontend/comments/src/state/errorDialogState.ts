import { atom } from 'jotai'

export const errorDialogState = atom<{
  open: boolean
  message: string
  detail: string
}>({
  open: false,
  message: '',
  detail: '',
})
