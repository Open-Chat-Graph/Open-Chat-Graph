/**
 * 人数増加通知の「既読」状態（端末ローカル）。
 *
 * 認証はまだ無いフェーズなので、サーバーに購読を持たず localStorage で完結させる。
 * 各ルームを最後に確認したときのメンバー数を覚えておき、それを上回ったら「未読の増加」とみなす。
 */
import { STORAGE_KEYS } from '@/lib/storage-keys'

const NOTIF_SEEN_KEY = STORAGE_KEYS.notifSeen

/** chatId -> 最後に確認したときのメンバー数 */
export type SeenMembers = Record<number, number>

export function loadSeenMembers(): SeenMembers {
  try {
    const raw = localStorage.getItem(NOTIF_SEEN_KEY)
    return raw ? (JSON.parse(raw) as SeenMembers) : {}
  } catch {
    return {}
  }
}

export function saveSeenMembers(map: SeenMembers): void {
  try {
    localStorage.setItem(NOTIF_SEEN_KEY, JSON.stringify(map))
  } catch {
    // 保存失敗は通知の精度が落ちるだけなので握りつぶす
  }
}
