import { useEffect, useState } from 'react'
import { Plus, Trash2, Sparkles, Eye, ListChecks } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { CATEGORIES, categoryName } from '@/lib/categories'
import { useBackDismiss } from '@/hooks/useBackDismiss'
import { useAlertsConfig, configToRequest } from './useAlertsConfig'
import type {
  AlertsConfigRequestKeyword,
  AlertsConfigRequestRoom,
} from '@/types/api'

interface WatchSettingsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** number|null を入力欄文字列に。null/0未満は空。 */
const toInput = (v: number | null | undefined): string =>
  v == null ? '' : String(v)
/** 入力欄文字列を number|null に。空や非数値は null。 */
const toNum = (s: string): number | null => {
  const t = s.trim()
  if (t === '') return null
  const n = Number(t)
  return Number.isFinite(n) ? n : null
}

/**
 * ウォッチ条件設定ダイアログ。
 *
 * GET config で初期化したローカル状態を編集し、PUT で全置き換え保存する。
 * 3区分: (a)キーワード見張り (b)部屋ウォッチのしきい値 (c)マイリスト全体のしきい値。
 */
export function WatchSettingsDialog({ open, onOpenChange }: WatchSettingsDialogProps) {
  // ブラウザバックで閉じる（アプリ全体の統一挙動）
  useBackDismiss(open, () => onOpenChange(false))
  const { config, isLoading, save } = useAlertsConfig()

  // ローカル編集状態（保存形→送信形に正規化したもの）
  const [keywords, setKeywords] = useState<AlertsConfigRequestKeyword[]>([])
  const [rooms, setRooms] = useState<AlertsConfigRequestRoom[]>([])
  const [mylistEnabled, setMylistEnabled] = useState(false)
  const [mylistUp, setMylistUp] = useState('')
  const [mylistDown, setMylistDown] = useState('')

  // キーワード追加フォーム
  const [newKeyword, setNewKeyword] = useState('')
  const [newCategory, setNewCategory] = useState('0')

  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState(false)

  // ダイアログを開いた時 / config 到着時にローカル状態を初期化
  useEffect(() => {
    if (!open || !config) return
    const req = configToRequest(config)
    setKeywords(req.keywords ?? [])
    setRooms(req.rooms ?? [])
    setMylistEnabled(req.mylistThreshold?.enabled ?? false)
    setMylistUp(toInput(req.mylistThreshold?.upPercent))
    setMylistDown(toInput(req.mylistThreshold?.downPercent))
    setSaveError(false)
  }, [open, config])

  const addKeywordLocal = () => {
    const kw = newKeyword.trim().slice(0, 190)
    if (!kw) return
    const cat = newCategory === '0' ? null : Number(newCategory)
    const dup = keywords.some((k) => k.keyword === kw && (k.category ?? null) === cat)
    if (!dup) setKeywords((prev) => [...prev, { keyword: kw, category: cat }])
    setNewKeyword('')
    setNewCategory('0')
  }

  const removeKeyword = (idx: number) =>
    setKeywords((prev) => prev.filter((_, i) => i !== idx))

  const removeRoom = (idx: number) =>
    setRooms((prev) => prev.filter((_, i) => i !== idx))

  const updateRoom = (
    idx: number,
    field: keyof Omit<AlertsConfigRequestRoom, 'openChatId'>,
    raw: string,
  ) =>
    setRooms((prev) =>
      prev.map((r, i) => (i === idx ? { ...r, [field]: toNum(raw) } : r)),
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
      onOpenChange(false)
    } catch {
      setSaveError(true)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-h-[85vh] gap-0 overflow-y-auto p-0"
        onOpenAutoFocus={(e) => e.preventDefault()}
      >
        <DialogHeader className="sticky top-0 z-subheader border-b bg-background px-5 py-4">
          <DialogTitle>ウォッチ条件の設定</DialogTitle>
          <DialogDescription>
            条件に合う部屋や増減があると、毎時のデータ更新後に通知でお知らせします。
          </DialogDescription>
        </DialogHeader>

        {isLoading && !config ? (
          <div className="flex justify-center py-12">
            <div className="h-7 w-7 animate-spin rounded-full border-b-2 border-primary" />
          </div>
        ) : (
          <div className="space-y-6 px-5 py-4">
            {/* (a) キーワード見張り */}
            <section className="space-y-3">
              <div className="flex items-center gap-2">
                <Sparkles className="h-4 w-4 text-primary" />
                <h3 className="text-sm font-semibold">キーワード見張り</h3>
              </div>
              <p className="text-xs text-muted-foreground">
                指定したキーワードを含む新しい部屋が見つかると通知します。
              </p>

              <div className="flex flex-col gap-2 sm:flex-row">
                <Input
                  value={newKeyword}
                  maxLength={190}
                  placeholder="キーワード（例: 雑談）"
                  onChange={(e) => setNewKeyword(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault()
                      addKeywordLocal()
                    }
                  }}
                  data-testid="watch-keyword-input"
                />
                <Select value={newCategory} onValueChange={setNewCategory}>
                  <SelectTrigger className="h-10 sm:w-44" data-testid="watch-keyword-category">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {CATEGORIES.map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>
                        {c.id === 0 ? 'カテゴリ指定なし' : c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Button
                  type="button"
                  variant="secondary"
                  className="h-10 flex-shrink-0"
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
                        className="ml-auto h-7 w-7 flex-shrink-0"
                        onClick={() => removeKeyword(i)}
                        aria-label="削除"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="rounded-md border border-dashed px-3 py-3 text-center text-xs text-muted-foreground">
                  見張るキーワードはまだありません
                </p>
              )}
            </section>

            {/* (b) 部屋ウォッチのしきい値 */}
            <section className="space-y-3 border-t pt-5">
              <div className="flex items-center gap-2">
                <Eye className="h-4 w-4 text-primary" />
                <h3 className="text-sm font-semibold">部屋ウォッチ</h3>
              </div>
              <p className="text-xs text-muted-foreground">
                ウォッチ中の部屋で、設定した増減（人数 / 割合）を超えたら通知します。
                部屋の追加は各部屋の詳細画面から行います。
              </p>

              {rooms.length > 0 ? (
                <ul className="space-y-2">
                  {rooms.map((r, i) => (
                    <li key={r.openChatId} className="rounded-md border bg-card p-3">
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-medium text-muted-foreground">
                          ルームID {r.openChatId}
                        </span>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7"
                          onClick={() => removeRoom(i)}
                          aria-label="このウォッチを削除"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                      <div className="mt-2 grid grid-cols-2 gap-x-3 gap-y-2">
                        <ThresholdField
                          label="増加（人数）"
                          value={toInput(r.upMember)}
                          onChange={(v) => updateRoom(i, 'upMember', v)}
                        />
                        <ThresholdField
                          label="増加（％）"
                          value={toInput(r.upPercent)}
                          onChange={(v) => updateRoom(i, 'upPercent', v)}
                        />
                        <ThresholdField
                          label="減少（人数）"
                          value={toInput(r.downMember)}
                          onChange={(v) => updateRoom(i, 'downMember', v)}
                        />
                        <ThresholdField
                          label="減少（％）"
                          value={toInput(r.downPercent)}
                          onChange={(v) => updateRoom(i, 'downPercent', v)}
                        />
                      </div>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="rounded-md border border-dashed px-3 py-3 text-center text-xs text-muted-foreground">
                  ウォッチ中の部屋はまだありません
                </p>
              )}
            </section>

            {/* (c) マイリスト全体のしきい値 */}
            <section className="space-y-3 border-t pt-5">
              <div className="flex items-center gap-2">
                <ListChecks className="h-4 w-4 text-primary" />
                <h3 className="text-sm font-semibold">マイリスト全体</h3>
              </div>
              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={mylistEnabled}
                  onCheckedChange={(c) => setMylistEnabled(c === true)}
                  data-testid="watch-mylist-enabled"
                />
                マイリストのルームの増減を通知する
              </label>
              <div className="grid grid-cols-2 gap-3">
                <ThresholdField
                  label="増加（％）"
                  value={mylistUp}
                  disabled={!mylistEnabled}
                  onChange={setMylistUp}
                />
                <ThresholdField
                  label="減少（％）"
                  value={mylistDown}
                  disabled={!mylistEnabled}
                  onChange={setMylistDown}
                />
              </div>
            </section>
          </div>
        )}

        <div className="sticky bottom-0 z-subheader flex items-center gap-3 border-t bg-background px-5 py-4">
          {saveError && (
            <span className="text-xs text-destructive">保存に失敗しました</span>
          )}
          <Button
            variant="ghost"
            className="ml-auto"
            onClick={() => onOpenChange(false)}
            disabled={saving}
          >
            キャンセル
          </Button>
          <Button onClick={handleSave} disabled={saving || isLoading} data-testid="watch-save">
            {saving ? '保存中…' : '保存'}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
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
