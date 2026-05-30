import { Link, useLocation } from 'react-router-dom'
import { Search, List, Bell, Settings } from 'lucide-react'
import { useNavigationHandler } from '@/hooks/useNavigationHandler'
import { useGrowthNotifications } from '@/hooks/useGrowthNotifications'
import { useMemo } from 'react'

export function MobileBottomNav() {
  const location = useLocation()
  const { navigateToSearch, navigateToMylist, navigateToSettings } = useNavigationHandler()
  const { unseenCount } = useGrowthNotifications()

  const navItems = useMemo(() => {
    return [
      { path: '/', icon: Search, label: '検索' },
      { path: '/mylist', icon: List, label: 'マイリスト' },
      { path: '/notifications', icon: Bell, label: '通知', badge: unseenCount },
      { path: '/settings', icon: Settings, label: '設定' },
    ]
  }, [unseenCount])

  return (
    <nav className="fixed bottom-0 left-0 right-0 z-50 bg-background border-t md:hidden">
      <div className="flex items-center justify-around h-12">
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
              className={`flex flex-col items-center justify-center flex-1 gap-1 transition-colors select-none ${
                isActive
                  ? 'text-primary font-medium'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              <span className="relative">
                <Icon className="h-6 w-6" />
                {badge > 0 && (
                  <span className="absolute -right-2 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold leading-none text-primary-foreground">
                    {badge > 99 ? '99+' : badge}
                  </span>
                )}
              </span>
              <span className="text-xs">{item.label}</span>
            </Link>
          )
        })}
      </div>
    </nav>
  )
}
