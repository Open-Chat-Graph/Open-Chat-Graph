import { memo } from 'react'
import { LayoutGrid } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import type { RankingPageMetric } from '@/types/api'

interface LabsPagesSectionProps {
  pages: RankingPageMetric[]
}

// 数値1枠。大きな数字（Sora・tabular-nums）＋小ラベル。部屋カードの作法に揃える。
function Stat({ value, label }: { value: number; label: string }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="font-display text-base font-bold tabular-nums leading-none text-foreground">
        {value.toLocaleString()}
      </span>
      <span className="text-[10px] leading-none text-muted-foreground">{label}</span>
    </div>
  )
}

// ランキング全体に対する「ページ単位」の指標（トップ / おすすめ等）。
// 部屋ランキングと並置し、サイト構造そのものの流入を俯瞰できるようにする。
export const LabsPagesSection = memo(({ pages }: LabsPagesSectionProps) => {
  if (pages.length === 0) return null

  return (
    <section className="space-y-2" data-testid="labs-pages-section">
      <div className="flex items-center gap-1.5 px-0.5">
        <LayoutGrid className="h-3.5 w-3.5 text-muted-foreground" />
        <h2 className="text-sm font-semibold text-foreground">ページ全体</h2>
        <span className="text-[11px] text-muted-foreground">
          トップ・おすすめなど、部屋以外のページの流入
        </span>
      </div>

      <div className="grid gap-2 md:gap-2.5">
        {pages.map((page) => (
          <Card key={page.path} data-testid={`labs-page-${page.path}`} className="overflow-hidden">
            <CardContent className="p-3 md:p-4">
              <div className="flex items-baseline justify-between gap-2">
                <span className="truncate text-sm font-semibold text-foreground">{page.label}</span>
                <code className="flex-shrink-0 text-[11px] tabular-nums text-muted-foreground/80">
                  {page.path}
                </code>
              </div>

              <div className="mt-2.5 flex flex-wrap items-end gap-x-6 gap-y-2 rounded-md border bg-muted/40 px-3 py-2.5">
                <Stat value={page.pageviews} label="純PV" />
                <Stat value={page.activeUsers} label="ユニークユーザー" />
                <Stat value={page.searchClicks} label="検索クリック" />
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </section>
  )
})

LabsPagesSection.displayName = 'LabsPagesSection'
