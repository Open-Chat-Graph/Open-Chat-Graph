import { graphStore } from '../state/store'
import {
  categoryAtom,
  chart,
  chartModeAtom,
  limitAtom,
  loadingAtom,
  rankingRisingAtom,
  renderPositionBtnsAtom,
  updateTabVisibility,
} from '../state/chartState'
import fetcher from './fetcher'
import { t } from './translation'

export const chatArgDto: RankingPositionChartArgDto = JSON.parse(
  (document.getElementById('chart-arg') as HTMLScriptElement).textContent!
)
export const statsDto: StatisticsChartDto = JSON.parse(
  (document.getElementById('stats-dto') as HTMLScriptElement).textContent!
)

export const langCode = chatArgDto.urlRoot.replace(/^\/+/, '') as '' | 'tw' | 'th'

const getApiQuery = (param: ChartApiParam, isHour: boolean) => {
  const query = {
    sort: '',
    category: '',
    start_date: isHour ? '' : statsDto.startDate,
    end_date: isHour ? '' : statsDto.endDate,
  }

  switch (param) {
    case 'ranking':
      query.sort = 'ranking'
      query.category = chatArgDto.categoryKey?.toString() ?? ''
      break
    case 'ranking_all':
      query.sort = 'ranking'
      query.category = '0'
      break
    case 'rising':
      query.sort = 'rising'
      query.category = chatArgDto.categoryKey?.toString() ?? ''
      break
    case 'rising_all':
      query.sort = 'rising'
      query.category = '0'
  }

  return new URLSearchParams(query).toString()
}

const renderChart =
  (param: ChartApiParam, animation: boolean, limit: ChartLimit) => (data: RankingPositionChart) => {
    graphStore.set(loadingAtom, false)
    const isRising = param === 'rising' || param === 'rising_all'

    chart.render(
      {
        date: data.date.length ? data.date : statsDto.date,
        graph1: data.member.length ? data.member : statsDto.member,
        graph2: data.position,
        time: data.time,
        totalCount: data.totalCount,
      },
      {
        label1: t('メンバー数'),
        label2: isRising ? t('公式急上昇の順位') : t('公式ランキングの順位'),
        category: param.indexOf('all') !== -1 ? t('すべて') : chatArgDto.categoryName,
        isRising,
      },
      animation,
      limit
    )
  }

const renderMemberChart =
  (animation: boolean, limit: ChartLimit) => (data: RankingPositionChart | StatisticsChartDto) => {
    graphStore.set(loadingAtom, false)
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

export function renderChartWithoutRanking() {
  renderMemberChart(true, graphStore.get(limitAtom) as ChartLimit)(statsDto)
}

export async function fetchChart(animation: boolean) {
  if (graphStore.get(chartModeAtom) === 'candlestick') {
    graphStore.set(renderPositionBtnsAtom, true)

    // メンバーOHLCをAPI経由で取得
    graphStore.set(loadingAtom, true)
    const memberOhlcData = await fetcher<MemberOhlc[]>(
      `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/member_ohlc`
    )
    chart.memberOhlcApiData = memberOhlcData

    // OHLCデータ数に基づいてタブ表示を更新
    updateTabVisibility(memberOhlcData.length)
    const currentLimit = graphStore.get(limitAtom)
    const limit: ChartLimit = currentLimit === 25 ? 31 : currentLimit

    if (graphStore.get(rankingRisingAtom) !== 'none') {
      const sort = graphStore.get(rankingRisingAtom)
      const category = graphStore.get(categoryAtom) === 'all' ? 0 : chatArgDto.categoryKey
      const ohlcData = await fetcher<RankingPositionOhlc[]>(
        `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/position_ohlc?sort=${sort}&category=${category}`
      )
      graphStore.set(loadingAtom, false)
      const isRising = sort === 'rising'
      chart.render(
        {
          date: statsDto.date,
          graph1: statsDto.member,
          graph2: [],
          time: [],
          totalCount: [],
          rankingOhlc: ohlcData,
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
    } else {
      graphStore.set(loadingAtom, false)
      renderMemberChart(animation, limit)(statsDto)
    }
    return
  }

  // 折れ線グラフモード: statsDto基準でタブ表示を復元
  updateTabVisibility(statsDto.date.length)

  const path: PotisionPath = chart.getIsHour() ? 'position_hour' : 'position'
  const currentLimit2 = graphStore.get(limitAtom)
  const limit: ChartLimit = currentLimit2 === 25 ? 31 : currentLimit2

  const ranking = graphStore.get(rankingRisingAtom)

  // メンバーグラフのみの場合
  if (ranking === 'none') {
    graphStore.set(renderPositionBtnsAtom, true)

    if (chart.getIsHour()) {
      graphStore.set(loadingAtom, true)
      await fetcher<RankingPositionChart>(
        `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/${path}?${getApiQuery('ranking', true)}`
      ).then(renderMemberChart(animation, limit))
    } else {
      renderMemberChart(animation, limit)(statsDto)
    }
    return
  }

  const param: ChartApiParam = `${ranking}${
    graphStore.get(categoryAtom) === 'all' ? '_all' : ''
  }` as ChartApiParam

  graphStore.set(loadingAtom, true)
  await fetcher<RankingPositionChart>(
    `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/${path}?${getApiQuery(param, chart.getIsHour())}`
  ).then((data) => {
    graphStore.set(renderPositionBtnsAtom, true)
    renderChart(param, animation, limit)(data)
  })
}
