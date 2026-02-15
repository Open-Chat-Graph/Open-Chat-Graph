import { Chart as ChartJS } from 'chart.js/auto';
import OpenChatChart from "../../OpenChatChart";
import buildData from "./buildData";
import buildOptions from "./buildOptions";
import buildPlugin from "./buildPlugin";
import getCandlestickRankingLabelPlugin from "../Plugin/getCandlestickRankingLabelPlugin";
import getCandlestickMemberLabelPlugin from "../Plugin/getCandlestickMemberLabelPlugin";

export default function openChatChartJSFactory(ocChart: OpenChatChart) {
  /* @ts-ignore */
  return new ChartJS(ocChart.canvas!, {
    data: buildData(ocChart),
    options: buildOptions(ocChart, buildPlugin(ocChart)),
    plugins: [getCandlestickMemberLabelPlugin(ocChart), getCandlestickRankingLabelPlugin(ocChart)],
  })
}