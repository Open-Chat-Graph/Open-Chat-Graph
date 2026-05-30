import { useCallback, useMemo } from 'react'
import useSWR from 'swr'
import { alphaApi } from '@/api/alpha'
import { loadMyList } from '@/services/storage'
import { loadSeenMembers, saveSeenMembers } from '@/services/notifications'
import type { BatchStatsResponse, OpenChat } from '@/types/api'

export interface GrowthNotifications {
  /** マイリストの全ルーム（統計付き） */
  rooms: OpenChat[]
  /** 直近24時間で人数が増えたルーム（増加幅の降順） */
  gainers: OpenChat[]
  /** 前回確認時より人数が増えている未読ルーム数（バッジ用） */
  unseenCount: number
  /** 指定ルームが未読の増加か */
  isUnseen: (room: OpenChat) => boolean
  /** 現在のメンバー数を「確認済み」として記録（バッジをクリア） */
  markSeen: () => void
  isLoading: boolean
  /** マイリストにルームが登録されているか */
  hasMyList: boolean
}

/**
 * 人数増加通知の状態を返すフック。
 *
 * マイリストのルームを batch-stats でまとめて取得し、24時間で増えたルームと、
 * 前回確認時より増えた「未読」件数（バッジ用）を計算する。サーバー側の購読は持たない。
 * 同一キーの SWR なのでナビとページで重複フェッチされない。
 */
export function useGrowthNotifications(): GrowthNotifications {
  const ids = loadMyList().items.map((item) => item.id)
  // ID集合が同じならキーも同じ（順序非依存）→ SWR が重複排除
  const key = ids.length ? `notif-batch:${[...ids].sort((a, b) => a - b).join(',')}` : null

  const { data, isLoading } = useSWR<BatchStatsResponse>(
    key,
    () => alphaApi.batchStats(ids),
    { revalidateOnFocus: false },
  )

  const rooms = useMemo(() => data?.data ?? [], [data])
  const seen = loadSeenMembers()

  const gainers = useMemo(
    () => rooms.filter((r) => r.diff24h > 0).sort((a, b) => b.diff24h - a.diff24h),
    [rooms],
  )

  const isUnseen = useCallback(
    (room: OpenChat) => seen[room.id] === undefined || room.member > seen[room.id],
    [seen],
  )

  const unseenCount = rooms.filter((r) => r.diff24h > 0 && isUnseen(r)).length

  const markSeen = useCallback(() => {
    const map: Record<number, number> = {}
    rooms.forEach((r) => {
      map[r.id] = r.member
    })
    saveSeenMembers(map)
  }, [rooms])

  return { rooms, gainers, unseenCount, isUnseen, markSeen, isLoading, hasMyList: ids.length > 0 }
}
