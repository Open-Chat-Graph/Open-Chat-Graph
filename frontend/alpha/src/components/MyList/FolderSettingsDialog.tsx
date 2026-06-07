import { useState, useEffect } from 'react'
import { Sparkles, Bell, Loader2 } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
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
import { ThresholdInput, type ThresholdUnit } from '@/components/Notifications/ThresholdInput'
import { CATEGORIES } from '@/lib/categories'
import { useBackDismiss } from '@/hooks/useBackDismiss'
import { alphaApi } from '@/api/alpha'
import type { Folder } from '@/types/storage'

interface FolderSettingsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  folder: Folder
  /** 設定保存後に呼ばれる。hasRule が変わったときにフォルダ行アイコンを更新するため */
  onSaved?: (folderId: string, hasRule: boolean) => void
  /** マイリストの再同期（autoAdded 反映用） */
  onResync?: () => Promise<void>
}

/** 入力欄文字列を「正の有限数 or null」に。空・非数・0以下は null。 */
const toPositive = (s: string): number | null => {
  const t = s.trim()
  if (t === '') return null
  const n = Number(t)
  return Number.isFinite(n) && n > 0 ? n : null
}

/** しきい値(値・単位)を threshold の up/down 4列へ。null は全クリア。 */
const buildThreshold = (
  n: number | null,
  unit: ThresholdUnit,
): { upPercent: number | null; downPercent: number | null; upMember: number | null; downMember: number | null } => {
  if (n == null) {
    return { upPercent: null, downPercent: null, upMember: null, downMember: null }
  }
  return unit === 'member'
    ? { upMember: n, downMember: n, upPercent: null, downPercent: null }
    : { upPercent: n, downPercent: n, upMember: null, downMember: null }
}

/**
 * フォルダ設定ダイアログ。
 * - 自動追加ルール（スマートフォルダ）: キーワード＋カテゴリ＋有効スイッチ
 * - 増減アラートしきい値: %/人数（ThresholdInput 再利用）
 *
 * 保存成功時: autoAdded>0 ならフィードバック表示 → マイリスト再同期。
 * ブラウザバックで閉じる（useBackDismiss）。
 */
