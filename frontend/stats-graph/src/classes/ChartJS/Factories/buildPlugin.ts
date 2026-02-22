import OpenChatChart from '../../OpenChatChart'
import getZoomOption from '../Plugin/zoomOptions'
import getTooltipLabelCallback from '../Plugin/getTooltipLabelCallback'
import { ChartType, Tooltip, TooltipPositionerFunction } from 'chart.js'
import { getTooltipAndLineCallback } from '../Plugin/getTooltipAndLineCallback'
import { t } from '../../../util/translation'

declare module 'chart.js' {
  interface TooltipPositionerMap {
    tooltipAndLine: TooltipPositionerFunction<ChartType>
  }
}

export default function buildPlugin(ocChart: OpenChatChart): any {
  const limit = ocChart.limit
  const isLimit8 = limit === 8

  const tooltipFontSize = ocChart.isPC ? 12 : ocChart.isMiniMobile ? 10.5 : 11

  const datalabelFontSize = isLimit8
    ? ocChart.isPC
      ? 12.5
      : ocChart.isMiniMobile
        ? 11
        : 12
    : limit === 31
      ? 10.5
      : ocChart.isPC
        ? 11
        : 10.5

  Tooltip.positioners.tooltipAndLine = getTooltipAndLineCallback(ocChart)

  return {
    zoom: limit === 0 && ocChart.enableZoom ? getZoomOption(ocChart) : undefined,
    legend: {
      display: true,
      onClick: () => false,
      labels: {
        font: {
          weight: 'normal',
          size: ocChart.isPC ? 12 : ocChart.isMiniMobile ? 11 : 11,
          family:
            "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
        },
        padding: 6,
        color: '#111',
        usePointStyle: ocChart.getMode() === 'candlestick',
        pointStyle: ocChart.getMode() === 'candlestick' ? 'rectRounded' : undefined,
        generateLabels:
          ocChart.getMode() === 'candlestick'
            ? (chart: any) => {
                const colors = ['#00c853', 'rgba(41, 121, 255, 0.35)']
                return chart.data.datasets.map((ds: any, i: number) => ({
                  text: ds.label,
                  fillStyle: colors[i] ?? '#999',
                  strokeStyle: colors[i] ?? '#999',
                  lineWidth: 0,
                  hidden: false,
                  datasetIndex: i,
                  pointStyle: 'rectRounded',
                }))
              }
            : undefined,
      },
    },
    tooltip: {
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
        label:
          ocChart.getMode() === 'candlestick'
            ? (tooltipItem: any) => {
                const raw = tooltipItem.raw
                if (!raw) return ''
                const unit = tooltipItem.datasetIndex === 0 ? t('人') : t('位')
                const f = (v: number) => v.toLocaleString()
                if (tooltipItem.datasetIndex !== 0) {
                  // ランキング: 数字が小さいほど上位なので高安を反転
                  const isNullLow = ocChart.ohlcRankingNullLow.has(raw.x)
                  const highStr = isNullLow ? `${f(raw.h)}${unit}` : `${f(raw.l)}${unit}`
                  const lowStr = isNullLow ? t('圏外') : `${f(raw.h)}${unit}`
                  return `${t('始')}${f(raw.o)}${unit}→${t('終')}${f(raw.c)}${unit}  ${t('高')}${highStr}/${t('安')}${lowStr}`
                }
                return `${t('始')}${f(raw.o)}${unit}→${t('終')}${f(raw.c)}${unit}  ${t('高')}${f(raw.h)}${unit}/${t('安')}${f(raw.l)}${unit}`
              }
            : getTooltipLabelCallback(ocChart),
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
