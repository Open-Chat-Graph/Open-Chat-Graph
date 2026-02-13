import { CoreScaleOptions, Scale, Tick } from "chart.js";
import OpenChatChart from "../../OpenChatChart";

export const isYestString = '\u200B'
export const isRecentString = '\u200C'

const suppressZero3 = (str: string) => {
  if (str === '00') return '0'
  let idx = 0;
  while (str.charAt(idx) === '0') idx++;
  return str.slice(idx)
}

// 最新24時間の場合のticksフォーマッター
export const getHourTicksFormatterCallback = (ocChart: OpenChatChart)
  : ((this: Scale<CoreScaleOptions>, tickValue: string | number, index: number, ticks: Tick[]) => string | number | number[] | string[] | null | undefined) | undefined =>
  (tickValue: string | number, index: number, ticks: Tick[]) => {
    const hourString = (ocChart.data.date[Number(tickValue)] as string).substring(6)
    const hour = hourString.split(':').map(str => suppressZero3(str)).join(':')

    if (index === ticks.length - 1) return isRecentString + hour

    const today = (ocChart.data.date[ocChart.data.date.length - 1] as string).substring(3, 5);
    const day = (ocChart.data.date[Number(tickValue)] as string).substring(3, 5);

    if (today !== day) return isYestString + hour
    return hour
  }