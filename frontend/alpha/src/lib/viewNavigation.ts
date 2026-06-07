import { STORAGE_KEYS } from './storage-keys'

/**
 * 画面表示状態カーネル（唯一の定義元）。
 *
 * タブの「状態保持／同タブ再押下でリセット／オーバーレイを閉じる／状態復元」は
 * これまで App.tsx / useNavigationHandler / useScrollToTopOnReclick / 各ページに
 * バラバラに実装されていた。ここに宣言的なビュー表とインテント正規化を集約し、
 * `useViewNavigation()`（hooks/useViewNavigation.ts）だけがナビを発行する。
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
  /** 再クリックで戻る基準パス */
  rootPath: string
  /** rootPath で表示されるパネル（reset でこのパネルを強制再マウントする） */
  rootPanelKey: string
  /** この pathname がこのビューに属するか（サブルート含む） */
  owns: (pathname: string) => boolean
  /** enter 時に最後のサブ画面を復元するか（分析だけ true） */
  remembersSubRoute?: boolean
}

export const VIEWS: ViewDef[] = [
  { key: 'search', rootPath: '/', rootPanelKey: 'search', owns: (p) => p === '/' },
  {
    key: 'mylist',
    rootPath: '/mylist',
    rootPanelKey: 'mylist',
    owns: (p) => p === '/mylist' || p.startsWith('/mylist/'),
  },
  {
    key: 'analysis',
    rootPath: '/analysis',
    rootPanelKey: 'analysis',
    owns: (p) => p === '/analysis' || p === '/period-growth' || p === '/labs',
    remembersSubRoute: true,
  },
  { key: 'notifications', rootPath: '/notifications', rootPanelKey: 'notifications', owns: (p) => p === '/notifications' },
  { key: 'settings', rootPath: '/settings', rootPanelKey: 'settings', owns: (p) => p === '/settings' },
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
 * - すでに対象ビューがアクティブ  → reclick（ルートへ戻し強制再マウント＋トップへ）
 * - それ以外                      → enter（記憶した状態を復元して入る）
 */
export function resolveIntent(currentPath: string, target: ViewKey): ViewIntent {
  if (isOverlayPath(currentPath)) return 'back'
  return viewOwning(currentPath)?.key === target ? 'reclick' : 'enter'
}

/**
 * enter 時に飛ばす先（記憶した状態を復元）。無ければ rootPath。
 * 検索＝最後の検索クエリ／マイリスト＝最後のフォルダ／分析＝最後のサブ画面。
 */
export function resolveEnterPath(key: ViewKey): string {
  switch (key) {
    case 'search': {
      const saved = sessionStorage.getItem(STORAGE_KEYS.searchQuery)
      return saved ? `/?${saved}` : '/'
    }
    case 'mylist': {
      const folder = sessionStorage.getItem(STORAGE_KEYS.myListCurrentFolder)
      return folder ? `/mylist/${folder}` : '/mylist'
    }
    case 'analysis': {
      const sub = sessionStorage.getItem(STORAGE_KEYS.analysisLastSub)
      return sub || '/analysis'
    }
    default:
      return VIEWS.find((v) => v.key === key)!.rootPath
  }
}

/**
 * SPA 内で「戻れる」かどうかを判定する。
 * `window.history.length > 2` は外部サイト経由の直リンク入場（history.length>2 かつ SPA idx=0）
 * でも true になるため使わない。React Router が history.state.idx に積む SPA 内遷移カウンタを使う。
 */
export function canGoBackInApp(): boolean {
  return (window.history.state?.idx ?? 0) > 0
}

/**
 * 現在地に応じて「記録すべきビュー状態」を sessionStorage に保存する（enter 復元用）。
 * App 直下の effect から location 変化のたびに呼ぶ。マイリストのフォルダ記憶は
 * useFolderNavigation 側が担うのでここでは扱わない。
 */
export function recordViewState(pathname: string, search: string): void {
  if (pathname === '/') {
    const params = new URLSearchParams(search)
    if (params.get('q')) sessionStorage.setItem(STORAGE_KEYS.searchQuery, params.toString())
  } else if (pathname === '/period-growth' || pathname === '/labs') {
    sessionStorage.setItem(STORAGE_KEYS.analysisLastSub, pathname + search)
  }
}
