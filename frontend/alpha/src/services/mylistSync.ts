/**
 * マイリストのサーバ同期（サーバ正本・localStorage はローカルキャッシュ）。
 *
 * - 起動時 initMylistSync(): GET でサーバ状態を取得。
 *   - exists:false かつローカルにデータあり → 全置換 PUT（loadedAt:null）で初回移行。
 *   - exists:true → サーバ状態でローカルを上書き。
 * - 変更時 notifyMylistMutation(): addItem/removeItem は単発 API を即時 fire-and-forget、
 *   それ以外（フォルダ CRUD・並べ替え・一括・move）は 800ms デバウンスで全置換 PUT。
 * - サーバ不達時は console.warn のみで UI は一切ブロックしない（ローカルのみで動き続け、
 *   次の変更時の全置換 PUT で自然に再同期される）。
 */
import {
  loadMyList,
  saveMyList,
  setMylistMutationListener,
  MYLIST_DATA_VERSION,
  type MylistMutationKind,
  type MylistMutationDetail,
} from './storage'
import { mylistApi, type MylistGetResponse, type MylistMutationResponse } from '@/api/mylist'
import { STORAGE_KEYS } from '@/lib/storage-keys'
import type { ChatItem, MyListData } from '@/types/storage'

const DEBOUNCE_MS = 800

/** 最後にサーバ状態を取り込んだ時刻（serverTime）。PUT の loadedAt に使う */
let loadedAt: string | null = readStoredLoadedAt()
/** 初期同期の単一実行ガード（二重 GET / 二重初回移行 PUT を防ぐ） */
let initPromise: Promise<void> | null = null
let debounceTimer: number | null = null
/** 全置換 PUT のインフライト中フラグ。重なりを防ぎ、完了後に再スケジュールする */
let putInFlight = false
let putQueued = false

function readStoredLoadedAt(): string | null {
  try {
    return localStorage.getItem(STORAGE_KEYS.myListLoadedAt)
  } catch {
    return null
  }
}

function setLoadedAt(serverTime: string | undefined): void {
  if (!serverTime) return
  loadedAt = serverTime
  try {
    localStorage.setItem(STORAGE_KEYS.myListLoadedAt, serverTime)
  } catch {
    // localStorage 不可でもメモリ上の loadedAt で動作継続
  }
}

/** サーバ形式 'Y-m-d H:i:s' → ISO 8601（変換不能ならそのまま返す） */
function toIsoString(serverDate: string): string {
  const d = new Date(serverDate.includes('T') ? serverDate : serverDate.replace(' ', 'T'))
  return Number.isNaN(d.getTime()) ? serverDate : d.toISOString()
}

/** GET 応答をローカルの MyListData 形に変換する */
function serverToLocal(res: MylistGetResponse): MyListData {
  return {
    version: MYLIST_DATA_VERSION,
    folders: res.folders.map(f => ({
      id: f.id,
      name: f.name,
      parentId: f.parentId ?? null,
      order: f.order,
      expanded: Boolean(f.expanded),
    })),
    items: res.items.map(i => ({
      id: i.id,
      folderId: i.folderId ?? null,
      order: i.order,
      addedAt: toIsoString(i.addedAt),
      source: i.source ?? 'manual',
    })),
    lastModified: new Date().toISOString(),
  }
}

/** PUT body 用に source 未設定（既存ローカルデータ）を 'manual' に正規化する */
function withSource(items: ChatItem[]): Array<ChatItem & { source: 'manual' | 'auto' }> {
  return items.map(i => ({ ...i, source: i.source ?? 'manual' }))
}

/**
 * サーバ状態でローカルを強制上書きする再同期。
 * フォルダ設定の保存後など、サーバが auto 追加した結果をローカルに反映したいときに使う。
 * 失敗時は console.warn のみで UI をブロックしない。
 */