export function FolderSettingsDialog({
  open,
  onOpenChange,
  folder,
  onSaved,
  onResync,
}: FolderSettingsDialogProps) {
  // ローカル編集状態
  const [ruleEnabled, setRuleEnabled] = useState(false)
  const [keyword, setKeyword] = useState('')
  const [category, setCategory] = useState(0)

  const [thresholdEnabled, setThresholdEnabled] = useState(false)
  const [thresholdValue, setThresholdValue] = useState('10')
  const [thresholdUnit, setThresholdUnit] = useState<ThresholdUnit>('percent')

  // 自動追加ON でキーワード未入力のまま保存しようとしたときのクライアント側エラー
  const [keywordError, setKeywordError] = useState(false)

  // 通信状態
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState(false)
  const [autoAdded, setAutoAdded] = useState<number | null>(null)

  // ブラウザバックで閉じる
  useBackDismiss(open, () => onOpenChange(false))

  // ダイアログが開いたらサーバから現在の設定を取得
  useEffect(() => {
    if (!open) {
      // 閉じたときにリセット
      setAutoAdded(null)
      setSaveError(false)
      setKeywordError(false)
      return
    }

    setLoading(true)
    alphaApi
      .getFolderSettings(folder.id)
      .then((res) => {
        if (res.rule) {
          setRuleEnabled(res.rule.enabled)
          setKeyword(res.rule.keyword)
          setCategory(res.rule.category ?? 0)
        } else {
          setRuleEnabled(false)
          setKeyword('')
          setCategory(0)
        }
        if (res.threshold) {
          setThresholdEnabled(res.threshold.enabled)
          // up_member / up_percent の優先で代表値を設定
          if (res.threshold.upMember != null) {
            setThresholdUnit('member')
            setThresholdValue(String(res.threshold.upMember))
          } else if (res.threshold.upPercent != null) {
            setThresholdUnit('percent')
            setThresholdValue(String(res.threshold.upPercent))
          } else {
            setThresholdValue('10')
            setThresholdUnit('percent')
          }
        } else {
          setThresholdEnabled(false)
          setThresholdValue('10')
          setThresholdUnit('percent')
        }
      })
      .catch(() => {
        // 取得失敗は無視してデフォルト値のまま
      })
      .finally(() => {
        setLoading(false)
      })
  }, [open, folder.id])

  const handleSave = async () => {
    // 自動追加ON なのにキーワードが空なら、サーバへ投げずにその場で理由を示す
    if (ruleEnabled && keyword.trim() === '') {
      setKeywordError(true)
      return
    }
    setSaving(true)
    setSaveError(false)
    setAutoAdded(null)
    try {
      const n = toPositive(thresholdValue)
      const body = {
        rule: ruleEnabled
          ? {
              keyword: keyword.trim().slice(0, 190),
              category: category || null,
              enabled: true,
            }
          : keyword.trim()
          ? { keyword: keyword.trim().slice(0, 190), category: category || null, enabled: false }
          : null,
        threshold: thresholdEnabled
          ? { ...buildThreshold(n, thresholdUnit), enabled: true }
          : null,
      }
      const result = await alphaApi.putFolderSettings(folder.id, body)
      setAutoAdded(result.autoAdded)

      // hasRule を localStorage のフォルダに書き戻す
      const hasRule = !!(body.rule?.enabled)
      onSaved?.(folder.id, hasRule)

      // autoAdded があればマイリストを再同期（サーバが追加した部屋をローカルに反映）
      if (result.autoAdded > 0 && onResync) {
        await onResync()
      }
    } catch {
      setSaveError(true)
    } finally {
      setSaving(false)
    }
  }

  const handleClose = () => {
    onOpenChange(false)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[480px] max-w-[calc(100%-2rem)] !top-[20vh] !-translate-y-0 !translate-y-0 !p-4 overflow-y-auto max-h-[80vh]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Sparkles className="h-4 w-4 text-primary" />
            フォルダ設定: {folder.name}
          </DialogTitle>
        </DialogHeader>

        {loading ? (
          <div className="flex justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : (
          <div className="space-y-5 py-1">
            {/* 自動追加ルール */}
            <section className="space-y-3">
              <div className="flex items-center gap-2">
                <Sparkles className="h-4 w-4 text-primary flex-shrink-0" />
                <h3 className="text-sm font-semibold">自動追加ルール</h3>
              </div>
              <p className="text-xs text-muted-foreground">
                キーワードに一致する新着オプチャを毎時自動で追加します。設定時には現在の一致部屋（人数上位50件）もすぐ追加されます。
              </p>

              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={ruleEnabled}
                  onCheckedChange={(c) => {
                    setRuleEnabled(c === true)
                    if (c !== true) setKeywordError(false)
                  }}
                  data-testid="folder-rule-enabled"
                />
                自動追加を有効にする
              </label>

              <div className="space-y-1">
                <Label htmlFor="folder-keyword" className="text-xs text-muted-foreground">
                  キーワード
                </Label>
                <Input
                  id="folder-keyword"
                  value={keyword}
                  onChange={(e) => {
                    setKeyword(e.target.value)
                    if (keywordError) setKeywordError(false)
                  }}
                  placeholder="キーワードを入力"
                  maxLength={190}
                  disabled={!ruleEnabled}
                  className="!text-base md:!text-sm"
                  aria-invalid={keywordError || undefined}
                  data-testid="folder-rule-keyword"
                />
                {keywordError && (
                  <p className="text-xs text-destructive" data-testid="folder-rule-keyword-error">
                    キーワードを入力してください
                  </p>
                )}
              </div>

              <div className="space-y-1">
                <Label className="text-xs text-muted-foreground">カテゴリ</Label>
                <Select
                  value={String(category)}
                  disabled={!ruleEnabled}
                  onValueChange={(v) => setCategory(Number(v))}
                >
                  <SelectTrigger className="h-10 !text-base md:!text-sm" data-testid="folder-rule-category">
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
              </div>
            </section>

            {/* 増減アラート（フォルダ全体ではなく配下の各部屋ごとに判定する） */}
            <section className="space-y-3">
              <div className="flex items-center gap-2">
                <Bell className="h-4 w-4 text-primary flex-shrink-0" />
                <h3 className="text-sm font-semibold">フォルダ内の各部屋の増減を通知</h3>
              </div>
              <p className="text-xs text-muted-foreground">
                フォルダ配下のどれかの部屋が条件を超えたら通知します。
              </p>

              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={thresholdEnabled}
                  onCheckedChange={(c) => setThresholdEnabled(c === true)}
                  data-testid="folder-threshold-enabled"
                />
                このフォルダの増減を通知する
              </label>

              <div className={thresholdEnabled ? '' : 'opacity-50 pointer-events-none'}>
                <ThresholdInput
                  value={thresholdValue}
                  unit={thresholdUnit}
                  disabled={!thresholdEnabled}
                  onValueChange={setThresholdValue}
                  onUnitChange={setThresholdUnit}
                  ariaPrefix="フォルダ増減の"
                />
                <p className="mt-1.5 text-xs text-muted-foreground">
                  {thresholdUnit === 'percent'
                    ? `各部屋が ±${toPositive(thresholdValue) ?? '—'}％ を超えて増減したら通知します。`
                    : `各部屋が ±${toPositive(thresholdValue) ?? '—'}人 を超えて増減したら通知します。`}
                </p>
              </div>
            </section>

            {/* 保存後フィードバック */}
            {autoAdded !== null && autoAdded > 0 && (
              <p className="rounded-md bg-primary/10 px-3 py-2 text-sm text-primary" data-testid="folder-auto-added">
                {autoAdded}件の部屋を追加しました
              </p>
            )}
            {autoAdded === 0 && (
              <p className="text-xs text-muted-foreground" data-testid="folder-auto-added-zero">
                現在一致する新規部屋はありませんでした。
              </p>
            )}
          </div>
        )}

        <DialogFooter className="!flex-row justify-between items-center gap-2">
          <div />
          <div className="flex gap-2 items-center">
            {saveError && (
              <span className="text-xs text-destructive" data-testid="folder-settings-error">
                保存に失敗しました
              </span>
            )}
            <Button variant="outline" onClick={handleClose} disabled={saving}>
              {autoAdded !== null ? '閉じる' : 'キャンセル'}
            </Button>
            <Button
              onClick={handleSave}
              disabled={saving || loading}
              data-testid="folder-settings-save"
            >
              {saving ? (
                <>
                  <Loader2 className="mr-1 h-3 w-3 animate-spin" />
                  保存中…
                </>
              ) : '保存'}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
