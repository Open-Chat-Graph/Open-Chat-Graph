import { atom } from 'recoil'

export const imageFilesState = atom<File[]>({
  key: 'imageFilesState',
  default: [],
})