export async function resyncMylist(): Promise<void> {
  try {
    const res = await mylistApi.get()
    if (res.exists) {
      saveMyList(serverToLocal(res))
      setLoadedAt(res.serverTime)
    }
  } catch (error) {
    console.warn('[mylistSync] 再同期に失敗しました。', error)
  }
}

/**
 * 起動時に1回呼ぶ初期同期。失敗（オフライン/5xx）時は何もしない＝ローカルのみで動き続ける。
 * 複数回呼ばれても実行は1回だけ。
 */
export function initMylistSync(): Promise<void> {
  initPromise ??= doInit()
  return initPromise
}

async function doInit(): Promise<void> {
  try {
    const res = await mylistApi.get()

    if (res.exists) {
      // サーバが正本: ローカルキャッシュを上書き（開いている画面は遷移時の再読込で反映）
      saveMyList(serverToLocal(res))
      setLoadedAt(res.serverTime)
      return
    }

    const local = loadMyList()
    if (local.folders.length > 0 || local.items.length > 0) {
      // 初回移行: ローカルデータをサーバへ（loadedAt:null）
      const put = await mylistApi.replace({
        folders: local.folders,
        items: withSource(local.items),
        loadedAt: null,
      })
      setLoadedAt(put.serverTime)
    } else {
      // 双方空。serverTime を起点として記録
      setLoadedAt(res.serverTime)
    }
  } catch (error) {
    console.warn('[mylistSync] 初期同期に失敗しました。ローカルのみで継続します。', error)
  }
}

/**
 * storage.ts の mutate 関数から呼ばれる変更通知（コールバック登録で循環 import を回避）。
 * 同期処理はすべて非同期 fire-and-forget で、呼び出し元をブロック・失敗させない。
 */
export function notifyMylistMutation(kind: MylistMutationKind, detail?: MylistMutationDetail): void {
  const id = detail?.id

  if (kind === 'addItem' && typeof id === 'number') {
    const folderId = detail?.folderId ?? null
    void pushSingle(() => mylistApi.addItem(id, folderId), 'addItem')
    return
  }

  if (kind === 'removeItem' && typeof id === 'number') {
    void pushSingle(() => mylistApi.removeItem(id), 'removeItem')
    return
  }

  schedulePut()
}

// モジュール読み込み時に登録（App 起動時の initMylistSync import で評価される）
setMylistMutationListener(notifyMylistMutation)

/** 単発 API を即時送信。初期同期完了を待ってから送る（初回移行 PUT との順序を保証） */
async function pushSingle(request: () => Promise<MylistMutationResponse>, label: string): Promise<void> {
  try {
    if (initPromise) await initPromise // doInit は throw しない
    const res = await request()
    setLoadedAt(res.serverTime)
  } catch (error) {
    console.warn(`[mylistSync] ${label} の同期に失敗しました（次の変更時の全置換で再同期されます）`, error)
  }
}

/** 全置換 PUT を 800ms デバウンスでスケジュールする（連打中はタイマーを延長） */
function schedulePut(): void {
  if (debounceTimer !== null) clearTimeout(debounceTimer)
  debounceTimer = window.setTimeout(() => {
    debounceTimer = null
    void flushPut()
  }, DEBOUNCE_MS)
}

async function flushPut(): Promise<void> {
  if (putInFlight) {
    // インフライト中の変更は完了後に再スケジュール（PUT を重ねない）
    putQueued = true
    return
  }

  putInFlight = true
  try {
    if (initPromise) await initPromise // 初期同期前のローカル状態でサーバを上書きしない
    const data = loadMyList() // body は送信時点の最新ローカル状態から構築
    const res = await mylistApi.replace({
      folders: data.folders,
      items: withSource(data.items),
      loadedAt,
    })
    setLoadedAt(res.serverTime)
  } catch (error) {
    console.warn('[mylistSync] 全置換 PUT に失敗しました（次の変更時に再試行されます）', error)
  } finally {
    putInFlight = false
    if (putQueued) {
      putQueued = false
      schedulePut()
    }
  }
}
