import { BrowserRouter, Routes, Route, useLocation } from 'react-router-dom'
import { Activity } from 'react'
import type { ReactNode } from 'react'
import { DashboardLayout } from './components/Layout'
import { LayoutProvider } from './contexts/layout-context'
import { DetailOverlay } from './components/Layout/DetailOverlay'
import { cn } from './lib/utils'
import SearchPage from './pages/SearchPage'
import MyListPage from './pages/MyListPage'
import DetailPage from './pages/DetailPage'
import SettingsPage from './pages/SettingsPage'
import NotificationsPage from './pages/NotificationsPage'
import PeriodGrowthPage from './pages/PeriodGrowthPage'
import WatchSettingsPage from './pages/WatchSettingsPage'
import LabsPage from './pages/LabsPage'
import FolderChartPage from './pages/FolderChartPage'

/**
 * 常駐（keep-alive）するベースページの定義。
 *
 * 検索/マイリスト/設定/通知は常に DOM に置き、React 19 の <Activity> で表示を切り替える。
 * これによりタブを行き来してもスクロール位置や入力状態が保持される。
 * 詳細ページだけは別扱いで、上に被せるオーバーレイとして都度マウントする。
 */
interface KeepAlivePage {
  key: string
  isActive: (pathname: string) => boolean
  element: ReactNode
  /** ヘッダー(48px)＋検索バー(56px)の下から始めるか、タイトルバー(48px)の下からか */
  top: 'header-and-searchbar' | 'title'
  /** パネル自身がスクロールするか（false の場合はページ側でスクロール制御） */
  scrollable: boolean
}

const KEEP_ALIVE_PAGES: KeepAlivePage[] = [
  {
    key: 'search',
    isActive: (p) => p === '/',
    element: <SearchPage />,
    top: 'header-and-searchbar',
    scrollable: true,
  },
  {
    key: 'mylist',
    isActive: (p) => p === '/mylist' || p.startsWith('/mylist/'),
    element: <MyListPage />,
    top: 'title',
    scrollable: false,
  },
  {
    key: 'notifications',
    isActive: (p) => p === '/notifications',
    element: <NotificationsPage />,
    top: 'title',
    scrollable: true,
  },
  {
    key: 'period-growth',
    isActive: (p) => p === '/period-growth',
    element: <PeriodGrowthPage />,
    top: 'title',
    scrollable: true,
  },
  {
    key: 'settings',
    isActive: (p) => p === '/settings',
    element: <SettingsPage />,
    top: 'title',
    scrollable: true,
  },
  {
    key: 'watch',
    isActive: (p) => p === '/watch',
    element: <WatchSettingsPage />,
    top: 'title',
    scrollable: true,
  },
  {
    key: 'labs',
    isActive: (p) => p === '/labs',
    element: <LabsPage />,
    top: 'title',
    scrollable: true,
  },
]

function KeepAlivePanel({ page, active }: { page: KeepAlivePage; active: boolean }) {
  return (
    <Activity mode={active ? 'visible' : 'hidden'}>
      <div
        style={{
          position: 'absolute',
          left: 0,
          right: 0,
          ...(page.scrollable
            ? { overflowY: 'auto', overflowX: 'hidden', scrollbarGutter: 'stable' }
            : {}),
        }}
        className={cn(
          page.top === 'header-and-searchbar' ? 'top-[var(--header-searchbar-h)]' : 'top-12',
          'bottom-[var(--bottomnav-h)] md:bottom-0',
          page.scrollable && 'p-3 md:p-6',
        )}
      >
        {page.scrollable ? <div className="space-y-6">{page.element}</div> : page.element}
      </div>
    </Activity>
  )
}

function isDetailPage(pathname: string): boolean {
  return pathname.startsWith('/openchat/')
}

// /mylist/:folderId/chart … フォルダ統合グラフ（マイリストの上に被せるオーバーレイ）
function isFolderChartPage(pathname: string): boolean {
  return /^\/mylist\/[^/]+\/chart$/.test(pathname)
}

function AppContent() {
  const location = useLocation()
  const showDetail = isDetailPage(location.pathname)
  const showFolderChart = isFolderChartPage(location.pathname)

  return (
    <LayoutProvider>
      <DashboardLayout>
        {KEEP_ALIVE_PAGES.map((page) => (
          <KeepAlivePanel key={page.key} page={page} active={page.isActive(location.pathname)} />
        ))}

        {/* 詳細ページはベースページの上に被せるオーバーレイ（都度マウント） */}
        {showDetail && (
          <DetailOverlay>
            <DetailPage />
          </DetailOverlay>
        )}

        {/* フォルダ統合グラフもオーバーレイ（マイリストの上に被せる） */}
        {showFolderChart && (
          <DetailOverlay>
            <FolderChartPage />
          </DetailOverlay>
        )}
      </DashboardLayout>
    </LayoutProvider>
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
        <Route path="/mylist/:folderId/chart" element={<AppContent />} />
        <Route path="/settings" element={<AppContent />} />
        <Route path="/notifications" element={<AppContent />} />
        <Route path="/period-growth" element={<AppContent />} />
        <Route path="/watch" element={<AppContent />} />
        <Route path="/labs" element={<AppContent />} />
        <Route path="/openchat/:id" element={<AppContent />} />
        <Route path="/openchat/:id/ranking-history" element={<AppContent />} />
        <Route path="/openchat/:id/image" element={<AppContent />} />
        <Route path="*" element={<AppContent />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
