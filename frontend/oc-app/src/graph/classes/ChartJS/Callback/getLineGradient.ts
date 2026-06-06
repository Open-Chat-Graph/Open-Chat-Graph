import { ChartArea } from 'chart.js'
import { getColors, isDarkMode } from '../../../util/theme'

let width = 0,
  height = 0,
  dark: boolean | null = null,
  gradient: CanvasGradient | null = null

export default function getLineGradient(ctx: CanvasRenderingContext2D, chartArea: ChartArea) {
  const chartWidth = chartArea.right - chartArea.left
  const chartHeight = chartArea.bottom - chartArea.top
  const isDark = isDarkMode()
  if (!gradient || width !== chartWidth || height !== chartHeight || dark !== isDark) {
    // 初回・リサイズ・テーマ切替時のみグラデーションを作り直す
    width = chartWidth
    height = chartHeight
    dark = isDark
    gradient = ctx.createLinearGradient(0, chartArea.height / 2, chartArea.width, 0)
    for (const stop of getColors().lineGradient.stops) {
      gradient.addColorStop(stop.offset, stop.color)
    }
  }

  return gradient
}
