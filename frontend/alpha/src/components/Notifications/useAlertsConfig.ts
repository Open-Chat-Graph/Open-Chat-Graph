import { useCallback } from 'react'
import useSWR, { useSWRConfig } from 'swr'
import { alphaApi } from '@/api/alpha'
import type {
  AlertsConfigResponse,
  AlertsConfigRequest,
  AlertsConfigRequestKeyword,
} from '@/types/api'

const CONFIG_KEY = 'alpha-alerts-config'

/** GETレスポンス（保存形）を PUTリクエスト（送信形）へ変換する。全置き換え保存に使う。 */
export function configToRequest(config: AlertsConfigResponse): AlertsConfigRequest {
  return {
    keywords: config.keywords.map((k) => ({ keyword: k.keyword, category: k.category })),
    rooms: config.rooms.map((r) => ({
      openChatId: r.open_chat_id,
      upMember: r.up_member,
      upPercent: r.up_percent,
      downMember: r.down_member,
      downPercent: r.down_percent,
    })),
    mylistThreshold: {
      upPercent: config.mylistThreshold.up_percent,
      downPercent: config.mylistThreshold.down_percent,
      enabled: config.mylistThreshold.enabled,
    },
  }
}

/**
 * アラート条件（GET /alerts/config）を読み、保存（PUT・全置き換え）するフック。
 *
 * 保存後の最新が返るのでキャッシュへ反映する。`addKeyword` は検索バー等からの
 * 「このキーワードをアラート」導線用で、現在の設定に1件足して全置き換え保存する。
 */
export function useAlertsConfig() {
  const { mutate } = useSWRConfig()
  const { data, isLoading, error } = useSWR<AlertsConfigResponse>(
    CONFIG_KEY,
    () => alphaApi.getAlertsConfig(),
    { revalidateOnFocus: false },
  )

  const save = useCallback(
    async (body: AlertsConfigRequest): Promise<AlertsConfigResponse> => {
      const latest = await alphaApi.putAlertsConfig(body)
      await mutate(CONFIG_KEY, latest, { revalidate: false })
      return latest
    },
    [mutate],
  )

  // 検索バー等からのキーワード追加。同一キーワード＋カテゴリの重複は足さない。
  const addKeyword = useCallback(
    async (kw: AlertsConfigRequestKeyword): Promise<{ added: boolean }> => {
      const current = data ?? (await alphaApi.getAlertsConfig())
      const norm = kw.keyword.trim().slice(0, 190)
      if (!norm) return { added: false }
      const cat = kw.category ?? null
      const exists = current.keywords.some(
        (k) => k.keyword === norm && (k.category ?? null) === cat,
      )
      if (exists) return { added: false }

      const req = configToRequest(current)
      req.keywords = [...(req.keywords ?? []), { keyword: norm, category: cat }]
      await save(req)
      return { added: true }
    },
    [data, save],
  )

  // 詳細画面からの部屋追加（ワンタップ）。既定しきい値は ±10%。重複は足さない。
  // 細かいしきい値は詳細画面の「アラートON」カードで調整できる。
  const addRoom = useCallback(
    async (openChatId: number): Promise<{ added: boolean }> => {
      const current = data ?? (await alphaApi.getAlertsConfig())
      if (current.rooms.some((r) => r.open_chat_id === openChatId)) return { added: false }
      const req = configToRequest(current)
      req.rooms = [
        ...(req.rooms ?? []),
        { openChatId, upPercent: 10, downPercent: 10, upMember: null, downMember: null },
      ]
      await save(req)
      return { added: true }
    },
    [data, save],
  )

  // 詳細画面からのアラート解除。openChatId で対象を特定して除外し全置き換え保存する。
  const removeRoom = useCallback(
    async (openChatId: number): Promise<{ removed: boolean }> => {
      const current = data ?? (await alphaApi.getAlertsConfig())
      if (!current.rooms.some((r) => r.open_chat_id === openChatId)) return { removed: false }
      const req = configToRequest(current)
      req.rooms = (req.rooms ?? []).filter((r) => r.openChatId !== openChatId)
      await save(req)
      return { removed: true }
    },
    [data, save],
  )

  // 詳細画面の「アラートON」カードからの単一％設定。増減同値（up=down）の対称しきい値にし、
  // 人数しきい値はクリアする（％だけで運用する単純化）。空・非数なら保存しない。
  const setRoomPercent = useCallback(
    async (openChatId: number, raw: string): Promise<{ saved: boolean }> => {
      const current = data ?? (await alphaApi.getAlertsConfig())
      if (!current.rooms.some((r) => r.open_chat_id === openChatId)) return { saved: false }
      const n = Number(raw)
      if (raw.trim() === '' || !Number.isFinite(n) || n <= 0) return { saved: false }
      const req = configToRequest(current)
      req.rooms = (req.rooms ?? []).map((r) =>
        r.openChatId === openChatId
          ? { ...r, upPercent: n, downPercent: n, upMember: null, downMember: null }
          : r,
      )
      await save(req)
      return { saved: true }
    },
    [data, save],
  )

  return { config: data, isLoading, error, save, addKeyword, addRoom, removeRoom, setRoomPercent }
}
