import { useEffect, useRef } from 'react'
import { useAtomValue } from 'jotai'
import { Provider } from 'jotai'
import { graphStore } from './state/store'
import ChartLimitBtns from './components/ChartLimitBtns'
import ToggleButtons from './components/ToggleButtons'
import {
  applyAvailabilityFallbacks,
  applyUncategorizedDefaults,
  chart,
  loadingAtom,
  markInitialLoadComplete,
  renderPositionBtnsAtom,
  renderTabAtom,
  setChartStatesFromUrlParams,
  setUrlParamsFromChartStates,
} from './state/chartState'
import { fetchChart, fetchChartData, getChartViewQuery, renderChartData } from './util/fetchRenderer'
import { Box, CircularProgress } from '@mui/material'
import { OcThemeProvider } from '../themeMui'
import { onThemeChange } from './util/theme'
import { t } from './util/translation'

const init = async () => {
  // URLパラメータとローカル設定から表示ビューを組み立て、楽観的に1リクエストで
  // 描画データ+可用性メタデータ(meta=1)を取得する
  setChartStatesFromUrlParams()
  applyUncategorizedDefaults()

  graphStore.set(loadingAtom, true)
  const viewQuery = getChartViewQuery()
  const data = await fetchChartData(true)

  // メタデータ判定でデータが無いビューだった場合のみ、フォールバック先を補正フェッチする
  applyAvailabilityFallbacks()
  if (getChartViewQuery() === viewQuery) {
    renderChartData(data, true)
  } else {
    await fetchChart(true)
  }

  markInitialLoadComplete()
  graphStore.set(renderTabAtom, true)
  setUrlParamsFromChartStates()
}

function LoadingSpinner() {
  return (
    <Box
      className="fade-in"
      sx={{
        position: 'absolute',
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
      }}
    >
      <CircularProgress color="inherit" />
    </Box>
  )
}

function AppInner() {
  const canvas = useRef<null | HTMLCanvasElement>(null)
  const loading = useAtomValue(loadingAtom)
  const renderTab = useAtomValue(renderTabAtom)
  const renderPositionBtns = useAtomValue(renderPositionBtnsAtom)

  useEffect(() => {
    chart.init(canvas.current!)
    init()
    document.getElementById('graph-box')!.style.opacity = '1'
    // ダークモード切替時: 現在のデータのままチャートを再構築（canvas は CSS 変数が効かないため）
    return onThemeChange(() => chart.applyTheme())
  }, [])

  return (
    <div>
      <div className="chart-canvas-box" style={{ position: 'absolute', top: 0, left: 0 }}>
        {loading && <LoadingSpinner />}
        <canvas
          id="chart-preact-canvas"
          ref={canvas}
          aria-label={t('メンバー数・ランキング履歴の統計グラフ')}
          role="img"
        ></canvas>
      </div>
      <div style={{ minHeight: '49px' }}>{renderTab && <ChartLimitBtns />}</div>
      {renderPositionBtns && (
        <div style={{ minHeight: '84px' }}>
          <ToggleButtons />
        </div>
      )}
    </div>
  )
}

export function App() {
  return (
    <Provider store={graphStore}>
      <OcThemeProvider>
        <AppInner />
      </OcThemeProvider>
    </Provider>
  )
}
