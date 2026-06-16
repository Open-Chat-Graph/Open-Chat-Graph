import { ChartConfiguration, Chart as ChartJS } from 'chart.js/auto'
import OpenChatChart from '../../OpenChatChart'
import getVerticalLabelRange from '../Util/getVerticalLabelRange'
import getRankBarScale from '../Util/getRankBarScale'
import getHorizontalLabelFontColor from '../Callback/getHorizontalLabelFontColor'
import { getHourTicksFormatterCallback } from '../Callback/getHourTicksFormatterCallback'
import { sprintfT } from '../../../util/translation'
import { getColors } from '../../../util/theme'

const aspectRatio = (ocChart: OpenChatChart) => {
  ocChart.setSize()
  // スマホ幅のグラフ高さ（CSS の .chart-canvas-box / #chart-preact-canvas の aspect-ratio と必ず一致
  // させること。ズレるとロード時にレイアウトシフト）。元値より少しだけ高い程度に調整。
  return ocChart.isMiniMobile ? 1.15 / 1 : ocChart.isPC ? 1.8 / 1 : 1.34 / 1
}

export default function buildOptions(
  ocChart: OpenChatChart,
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  plugins: any
): ChartConfiguration<'bar' | 'line', number[], string | string[]>['options'] {
  const hasPosition = !!ocChart.data.graph2.length || !!ocChart.ohlcRankingData.length
  const limit = ocChart.limit
  const isWeekly = limit === 8

  ChartJS.defaults.borderColor = isWeekly ? getColors().borderWeekly : getColors().border

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
  // 1週間表示は通常 y軸・グリッドを隠してコンパクトにするが、順位(ランキング/急上昇)表示時は
  // 他の期間と同じく縦横グリッド＋順位軸を出す（順位の基準が無いと読みにくいため）
  const displayY = ocChart.getMode() === 'candlestick' ? true : !isWeekly || hasPosition

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
          color: getColors().grid,
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
        // 順位(ランキング/急上昇)表示時は水平グリッドを順位軸(temperatureChart)側に合わせるため、
        // メンバー軸側のグリッドは消す。順位非表示時は従来どおりメンバー軸にグリッドを引く
        grid: {
          display: !hasPosition,
          color: getColors().grid,
        },
        ticks: {
          // eslint-disable-next-line @typescript-eslint/no-explicit-any
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
          color: getColors().text.tertiary,
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
    const realRankValues = ocChart.ohlcRankingData.flatMap((d) => {
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
      // 順位表示中は水平グリッドを順位軸側に引く（メンバー軸側はオフ）。
      // 色は控えめな grid トークンにして順位バーと明度がかぶらないようにする
      grid: {
        display: displayY,
        color: getColors().grid,
      },
      ticks: {
        display: displayY,
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
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
        color: getColors().text.tertiary,
      },
    }
  } else if (ocChart.data.graph2.length) {
    const scale = getRankBarScale(ocChart, ocChart.getReverseGraph2(ocChart.data.graph2))
    const show = displayY && ocChart.data.graph2.some((v) => v !== 0 && v !== null)

    options.scales!.temperatureChart! = {
      position: 'right',
      min: scale.min,
      max: scale.max,
      display: show,
      // 非線形時は最良/最悪順位を上下端のグリッド線に必ず合わせるため目盛りを明示配置する
      afterBuildTicks: scale.afterBuildTicks,
      // 順位表示中は水平グリッドを順位軸側に引く（メンバー軸側はオフ）。
      // 色は控えめな grid トークンにして順位バーと明度がかぶらないようにする
      grid: {
        display: show,
        color: getColors().grid,
      },
      ticks: {
        display: show,
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        callback: scale.makeTickCallback() as any,
        stepSize: scale.stepSize,
        // 明示配置(afterBuildTicks)の時は端の目盛りが間引かれないよう autoSkip を切る
        autoSkip: !scale.afterBuildTicks,
        maxTicksLimit: 14,
        font: {
          size: ticksFontSize,
        },
        color: getColors().text.tertiary,
      },
    }
  }

  // ローソク足モード: CandlestickControllerがautoSkipを極端に効かせるため無効化
  // autoSkipが無いと狭い画面で全ラベルが重なって潰れるため、月・全期間はcallbackで間引く
  // （折れ線はautoSkipが自動で間引くが、ローソク足では手動間引きが必須）
  if (ocChart.getMode() === 'candlestick') {
    options.scales!.x!.ticks!.autoSkip = false

    const dataLen = ocChart.ohlcData.length

    if (!isWeekly) {
      const maxLabels = limit === 31 ? 15 : 20
      const step = Math.max(1, Math.ceil(dataLen / maxLabels))
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
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
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        color: (ctx: any) => (ctx.index % gridStep === 0 ? getColors().grid : 'transparent'),
      }
    }
  }

  return options
}
