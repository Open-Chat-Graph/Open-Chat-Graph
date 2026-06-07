import { memo } from 'react'
import { useNavigate } from 'react-router-dom'
import { CalendarRange, ChevronRight, FlaskConical } from 'lucide-react'

/**
 * 分析ツールのランディング（独立タブ `/analysis`）。
 * 各分析ツール（指定期間の増減ランキング / アクセス・流入ランキング）への入口を並べる。
 * 見出しは固定タイトルバーが「分析」を表示するので、ここでは説明＋入口のみ。
 */
const tools = [
  {
    to: '/period-growth',
    icon: CalendarRange,
    title: '指定期間の増減ランキング',
    desc: 'キーワードと期間を指定して、その期間の増減で部屋を並べる',
    testid: 'analysis-period-growth-link',
  },
  {
    to: '/labs',
    icon: FlaskConical,
    title: 'アクセス・流入ランキング',
    desc: 'Googleからの流入とGoogleアナリティクスで部屋を分析する',
    testid: 'analysis-labs-link',
  },
]

const AnalysisPage = memo(() => {
  const navigate = useNavigate()

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">
        オプチャグラフ本体のデータを使った、αならではの分析ツール。
      </p>

      <div className="space-y-2">
        {tools.map((t) => (
          <button
            key={t.to}
            type="button"
            onClick={() => navigate(t.to)}
            className="flex w-full items-center gap-3 rounded-lg border bg-card px-4 py-3 text-left transition-colors hover:bg-accent"
            data-testid={t.testid}
          >
            <span className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
              <t.icon className="h-5 w-5" />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block text-sm font-medium">{t.title}</span>
              {/* モバイルで途切れて読めないため truncate せず2行まで折り返す */}
              <span className="block text-xs leading-snug text-muted-foreground line-clamp-2">{t.desc}</span>
            </span>
            <ChevronRight className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
          </button>
        ))}
      </div>
    </div>
  )
})

AnalysisPage.displayName = 'AnalysisPage'

export default AnalysisPage
