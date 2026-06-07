import { useCallback } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useLayout } from '@/contexts/layout-context'
import {
  VIEWS,
  resolveIntent,
  viewOwning,
  isOverlayPath,
  canGoBackInApp,
  type ViewKey,
} from '@/lib/viewNavigation'

/**
 * 画面表示状態カーネルの唯一の入口。すべてのタブ押下はここを経由する。
 *
 * - enter / reclick: どちらも「破棄」。ビュー配下の全パネルの reset nonce を bump して
 *   keep-alive パネルを key で強制再マウント（React state を破棄）＋スクロール先頭にし、
 *   ルートパスへ遷移する。データは SWR キャッシュが持つので再実行すれば即表示される。
 *   履歴だけ違う: enter は push（ブラウザバックで元タブのURLへ戻れる）、reclick は replace。
 * - back（オーバーレイ表示中）: 履歴を戻ってオーバーレイを閉じる（破棄しない）。
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
        // 詳細／統合グラフを閉じる。SPA 内履歴があれば戻る、無ければ対象ビューのルートへ。
        if (canGoBackInApp()) navigate(-1)
        else navigate(view.rootPath)
        return
      }

      // enter / reclick: ビュー配下の全パネルを破棄（強制再マウント＋トップへ）し、ルートへ。
      // bump と navigate は同一イベント内で batch されるので、古い画面のフラッシュは出ない。
      for (const panelKey of view.panelKeys) bumpReset(panelKey)
      navigate(view.rootPath, { replace: intent === 'reclick' })
    },
    [location.pathname, navigate, bumpReset],
  )

  return {
    goToView,
    currentViewKey: viewOwning(location.pathname)?.key ?? null,
    isOverlay: isOverlayPath(location.pathname),
  }
}
