import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Trash2, Sparkles, ListChecks, Plus, Bell } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { CATEGORIES, categoryName } from '@/lib/categories'
import { loadMyList } from '@/services/storage'
import { useAlertsConfig, configToRequest } from '@/components/Notifications/useAlertsConfig'
import { ThresholdInput, type ThresholdUnit } from '@/components/Notifications/ThresholdInput'
import { resolveScopeOcIds } from '@/components/Notifications/mylistScope'
import { alphaApi } from '@/api/alpha'
import type {
  AlertsConfigRequestKeyword,
  AlertsConfigRequestRoom,
  MylistAlertScope,
} from '@/types/api'

/** 入力欄文字列を「正の有限数 or null」に。空・非数・0以下は null。 */
const toPositive = (s: string): number | null => {
  const t = s.trim()
  if (t === '') return null
  const n = Number(t)
  return Number.isFinite(n) && n > 0 ? n : null
}

/** しきい値(値・単位)を request の up/down 4列へ。null は全クリア。 */
const thresholdToRequest = (
  n: number | null,
  unit: ThresholdUnit,
): Pick<
  AlertsConfigRequestRoom,
  'upMember' | 'downMember' | 'upPercent' | 'downPercent'
> => {
  if (n == null) {
    return { upMember: null, downMember: null, upPercent: null, downPercent: null }
  }
  return unit === 'member'
    ? { upMember: n, downMember: n, upPercent: null, downPercent: null }
    : { upPercent: n, downPercent: n, upMember: null, downMember: null }
}

/**
 * アラート設定ページ（`/watch`）。戻る/進むはブラウザ標準で効く。
 *
 * セクション:
 *  (1) キーワードのアラート … この画面から追加／一覧から削除できる。
 *  (2) 設定済みルーム … 部屋詳細で設定した増減アラートの一覧。ここから個別解除できる。
 *  (3) マイリスト変動アラート … 全体／ルート直下のみ／特定フォルダ配下 の3スコープ。
 *      しきい値は部屋詳細と同じ %/人数 プルダウン（ThresholdInput）を使い回す。
 *
 * 保存は PUT 全置き換え。configToRequest(config) を土台に、編集した keywords / rooms /
 * mylistThreshold を上書きして送る。
 */
