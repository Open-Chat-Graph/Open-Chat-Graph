import { memo, useCallback, useMemo } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import useSWR from 'swr'
import { FlaskConical } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { LabsControls, LabsRankingCard, type LabsMode } from '@/components/Labs'
import { alphaApi } from '@/api/alpha'
import type {
  AccessRankingResponse,
  AccessRankingRoom,
  SearchRankingResponse,
  SearchRankingRoom,
  OpenChat,
} from '@/types/api'

const LIMIT = 20
const DEFAULT_DAYS = 30

type LabsRoom = AccessRankingRoom | SearchRankingRoom

// "2024-05-30 12:00:00" → "2024/05/30 12:00"
const formatUpdatedAt = (raw?: string | null): string => {
  if (!raw) return ''
  const m = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/)
  if (m) return `${m[1]}/${m[2]}/${m[3]} ${m[4]}:${m[5]}`
  const d = String(raw).match(/^(\d{4})-(\d{2})-(\d{2})/)
  return d ? `${d[1]}/${d[2]}/${d[3]}` : String(raw)
}

// ランキングの1部屋を DetailPage が期待する OpenChat 形へ写像（即時表示用の初期データ）。
// アクセス/検索の指標は OpenChat に対応フィールドが無いため持ち越さない（詳細側で再取得される）。
const toOpenChat = (room: LabsRoom): OpenChat => ({
  id: room.id,
  name: room.name,
  desc: room.desc,
  member: room.member,
  img: room.img,
  emblem: room.emblem,
  category: room.category,
  categoryName: room.categoryName,
  join_method_type: room.join_method_type,
  increasedMember: 0,
  percentageIncrease: 0,
  diff24h: 0,
  percent24h: 0,
  diff1w: 0,
  percent1w: 0,
  createdAt: room.createdAt ?? null,
  registeredAt: room.registeredAt,
  isInRanking: false,
  url: room.url,
})

const LabsPage = memo(() => {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()

  const mode: LabsMode = searchParams.get('mode') === 'search' ? 'search' : 'access'
  const days = Number(searchParams.get('days')) || DEFAULT_DAYS

  const { data, error, isLoading } = useSWR<AccessRankingResponse | SearchRankingResponse>(
    ['labs-ranking', mode, days],
    () =>
      mode === 'access'
        ? alphaApi.getAccessRanking({ days, order: 'desc', limit: LIMIT })
        : alphaApi.getSearchRanking({ days, order: 'desc', limit: LIMIT }),
    {
      revalidateOnFocus: false,
      revalidateOnReconnect: false,
      dedupingInterval: 60000,
      keepPreviousData: true,
    }
  )

  const rooms = useMemo<LabsRoom[]>(() => data?.data ?? [], [data])

  const setParam = useCallback(
    (next: Partial<{ mode: LabsMode; days: number }>) => {
      const params = new URLSearchParams(searchParams)
      if (next.mode !== undefined) params.set('mode', next.mode)
      if (next.days !== undefined) params.set('days', String(next.days))
      setSearchParams(params, { replace: true })
    },
    [searchParams, setSearchParams]
  )

  const handleCardClick = useCallback(
    (id: number) => {
      const room = rooms.find((r) => r.id === id)
      navigate(`/openchat/${id}`, {
        state: room ? { initialData: toOpenChat(room) } : undefined,
      })
    },
    [navigate, rooms]
  )

  const updatedAt = data?.updatedAt ?? null

  return (
    <div className="space-y-4">
      {/* 見出し＋戻るは固定タイトルバー（DashboardLayout）が担うので、ここでは説明のみ。 */}
      <p className="text-sm text-muted-foreground">
        本家ページ（openchat-review.me）のアクセスと検索からの流入で並べた、初見・SEO向けの指標です。
      </p>

      <LabsControls
        mode={mode}
        days={days}
        onModeChange={(m) => setParam({ mode: m })}
        onDaysChange={(d) => setParam({ days: d })}
      />

      {error && (
        <Card className="border-destructive">
          <CardContent className="pt-6">
            <p className="text-sm text-destructive">データの取得に失敗しました</p>
          </CardContent>
        </Card>
      )}

      {!error && isLoading && !data && (
        <div className="flex justify-center py-8">
          <div className="text-muted-foreground">読み込み中...</div>
        </div>
      )}

      {!error && data && (
        <>
          {rooms.length === 0 ? (
            // creds 投入前は空配列が返る。集計待ちを丁寧に案内する。
            <Card>
              <CardContent className="py-12 text-center">
                <FlaskConical className="mx-auto h-12 w-12 text-muted-foreground/40" />
                <p className="mt-4 text-sm text-muted-foreground">
                  集計待ち（GA連携の設定後に表示されます）
                </p>
                <p className="mt-1 text-xs text-muted-foreground/80">
                  GA連携後に集計されます。集計は日次で更新されます。
                </p>
              </CardContent>
            </Card>
          ) : (
            <>
              <div className="grid gap-2 md:gap-3">
                {rooms.map((room, index) => (
                  <LabsRankingCard
                    key={room.id}
                    room={room}
                    rank={index + 1}
                    mode={mode}
                    onCardClick={handleCardClick}
                  />
                ))}
              </div>

              {/* 集計の最終更新は控えめに足元へ */}
              {updatedAt && (
                <p className="pt-1 text-center text-[11px] tabular-nums text-muted-foreground/70">
                  最終更新 {formatUpdatedAt(updatedAt)}
                </p>
              )}
            </>
          )}
        </>
      )}
    </div>
  )
})

LabsPage.displayName = 'LabsPage'

export default LabsPage
