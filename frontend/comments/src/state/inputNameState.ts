import { atom } from "recoil";

export const inputNameState = atom<string>({
  key: 'inputName',
  default: '',
});