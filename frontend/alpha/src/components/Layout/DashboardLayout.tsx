import { useState, useEffect, useMemo, useCallback } from 'react'
import type { ReactNode } from 'react'
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { Search, BarChart3, FolderOpen, X as XIcon, ArrowLeft, Settings, ArrowUpDown, Check, Bell } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { MobileBottomNav } from './MobileBottomNav'
import { cn } from '@/lib/utils'
import { useNavigationHandler } from '@/hooks/useNavigationHandler'
import { useGrowthNotifications } from '@/hooks/useGrowthNotifications'
import { usePageTitle } from '@/hooks/usePageTitle'
import { useLayout } from '@/contexts/layout-context'
import { UNIFIED_SORT_OPTIONS } from '@/lib/sort-options'

interface DashboardLayoutProps {
  children: ReactNode
}

export default function DashboardLayout({ children }: DashboardLayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [mobileSearchValue, setMobileSearchValue] = useState('')
  const [isSearchFocused, setIsSearchFocused] = useState(false)
  const location = useLocation()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const { navigateToSearch, navigateToMylist, navigateToSettings } = useNavigationHandler()
  const { triggerSearch } = useLayout()
  const { pageTitle, detailTitle } = usePageTitle()
  const { unseenCount } = useGrowthNotifications()

  // ナビゲーションメニュー（マイリストは常に/mylist固定）
  const navigation = useMemo(() => {
    return [
      { name: '検索', href: '/', icon: Search, badge: 0 },
      { name: 'マイリスト', href: '/mylist', icon: FolderOpen, badge: 0 },
      { name: '通知', href: '/notifications', icon: Bell, badge: unseenCount },
      { name: '設定', href: '/settings', icon: Settings, badge: 0 },
    ]
  }, [unseenCount])

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

  // 検索バーの値をURLパラメータと同期
  useEffect(() => {
    const q = searchParams.get('q') || ''
    setMobileSearchValue(q)
  }, [searchParams])

  // 検索実行ハンドラー
  const executeSearch = useCallback(() => {
    if (mobileSearchValue.trim()) {
      setSearchParams({ q: mobileSearchValue.trim() })
      // 同じキーワードでも再フェッチさせるため Context のシグナルを bump
      triggerSearch()
    } else {
      setSearchParams({})
    }
  }, [mobileSearchValue, setSearchParams, triggerSearch])

  // iOSのSafariで検索バー入力中のタップを正しく処理する
  useEffect(() => {
    if (!isSearchFocused) return

    const handleClick = (e: MouseEvent) => {
      const target = e.target as HTMLElement
      // 検索バー自体とその子要素のクリックは許可
      const searchBar = document.querySelector('input[placeholder="キーワードを入力..."]')
      if (searchBar?.contains(target) || target.closest('button[aria-label="クリア"]')) {
        return
      }
      // その他のクリックはブロック（IME確定処理を優先）
      e.preventDefault()
      e.stopPropagation()
    }

    // キャプチャフェーズでイベントを捕捉
    document.addEventListener('click', handleClick, true)

    return () => {
      document.removeEventListener('click', handleClick, true)
    }
  }, [isSearchFocused])

  return (
    <div className="flex min-h-screen bg-background">
      {/* モバイルサイドバー背景（タップで閉じる） */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-[60] bg-black/50 lg:hidden"
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
            "fixed inset-y-0 right-0 z-[70] w-64 transform bg-card border-l transition-transform duration-300 ease-in-out md:fixed md:translate-x-0 md:w-14 md:h-screen md:right-auto md:left-[var(--sidebar-left-md)] md:border-l-0 md:border-r lg:w-64 lg:left-[var(--sidebar-left-lg)]",
            sidebarOpen ? "translate-x-0" : "translate-x-full md:translate-x-0"
          )}
        >
          <div className="flex h-screen flex-col">
            {/* ロゴ (h-12: ヘッダーの高さと一致) */}
            <div className="flex h-12 items-center justify-between border-b px-6 md:px-0 md:justify-center lg:px-6 lg:justify-between select-none">
              <div className="flex items-center gap-2 md:justify-center lg:justify-start">
                <BarChart3 className="h-6 w-6 text-primary" />
                <span className="text-lg font-semibold md:hidden lg:inline">オプチャグラフα</span>
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
          <div className="fixed top-0 left-0 right-0 z-[60] bg-card border-b md:left-[var(--main-offset-md)] lg:left-[var(--main-offset-lg)] md:w-[var(--content-w)] md:border-r">
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
            {location.pathname === '/' && (
              <div className="border-t md:border-t-0 md:border-b bg-background px-3 py-2 md:px-4">
                <div className="flex gap-2">
                  <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                      type="text"
                      placeholder="キーワードを入力..."
                      className="w-full h-10 pl-10 pr-12 rounded-md border border-input bg-background text-base"
                      value={mobileSearchValue}
                      onChange={(e) => {
                        setMobileSearchValue(e.target.value)
                      }}
                      onFocus={() => setIsSearchFocused(true)}
                      onBlur={() => {
                        // iOSのSafariでIME確定後のタップを正しく処理するため、少し遅延させる
                        setTimeout(() => setIsSearchFocused(false), 100)
                      }}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault()
                          executeSearch()
                          // キーボードを閉じる
                          e.currentTarget.blur()
                        }
                      }}
                    />
                    {mobileSearchValue && (
                      <button
                        type="button"
                        onClick={() => setMobileSearchValue('')}
                        className="absolute right-1 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors p-2 rounded-md hover:bg-accent w-8 h-8 flex items-center justify-center"
                        aria-label="クリア"
                      >
                        <XIcon className="h-5 w-5" />
                      </button>
                    )}
                  </div>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button
                        variant="outline"
                        className="h-10 px-3 flex-shrink-0"
                        data-testid="toolbar-sort-dropdown-trigger"
                      >
                        <ArrowUpDown className="h-4 w-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56" data-testid="toolbar-sort-dropdown-content">
                      {UNIFIED_SORT_OPTIONS.map((option) => {
                        const currentSort = searchParams.get('sort') || 'member'
                        const currentOrder = searchParams.get('order') || 'desc'
                        const isSelected = option.value === currentSort && option.order === currentOrder
                        return (
                          <DropdownMenuItem
                            key={`${option.value}-${option.order}`}
                            onClick={() => {
                              const newParams = new URLSearchParams(searchParams)
                              newParams.set('sort', option.value)
                              newParams.set('order', option.order)
                              setSearchParams(newParams)
                            }}
                            data-testid={`toolbar-sort-option-${option.value}-${option.order}`}
                          >
                            <div className="flex items-center gap-2 w-full">
                              <Check className={`h-4 w-4 ${isSelected ? 'opacity-100' : 'opacity-0'}`} />
                              <span>{option.label}</span>
                            </div>
                          </DropdownMenuItem>
                        )
                      })}
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </div>
            )}
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
