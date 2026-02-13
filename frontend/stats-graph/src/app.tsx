import { useEffect, useRef } from 'preact/hooks'
import ChartLimitBtns from './components/ChartLimitBtns'
import ToggleButtons from './components/ToggleButtons'
import { signal } from '@preact/signals'
import {
  chart,
  initDisplay,
  loading,
  setChartStatesFromUrlParams,
  setUrlParamsFromChartStates,
} from './signal/chartState'
import { fetchChart, renderChartWithoutRanking } from './util/fetchRenderer'
import { Box, CircularProgress } from '@mui/material'
import { t } from './util/translation'

const renderTab = signal(false)
const renderPositionBtns = signal(false)

export const setRenderPositionBtns = (toggle: boolean) => {
  renderPositionBtns.value = toggle
}

const init = async () => {
  setChartStatesFromUrlParams()

  if (initDisplay()) {
    await fetchChart(true)
  } else {
    renderChartWithoutRanking()
  }

  renderTab.value = true
  setUrlParamsFromChartStates()
}

function LoadingSpinner() {
  return (
    <Box
      className='fade-in'
      sx={{ position: 'absolute', top: '50%', left: '50%', transform: 'translate(-50%, -50%)' }}
    >
      <CircularProgress color='inherit' />
    </Box>
  )
}

export function App() {
  const canvas = useRef<null | HTMLCanvasElement>(null)

  useEffect(() => {
    chart.init(canvas.current!)
    init()
    document.getElementById('graph-box')!.style.opacity = '1'
  }, [])

  return (
    <div>
      <div class='chart-canvas-box' style={{ position: 'absolute', top: 0, left: 0 }}>
        {loading.value && <LoadingSpinner />}
        <canvas
          id='chart-preact-canvas'
          ref={canvas}
          aria-label={t('メンバー数・ランキキング履歴の統計グラフ')}
          role='img'
        ></canvas>
      </div>
      <div style='min-height: 49px;'>{renderTab.value && <ChartLimitBtns />}</div>
      {renderPositionBtns.value && (
        <div style='min-height: 84px;'>
          <ToggleButtons />
        </div>
      )}
    </div>
  )
}
