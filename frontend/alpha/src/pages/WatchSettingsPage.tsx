import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import useSWR from 'swr'
import { Trash2, Sparkles, Eye, ListChecks } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { alphaApi } from '@/api/alpha'
import { imgPreviewUrl } from '@/lib/imageUrl'
import { categoryName } from '@/lib/categories'
import { loadMyList } from '@/services/storage'
import { useAlertsConfig, configToRequest } from '@/components/Notifications/useAlertsConfig'
import type {
  AlertsConfigRequestKeyword,
  AlertsConfigRequestRoom,
  BatchStatsResponse,
} from '@/types/api'

/** number|null を入力欄文字列に。null/未設定は空。 */
const toInput = (v: number | null | undefined): string => (v == null ? '' : String(v))
/** 入力欄文字列を number|null に。空や非数値は null。 */
const toNum = (s: string): number | null => {
  const t = s.trim()
  if (t === '') return null
  const n = Number(t)
  return Number.isFinite(n) ? n : null
}

/**
 * 見張り設定ページ（`/watch`）。
 *
 * 旧 WatchSettingsDialog をルートを持つページに作り替えたもの。戻る/進むはブラウザ標準で効く。
 * GET config で初期化したローカル状態を編集し、PUT で全置き換え保存する。
 * 3セクション: (1)キーワードの見張り (2)部屋の見張り (3)マイリスト全体の変動。
 * 部屋は openChatId だけ持つので batchStats で名前/画像/現在人数を引いて表示する。
 */
