import { Link, useLocation } from 'react-router-dom'
import { Search, List, Bell, Settings, LineChart } from 'lucide-react'
import { useNavigationHandler } from '@/hooks/useNavigationHandler'
import { useAlerts } from '@/hooks/useAlerts'
import { useMemo } from 'react'

export function MobileBottomNav() {
  const location = useLocation()
  const { navigateToSearch, navigateToMylist, navigateToSettings } = useNavigationHandler()
  const { unreadCount } = useAlerts()

  const navItems = useMemo(() => {
    return [
      { path: '/', icon: Search, label: '検索' },
      { path: '/mylist', icon: List, label: 'マイリスト' },
      { path: '/analysis', icon: LineChart, label: '分析' },
      { path: '/notifications', icon: Bell, label: '通知', badge: unreadCount },
      { path: '/settings', icon: Settings, label: '設定' },
    ]
  }, [unreadCount])

  return (
    <nav className="fixed bottom-0 left-0 right-0 z-nav bg-background border-t md:hidden">
      <div className="flex items-stretch justify-around h-[var(--bottomnav-h)]">
        {navItems.map((item) => {
          const Icon = item.icon
          // マイリストの場合は/mylist/*すべてでアクティブ判定
          const isActive = item.path.startsWith('/mylist')
            ? location.pathname === '/mylist' || location.pathname.startsWith('/mylist/')
            : location.pathname === item.path
          const badge = item.badge ?? 0

          return (
            <Link
              key={item.path}
              to={item.path}
              onClick={(e) => {
                // 各ページの専用ハンドラーを使用（通知は通常遷移）
                if (item.path === '/') {
                  navigateToSearch(e)
                } else if (item.path === '/mylist') {
                  navigateToMylist(e)
                } else if (item.path === '/settings') {
                  navigateToSettings(e)
                }
              }}
              className={`flex flex-1 flex-col items-center justify-center gap-1 py-1.5 transition-colors select-none ${
                isActive
                  ? 'text-primary font-medium'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              <span className="relative">
                <Icon className="h-[18px] w-[18px]" />
                {badge > 0 && (
                  <span className="absolute -right-2 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold leading-none text-primary-foreground">
                    {badge > 99 ? '99+' : badge}
                  </span>
                )}
              </span>
              <span className="text-[11px] leading-none">{item.label}</span>
            </Link>
          )
        })}
      </div>
    </nav>
  )
}
