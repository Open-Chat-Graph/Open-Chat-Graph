import { useState, useMemo, useRef, useEffect } from 'react'
import type { ReactNode } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { Search, BarChart3, FolderOpen, X as XIcon, ArrowLeft, Settings, Bell } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { MobileBottomNav } from './MobileBottomNav'
import { HeaderSearchBar } from './HeaderSearchBar'
import { cn } from '@/lib/utils'
import { useNavigationHandler } from '@/hooks/useNavigationHandler'
import { useAlerts } from '@/hooks/useAlerts'
import { usePageTitle } from '@/hooks/usePageTitle'
import { UNIFIED_SORT_OPTIONS } from '@/lib/sort-options'

interface DashboardLayoutProps {
  children: ReactNode
}

export default function DashboardLayout({ children }: DashboardLayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const location = useLocation()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { navigateToSearch, navigateToMylist, navigateToSettings } = useNavigationHandler()
  const { pageTitle, detailTitle } = usePageTitle()
  const { unreadCount } = useAlerts()

  // 固定ヘッダー（タイトルバー＋検索バー）の実高さを測って CSS 変数に反映する。
  // 検索バーの行数が増減（カテゴリ行の追加など）してもコンテンツが潜らないようにする。
  const headerRef = useRef<HTMLDivElement>(null)
  useEffect(() => {
    const el = headerRef.current
    if (!el) return
    const apply = () => {
      document.documentElement.style.setProperty('--header-searchbar-h', `${el.offsetHeight}px`)
    }
    apply()
    const ro = new ResizeObserver(apply)
    ro.observe(el)
    return () => ro.disconnect()
  }, [location.pathname])

  // ナビゲーションメニュー（マイリストは常に/mylist固定）
  const navigation = useMemo(() => {
    return [
      { name: '検索', href: '/', icon: Search, badge: 0 },
      { name: 'マイリスト', href: '/mylist', icon: FolderOpen, badge: 0 },
      { name: '通知', href: '/notifications', icon: Bell, badge: unreadCount },
      { name: '設定', href: '/settings', icon: Settings, badge: 0 },
    ]
  }, [unreadCount])

  // 詳細ページ判定（戻るボタンを表示）
  const isDetailPage = location.pathname.startsWith('/openchat/')

  // 戻るボタンの動作: 履歴があればブラウザバック、なければトップページへ
  const handleBack = () => {
    if (window.history.length > 2) {
      navigate(-1)
    } else {
      navigate('/')
    }
  }

  return (
    <div className="flex min-h-screen bg-background">
      {/* モバイルサイドバー背景（タップで閉じる） */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-header bg-black/50 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      <div className="flex w-full">
        {/**
         * サイドバー
         * - モバイル: 右からスライドイン (w-64)
         * - タブレット: 左固定アイコンのみ (w-14)
         * - デスクトップ: 左固定フル表示 (w-64)
         */}
        <aside
          className={cn(
            "fixed inset-y-0 right-0 z-sidebar w-64 transform bg-card border-l transition-transform duration-300 ease-in-out md:fixed md:translate-x-0 md:w-14 md:h-screen md:right-auto md:left-[var(--sidebar-left-md)] md:border-l-0 md:border-r lg:w-64 lg:left-[var(--sidebar-left-lg)]",
            sidebarOpen ? "translate-x-0" : "translate-x-full md:translate-x-0"
          )}
        >
          <div className="flex h-screen flex-col">
            {/* ロゴ (h-12: ヘッダーの高さと一致) */}
            <div className="flex h-12 items-center justify-between border-b px-6 md:px-0 md:justify-center lg:px-6 lg:justify-between select-none">
              <div className="flex items-center gap-2 md:justify-center lg:justify-start">
                <BarChart3 className="h-6 w-6 text-primary" />
                <span className="font-display text-lg font-bold tracking-tight md:hidden lg:inline">オプチャグラフα</span>
              </div>
              <Button
                variant="ghost"
                size="icon"
                className="md:hidden"
                onClick={() => setSidebarOpen(false)}
              >
                <XIcon className="h-5 w-5" />
              </Button>
            </div>

            {/* ナビゲーション */}
            <nav className="flex-1 space-y-1 p-4 md:p-2 lg:p-4">
              {navigation.map((item) => {
                // マイリストの場合は/mylist/*すべてでアクティブ判定
                const isActive = item.href.startsWith('/mylist')
                  ? location.pathname === '/mylist' || location.pathname.startsWith('/mylist/')
                  : location.pathname === item.href
                return (
                  <Link
                    key={item.name}
                    to={item.href}
                    className={cn(
                      "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors select-none md:justify-center md:px-2 lg:justify-start lg:px-3",
                      isActive
                        ? "bg-primary text-primary-foreground"
                        : "text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                    )}
                    onClick={(e) => {
                      setSidebarOpen(false)

                      // 各ページの専用ハンドラーを使用
                      if (item.href === '/') {
                        navigateToSearch(e)
                      } else if (item.href === '/mylist') {
                        navigateToMylist(e)
                      } else if (item.href === '/settings') {
                        navigateToSettings(e)
                      }
                    }}
                    title={item.name}
                  >
                    <span className="relative flex-shrink-0">
                      <item.icon className="h-5 w-5" />
                      {item.badge > 0 && (
                        <span className="absolute -right-2 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold leading-none text-primary-foreground">
                          {item.badge > 99 ? '99+' : item.badge}
                        </span>
                      )}
                    </span>
                    <span className="md:hidden lg:inline">{item.name}</span>
                  </Link>
                )
              })}
            </nav>

            {/* フッター */}
            <div className="border-t p-4 md:hidden lg:block select-none">
              <p className="text-xs text-muted-foreground">
                統計監視ツール v0.1
              </p>
            </div>
          </div>
        </aside>

        {/**
         * メインコンテンツエリア
         * - モバイル: 全幅、スクロール可能
         * - タブレット以上: 700px固定幅、中央寄せ (calc利用)
         * - デスクトップ: 700px固定幅、中央寄せ (calc利用)
         */}
        <div className="flex flex-1 flex-col overflow-x-hidden md:w-[var(--content-w)] md:flex-none md:border-r md:overflow-y-auto md:ml-[var(--main-offset-md)] lg:ml-[var(--main-offset-lg)]">
          {/**
           * ヘッダー（タイトル + 検索バー）
           * - モバイル: 画面全幅で固定、スクロールで非表示
           * - タブレット以上: サイドバー右側に固定 (left-14/left-64)、700px幅
           */}
          {/* アプリ固定ヘッダ（タイトル＋検索バー）。層=header(60)。
              実高さは headerRef を ResizeObserver で測り --header-searchbar-h に反映し、
              keep-aliveパネルはこの値を top に使う（決め打ち禁止）。 */}
          <div ref={headerRef} className="fixed top-0 left-0 right-0 z-header bg-card border-b md:left-[var(--main-offset-md)] lg:left-[var(--main-offset-lg)] md:w-[var(--content-w)] md:border-r">
            {/* タイトルバー (h-12: 48px) */}
            <header className="flex h-12 items-center justify-between px-4 select-none">
              <div className="flex items-center gap-2 flex-1 min-w-0">
                {isDetailPage && (
                  <Button
                    variant="ghost"
                    size="icon"
                    className="flex-shrink-0"
                    onClick={handleBack}
                  >
                    <ArrowLeft className="h-5 w-5" />
                  </Button>
                )}
                {/* 検索結果の場合はキーワード部分のみtruncate */}
                {location.pathname === '/' && searchParams.get('q') ? (
                  <span className="text-base font-semibold flex items-center min-w-0">
                    <span>「</span>
                    <span className="truncate">{searchParams.get('q')}</span>
                    <span className="flex-shrink-0">」の検索結果 - {
                      UNIFIED_SORT_OPTIONS.find(
                        opt => opt.value === (searchParams.get('sort') || 'member') && opt.order === (searchParams.get('order') || 'desc')
                      )?.label ?? '人数降順'
                    }</span>
                  </span>
                ) : detailTitle ? (
                  // 詳細ページ: "ChatName (member)" 形式で人数は省略しない
                  <span className="text-base font-semibold flex items-center min-w-0">
                    <span className="truncate">{detailTitle.name}</span>
                    <span className="flex-shrink-0 ml-1">({detailTitle.member.toLocaleString()})</span>
                  </span>
                ) : (
                  <span className="text-base font-semibold truncate">{pageTitle}</span>
                )}
              </div>
            </header>

            {/* 検索バー（検索ページのみ、約56pxの高さ） */}
            {location.pathname === '/' && <HeaderSearchBar />}
          </div>

          {/**
           * ページコンテンツ
           * - モバイル: 下部ナビ分の余白 (pb-12)
           * - 絶対配置コンテナが独自にtop位置を設定
           */}
          <main className="flex-1 pb-12 md:pb-0 overflow-x-hidden relative">
            {children}
          </main>

          {/* モバイル下部ナビゲーション */}
          <MobileBottomNav />
        </div>
      </div>
    </div>
  )
}
