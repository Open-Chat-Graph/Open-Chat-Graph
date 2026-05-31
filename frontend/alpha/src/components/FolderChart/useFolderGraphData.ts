import useSWR from 'swr'
import { getGraphData } from '@/api/graph'
import type { GraphDataResponse } from '@/types/api'

/** 統合グラフで一度に重ねるルーム数の上限。これを超えた分は対象外にする（負荷・可読性対策）。 */
export const MAX_ROOMS = 12

/** マージ後の1行（1日付）。各ルームは `m{openChatId}` キーで人数を持つ。欠損は null。 */
export type MergedRow = { date: string; [key: string]: number | string | null }

export interface FolderGraphResult {
  rows: MergedRow[]
  /** 取得できたルームの openChatId（元の並び順を保持） */
  loadedIds: number[]
  isLoading: boolean
  error: unknown
}

/**
 * 複数ルームの時系列を取得し、日付軸を和集合でマージして1データセットにする。
 *
 * - 並列フェッチ（Promise.all）。呼び出し側で MAX_ROOMS に丸めて渡す前提。
 * - 日付の和集合をソートし、各ルームの人数を date→値 の引き当てで埋める。
 *   あるルームにその日付が無ければ null（recharts の connectNulls=false なら線が途切れる）。
 */
export function useFolderGraphData(ids: number[]): FolderGraphResult {
  const key = ids.length > 0 ? ['folder-graph', ids.join(',')] : null

  const { data, error, isLoading } = useSWR(
    key,
    async () => {
      const results = await Promise.all(
        ids.map(async (id) => {
          try {
            const g = await getGraphData(id)
            return { id, graph: g }
          } catch {
            return { id, graph: null as GraphDataResponse | null }
          }
        }),
      )
      return results
    },
    { revalidateOnFocus: false, revalidateOnReconnect: false },
  )

  if (!data) {
    return { rows: [], loadedIds: [], isLoading, error }
  }

  const loaded = data.filter((r) => r.graph && r.graph.dates.length > 0)
  const loadedIds = loaded.map((r) => r.id)

  // 日付の和集合（昇順）
  const dateSet = new Set<string>()
  for (const r of loaded) {
    for (const d of r.graph!.dates) dateSet.add(d)
  }
  const allDates = Array.from(dateSet).sort()

  // 各ルームを date→人数 に引き当てやすい形へ
  const perRoom = new Map<number, Map<string, number | null>>()
  for (const r of loaded) {
    const g = r.graph!
    const m = new Map<string, number | null>()
    for (let i = 0; i < g.dates.length; i++) {
      m.set(g.dates[i], g.members[i] ?? null)
    }
    perRoom.set(r.id, m)
  }

  const rows: MergedRow[] = allDates.map((date) => {
    const row: MergedRow = { date }
    for (const id of loadedIds) {
      const member = perRoom.get(id)?.get(date)
      row[`m${id}`] = member ?? null
    }
    return row
  })

  return { rows, loadedIds, isLoading, error }
}

/** ルーム id → 人数系列の dataKey（`m{id}`）。 */
export function dataKeyFor(id: number): string {
  return `m${id}`
}
