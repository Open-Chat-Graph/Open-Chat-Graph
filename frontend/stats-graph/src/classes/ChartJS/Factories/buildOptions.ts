import { ChartConfiguration, Chart as ChartJS } from 'chart.js/auto'
import OpenChatChart from '../../OpenChatChart'
import getVerticalLabelRange from '../Util/getVerticalLabelRange'
import getRankingBarLabelRange from '../Util/getRankingBarLabelRange'
import getHorizontalLabelFontColor from '../Callback/getHorizontalLabelFontColor'
import { getHourTicksFormatterCallback } from '../Callback/getHourTicksFormatterCallback'
import { sprintfT } from '../../../util/translation'

const aspectRatio = (ocChart: OpenChatChart) => {
  ocChart.setSize()
  return ocChart.isMiniMobile ? 1.2 / 1 : ocChart.isPC ? 1.8 / 1 : 1.4 / 1
}

export default function buildOptions(
  ocChart: OpenChatChart,
  plugins: any
): ChartConfiguration<'bar' | 'line', number[], string | string[]>['options'] {
  const hasPosition = !!ocChart.data.graph2.length || !!ocChart.ohlcRankingData.length
  const limit = ocChart.limit
  const isWeekly = limit === 8

  ChartJS.defaults.borderColor = isWeekly ? 'rgba(0,0,0,0)' : '#efefef'

  const ticksFontSizeMobile = ocChart.isMiniMobile ? 10.5 : 11

  const ticksFontSize = isWeekly
    ? ocChart.isPC
      ? 12
      : ocChart.isMiniMobile
      ? 11
      : 11.5
    : limit === 31
    ? ocChart.isPC
      ? ocChart.getIsHour()
        ? 11.5
        : 11
      : ticksFontSizeMobile
    : ocChart.isPC
    ? 12
    : ticksFontSizeMobile

  const paddingX = 20
  const paddingY = isWeekly ? 0 : 5
  const displayY = ocChart.getMode() === 'candlestick' ? true : !isWeekly

  const labelRangeLine = getVerticalLabelRange(ocChart, ocChart.data.graph1)

  const options: ChartConfiguration<'bar' | 'line', number[], string | string[]>['options'] = {
    animation: {
      duration: ocChart.animationAll ? undefined : 0,
    },
    layout: {
      padding: {
        top: 0,
        left: 0,
        right: hasPosition ? 0 : 24,
        bottom: hasPosition ? 0 : 9,
      },
    },
    onResize: (chart: ChartJS) => {
      chart.options.aspectRatio = aspectRatio(ocChart)
      chart.resize()
    },
    aspectRatio: aspectRatio(ocChart),
    scales: {
      x: {
        type: 'category' as const,
        grid: {
          display: hasPosition ? displayY : true,
          color: '#efefef',
        },
        ticks: {
          color: getHorizontalLabelFontColor,
          padding: hasPosition ? paddingX : isWeekly ? 10 : 3,
          maxRotation: 90,
          font: {
            size: ticksFontSize,
          },
        },
      },
      rainChart: {
        position: 'left',
        min: labelRangeLine.dataMin,
        max: labelRangeLine.dataMax,
        display: displayY,
        ticks: {
          callback: (v: any) => {
            if (v === 0) return 1
            return v
          },
          stepSize: labelRangeLine.stepSize,
          precision: 0,
          autoSkip: true,
          padding: paddingY,
          font: {
            size: ticksFontSize,
          },
          color: '#aaa',
        },
      },
    },
    plugins,
  }

  // 最新24時間の場合のticksフォーマッター
  if (ocChart.getIsHour()) {
    options.scales!.x!.ticks!.callback = getHourTicksFormatterCallback(ocChart)
  }

  if (ocChart.getMode() === 'candlestick' && ocChart.ohlcRankingData.length) {
    // nullLow（圏外）のl値を除外して軸範囲を計算
    const realRankValues = ocChart.ohlcRankingData.flatMap(d => {
      const vals = [d.o, d.h, d.c]
      if (!ocChart.ohlcRankingNullLow.has(d.x)) vals.push(d.l)
      return vals
    })
    const rankMin = Math.min(...realRankValues)
    const rankMax = Math.max(...realRankValues)
    const padding = Math.max(1, Math.ceil((rankMax - rankMin) * 0.1))
    const axisMax = rankMax + padding

    // 圏外のl値を軸最大値に置換（ヒゲがチャートの一番下まで伸びる）
    for (const d of ocChart.ohlcRankingData) {
      if (ocChart.ohlcRankingNullLow.has(d.x)) d.l = axisMax
    }

    options.scales!.temperatureChart! = {
      position: 'right',
      min: Math.max(1, rankMin - padding),
      max: axisMax,
      reverse: true,
      display: displayY,
      grid: {
        display: false,
      },
      ticks: {
        display: displayY,
        callback: (v: any) => {
          const tick = Math.round(v)
          if (tick !== v || tick < 1) return ''
          return sprintfT('%s 位', tick)
        },
        autoSkip: true,
        maxTicksLimit: 14,
        precision: 0,
        font: {
          size: ticksFontSize,
        },
        color: '#aaa',
      },
    }
  } else if (ocChart.data.graph2.length) {
    const labelRangeBar = getRankingBarLabelRange(
      ocChart,
      ocChart.getReverseGraph2(ocChart.data.graph2)
    )
    const show = displayY && ocChart.data.graph2.some((v) => v !== 0 && v !== null)

    let lastTick = 0

    options.scales!.temperatureChart! = {
      position: 'right',
      min: labelRangeBar.dataMin,
      max: labelRangeBar.dataMax,
      display: show,
      grid: {
        display: false,
      },
      ticks: {
        display: show,
        callback: (v: any) => {
          const value = ocChart.graph2Max - v + 1
          let tick = Math.ceil(value)

          if (!tick || tick === lastTick) return ''
          lastTick = tick

          return sprintfT('%s 位', tick)
        },
        stepSize: labelRangeBar.stepSize,
        autoSkip: true,
        maxTicksLimit: 14,
        font: {
          size: ticksFontSize,
        },
        color: '#aaa',
      },
    }
  }

  // ローソク足モード: CandlestickControllerがautoSkipを極端に効かせるため無効化
  // 月・全期間はcallbackでラベルを間引く
  if (ocChart.getMode() === 'candlestick') {
    options.scales!.x!.ticks!.autoSkip = false

    const dataLen = ocChart.ohlcData.length

    if (!isWeekly) {
      const maxLabels = limit === 31 ? 15 : 20
      const step = Math.max(1, Math.ceil(dataLen / maxLabels))
      options.scales!.x!.ticks!.callback = function (this: any, _val: any, index: number) {
        if (index % step !== 0) return ''
        return this.getLabelForValue(index)
      }
    }

    // グリッド線もラベルと同じ間隔で間引く（大量データでグレーになるのを防止）
    if (dataLen > 40) {
      const gridStep = Math.max(1, Math.ceil(dataLen / 20))
      options.scales!.x!.grid = {
        ...options.scales!.x!.grid,
        color: (ctx: any) => ctx.index % gridStep === 0 ? '#efefef' : 'transparent',
      }
    }
  }

  return options
}
