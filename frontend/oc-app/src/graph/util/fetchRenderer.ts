import { graphStore } from '../state/store'
import {
  categoryAtom,
  chart,
  chartModeAtom,
  limitAtom,
  loadingAtom,
  rankingRisingAtom,
  updateCandleTabVisibility,
  updateTabVisibility,
} from '../state/chartState'
import fetcher from './fetcher'
import { t } from './translation'

export const chatArgDto: RankingPositionChartArgDto = JSON.parse(
  (document.getElementById('chart-arg') as HTMLScriptElement).textContent!
)

/**
 * ページHTMLに埋め込まれた可用性メタ(#chart-meta)を読む。
 * 事前計算済みなら ChartMeta、未生成(null/空)なら null（→初回ロードで meta=1 のライブ計算にフォールバック）。
 */
function readEmbeddedChartMeta(): ChartMeta | null {
  const text = document.getElementById('chart-meta')?.textContent?.trim()
  if (!text) return null
  try {
    const parsed = JSON.parse(text)
    return parsed && typeof parsed === 'object' ? (parsed as ChartMeta) : null
  } catch {
    return null
  }
}

// タブ・ボタン出し分け用の可用性メタデータ。ページ埋め込み(#chart-meta)があればそれを採用し、
// 無ければ初回ロードの meta=1 レスポンスで設定される
const embeddedChartMeta = readEmbeddedChartMeta()

/** 初回ロードで meta=1 を撃たずに済むか（埋め込みメタがあるか） */
export const hasEmbeddedChartMeta = embeddedChartMeta !== null

export let chartMeta: ChartMeta = embeddedChartMeta as ChartMeta

export const langCode = chatArgDto.urlRoot.replace(/^\/+/, '') as '' | 'tw' | 'th'

/**
 * 現在のチャート状態が指す表示ビューをAPIクエリ文字列にする。
 * 同一ビューは同一URLになるため、fetcher のURLキャッシュがそのまま効く
 */
export function getChartViewQuery(withMeta = false): string {
  const mode = graphStore.get(chartModeAtom)
  const sort = graphStore.get(rankingRisingAtom)
  const span = mode === 'line' && chart.getIsHour() ? 'hour' : 'day'
  const scope = sort !== 'none' && graphStore.get(categoryAtom) !== 'all' ? 'in' : 'all'

  const query = new URLSearchParams({
    span,
    sort,
    scope,
    category: (chatArgDto.categoryKey ?? 0).toString(),
    mode,
  })
  if (withMeta) query.set('meta', '1')

  return query.toString()
}

export async function fetchChartData(withMeta = false): Promise<ChartResponse> {
  const data = await fetcher<ChartResponse>(
    `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/chart?${getChartViewQuery(withMeta)}`
  )
  if (data.meta) {
    chartMeta = data.meta
  }
  return data
}

const renderPositionChart = (data: ChartResponse, animation: boolean, limit: ChartLimit) => {
  const isRising = graphStore.get(rankingRisingAtom) === 'rising'

  chart.render(
    {
      date: data.date,
      graph1: data.member,
      graph2: data.position,
      time: data.time,
      totalCount: data.totalCount,
    },
    {
      label1: t('メンバー数'),
      label2: isRising ? t('公式急上昇の順位') : t('公式ランキングの順位'),
      category: graphStore.get(categoryAtom) === 'all' ? t('すべて') : chatArgDto.categoryName,
      isRising,
    },
    animation,
    limit
  )
}

const renderMemberChart = (data: ChartResponse, animation: boolean, limit: ChartLimit) => {
  chart.render(
    {
      date: data.date,
      graph1: data.member,
      graph2: [],
      time: [],
      totalCount: [],
    },
    {
      label1: t('メンバー数'),
      label2: '',
      category: chatArgDto.categoryName,
    },
    animation,
    limit
  )
}

const renderCandlestickChart = (data: ChartResponse, animation: boolean, limit: ChartLimit) => {
  const isRising = graphStore.get(rankingRisingAtom) === 'rising'

  chart.render(
    {
      date: data.date,
      graph1: data.member,
      graph2: [],
      time: [],
      totalCount: [],
      rankingOhlc: data.positionOhlc,
    },
    {
      label1: t('メンバー数'),
      label2: isRising ? t('急上昇') : t('ランキング'),
      category: graphStore.get(categoryAtom) === 'all' ? t('すべて') : chatArgDto.categoryName,
      isRising,
    },
    animation,
    limit
  )
}

/** 取得済みのレスポンスを現在のチャート状態に従って描画する */
export function renderChartData(data: ChartResponse, animation: boolean) {
  graphStore.set(loadingAtom, false)

  const currentLimit = graphStore.get(limitAtom)
  const limit: ChartLimit = currentLimit === 25 ? 31 : currentLimit
  const sort = graphStore.get(rankingRisingAtom)

  if (graphStore.get(chartModeAtom) === 'candlestick') {
    chart.memberOhlcApiData = data.memberOhlc ?? []

    // 期間タブ毎のローソク足本数に基づいてタブ表示を更新
    updateCandleTabVisibility(chart.memberOhlcApiData, data.date)

    if (sort === 'none') {
      renderMemberChart(data, animation, limit)
    } else {
      renderCandlestickChart(data, animation, limit)
    }
    return
  }

  // 折れ線グラフモード: 日次データ数に基づいてタブ表示を復元
  updateTabVisibility(chartMeta.dateCount)

  if (sort === 'none') {
    renderMemberChart(data, animation, limit)
  } else {
    renderPositionChart(data, animation, limit)
  }
}

export async function fetchChart(animation: boolean) {
  graphStore.set(loadingAtom, true)
  renderChartData(await fetchChartData(), animation)
}
