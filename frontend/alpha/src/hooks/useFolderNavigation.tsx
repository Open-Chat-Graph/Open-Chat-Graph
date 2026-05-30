import { useCallback } from 'react'
import { useNavigate } from 'react-router-dom'

const STORAGE_KEY = 'alpha_mylist_current_folder'

export interface UseFolderNavigationReturn {
  currentFolderId: string | null
  navigateToFolder: (folderId: string | null) => void
  resetNavigation: () => void
}

/**
 * フォルダナビゲーションフック
 *
 * URLをフォルダ状態の真実の情報源として扱い、sessionStorageは
 * 「最後にいたフォルダ」を記憶するためだけに使用します。
 *
 * @param currentFolderId - URLから取得した現在のフォルダID（useParams経由）
 */
export function useFolderNavigation(currentFolderId: string | null | undefined): UseFolderNavigationReturn {
  const navigate = useNavigate()

  const navigateToFolder = useCallback((folderId: string | null) => {
    // sessionStorageに最後のフォルダを保存（メニューから戻るため）
    if (folderId) {
      sessionStorage.setItem(STORAGE_KEY, folderId)
      navigate(`/mylist/${folderId}`)
    } else {
      sessionStorage.removeItem(STORAGE_KEY)
      navigate('/mylist')
    }
  }, [navigate])

  const resetNavigation = useCallback(() => {
    sessionStorage.removeItem(STORAGE_KEY)
    navigate('/mylist')
  }, [navigate])

  return {
    currentFolderId: currentFolderId ?? null,
    navigateToFolder,
    resetNavigation,
  }
}

/**
 * sessionStorageから最後にいたフォルダIDを取得
 * メニューからマイリストに戻る際に使用
 */
export function getLastFolderId(): string | null {
  return sessionStorage.getItem(STORAGE_KEY)
}
