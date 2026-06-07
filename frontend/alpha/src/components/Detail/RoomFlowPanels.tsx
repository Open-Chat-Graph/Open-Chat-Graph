import { Search, Globe } from 'lucide-react'
import { cn } from '@/lib/utils'
import { InfoChip } from '@/components/ui/info-chip'
import type { RoomSearchQuery, RoomReferrer } from '@/types/api'

// 参照元が外部の実URL（http/https）なら、チップ内にクリック可能なリンクとして出す。
const isHttpUrl = (s: string): boolean => /^https?:\/\//i.test(s)

/**
 * 詳細メトリクス下部の「どう流入したか」を示す 2 つの小窓。
 *
 * 数字タイル（PV/UU/SEO…）が“どれだけ”見られたかを語るのに対し、こちらは
 * “どこから・どんな語で”来たかを静かに添える。本家が表に出さない GSC/GA4 の素を
 * オプチャグラフ側で見せる差別化点。RoomMetricsBlock からのみ import する補助。
 *
 * 空配列の窓は出さない（両方空なら RoomMetricsBlock 側で領域ごと出さない）。
 */

// 1窓の外枠：ヘッダ帯（アイコン＋タイトル＋小ヒント）＋スクロール可能なリスト本体。
function FlowPanel({
  icon: Icon,
  accent,
  title,
  hint,
  children,
}: {
  icon: typeof Search
  accent: string
  title: string
  hint: string
  children: React.ReactNode
}) {
  return (
    <div className="flex flex-col overflow-hidden rounded-lg border bg-muted/30">
      {/* ヘッダ帯：何の窓か＋ごく短いヒント。数字タイルより一段控えめに */}
      <div className="flex items-center gap-1.5 border-b bg-muted/40 px-3 py-1.5">
        <Icon className={cn('h-3.5 w-3.5 flex-shrink-0', accent)} aria-hidden />
        <span className="text-xs font-semibold text-foreground">{title}</span>
        <span className="ml-auto truncate text-[10px] text-muted-foreground">{hint}</span>
      </div>
      {/* リスト本体：高さを抑えてスクロール。多くても領域を食い潰さない */}
      <ul className="max-h-44 divide-y divide-border/50 overflow-y-auto">{children}</ul>
    </div>
  )
}

export function RoomFlowPanels({
  searchQueries,
  referrers,
}: {
  searchQueries: RoomSearchQuery[]
  referrers: RoomReferrer[]
}) {
  // 横並びはビューポートでなく実コンテナ幅で判定（auto-fit）。
  // PCの詳細2カラムでは右カラム(340-400px)に入るため、固定 sm:grid-cols-2 だと
  // 1窓170px前後になりラベルが数文字で切れる。508px未満なら自動で縦積み1カラム
  return (
    <div className="grid gap-2 px-3 pb-3 [grid-template-columns:repeat(auto-fit,minmax(250px,1fr))]">
      {/* 流入キーワード：Google 検索でこのページに辿り着いた語（多い順） */}
      {searchQueries.length > 0 && (
        <FlowPanel
          icon={Search}
          accent="text-primary"
          title="流入キーワード"
          hint="Google検索からこの部屋のページに来た語"
        >
          {searchQueries.map((q, i) => (
            <li key={`${q.query}-${i}`} className="flex items-center gap-2 px-3 py-1.5">
              <span className="min-w-0 flex-1 truncate text-xs text-foreground">{q.query}</span>
              <span className="flex-shrink-0 text-[11px] tabular-nums text-muted-foreground">
                {q.clicks.toLocaleString()} クリック
              </span>
            </li>
          ))}
        </FlowPanel>
      )}

      {/* 参照元：どこから来たか。本家内からの遷移は「SEO経由」として明示する */}
      {referrers.length > 0 && (
        <FlowPanel
          icon={Globe}
          accent="text-primary"
          title="参照元"
          hint="どこから来たか"
        >
          {referrers.map((r, i) => (
            <li key={`${r.referrer}-${i}`} className="flex items-center gap-2 px-3 py-1.5">
              {/* ラベルは省略表示。タップ/ホバーでチップに全文＋元URL（どこから来たか）を出す。 */}
              <span className="flex min-w-0 flex-1 items-center gap-1.5">
                <InfoChip
                  triggerClassName="min-w-0 flex-1"
                  trigger={
                    <span className="block truncate text-xs text-foreground underline decoration-dotted decoration-muted-foreground/40 underline-offset-2">
                      {r.label}
                    </span>
                  }
                >
                  <p className="font-medium text-foreground">{r.detail}</p>
                  {isHttpUrl(r.referrer) ? (
                    <a
                      href={r.referrer}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="mt-1 block break-all text-[11px] text-primary underline underline-offset-2"
                    >
                      {r.referrer}
                    </a>
                  ) : (
                    <p className="mt-1 break-all text-[11px] text-muted-foreground">{r.referrer}</p>
                  )}
                </InfoChip>
                {/* isInternal＝本家のSEOページから流入してこの部屋に来た動線 */}
                {r.isInternal && (
                  <span className="flex-shrink-0 rounded-full bg-primary/10 px-1.5 py-0.5 text-[9px] font-medium text-primary">
                    SEO経由
                  </span>
                )}
              </span>
              <span className="flex-shrink-0 text-[11px] tabular-nums text-muted-foreground">
                {r.pageviews.toLocaleString()} PV
              </span>
            </li>
          ))}
          {/* 「SEO経由」バッジの意味を、ごく控えめに 1 行で補足 */}
          {referrers.some((r) => r.isInternal) && (
            <li className="px-3 py-1.5 text-[10px] leading-tight text-muted-foreground/70">
              「SEO経由」＝検索でオプチャグラフに来た人がサイト内からこの部屋に来た動線
            </li>
          )}
        </FlowPanel>
      )}
    </div>
  )
}
