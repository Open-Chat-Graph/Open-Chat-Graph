import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Folder as FolderIcon, FolderOpen } from 'lucide-react'
import type { Folder } from '@/types/storage'

interface FolderSelectDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  folders: Folder[]
  onSelect: (folderId: string | null) => void
  title: string
}

export function FolderSelectDialog({
  open,
  onOpenChange,
  folders,
  onSelect,
  title,
}: FolderSelectDialogProps) {
  const handleSelect = (folderId: string | null) => {
    onSelect(folderId)
    onOpenChange(false)
  }

  // フォルダ階層を再帰的に取得
  const getFolderHierarchy = (parentId: string | null, depth: number = 0): React.ReactElement[] => {
    const childFolders = folders.filter((f) => f.parentId === parentId).sort((a, b) => a.order - b.order)

    return childFolders.flatMap((folder) => {
      const indent = '　'.repeat(depth)
      return [
        <button
          key={folder.id}
          onClick={() => handleSelect(folder.id)}
          className="flex items-center gap-2 w-full p-3 hover:bg-accent rounded-md text-left transition-colors group"
        >
          <FolderIcon className="h-4 w-4 text-primary flex-shrink-0" />
          <span className="flex-1">
            {indent}
            {folder.name}
          </span>
          <span className="text-sm text-muted-foreground group-hover:text-foreground transition-colors">
            選択
          </span>
        </button>,
        ...getFolderHierarchy(folder.id, depth + 1),
      ]
    })
  }

  const folderElements = getFolderHierarchy(null)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>保存先のフォルダを選択してください</DialogDescription>
        </DialogHeader>
        <div className="max-h-[400px] overflow-y-auto pr-4">
          <div className="space-y-1">
            {/* ルートフォルダ（フォルダなし） */}
            <button
              onClick={() => handleSelect(null)}
              className="flex items-center gap-2 w-full p-3 hover:bg-accent rounded-md text-left transition-colors group"
            >
              <FolderOpen className="h-4 w-4 text-primary flex-shrink-0" />
              <span className="flex-1">フォルダなし（ルート）</span>
              <span className="text-sm text-muted-foreground group-hover:text-foreground transition-colors">
                選択
              </span>
            </button>

            {/* フォルダ階層 */}
            {folderElements}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
