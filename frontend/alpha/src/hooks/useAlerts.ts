import { useCallback } from 'react'
import useSWR, { useSWRConfig } from 'swr'
import { alphaApi } from '@/api/alpha'
import type { AlertsResponse } from '@/types/api'

const ALERTS_KEY = 'alpha-alerts'

const EMPTY_ALERTS: AlertsResponse = {
  keywordHits: [],
  movements: [],
  signals: [],
  unreadCount: 0,
  computedAt: null,
}

/**
 * サーバー算出の通知一覧と未読数を返すフック。
 *
 * 通知は毎時クロール後にサーバー側で算出される（即時ではない）。未読数 `unreadCount` は
 * バッジに、`keywordHits`/`movements` は通知ページに使う。同一キーの SWR なので、
 * ナビのバッジと通知ページで重複フェッチされない。
 *
 * 既読化はサーバーの markRead パラメータで行う:
 * - `markAllRead()` … 全件を既読化して再取得
 * - `markRead(ids)` … 指定 id だけ既読化して再取得
 * いずれも楽観的に unreadCount を 0 / 減算しておき、結果で確定する。
 */
export interface UseAlerts {
  data: AlertsResponse | undefined
  unreadCount: number
  isLoading: boolean
  error: unknown
  markAllRead: () => Promise<void>
  markRead: (ids: number[]) => Promise<void>
}

export function useAlerts(): UseAlerts {
  const { mutate } = useSWRConfig()
  const { data, isLoading, error } = useSWR<AlertsResponse>(
    ALERTS_KEY,
    () => alphaApi.getAlerts(),
    { revalidateOnFocus: false },
  )

  const markAllRead = useCallback(async () => {
    // 楽観更新: バッジを即0にして再フェッチを抑止しつつ、サーバーで既読化
    await mutate(
      ALERTS_KEY,
      async (current?: AlertsResponse) => {
        const res = await alphaApi.getAlerts('all')
        return res ?? current
      },
      {
        optimisticData: (current?: AlertsResponse) =>
          current
            ? {
                ...current,
                unreadCount: 0,
                keywordHits: current.keywordHits.map((h) => ({ ...h, isRead: true })),
                movements: current.movements.map((m) => ({ ...m, isRead: true })),
                signals: (current.signals ?? []).map((s) => ({ ...s, isRead: true })),
              }
            : EMPTY_ALERTS,
        revalidate: false,
        populateCache: true,
        rollbackOnError: true,
      },
    )
  }, [mutate])

  const markRead = useCallback(
    async (ids: number[]) => {
      if (ids.length === 0) return
      const idSet = new Set(ids)
      await mutate(
        ALERTS_KEY,
        async (current?: AlertsResponse) => {
          const res = await alphaApi.getAlerts(ids.join(','))
          return res ?? current
        },
        {
          optimisticData: (current?: AlertsResponse) => {
            if (!current) return EMPTY_ALERTS
            const markIfTarget = <T extends { id: number; isRead: boolean }>(a: T): T =>
              idSet.has(a.id) && !a.isRead ? { ...a, isRead: true } : a
            const newlyRead =
              current.keywordHits.filter((h) => idSet.has(h.id) && !h.isRead).length +
              current.movements.filter((m) => idSet.has(m.id) && !m.isRead).length +
              (current.signals ?? []).filter((s) => idSet.has(s.id) && !s.isRead).length
            return {
              ...current,
              unreadCount: Math.max(0, current.unreadCount - newlyRead),
              keywordHits: current.keywordHits.map(markIfTarget),
              movements: current.movements.map(markIfTarget),
              signals: (current.signals ?? []).map(markIfTarget),
            }
          },
          revalidate: false,
          populateCache: true,
          rollbackOnError: true,
        },
      )
    },
    [mutate],
  )

  return {
    data,
    unreadCount: data?.unreadCount ?? 0,
    isLoading,
    error,
    markAllRead,
    markRead,
  }
}
