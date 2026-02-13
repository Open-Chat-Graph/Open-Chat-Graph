import OpenChatChart from "../../OpenChatChart";

export default function afterOpenChatChartJSFactory(ocChart: OpenChatChart) {
  ocChart.chart.options.plugins!.zoom!.pan!.enabled = ocChart.isZooming
  ocChart.chart.update()
}