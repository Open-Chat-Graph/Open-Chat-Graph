declare module 'chartjs-chart-financial' {
  import { ChartComponent, BarController, Chart } from 'chart.js'

  export const CandlestickController: ChartComponent & {
    prototype: BarController
    new (chart: Chart, datasetIndex: number): BarController
  }
  export const OhlcController: ChartComponent & {
    prototype: BarController
    new (chart: Chart, datasetIndex: number): BarController
  }
  export const CandlestickElement: Element
  export const OhlcElement: Element
}
