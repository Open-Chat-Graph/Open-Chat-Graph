/**
 * スパークライン用メモリキャッシュ。
 * - Map<id, number[] | null> でキャッシュ（null = データ無し）
 * - インフライト重複排除（同一IDを複数箇所から同時リクエストしても1fetchのみ）
 * - subscribe/notify で変化を購読
 */

const API_URL = '/alpha-api/sparkline'
const CHUNK_SIZE = 50

// キャッシュ本体: undefined=未取得, null=データ無し, number[]=取得済み
const cache = new Map<number, number[] | null>()

// インフライト管理: 取得中の ID セット
const inflight = new Set<number>()

// 購読者一覧
type Subscriber = () => void
const subscribers = new Set<Subscriber>()

export function subscribe(fn: Subscriber): () => void {
  subscribers.add(fn)
  return () => subscribers.delete(fn)
}

function notify(): void {
  for (const fn of subscribers) fn()
}

/** キャッシュから取得。undefined=未取得, null=データ無し, number[]=取得済み */
export function get(id: number): number[] | null | undefined {
  return cache.get(id)
}

/** 未取得 ID だけを 50 件ずつチャンクして並列 fetch し、完了後に通知する。 */
export async function ensure(ids: number[]): Promise<void> {
  // 未取得かつインフライトでない ID を抽出
  const missing = ids.filter((id) => !cache.has(id) && !inflight.has(id))
  if (missing.length === 0) return

  // インフライトに登録
  for (const id of missing) inflight.add(id)

  // 50件ずつチャンク
  const chunks: number[][] = []
  for (let i = 0; i < missing.length; i += CHUNK_SIZE) {
    chunks.push(missing.slice(i, i + CHUNK_SIZE))
  }

  await Promise.all(
    chunks.map(async (chunk) => {
      try {
        const res = await fetch(`${API_URL}?ids=${chunk.join(',')}`)
        if (!res.ok) throw new Error(`sparkline fetch failed: ${res.status}`)
        const json = await res.json() as { items: Record<string, Array<{ date: string; member: number }>> }
        const items = json.items ?? {}

        for (const id of chunk) {
          const raw = items[String(id)]
          if (Array.isArray(raw) && raw.length > 0) {
            cache.set(id, raw.map((p) => p.member))
          } else {
            cache.set(id, null)
          }
          inflight.delete(id)
        }
      } catch {
        // エラー時はキャッシュに null を入れてリトライをしない（ページリロードまで無視）
        for (const id of chunk) {
          cache.set(id, null)
          inflight.delete(id)
        }
      }
    }),
  )

  notify()
}
