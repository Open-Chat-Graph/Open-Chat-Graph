import {
  Chart,
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Title,
} from 'chart.js'
import ChartDataLabels from 'chartjs-plugin-datalabels'

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Title, ChartDataLabels)

interface BandData {
  band_id: number
  band_label: string
  room_count: number
  total_members: number
}

const colors = [
  'rgba(59, 130, 246, 0.7)',
  'rgba(79, 116, 235, 0.7)',
  'rgba(99, 102, 241, 0.7)',
  'rgba(139, 92, 246, 0.7)',
  'rgba(236, 72, 153, 0.7)',
  'rgba(245, 158, 11, 0.7)',
  'rgba(16, 185, 129, 0.7)',
  'rgba(239, 68, 68, 0.7)',
]
const borderColors = colors.map((c) => c.replace('0.7)', '1)'))

const el = document.getElementById('distribution-data')
if (el) {
  const data: BandData[] = JSON.parse(el.textContent ?? '[]')
  const roomCanvas = document.getElementById('distribution-room-chart') as HTMLCanvasElement | null
  const memberCanvas = document.getElementById('distribution-member-chart') as HTMLCanvasElement | null
  if (data.length > 0) {
    if (roomCanvas) renderRoomCountChart(roomCanvas, data)
    if (memberCanvas) renderMemberChart(memberCanvas, data)
  }
}

function renderRoomCountChart(canvas: HTMLCanvasElement, data: BandData[]): void {
  const labels = data.map((d) => d.band_label)
  const values = data.map((d) => d.room_count)

  const isNarrow = window.innerWidth < 640
  const dataLabelFontSize = isNarrow ? 10 : 11
  const xTickFontSize = isNarrow ? 10 : 11

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'ルーム数',
          data: values,
          backgroundColor: colors,
          borderColor: borderColors,
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: '人数帯別ルーム数',
          align: 'start',
          font: { size: 13, weight: 'bold' },
          color: '#374151',
          padding: { bottom: 12 },
        },
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: (items) => items[0].label,
            label: (item) => 'ルーム数: ' + (item.raw as number).toLocaleString() + '部屋',
          },
        },
        datalabels: {
          anchor: 'end',
          align: 'top',
          formatter: (value: number) => value.toLocaleString(),
          font: { size: dataLabelFontSize, weight: 'bold' },
          color: '#374151',
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          suggestedMax: Math.max(...values) * 1.15,
          ticks: {
            callback: (v) => {
              const num = Number(v)
              return num >= 10000 ? num / 10000 + '万' : num.toLocaleString()
            },
          },
          title: { display: false },
        },
        x: {
          ticks: { font: { size: xTickFontSize } },
        },
      },
    },
    plugins: [ChartDataLabels],
  })
}

function renderMemberChart(canvas: HTMLCanvasElement, data: BandData[]): void {
  const labels = data.map((d) => d.band_label)
  const values = data.map((d) => d.total_members)

  const isNarrow = window.innerWidth < 640
  const dataLabelFontSize = isNarrow ? 10 : 11
  const xTickFontSize = isNarrow ? 10 : 11

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: '合計参加者数',
          data: values,
          backgroundColor: colors,
          borderColor: borderColors,
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: '人数帯別 合計参加者数',
          align: 'start',
          font: { size: 13, weight: 'bold' },
          color: '#374151',
          padding: { bottom: 12 },
        },
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: (items) => items[0].label,
            label: (item) => {
              const num = item.raw as number
              return '合計参加者数: ' + (num >= 10000 ? (num / 10000).toFixed(1) + '万' : num.toLocaleString())
            },
          },
        },
        datalabels: {
          anchor: 'end',
          align: 'top',
          formatter: (value: number) => {
            return value >= 10000 ? (value / 10000).toFixed(1) + '万' : value.toLocaleString()
          },
          font: { size: dataLabelFontSize, weight: 'bold' },
          color: '#374151',
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          suggestedMax: Math.max(...values) * 1.15,
          ticks: {
            callback: (v) => {
              const num = Number(v)
              return num >= 10000 ? num / 10000 + '万' : num.toLocaleString()
            },
          },
          title: { display: false },
        },
        x: {
          ticks: { font: { size: xTickFontSize } },
        },
      },
    },
    plugins: [ChartDataLabels],
  })
}
