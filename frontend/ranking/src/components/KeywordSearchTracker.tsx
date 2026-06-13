import { useEffect, useRef } from 'react'
import { useAtomValue } from 'jotai'
import { keywordState } from '../store/atom'
import { trackEvent } from '../utils/track'

// キーワード検索が実行されたとき(keywordが空→非空、または値が変化)に1回だけ計測する。
// トップフォームからのGET着地・URL直アクセス・アプリ内検索の全経路を網羅し、
// カテゴリ/並び替え変更(keyword不変)やクリア(空)では撃たない。検索語は search_term に入れる。
export default function KeywordSearchTracker() {
  const keyword = useAtomValue(keywordState)
  const prevRef = useRef<string | null>(null)

  useEffect(() => {
    if (keyword && keyword !== prevRef.current) {
      trackEvent('keyword_search', { search_term: keyword })
    }
    prevRef.current = keyword
  }, [keyword])

  return null
}
