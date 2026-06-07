import { useState, useEffect } from 'react'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useBackDismiss } from '@/hooks/useBackDismiss'
import type { Folder } from '@/types/storage'

interface FolderDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  folder?: Folder
  folders: Folder[]
  onSave: (name: string, parentId: string | null) => void
  onDelete?: () => void
  mode: 'create' | 'edit'
}

export function FolderDialog({
  open,
  onOpenChange,
  folder,
  onSave,
  onDelete,
  mode,
}: FolderDialogProps) {
  const [name, setName] = useState('')

  // ブラウザバックで閉じる（アプリ全体の統一挙動。共通フックに集約）
  useBackDismiss(open, () => onOpenChange(false))

  useEffect(() => {
    if (open) {
      if (mode === 'edit' && folder) {
        setName(folder.name)
      } else {
        setName('')
      }
    }
  }, [open, mode, folder])

  const handleSave = () => {
    if (name.trim()) {
      // 常にparentIdはnullで保存（フラットな構造）
      onSave(name.trim(), null)
      onOpenChange(false)
    }
  }

  const handleDelete = () => {
    if (onDelete) {
      onDelete()
      onOpenChange(false)
    }
  }

  const handleCancel = () => {
    onOpenChange(false)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px] max-w-[calc(100%-2rem)] !top-[25vh] !-translate-y-1/2 !p-4">
        <DialogHeader>
          <DialogTitle>
            {mode === 'create' ? 'フォルダを作成' : 'フォルダ名の変更'}
          </DialogTitle>
        </DialogHeader>
        <div className="grid gap-2 py-2">
          <div className="grid gap-2">
            <Label htmlFor="name">フォルダ名</Label>
            <Input
              id="name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="フォルダ名を入力"
              className="!text-base"
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  handleSave()
                }
              }}
            />
          </div>
          {mode === 'create' && (
            <p className="text-xs text-muted-foreground">
              作成後、キーワードを設定すると一致する新着部屋が自動で入ります
            </p>
          )}
        </div>
        <DialogFooter className="!flex-row justify-between items-center gap-2">
          {/* 削除ボタン（左寄せ） */}
          {mode === 'edit' && onDelete ? (
            <Button
              variant="destructive"
              size="sm"
              onClick={handleDelete}
            >
              フォルダを削除
            </Button>
          ) : (
            <div />
          )}

          {/* キャンセル・保存（右寄せ、横並び） */}
          <div className="flex gap-2">
            <Button variant="outline" onClick={handleCancel}>
              キャンセル
            </Button>
            <Button onClick={handleSave} disabled={!name.trim()}>
              {mode === 'create' ? '作成' : '保存'}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
