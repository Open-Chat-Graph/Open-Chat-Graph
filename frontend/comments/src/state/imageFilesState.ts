import { atom } from 'recoil'

export const imageFilesState = atom<Blob[]>({
  key: 'imageFilesState',
  default: [],
})
