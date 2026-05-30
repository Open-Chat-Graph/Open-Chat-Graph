import { useEffect } from 'react'

interface ChatArgDto {
  id: number
  baseUrl: string
  categoryName: string
  categoryKey: number
  urlRoot: string
}

interface ThemeConfig {
  theme: string
  isDark: boolean
}

declare global {
  interface Window {
    mountPreactChart?: (chatArgDto?: ChatArgDto, themeConfig?: ThemeConfig) => void
    unmountPreactChart?: () => void
  }
}

// 共有 Preact グラフバンドル。サーバーの public/js/preact-chart に1つだけ存在し、
// /js/preact-chart で配信される（このフロント側に複製は持たない）。
const PREACT_CHART_SRC = '/js/preact-chart/assets/index.js'
const PREACT_CHART_SCRIPT_ID = 'preact-chart-script'
const MOUNT_WAIT_TIMEOUT_MS = 5000

interface PreactChartProps {
  chatId: number
  categoryKey: number
  /** 'light' | 'dark' など解決済みテーマ */
  theme: string
}

/**
 * 外部 Preact 製グラフを React のライフサイクルに載せるラッパー。
 *
 * 旧 DetailPage では script の動的注入・mount/unmount・ポーリングが
 * ページ本体に直書きされていた。それをこのコンポーネントに閉じ込め、
 * DetailPage 側は `<PreactChart … />` を置くだけにする。
 *
 * - バンドルは初回のみ <head> に1度ロードし `window.mountPreactChart` を生やす
 * - theme 変更時はマウントし直す。chatId 変更は呼び出し側の `key` で再マウント
 * - アンマウント時に `window.unmountPreactChart()` を呼ぶ
 */
export function PreactChart({ chatId, categoryKey, theme }: PreactChartProps) {
  // バンドルを初回のみロード
  useEffect(() => {
    if (document.getElementById(PREACT_CHART_SCRIPT_ID)) return

    const script = document.createElement('script')
    script.id = PREACT_CHART_SCRIPT_ID
    script.type = 'module'
    script.src = PREACT_CHART_SRC
    script.async = true
    script.onerror = () => console.error('Failed to load Preact chart script')
    document.head.appendChild(script)
  }, [])

  // マウント関数が利用可能になり次第チャートを描画。離脱時にアンマウント。
  useEffect(() => {
    let cancelled = false

    const chatArgDto: ChatArgDto = {
      id: chatId,
      baseUrl: window.location.origin,
      categoryName: '全て',
      categoryKey,
      urlRoot: '',
    }
    const themeConfig: ThemeConfig = { theme: theme || 'light', isDark: theme === 'dark' }

    const startedAt = Date.now()
    const timer = setInterval(() => {
      if (window.mountPreactChart) {
        clearInterval(timer)
        if (!cancelled) window.mountPreactChart(chatArgDto, themeConfig)
      } else if (Date.now() - startedAt > MOUNT_WAIT_TIMEOUT_MS) {
        clearInterval(timer)
        console.error('Preact chart script failed to load within 5 seconds')
      }
    }, 50)

    return () => {
      cancelled = true
      clearInterval(timer)
      window.unmountPreactChart?.()
    }
  }, [chatId, categoryKey, theme])

  return (
    <div className="max-w-[600px] md:mx-auto">
      <div
        id="graph-box"
        style={{
          position: 'relative',
          marginTop: '1.5rem',
          // 読み込み前の最低限の高さだけ確保（中身に応じて伸びる）。
          // 以前は clamp(400px,50vh,600px) で縦長画面だと最大600px予約され、
          // 中身が短いとグラフ下に巨大な空白が出ていた。
          minHeight: '300px',
        }}
      >
        <div className="chart-canvas-box" id="dummy-canvas"></div>
        <div id="app"></div>
      </div>
    </div>
  )
}
