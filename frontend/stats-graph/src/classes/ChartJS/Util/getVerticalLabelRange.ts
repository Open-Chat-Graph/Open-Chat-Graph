import OpenChatChart from "../../OpenChatChart"

const incrementIfOdd = (n: number) => (n % 2 !== 0 ? n + 1 : n)
const decrementIfOdd = (n: number) => (n % 2 !== 0 ? n - 1 : n)

export default function getVerticalLabelRange(ocChart: OpenChatChart, data: (number | null)[]): labelRangeLine {
  const diffMaxConst = ocChart.isPC ? 0.25 : 0.31
  const diffMinConst = ocChart.isPC ? 0.1 : 0.15
  const diff8Const = ocChart.isPC ? 0.25 : 0.5

  let stepSize = 2
  let maxNum = incrementIfOdd(data.reduce((a, b) => Math.max(a === null ? 0 : a, b === null ? 0 : b), -Infinity) as number)

  const minData = data.filter(v => v !== null && v !== 0) as number[]
  let minNum = decrementIfOdd(minData.reduce((a, b) => Math.min(a, b), Infinity) as number)
  
  let dataDiffMax = incrementIfOdd(Math.ceil((maxNum - minNum) * diffMaxConst))
  let dataDiffMin = decrementIfOdd(Math.ceil((maxNum - minNum) * diffMinConst))
  let dataDiff8 = decrementIfOdd(Math.ceil(dataDiffMax * diff8Const))

  if (dataDiffMax === 0) {
    dataDiffMax = 2
    dataDiff8 = 2
  } else if (dataDiff8 === 0) {
    dataDiff8 = 2
  }

  if (dataDiffMin === 0) dataDiffMin = 2

  const trueDiff = maxNum - minNum
  if (trueDiff >= 50 && ocChart.limit !== 8) {
    maxNum = Math.floor(maxNum / 10) * 10
    minNum = Math.ceil(minNum / 10) * 10
    dataDiffMax = Math.floor(dataDiffMax / 10) * 10
    dataDiffMin = Math.ceil(dataDiffMin / 10) * 10
  }

  if (trueDiff >= 100) stepSize = 10
  if (trueDiff >= 1000) stepSize = 100

  let dataMin = 0
  if (ocChart.limit === 8) {
    dataMin = minNum - dataDiff8
  } else {
    dataMin = minNum - dataDiffMin
  }

  dataMin = dataMin < 0 ? 0 : dataMin

  return {
    dataMax: maxNum + dataDiffMax,
    dataMin: dataMin,
    stepSize: stepSize,
  }
}
