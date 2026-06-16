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
import { applyZoomComplete } from './ChartJS/Plugin/zoomOptions.ts'
import paddingArray from './ChartJS/Util/paddingArray.ts'
import { t } from '../util/translation'
import { getColors } from '../util/theme'

// ピンチズームの減衰指数。1.0=指の広がり比率をそのまま軸ズームに反映（プラグイン既定＝強すぎる）。
// <1 にするほど緩やかになる。例: 0.5 なら指を2倍に広げてもズームは約1.4倍に留まる。
const PINCH_DAMPING = 0.5
// 可視件数が少ないほどピンチを緩める（ギアを下げる）。件数が PINCH_GEAR_FULL 以上なら通常の効き、
// 少なくなるほど下限 PINCH_GEAR_MIN まで弱める。少件数での過敏さを抑える（下げすぎない程度に）。
const PINCH_GEAR_FULL = 20
const PINCH_GEAR_MIN = 0.5
// PC: Alt + ホイールで通常(プラグイン既定0.1)の3倍速ズーム。1ノッチあたり拡大1.3倍/縮小0.7倍。
const ALT_WHEEL_SPEED = 0.3

// 順位バーの縦スケール強度（Box-Cox 変換のべき指数 k）。上位（小さい順位）ほど縦に拡大して
// 1位と10位の差を見せる。値が小さいほど上位を強く拡大する:
//   k=1   … 従来どおり等間隔（linear）
//   k=0.5 … 平方根（緩め）
//   k=0   … 対数（log・比率スケール）
//   k=-0.2… logより少し強い上位強調（既定）。1↔2 は広げすぎず（最大レンジでも縦の約16%）滑らか
//   k=-0.5… さらに強いが1位と2位の差が広がりすぎ下位が潰れる
const RANK_SCALE_POWER = -0.2
// 順位スケールの下端を決めるときの外れ値除外。Q3 + k×IQR を超える深い順位は「まれな外れ値」として
// スケールから外し下端に圧縮する（例: 初日だけ1567位→以後77〜150位の部屋で、いつもいる帯を広く表示）。
// 外れ値が無い部屋ではフェンス≧最悪となり、最悪がそのまま下端になる（従来どおり）。
const RANK_OUTLIER_MULT = 1.5

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
  /** 外れ値を除いた「実質的な最悪順位」(順位スケールの下端)。非線形(上位強調)時のみ使用 */
  graph2ScaleWorst = 0
  isZooming = false
  onZooming = false
  onPaning = false
  enableZoom = false
  /** 順位バー「上位を強調」(非線形スケール)。OFFで従来の等間隔。起動時に保存設定から chartState が設定 */
  rankEmphasis = true
  /** OHLC専用の日付軸（memberOhlcApiData / positionOhlcApiData と index 整合・昇順） */
  ohlcDate: string[] = []
  memberOhlcApiData: MemberOhlc[] = []
  /** 順位OHLC（ohlcDate と index 整合。null=その日は圏外）。順位OFF時は空配列 */
  positionOhlcApiData: (RankingPositionOhlc | null)[] = []
  ohlcData: { x: number; o: number; h: number; l: number; c: number }[] = []
  ohlcRankingData: { x: number; o: number; h: number; l: number; c: number }[] = []
  ohlcRankingNullLow: Set<number> = new Set()
  ohlcDates: string[] = []
  private isHour: boolean = false
  private mode: ChartMode = 'line'
  private pinching = false
  private pinchLastDist = 0
  private touchPinchAttached = false
  private zoomCompleteTimer: ReturnType<typeof setTimeout> | null = null
  /** 順位種別/カテゴリ切替で作り直すとき、拡大中の表示窓(x.min/max)を引き継ぐための一時保存 */
  private pendingZoomWindow: { min: number; max: number } | null = null

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
    this.attachTouchPinch()
    !this.isPC && this.visibilitychange()
  }

  /** ズーム有効時(全期間＋スイッチON)だけ canvas の touch-action を切り、ピンチを横取りできるようにする */
  private applyTouchAction() {
    if (this.canvas) {
      this.canvas.style.touchAction = this.limit === 0 && this.enableZoom ? 'none' : ''
    }
  }

  /**
   * モバイルのピンチズームを減衰付きで自前処理する（canvas に一度だけ付与）。
   * プラグインのピンチ(1:1で強すぎる)は無効化済み。2本指の広がり比率を PINCH_DAMPING で
   * 緩めてから chart.zoom() に渡す。1本指パン(Hammer)は pointers=1 必須なので衝突しない。
   */
  private attachTouchPinch() {
    if (this.touchPinchAttached || !this.canvas) return
    this.touchPinchAttached = true
    const canvas = this.canvas

    const dist = (a: Touch, b: Touch) => Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY)
    // ズーム経路が組まれているのは「全期間＋スイッチON」のときだけ（buildPlugin）
    const zoomActive = () =>
      this.chart && this.enableZoom && this.limit === 0 && this.chart.options?.plugins?.zoom

    canvas.addEventListener(
      'touchstart',
      (e: TouchEvent) => {
        if (!zoomActive() || e.touches.length !== 2) return
        e.preventDefault()
        this.pinching = true
        this.onZooming = true
        this.pinchLastDist = dist(e.touches[0], e.touches[1])
      },
      { passive: false }
    )

    canvas.addEventListener(
      'touchmove',
      (e: TouchEvent) => {
        if (!this.pinching || !zoomActive() || e.touches.length !== 2) return
        e.preventDefault()
        const d = dist(e.touches[0], e.touches[1])
        if (this.pinchLastDist <= 0) {
          this.pinchLastDist = d
          return
        }

        // 指の広がり比率を減衰させて軸ズーム倍率にする（>1=拡大, <1=縮小）
        // 可視件数が少ないほどギアを下げる（拡大が進むほど1ピンチの変化を小さくして過敏さを抑える）
        const visible = this.chart.scales.x.max - this.chart.scales.x.min + 1
        const gear = Math.min(1, Math.max(PINCH_GEAR_MIN, visible / PINCH_GEAR_FULL))
        const factor = Math.pow(d / this.pinchLastDist, PINCH_DAMPING * gear)
        if (!isFinite(factor) || factor <= 0) return

        // カテゴリ軸は1辺あたり最低±1カテゴリ削る(integerChange)ため、微小ズームを毎フレーム適用すると
        // 件数が少ないとき過剰に動く。1カテゴリ未満の変化は溜め、十分たまってからまとめて適用する
        // （pinchLastDist は据え置き＝次フレームで累積）。これで少件数でもギア通りに緩やかに効く。
        if (Math.abs(visible - visible / factor) < 1) return
        this.pinchLastDist = d

        // 焦点 = 2本指の中点（チャート領域内にクランプ）。x のみズーム
        const rect = canvas.getBoundingClientRect()
        const midX = (e.touches[0].clientX + e.touches[1].clientX) / 2 - rect.left
        const area = this.chart.chartArea
        const fx = Math.min(Math.max(midX, area.left), area.right)
        const fy = (area.top + area.bottom) / 2
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        ;(this.chart as any).zoom({ x: factor, focalPoint: { x: fx, y: fy } }, 'none')
      },
      { passive: false }
    )

    const onEnd = (e: TouchEvent) => {
      if (!this.pinching) return
      if (e.touches.length >= 2) return
      this.pinching = false
      this.pinchLastDist = 0
      this.onZooming = false
      // 公開API chart.zoom は onZoomComplete を呼ばないので、Y軸再計算等の後処理を明示実行
      if (this.chart && this.enableZoom && this.limit === 0) applyZoomComplete(this)
    }
    canvas.addEventListener('touchend', onEnd)
    canvas.addEventListener('touchcancel', onEnd)

    // PC: Alt+ホイールは3倍速ズーム。capture段階で横取りし、プラグインの通常ホイールは止める
    // （Altなしの通常ホイールはそのままプラグインに任せる）
    canvas.addEventListener(
      'wheel',
      (e: WheelEvent) => {
        if (!e.altKey || !zoomActive()) return
        e.preventDefault()
        e.stopImmediatePropagation()
        const rect = canvas.getBoundingClientRect()
        const area = this.chart.chartArea
        const fx = Math.min(Math.max(e.clientX - rect.left, area.left), area.right)
        const fy = Math.min(Math.max(e.clientY - rect.top, area.top), area.bottom)
        const factor = e.deltaY < 0 ? 1 + ALT_WHEEL_SPEED : 1 - ALT_WHEEL_SPEED
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        ;(this.chart as any).zoom({ x: factor, focalPoint: { x: fx, y: fy } }, 'none')
        this.debouncedZoomComplete()
      },
      { capture: true, passive: false }
    )
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

  /** 「上位を強調」(順位スケール)の切替。現在のデータのままチャートを作り直す */
  updateRankEmphasis(value: boolean) {
    this.rankEmphasis = value
    if (!this.chart) return
    this.chart.destroy()
    this.createChart(false)
  }

  /**
   * 縮小・拡大ボタン用。可視件数を rangeFactor 倍にして中央基準でズームする
   * （拡大=0.5で表示を半分／縮小=2で倍に。カテゴリ軸の chart.zoom 倍率は直感に反するので範囲を直接指定）。
   */
  zoomStep(rangeFactor: number) {
    if (!this.chart || !this.enableZoom || this.limit !== 0) return
    const x = this.chart.scales.x
    const count = this.chart.data.labels?.length ?? 0
    if (count < 2) return
    const center = (x.min + x.max) / 2
    const range = x.max - x.min + 1
    let newRange = Math.round(range * rangeFactor)
    newRange = Math.max(7, Math.min(count, newRange)) // minRange=7（zoomOptions の limits と一致）
    let min = Math.round(center - (newRange - 1) / 2)
    let max = min + newRange - 1
    if (min < 0) {
      min = 0
      max = newRange - 1
    }
    if (max > count - 1) {
      max = count - 1
      min = max - newRange + 1
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    ;(this.chart as any).zoomScale('x', { min, max }, 'default')
    applyZoomComplete(this)
  }

  /** Alt+ホイール用: Y軸再計算等の後処理を間引いて実行（高速スクロール中の負荷を抑える） */
  private debouncedZoomComplete() {
    if (this.zoomCompleteTimer) clearTimeout(this.zoomCompleteTimer)
    this.zoomCompleteTimer = setTimeout(() => {
      this.zoomCompleteTimer = null
      if (this.chart && this.enableZoom && this.limit === 0) applyZoomComplete(this)
    }, 200)
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

    this.applyTouchAction()
    this.restoreZoomWindow()
  }

  /**
   * 順位種別/カテゴリ切替で作り直す直前に、拡大中の表示窓を保存する。
   * 折れ線は期間(x軸の日付)が変わらないので窓をそのまま引き継げる。ローソク足は期間が変わりうるので対象外。
   */
  captureZoomWindowForReload() {
    this.pendingZoomWindow = null
    if (!this.chart || !this.enableZoom || this.limit !== 0 || this.mode !== 'line') return
    // isZooming フラグは復元(zoomScale)経由で拡大した直後に落ちていることがあり、当てにできない。
    // 実際のx軸の窓が全期間より狭ければ「拡大中」とみなして保存する（連続切替でも確実に維持）。
    const x = this.chart.scales.x
    const total = this.chart.data.labels?.length ?? 0
    const min = Math.round(x.min)
    const max = Math.round(x.max)
    if (total >= 2 && max - min + 1 < total) {
      this.pendingZoomWindow = { min, max }
    }
  }

  /** 切替フェッチが失敗して作り直しに至らなかったときに、保存窓を破棄する。
   * 残すと後続の無関係な再構築(テーマ切替・タブ復帰など)が古い窓を誤って復元してしまう。 */
  clearPendingZoomWindow() {
    this.pendingZoomWindow = null
  }

  /** createChart 末尾: 保存した表示窓があれば復元する（スクロール位置・拡大状態を維持） */
  private restoreZoomWindow() {
    const w = this.pendingZoomWindow
    this.pendingZoomWindow = null
    if (!w || !this.chart || !this.enableZoom || this.limit !== 0 || this.mode !== 'line') return
    const count = this.chart.data.labels?.length ?? 0
    if (count < 2) return
    const min = Math.max(0, Math.min(w.min, count - 1))
    const max = Math.max(min, Math.min(w.max, count - 1))
    if (max - min + 1 >= count) return // 全期間なら復元不要
    // createChart が予約した全期間レイアウトへのアニメーションを止める。止めないと、この後の
    // 'none' 更新で正しい窓に配置した線が、次フレームで全期間の座標へ巻き戻され左へ潰れる
    // （拡大中に種別/カテゴリを切り替えると人数の線が途切れて見えるバグの原因）。
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    ;(this.chart as any).stop()
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    ;(this.chart as any).zoomScale('x', { min, max }, 'none')
    applyZoomComplete(this, 'none') // 切替時の窓復元では線・棒をアニメーションさせない
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
    const ohlcDate = this.ohlcDate // OHLC日付軸（昇順）
    const member = this.memberOhlcApiData // ohlcDate と index 整合
    const ranking = this.positionOhlcApiData // ohlcDate と index 整合（null=圏外）。順位OFF時は空
    const hasRanking = !!ranking.length
    const len = ohlcDate.length

    // 期間タブは末尾 limit 本のローソクを表示する（OHLCは日次なので「直近 limit 日」と一致）。0=全期間。
    const startIdx = limit ? Math.max(0, len - limit) : 0

    const ohlcData: { x: number; o: number; h: number; l: number; c: number }[] = []
    const allValues: number[] = []
    const ohlcDates: string[] = []
    // ランキング順位OHLCも同じ index 軸（ohlcData の並び）で構築する
    const ohlcRankingData: { x: number; o: number; h: number; l: number; c: number }[] = []
    const ohlcRankingNullLow = new Set<number>()

    for (let i = startIdx; i < len; i++) {
      const m = member[i]
      if (!m) continue // member OHLC は ohlcDate と1:1（防御的にスキップ）

      const x = ohlcData.length
      ohlcDates.push(ohlcDate[i])
      ohlcData.push({ x, o: m.open_member, h: m.high_member, l: m.low_member, c: m.close_member })
      allValues.push(m.open_member, m.high_member, m.low_member, m.close_member)

      if (hasRanking) {
        const r = ranking[i]
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
    const nz = graph2.filter((v) => v !== null && v !== 0) as number[]
    this.graph2Min = nz.reduce((a, b) => Math.min(a, b), Infinity)

    // 外れ値に強い「実質的な最悪順位」= Q3 + k×IQR（最悪値で上限）。深い外れ値はここを超え、
    // スケールから外れて下端に圧縮される。外れ値が無ければフェンス≧最悪→graph2Max のまま。
    if (nz.length >= 4) {
      const sorted = [...nz].sort((a, b) => a - b)
      const q = (p: number) => sorted[Math.max(0, Math.round(p * (sorted.length - 1)))]
      const fence = q(0.75) + RANK_OUTLIER_MULT * (q(0.75) - q(0.25))
      this.graph2ScaleWorst = Math.min(this.graph2Max, Math.max(this.graph2Min, Math.ceil(fence)))
    } else {
      this.graph2ScaleWorst = this.graph2Max
    }
  }

  /** 順位スケールの基準となる最悪順位（軸下端）。非線形は外れ値除外、linearは従来どおり実最悪 */
  private rankScaleN(): number {
    return this.isLinearRankScale() ? this.graph2Max : this.graph2ScaleWorst
  }

  getRankScalePower(): number {
    return this.rankEmphasis ? RANK_SCALE_POWER : 1 // 強調OFF=従来の等間隔(linear)
  }

  /** 順位スケールが従来の等間隔（linear, k=1）かどうか */
  isLinearRankScale(): boolean {
    return this.getRankScalePower() === 1
  }

  // φ: Box-Cox 変換 (k=0 は log)。順位→軸値は value = φ(N+1) - φ(rank) とし、上位ほど大きな軸値
  // （＝チャート上方）にする。k<1 ほど上位の目盛り間隔が広がり、k=1 は従来どおり等間隔。
  private rankScalePhi(r: number): number {
    const k = this.getRankScalePower()
    return k === 0 ? Math.log(r) : (Math.pow(r, k) - 1) / k
  }

  private rankScalePhiInv(y: number): number {
    const k = this.getRankScalePower()
    return k === 0 ? Math.exp(y) : Math.pow(k * y + 1, 1 / k)
  }

  /** 順位 → 軸値。0(圏外)/外れ値より深い順位は下端(0)に圧縮。小さい順位ほど大きな値（上方）になる */
  rankToValue(rank: number): number {
    if (rank <= 0) return 0
    const v = this.rankScalePhi(this.rankScaleN() + 1) - this.rankScalePhi(rank)
    return v < 0 ? 0 : v
  }

  /** 軸値 → 順位（目盛り・ツールチップ・データラベルの逆変換）。linear では graph2Max+1-value と一致 */
  valueToRank(value: number): number {
    return this.rankScalePhiInv(this.rankScalePhi(this.rankScaleN() + 1) - value)
  }

  getReverseGraph2(graph2: (number | null)[]) {
    return graph2.map((v) => (v === null ? v : this.rankToValue(v)))
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
