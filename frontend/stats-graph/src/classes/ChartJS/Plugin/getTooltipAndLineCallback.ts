import { Chart as ChartJS } from 'chart.js/auto';
import { ActiveElement, ChartTypeRegistry, Point, Tooltip, TooltipPositionerFunction } from "chart.js";
import OpenChatChart from "../../OpenChatChart";
import { resetTooltip } from './getEventCatcherPlugin';

const defaultVerticalLine = {
  color: 'black',
  lineWidth: 1,
  setLineDash: [6, 6],
}

const verticalLine = (chart: ChartJS, options: {
  color: string;
  lineWidth: number;
  setLineDash: number[];
}, x: number) => {
  const ctx = chart.ctx
  const chartArea = chart.chartArea

  if (!isNaN(x) && options.lineWidth) {
    ctx.save()
    ctx.lineWidth = options.lineWidth
    ctx.strokeStyle = options.color
    ctx.setLineDash(options.setLineDash)
    ctx.beginPath()
    ctx.moveTo(x, chartArea.bottom)
    ctx.lineTo(x, chartArea.top)
    ctx.stroke()
    ctx.restore()
  }
}

let isShow = false
let onPaning = false
let onZooming = false

export const getTooltipAndLineCallback = (ocChart: OpenChatChart): TooltipPositionerFunction<keyof ChartTypeRegistry> =>
  (items: readonly ActiveElement[], eventPosition: Point) => {
    /** @ts-ignore */
    const pos = Tooltip.positioners.average(items, eventPosition);
    if (pos === false) {
      isShow = false
      return false;
    }

    const index = items[0].index

    // paddingで空のデータの場合は非表示にする
    if (!ocChart.data.date[index]) {
      isShow = false
      return false
    }

    if (ocChart.isZooming) {
      const min = ocChart.chart.scales.x.min
      const max = ocChart.chart.scales.x.max

      if ((!ocChart.onPaning && onPaning) || (ocChart.onPaning && !onPaning)) {
        onPaning = ocChart.onPaning
        isShow && resetTooltip(ocChart)
        isShow = false
        return false
      }

      if ((!ocChart.onZooming && onZooming) || (ocChart.onZooming && !onZooming)) {
        onZooming = ocChart.onZooming
        isShow && resetTooltip(ocChart)
        isShow = false
        return false
      }

      if (index < min || index > max) {
        isShow && resetTooltip(ocChart)
        isShow = false
        return false
      }
    }

    // 1週間表示時に棒グラフのデータがない場合は非表示にする
    if (
      (ocChart.limit === 8 || ocChart.zoomWeekday === 2)
      && (!ocChart.data.graph2.length)
    ) {
      isShow = false
      return false
    }

    // 1週間表示時以外
    if (!(ocChart.limit === 8 || ocChart.zoomWeekday === 2)) {
      verticalLine(ocChart.chart, defaultVerticalLine, pos.x)
    }

    isShow = true
    return {
      x: pos.x,
      y: 0,
      yAlign: 'bottom',
    }
  }
