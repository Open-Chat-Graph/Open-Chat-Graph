class LiveTalkTimeframeGraph {
  constructor(parsedLog, limit = 31) {
    this.timeframeArray = parsedLog.timeframe
    this.#init(limit)
  }

  #init(limit) {
    this.limit = limit
    const timeframe = this.#parseTimeframe(this.timeframeArray.slice(-limit))
    this.chart = this.#graph(timeframe)
  }

  update(limit) {
    this.chart.destroy()
    this.#init(limit)
  }

  /**
   * 入力配列から各時間帯ごとの合計時間を計算し、新しい配列として返す関数。
   * @param {Array} inputArray - 入力配列。各要素は時間帯の範囲を示す配列を含む。
   * @returns {Array} - 各時間帯ごとの合計時間を格納した新しい配列。
   */
  #parseTimeframe(inputArray) {
    // 新しい配列を初期化し、各時間帯の合計時間を格納する
    const resultArray = Array(24).fill(0)

    // 各時間帯ごとに合計時間を計算
    for (const hourData of inputArray) {
      for (const timeRange of hourData) {
        const [startTime, endTime] = timeRange
        const [startHour, startMinute] = startTime.split(':').map(Number)
        const [endHour, endMinute] = endTime.split(':').map(Number)

        // 各時間帯内の時間範囲をループ
        for (let hour = startHour; hour <= endHour; hour++) {
          if (hour === startHour) {
            // 開始時刻内の処理
            if (hour === endHour) {
              // 開始時刻と終了時刻が同じ時間帯の場合
              resultArray[hour] += endMinute - startMinute
            } else {
              // 開始時刻と終了時刻が異なる時間帯の場合
              resultArray[hour] += 60 - startMinute
            }
          } else if (hour === endHour) {
            // 終了時刻内の処理
            resultArray[hour] += endMinute
          } else {
            // 開始時刻と終了時刻の間の時間帯の処理
            resultArray[hour] += 60
          }
        }
      }
    }

    return resultArray
  }

  #graph(timeframe) {
    // HTML内のcanvas要素を取得
    const ctx = document.getElementById('openchat-statistics-timeframe').getContext('2d')

    Chart.Tooltip.positioners.tooltipAndLine2 = (items, eventPosition) => {
      const defaultVerticalLine = {
        color: 'black',
        lineWidth: 1,
        setLineDash: [6, 6],
      }

      const pos = Chart.Tooltip.positioners.average(items, eventPosition)
      if (pos === false) return false

      const { x } = pos
      const ctx = this.chart.ctx
      const chartArea = this.chart.chartArea

      if (!isNaN(x) && defaultVerticalLine.lineWidth) {
        ctx.save()
        ctx.lineWidth = defaultVerticalLine.lineWidth
        ctx.strokeStyle = defaultVerticalLine.color
        ctx.setLineDash(defaultVerticalLine.setLineDash)
        ctx.beginPath()
        ctx.moveTo(x, chartArea.bottom)
        ctx.lineTo(x, chartArea.top)
        ctx.stroke()
        ctx.restore()
      }

      return {
        x: pos.x,
        y: 0,
        yAlign: 'bottom',
      }
    }

    // グラフを作成
    return new Chart(ctx, {
      type: 'bar',
      data: {
        labels: Array.from({ length: 24 }, (_, i) => `${i}時`),
        datasets: [
          {
            label: '時間帯別の利用時間 (分)',
            data: timeframe,
            backgroundColor: '#118bee', // 棒の色
            borderColor: '#118bee', // 枠線の色
            borderWidth: 1, // 枠線の幅
            clip: false,
            datalabels: {
              align: 'end',
              anchor: 'end',
            },
          },
        ],
      },
      options: {
        scales: {
          y: {
            beginAtZero: true,
          },
          x: {
            ticks: {
              autoSkip: true,
              maxRotation: 90,
              font: {
                size: 12,
              },
            },
          },
        },
        layout: {
          padding: {
            right: 20,
            top: 0,
          },
        },
        plugins: {
          legend: {
            display: true,
          },
          tooltip: {
            titleFont: {
              size: 12,
            },
            bodyFont: {
              size: 12,
            },
            enabled: true,
            mode: 'index',
            intersect: false,
            position: 'tooltipAndLine2',
            displayColors: false,
            callbacks: {
              label: (tooltipItem) => {
                if (tooltipItem.raw == 0) return '0分'
                return this.#formatTimeCallback(tooltipItem.raw, tooltipItem.dataIndex)
              },
            },
          },
          datalabels: {
            display: false,
          },
        },
      },
    })
  }

  #formatTimeCallback(value, dataIndex) {
    if (value < 0) return ''
    // ラベル表示をhh:mm形式に変換する
    const hours = Math.floor(value / 60)
    const minutes = value % 60
    if (!hours && !minutes) return '0分'

    const tooltip = (hours ? hours + '時間' : '') + (minutes ? minutes + '分' : '')
    return tooltip
  }
}
