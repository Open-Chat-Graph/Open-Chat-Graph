import { useCallback } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { getLastFolderId } from './useFolderNavigation'

/**
 * ナビゲーションハンドラーフック
 *
 * アプリケーション全体のナビゲーションロジックを一元管理
 * - 検索クエリの保存・復元
 * - ページ遷移の制御
 */
export function useNavigationHandler() {
  const location = useLocation()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  /**
   * 検索クエリをsessionStorageに保存
   * 検索キーワード（q）だけでなく、ソート設定（sort, order）も保存
   */
  const saveSearchQuery = useCallback(() => {
    const isSearchPage = location.pathname === '/' || location.pathname.startsWith('/?')
    if (isSearchPage) {
      const searchQuery = searchParams.get('q') || ''
      if (searchQuery) {
        // すべてのクエリパラメータを保存
        sessionStorage.setItem('searchPageQuery', searchParams.toString())
      }
    }
  }, [location.pathname, searchParams])

  /**
   * 検索ページへのナビゲーション
   * - 詳細ページからの場合はブラウザバックで元の検索ページに戻る
   * - 検索ページからの場合は空にリセット
   * - 他のページからの場合はクエリを復元
   */
  const navigateToSearch = useCallback((e?: React.MouseEvent) => {
    const isDetailPage = location.pathname.startsWith('/openchat/')

    if (isDetailPage) {
      // 詳細ページから検索ボタン → ブラウザバックで元のページに戻る
      if (e) e.preventDefault()
      if (window.history.state?.idx > 0) {
        navigate(-1)
      } else {
        // 履歴がない場合（直接アクセス）は検索ページに遷移
        const savedParams = sessionStorage.getItem('searchPageQuery')
        if (savedParams) {
          navigate(`/?${savedParams}`)
        } else {
          navigate('/')
        }
      }
    } else if (location.pathname === '/') {
      // 検索ページで検索ボタン → 空の検索に戻る（再レンダリング）
      if (e) e.preventDefault()
      // sessionStorageもクリアして、次回の復元時に空の状態にする
      sessionStorage.removeItem('searchPageQuery')
      navigate('/', { replace: true })
    } else {
      // 他のページから検索ボタン → 検索ページに遷移（クエリを復元）
      if (e) e.preventDefault()
      saveSearchQuery()
      const savedParams = sessionStorage.getItem('searchPageQuery')
      if (savedParams) {
        navigate(`/?${savedParams}`)
      } else {
        navigate('/')
      }
    }
  }, [location.pathname, navigate, saveSearchQuery])

  /**
   * マイリストページへのナビゲーション
   * - 詳細ページからの場合はブラウザバックで元のマイリストに戻る
   * - マイリストページからの場合はルートに戻る（hrefで処理）
   * - 他のページからの場合は最後のフォルダに戻る
   */
  const navigateToMylist = useCallback((e?: React.MouseEvent) => {
    const isDetailPage = location.pathname.startsWith('/openchat/')
    const isMyListPage = location.pathname === '/mylist' || location.pathname.startsWith('/mylist/')

    if (isDetailPage) {
      // 詳細ページからマイリストボタン → ブラウザバックで元のページに戻る
      if (e) e.preventDefault()
      if (window.history.state?.idx > 0) {
        navigate(-1)
      } else {
        // 履歴がない場合（直接アクセス）は最後のフォルダに戻る
        saveSearchQuery()
        const lastFolderId = getLastFolderId()
        if (lastFolderId) {
          navigate(`/mylist/${lastFolderId}`)
        } else {
          navigate('/mylist')
        }
      }
    } else if (isMyListPage) {
      // マイリストページでマイリストボタン → ルートフォルダに戻る（hrefで処理）
      // eが存在しない場合はプログラムから呼ばれているので、直接ナビゲート
      // eが存在する場合は、デフォルトのリンク動作に任せる（preventDefault しない）
      if (!e) {
        navigate('/mylist')
      }
      // eが存在する場合は何もしない（hrefが/mylistなので自動的にルートに遷移）
    } else {
      // 他のページからマイリストボタン → 最後のフォルダに戻る
      if (e) e.preventDefault()
      saveSearchQuery()
      const lastFolderId = getLastFolderId()
      if (lastFolderId) {
        navigate(`/mylist/${lastFolderId}`)
      } else {
        navigate('/mylist')
      }
    }
  }, [location.pathname, navigate, saveSearchQuery])

  /**
   * 設定ページへのナビゲーション
   * 同じページの場合は再レンダリング
   */
  const navigateToSettings = useCallback((e?: React.MouseEvent) => {
    if (e) e.preventDefault()

    if (location.pathname === '/settings') {
      // 設定ページで設定ボタン → 再レンダリング
      navigate('/settings', { replace: true, state: { timestamp: Date.now() } })
    } else {
      // 他のページから設定ボタン → 設定ページに遷移
      saveSearchQuery()
      navigate('/settings')
    }
  }, [location.pathname, navigate, saveSearchQuery])

  /**
   * 通常のページ遷移時のハンドラー
   * 検索ページから他のページへ遷移する場合は検索クエリを保存
   */
  const handleNavigate = useCallback((to: string) => {
    saveSearchQuery()
    navigate(to)
  }, [saveSearchQuery, navigate])

  return {
    navigateToSearch,
    navigateToMylist,
    navigateToSettings,
    handleNavigate,
    saveSearchQuery,
    isSearchPage: location.pathname === '/',
  }
}
