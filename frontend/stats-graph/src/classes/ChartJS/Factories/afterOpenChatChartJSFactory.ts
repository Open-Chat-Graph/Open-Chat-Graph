import OpenChatChart from "../../OpenChatChart";

export default function afterOpenChatChartJSFactory(ocChart: OpenChatChart) {
  const zoom = ocChart.chart.options.plugins?.zoom
  if (zoom?.pan) {
    zoom.pan.enabled = ocChart.isZooming
  }
  ocChart.chart.update()
}