import { createContext, useContext, useState } from 'react'
import type { ReactNode } from 'react'

export interface DetailTitle {
  name: string
  member: number
}

interface LayoutContextValue {
  /** 詳細ページのヘッダーに出すタイトル（詳細ページ以外では null） */
  detailTitle: DetailTitle | null
  setDetailTitle: (title: DetailTitle | null) => void
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

  return (
    <LayoutContext.Provider value={{ detailTitle, setDetailTitle }}>
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
