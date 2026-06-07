import { memo, useState, type ReactNode } from 'react'
import useSWR from 'swr'
import {
  BarChart3,
  Eye,
  Users,
  Search,
  ExternalLink,
  Timer,
  Info,
  type LucideIcon,
} from 'lucide-react'
import { alphaApi } from '@/api/alpha'
import { cn } from '@/lib/utils'
import { InfoChip } from '@/components/ui/info-chip'
import { PeriodRangePicker } from '@/components/ui/period-range-picker'
import { DEFAULT_PERIOD, periodKey, type PeriodValue } from '@/lib/period'
import { RoomFlowPanels } from './RoomFlowPanels'
import type { RoomMetricsResponse } from '@/types/api'

interface RoomMetricsBlockProps {
  openChatId: number
}

/**
 * GA/GSC 由来のアクセス・検索メトリクスブロック（詳細ページ向け）。
 *
 * グラフ（メンバー数の推移）が見せられない「人がどう訪れているか」を補う補助ブロック。
 * ページ閲覧数・ユニークユーザー・SEO流入・参加リンクのタップ・平均エンゲージメント時間を、
 * 数字を主役に静かに並べる。期間は既定30日。プルダウンでプリセット/カレンダー/全期間を選べる。
 *
 * creds 投入前は updatedAt が null で全指標がゼロで返る。その場合は「壊れている」のではなく
 * 「まだ集計が無い」状態なので、ブロックごと無表示にして注意を奪わない。
 */

// 秒 → 「M分S秒」/「S秒」。エンゲージメント時間の整形。
function formatEngagement(seconds: number): string {
  const total = Math.max(0, Math.round(seconds))
  const m = Math.floor(total / 60)
  const s = total % 60
  if (m === 0) return `${s}秒`
  return `${m}分${s}秒`
}

// 1指標タイル。数値は Sora の tabular-nums で構造化。アイコンは控えめなアクセント。
// info を渡すとラベル横にⓘが付き、タップで説明ポップオーバー（InfoChip）を出す。
function MetricTile({
  icon: Icon,
  accent,
  label,
  value,
  unit,
  sub,
  info,
}: {
  icon: LucideIcon
  accent: string
  label: string
  value: string
  unit?: string
  sub?: ReactNode
  info?: ReactNode
}) {
  return (
    <div className="surface-tonal flex flex-col gap-1 px-3 py-2.5">
      <div className="flex items-center gap-1.5">
        <Icon className={cn('h-3.5 w-3.5 flex-shrink-0', accent)} aria-hidden />
        <span className="text-[11px] tracking-wide font-medium text-muted-foreground">{label}</span>
        {info && (
          <InfoChip
            trigger={<Info className="h-3 w-3 text-muted-foreground/70" aria-label={`${label}の説明`} />}
            triggerClassName="flex-shrink-0"
          >
            {info}
          </InfoChip>
        )}
      </div>
      <div className="flex items-baseline gap-1">
        <span className="font-display text-2xl font-bold leading-none tabular-nums text-foreground">
          {value}
        </span>
        {unit && <span className="text-xs text-muted-foreground">{unit}</span>}
      </div>
      {sub && (
        <span className="text-[11px] leading-tight tabular-nums text-muted-foreground">{sub}</span>
      )}
    </div>
  )
}