export default function WatchSettingsPage() {
  const navigate = useNavigate()
  const { config, isLoading, save } = useAlertsConfig()

  // ローカル編集状態
  const [keywords, setKeywords] = useState<AlertsConfigRequestKeyword[]>([])
  const [rooms, setRooms] = useState<AlertsConfigRequestRoom[]>([])

  // キーワード追加フォーム
  const [newKeyword, setNewKeyword] = useState('')
  const [newCategory, setNewCategory] = useState(0)

  // マイリスト変動
  const [mylistEnabled, setMylistEnabled] = useState(false)
  const [mylistScope, setMylistScope] = useState<MylistAlertScope>('all')
  const [mylistFolderId, setMylistFolderId] = useState<string | null>(null)
  const [mylistValue, setMylistValue] = useState('10')
  const [mylistUnit, setMylistUnit] = useState<ThresholdUnit>('percent')

  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState(false)

  // マイリスト本体（localStorage）。フォルダ選択肢とスコープ解決に使う。
  const [myList] = useState(() => loadMyList())
  const myListEmpty = myList.items.length === 0

  // 設定済みルームの名前解決（batchStats）。id => name。
  const [roomNames, setRoomNames] = useState<Record<number, string>>({})

  // config 到着時にローカル状態を初期化
  useEffect(() => {
    if (!config) return
    const req = configToRequest(config)
    setKeywords(req.keywords ?? [])
    setRooms(req.rooms ?? [])
    const mt = config.mylistThreshold
    setMylistEnabled(mt.enabled)
    setMylistScope(mt.scope ?? 'all')
    // しきい値: up 側を代表に。人数があれば人、なければ％（既定）。
    if (mt.up_member != null) {
      setMylistUnit('member')
      setMylistValue(String(mt.up_member))
    } else if (mt.up_percent != null) {
      setMylistUnit('percent')
      setMylistValue(String(mt.up_percent))
    }
    setSaveError(false)
  }, [config])

  // 設定済みルームの名前を取得（id 集合が変わったら）。
  const roomIdsKey = rooms.map((r) => r.openChatId).join(',')
  useEffect(() => {
    const ids = rooms.map((r) => r.openChatId).filter((id) => id > 0)
    if (ids.length === 0) {
      setRoomNames({})
      return
    }
    let cancelled = false
    alphaApi
      .batchStats(ids)
      .then((res) => {
        if (cancelled) return
        const map: Record<number, string> = {}
        for (const oc of res.data) map[oc.id] = oc.name
        setRoomNames(map)
      })
      .catch(() => {})
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [roomIdsKey])

  // フォルダ選択肢（フラット。名前順）。
  const folderOptions = useMemo(
    () => [...myList.folders].sort((a, b) => a.name.localeCompare(b.name, 'ja')),
    [myList.folders],
  )

  const addKeywordLocal = () => {
    const kw = newKeyword.trim().slice(0, 190)
    if (!kw) return
    const cat = newCategory || null
    if (keywords.some((k) => k.keyword === kw && (k.category ?? null) === cat)) {
      setNewKeyword('')
      return
    }
    setKeywords((prev) => [...prev, { keyword: kw, category: cat }])
    setNewKeyword('')
    setNewCategory(0)
  }

  const removeKeyword = (idx: number) =>
    setKeywords((prev) => prev.filter((_, i) => i !== idx))

  const removeRoom = (openChatId: number) =>
    setRooms((prev) => prev.filter((r) => r.openChatId !== openChatId))

  // 設定済みルームのしきい値の表示文（±N人/％）。
  const roomThresholdLabel = (r: AlertsConfigRequestRoom): string => {
    if (r.upMember != null) return `±${r.upMember}人`
    if (r.upPercent != null) return `±${r.upPercent}％`
    if (r.downMember != null) return `±${r.downMember}人`
    if (r.downPercent != null) return `±${r.downPercent}％`
    return '設定済み'
  }

  const handleSave = async () => {
    if (!config) return
    setSaving(true)
    setSaveError(false)
    try {
      const req = configToRequest(config)
      const n = toPositive(mylistValue)
      // scope!=='all' のときだけ対象集合を解決して送る。
      const targetOcIds =
        mylistScope === 'all'
          ? undefined
          : resolveScopeOcIds(myList, mylistScope, mylistFolderId)
      await save({
        ...req,
        keywords,
        rooms,
        mylistThreshold: {
          enabled: mylistEnabled,
          scope: mylistScope,
          ...(targetOcIds ? { targetOcIds } : {}),
          ...thresholdToRequest(n, mylistUnit),
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
    <div>
      {/* (固定) ヘッダーバー: 最終更新ヒント＋保存。z は header(60) と衝突しない subheader。 */}
      <div className="sticky top-0 z-subheader -mx-3 mb-4 flex items-center gap-2 border-b bg-background/95 px-3 py-2 backdrop-blur md:-mx-6 md:px-6">
        <p className="text-xs text-muted-foreground">
          設定は毎時のデータ更新後に反映されます
        </p>
        <div className="ml-auto flex items-center gap-2">
          {saveError && <span className="text-xs text-destructive">保存に失敗</span>}
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate(-1)}
            disabled={saving}
          >
            キャンセル
          </Button>
          <Button size="sm" onClick={handleSave} disabled={saving || isLoading} data-testid="watch-save">
            {saving ? '保存中…' : '保存'}
          </Button>
        </div>
      </div>

      {isLoading && !config ? (
        <div className="flex justify-center py-12">
          <div className="h-7 w-7 animate-spin rounded-full border-b-2 border-primary" />
        </div>
      ) : (
        <div className="space-y-6">
          {/* (1) キーワードのアラート */}
          <Section
            icon={<Sparkles className="h-4 w-4 text-primary" />}
            title="キーワードのアラート"
            description="指定したキーワードを含む新しい部屋が見つかると通知します。"
          >
            {/* 追加フォーム */}
            <div className="flex flex-wrap items-center gap-2">
              <Input
                value={newKeyword}
                onChange={(e) => setNewKeyword(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') addKeywordLocal()
                }}
                placeholder="キーワードを追加"
                maxLength={190}
                className="h-10 min-w-[8rem] flex-1"
                data-testid="watch-keyword-input"
              />
              <Select value={String(newCategory)} onValueChange={(v) => setNewCategory(Number(v))}>
                <SelectTrigger className="h-10 w-32" data-testid="watch-keyword-category">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {CATEGORIES.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.id === 0 ? '全カテゴリ' : c.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button
                type="button"
                size="default"
                className="h-10 gap-1.5"
                onClick={addKeywordLocal}
                disabled={!newKeyword.trim()}
                data-testid="watch-keyword-add"
              >
                <Plus className="h-4 w-4" />
                追加
              </Button>
            </div>

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
                アラートを設定したキーワードはまだありません。上の入力欄か、検索画面から追加できます。
              </EmptyHint>
            )}
          </Section>

          {/* (2) 設定済みルーム */}
          <Section
            icon={<Bell className="h-4 w-4 text-primary" />}
            title="設定済みルーム"
            description="部屋詳細で設定した増減アラートの一覧です。ここから個別に解除できます。"
          >
            {rooms.length > 0 ? (
              <ul className="space-y-1.5">
                {rooms.map((r) => (
                  <li
                    key={r.openChatId}
                    className="flex items-center gap-2 rounded-md border bg-card px-3 py-2 text-sm"
                  >
                    <Link
                      to={`/openchat/${r.openChatId}`}
                      className="truncate font-medium text-primary underline-offset-4 hover:underline"
                    >
                      {roomNames[r.openChatId] ?? `ID: ${r.openChatId}`}
                    </Link>
                    <span className="flex-shrink-0 text-xs text-muted-foreground tabular-nums">
                      {roomThresholdLabel(r)}で通知
                    </span>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="ml-auto h-10 w-10 flex-shrink-0"
                      onClick={() => removeRoom(r.openChatId)}
                      aria-label="解除"
                      data-testid="watch-room-remove-list"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </li>
                ))}
              </ul>
            ) : (
              <EmptyHint>
                増減アラートを設定した部屋はまだありません。部屋の詳細画面の「増減アラート」から設定できます。
              </EmptyHint>
            )}
          </Section>

          {/* (3) マイリスト変動アラート */}
          <Section
            icon={<ListChecks className="h-4 w-4 text-primary" />}
            title="マイリスト変動アラート"
            description="マイリストの部屋に、設定したしきい値を超える増減があれば通知します。"
          >
            {myListEmpty && (
              <EmptyHint>
                マイリストに部屋を追加すると有効になります。
                <Link to="/mylist" className="ml-1 font-medium text-primary underline-offset-4 hover:underline">
                  マイリストを開く
                </Link>
              </EmptyHint>
            )}
            <label className={`flex items-center gap-2 text-sm ${myListEmpty ? 'opacity-50' : ''}`}>
              <Checkbox
                checked={mylistEnabled}
                disabled={myListEmpty}
                onCheckedChange={(c) => setMylistEnabled(c === true)}
                data-testid="watch-mylist-enabled"
              />
              マイリストの部屋の増減を通知する
            </label>

            {/* スコープ選択 */}
            <div className="mt-3 space-y-1">
              <span className="text-xs text-muted-foreground">対象</span>
              <Select
                value={mylistScope}
                disabled={myListEmpty || !mylistEnabled}
                onValueChange={(v) => setMylistScope(v as MylistAlertScope)}
              >
                <SelectTrigger className="h-10" data-testid="watch-mylist-scope">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">マイリスト全体</SelectItem>
                  <SelectItem value="root">ルート直下のみ（フォルダ未分類）</SelectItem>
                  <SelectItem value="folder">特定のフォルダ</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {/* フォルダ選択（scope=folder のときだけ） */}
            {mylistScope === 'folder' && (
              <div className="mt-3 space-y-1">
                <span className="text-xs text-muted-foreground">フォルダ</span>
                <Select
                  value={mylistFolderId ?? ''}
                  disabled={myListEmpty || !mylistEnabled}
                  onValueChange={(v) => setMylistFolderId(v || null)}
                >
                  <SelectTrigger className="h-10" data-testid="watch-mylist-folder">
                    <SelectValue placeholder="フォルダを選択" />
                  </SelectTrigger>
                  <SelectContent>
                    {folderOptions.length === 0 ? (
                      <SelectItem value="__none" disabled>
                        フォルダがありません
                      </SelectItem>
                    ) : (
                      folderOptions.map((f) => (
                        <SelectItem key={f.id} value={f.id}>
                          {f.name}
                        </SelectItem>
                      ))
                    )}
                  </SelectContent>
                </Select>
              </div>
            )}

            {/* しきい値（部屋詳細と同じ %/人数 プルダウンを使い回す） */}
            <div className="mt-3">
              <ThresholdInput
                value={mylistValue}
                unit={mylistUnit}
                disabled={myListEmpty || !mylistEnabled}
                onValueChange={setMylistValue}
                onUnitChange={setMylistUnit}
                ariaPrefix="マイリスト変動の"
              />
              <p className="mt-1.5 text-xs text-muted-foreground">
                {mylistUnit === 'percent'
                  ? `各部屋が ±${toPositive(mylistValue) ?? '—'}％ を超えて増減したら通知します。`
                  : `各部屋が ±${toPositive(mylistValue) ?? '—'}人 を超えて増減したら通知します。`}
              </p>
              <p className="mt-1 text-xs text-muted-foreground">
                ※ 部屋ごとに個別の増減アラートを設定している場合は、そちらが優先され重複通知しません。
              </p>
            </div>
          </Section>
        </div>
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
