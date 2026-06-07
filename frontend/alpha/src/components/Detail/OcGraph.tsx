import { useEffect, useRef } from 'react'
import useSWR from 'swr'
import { alphaApi } from '@/api/alpha'
import type { GraphEmbedResponse } from '@/types/api'

interface MountOcGraphOptions {
  container: HTMLElement
  chatArgDto: unknown
  statsDto: unknown
  /** false で location / history を一切読み書きしない（SPA 用）。省略時 true */
  urlSync?: boolean
}

declare global {
  interface Window {
    mountOcGraph?: (opts: MountOcGraphOptions) => void
    unmountOcGraph?: () => void
  }
}

const OC_GRAPH_SCRIPT_ID = 'oc-graph-script'
const MOUNT_WAIT_TIMEOUT_MS = 5000

/* ===== 操作列（グラフ下の in-flow 部分）の予約高 =====
 * 描画完了（opacity 0→1）の瞬間に下の要素がずれないよう、oc-app が実際に描く高さを
 * DTO から事前計算してマウント先 div の min-height に予約する。内訳は oc-app 側
 * (frontend/oc-app/src/graph/App.tsx / ChartLimitBtns.tsx) と対で管理:
 *  - タブ列: MUI Tabs 48 + 下罫線 1 + .limit-btns margin-bottom 8 = 57（常時表示）
 *  - ローソク足トグル行: pt 16 + Chip(small) 24 = 40。hasOhlcData＝statsDto.date が2点以上で表示
 *  - 期間/カテゴリのトグル列(ToggleButtons): 84。ランキング掲載時（categoryKey !== null）のみ
 */
const TABS_ROW_H = 57
const CANDLE_TOGGLE_H = 40
const POSITION_BTNS_H = 84
/** データ未着時の既定値＝最頻ケース（統計2点以上＋ランキング掲載） */
const DEFAULT_CONTROLS_H = TABS_ROW_H + CANDLE_TOGGLE_H + POSITION_BTNS_H // 181

function controlsHeight(data: GraphEmbedResponse | undefined): number {
  if (!data) return DEFAULT_CONTROLS_H
  const dates = (data.statsDto as { date?: unknown[] }).date
  const hasOhlc = Array.isArray(dates) && dates.length > 1
  const listed = (data.chartArgDto as { categoryKey?: number | null }).categoryKey !== null
  return TABS_ROW_H + (hasOhlc ? CANDLE_TOGGLE_H : 0) + (listed ? POSITION_BTNS_H : 0)
}

interface OcGraphProps {
  chatId: number
}

/**
 * oc-app グラフ（本家 /oc/{id} と同一バンドル）を React のライフサイクルに載せるラッパー。
 *
 * - DTO とスクリプトパスは `/alpha-api/oc/{id}/graph-embed` から取得。
 *   scriptPath はサーバー側で glob 解決済みのハッシュ付きパスなので、
 *   デプロイ後も SPA が常に最新ビルドをロードできる
 * - バンドルは固定 id の <script> で1度だけロードし `window.mountOcGraph` を生やす
 *   （ハッシュが変わっていたら差し替えて再ロード）
 * - `urlSync: false` 必須。グラフが ?category= 等を書くと α のルーティングが壊れる
 * - テーマは <html> の data-theme ＋ octhemechange イベント（theme-provider が発火）で
 *   グラフ側が追従するため、テーマ変更での再マウントは不要
 * - chatId 変更は呼び出し側の `key` で再マウント。アンマウント時に `window.unmountOcGraph()`
 */
export function OcGraph({ chatId }: OcGraphProps) {
  const containerRef = useRef<HTMLDivElement>(null)

  const { data, error } = useSWR<GraphEmbedResponse>(
    ['graph-embed', chatId],
    () => alphaApi.getGraphEmbed(chatId),
    { revalidateOnFocus: false, revalidateOnReconnect: false }
  )

  // DTO が揃い次第バンドルをロード→マウント。離脱時にアンマウント。
  useEffect(() => {
    if (!data) return
    let cancelled = false

    // スクリプト注入（固定 id で重複防止。デプロイでハッシュが変わったら差し替え）
    const src = '/' + data.scriptPath
    let script = document.getElementById(OC_GRAPH_SCRIPT_ID) as HTMLScriptElement | null
    if (script && script.getAttribute('src') !== src) {
      script.remove()
      script = null
    }
    if (!script) {
      script = document.createElement('script')
      script.id = OC_GRAPH_SCRIPT_ID
      script.type = 'module'
      script.crossOrigin = ''
      script.src = src
      script.onerror = () => console.error('Failed to load oc-app graph script')
      document.head.appendChild(script)
    }

    // マウント関数が利用可能になり次第チャートを描画
    const startedAt = Date.now()
    const timer = setInterval(() => {
      if (window.mountOcGraph) {
        clearInterval(timer)
        if (!cancelled && containerRef.current) {
          window.mountOcGraph({
            container: containerRef.current,
            chatArgDto: data.chartArgDto,
            statsDto: data.statsDto,
            urlSync: false,
          })
        }
      } else if (Date.now() - startedAt > MOUNT_WAIT_TIMEOUT_MS) {
        clearInterval(timer)
        console.error('oc-app graph script failed to load within 5 seconds')
      }
    }, 50)

    return () => {
      cancelled = true
      clearInterval(timer)
      window.unmountOcGraph?.()
    }
  }, [data])

  if (error) console.error('Graph embed API failed', error)

  return (
    <div className="max-w-[600px] md:mx-auto">
      {/* opacity:0 で開始し、グラフ初期化完了時にスクリプト側が closest('#graph-box') を 1 に上げる */}
      <div
        id="graph-box"
        style={{
          position: 'relative',
          marginTop: '1.5rem',
          opacity: 0,
        }}
      >
        {/* canvas 領域は .chart-canvas-box(ダミー枠)が aspect-ratio で予約
            （実 canvas は React ツリー内の position:absolute な同クラス枠に描画される）。
            操作列はマウント先の min-height（DTO から controlsHeight() で実高を事前計算）で予約。
            両方を事前予約することで描画完了時に下要素がずれない。 */}
        <div className="chart-canvas-box" id="dummy-canvas"></div>
        <div ref={containerRef} style={{ minHeight: controlsHeight(data) }}></div>
      </div>
    </div>
  )
}
