import { Chart as ChartJS } from 'chart.js/auto'
import ChartDataLabels from 'chartjs-plugin-datalabels'
import zoomPlugin from 'chartjs-plugin-zoom'
import {
  CandlestickController,
  CandlestickElement,
  OhlcController,
  OhlcElement,
} from 'chartjs-chart-financial'
import 'chartjs-adapter-luxon'
import formatDates from './ChartJS/Util/formatDates'
import ModelFactory from './ModelFactory.ts'
import openChatChartJSFactory from './ChartJS/Factories/openChatChartJSFactory.ts'
import afterOpenChatChartJSFactory from './ChartJS/Factories/afterOpenChatChartJSFactory.ts'
import getIncreaseLegendSpacingPlugin from './ChartJS/Plugin/getIncreaseLegendSpacingPlugin.ts'
import getEventCatcherPlugin from './ChartJS/Plugin/getEventCatcherPlugin.ts'
import paddingArray from './ChartJS/Util/paddingArray.ts'
import { t } from '../util/translation'
import { getColors } from '../util/theme'

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
  /** OHLC専用の日付軸（memberOhlcApiData / initData.rankingOhlc と index 整合）。日次 date 軸の部分集合 */
  ohlcDate: string[] = []
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

        this.canvas
          ?.getContext('2d')
          ?.clearRect(0, 0, this.canvas.clientWidth, this.canvas.clientHeight)
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

  render(
    data: ChartArgs,
    option: OpenChatChartOption,
    animation: boolean,
    limit: ChartLimit
  ): void {
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

  /** テーマ（ライト/ダーク）切替時にチャートを現在のデータのまま作り直す */
  applyTheme() {
    if (!this.chart) return

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
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const anim = (this.chart.data.datasets[0] as any).animation
    if (anim && typeof anim === 'object') {
      anim.duration = undefined
      this.chart.update()
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
      date: paddingArray<string | string[]>(data.date, ''),
      graph1: paddingArray<number | null>(data.graph1, null),
      graph2: data.graph2.length ? paddingArray<number | null>(data.graph2, null) : [],
      time: data.time.length ? paddingArray<string | null>(data.time, null) : [],
      totalCount: data.totalCount.length ? paddingArray<number | null>(data.totalCount, null) : [],
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
    const dailyDates = this.initData.date // 日次 date 軸（期間タブの窓計算に使う）
    const ohlcDate = this.ohlcDate // OHLC日付軸（dailyDates の部分集合・昇順）
    const member = this.memberOhlcApiData // ohlcDate と index 整合
    const rankingOhlc = this.initData.rankingOhlc // ohlcDate と index 整合（null=圏外）
    const hasRanking = !!rankingOhlc?.length

    // 期間タブの窓は従来どおり「日次 date 軸の末尾 limit 日」。その開始日以降の OHLC だけ描く。
    // （OHLC本数ではなく日次日数で窓を決めることで updateCandleTabVisibility のタブ表示判定と一致させる）
    const startIdx = limit ? Math.max(0, dailyDates.length - limit) : 0
    const windowStart = dailyDates[startIdx] ?? ''

    const ohlcData: { x: number; o: number; h: number; l: number; c: number }[] = []
    const allValues: number[] = []
    const ohlcDates: string[] = []
    // ランキング順位OHLCも同じ index 軸（ohlcData の並び）で構築する
    const ohlcRankingData: { x: number; o: number; h: number; l: number; c: number }[] = []
    const ohlcRankingNullLow = new Set<number>()

    for (let k = 0; k < ohlcDate.length; k++) {
      if (ohlcDate[k] < windowStart) continue // 窓より前の OHLC は描かない
      const m = member[k]
      if (!m) continue // member OHLC は ohlcDate と1:1（防御的にスキップ）

      const x = ohlcData.length
      ohlcDates.push(ohlcDate[k])
      ohlcData.push({ x, o: m.open_member, h: m.high_member, l: m.low_member, c: m.close_member })
      allValues.push(m.open_member, m.high_member, m.low_member, m.close_member)

      if (hasRanking) {
        const r = rankingOhlc![k]
        if (r) {
          if (r.low_position === null) ohlcRankingNullLow.add(x)
          ohlcRankingData.push({
            x,
            o: r.open_position,
            h: r.high_position,
            l: r.low_position ?? 0,
            c: r.close_position,
          })
        } else {
          // ランキングOHLCがない日は圏外（position=0）で埋める
          ohlcRankingData.push({ x, o: 0, h: 0, l: 0, c: 0 })
        }
      }
    }

    const labels = formatDates(ohlcDates, limit)

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
    this.graph2Max = graph2.reduce(
      (a, b) => Math.max(a === null ? 0 : a, b === null ? 0 : b),
      -Infinity
    ) as number
    this.graph2Min = (graph2.filter((v) => v !== null && v !== 0) as number[]).reduce(
      (a, b) => Math.min(a, b),
      Infinity
    ) as number
  }

  getReverseGraph2(graph2: (number | null)[]) {
    return graph2.map((v) => {
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
    ctx.fillStyle = getColors().watermark
    ctx.font =
      '14px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText(t('OHLCデータがありません'), w / 2, h / 2)
    ctx.restore()
  }
}
