import OpenChatChart from "../../OpenChatChart";

export default function afterOpenChatChartJSFactory(ocChart: OpenChatChart) {
  if (ocChart.chart.options.plugins?.zoom?.pan) {
    ocChart.chart.options.plugins.zoom.pan.enabled = ocChart.isZooming;
  }
  ocChart.chart.update();
}
