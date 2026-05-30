import { BrowserRouter, Routes, Route, useLocation } from 'react-router-dom'
import { useEffect, useRef, Activity } from 'react'
import { DashboardLayout } from './components/Layout'
import SearchPage from './pages/SearchPage'
import MyListPage from './pages/MyListPage'
import DetailPage from './pages/DetailPage'
import SettingsPage from './pages/SettingsPage'

/**
 * 詳細ページかどうかを判定
 *
 * 詳細ページのみがオーバーレイとして表示される。
 * 他のページ（検索、マイリスト、設定）は常にDOMに存在する。
 *
 * @param pathname - 現在のパス
 * @returns 詳細ページの場合true
 */
function isDetailPage(pathname: string): boolean {
  return pathname.startsWith('/openchat/')
}

/**
 * アプリケーションのメインコンテンツ
 *
 * アーキテクチャ:
 * - 検索、マイリスト、設定ページは常にDOMに存在し、React 19.2の<Activity />で表示切替
 * - <Activity />のmode='hidden'は自動的にdisplay:noneを設定し、副作用も自動制御
 * - これにより、スクロール位置やフォーム状態が自動的に保持される
 * - 詳細ページのみがオーバーレイとして動的に表示される
 * - 詳細ページは常に新しくマウントされ、スクロール位置は0から始まる
 */
function AppContent() {
  const location = useLocation()
  const showDetailOverlay = isDetailPage(location.pathname)
  const overlayRef = useRef<HTMLDivElement>(null)

  // 詳細ページのオーバーレイ表示時にbodyスクロールを無効化
  useEffect(() => {
    if (showDetailOverlay) {
      document.body.style.overflow = 'hidden'
      // オーバーレイを常に0からスタート
      if (overlayRef.current) {
        overlayRef.current.scrollTo(0, 0)
      }
    } else {
      document.body.style.overflow = ''
    }

    return () => {
      document.body.style.overflow = ''
    }
  }, [showDetailOverlay])

  return (
    <DashboardLayout>
      {/*
        ベースページ（検索、マイリスト、設定）
        - すべて常にDOMに存在し、<Activity />で表示切替（mode='visible'/'hidden'）
        - 各ページは独自のスクロールコンテナを持つ（絶対配置）
        - スクロール位置、入力値、フォーム状態などすべて自動的に保持される
        - <Activity />が副作用（useEffect）の制御も自動で行う
        - 復元ロジック不要のシンプルなアーキテクチャ
      */}
      {/* 検索ページ: タイトルバー(48px) + 検索バー(56px) */}
      <Activity mode={location.pathname === '/' ? 'visible' : 'hidden'}>
        <div
          style={{
            position: 'absolute',
            left: 0,
            right: 0,
            overflowY: 'auto',
            overflowX: 'hidden',
            scrollbarGutter: 'stable'
          }}
          className="top-[104px] bottom-[49px] md:bottom-0 p-3 md:p-6"
        >
          <div className="space-y-6">
            <SearchPage />
          </div>
        </div>
      </Activity>

      {/* マイリストページ: タイトルバー(48px)のみ - スクロールなし（ページ内で制御） */}
      <Activity mode={location.pathname === '/mylist' || location.pathname.startsWith('/mylist/') ? 'visible' : 'hidden'}>
        <div
          style={{
            position: 'absolute',
            left: 0,
            right: 0,
          }}
          className="top-12 bottom-[49px] md:bottom-0"
        >
          <MyListPage />
        </div>
      </Activity>

      {/* 設定ページ: タイトルバー(48px)のみ */}
      <Activity mode={location.pathname === '/settings' ? 'visible' : 'hidden'}>
        <div
          style={{
            position: 'absolute',
            left: 0,
            right: 0,
            overflowY: 'auto',
            overflowX: 'hidden',
            scrollbarGutter: 'stable'
          }}
          className="top-12 bottom-[49px] md:bottom-0 p-3 md:p-6"
        >
          <div className="space-y-6">
            <SettingsPage />
          </div>
        </div>
      </Activity>

      {/*
        オーバーレイページ（詳細ページのみ）
        - ベースページの上に重ねて表示
        - fixed positioning で画面全体を覆う
        - 常に新しくマウントされ、スクロール位置は0から始まる
      */}
      {showDetailOverlay && (
        <div
          className="fixed inset-0 z-50 bg-background pt-12 md:pt-12"
          style={{ willChange: 'auto' }}
        >
          <div
            ref={overlayRef}
            className="p-3 md:p-6 md:ml-[max(56px,calc((100vw-756px)/2+56px))] lg:ml-[max(256px,calc((100vw-956px)/2+256px))] max-w-full md:max-w-[700px] h-full overflow-y-auto overflow-x-hidden md:border-r"
            style={{ scrollbarGutter: 'stable' }}
          >
            <DetailPage />
          </div>
        </div>
      )}
    </DashboardLayout>
  )
}

function App() {
  // 開発環境では '/', 本番環境では '/alpha'
  const basename = import.meta.env.DEV ? '/' : '/alpha'

  return (
    <BrowserRouter basename={basename}>
      <Routes>
        <Route path="/" element={<AppContent />} />
        <Route path="/mylist" element={<AppContent />} />
        <Route path="/mylist/:folderId" element={<AppContent />} />
        <Route path="/settings" element={<AppContent />} />
        <Route path="/openchat/:id" element={<AppContent />} />
        <Route path="*" element={<AppContent />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
