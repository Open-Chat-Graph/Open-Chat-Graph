import { atom } from "recoil";

export const postedItemState = atom<CommentItem[]>({
  key: 'postedItem',
  default: [],
});