export const RoomMetricsBlock = memo(({ openChatId }: RoomMetricsBlockProps) => {
  const [period, setPeriod] = useState<PeriodValue>(DEFAULT_PERIOD)

  // 遅延取得。詳細の主役はグラフなので、メトリクスは後追いで静かに現れればよい。
  const { data, error, isLoading } = useSWR<RoomMetricsResponse>(
    ['room-metrics', openChatId, periodKey(period)],
    () => alphaApi.getRoomMetrics(openChatId, period),
    { revalidateOnFocus: false, revalidateOnReconnect: false, keepPreviousData: true },
  )

  // ローディング/エラーは無表示。補助ブロックなので存在を主張しない。
  if (isLoading || error || !data) return null

  // creds 前（未集計）は静かな空表示＝ブロックごと出さない。
  // updatedAt が null かつ全指標ゼロを「まだ集計が無い」と判断する。
  const searchQueries = data.searchQueries ?? []
  const referrers = data.referrers ?? []
  const hasAnyData =
    data.updatedAt !== null &&
    (data.pageviews > 0 ||
      data.activeUsers > 0 ||
      data.searchClicks > 0 ||
      data.searchImpressions > 0 ||
      data.seoIndirect > 0 ||
      data.jumpClicks > 0 ||
      data.avgEngagementSeconds > 0 ||
      // 指標ゼロでも、流入語/参照元が在れば「どう来たか」として出す価値がある
      searchQueries.length > 0 ||
      referrers.length > 0)
  if (!hasAnyData) return null

  return (
    <section
      className="max-w-[var(--content-w)] mx-auto overflow-hidden rounded-xl border bg-card shadow-sm"
      aria-label="アクセス・検索の指標"
    >
      {/* 見出し帯：オプチャグラフ上での“見られ方・送客”を示す控えめなヘッダー。
          副題は出所の手がかりなので常時表示（狭い幅では折り返す） */}
      <header className="flex flex-wrap items-center gap-x-2 gap-y-0.5 border-b bg-muted/30 px-4 py-2.5">
        <BarChart3 className="h-4 w-4 flex-shrink-0 text-primary" />
        <h2 className="text-sm font-bold">アクセス・検索の指標</h2>
        <span className="text-[11px] font-normal text-muted-foreground">
          オプチャグラフ上での見られ方
        </span>
        {/* 期間指定：既定30日／プリセット／カレンダー／全期間 */}
        <div className="ml-auto">
          <PeriodRangePicker value={period} onChange={setPeriod} />
        </div>
      </header>

      {/* 指標タイル。数字を主役に。SEO流入は表示回数/平均順位を sub に添える */}
      <div className="grid grid-cols-2 gap-2 p-3 sm:grid-cols-3">
        <MetricTile
          icon={Eye}
          accent="text-primary"
          label="ページ閲覧数"
          value={data.pageviews.toLocaleString()}
          unit="回"
        />
        <MetricTile
          icon={Users}
          accent="text-indigo-600 dark:text-indigo-400"
          label="ユニークユーザー"
          value={data.activeUsers.toLocaleString()}
          unit="人"
        />
        <MetricTile
          icon={Search}
          accent="text-primary"
          label="SEO流入"
          // 合計＝直接(Google→このページ)＋間接(本家内SEOページ経由で回遊到達)。
          // 直接が0でも間接が多い部屋があるので合計で見せる。
          value={(data.searchClicks + data.seoIndirect).toLocaleString()}
          unit="流入"
          info="直接=Google検索の結果からこのページに来た数。間接=検索でオプチャグラフに来た人がサイト内を回遊してこのページに来た数。"
          sub={
            <>
              直接 {data.searchClicks.toLocaleString()}クリック ・ 間接 {data.seoIndirect.toLocaleString()}PV
              {data.searchClicks > 0 && (
                <>
                  <br />
                  表示 {data.searchImpressions.toLocaleString()} ・ 平均{' '}
                  {data.searchPosition != null ? data.searchPosition.toFixed(1) : '—'}位
                </>
              )}
            </>
          }
        />
        <MetricTile
          icon={ExternalLink}
          accent="text-amber-600 dark:text-amber-400"
          label="参加リンクのタップ"
          value={data.jumpClicks.toLocaleString()}
          unit="回"
          // 参加に至ったタップのうち、Google検索起点で来た数（本家が出さない数字）
          sub={
            data.jumpClicks > 0
              ? `うち検索経由 ${(data.jumpClicksOrganic ?? 0).toLocaleString()}回`
              : 'LINEへの送客'
          }
        />
        <MetricTile
          icon={Timer}
          accent="text-violet-600 dark:text-violet-400"
          label="平均滞在時間"
          value={formatEngagement(data.avgEngagementSeconds)}
        />
      </div>

      {/* 「どう流入したか」の小窓（流入キーワード／参照元）。両方空なら領域ごと出さない */}
      {(searchQueries.length > 0 || referrers.length > 0) && (
        <RoomFlowPanels searchQueries={searchQueries} referrers={referrers} />
      )}

      {/* 数字の出所の注記。LINEアプリ内の数字と誤読されないように明示する */}
      <p className="px-4 pb-3 text-[11px] leading-relaxed text-muted-foreground/80">
        ※ オプチャグラフ内のこの部屋の紹介ページの閲覧データです（LINEアプリ内の数字ではありません）
      </p>

      {/* 集計の鮮度を控えめに（更新日時） */}
      {data.updatedAt && (
        <footer className="border-t bg-muted/20 px-4 py-1.5 text-right">
          <span className="text-[10px] tabular-nums text-muted-foreground/70">
            {data.fromDate && data.toDate
              ? `${data.fromDate}〜${data.toDate}（${data.days}日）`
              : `直近${data.days}日`}
            {' ・ '}{data.updatedAt} 時点
          </span>
        </footer>
      )}
    </section>
  )
})

RoomMetricsBlock.displayName = 'RoomMetricsBlock'
