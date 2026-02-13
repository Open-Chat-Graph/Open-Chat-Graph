import { Context } from 'chartjs-plugin-datalabels';

export default function getDataLabelLineCallback(firstIndex: number, lastIndex: number) {
  return (context: Context) => {
    const min = context.chart.scales.x.min
    const max = context.chart.scales.x.max
    const range = max - min + 1
    const index = context.dataIndex

    // zoom,limit8
    if (range < 9 && index >= min && index <= max) {
      return true
    }

    const contextLen = context.dataset.data.length
    if (context.chart.data.labels?.length !== contextLen) {
      if (index === contextLen - 1) {
        return 'auto'
      } else {
        return false
      }
    }

    if (index === min || index === max) {
      return 'auto'
    } else {
      return firstIndex === index || lastIndex === index ? 'auto' : false
    }
  }
}