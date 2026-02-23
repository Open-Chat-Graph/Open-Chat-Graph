import { TooltipItem } from 'chart.js'
import OpenChatChart from '../../OpenChatChart'
import { sprintfT, t } from '../../../util/translation'

export default function getTooltipLabelCallback(ocChart: OpenChatChart) {
  return (tooltipItem: TooltipItem<'bar' | 'line'>) => {
    if (tooltipItem.datasetIndex === 1) {
      if (tooltipItem.raw === null) return ''

      if (tooltipItem.raw === 0) return t('圏外')

      const value = ocChart.graph2Max - (tooltipItem.raw as number) + 1

      const tip = sprintfT(
        '%s 位 / %s 件',
        value,
        ocChart.data.totalCount[tooltipItem.dataIndex] ?? 0
      )

      if (ocChart.data.time[tooltipItem.dataIndex])
        return `${tip}・${sprintfT('%s 時点', ocChart.data.time[tooltipItem.dataIndex] as string)}`

      return tip
    }

    // １周間表示時はメンバー数を非表示にする
    return (ocChart.limit === 8 || ocChart.zoomWeekday === 2) && !ocChart.data.graph2.length
      ? ''
      : `${t('メンバー')} ${tooltipItem.formattedValue}`
  }
}
