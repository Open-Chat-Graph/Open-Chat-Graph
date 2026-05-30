import { memo } from 'react'

/**
 * 任意のN日増減ビュー（プレースホルダ）。
 * 実装は担当エージェントBがこのファイルとUI部品で行う。
 * 仕様: キーワード(必須)＋カテゴリ＋N日＋昇降順で、
 * 「N日前と現在の両方に統計があるルーム」に絞った増減ランキングを表示する。
 */
const PeriodGrowthPage = memo(() => {
  return (
    <div className="p-3 md:p-6">
      <p className="text-sm text-muted-foreground">準備中…</p>
    </div>
  )
})

PeriodGrowthPage.displayName = 'PeriodGrowthPage'

export default PeriodGrowthPage
