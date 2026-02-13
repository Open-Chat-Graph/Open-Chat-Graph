import { Context } from 'chartjs-plugin-datalabels';
import OpenChatChart from '../../OpenChatChart';

export default function getDataLabelBarCallback(ocChart: OpenChatChart) {
  return (context: Context) => {
    const min = context.chart.scales.x.min
    const max = context.chart.scales.x.max
    const index = context.dataIndex

    if (index >= min && index <= max) {
      return ocChart.limit === 8 ? true : 'auto'
    } else {
      return false
    }
  }
}