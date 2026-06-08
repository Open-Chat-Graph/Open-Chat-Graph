import { Link, useLocation } from 'react-router-dom'
import { Search, List, Bell, Settings, FlaskConical } from 'lucide-react'
import { useViewNavigation } from '@/hooks/useViewNavigation'
import type { ViewKey } from '@/lib/viewNavigation'
import { useAlerts } from '@/hooks/useAlerts'
import { useMemo } from 'react'

export function MobileBottomNav() {
  const location = useLocation()
  const { goToView } = useViewNavigation()
  const { unreadCount } = useAlerts()

  const navItems = useMemo(() => {
    return [
      { path: '/', view: 'search' as ViewKey, icon: Search, label: '検索' },
      { path: '/mylist', view: 'mylist' as ViewKey, icon: List, label: 'マイリスト' },
      { path: '/notifications', view: 'notifications' as ViewKey, icon: Bell, label: '通知', badge: unreadCount },
      { path: '/settings', view: 'settings' as ViewKey, icon: Settings, label: '設定' },
      { path: '/analysis', view: 'analysis' as ViewKey, icon: FlaskConical, label: 'Labs' },
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
              onClick={(e) => goToView(item.view, e)}
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
