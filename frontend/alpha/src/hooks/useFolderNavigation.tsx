import { useCallback } from 'react'
import { useNavigate } from 'react-router-dom'

export interface UseFolderNavigationReturn {
  currentFolderId: string | null
  navigateToFolder: (folderId: string | null) => void
  resetNavigation: () => void
}

/**
 * フォルダナビゲーションフック
 *
 * URLをフォルダ状態の唯一の真実の情報源として扱います。
 * （旧「最後にいたフォルダ」の sessionStorage 記憶は、タブ進入を常にルートへ
 * 破棄する挙動になったため廃止。）
 *
 * @param currentFolderId - URLから取得した現在のフォルダID（useParams経由）
 */
export function useFolderNavigation(currentFolderId: string | null | undefined): UseFolderNavigationReturn {
  const navigate = useNavigate()

  const navigateToFolder = useCallback((folderId: string | null) => {
    if (folderId) {
      navigate(`/mylist/${folderId}`)
    } else {
      navigate('/mylist')
    }
  }, [navigate])

  const resetNavigation = useCallback(() => {
    navigate('/mylist')
  }, [navigate])

  return {
    currentFolderId: currentFolderId ?? null,
    navigateToFolder,
    resetNavigation,
  }
}
