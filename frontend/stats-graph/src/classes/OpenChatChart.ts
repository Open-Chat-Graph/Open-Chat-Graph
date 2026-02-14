import { Chart as ChartJS } from 'chart.js/auto';
import ChartDataLabels from 'chartjs-plugin-datalabels'
import zoomPlugin from 'chartjs-plugin-zoom'
import { CandlestickController, CandlestickElement, OhlcController, OhlcElement } from 'chartjs-chart-financial'
import 'chartjs-adapter-luxon'
import formatDates from "./ChartJS/Util/formatDates";
import ModelFactory from "./ModelFactory.ts"
import openChatChartJSFactory from "./ChartJS/Factories/openChatChartJSFactory.ts";
import afterOpenChatChartJSFactory from './ChartJS/Factories/afterOpenChatChartJSFactory.ts'; import getIncreaseLegendSpacingPlugin from './ChartJS/Plugin/getIncreaseLegendSpacingPlugin.ts';
import getEventCatcherPlugin from './ChartJS/Plugin/getEventCatcherPlugin.ts';
import paddingArray from './ChartJS/Util/paddingArray.ts';
import { statsDto } from '../util/fetchRenderer';
import { t } from '../util/translation';

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
  memberOhlcApiData: MemberOhlc[] = []
  ohlcData: { x: number; o: number; h: number; l: number; c: number }[] = []
  ohlcRankingData: { x: number; o: number; h: number; l: number; c: number }[] = []
  ohlcRankingNullLow: Set<number> = new Set()
  ohlcDates: string[] = []
  private isHour: boolean = false
  private mode: ChartMode = 'line'

  constructor() {
    ChartJS.register(ChartDataLabels)
    ChartJS.register(zoomPlugin)
    ChartJS.register(CandlestickController, CandlestickElement, OhlcController, OhlcElement)
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

  setMode(mode: ChartMode) {
    this.mode = mode
  }

  getMode(): ChartMode {
    return this.mode
  }

  private createChart(animation: boolean) {
    this.setSize()
    this.isZooming = false
    this.zoomWeekday = 0

    if (this.mode === 'candlestick') {
      this.buildCandlestickData()
      if (!this.ohlcData.length) {
        this.drawEmptyMessage()
        return
      }
    } else {
      this.ohlcData = []
      this.ohlcRankingData = []
      this.ohlcDates = []
      if (this.isHour) {
        this.buildHourData()
      } else {
        this.buildData()
      }
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

    {
      afterOpenChatChartJSFactory(this)
    }
  }

  private enableAnimationOption() {
    const anim = (this.chart.data.datasets[0] as any).animation;
    if (anim && typeof anim === 'object') {
      anim.duration = undefined;
      this.chart.update();
    }
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

  private buildCandlestickData() {
    const limit = this.limit
    const dates = statsDto.date
    const len = dates.length

    const startIdx = limit ? Math.max(0, len - limit) : 0

    const ohlcData: { x: number; o: number; h: number; l: number; c: number }[] = []
    const allValues: number[] = []
    const ohlcDates: string[] = []
    const apiOhlcMap = new Map(this.memberOhlcApiData.map(r => [r.date, r]))

    for (let i = startIdx; i < len; i++) {
      const record = apiOhlcMap.get(dates[i])
      if (record) {
        ohlcDates.push(dates[i])
        ohlcData.push({ x: ohlcData.length, o: record.open_member, h: record.high_member, l: record.low_member, c: record.close_member })
        allValues.push(record.open_member, record.high_member, record.low_member, record.close_member)
      }
    }

    const labels = formatDates(ohlcDates, limit)

    // ランキング順位OHLCを基準日付に合わせて構築
    const ohlcRankingData: { x: number; o: number; h: number; l: number; c: number }[] = []
    const ohlcRankingNullLow = new Set<number>()
    const rankingOhlc = this.initData.rankingOhlc
    if (rankingOhlc?.length) {
      const rankingMap = new Map(rankingOhlc.map(r => [r.date, r]))
      for (let i = 0; i < ohlcDates.length; i++) {
        const r = rankingMap.get(ohlcDates[i])
        if (r) {
          if (r.low_position === null) {
            ohlcRankingNullLow.add(i)
          }
          ohlcRankingData.push({
            x: i,
            o: r.open_position,
            h: r.high_position,
            l: r.low_position ?? 0,
            c: r.close_position,
          })
        } else {
          // ランキングOHLCがない日は圏外（position=0）で埋める
          ohlcRankingData.push({ x: i, o: 0, h: 0, l: 0, c: 0 })
        }
      }
    }

    this.data = {
      date: labels,
      graph1: allValues,
      graph2: [],
      time: [],
      totalCount: [],
    }
    this.ohlcData = ohlcData
    this.ohlcRankingData = ohlcRankingData
    this.ohlcRankingNullLow = ohlcRankingNullLow
    this.ohlcDates = ohlcDates
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
    if (this.mode === 'candlestick') {
      return formatDates(this.ohlcDates, limit)
    }
    const data = this.initData.date.slice(this.limit * -1)
    return formatDates(data, limit)
  }

  private drawEmptyMessage() {
    if (!this.canvas) return
    const ctx = this.canvas.getContext('2d')
    if (!ctx) return

    const w = this.canvas.width
    const h = this.canvas.height
    ctx.clearRect(0, 0, w, h)
    ctx.save()
    ctx.fillStyle = '#888'
    ctx.font = '14px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText(t('OHLCデータがありません'), w / 2, h / 2)
    ctx.restore()
  }
}