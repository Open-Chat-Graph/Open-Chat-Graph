import { memo } from 'react'

/**
 * フォルダ統合グラフ（プレースホルダ）。
 * 実装は担当エージェントが行う。
 * 仕様: マイリストのフォルダ配下の全ルームの人数/ランキング線を1グラフに重ね、
 * 下のリストでチェックを外すと線が消せる。標準機能（本家へのメタ）。
 */
const FolderChartPage = memo(() => {
  return (
    <div>
      <p className="text-sm text-muted-foreground">準備中…</p>
    </div>
  )
})

FolderChartPage.displayName = 'FolderChartPage'

export default FolderChartPage
