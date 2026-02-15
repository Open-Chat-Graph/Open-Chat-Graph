import OpenChatChart from "../../OpenChatChart"
import getZoomOption from "../Plugin/zoomOptions"
import getTooltipLabelCallback from '../Plugin/getTooltipLabelCallback'
import { ChartType, Tooltip, TooltipPositionerFunction } from "chart.js"
import { getTooltipAndLineCallback } from "../Plugin/getTooltipAndLineCallback"

declare module 'chart.js' {
  interface TooltipPositionerMap {
    tooltipAndLine: TooltipPositionerFunction<ChartType>;
  }
}

export default function buildPlugin(ocChart: OpenChatChart): any {
  const limit = ocChart.limit
  const isLimit8 = limit === 8

  const tooltipFontSize = ocChart.isPC ? 12 : (ocChart.isMiniMobile ? 10.5 : 11)

  const datalabelFontSize =
    isLimit8 ? (ocChart.isPC ? 12.5 : (ocChart.isMiniMobile ? 11 : 12)) : limit === 31 ? 10.5 : (ocChart.isPC ? 11 : 10.5)

  Tooltip.positioners.tooltipAndLine = getTooltipAndLineCallback(ocChart)

  return {
    zoom: limit === 0 && ocChart.enableZoom ? getZoomOption(ocChart) : undefined,
    legend: {
      onClick: () => false,
      labels: {
        font: {
          weight: 'normal',
          size: ocChart.isPC ? 12 : (ocChart.isMiniMobile ? 11 : 11),
          family: "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
        },
        padding: 6,
        color: '#111',
      },
    },
    tooltip:
    {
      intersect: false,
      titleFont: {
        size: tooltipFontSize,
      },
      mode: 'index',
      bodyFont: {
        size: tooltipFontSize,
      },
      enabled: true,
      displayColors: false,
      callbacks: {
        label: getTooltipLabelCallback(ocChart)
      },
      position: 'tooltipAndLine',
    },
    datalabels: {
      borderRadius: 4,
      color: '#111',
      backgroundColor: 'rgba(0,0,0,0)',
      font: {
        size: datalabelFontSize,
        weight: 'bold',
      },
    },
  }
}

