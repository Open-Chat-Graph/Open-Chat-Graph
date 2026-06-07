/**
 * Web Push 購読サービス
 *
 * - getPushStatus(): ブラウザ/権限/購読状態を返す
 * - subscribe(): 公開鍵取得 → 権限要求 → pushManager 購読 → サーバー登録
 * - unsubscribe(): pushManager 解除 → サーバー削除
 * - ensureSubscription(): アプリ起動時の自己修復（権限 granted + localStorage フラグ + SW 購読 null → 再購読）
 */

const API_BASE = '/alpha-api'

/** localStorage に購読済みフラグを保存するキー */
const LS_KEY = 'alpha_push_subscribed'

export type PushStatus = 'unsupported' | 'denied' | 'subscribed' | 'unsubscribed'

/** pushManager の購読に渡す applicationServerKey 用のデコーダ */
function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = atob(base64)
  return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)))
}

/** Service Worker の登録を取得（なければ null） */
async function getRegistration(): Promise<ServiceWorkerRegistration | null> {
  if (!('serviceWorker' in navigator)) return null
  try {
    return (await navigator.serviceWorker.getRegistration()) ?? null
  } catch {
    return null
  }
}

/**
 * 現在のプッシュ購読状態を返す。
 *
 * - 'unsupported': ブラウザが ServiceWorker/PushManager を持たない
 * - 'denied':      通知権限が拒否済み
 * - 'subscribed':  pushManager に有効な購読がある
 * - 'unsubscribed': それ以外（未要求 or 解除済み）
 */
export async function getPushStatus(): Promise<PushStatus> {
  if (
    !('serviceWorker' in navigator) ||
    !('PushManager' in window) ||
    !('Notification' in window)
  ) {
    return 'unsupported'
  }
  if (Notification.permission === 'denied') {
    return 'denied'
  }

  const reg = await getRegistration()
  if (!reg) return 'unsubscribed'

  try {
    const sub = await reg.pushManager.getSubscription()
    return sub ? 'subscribed' : 'unsubscribed'
  } catch {
    return 'unsubscribed'
  }
}

/** /alpha-api/push/config を取得する */
async function fetchPushConfig(): Promise<{ publicKey: string; enabled: boolean }> {
  const res = await fetch(`${API_BASE}/push/config`, { credentials: 'include' })
  if (!res.ok) throw new Error('push/config fetch failed: ' + res.status)
  return res.json() as Promise<{ publicKey: string; enabled: boolean }>
}

/** pushSubscription オブジェクトをサーバーに登録する */
async function postSubscribe(sub: PushSubscription): Promise<void> {
  const json = sub.toJSON()
  const res = await fetch(`${API_BASE}/push/subscribe`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      endpoint: json.endpoint,
      keys: json.keys,
    }),
  })
  if (!res.ok) throw new Error('push/subscribe failed: ' + res.status)
}

/** pushSubscription のエンドポイントをサーバーから削除する */
async function postUnsubscribe(endpoint: string): Promise<void> {
  const res = await fetch(`${API_BASE}/push/unsubscribe`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ endpoint }),
  })
  // 404（サーバー側に購読がない）は無視して正常扱い
  if (!res.ok && res.status !== 404) {
    throw new Error('push/unsubscribe failed: ' + res.status)
  }
}

/**
 * プッシュ通知を購読する。
 *
 * 1. /alpha-api/push/config から VAPID 公開鍵を取得（enabled:false なら中断）
 * 2. Notification.requestPermission() で権限を要求
 * 3. pushManager.subscribe() で購読を作成
 * 4. サーバーに購読情報を POST
 * 5. localStorage にフラグを保存
 */
export async function subscribe(): Promise<void> {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    throw new Error('このブラウザはプッシュ通知に対応していません')
  }

  const config = await fetchPushConfig()
  if (!config.enabled) {
    throw new Error('プッシュ通知はまだ準備中です')
  }

  const permission = await Notification.requestPermission()
  if (permission !== 'granted') {
    throw new Error('通知の許可が得られませんでした')
  }

  const reg = await navigator.serviceWorker.ready
  const sub = await reg.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(config.publicKey),
  })

  await postSubscribe(sub)
  localStorage.setItem(LS_KEY, '1')
}

/**
 * プッシュ通知の購読を解除する。
 *
 * 1. pushManager の購読を解除
 * 2. サーバーに unsubscribe POST
 * 3. localStorage フラグを削除
 */
export async function unsubscribe(): Promise<void> {
  const reg = await getRegistration()
  if (reg) {
    const sub = await reg.pushManager.getSubscription()
    if (sub) {
      await postUnsubscribe(sub.endpoint)
      await sub.unsubscribe()
    }
  }
  localStorage.removeItem(LS_KEY)
}

/**
 * アプリ起動時の自己修復:
 * 「権限 granted かつ localStorage フラグあり かつ pushManager 購読が null」
 * のとき静かに再購読する。
 * 失敗しても例外を投げない（ユーザーへの通知不要）。
 */
export async function ensureSubscription(): Promise<void> {
  if (
    !('serviceWorker' in navigator) ||
    !('PushManager' in window) ||
    !('Notification' in window)
  ) {
    return
  }
  if (Notification.permission !== 'granted') return
  if (!localStorage.getItem(LS_KEY)) return

  try {
    const reg = await getRegistration()
    if (!reg) return
    const existing = await reg.pushManager.getSubscription()
    if (existing) return // すでに購読済み

    // SW が準備できていれば再購読を試みる
    await subscribe()
  } catch {
    // 失敗は無視（VAPID 未設定やネットワーク切断など）
  }
}
