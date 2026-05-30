import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ThemeToggle } from '@/components/Settings/ThemeToggle'

export default function SettingsPage() {
  const location = useLocation()

  // 同じページでボタン再クリック時のスクロールリセット
  useEffect(() => {
    const timestamp = (location.state as any)?.timestamp
    if (timestamp && location.pathname === '/settings') {
      // スクロール位置をリセット
      const containers = document.querySelectorAll('main > div[style*="position: absolute"]')
      const settingsContainer = Array.from(containers).find(c =>
        (c as HTMLElement).style.display === 'block'
      ) as HTMLElement | undefined

      if (settingsContainer) {
        settingsContainer.scrollTo(0, 0)
      }
    }
  }, [location.state, location.pathname])

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">設定</h1>
        <p className="text-muted-foreground mt-1">
          アプリケーションの設定を管理します
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>テーマ</CardTitle>
          <CardDescription>
            表示テーマを選択してください。AUTOを選択すると、システムの設定に従います。
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ThemeToggle />
        </CardContent>
      </Card>
    </div>
  )
}
