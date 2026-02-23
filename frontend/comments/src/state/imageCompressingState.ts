import { atom } from 'recoil'

export const imageCompressingState = atom<boolean>({
  key: 'imageCompressingState',
  default: false,
})
