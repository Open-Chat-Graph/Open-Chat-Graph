import { useLocation, useNavigate } from 'react-router-dom'
import { CalendarRange, ChevronRight, Eye, FlaskConical } from 'lucide-react'
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

      <Card>
        <CardHeader>
          <CardTitle>分析ツール</CardTitle>
          <CardDescription>
            キーワードと期間を指定して増減を分析します。
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-2">
          <Button
            variant="outline"
            className="w-full justify-start gap-3 h-auto py-3"
            onClick={() => navigate('/period-growth')}
            data-testid="settings-period-growth-link"
          >
            <CalendarRange className="h-5 w-5 flex-shrink-0 text-muted-foreground" />
            <span className="min-w-0 flex-1 text-left">
              <span className="block truncate font-medium">指定期間の増減ランキング</span>
              <span className="block truncate text-xs font-normal text-muted-foreground">
                キーワードと期間を指定して増減ランキングを表示
              </span>
            </span>
            <ChevronRight className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
          </Button>

          <Button
            variant="outline"
            className="w-full justify-start gap-3 h-auto py-3"
            onClick={() => navigate('/labs')}
            data-testid="settings-labs-link"
          >
            <FlaskConical className="h-5 w-5 flex-shrink-0 text-muted-foreground" />
            <span className="min-w-0 flex-1 text-left">
              <span className="block truncate font-medium">アクセス・流入ランキング</span>
              <span className="block truncate text-xs font-normal text-muted-foreground">
                本家ページのアクセス数・検索流入で部屋を並べる（SEO/初見向け）
              </span>
            </span>
            <ChevronRight className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
          </Button>
        </CardContent>
      </Card>

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
