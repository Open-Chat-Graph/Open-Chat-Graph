import { memo } from 'react'
import useSWR from 'swr'
import {
  Sparkles,
  TrendingUp,
  Trophy,
  Layers,
  type LucideIcon,
} from 'lucide-react'
import { alphaApi } from '@/api/alpha'
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

// type ごとのアイコンとラベル。アクセント色は全て text-primary に統一。
type InsightStyle = {
  icon: LucideIcon
  label: string
}

const INSIGHT_STYLES: Record<string, InsightStyle> = {
  momentum: { icon: TrendingUp, label: '勢い' },
  rank_position: { icon: Trophy, label: '公式ランキングでの位置' },
  category_position: { icon: Layers, label: 'カテゴリ内での位置' },
}

const DEFAULT_STYLE: InsightStyle = {
  icon: Sparkles,
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
  const { icon: Icon, label } = styleFor(item.type)
  return (
    <li className="flex gap-3 px-4 py-3">
      {/* type アイコン：淡いリングの中に。形でカテゴリを示す */}
      <span
        className="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-muted/60 text-primary"
        aria-hidden
      >
        <Icon className="h-4 w-4" />
      </span>
      <div className="min-w-0 flex-1 space-y-0.5">
        {/* 見出し階層: 帯ヘッダ(text-sm font-bold) を基準に、行ラベルはその一段下として
            text-xs font-semibold で揃える。text(本文)が主役なので強くしすぎない。 */}
        <span className="text-xs font-semibold tracking-wide text-primary">{label}</span>
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
