/**
 * プッシュ通知ハンドラ（generateSW から importScripts で読み込まれる追加スクリプト）
 *
 * ペイロード無しプッシュ（tickle）方式:
 * - push イベントで /alpha-api/alerts を fetch して未読件数・最新内容を取得
 * - 1本のサマリ通知を表示
 * - fetch 失敗時は汎用文言で必ず通知（iOS はプッシュ受信時の通知表示が必須）
 * - notificationclick で通知ページにフォーカス or 新規タブで開く
 */

/** アイコン・バッジのパス（本番: /js/alpha/icons/、開発: /icons/） */
function getIconPath(file) {
  // SW スコープから判定: 本番 scope=/alpha → /js/alpha/icons/ を使う
  const scope = self.registration.scope
  if (scope.includes('/alpha')) {
    return '/js/alpha/icons/' + file
  }
  return '/icons/' + file
}

/** /alpha-api/alerts から未読アラートを取得してサマリ文言を組み立てる */
async function buildNotificationOptions() {
  try {
    const res = await fetch('/alpha-api/alerts', {
      credentials: 'include',
      cache: 'no-store',
    })
    if (!res.ok) throw new Error('fetch failed: ' + res.status)

    /** @type {{ keywordHits: Array<{name:string,isRead:boolean}>, movements: Array<{name:string,diff:number,isRead:boolean}>, signals: Array<{type:string,payload:{name:string,openChatId:number},isRead:boolean}>, unreadCount: number }} */
    const data = await res.json()
    const unreadCount = data.unreadCount ?? 0

    if (unreadCount === 0) {
      return {
        title: 'オプチャグラフα',
        body: '新着アラートがあります',
        icon: getIconPath('icon-192x192.png'),
        badge: getIconPath('icon-96x96.png'),
        data: { url: '/alpha/notifications' },
      }
    }

    // 最初の未読アラートから代表文言を組み立てる
    const unreadMovements = (data.movements ?? []).filter((m) => !m.isRead)
    const unreadKeywords = (data.keywordHits ?? []).filter((h) => !h.isRead)
    const unreadSignals = (data.signals ?? []).filter((s) => !s.isRead)

    // movements → signals → keywords の優先順で代表文言を選ぶ
    let firstText = ''
    const firstMovement = unreadMovements[0] ?? null
    const firstSignal = unreadSignals[0] ?? null
    const firstKeyword = unreadKeywords[0] ?? null

    if (firstMovement) {
      const sign = firstMovement.diff > 0 ? '+' : ''
      firstText = `【${firstMovement.name}】${sign}${firstMovement.diff}人`
    } else if (firstSignal) {
      const name = firstSignal.payload && firstSignal.payload.name ? firstSignal.payload.name : ''
      if (firstSignal.type === 'room_change') {
        firstText = `【${name}】の部屋情報が変更`
      } else if (firstSignal.type === 'rank_jump') {
        firstText = `【${name}】がランキングに動き`
      } else if (firstSignal.type === 'pace') {
        firstText = `【${name}】の増加ペースが急上昇`
      } else {
        firstText = `【${name}】`
      }
    } else if (firstKeyword) {
      firstText = `【${firstKeyword.name}】`
    }

    const body =
      unreadCount === 1
        ? firstText || '新着アラート1件'
        : firstText
          ? `新着アラート${unreadCount}件: ${firstText} ほか`
          : `新着アラート${unreadCount}件`

    return {
      title: 'オプチャグラフα',
      body,
      icon: getIconPath('icon-192x192.png'),
      badge: getIconPath('icon-96x96.png'),
      data: { url: '/alpha/notifications' },
    }
  } catch (_) {
    // fetch 失敗時: 汎用文言で必ず通知する（iOS では push 受信時に通知表示が必須）
    return {
      title: 'オプチャグラフα',
      body: '新着アラートがあります',
      icon: getIconPath('icon-192x192.png'),
      badge: getIconPath('icon-96x96.png'),
      data: { url: '/alpha/notifications' },
    }
  }
}

self.addEventListener('push', (event) => {
  event.waitUntil(
    buildNotificationOptions().then((opts) =>
      self.registration.showNotification(opts.title, {
        body: opts.body,
        icon: opts.icon,
        badge: opts.badge,
        data: opts.data,
      })
    )
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()

  const targetUrl = (event.notification.data && event.notification.data.url)
    ? event.notification.data.url
    : '/alpha/notifications'

  event.waitUntil(
    self.clients
      .matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // すでに通知タブ（/alpha/notifications）が開いていれば focus する
        for (const client of clientList) {
          const url = new URL(client.url)
          // /alpha で始まるタブ（= このアプリ）があれば navigate して focus
          if (url.pathname.startsWith('/alpha')) {
            return client.navigate(targetUrl).then((c) => (c ? c.focus() : null))
          }
        }
        // 開いていなければ新規タブで開く
        return self.clients.openWindow(targetUrl)
      })
  )
})
