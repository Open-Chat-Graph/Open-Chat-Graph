class LiveTalkChartFactory {
  constructor(data, canvas, limit = 31) {
    this.data = data
    this.canvas = canvas
    this.ctx = canvas.getContext('2d')
    this.limit = limit
    this.animation = true
    this.chart = this.createChart()
  }

  update(limit = null) {
    if (limit === null) {
      this.chart.destroy()
      this.animation = false
    } else {
      this.chart.destroy()
      this.animation = true
      this.limit = limit
    }

    this.chart = this.createChart()
  }

  createChart() {
    const noLimit = this.limit === 0
    const config = this.getChartConfig(
      this.limit,
      noLimit ? null : this.getDate(this.limit),
      noLimit ? null : this.getliveTime(this.limit)
    )

    return new Chart(this.canvas, config)
  }

  getDate(limit) {
    const dates = this.data.date.slice(limit * -1)
    if (limit === 7) {
      return this.formatDate(dates)
    } else {
      return this.formatDate(dates, true)
    }
  }

  getliveTime(limit) {
    return this.data.liveTime.slice(limit * -1)
  }

  formatDate(dates, includeWeekday = false) {
    return dates.map((date) => LiveTalkChartFactory.dateFormatter(date, includeWeekday))
  }

  static dateFormatter(date, includeWeekday) {
    const weekdays = ['日', '月', '火', '水', '木', '金', '土']
    const dateArray = date.split('/')
    const pad = (num) => num.toString().padStart(2, '0')

    if (dateArray.length === 2) {
      const [month, day] = dateArray
      const weekdayIndex = new Date(new Date().getFullYear(), month - 1, day).getDay()
      const weekday = weekdays[weekdayIndex]

      if (!includeWeekday) return [`${pad(month)}/${pad(day)}`, `(${weekday})`]
      return `${pad(month)}/${pad(day)}(${weekday})`
    } else {
      const [year, month, day] = dateArray
      const weekdayIndex = new Date(year, month - 1, day).getDay()
      const weekday = weekdays[weekdayIndex]

      if (!includeWeekday) return [`${year}/${pad(month)}/${pad(day)}`, `(${weekday})`]
      return `${year}/${pad(month)}/${pad(day)}(${weekday})`
    }
  }

  static dateFrontColor(date) {
    if (date.includes('土')) {
      return '#44617B'
    } else if (date.includes('日')) {
      return '#9C3848'
    } else {
      return 'black'
    }
  }

  getChartConfig(limit, date = null, liveTime = null) {
    date = date ?? this.data.date
    liveTime = liveTime ?? this.data.liveTime

    const isWeekly = limit === 7
    const windowWidth = window.innerWidth
    const isMiddleMobile = windowWidth <= 375
    const isMiniMobile = windowWidth <= 360
    const isPC = windowWidth >= 512

    const fullSizeWindow = window.innerWidth >= 652
    const aspectRatio = () => (window.innerWidth >= 512 ? 2 / 1 : 1.5 / 1)
    const ticksFontSizeMobile = isMiniMobile ? 11 : 12
    const ticksFontSizeMobile2 = isMiniMobile ? 10.5 : 11
    const ticksFontSize =
      limit === 7
        ? isPC
          ? 13
          : ticksFontSizeMobile
        : limit === 31
          ? isPC
            ? 11.5
            : ticksFontSizeMobile2
          : isPC
            ? 13
            : ticksFontSizeMobile
    const dataFontSize = isPC ? 13 : ticksFontSizeMobile
    const paddingX = isWeekly ? 10 : 0
    const paddingY = isWeekly ? 0 : 10
    const displayY = !isWeekly

    const displayLabel = this.getDisplayLabel(limit)
    const pointRadius = this.getPointRadius(limit)
    const lineTension = 0.4

    const { dataMin, dataMax, stepSize } = this.getliveTimeLabelRange(liveTime)

    Chart.Tooltip.positioners.tooltipAndLine = (items, eventPosition) => {
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

    return {
      type: 'line',
      data: {
        labels: date,
        datasets: [
          {
            label: 'メンバー',
            data: liveTime,
            pointRadius: pointRadius,
            fill: 'start',
            backgroundColor: 'rgba(0,0,0,0)',
            borderColor: '#118bee',
            borderWidth: 3,
            pointColor: '#fff',
            pointBackgroundColor: '#fff',
            pointStrokeColor: '#118bee',
            pointHighlightFill: '#fff',
            pointHighlightStroke: '#118bee',
            lineTension: lineTension,
            clip: false,
            datalabels: {
              align: 'end',
              anchor: 'end',
            },
          },
        ],
      },
      options: {
        animation: this.animation,
        onResize: (chart) => {
          chart.options.aspectRatio = aspectRatio()
          chart.resize()
        },
        layout: {
          padding: {
            right: 20,
            top: 0,
          },
        },
        aspectRatio: aspectRatio(),
        scales: {
          x: {
            ticks: {
              color: this.labelFrontColorCallback,
              padding: paddingX,
              autoSkip: true,
              maxRotation: 90,
              font: {
                size: ticksFontSize,
              },
            },
          },
          y: {
            min: dataMin,
            max: dataMax,
            display: displayY,
            ticks: {
              stepSize: stepSize,
              padding: paddingY,
              font: {
                size: dataFontSize,
              },
              callback: LiveTalkChartFactory.formatTimeCallback2,
            },
          },
        },
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            intersect: false,
            titleFont: {
              size: dataFontSize,
            },
            mode: 'index',
            bodyFont: {
              size: dataFontSize,
            },
            enabled: true,
            displayColors: false,
            callbacks: {
              label: (tooltipItem) => {
                if (tooltipItem.raw == 0) return '0分'
                return this.formatTimeCallback(tooltipItem.raw, tooltipItem.dataIndex)
              },
            },
            position: 'tooltipAndLine',
          },
          datalabels: {
            formatter: LiveTalkChartFactory.formatTimeCallback2,
            clip: false,
            borderRadius: 4,
            display: displayLabel,
            color: 'black',
            backgroundColor: 'rgba(0,0,0,0)',
            font: {
              size: dataFontSize,
              weight: 'bold',
            },
          },
        },
      },
    }
  }

  formatTimeCallback(value, dataIndex) {
    if (value < 0) return ''
    // ラベル表示をhh:mm形式に変換する
    const hours = Math.floor(value / 60)
    const minutes = value % 60
    if (!hours && !minutes) return '0分'

    const liveUser = this.limit ? this.data.liveUser.slice(this.limit * -1) : this.data.liveUser
    const tooltip = [(hours ? hours + '時間' : '') + (minutes ? minutes + '分' : '')]
    return tooltip.concat(liveUser[dataIndex])
  }

  static formatTimeCallback2(value) {
    if (value < 0) return ''

    const hours = Math.floor(value / 60)
    const minutes = value % 60

    return `${hours.toString().padStart(1, '0')}:${minutes.toString().padStart(2, '0')}`
  }

  getliveTimeLabelRange(mem) {
    const fullSizeWindow = window.innerWidth >= 512
    const diffMaxConst = fullSizeWindow ? 0.15 : 0.18
    const diffMinConst = fullSizeWindow ? 0.1 : 0.15
    const diff8Const = fullSizeWindow ? 0.15 : 0.5

    let stepSize = 2
    let maxNum = this.incrementIfOdd(Math.max(...mem))
    let minNum = this.decrementIfOdd(Math.min(...mem))

    let dataDiffMax = this.decrementIfOdd(Math.ceil((maxNum - minNum) * diffMaxConst))
    let dataDiffMin = this.decrementIfOdd(Math.ceil((maxNum - minNum) * diffMinConst))
    let dataDiff8 = this.decrementIfOdd(Math.ceil(dataDiffMax * diff8Const))

    if (dataDiffMax === 0) {
      dataDiffMax = 2
      dataDiff8 = 2
    } else if (dataDiff8 === 0) {
      dataDiff8 = 2
    }

    if (dataDiffMin === 0) dataDiffMin = 2

    const trueDiff = maxNum - minNum
    if (trueDiff >= 50 && this.limit !== 7) {
      maxNum = Math.floor(maxNum / 10) * 10
      minNum = Math.ceil(minNum / 10) * 10
      dataDiffMax = Math.floor(dataDiffMax / 10) * 10
      dataDiffMin = Math.ceil(dataDiffMin / 10) * 10
      if (trueDiff >= 100) stepSize = 10
    }

    let dataMin = 0
    if (this.limit === 7) {
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

  incrementIfOdd(number) {
    if (number % 2 !== 0) {
      return number + 1
    }
    return number
  }

  decrementIfOdd(number) {
    if (number % 2 !== 0) {
      return number - 1
    }
    return number
  }

  getDisplayLabel(limit) {
    if (limit === 7) {
      return true
    }

    return function (context) {
      const index = context.dataIndex
      const dataLength = context.dataset.data.length

      // 最初と最後のデータポイントのみにラベルを表示する
      if (index === 0 || index === dataLength - 1) {
        return 'displayLabel'
      } else {
        return false
      }
    }
  }

  getPointRadius(limit) {
    if (limit === 7) {
      return 3
    }

    return function (context) {
      const index = context.dataIndex
      const dataLength = context.dataset.data.length

      // 最初と最後のデータポイントのみにポイントを表示する
      if (index === 0 || index === dataLength - 1) {
        return 3 // ポイントの半径を設定
      } else {
        return 0 // ポイントを非表示にする
      }
    }
  }

  labelFrontColorCallback(context) {
    let label = context.tick.label
    if (Array.isArray(label)) {
      label = label[1]
    }

    return LiveTalkChartFactory.dateFrontColor(label)
  }

  static verticalLinePlugin() {
    return {
      id: 'verticalLinePlugin',
      beforeEvent: function (chart, args, options) {
        if (!options.display) {
          return
        }

        const e = args.event
        const chartArea = chart.chartArea
        const elements = chart.getElementsAtEventForMode(e, 'nearest', { intersect: false }, true)

        if (
          elements.length > 0 &&
          e.x >= chartArea.left &&
          e.x <= chartArea.right &&
          e.y >= chartArea.top &&
          e.y <= chartArea.bottom
        ) {
          options.x = elements[0].element.x
        } else {
          options.x = NaN
        }
      },
      afterDraw: function (chart, args, options) {
        if (!options.display) {
          return
        }

        const ctx = chart.ctx
        const chartArea = chart.chartArea
        const x = options.x

        if (!isNaN(x)) {
          ctx.save()
          ctx.lineWidth = options.lineWidth || 1
          ctx.strokeStyle = options.color || 'black'
          ctx.setLineDash(options.setLineDash || [])
          ctx.beginPath()
          ctx.moveTo(x, chartArea.bottom)
          ctx.lineTo(x, chartArea.top)
          ctx.stroke()
          ctx.restore()
        }
      },
      defaults: {
        x: NaN,
        lineWidth: 1,
        color: 'black',
        setLineDash: [],
        display: false,
      },
    }
  }
}
