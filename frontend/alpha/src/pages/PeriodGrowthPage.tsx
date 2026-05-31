import { memo, useCallback, useMemo } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import useSWR from 'swr'
import { CalendarRange, Info } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { PeriodGrowthCard, PeriodGrowthControls, type PeriodOrder, type PeriodGrowthQuery } from '@/components/PeriodGrowth'
import { alphaApi } from '@/api/alpha'
import { categoryName } from '@/lib/categories'
import type { PeriodGrowthItem, PeriodGrowthResponse, OpenChat } from '@/types/api'

const LIMIT = 50
const DEFAULT_DAYS = 365

// "2024-05-30 12:00:00" → "2024/05/30"
const formatDate = (raw?: string | null): string => {
  if (!raw) return ''
  const m = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})/)
  return m ? `${m[1]}/${m[2]}/${m[3]}` : String(raw)
}

// PeriodGrowthItem を DetailPage が期待する OpenChat 形へ写像（即時表示用の初期データ）。
// 期間差分(diff/percent)は OpenChat に対応フィールドが無いため持ち越さない（詳細側で再取得される）。
const toOpenChat = (item: PeriodGrowthItem): OpenChat => ({
  id: item.id,
  name: item.name,
  desc: item.desc,
  member: item.member,
  img: item.img,
  emblem: item.emblem,
  category: item.category,
  categoryName: item.categoryName,
  join_method_type: item.join_method_type,
  increasedMember: 0,
  percentageIncrease: 0,
  diff24h: 0,
  percent24h: 0,
  diff1w: 0,
  percent1w: 0,
  createdAt: item.createdAt ?? null,
  registeredAt: item.registeredAt,
  isInRanking: false,
  url: item.url,
})

const PeriodGrowthPage = memo(() => {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()

  const keyword = searchParams.get('q') || ''
  const category = Number(searchParams.get('category')) || 0
  const order = (searchParams.get('order') as PeriodOrder) || 'desc'
  const start = searchParams.get('start') || ''
  const end = searchParams.get('end') || ''

  // 開始日・終了日が両方あれば日付指定（days より優先）、無ければ従来の日数指定。
  const hasRange = Boolean(start && end)
  const days = hasRange ? 0 : Number(searchParams.get('days')) || DEFAULT_DAYS

  // 検索を実行したか。キーワードは任意（空欄＝全件）なので、キーワード有無では判定できない。
  // 検索画面からの遷移(q付き)か、このページで「検索」を押した(go=1)ときに結果を出す。
  const searched = keyword !== '' || searchParams.has('go')

  const { data, error, isLoading } = useSWR<PeriodGrowthResponse>(
    searched ? ['period-growth', keyword, category, days, order, start, end] : null,
    () =>
      alphaApi.getPeriodGrowth(
        hasRange
          ? { keyword, category: category || undefined, startDate: start, endDate: end, order, limit: LIMIT }
          : { keyword, category: category || undefined, days, order, limit: LIMIT }
      ),
    {
      revalidateOnFocus: false,
      revalidateOnReconnect: false,
      dedupingInterval: 60000,
      keepPreviousData: true,
    }
  )

  const items = useMemo(() => data?.data ?? [], [data])

  const handleSubmit = useCallback(
    (next: PeriodGrowthQuery) => {
      const params = new URLSearchParams()
      if (next.keyword) params.set('q', next.keyword)
      if (next.category) params.set('category', String(next.category))
      if (next.order) params.set('order', next.order)
      // 開始日・終了日が両方あれば日付で保持。片方でも欠ければ days(従来)に戻す。
      if (next.start && next.end) {
        params.set('start', next.start)
        params.set('end', next.end)
      } else if (days) {
        params.set('days', String(days))
      }
      // キーワード空でも検索実行を表す。これがあると結果を表示する。
      params.set('go', '1')
      setSearchParams(params)
    },
    [setSearchParams, days]
  )

  const handleCardClick = useCallback(
    (id: number) => {
      const item = items.find((i) => i.id === id)
      navigate(`/openchat/${id}`, {
        state: item ? { initialData: toOpenChat(item) } : undefined,
      })
    },
    [navigate, items]
  )

  return (
    <div className="space-y-4">
      {/* 見出し＋戻るは固定タイトルバー（DashboardLayout）が担うので、ここでは説明のみ。 */}
      <p className="text-sm text-muted-foreground">
        指定した期間の前後どちらにもデータがある部屋だけを並べます。
      </p>

      <PeriodGrowthControls
        keyword={keyword}
        category={category}
        start={start}
        end={end}
        order={order}
        onSubmit={handleSubmit}
      />

      {/* 未検索時の案内（キーワードは任意・空欄で全件） */}
      {!searched && (
        <Card>
          <CardContent className="py-12 text-center">
            <CalendarRange className="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p className="mt-4 text-sm text-muted-foreground">
              期間とカテゴリを選んで「検索」を押してください
            </p>
            <p className="mt-1 text-xs text-muted-foreground/80">
              キーワードは任意。空欄なら全部屋から、その期間に伸びた部屋を一覧します
            </p>
          </CardContent>
        </Card>
      )}

      {searched && error && (
        <Card className="border-destructive">
          <CardContent className="pt-6">
            <p className="text-sm text-destructive">データの取得に失敗しました</p>
          </CardContent>
        </Card>
      )}

      {searched && isLoading && !data && (
        <div className="flex justify-center py-8">
          <div className="text-muted-foreground">読み込み中...</div>
        </div>
      )}

      {searched && data && (
        <>
          {/* サマリ（ラベル付き・数値は tabular-nums で軽く構造化） */}
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
            <span>
              <span className="text-xs text-muted-foreground/80">対象</span>{' '}
              <span className="font-semibold text-foreground">{keyword || '全部屋'}</span>
              {category > 0 && (
                <span className="text-foreground">（{categoryName(category)}）</span>
              )}
            </span>
            <span aria-hidden className="opacity-40">|</span>
            <span>
              <span className="text-xs text-muted-foreground/80">期間</span>{' '}
              <span className="tabular-nums text-foreground">
                {formatDate(data.targetPastDate)} 〜 {formatDate(data.baseDate)}
              </span>
              <span className="tabular-nums">（{data.days.toLocaleString()}日）</span>
            </span>
            <span aria-hidden className="opacity-40">|</span>
            <span>
              <span className="text-xs text-muted-foreground/80">該当</span>{' '}
              <span className="tabular-nums text-foreground">{data.totalMatched.toLocaleString()}</span>件
            </span>
          </div>

          {items.length === 0 ? (
            <Card>
              <CardContent className="py-10 text-center">
                <Info className="mx-auto h-8 w-8 text-muted-foreground/50" />
                <p className="mt-3 text-sm text-muted-foreground">
                  条件に合う部屋がありません
                </p>
                <p className="mt-1 text-xs text-muted-foreground/80">
                  指定した期間の開始時点にデータがある部屋が見つかりませんでした。期間を短くするとヒットしやすくなります。
                </p>
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-2 md:gap-3">
              {items.map((item, index) => (
                <PeriodGrowthCard
                  key={item.id}
                  item={item}
                  rank={index + 1}
                  onCardClick={handleCardClick}
                />
              ))}
            </div>
          )}
        </>
      )}
    </div>
  )
})

PeriodGrowthPage.displayName = 'PeriodGrowthPage'

export default PeriodGrowthPage
