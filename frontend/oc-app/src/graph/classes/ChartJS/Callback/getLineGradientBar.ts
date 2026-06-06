import { ChartArea } from 'chart.js'
import { getColors, isDarkMode } from '../../../util/theme'

let width = 0,
  height = 0,
  dark: boolean | null = null,
  gradient: CanvasGradient | null = null

export default function getLineGradientBar(ctx: CanvasRenderingContext2D, chartArea: ChartArea) {
  const chartWidth = chartArea.right - chartArea.left
  const chartHeight = chartArea.bottom - chartArea.top
  const isDark = isDarkMode()
  if (!gradient || width !== chartWidth || height !== chartHeight || dark !== isDark) {
    width = chartWidth
    height = chartHeight
    dark = isDark
    gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top)
    for (const stop of getColors().barGradient.stops) {
      gradient.addColorStop(stop.offset, stop.color)
    }
  }

  return gradient
}
