import { memo, useCallback, useMemo } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import useSWR from 'swr'
import { CalendarRange, Info } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { PeriodGrowthCard, PeriodGrowthControls, type PeriodOrder } from '@/components/PeriodGrowth'
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
  const days = Number(searchParams.get('days')) || DEFAULT_DAYS
  const order = (searchParams.get('order') as PeriodOrder) || 'desc'

  const { data, error, isLoading } = useSWR<PeriodGrowthResponse>(
    keyword ? ['period-growth', keyword, category, days, order] : null,
    () => alphaApi.getPeriodGrowth({ keyword, category: category || undefined, days, order, limit: LIMIT }),
    {
      revalidateOnFocus: false,
      revalidateOnReconnect: false,
      dedupingInterval: 60000,
      keepPreviousData: true,
    }
  )

  const items = useMemo(() => data?.data ?? [], [data])

  const handleSubmit = useCallback(
    (next: { keyword: string; category: number; days: number; order: PeriodOrder }) => {
      const params = new URLSearchParams()
      if (next.keyword) params.set('q', next.keyword)
      if (next.category) params.set('category', String(next.category))
      if (next.days) params.set('days', String(next.days))
      if (next.order) params.set('order', next.order)
      setSearchParams(params)
    },
    [setSearchParams]
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
      {/* 見出しは固定ヘッダ（タイトルバー）が「任意のN日増減」を表示するので、ここは説明のみ */}
      <p className="text-sm text-muted-foreground">
        キーワードに一致し「N日前と現在の両方に統計があるルーム」だけを、その期間の増減で並べます。
      </p>

      <PeriodGrowthControls
        keyword={keyword}
        category={category}
        days={days}
        order={order}
        onSubmit={handleSubmit}
      />

      {/* キーワード未入力時の案内 */}
      {!keyword && (
        <Card>
          <CardContent className="py-12 text-center">
            <CalendarRange className="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p className="mt-4 text-sm text-muted-foreground">
              キーワードを入力して検索してください
            </p>
            <p className="mt-1 text-xs text-muted-foreground/80">
              例: 「ポケモン」で1年前から今も続くルームの1年間の増加ランキング
            </p>
          </CardContent>
        </Card>
      )}

      {keyword && error && (
        <Card className="border-destructive">
          <CardContent className="pt-6">
            <p className="text-sm text-destructive">データの取得に失敗しました</p>
          </CardContent>
        </Card>
      )}

      {keyword && isLoading && !data && (
        <div className="flex justify-center py-8">
          <div className="text-muted-foreground">読み込み中...</div>
        </div>
      )}

      {keyword && data && (
        <>
          {/* サマリ */}
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
            <span>
              対象: <span className="font-semibold text-foreground">{keyword}</span>
            </span>
            {category > 0 && (
              <>
                <span aria-hidden className="opacity-50">・</span>
                <span>{categoryName(category)}</span>
              </>
            )}
            <span aria-hidden className="opacity-50">・</span>
            <span className="tabular-nums">
              {formatDate(data.targetPastDate)} 〜 {formatDate(data.baseDate)}（{data.days}日）
            </span>
            <span aria-hidden className="opacity-50">・</span>
            <span className="tabular-nums">{data.totalMatched.toLocaleString()}件中</span>
          </div>

          {items.length === 0 ? (
            <Card>
              <CardContent className="py-10 text-center">
                <Info className="mx-auto h-8 w-8 text-muted-foreground/50" />
                <p className="mt-3 text-sm text-muted-foreground">
                  条件に合うルームがありません
                </p>
                <p className="mt-1 text-xs text-muted-foreground/80">
                  N日前にも統計が存在するルームが見つかりませんでした。期間を短くするとヒットしやすくなります。
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
