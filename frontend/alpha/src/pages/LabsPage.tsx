import { memo, useCallback, useMemo } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import useSWR from 'swr'
import { FlaskConical } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import {
  LabsControls,
  LabsRankingCard,
  LabsQuerySection,
  type LabsMode,
  type LabsEntity,
} from '@/components/Labs'
import { alphaApi } from '@/api/alpha'
import type {
  AccessRankingResponse,
  AccessRankingRoom,
  SearchRankingResponse,
  SearchRankingRoom,
  SearchQueryRankingResponse,
  OpenChat,
} from '@/types/api'

const DEFAULT_LIMIT = 20
const DEFAULT_DAYS = 30
// 表示件数は選択式（10/20/50）。想定外の値は既定へ丸める。
const ALLOWED_LIMITS = [10, 20, 50]

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
  const limitParam = Number(searchParams.get('limit'))
  const limit = ALLOWED_LIMITS.includes(limitParam) ? limitParam : DEFAULT_LIMIT

  const { data, error, isLoading } = useSWR<AccessRankingResponse | SearchRankingResponse>(
    ['labs-ranking', mode, days, limit],
    () =>
      mode === 'access'
        ? alphaApi.getAccessRanking({ days, order: 'desc', limit })
        : alphaApi.getSearchRanking({ days, order: 'desc', limit }),
    {
      revalidateOnFocus: false,
      revalidateOnReconnect: false,
      dedupingInterval: 60000,
      keepPreviousData: true,
    }
  )

  // 検索流入モードのときだけ、サイト全体の検索キーワード（流入クエリ）も取得する。
  const { data: queryData } = useSWR<SearchQueryRankingResponse>(
    mode === 'search' ? ['labs-query-ranking', days, limit] : null,
    () => alphaApi.getSearchQueryRanking({ days, limit }),
    {
      revalidateOnFocus: false,
      revalidateOnReconnect: false,
      dedupingInterval: 60000,
      keepPreviousData: true,
    }
  )

  const rooms = useMemo<LabsRoom[]>(() => data?.data ?? [], [data])
  const pages = useMemo(() => data?.pages ?? [], [data])
  const queries = useMemo(() => queryData?.data ?? [], [queryData])

  // 部屋とページ全体を1つの並びに統合し、アクティブ指標（access=純PV / search=検索クリック）で降順ソート。
  // ソート値はエンティティ構築時に mode を見て安全に取り出す（room の型は mode と一致する）。
  const entities = useMemo<(LabsEntity & { sortValue: number })[]>(() => {
    const list: (LabsEntity & { sortValue: number })[] = []
    for (const room of rooms) {
      const sortValue =
        mode === 'access'
          ? (room as AccessRankingRoom).pageviews
          : (room as SearchRankingRoom).searchClicks
      list.push({ kind: 'room', room, sortValue })
    }
    for (const page of pages) {
      const sortValue = mode === 'access' ? page.pageviews : page.searchClicks
      list.push({ kind: 'page', page, sortValue })
    }
    list.sort((a, b) => b.sortValue - a.sortValue)
    return list
  }, [rooms, pages, mode])

  const setParam = useCallback(
    (next: Partial<{ mode: LabsMode; days: number; limit: number }>) => {
      const params = new URLSearchParams(searchParams)
      if (next.mode !== undefined) params.set('mode', next.mode)
      if (next.days !== undefined) params.set('days', String(next.days))
      if (next.limit !== undefined) params.set('limit', String(next.limit))
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
        本家ページ（openchat-review.me）への Google
        からの流入を、Google アナリティクスで分析した指標です。
      </p>

      <LabsControls
        mode={mode}
        days={days}
        limit={limit}
        onModeChange={(m) => setParam({ mode: m })}
        onDaysChange={(d) => setParam({ days: d })}
        onLimitChange={(l) => setParam({ limit: l })}
      />

      {/* 指標の読み方（純PV と ユニークユーザーの違い）を一度だけ明示する。 */}
      <p className="text-[11px] leading-relaxed text-muted-foreground/80">
        純PV＝ページ閲覧数（同じ人の連続表示も加算）／ ユニークユーザー＝その期間の実訪問者数
      </p>

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
            <div className="space-y-5">
              {/* 部屋とページ全体（トップ/おすすめ等）を同じ並びに統合し、指標で通し順に表示。 */}
              <section className="space-y-2" data-testid="labs-rooms-section">
                <div className="grid gap-2 md:gap-3">
                  {entities.map((entity, index) => (
                    <LabsRankingCard
                      key={entity.kind === 'room' ? `r${entity.room.id}` : `p${entity.page.path}`}
                      entity={entity}
                      rank={index + 1}
                      mode={mode}
                      onRoomClick={handleCardClick}
                    />
                  ))}
                </div>
              </section>

              {/* 検索流入モードのときだけ、流入キーワードの一覧を添える。 */}
              {mode === 'search' && <LabsQuerySection queries={queries} />}

              {/* 集計の最終更新は控えめに足元へ */}
              {updatedAt && (
                <p className="pt-1 text-center text-[11px] tabular-nums text-muted-foreground/70">
                  最終更新 {formatUpdatedAt(updatedAt)}
                </p>
              )}
            </div>
          )}
        </>
      )}
    </div>
  )
})

LabsPage.displayName = 'LabsPage'

export default LabsPage
