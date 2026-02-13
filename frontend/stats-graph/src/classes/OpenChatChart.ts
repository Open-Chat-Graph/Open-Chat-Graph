import { Chart as ChartJS } from 'chart.js/auto';
import ChartDataLabels from 'chartjs-plugin-datalabels'
import zoomPlugin from 'chartjs-plugin-zoom'
import formatDates from "./ChartJS/Util/formatDates";
import ModelFactory from "./ModelFactory.ts"
import openChatChartJSFactory from "./ChartJS/Factories/openChatChartJSFactory.ts";
import afterOpenChatChartJSFactory from './ChartJS/Factories/afterOpenChatChartJSFactory.ts'; import getIncreaseLegendSpacingPlugin from './ChartJS/Plugin/getIncreaseLegendSpacingPlugin.ts';
import getEventCatcherPlugin from './ChartJS/Plugin/getEventCatcherPlugin.ts';
import paddingArray from './ChartJS/Util/paddingArray.ts';

export default class OpenChatChart implements ChartFactory {
  chart: ChartJS = null!
  innerWidth = 0
  isPC = true
  animation = true
  animationAll = true
  initData = ModelFactory.initChartArgs()
  data = ModelFactory.initChartData()
  option = ModelFactory.initOpenChatChartOption()
  canvas?: HTMLCanvasElement
  limit: ChartLimit = 0
  zoomWeekday: 0 | 1 | 2 = 0
  isMiniMobile = false
  isMiddleMobile = false
  graph2Max = 0
  graph2Min = 0
  isZooming = false
  onZooming = false
  onPaning = false
  enableZoom = false
  private isHour: boolean = false

  constructor() {
    ChartJS.register(ChartDataLabels)
    ChartJS.register(zoomPlugin)
    ChartJS.register(getIncreaseLegendSpacingPlugin(this))
    ChartJS.register(getEventCatcherPlugin(this))
  }

  init(canvas: HTMLCanvasElement) {
    this.setSize()
    this.canvas = canvas
    !this.isPC && this.visibilitychange()
  }

  private visibilitychange() {
    document.addEventListener('visibilitychange', () => {
      if (this.isZooming) {
        return
      }

      if (document.visibilityState === 'visible') {
        if (!this.chart) {
          return false
        }

        this.canvas?.getContext('2d')?.clearRect(0, 0, this.canvas.clientWidth, this.canvas.clientHeight)
        this.animationAll = false
        this.createChart(false)
        this.animationAll = true
      }

      if (document.visibilityState === 'hidden') {
        if (!this.chart) {
          return false
        }

        this.chart.destroy()
      }
    })
  }

  render(data: ChartArgs, option: OpenChatChartOption, animation: boolean, limit: ChartLimit): void {
    if (!this.canvas) {
      throw Error('HTMLCanvasElement is not defined')
    }

    this.chart?.destroy()
    this.limit = limit
    this.option = option
    this.initData = data
    this.createChart(animation)
  }

  update(limit: ChartLimit): boolean {
    if (!this.chart) {
      return false
    }

    this.chart.destroy()
    this.limit = limit

    this.createChart(true)

    return true
  }

  updateEnableZoom(value: boolean) {
    if (!this.chart) return
  
    this.enableZoom = value
    this.chart.destroy()
    this.createChart(false)
  }

  setSize() {
    this.innerWidth = window.innerWidth
    this.isPC = this.innerWidth >= 512
    this.isMiniMobile = this.innerWidth < 360
    this.isMiddleMobile = this.innerWidth < 390
  }

  setIsHour(isHour: boolean) {
    this.isHour = isHour
  }

  getIsHour(): boolean {
    return !!this.isHour
  }

  private createChart(animation: boolean) {
    this.setSize()
    this.isZooming = false
    this.zoomWeekday = 0

    if (this.isHour) {
      this.buildHourData()
    } else {
      this.buildData()
    }

    this.setGraph2Max(this.data.graph2)

    if (animation) {
      this.animation = true
      this.chart = openChatChartJSFactory(this)
    } else {
      this.animation = false
      this.chart = openChatChartJSFactory(this)
      this.animation = true
      this.enableAnimationOption()
    }

    afterOpenChatChartJSFactory(this)
  }

  private enableAnimationOption() {
    (this.chart.data.datasets[0] as any).animation.duration = undefined;
    this.chart.update()
  }

  private buildData() {
    const li = this.limit

    const data = {
      date: this.getDate(this.limit),
      graph1: li ? this.initData.graph1.slice(li * -1) : this.initData.graph1,
      graph2: li ? this.initData.graph2.slice(li * -1) : this.initData.graph2,
      time: li ? this.initData.time.slice(li * -1) : this.initData.time,
      totalCount: li ? this.initData.totalCount.slice(li * -1) : this.initData.totalCount,
    }

    this.data = {
      date: paddingArray<(string | string[])>(data.date, ''),
      graph1: paddingArray<(number | null)>(data.graph1, null),
      graph2: data.graph2.length ? paddingArray<(number | null)>(data.graph2, null) : [],
      time: data.time.length ? paddingArray<(string | null)>(data.time, null) : [],
      totalCount: data.totalCount.length ? paddingArray<(number | null)>(data.totalCount, null) : [],
    }
  }

  private buildHourData() {
    this.data = {
      date: this.initData.date,
      graph1: this.initData.graph1,
      graph2: this.initData.graph2,
      time: this.initData.time,
      totalCount: this.initData.totalCount,
    }
  }

  setGraph2Max(graph2: (number | null)[]) {
    this.graph2Max = graph2.reduce((a, b) => Math.max(a === null ? 0 : a, b === null ? 0 : b), -Infinity) as number
    this.graph2Min = (graph2.filter(v => v !== null && v !== 0) as number[]).reduce((a, b) => Math.min(a, b), Infinity) as number
  }

  getReverseGraph2(graph2: (number | null)[]) {
    return graph2.map(v => {
      if (v === null) return v
      return v ? this.graph2Max + 1 - v : 0
    })
  }

  getDate(limit: ChartLimit): (string | string[])[] {
    const data = this.initData.date.slice(this.limit * -1)
    return formatDates(data, limit)
  }
}