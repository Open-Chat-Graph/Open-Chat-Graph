import {  Chart as ChartJS } from 'chart.js/auto';
import OpenChatChart from "../../OpenChatChart"

export const resetTooltip = (ocChart: OpenChatChart) => {
  ocChart.chart.tooltip!.setActiveElements([], { x: 0, y: 0 });
  ocChart.chart.update()
}

let inChartArea = false;

/** @ts-ignore */
export default function getEventCatcherPlugin(ocChart: OpenChatChart) {
  return {
    id: 'myEventCatcher',
    /** @ts-ignore */
    beforeEvent(chart: ChartJS, args) {
      if (inChartArea && !args.inChartArea && !ocChart.isZooming) {
        // チャートエリア外イベントでツールチップ非表示
        resetTooltip(ocChart)
      }

      if (args.inChartArea) inChartArea = true
      if (!args.inChartArea) inChartArea = false
    }
  }
}