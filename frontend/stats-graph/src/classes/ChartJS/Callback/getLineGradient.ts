import { ChartArea } from "chart.js";

let width = 0, height = 0, gradient: CanvasGradient | null = null

export default function getLineGradient(ctx: CanvasRenderingContext2D, chartArea: ChartArea) {
  const chartWidth = chartArea.right - chartArea.left;
  const chartHeight = chartArea.bottom - chartArea.top;
  if (!gradient || width !== chartWidth || height !== chartHeight) {
    // Create the gradient because this is either the first render
    // or the size of the chart has changed
    width = chartWidth;
    height = chartHeight;
    gradient = ctx.createLinearGradient(0, chartArea.height / 2, chartArea.width, 0);
    gradient.addColorStop(1, 'rgba(0, 183, 96, 1.0)');
    gradient.addColorStop(0.8, 'rgba(17, 216, 113, 1.0)');
    gradient.addColorStop(0.5, 'rgba(17, 213, 147, 1.0)');
    gradient.addColorStop(0.3, 'rgba(18, 207, 205, 1.0)');
    gradient.addColorStop(0, 'rgba(22, 194, 193, 1.0)');
  }

  return gradient;
}