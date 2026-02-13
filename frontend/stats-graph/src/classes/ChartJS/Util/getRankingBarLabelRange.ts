import OpenChatChart from "../../OpenChatChart"

const incrementIfOdd = (n: number) => (n % 2 !== 0 ? n + 1 : n)
const decrementIfOdd = (n: number) => (n % 2 !== 0 ? n - 1 : n)

export default function getRankingBarLabelRange(ocChart: OpenChatChart, data: (number | null)[]): labelRangeLine {
  const diffMaxConst = (ocChart.isPC ? 0.1 : 0.15)

  let stepSize = 2
  const realMax = data.reduce((a, b) => Math.max(a === null ? 0 : a, b === null ? 0 : b), -Infinity) as number
  const realMin = data.reduce((a, b) => Math.min(a === null ? 0 : a, b === null ? 0 : b), Infinity) as number
  let maxNum = incrementIfOdd(realMax)
  let minNum = decrementIfOdd(realMin)

  let dataDiffMax = incrementIfOdd(Math.ceil((maxNum - minNum)) * diffMaxConst)

  if (dataDiffMax === 0) {
    dataDiffMax = 2
  }

  const trueDiff = maxNum - minNum

  if (trueDiff >= 50 && ocChart.limit !== 8) {
    maxNum = Math.floor(maxNum / 10) * 10
    dataDiffMax = Math.ceil(dataDiffMax / 10) * 10
  }

  if (trueDiff >= 100) stepSize = 10
  if (trueDiff >= 1000) stepSize = 100

  if (maxNum === 0) {
    return {
      dataMax: 1,
      dataMin: 0,
      stepSize: 2,
    }
  }

  return {
    dataMax: maxNum + dataDiffMax > ocChart.graph2Max ? ocChart.graph2Max : maxNum + dataDiffMax,
    dataMin: 0,
    stepSize: stepSize,
  }
}
