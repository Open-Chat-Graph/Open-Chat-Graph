import OpenChatChart from "../../OpenChatChart"

export default function getCandlestickMemberLabelPlugin(ocChart: OpenChatChart) {
  return {
    id: 'candlestick-member-labels',
    afterDatasetsDraw(chart: any) {
      if (ocChart.getMode() !== 'candlestick') return

      const meta = chart.getDatasetMeta(0)
      if (!meta?.data?.length) return

      const scale = chart.scales.rainChart
      const xMin = chart.scales.x.min
      const xMax = chart.scales.x.max
      const range = xMax - xMin + 1
      const showAll = range < 9
      const dataLen = Math.min(meta.data.length, ocChart.ohlcData.length)
      if (!dataLen) return

      let firstVisible = -1
      let lastVisible = -1
      for (let i = 0; i < dataLen; i++) {
        const d = ocChart.ohlcData[i]
        if (d.x >= xMin && d.x <= xMax) {
          if (firstVisible === -1) firstVisible = i
          lastVisible = i
        }
      }

      const isLimit8 = ocChart.limit === 8
      const fontSize = isLimit8
        ? (ocChart.isPC ? 11.5 : (ocChart.isMiniMobile ? 10 : 11))
        : ocChart.isPC ? (ocChart.limit === 31 ? 10 : 10.5)
        : 10

      const ctx = chart.ctx
      ctx.save()
      const fontWeight = isLimit8 ? 'normal' : 'bold'
      ctx.font = `${fontWeight} ${fontSize}px system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif`
      ctx.fillStyle = '#111'
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'

      const gap = 4
      let lastRight = -Infinity

      for (let i = 0; i < dataLen; i++) {
        const d = ocChart.ohlcData[i]
        if (!d || d.x < xMin || d.x > xMax) continue

        if (!showAll && i !== firstVisible && i !== lastVisible) continue

        const el = meta.data[i]
        if (!el || d.c == null) continue

        const text = d.c.toLocaleString()
        const halfWidth = ctx.measureText(text).width / 2
        const left = el.x - halfWidth

        if (!showAll && left < lastRight + gap && i !== firstVisible && i !== lastVisible) continue

        const y = scale.getPixelForValue(d.c)
        ctx.fillText(text, el.x, y)
        lastRight = el.x + halfWidth
      }

      ctx.restore()
    },
  }
}
