import { useLocation, useNavigate } from 'react-router-dom'
import { CalendarRange, ChevronRight } from 'lucide-react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { ThemeToggle } from '@/components/Settings/ThemeToggle'
import { useScrollToTopOnReclick } from '@/hooks/useScrollToTopOnReclick'

export default function SettingsPage() {
  const location = useLocation()
  const navigate = useNavigate()
  // 設定タブ再クリック時に先頭へスクロール
  useScrollToTopOnReclick(location.pathname === '/settings')

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
          <CardTitle>分析ツール</CardTitle>
          <CardDescription>
            上級者向けの分析ビュー。広告やSEOを気にせず深掘りできます。
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button
            variant="outline"
            className="w-full justify-start gap-3 h-auto py-3"
            onClick={() => navigate('/period-growth')}
            data-testid="settings-period-growth-link"
          >
            <CalendarRange className="h-5 w-5 flex-shrink-0 text-muted-foreground" />
            <span className="flex-1 text-left">
              <span className="block font-medium">任意のN日増減</span>
              <span className="block text-xs font-normal text-muted-foreground">
                キーワードと期間を指定して増減ランキングを表示
              </span>
            </span>
            <ChevronRight className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
          </Button>
        </CardContent>
      </Card>

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
