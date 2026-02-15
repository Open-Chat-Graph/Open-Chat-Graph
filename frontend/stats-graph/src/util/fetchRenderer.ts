import {
  categorySignal,
  chart,
  chartModeSignal,
  limitSignal,
  loading,
  rankingRisingSignal,
  updateTabVisibility,
} from '../signal/chartState'
import { setRenderPositionBtns } from '../app'
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
    loading.value = false
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
    loading.value = false
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
  renderMemberChart(true, limitSignal.value as ChartLimit)(statsDto)
}

export async function fetchChart(animation: boolean) {
  if (chartModeSignal.value === 'candlestick') {
    setRenderPositionBtns(true)

    // メンバーOHLCをAPI経由で取得
    loading.value = true
    const memberOhlcData = await fetcher<MemberOhlc[]>(
      `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/member_ohlc`
    )
    chart.memberOhlcApiData = memberOhlcData

    // OHLCデータ数に基づいてタブ表示を更新
    updateTabVisibility(memberOhlcData.length)
    const limit: ChartLimit = limitSignal.value === 25 ? 31 : limitSignal.value

    if (rankingRisingSignal.value !== 'none') {
      const sort = rankingRisingSignal.value
      const category = categorySignal.value === 'all' ? 0 : chatArgDto.categoryKey
      const ohlcData = await fetcher<RankingPositionOhlc[]>(
        `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/position_ohlc?sort=${sort}&category=${category}`
      )
      loading.value = false
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
          category: categorySignal.value === 'all' ? t('すべて') : chatArgDto.categoryName,
          isRising,
        },
        animation,
        limit
      )
    } else {
      loading.value = false
      renderMemberChart(animation, limit)(statsDto)
    }
    return
  }

  // 折れ線グラフモード: statsDto基準でタブ表示を復元
  updateTabVisibility(statsDto.date.length)

  const path: PotisionPath = chart.getIsHour() ? 'position_hour' : 'position'
  const limit: ChartLimit = limitSignal.value === 25 ? 31 : limitSignal.value

  // メンバーグラフのみの場合
  if (rankingRisingSignal.value === 'none') {
    setRenderPositionBtns(true)

    if (chart.getIsHour()) {
      loading.value = true
      await fetcher<RankingPositionChart>(
        `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/${path}?${getApiQuery('ranking', true)}`
      ).then(renderMemberChart(animation, limit))
    } else {
      renderMemberChart(animation, limit)(statsDto)
    }
    return
  }

  const param: ChartApiParam = `${rankingRisingSignal.value}${
    categorySignal.value === 'all' ? '_all' : ''
  }`

  loading.value = true
  await fetcher<RankingPositionChart>(
    `${chatArgDto.baseUrl}/oc/${chatArgDto.id}/${path}?${getApiQuery(param, chart.getIsHour())}`
  ).then((data) => {
    setRenderPositionBtns(true)
    renderChart(param, animation, limit)(data)
  })
}