export default function WatchSettingsPage() {
  const navigate = useNavigate()
  const { config, isLoading, save } = useAlertsConfig()

  // ローカル編集状態（保存形→送信形に正規化したもの）
  const [keywords, setKeywords] = useState<AlertsConfigRequestKeyword[]>([])
  const [rooms, setRooms] = useState<AlertsConfigRequestRoom[]>([])
  const [mylistEnabled, setMylistEnabled] = useState(false)
  const [mylistUp, setMylistUp] = useState('')
  const [mylistDown, setMylistDown] = useState('')

  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState(false)

  // マイリスト全体の変動セクションは、マイリストに部屋が無いと空振りする。
  // 件数を見て 0 件なら入力欄を無効化し、追加への導線を出す。
  const [myListItemCount] = useState(() => loadMyList().items.length)
  const myListEmpty = myListItemCount === 0

  // config 到着時にローカル状態を初期化
  useEffect(() => {
    if (!config) return
    const req = configToRequest(config)
    setKeywords(req.keywords ?? [])
    setRooms(req.rooms ?? [])
    setMylistEnabled(req.mylistThreshold?.enabled ?? false)
    setMylistUp(toInput(req.mylistThreshold?.upPercent))
    setMylistDown(toInput(req.mylistThreshold?.downPercent))
    setSaveError(false)
  }, [config])

  // 見張り中の部屋の名前/画像/現在人数（バッチ取得）
  const roomIds = useMemo(() => rooms.map((r) => r.openChatId), [rooms])
  const { data: statsData } = useSWR<BatchStatsResponse>(
    roomIds.length > 0 ? ['watch-rooms-batch', roomIds.join(',')] : null,
    () => alphaApi.batchStats(roomIds),
    { revalidateOnFocus: false, revalidateOnReconnect: false },
  )
  const statById = useMemo(
    () => new Map(statsData?.data.map((s) => [s.id, s]) ?? []),
    [statsData?.data],
  )

  const removeKeyword = (idx: number) =>
    setKeywords((prev) => prev.filter((_, i) => i !== idx))

  const removeRoom = (idx: number) =>
    setRooms((prev) => prev.filter((_, i) => i !== idx))

  // 部屋ごとの見張りは「増減 ±N%」の1値だけに簡素化（普通の人向け）。
  // 上下とも同じ％にし、人数しきい値は使わない（null）。
  const setRoomPercent = (idx: number, raw: string) =>
    setRooms((prev) =>
      prev.map((r, i) =>
        i === idx
          ? { ...r, upPercent: toNum(raw), downPercent: toNum(raw), upMember: null, downMember: null }
          : r,
      ),
    )

  const handleSave = async () => {
    setSaving(true)
    setSaveError(false)
    try {
      await save({
        keywords,
        rooms,
        mylistThreshold: {
          enabled: mylistEnabled,
          upPercent: toNum(mylistUp),
          downPercent: toNum(mylistDown),
        },
      })
      navigate(-1)
    } catch {
      setSaveError(true)
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* 説明（見出し「見張り設定」は固定タイトルバーが表示） */}
      <p className="text-sm text-muted-foreground">
        条件に合う部屋や変動があったとき、毎時の更新後にお知らせします。
      </p>

      {isLoading && !config ? (
        <div className="flex justify-center py-12">
          <div className="h-7 w-7 animate-spin rounded-full border-b-2 border-primary" />
        </div>
      ) : (
        <>
          {/* (1) キーワードの見張り */}
          <Section
            icon={<Sparkles className="h-4 w-4 text-primary" />}
            title="キーワードの見張り"
            description="指定したキーワードを含む新しい部屋が見つかると通知します。追加は検索画面から行います。"
          >
            {keywords.length > 0 ? (
              <ul className="space-y-1.5">
                {keywords.map((k, i) => (
                  <li
                    key={`${k.keyword}-${k.category ?? 'all'}-${i}`}
                    className="flex items-center gap-2 rounded-md border bg-card px-3 py-2 text-sm"
                  >
                    <span className="font-medium">{k.keyword}</span>
                    <span className="text-xs text-muted-foreground">
                      {k.category == null ? '全カテゴリ' : categoryName(k.category)}
                    </span>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="ml-auto h-10 w-10 flex-shrink-0"
                      onClick={() => removeKeyword(i)}
                      aria-label="削除"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </li>
                ))}
              </ul>
            ) : (
              <EmptyHint>
                見張っているキーワードはまだありません。検索画面の「このキーワードを見張る」から追加できます。
              </EmptyHint>
            )}
          </Section>

          {/* (2) 部屋の見張り */}
          <Section
            icon={<Eye className="h-4 w-4 text-primary" />}
            title="部屋の見張り"
            description="見張っている部屋で、設定した割合を超える増減があれば通知します。追加は各部屋の詳細画面から行います。"
          >
            {rooms.length > 0 ? (
              <ul className="space-y-3">
                {rooms.map((r, i) => {
                  const s = statById.get(r.openChatId)
                  const thumb = imgPreviewUrl(s?.img) || undefined
                  return (
                    <li key={r.openChatId} className="rounded-lg border bg-card p-3">
                      <div className="flex items-center gap-3">
                        {thumb ? (
                          <img
                            src={thumb}
                            alt=""
                            className="h-10 w-10 flex-shrink-0 rounded-md object-cover"
                            loading="lazy"
                            decoding="async"
                          />
                        ) : (
                          <span className="h-10 w-10 flex-shrink-0 rounded-md bg-muted" aria-hidden />
                        )}
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium">
                            {s?.name ?? `部屋 #${r.openChatId}`}
                          </p>
                          <p className="text-xs text-muted-foreground tabular-nums">
                            {s ? `${s.member.toLocaleString('ja-JP')}人` : '読み込み中…'}
                          </p>
                        </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="h-10 w-10 flex-shrink-0"
                          onClick={() => removeRoom(i)}
                          aria-label="この見張りを削除"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                      <div className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 border-t pt-3 text-sm text-muted-foreground">
                        <span>増減が ±</span>
                        <Input
                          type="number"
                          min={1}
                          inputMode="numeric"
                          value={toInput(r.upPercent ?? r.downPercent)}
                          onChange={(e) => setRoomPercent(i, e.target.value)}
                          className="h-10 w-20"
                          aria-label="通知する増減の割合（％）"
                        />
                        <span>% を超えたら通知</span>
                      </div>
                    </li>
                  )
                })}
              </ul>
            ) : (
              <EmptyHint>
                見張っている部屋はまだありません。各部屋の詳細画面の「この部屋を見張る」から追加できます。
              </EmptyHint>
            )}
          </Section>

          {/* (3) マイリスト全体の変動 */}
          <Section
            icon={<ListChecks className="h-4 w-4 text-primary" />}
            title="マイリスト全体の変動"
            description="マイリストに入れた部屋全体で、設定した割合を超える増減があれば通知します。"
          >
            {myListEmpty && (
              <EmptyHint>
                マイリストに部屋を追加すると有効になります。
                <Link to="/mylist" className="ml-1 font-medium text-primary underline-offset-4 hover:underline">
                  マイリストを開く
                </Link>
              </EmptyHint>
            )}
            <label
              className={`flex items-center gap-2 text-sm ${myListEmpty ? 'opacity-50' : ''}`}
            >
              <Checkbox
                checked={mylistEnabled}
                disabled={myListEmpty}
                onCheckedChange={(c) => setMylistEnabled(c === true)}
                data-testid="watch-mylist-enabled"
              />
              マイリストの部屋の増減を通知する
            </label>
            <div className="mt-3 grid grid-cols-2 gap-3">
              <ThresholdField
                label="増加（％）"
                value={mylistUp}
                disabled={myListEmpty || !mylistEnabled}
                onChange={setMylistUp}
              />
              <ThresholdField
                label="減少（％）"
                value={mylistDown}
                disabled={myListEmpty || !mylistEnabled}
                onChange={setMylistDown}
              />
            </div>
          </Section>

          {/* 保存バー */}
          <div className="sticky bottom-0 -mx-3 flex items-center gap-3 border-t bg-background px-3 py-3 md:-mx-6 md:px-6">
            {saveError && (
              <span className="text-xs text-destructive">保存に失敗しました</span>
            )}
            <Button
              variant="ghost"
              className="ml-auto"
              onClick={() => navigate(-1)}
              disabled={saving}
            >
              キャンセル
            </Button>
            <Button onClick={handleSave} disabled={saving || isLoading} data-testid="watch-save">
              {saving ? '保存中…' : '保存'}
            </Button>
          </div>
        </>
      )}
    </div>
  )
}

function Section({
  icon,
  title,
  description,
  children,
}: {
  icon: React.ReactNode
  title: string
  description: string
  children: React.ReactNode
}) {
  return (
    <section className="space-y-3">
      <div className="space-y-1">
        <div className="flex items-center gap-2">
          {icon}
          <h2 className="text-sm font-semibold">{title}</h2>
        </div>
        <p className="text-xs text-muted-foreground">{description}</p>
      </div>
      {children}
    </section>
  )
}

function EmptyHint({ children }: { children: React.ReactNode }) {
  return (
    <p className="rounded-md border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
      {children}
    </p>
  )
}

function ThresholdField({
  label,
  value,
  onChange,
  disabled,
}: {
  label: string
  value: string
  onChange: (v: string) => void
  disabled?: boolean
}) {
  return (
    <div className="space-y-1">
      <Label className="text-xs text-muted-foreground">{label}</Label>
      <Input
        type="number"
        inputMode="numeric"
        min={0}
        value={value}
        disabled={disabled}
        placeholder="—"
        className="h-9"
        onChange={(e) => onChange(e.target.value)}
      />
    </div>
  )
}
