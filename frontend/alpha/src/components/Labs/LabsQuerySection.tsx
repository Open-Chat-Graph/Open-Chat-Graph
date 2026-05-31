import { memo } from 'react'
import { Search } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import type { SearchQueryRankingItem } from '@/types/api'

interface LabsQuerySectionProps {
  queries: SearchQueryRankingItem[]
}

// 検索キーワード（流入クエリ）の一覧。サイト全体に対し Google 検索で
// どんな語句から流入しているかを、クリック・表示・平均順位で羅列する。
export const LabsQuerySection = memo(({ queries }: LabsQuerySectionProps) => {
  if (queries.length === 0) return null

  return (
    <section className="space-y-2" data-testid="labs-query-section">
      <div className="flex items-center gap-1.5 px-0.5">
        <Search className="h-3.5 w-3.5 text-muted-foreground" />
        <h2 className="text-sm font-semibold text-foreground">検索キーワード（流入クエリ）</h2>
        <span className="text-[11px] text-muted-foreground">Google 検索からの流入語句</span>
      </div>

      <Card className="overflow-hidden">
        <CardContent className="p-0">
          {/* 見出し行（数値カラムのラベル） */}
          <div className="flex items-center gap-3 border-b px-3 py-2 text-[11px] text-muted-foreground md:px-4">
            <span className="w-5 flex-shrink-0 text-center">#</span>
            <span className="flex-1">キーワード</span>
            <span className="w-14 flex-shrink-0 text-right">クリック</span>
            <span className="w-14 flex-shrink-0 text-right">表示</span>
            <span className="w-12 flex-shrink-0 text-right">順位</span>
          </div>

          <ul>
            {queries.map((q, index) => (
              <li
                key={q.query}
                data-testid={`labs-query-${index + 1}`}
                className="flex items-center gap-3 border-b px-3 py-2.5 last:border-b-0 md:px-4"
              >
                <span className="w-5 flex-shrink-0 text-center font-display text-xs font-bold tabular-nums text-muted-foreground">
                  {index + 1}
                </span>
                <span className="flex-1 truncate text-sm text-foreground">{q.query}</span>
                <span className="w-14 flex-shrink-0 text-right font-display text-sm font-semibold tabular-nums text-primary">
                  {q.clicks.toLocaleString()}
                </span>
                <span className="w-14 flex-shrink-0 text-right text-xs tabular-nums text-muted-foreground">
                  {q.impressions.toLocaleString()}
                </span>
                <span className="w-12 flex-shrink-0 text-right text-xs tabular-nums text-muted-foreground">
                  {q.position.toFixed(1)}
                </span>
              </li>
            ))}
          </ul>
        </CardContent>
      </Card>
    </section>
  )
})

LabsQuerySection.displayName = 'LabsQuerySection'
