import { createStore } from 'jotai'

// ESM の live binding 経由で全モジュールが常に最新の store を参照する
export let graphStore = createStore()

/** 再マウント時に全 atom を初期値へ戻す（前回マウントの状態リーク防止） */
export function resetGraphStore() {
  graphStore = createStore()
}
