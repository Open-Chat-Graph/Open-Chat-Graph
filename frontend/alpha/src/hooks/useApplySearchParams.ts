import { useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useLayout } from '@/contexts/layout-context'

/**
 * 検索条件の適用を1箇所に集約する（検索バー／最近の検索チップ／保存した検索条件が共用）。
 *
 * - 条件が URL と異なる → URL 遷移だけ行う。SWR キーが変わるので、過去に同じ条件で
 *   検索済みならキャッシュから即描画される（stale-while-revalidate。再フェッチで前面を塞がない）。
 * - 条件が URL と同一 → URL が変わらず SWR キーも変わらないため、searchNonce を bump して
 *   キーを変え強制再フェッチする（「同じキーワードでもう一度検索」の従来挙動）。
 *
 * 旧実装は常に nonce を bump していたため、キャッシュ済みの条件でも毎回フル再フェッチに
 * なっていた（タブ破棄モデルでは「チップから1タップで即再表示」の受け皿が必要）。
 */
export function useApplySearchParams(): (next: URLSearchParams) => void {
  const [searchParams, setSearchParams] = useSearchParams()
  const { triggerSearch } = useLayout()

  return useCallback(
    (next: URLSearchParams) => {
      if (next.toString() === searchParams.toString()) {
        triggerSearch()
      } else {
        setSearchParams(next)
      }
    },
    [searchParams, setSearchParams, triggerSearch],
  )
}
