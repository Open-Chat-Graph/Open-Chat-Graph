import { useLocation, useNavigate } from 'react-router-dom'
import { ChevronRight, Eye } from 'lucide-react'
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
      {/* 見出しは固定ヘッダ（タイトルバー）が「設定」を表示するので、ここでは繰り返さない */}

      {/* 分析ツールは独立タブ「分析」へ移設 */}
      <Card>
        <CardHeader>
          <CardTitle>通知</CardTitle>
          <CardDescription>
            アラートする部屋・キーワードや通知のしきい値を設定します。
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button
            variant="outline"
            className="w-full justify-start gap-3 h-auto py-3"
            onClick={() => navigate('/watch')}
            data-testid="settings-watch-link"
          >
            <Eye className="h-5 w-5 flex-shrink-0 text-muted-foreground" />
            <span className="min-w-0 flex-1 text-left">
              <span className="block truncate font-medium">アラート設定</span>
              <span className="block truncate text-xs font-normal text-muted-foreground">
                キーワード・部屋・マイリスト全体のアラート条件を設定
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
