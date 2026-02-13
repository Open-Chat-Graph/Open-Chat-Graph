import { ChartConfiguration } from 'chart.js/auto'
import OpenChatChart from '../../OpenChatChart'
import getDataLabelBarCallback from '../Callback/getDataLabelBarCallback'
import getLineGradient from '../Callback/getLineGradient'
import getLineGradientBar from '../Callback/getLineGradientBar'
import getPointRadiusCallback from '../Callback/getPointRadiusCallback'
import getDataLabelLineCallback from '../Callback/getDataLabelLineCallback'
import { t } from '../../../util/translation'

export const lineEasing = 'easeOutQuart'
export const barEasing = 'easeOutCirc'

export default function buildData(ocChart: OpenChatChart) {
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
        pointBackgroundColor: '#fff',
        /* @ts-ignore */
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
        formatter: (v) => {
          if (v === null) return ''
          return v ? ocChart.graph2Max - v + 1 : t('圏外')
        },
        display: getDataLabelBarCallback(ocChart),
      },
      yAxisID: 'temperatureChart',
    })
  }

  return data
}
