import { Chart as ChartJS } from "chart.js/auto";
import OpenChatChart from "../../OpenChatChart";
import getVerticalLabelRange from "../Util/getVerticalLabelRange";
import getRankingBarLabelRange from "../Util/getRankingBarLabelRange";

const onZoomLabelRange = (chart: ChartJS, ocChart: OpenChatChart) => {
  const min = chart.scales.x.min;
  const max = chart.scales.x.max;

  if (ocChart.getMode() === "candlestick") {
    // メンバーOHLCのレンジ再計算
    const visibleOhlc = ocChart.ohlcData.slice(min, max + 1);
    const allValues = visibleOhlc.flatMap((d) => [d.o, d.h, d.l, d.c]);
    if (allValues.length) {
      const { dataMin, dataMax, stepSize } = getVerticalLabelRange(
        ocChart,
        allValues,
      );
      chart.options!.scales!.rainChart!.min = dataMin;
      chart.options!.scales!.rainChart!.max = dataMax;
      (chart.options!.scales!.rainChart!.ticks as any).stepSize = stepSize;
    }

    // ランキングOHLCのレンジ再計算
    if (
      ocChart.ohlcRankingData.length &&
      chart.options!.scales!.temperatureChart
    ) {
      const visibleRanking = ocChart.ohlcRankingData.filter(
        (d) => d.x >= min && d.x <= max,
      );
      if (visibleRanking.length) {
        const rankValues = visibleRanking.flatMap((d) => [d.o, d.h, d.l, d.c]);
        const rankMin = Math.min(...rankValues);
        const rankMax = Math.max(...rankValues);
        const padding = Math.max(1, Math.ceil((rankMax - rankMin) * 0.1));
        chart.options!.scales!.temperatureChart!.min = Math.max(
          1,
          rankMin - padding,
        );
        chart.options!.scales!.temperatureChart!.max = rankMax + padding;
      }
    }

    // ラベル間引きを可視範囲に合わせて再計算
    const range = max - min + 1;
    const maxLabels = range <= 8 ? range : range < 32 ? 15 : 20;
    const step = Math.max(1, Math.ceil(range / maxLabels));
    chart.options!.scales!.x!.ticks!.callback = function (
      this: any,
      val: any,
      index: number,
    ) {
      if (index % step !== 0) return '';
      return this.getLabelForValue(val);
    };

    // グリッド間引き
    const gridStep = Math.max(1, Math.ceil(range / 20));
    chart.options!.scales!.x!.grid = {
      ...chart.options!.scales!.x!.grid,
      color: ((ctx: any) =>
        ctx.index % gridStep === 0 ? '#efefef' : 'transparent') as any,
    };

    return [min, max];
  }

  const { dataMin, dataMax, stepSize } = getVerticalLabelRange(
    ocChart,
    ocChart.data.graph1.slice(min, max + 1),
  );

  chart.options!.scales!.rainChart!.min = dataMin;
  chart.options!.scales!.rainChart!.max = dataMax;
  (chart.options!.scales!.rainChart!.ticks as any).stepSize = stepSize;

  if (ocChart.data.graph2.length) {
    const graph2 = ocChart.data.graph2.slice(min, max + 1);
    ocChart.setGraph2Max(graph2);

    const graph2Reverse = ocChart.getReverseGraph2(graph2);
    const { dataMin, dataMax, stepSize } = getRankingBarLabelRange(
      ocChart,
      graph2Reverse,
    );

    chart.data.datasets[1].data = ocChart.getReverseGraph2(ocChart.data.graph2);

    chart.options!.scales!.temperatureChart!.min = dataMin;
    chart.options!.scales!.temperatureChart!.max = dataMax;
    (chart.options!.scales!.temperatureChart!.ticks as any).stepSize = stepSize;
  }

  return [min, max];
};

const toggleIsZooming = (ocChart: OpenChatChart, range: number) => {
  ocChart.isZooming = ocChart.data.date.length !== range;
  ocChart.chart.options.plugins!.zoom!.pan!.enabled = ocChart.isZooming;
};

const getOnZoomComplete = (ocChart: OpenChatChart) => {
  const [min, max] = onZoomLabelRange(ocChart.chart, ocChart);
  const range = max - min + 1;

  toggleIsZooming(ocChart, range);

  if (range <= 8 && ocChart.zoomWeekday !== 2) {
    ocChart.chart.data.labels = ocChart.getDate(8);
    ocChart.zoomWeekday = 2;
  } else if (range > 8 && range < 32 && ocChart.zoomWeekday !== 1) {
    ocChart.chart.data.labels = ocChart.getDate(31);
    ocChart.zoomWeekday = 1;
  } else if (range >= 32 && ocChart.zoomWeekday !== 0) {
    ocChart.chart.data.labels = ocChart.data.date;
    ocChart.zoomWeekday = 0;
  }
};

export default function getZoomOption(ocChart: OpenChatChart) {
  const enable = true;

  return {
    pan: {
      enabled: enable,
      mode: "x",
      onPanStart: () => {
        ocChart.onPaning = true;
      },
      onPanComplete: () => {
        ocChart.onPaning = false;
        getOnZoomComplete(ocChart);
        ocChart.chart.update();
      },
    },
    zoom: {
      pinch: {
        enabled: enable,
      },
      wheel: {
        enabled: enable,
      },
      mode: "x",
      onZoomStart: () => {
        ocChart.onZooming = true;
      },
      onZoomComplete: () => {
        ocChart.onZooming = false;
        getOnZoomComplete(ocChart);
        ocChart.chart.update();
      },
    },
    limits: {
      x: { minRange: 7 },
    },
  };
}
