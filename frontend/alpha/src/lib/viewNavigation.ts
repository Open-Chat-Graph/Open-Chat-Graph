/**
 * 画面表示状態カーネル（唯一の定義元）。
 *
 * タブの「破棄／オーバーレイを閉じる」は
 * これまで App.tsx / useNavigationHandler / useScrollToTopOnReclick / 各ページに
 * バラバラに実装されていた。ここに宣言的なビュー表とインテント正規化を集約し、
 * `useViewNavigation()`（hooks/useViewNavigation.ts）だけがナビを発行する。
 *
 * 挙動モデル（破棄＋SWRキャッシュ）:
 * タブに入る（enter）も同タブ再押下（reclick）も「破棄」で統一する。
 * ビュー配下の keep-alive パネルを nonce で強制再マウントし、ルートパスへ遷移＋スクロール先頭。
 * 前の画面状態（検索クエリ・サブ画面・スクロール位置）は持ち越さない。
 * データは SWR のグローバルキャッシュが持つので、同じ条件を再実行すれば即表示される。
 * ブラウザ/履歴の戻る（back）は対象外＝履歴で検索結果URL(?q=)に戻ればそのクエリで表示する。
 *
 * 用語:
 * - ビュー(view): 下部ナビ／サイドバーのタブ1つ。複数のパス（サブルート）を束ねる。
 * - パネル(panel): keep-alive で常駐する画面1枚（App.tsx の KEEP_ALIVE_PAGES の1要素）。
 *   1ビューが複数パネルを持つことがある（分析 = analysis / period-growth / labs）。
 * - オーバーレイ(overlay): 詳細・フォルダ統合グラフ。どのビューにも属さず上に重なる。
 */

export type ViewKey = 'search' | 'mylist' | 'analysis' | 'notifications' | 'settings'

export interface ViewDef {
  key: ViewKey
  /** タブ進入／再押下で戻る基準パス */
  rootPath: string
  /**
   * このビューに属する keep-alive パネル（進入／再押下でまとめて破棄＝強制再マウント）。
   * 分析はサブ画面（period-growth / labs）も含めて破棄する。
   * /watch はどのビューにも属さない（通知・設定の両方から到達し、保存ボタン＝未保存編集を持つ）ため含めない。
   */
  panelKeys: string[]
  /** この pathname がこのビューに属するか（サブルート含む） */
  owns: (pathname: string) => boolean
}

export const VIEWS: ViewDef[] = [
  { key: 'search', rootPath: '/', panelKeys: ['search'], owns: (p) => p === '/' },
  {
    key: 'mylist',
    rootPath: '/mylist',
    panelKeys: ['mylist'],
    owns: (p) => p === '/mylist' || p.startsWith('/mylist/'),
  },
  {
    key: 'analysis',
    rootPath: '/analysis',
    panelKeys: ['analysis', 'period-growth', 'labs'],
    owns: (p) => p === '/analysis' || p === '/period-growth' || p === '/labs',
  },
  { key: 'notifications', rootPath: '/notifications', panelKeys: ['notifications'], owns: (p) => p === '/notifications' },
  { key: 'settings', rootPath: '/settings', panelKeys: ['settings'], owns: (p) => p === '/settings' },
]

/** 詳細(/openchat/...) と フォルダ統合グラフ(/mylist/:id/chart) は「上に重ねるオーバーレイ」。 */
export function isOverlayPath(pathname: string): boolean {
  return pathname.startsWith('/openchat/') || /^\/mylist\/[^/]+\/chart$/.test(pathname)
}

/** この pathname を所有するビュー（無ければ null＝オーバーレイや /watch 等の非タブ画面）。 */
export function viewOwning(pathname: string): ViewDef | null {
  return VIEWS.find((v) => v.owns(pathname)) ?? null
}

export type ViewIntent = 'enter' | 'reclick' | 'back'

/**
 * タブ押下の意図を正規化する（分岐はここだけ）。
 * - オーバーレイ表示中            → back（履歴を戻ってオーバーレイを閉じる）
 * - すでに対象ビューがアクティブ  → reclick（破棄してルートへ。履歴は replace）
 * - それ以外                      → enter（破棄してルートへ。履歴は push＝戻るで元タブのURLへ戻れる）
 */
export function resolveIntent(currentPath: string, target: ViewKey): ViewIntent {
  if (isOverlayPath(currentPath)) return 'back'
  return viewOwning(currentPath)?.key === target ? 'reclick' : 'enter'
}

/**
 * SPA 内で「戻れる」かどうかを判定する。
 * `window.history.length > 2` は外部サイト経由の直リンク入場（history.length>2 かつ SPA idx=0）
 * でも true になるため使わない。React Router が history.state.idx に積む SPA 内遷移カウンタを使う。
 */
export function canGoBackInApp(): boolean {
  return (window.history.state?.idx ?? 0) > 0
}
