import React, { memo } from 'react'
import {
  Breadcrumb,
  BreadcrumbList,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbSeparator,
  BreadcrumbPage,
} from '@/components/ui/breadcrumb'
import type { Folder } from '@/types/storage'

interface FolderBreadcrumbProps {
  currentFolderId: string | null
  folders: Folder[]
  onNavigate: (folderId: string | null) => void
}

// 指定されたfolderIdまでのパスを取得
function getFolderPath(folderId: string | null, folders: Folder[]): Folder[] {
  if (!folderId) return []

  const path: Folder[] = []
  let currentId: string | null = folderId

  while (currentId) {
    const folder = folders.find(f => f.id === currentId)
    if (!folder) break

    path.unshift(folder) // 先頭に追加（逆順）
    currentId = folder.parentId
  }

  return path
}

export const FolderBreadcrumb = memo(({ currentFolderId, folders, onNavigate }: FolderBreadcrumbProps) => {
  const path = getFolderPath(currentFolderId, folders)

  return (
    <Breadcrumb data-testid="breadcrumb">
      <BreadcrumbList>
        {/* ルート（マイリスト） */}
        <BreadcrumbItem>
          {currentFolderId === null ? (
            <BreadcrumbPage>マイリスト</BreadcrumbPage>
          ) : (
            <BreadcrumbLink
              onClick={() => onNavigate(null)}
              data-testid="breadcrumb-root"
            >
              マイリスト
            </BreadcrumbLink>
          )}
        </BreadcrumbItem>

        {/* パス内のフォルダ */}
        {path.map((folder, index) => {
          const isLast = index === path.length - 1

          return (
            <React.Fragment key={folder.id}>
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                {isLast ? (
                  <BreadcrumbPage>{folder.name}</BreadcrumbPage>
                ) : (
                  <BreadcrumbLink
                    onClick={() => onNavigate(folder.id)}
                    data-testid={`breadcrumb-${folder.id}`}
                  >
                    {folder.name}
                  </BreadcrumbLink>
                )}
              </BreadcrumbItem>
            </React.Fragment>
          )
        })}
      </BreadcrumbList>
    </Breadcrumb>
  )
})

FolderBreadcrumb.displayName = 'FolderBreadcrumb'
