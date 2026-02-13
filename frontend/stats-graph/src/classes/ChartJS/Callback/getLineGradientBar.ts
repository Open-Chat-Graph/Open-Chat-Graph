import { ChartArea } from "chart.js";

let width = 0, height = 0, gradient: CanvasGradient | null = null

export default function getLineGradientBar(ctx: CanvasRenderingContext2D, chartArea: ChartArea) {
  const chartWidth = chartArea.right - chartArea.left;
  const chartHeight = chartArea.bottom - chartArea.top;
  if (!gradient || width !== chartWidth || height !== chartHeight) {
    // Create the gradient because this is either the first render
    // or the size of the chart has changed
    width = chartWidth;
    height = chartHeight;
    gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
    gradient.addColorStop(1, 'rgba(0, 183, 96, 0.2)');
    gradient.addColorStop(0.7, 'rgba(17, 216, 113, 0.2)');
    gradient.addColorStop(0.5, 'rgba(17, 213, 147, 0.2)');
    gradient.addColorStop(0.3, 'rgba(18, 207, 205, 0.2)');
    gradient.addColorStop(0, 'rgba(22, 194, 193, 0.2)');
  }

  return gradient;
}