import OpenChatChart from '../../OpenChatChart'

const incrementIfOdd = (n: number) => (n % 2 !== 0 ? n + 1 : n)
const decrementIfOdd = (n: number) => (n % 2 !== 0 ? n - 1 : n)

export default function getVerticalLabelRange(
  ocChart: OpenChatChart,
  data: (number | null)[]
): labelRangeLine {
  // データの最小〜最大が縦をほぼ満たすよう余白は控えめに。上はデータラベル用に少しだけ残す。
  const diffMaxConst = ocChart.isPC ? 0.08 : 0.1
  const diffMinConst = ocChart.isPC ? 0.05 : 0.07
  const diff8Const = ocChart.isPC ? 0.12 : 0.18

  let stepSize = 2
  const realMax = data.reduce(
    (a, b) => Math.max(a === null ? 0 : a, b === null ? 0 : b),
    -Infinity
  ) as number
  let maxNum = incrementIfOdd(realMax)

  const minData = data.filter((v) => v !== null && v !== 0) as number[]
  const realMin = minData.reduce((a, b) => Math.min(a, b), Infinity) as number
  let minNum = decrementIfOdd(realMin)

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

  let dataMin: number
  if (ocChart.limit === 8) {
    dataMin = minNum - dataDiff8
  } else {
    dataMin = minNum - dataDiffMin
  }

  dataMin = dataMin < 0 ? 0 : dataMin

  // 安全策: 丸め(maxNumの切り下げ等)＋控えめな余白で軸がデータをはみ出すのを防ぐ。
  // 実データの最大/最小を必ず軸の内側に収める（意図した余白 diffMaxConst を下限として確保）。
  const span = Math.max(1, realMax - (isFinite(realMin) ? realMin : 0))
  const dataMax = Math.max(maxNum + dataDiffMax, realMax + Math.max(2, Math.ceil(span * diffMaxConst)))
  if (isFinite(realMin)) {
    dataMin = Math.min(dataMin, Math.max(0, realMin - Math.max(2, Math.ceil(span * diffMinConst))))
  }

  return {
    dataMax,
    dataMin,
    stepSize,
  }
}
