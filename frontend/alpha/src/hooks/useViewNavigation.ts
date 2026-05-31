import { useCallback } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useLayout } from '@/contexts/layout-context'
import { STORAGE_KEYS } from '@/lib/storage-keys'
import {
  VIEWS,
  resolveIntent,
  resolveEnterPath,
  viewOwning,
  isOverlayPath,
  type ViewKey,
} from '@/lib/viewNavigation'

/**
 * 画面表示状態カーネルの唯一の入口。すべてのタブ押下はここを経由する。
 *
 * - reclick（同タブ再押下）: ルートへ replace し、対象パネルの reset nonce を bump。
 *   → keep-aliveパネルが強制再マウント＋スクロール先頭（全タブ統一）。
 * - enter（別タブから）: 記憶した状態（検索クエリ／フォルダ／分析サブ画面）を復元して入る。
 * - back（オーバーレイ表示中）: 履歴を戻ってオーバーレイを閉じる。
 */
export function useViewNavigation() {
  const location = useLocation()
  const navigate = useNavigate()
  const { bumpReset } = useLayout()

  const goToView = useCallback(
    (key: ViewKey, e?: { preventDefault: () => void }) => {
      if (e) e.preventDefault()
      const view = VIEWS.find((v) => v.key === key)!
      const intent = resolveIntent(location.pathname, key)

      if (intent === 'back') {
        // 詳細／統合グラフを閉じる。履歴があれば戻る、無ければ対象ビューへ入る。
        if ((window.history.state?.idx ?? 0) > 0) navigate(-1)
        else navigate(resolveEnterPath(key))
        return
      }

      if (intent === 'reclick') {
        // 記憶をリセット（次回 enter は素の状態から）。
        if (key === 'search') sessionStorage.removeItem(STORAGE_KEYS.searchQuery)
        if (view.remembersSubRoute) sessionStorage.removeItem(STORAGE_KEYS.analysisLastSub)
        // ルートへ戻し、パネルを強制再マウント＋トップへ。
        bumpReset(view.rootPanelKey)
        navigate(view.rootPath, { replace: true })
        return
      }

      // enter: 記憶した状態を復元して入る。
      navigate(resolveEnterPath(key))
    },
    [location.pathname, navigate, bumpReset],
  )

  return {
    goToView,
    currentViewKey: viewOwning(location.pathname)?.key ?? null,
    isOverlay: isOverlayPath(location.pathname),
  }
}
