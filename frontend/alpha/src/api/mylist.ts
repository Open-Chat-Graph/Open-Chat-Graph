import type { Folder, ChatItem } from '@/types/storage'

const API_BASE = '/alpha-api'

/** GET /alpha-api/mylist のアイテム（addedAt はサーバ形式 'Y-m-d H:i:s'） */
export interface ServerMylistItem {
  id: number
  folderId: string | null
  order: number
  addedAt: string
  source: 'manual' | 'auto'
}

export interface MylistGetResponse {
  exists: boolean
  folders: Folder[]
  items: ServerMylistItem[]
  /** 'Y-m-d H:i:s' */
  serverTime: string
}

export interface MylistPutBody {
  folders: Folder[]
  items: Array<ChatItem & { source: 'manual' | 'auto' }>
  /**
   * 最後にサーバ状態を取り込んだ時刻（GET/PUT 応答の serverTime）。
   * サーバはこれより後に追加された auto 項目を全置換の削除から保護する。初回移行は null。
   */
  loadedAt: string | null
}

/** PUT / 単発追加・削除の応答（単発系は serverTime を返さない実装でも壊れないよう optional） */
export interface MylistMutationResponse {
  ok: boolean
  serverTime?: string
}

export const mylistApi = {
  /** サーバのマイリストを取得する */
  async get(): Promise<MylistGetResponse> {
    const res = await fetch(`${API_BASE}/mylist`, { credentials: 'include' })
    if (!res.ok) throw new Error('MyList GET failed: ' + res.status)
    return res.json()
  },

  /** サーバのマイリストを全置換する */
  async replace(body: MylistPutBody): Promise<MylistMutationResponse> {
    const res = await fetch(`${API_BASE}/mylist`, {
      method: 'PUT',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    })
    if (!res.ok) throw new Error('MyList PUT failed: ' + res.status)
    return res.json()
  },

  /** アイテム1件をサーバに即時追加する */
  async addItem(id: number, folderId: string | null): Promise<MylistMutationResponse> {
    const res = await fetch(`${API_BASE}/mylist/items`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, folderId }),
    })
    if (!res.ok) throw new Error('MyList item add failed: ' + res.status)
    return res.json()
  },

  /**
   * アイテム1件をサーバから即時削除する。
   * バックエンドが POST /alpha-api/mylist/items/remove 方式に変わった場合はこの関数だけ直せばよい。
   */
  async removeItem(id: number): Promise<MylistMutationResponse> {
    const res = await fetch(`${API_BASE}/mylist/items/${id}`, {
      method: 'DELETE',
      credentials: 'include',
    })
    if (!res.ok) throw new Error('MyList item remove failed: ' + res.status)
    return res.json()
  },
}
