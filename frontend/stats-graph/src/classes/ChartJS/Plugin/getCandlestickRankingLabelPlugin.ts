import OpenChatChart from '../../OpenChatChart'

export default function getCandlestickRankingLabelPlugin(ocChart: OpenChatChart) {
  return {
    id: 'candlestick-ranking-labels',
    afterDatasetsDraw(chart: any) {
      if (ocChart.getMode() !== 'candlestick' || !ocChart.ohlcRankingData.length) return

      const meta = chart.getDatasetMeta(1)
      if (!meta?.data?.length) return

      const chartArea = chart.chartArea
      const xMin = chart.scales.x.min
      const xMax = chart.scales.x.max
      const range = xMax - xMin + 1
      const showAll = range < 9
      const autoSkipAll = ocChart.limit === 0 || ocChart.limit === 31
      const dataLen = Math.min(meta.data.length, ocChart.ohlcRankingData.length)
      if (!dataLen) return

      // 可視範囲内の先頭・末尾インデックスを特定
      let firstVisible = -1
      let lastVisible = -1
      for (let i = 0; i < dataLen; i++) {
        const x = ocChart.ohlcRankingData[i].x
        if (x >= xMin && x <= xMax) {
          if (firstVisible === -1) firstVisible = i
          lastVisible = i
        }
      }

      const isLimit8 = ocChart.limit === 8
      const fontSize = isLimit8
        ? ocChart.isPC
          ? 11.5
          : ocChart.isMiniMobile
            ? 10
            : 11
        : ocChart.isPC
          ? ocChart.limit === 31
            ? 10
            : 10.5
          : 10

      const ctx = chart.ctx
      ctx.save()
      const fontWeight = isLimit8 ? 'normal' : 'bold'
      ctx.font = `${fontWeight} ${fontSize}px system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif`
      ctx.fillStyle = '#111'
      ctx.textAlign = 'center'
      ctx.textBaseline = 'bottom'

      const y = chartArea.bottom - 2
      const gap = 4
      let lastRight = -Infinity

      for (let i = 0; i < dataLen; i++) {
        const x = ocChart.ohlcRankingData[i].x
        if (x < xMin || x > xMax) continue

        // 週: 全件、月・全期間: autoSkip付き全件、それ以外: 先頭と末尾のみ
        if (!showAll && !autoSkipAll && i !== firstVisible && i !== lastVisible) continue

        const el = meta.data[i]
        const raw = ocChart.ohlcRankingData[i]
        if (!el || raw?.h == null) continue

        const text = String(raw.h)
        const halfWidth = ctx.measureText(text).width / 2
        const left = el.x - halfWidth

        if (left < lastRight + gap && i !== firstVisible && i !== lastVisible) continue

        ctx.fillText(text, el.x, y)
        lastRight = el.x + halfWidth
      }

      ctx.restore()
    },
  }
}
