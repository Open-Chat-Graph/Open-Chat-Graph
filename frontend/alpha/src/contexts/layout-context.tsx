import { createContext, useContext, useState, useCallback } from 'react'
import type { ReactNode } from 'react'

export interface DetailTitle {
  name: string
  member: number
}

interface LayoutContextValue {
  /** 詳細ページのヘッダーに出すタイトル（詳細ページ以外では null） */
  detailTitle: DetailTitle | null
  setDetailTitle: (title: DetailTitle | null) => void
  /** 検索の再実行シグナル。同じキーワードでも再フェッチさせたいとき bump する */
  searchNonce: number
  triggerSearch: () => void
  /**
   * keep-alive パネルごとのリセット nonce（画面表示状態カーネル）。
   * 値が変わったパネルは key に混ぜて強制再マウント＋スクロール先頭へ戻す。
   * 同タブ再クリック等で `bumpReset(panelKey)` を呼ぶ。enter（タブ復帰）では
   * 触らないので状態は保持される。
   */
  resetNonces: Record<string, number>
  bumpReset: (panelKey: string) => void
}

const LayoutContext = createContext<LayoutContextValue | null>(null)

/**
 * レイアウト横断で共有する状態。
 *
 * 旧コードは詳細ページのタイトルを sessionStorage + window イベント経由で
 * ヘッダーに渡していたが、同一タブの sessionStorage 書き込みでは 'storage'
 * イベントが発火せずヘッダーが更新されない（タイトルが「オープンチャット」のまま）
 * というバグがあった。React state に置き換えて確実に再描画させる。
 */
export function LayoutProvider({ children }: { children: ReactNode }) {
  const [detailTitle, setDetailTitle] = useState<DetailTitle | null>(null)
  const [searchNonce, setSearchNonce] = useState(0)
  const triggerSearch = useCallback(() => setSearchNonce((n) => n + 1), [])
  const [resetNonces, setResetNonces] = useState<Record<string, number>>({})
  const bumpReset = useCallback((panelKey: string) => {
    setResetNonces((prev) => ({ ...prev, [panelKey]: (prev[panelKey] ?? 0) + 1 }))
  }, [])

  return (
    <LayoutContext.Provider
      value={{ detailTitle, setDetailTitle, searchNonce, triggerSearch, resetNonces, bumpReset }}
    >
      {children}
    </LayoutContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useLayout(): LayoutContextValue {
  const ctx = useContext(LayoutContext)
  if (!ctx) throw new Error('useLayout must be used within LayoutProvider')
  return ctx
}
