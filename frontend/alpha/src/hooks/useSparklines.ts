import { useEffect, useRef, useState } from 'react'
import { ensure, get, subscribe } from '@/services/sparklineCache'

/**
 * 指定 ID 群のスパークラインデータを返す。
 * - キャッシュ済み分は即座に返す（再 fetch しない）
 * - 未取得分だけ ensure() でバックグラウンド fetch し、完了後に notify → 再レンダー
 */
export function useSparklines(ids: number[]): Record<number, number[] | undefined> {
  // ids の参照を安定させる（配列は毎レンダー新規生成されるため内容で比較）
  const idsRef = useRef<number[]>(ids)
  const idsKey = ids.join(',')
  if (idsRef.current.join(',') !== idsKey) {
    idsRef.current = ids
  }
  const stableIds = idsRef.current

  // キャッシュ内容から現在のスナップショットを作る
  const snapshot = (): Record<number, number[] | undefined> => {
    const result: Record<number, number[] | undefined> = {}
    for (const id of stableIds) {
      const v = get(id)
      if (v !== undefined && v !== null) {
        result[id] = v
      }
      // undefined(未取得) や null(データ無し) は result に含めない → undefined として扱われる
    }
    return result
  }

  const [data, setData] = useState<Record<number, number[] | undefined>>(snapshot)

  useEffect(() => {
    // 購読
    const unsub = subscribe(() => {
      setData(snapshot())
    })

    // 未取得分を fetch（已取得・インフライト中は内部でスキップ）
    if (stableIds.length > 0) {
      ensure(stableIds)
    }

    return unsub
    // stableIds が変わったときだけ再実行（ref で安定化済み）
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [idsKey])

  return data
}
