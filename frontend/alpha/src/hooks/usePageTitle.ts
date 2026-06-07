import { useEffect, useState } from 'react'
import { useLocation, useSearchParams } from 'react-router-dom'
import { useLayout } from '@/contexts/layout-context'
import type { DetailTitle } from '@/contexts/layout-context'
import { loadMyList } from '@/services/storage'
import { UNIFIED_SORT_OPTIONS } from '@/lib/sort-options'
import { categoryName } from '@/lib/categories'

/**
 * 現在のルートからヘッダーのページタイトルを導出するフック。
 *
 * 旧 DashboardLayout は getPageTitle()/getDetailPageTitle() と
 * sessionStorage 変更検知をコンポーネント内に抱えていた。それをここへ集約する。
 * - detailTitle は Context（DetailPage が設定）から取得
 * - マイリストの件数表示は localStorage 変更で再描画させる
 */
export function usePageTitle(): { pageTitle: string; detailTitle: DetailTitle | null } {
  const { pathname } = useLocation()
  const [searchParams] = useSearchParams()
  const { detailTitle: ctxDetailTitle } = useLayout()
  const [, forceRerender] = useState(0)

  // マイリスト件数の表示更新（他タブ/別経路での localStorage 変更を反映）
  useEffect(() => {
    const onChange = () => forceRerender((n) => n + 1)
    window.addEventListener('storage', onChange)
    return () => window.removeEventListener('storage', onChange)
  }, [])

  const detailTitle = pathname.startsWith('/openchat/') ? ctxDetailTitle : null

  const pageTitle = computeTitle(pathname, searchParams, detailTitle)

  return { pageTitle, detailTitle }
}

function computeTitle(
  pathname: string,
  searchParams: URLSearchParams,
  detailTitle: DetailTitle | null,
): string {
  if (pathname.startsWith('/openchat/')) {
    // 人数表示は詳細画面の DetailStats に一本化。ヘッダは部屋名のみ。
    return detailTitle ? detailTitle.name : 'オープンチャット'
  }

  if (pathname === '/mylist' || pathname.startsWith('/mylist/')) {
    const data = loadMyList()
    const folderId = pathname.startsWith('/mylist/') ? pathname.replace('/mylist/', '') : null
    if (folderId) {
      const folder = data.folders.find((f) => f.id === folderId)
      const count = data.items.filter((item) => item.folderId === folderId).length
      return folder ? `${folder.name} ${count}件` : `マイリスト ${data.items.length}件`
    }
    return `マイリスト ${data.items.length}件`
  }

  if (pathname === '/settings') return '設定'
  if (pathname === '/analysis') return '分析'
  if (pathname === '/notifications') return '通知'
  if (pathname === '/period-growth') return '指定期間の増減ランキング'
  if (pathname === '/watch') return 'アラート設定'
  if (pathname === '/labs') return 'アクセス・流入ランキング'

  if (pathname === '/') {
    const keyword = searchParams.get('q')
    const sort = searchParams.get('sort') || 'member'
    const order = searchParams.get('order') || 'desc'
    const label = UNIFIED_SORT_OPTIONS.find((o) => o.value === sort && o.order === order)?.label ?? '人数降順'
    if (keyword) {
      return `「${keyword}」の検索結果 - ${label}`
    }
    const cat = Number(searchParams.get('category') || '0')
    if (cat !== 0) {
      return `カテゴリ「${categoryName(cat)}」 - ${label}`
    }
    return 'オプチャグラフα'
  }

  return 'オプチャグラフα'
}
