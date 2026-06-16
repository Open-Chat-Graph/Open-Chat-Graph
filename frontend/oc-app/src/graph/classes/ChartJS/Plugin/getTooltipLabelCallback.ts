import { TooltipItem } from 'chart.js'
import OpenChatChart from '../../OpenChatChart'
import { sprintfT, t } from '../../../util/translation'

export default function getTooltipLabelCallback(ocChart: OpenChatChart) {
  return (tooltipItem: TooltipItem<'bar' | 'line'>) => {
    if (tooltipItem.datasetIndex === 1) {
      // 生順位(data.graph2)で圏外判定する。非線形スケールでは外れ値より深い順位がバー値0へ
      // 圧縮され0(圏外)と衝突するため、バー値(raw)では圏外と順位内を区別できない。
      const rank = ocChart.data.graph2[tooltipItem.dataIndex] ?? null
      if (rank === null) return ''
      if (!rank) return t('圏外')

      const value = Math.round(rank)

      const tip = sprintfT(
        '%s 位 / %s 件',
        value,
        ocChart.data.totalCount[tooltipItem.dataIndex] ?? 0
      )

      // time は急上昇(rising)のみ存在する。無い（ランキング等）場合は時刻を出さない。
      if (ocChart.data.time?.[tooltipItem.dataIndex])
        return `${tip}・${sprintfT('%s 時点', ocChart.data.time[tooltipItem.dataIndex] as string)}`

      return tip
    }

    // １周間表示時はメンバー数を非表示にする
    return (ocChart.limit === 8 || ocChart.zoomWeekday === 2) && !ocChart.data.graph2.length
      ? ''
      : `${t('メンバー')} ${tooltipItem.formattedValue}`
  }
}
