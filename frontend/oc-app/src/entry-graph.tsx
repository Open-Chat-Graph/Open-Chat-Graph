import { createRoot, type Root } from 'react-dom/client'
import { App } from './graph/App'
import { setDtos } from './graph/util/fetchRenderer'
import { setUrlSyncEnabled } from './graph/util/urlParam'
import { resetGraphStore } from './graph/state/store'
import { resetChartModuleState } from './graph/state/chartState'
import { clearFetcherCache } from './graph/util/fetcher'

/** α SPA からの埋め込み用オプション */
export type MountOcGraphOptions = {
  /** グラフを描画するコンテナ（PHPページの #app に相当） */
  container: HTMLElement
  chatArgDto: RankingPositionChartArgDto
  statsDto: StatisticsChartDto
  /** false で location / history を一切読み書きしない（SPA 用）。省略時 true */
  urlSync?: boolean
}

declare global {
  interface Window {
    mountOcGraph?: (opts: MountOcGraphOptions) => void
    unmountOcGraph?: () => void
  }
}

let root: Root | null = null

/**
 * グラフを container にマウントする。
 * 再マウント時は前回のモジュールレベル状態（Jotai store / チャート / fetch キャッシュ）を
 * すべて初期化するため、別ルームの DTO への切替が安全にできる
 */
function mountOcGraph(opts: MountOcGraphOptions) {
  // 二重マウント保護（先に既存インスタンスを片付ける）
  unmountOcGraph()

  setDtos(opts.chatArgDto, opts.statsDto)
  setUrlSyncEnabled(opts.urlSync ?? true)
  resetGraphStore()
  clearFetcherCache()

  root = createRoot(opts.container)
  root.render(<App />)
}

/** グラフをアンマウントし、Chart.js インスタンスとルームごとの状態を破棄する */
function unmountOcGraph() {
  if (!root) return
  root.unmount()
  root = null
  resetChartModuleState()
}

window.mountOcGraph = mountOcGraph
window.unmountOcGraph = unmountOcGraph

// PHPページ（oc_content.php）互換の自動マウント:
// #app と JSON タグ（#chart-arg / #stats-dto）が揃っている場合のみ実行。
// α SPA ではタグが無いため何もしない（API の公開だけ行う）
const el = document.getElementById('app')
const argEl = document.getElementById('chart-arg')
const statsEl = document.getElementById('stats-dto')
if (el && argEl?.textContent && statsEl?.textContent) {
  mountOcGraph({
    container: el,
    chatArgDto: JSON.parse(argEl.textContent),
    statsDto: JSON.parse(statsEl.textContent),
  })
}
