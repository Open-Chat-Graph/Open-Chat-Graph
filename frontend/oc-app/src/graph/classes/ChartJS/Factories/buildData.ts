import { ChartConfiguration } from 'chart.js/auto'
import OpenChatChart from '../../OpenChatChart'
import getDataLabelBarCallback from '../Callback/getDataLabelBarCallback'
import getLineGradient from '../Callback/getLineGradient'
import getLineGradientBar from '../Callback/getLineGradientBar'
import getPointRadiusCallback from '../Callback/getPointRadiusCallback'
import getDataLabelLineCallback from '../Callback/getDataLabelLineCallback'
import { t } from '../../../util/translation'
import { getColors } from '../../../util/theme'

export const lineEasing = 'easeOutQuart'
export const barEasing = 'easeOutCirc'

export default function buildData(ocChart: OpenChatChart) {
  if (ocChart.getMode() === 'candlestick') {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const datasets: any[] = [
      {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        type: 'candlestick' as any,
        label: t('メンバー数'),
        data: ocChart.ohlcData,
        color: {
          up: getColors().candle.up,
          down: getColors().candle.down,
          unchanged: getColors().candle.unchanged,
        },
        backgroundColors: getColors().candle.bg,
        borderColors: getColors().candle.borders,
        datalabels: { display: false },
        animation: false as const,
        yAxisID: 'rainChart',
      },
    ]

    if (ocChart.ohlcRankingData.length) {
      datasets.push({
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        type: 'candlestick' as any,
        label: `${ocChart.option.label2} | ${ocChart.option.category}`,
        data: ocChart.ohlcRankingData,
        color: getColors().candleRank.color,
        backgroundColors: getColors().candleRank.bg,
        borderColors: getColors().candleRank.borders,
        borderWidth: 1,
        datalabels: { display: false },
        animation: false as const,
        yAxisID: 'temperatureChart',
      })
    }

    return { labels: ocChart.data.date, datasets }
  }

  const firstIndex = ocChart.data.graph1.findIndex((v) => !!v)
  const lastIndex =
    ocChart.data.graph1.length -
    1 -
    ocChart.data.graph1
      .slice()
      .reverse()
      .findIndex((v) => !!v)

  const data: ChartConfiguration<'bar' | 'line', (number | null)[], string | string[]>['data'] = {
    labels: ocChart.data.date,
    datasets: [
      {
        type: 'line',
        label: ocChart.option.label1,
        data: ocChart.data.graph1,
        pointRadius: getPointRadiusCallback(firstIndex, lastIndex),
        fill: false,
        backgroundColor: 'rgba(0,0,0,0)',
        borderColor: function (context) {
          const chart = context.chart
          const { ctx, chartArea } = chart

          if (!chartArea) {
            // This case happens on initial chart load
            return
          }
          return getLineGradient(ctx, chartArea)
        },
        borderWidth: 3,
        spanGaps: true,
        pointBackgroundColor: getColors().pointBackground,
        /* @ts-expect-error lineTension not in type definition */
        lineTension: 0.4,
        datalabels: {
          display: getDataLabelLineCallback(firstIndex, lastIndex),
          align: 'end',
          anchor: 'end',
        },
        animation: {
          duration: ocChart.animation ? undefined : 0,
        },
        yAxisID: 'rainChart',
      },
    ],
  }

  if (ocChart.data.graph2.length) {
    data.datasets.push({
      type: 'bar',
      label: `${ocChart.option.label2} | ${ocChart.option.category}`,
      data: ocChart.getReverseGraph2(ocChart.data.graph2),
      backgroundColor: function (context) {
        const chart = context.chart
        const { ctx, chartArea } = chart

        if (!chartArea) {
          // This case happens on initial chart load
          return
        }
        return getLineGradientBar(ctx, chartArea)
      },
      barPercentage: ocChart.limit === 8 ? 0.77 : 0.9,
      borderRadius: ocChart.limit === 8 || ocChart.data.date.length < 10 ? 4 : 2,
      datalabels: {
        align: 'start',
        anchor: 'start',
        // ラベルは元の生順位(data.graph2)で判定する。非線形スケールでは外れ値より深い順位が
        // バー値0に圧縮され、0が圏外センチネルと衝突してバー値から順位を復元できないため。
        formatter: (v, ctx) => {
          if (v === null) return ''
          const rank = ocChart.data.graph2[ctx.dataIndex]
          return rank ? Math.round(rank) : t('圏外')
        },
        display: getDataLabelBarCallback(ocChart),
      },
      yAxisID: 'temperatureChart',
    })
  }

  return data
}
