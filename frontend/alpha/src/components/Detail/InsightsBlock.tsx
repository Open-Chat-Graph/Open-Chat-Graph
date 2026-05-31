import { memo } from 'react'
import useSWR from 'swr'
import {
  Sparkles,
  TrendingUp,
  LineChart,
  Trophy,
  Layers,
  PieChart,
  Ruler,
  CalendarClock,
  Activity,
  type LucideIcon,
} from 'lucide-react'
import { alphaApi } from '@/api/alpha'
import { cn } from '@/lib/utils'
import type { InsightsResponse, InsightItem } from '@/types/api'

interface InsightsBlockProps {
  openChatId: number
}

/**
 * 高次の考察ブロック。
 *
 * サーバは「グラフや数字を見れば一目で分かること」は返さず、一目で分からない
 * 洞察だけを返す（空配列のこともある）。このブロックは詳細画面の主役（グラフ）を
 * 邪魔しない補助要素として、洞察が在るときだけ静かに現れる。
 * ローディング/エラー/空はすべて無表示で、注意を奪わない。
 */

// type ごとのアイコンとアクセント色。text を主役にするため装飾は控えめに。
type InsightStyle = {
  icon: LucideIcon
  // ドット/アイコンのアクセント色（light/dark）
  accent: string
  label: string
}

const INSIGHT_STYLES: Record<string, InsightStyle> = {
  // 成長率の順位（同カテゴリ内などで伸びが際立つ）
  growth_rank: { icon: TrendingUp, accent: 'text-emerald-600 dark:text-emerald-400', label: '成長の勢い' },
  // 順位の推移トレンド（じわじわ上がっている/下がっている）
  position_trend: { icon: LineChart, accent: 'text-sky-600 dark:text-sky-400', label: '順位の流れ' },
  // 自己ベスト順位
  best_rank: { icon: Trophy, accent: 'text-amber-600 dark:text-amber-400', label: '自己ベスト' },
  // カテゴリ内の順位
  category_rank: { icon: Layers, accent: 'text-indigo-600 dark:text-indigo-400', label: 'カテゴリ内順位' },
  // カテゴリ内シェア
  category_share: { icon: PieChart, accent: 'text-violet-600 dark:text-violet-400', label: 'カテゴリ内シェア' },
  // カテゴリ内での規模感
  category_scale: { icon: Ruler, accent: 'text-teal-600 dark:text-teal-400', label: 'カテゴリ内の規模' },
  // 単日の記録（過去最大の伸び等）
  record_single_day: { icon: CalendarClock, accent: 'text-rose-600 dark:text-rose-400', label: '記録的な1日' },
  // 増加ペースの異常検知
  pace_anomaly: { icon: Activity, accent: 'text-orange-600 dark:text-orange-400', label: 'ペースの変化' },
}

const DEFAULT_STYLE: InsightStyle = {
  icon: Sparkles,
  accent: 'text-primary',
  label: '考察',
}

function styleFor(type: string): InsightStyle {
  return INSIGHT_STYLES[type] ?? DEFAULT_STYLE
}

/** "2026-05-31 12:34:56" / ISO → "2026/05/31 12:34" を試み、無理なら原文を返す */
function formatGeneratedAt(s: string): string {
  if (!s) return ''
  const normalized = s.includes('T') ? s : s.replace(' ', 'T')
  const d = new Date(normalized)
  if (isNaN(d.getTime())) return s
  const p = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}/${p(d.getMonth() + 1)}/${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}

function InsightRow({ item }: { item: InsightItem }) {
  const { icon: Icon, accent, label } = styleFor(item.type)
  return (
    <li className="flex gap-3 px-4 py-3">
      {/* type アイコン：淡いリングの中に。色で種類を、形でカテゴリを示す */}
      <span
        className={cn(
          'mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-muted/60',
          accent,
        )}
        aria-hidden
      >
        <Icon className="h-4 w-4" />
      </span>
      <div className="min-w-0 flex-1 space-y-0.5">
        {/* 見出し階層: 帯ヘッダ(text-sm font-bold) を基準に、行ラベルはその一段下として
            text-xs font-semibold で揃える。text(本文)が主役なので強くしすぎない。 */}
        <span className={cn('text-xs font-semibold tracking-wide', accent)}>{label}</span>
        {/* text を主役に。読み物として落ち着いた行間で */}
        <p className="text-sm leading-relaxed text-foreground">{item.text}</p>
      </div>
    </li>
  )
}

export const InsightsBlock = memo(({ openChatId }: InsightsBlockProps) => {
  // 遅延取得。詳細の主役はグラフなので、考察は後追いで静かに現れればよい。
  const { data, error, isLoading } = useSWR<InsightsResponse>(
    ['insights', openChatId],
    () => alphaApi.getInsights(openChatId),
    { revalidateOnFocus: false, revalidateOnReconnect: false },
  )

  const insights = data?.insights ?? []

  // ローディング/エラー/空はすべて無表示。補助ブロックなので存在を主張しない。
  if (isLoading || error || insights.length === 0) return null

  return (
    <section
      className="max-w-[var(--content-w)] mx-auto overflow-hidden rounded-xl border bg-card shadow-sm"
      aria-label="高次の考察"
    >
      {/* 見出し帯：これは“数字の言い換え”ではなく分析（メタ）だと示す控えめなヘッダー */}
      <header className="flex items-center gap-2 border-b bg-muted/30 px-4 py-2.5">
        <Sparkles className="h-4 w-4 flex-shrink-0 text-primary" />
        <h2 className="text-sm font-bold">考察</h2>
        <span className="text-[11px] font-normal text-muted-foreground">グラフだけでは見えない傾向</span>
      </header>

      {/* 各洞察。区切り線で読み物として整理 */}
      <ul className="divide-y divide-border/60">
        {insights.map((item, i) => (
          <InsightRow key={`${item.type}-${i}`} item={item} />
        ))}
      </ul>

      {/* 生成時刻は控えめに（更新の鮮度を一応示す） */}
      {data?.generatedAt && (
        <footer className="border-t bg-muted/20 px-4 py-1.5 text-right">
          <span className="text-[10px] tabular-nums text-muted-foreground/70">
            {formatGeneratedAt(data.generatedAt)} 時点
          </span>
        </footer>
      )}
    </section>
  )
})

InsightsBlock.displayName = 'InsightsBlock'
